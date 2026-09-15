<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PasswordResetLinkController extends Controller
{
    /**
     * Display the password reset link request view.
     */
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    /**
     * Handle an incoming password reset link request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        // We will send the password reset link to this user. Whether the
        // address exists is NOT an answer this form gives: stock Breeze
        // branched on the broker status and flashed passwords.user
        // ("Không tìm thấy người dùng...") for a miss, so the page was a
        // guest-reachable account-enumeration oracle. #179's throttle:6,1
        // caps the rate; the rate was never the defect — the distinct branch
        // was. One uniform "sent" answer (OWASP reset-flow guidance, and
        // this repo's own login doctrine — auth.failed is deliberately
        // identical for unknown email and wrong password) leaves nothing to
        // grind; a real miss costs the sender nothing and the non-existent
        // mailbox never receives anything anyway.
        Password::sendResetLink(
            $request->only('email')
        );

        return back()->with('status', __(Password::RESET_LINK_SENT));
    }
}
