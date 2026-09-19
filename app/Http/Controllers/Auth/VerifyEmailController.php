<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;

class VerifyEmailController extends Controller
{
    /**
     * Mark the authenticated user's email address as verified.
     */
    public function __invoke(EmailVerificationRequest $request): RedirectResponse
    {
        // Issue #382: the query flag distinguishes the two outcomes, because
        // a user who reopens an old verification link has already verified —
        // congratulating them on that is not feedback, it is a stale
        // notification. ?verified=1 is "you just succeeded"; ?already=1 is
        // "nothing changed".
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->intended(
                route('dashboard', absolute: false).'?already=1'
            );
        }

        if ($request->user()->markEmailAsVerified()) {
            event(new Verified($request->user()));
        }

        return redirect()->intended(
            route('dashboard', absolute: false).'?verified=1'
        );
    }
}
