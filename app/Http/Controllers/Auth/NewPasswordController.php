<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class NewPasswordController extends Controller
{
    /**
     * Display the password reset view.
     */
    public function create(Request $request): View
    {
        return view('auth.reset-password', ['request' => $request]);
    }

    /**
     * Handle an incoming new password request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            // Issue #159: array-input class of #145/#154/#155. token[]=x
            // passes bare 'required' (Request::filled() is true for
            // non-empty arrays), then Password::reset -> validateReset ->
            // DatabaseTokenRepository::exists() feeds the array to
            // Hash::check -> password_verify(array) -> TypeError
            // 'password_verify(): Argument #1 ($password) must be of type
            // string, array given' -> 500, guest-reachable once any reset
            // row exists (the forgot-password form mints one for any
            // registered email). 'bail' is NOT needed here: unlike #154,
            // no further rule follows 'string' on this attribute, so the
            // failing type rule is the last thing the array touches before
            // the 302/422 response.
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        // Here we will attempt to reset the user's password. If it is successful we
        // will update the password on an actual user model and persist it to the
        // database. Otherwise we will parse the error and return the response.
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user) use ($request) {
                $user->forceFill([
                    'password' => Hash::make($request->password),
                    'remember_token' => Str::random(60),
                ])->save();

                // Issue #123: the same rotation guarantee #27 established for
                // changePassword (ProfileController) — a hijacker holding a
                // live session cookie must not survive the reset. The
                // remember_token regeneration above closes only the
                // remember-me door; with SESSION_DRIVER=database (the default)
                // the attacker's session row carries the victim's user_id and
                // re-authenticates them on their next request. Delete this
                // user's other rows; the requesting session is excluded as in
                // #27 (it is a guest row on this route, so the predicate is
                // belt-and-braces). Other drivers leave the table unused and
                // the delete is a harmless no-op.
                DB::table('sessions')
                    ->where('user_id', $user->id)
                    ->where('id', '!=', $request->session()->getId())
                    ->delete();

                event(new PasswordReset($user));
            }
        );

        // If the password was successfully reset, we will redirect the user back to
        // the application's home authenticated view. If there is an error we can
        // redirect them back to where they came from with their error message.
        return $status == Password::PASSWORD_RESET
                    ? redirect()->route('login')->with('status', __($status))
                    : back()->withInput($request->only('email'))
                        ->withErrors(['email' => __($status)]);
    }
}
