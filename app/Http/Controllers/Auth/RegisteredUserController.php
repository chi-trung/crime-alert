<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Concerns\GuardsUniqueRaces;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    use GuardsUniqueRaces;

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

        // Issue #205: the `unique:` rule above is a plain SELECT, and the
        // INSERT below is not guarded, so two concurrent registrations for
        // the same email (double-clicked submit — the form button has no
        // disable-once and the 10,1,auth-register lane permits it; two tabs;
        // two devices) both pass validation and the loser dies on the
        // users.email UNIQUE index with an uncaught UniqueConstraintViolation-
        // Exception -> HTTP 500. The window is widened by the ~50-100ms of
        // Hash::make() running between the check and the write. Convert the
        // raced violation into the same validation error the serial duplicate
        // already produces (#43's predicate, engine-agnostic): the loser's
        // browser now shows "email đã có" instead of a server-error page.
        try {
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                // isAdmin mặc định là 0
            ]);
        } catch (QueryException $e) {
            if (! $this->isDuplicateKey($e)) {
                throw $e;
            }

            throw ValidationException::withMessages([
                'email' => __('validation.unique', ['attribute' => 'email']),
            ]);
        }

        event(new Registered($user));

        Auth::login($user);

        return redirect(route('dashboard', absolute: false));
    }
}
