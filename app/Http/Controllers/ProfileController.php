<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Models\Alert;
use App\Models\Comment;
use App\Models\Experience;
use App\Models\SupportRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            // Issue #154: same array-input class #145 killed for GET filter
            // params. Request::filled() is true for non-empty arrays, so
            // password[]=a passed 'required' and the current_password rule
            // ended at password_verify(array) -> TypeError 'password_verify():
            // Argument #1 ($password) must be of type string, array given' ->
            // 500. 'string' declares the type, but it is 'bail' that matters:
            // Laravel's Validator keeps running later rules on an attribute
            // even after one fails (only implicit-rule failures halt it —
            // Validator::shouldStopValidating), so without bail the
            // current_password rule still receives the array. Together they
            // reject the crafted input with a validation error (302 back /
            // 422 to JSON) before the account delete below can run.
            'password' => ['required', 'string', 'bail', 'current_password'],
        ]);

        $user = $request->user();

        // Issue #53: the FK cascades (#48 and the original alert schema)
        // remove the rows at the database level, which never fires the
        // models' `deleting` events — so the uploaded image/avatar files
        // would survive account deletion. Delete the owned posts through
        // Eloquent first; the cascade stays as the safety net.
        // Issue #57 adds the reason this must be Eloquent, not just a
        // convention: the morph `likes` rows can only be swept by the model
        // hooks, and comments are deleted here too because their DB cascade
        // would strand replies' likes.
        // Issue #115: same class once more — the DB-level user_id cascade on
        // support_requests deletes the departing user's threads without
        // firing SupportRequest::deleting, so the #102 notification sweep
        // never runs and the admin's copy survives as a 404 link. Eloquent
        // first, cascade stays as the safety net.
        Alert::where('user_id', $user->id)->get()->each->delete();
        Experience::where('user_id', $user->id)->get()->each->delete();
        Comment::where('user_id', $user->id)->get()->each->delete();
        SupportRequest::where('user_id', $user->id)->get()->each->delete();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }

    public function changePassword(Request $request)
    {
        $request->validate([
            // Issue #154: see the destroy() comment above — the type guard
            // is 'string', the reason the crash actually goes away is 'bail'
            // (the Validator runs every rule on an attribute even after one
            // fails, so current_password would still see the array). Rejected
            // before the rule runs, the password below is never touched.
            'current_password' => ['required', 'string', 'bail', 'current_password'],
            'new_password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);
        $user = $request->user();
        $user->password = bcrypt($request->new_password);
        // A stolen device holding a "remember me" cookie would silently
        // re-authenticate after its session row is dropped below, defeating
        // the invalidation (issue #27). Nulling the token rejects it.
        $user->remember_token = null;
        $user->save();

        // Issue #204: rotation is the standard response to a suspected leak,
        // so every credential that could undo it must die with it — the
        // sessions below and, per #27/#123's doctrine, any outstanding
        // recovery token. `password_reset_tokens` is keyed by email and
        // nothing here touches it, so a reset link minted BEFORE this
        // change (leaked mailbox, shared device, forwarded email) stayed
        // valid for its full 60-minute TTL and POST /reset-password happily
        // forceFilled over the brand-new password — the rotation was
        // silently undone. Delete the address's rows as part of the
        // rotation; a later forgot-password request re-mints normally
        // because Password::broker()->createToken starts by sweeping its
        // own email's residue anyway.
        DB::table('password_reset_tokens')->where('email', $user->email)->delete();

        // NIST SP 800-63B: changing the password must end all other active
        // sessions. With SESSION_DRIVER=database that's a direct delete of
        // this user's rows minus the requesting one; it works without the
        // AuthenticateSession middleware that Auth::logoutOtherDevices()
        // would otherwise need. On other drivers the table is simply unused,
        // so the delete is a harmless no-op.
        DB::table('sessions')
            ->where('user_id', $user->id)
            ->where('id', '!=', $request->session()->getId())
            ->delete();

        return back()->with('success', 'Đổi mật khẩu thành công!');
    }

    public function myHistory()
    {
        $user = auth()->user();
        // Issue #67: both lists were unbounded ->get() rendered in one page,
        // the read-side twin of #37/#39. Paginate at the same size as the
        // alerts index. The page names keep the two side-by-side tables
        // independent: ?alerts_page= moves only the alerts table, ?exp_page=
        // only the experiences one.
        // Issue #81: id tiebreak for stable page boundaries (see #75).
        $myAlerts = Alert::where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(10, ['*'], 'alerts_page')
            ->withQueryString();
        $myExperiences = Experience::where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(10, ['*'], 'exp_page')
            ->withQueryString();

        return view('profile.my_history', compact('myAlerts', 'myExperiences'));
    }
}
