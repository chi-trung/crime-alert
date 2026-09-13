<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): View
    {
        return view('auth.register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            // Issue #160: array-input class of #145/#154/#155. 'string' alone
            // does not stop later rules on an attribute (Validator keeps
            // running them unless one is implicit or 'bail' halts the chain —
            // see Validator::shouldStopValidating), so email[]=a@b.com failed
            // 'string' and then STILL reached 'lowercase' -> Str::lower ->
            // mb_strtolower(array) -> TypeError 'mb_strtolower(): Argument #1
            // ($string) must be of type string, array given' -> 500 on the
            // guest-reachable POST /register. 'bail' after 'string' is what
            // makes the type guard effective. Same fix at
            // ProfileUpdateRequest (PATCH /profile, the second live caller of
            // this rule list); the crash never happens on forgot/reset because
            // their rules omit 'lowercase'.
            'email' => ['required', 'string', 'bail', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            // isAdmin mặc định là 0
        ]);

        event(new Registered($user));

        Auth::login($user);

        return redirect(route('dashboard', absolute: false));
    }
}
