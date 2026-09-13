<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\ConfirmablePasswordController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\EmailVerificationPromptController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\VerifyEmailController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('register', [RegisteredUserController::class, 'create'])
        ->name('register');

    // Issue #33: these POSTs were unthrottled — account flooding and
    // reset-link email spam. Login keeps its internal LoginRequest
    // limiter (per email+IP) instead of a raw route-level 429.
    // Issue #179: the #33 throttles were bare 'throttle:N,1' with no lane
    // prefix, so for guest requests (no user id) ThrottleRequests keys the
    // bucket by IP alone — register/forgot/reset shared ONE counter and
    // each route tested the shared hits against its own max. Signups from
    // one NAT therefore 429'd everyone behind it out of password recovery.
    // Named lanes give each endpoint the per-endpoint budget #33 intended
    // — the same fix #147/#165 applied to every web.php throttle.
    Route::post('register', [RegisteredUserController::class, 'store'])
        ->middleware('throttle:10,1,auth-register');

    Route::get('login', [AuthenticatedSessionController::class, 'create'])
        ->name('login');

    Route::post('login', [AuthenticatedSessionController::class, 'store']);

    Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])
        ->name('password.request');

    // Issue #179: dedicated 'auth-forgot' lane — see the register note above.
    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])
        ->middleware('throttle:6,1,auth-forgot')
        ->name('password.email');

    Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])
        ->name('password.reset');

    // Issue #179: dedicated 'auth-reset' lane — see the register note above.
    Route::post('reset-password', [NewPasswordController::class, 'store'])
        ->middleware('throttle:6,1,auth-reset')
        ->name('password.store');
});

Route::middleware('auth')->group(function () {
    Route::get('verify-email', EmailVerificationPromptController::class)
        ->name('verification.notice');

    // Issue #179: these two bare throttles shared one per-user bucket, so a
    // user who clicked a verification link 6x had also burned their right
    // to re-send the email. Separate 'auth-verify' / 'auth-verify-send'
    // lanes (these routes are auth'd, so #147's per-user keying already
    // isolated them from the guest lanes — the split is intra-pair).
    Route::get('verify-email/{id}/{hash}', VerifyEmailController::class)
        ->middleware(['signed', 'throttle:6,1,auth-verify'])
        ->name('verification.verify');

    Route::post('email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
        ->middleware('throttle:6,1,auth-verify-send')
        ->name('verification.send');

    Route::get('confirm-password', [ConfirmablePasswordController::class, 'show'])
        ->name('password.confirm');

    // Issue #180: this POST is a correct/incorrect oracle on the account
    // password (Auth::guard('web')->validate) with no limiter, while login
    // deliberately caps at 5 attempts per email|IP — a live session (shared
    // workstation; XSS demonstrated twice in #18/#77) could grind guesses
    // at network speed with no 429, recover the plaintext, and re-login
    // after #27/#63/#123 rotation. 5/min mirrors the login budget; its own
    // lane per #147/#179 so it shares no counter with anything else.
    Route::post('confirm-password', [ConfirmablePasswordController::class, 'store'])
        ->middleware('throttle:5,1,auth-pw-confirm');

    // Issue #63: stock Breeze registered a second password-change endpoint
    // here (PUT /password -> Auth\PasswordController). It updates the hash
    // but skips issue #27's rotation hygiene — neither nulling the remember
    // token nor deleting other session rows — so a hijacker holding a live
    // cookie survives a victim who happens to rotate through that door.
    // The profile UI only ever used the hardened POST /profile/change-
    // password; the route, its controller and the orphaned form partial are
    // removed so there is exactly one door with one set of guarantees.

    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');
});
