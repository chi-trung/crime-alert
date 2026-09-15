<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\GuardsUniqueRaces;
use App\Http\Requests\ProfileUpdateRequest;
use App\Models\Alert;
use App\Models\Comment;
use App\Models\Experience;
use App\Models\SupportRequest;
use App\Models\User;
use App\Support\DeferredFileUnlinks;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ProfileController extends Controller
{
    use GuardsUniqueRaces;

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
        // Issue #253: capture BEFORE fill() — by then the model already holds
        // the new address and the request's comparison would be self-referential.
        $emailChanging = $request->emailIsChanging();

        // Issue #253: validated() now carries current_password for an email
        // change. fill() ignores it because it is not fillable, but drop it
        // explicitly: the profile write must never grow a key the password
        // column could be confused for, whatever $fillable says later.
        $data = $request->validated();
        unset($data['current_password']);
        $request->user()->fill($data);

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        // Issue #253: moving the recovery email is a credential event, so it
        // gets the #27/#123 rotation changePassword() already applies: a
        // stolen device holding a "remember me" cookie would silently
        // re-authenticate after the session sweep and undo the lockout, and
        // every OTHER live session of the account stays valid against an
        // address its owner no longer controls. Set before the save below so
        // one write persists name+email+null token together; on the #209
        // duplicate-key path the assignment never reaches the database.
        if ($emailChanging) {
            $request->user()->remember_token = null;
        }

        // Issue #209: the unique rule in ProfileUpdateRequest is a SELECT,
        // and the UPDATE below was unguarded — the same check-then-act class
        // #205 closed for POST /register (the comment at ProfileUpdateRequest
        // already flagged both callers of the shared rule list). A
        // registration for the target email committing between the check and
        // the write made save() collide users.email's UNIQUE index and the
        // user got a 500 — losing the entire payload, name change included.
        // Convert the raced violation into the validation error the serial
        // duplicate already produces (#43's predicate via the shared trait);
        // the default error bag is what the form's $errors->get('email')
        // renders, so the outcome is byte-identical to the serial path.
        try {
            $request->user()->save();
        } catch (QueryException $e) {
            if (! $this->isDuplicateKey($e)) {
                throw $e;
            }

            throw ValidationException::withMessages([
                'email' => __('validation.unique', ['attribute' => 'email']),
            ]);
        }

        if ($emailChanging) {
            // Issue #253: the rest of the rotation, after the write is
            // durable (a #209 raced failure must not evict anyone). Other
            // sessions die (#27's NIST rule, same delete as changePassword()
            // — a no-op on non-database drivers where the table is unused),
            // and the NEW address's token residue dies with them: #191's
            // recycle doctrine in the opposite direction — a reset link
            // minted while that mailbox pointed elsewhere, or in the window
            // before this change, must not land on the account that just
            // moved in. #203's saving hook already swept the OLD address.
            DB::table('sessions')
                ->where('user_id', $request->user()->id)
                ->where('id', '!=', $request->session()->getId())
                ->delete();

            DB::table('password_reset_tokens')
                ->where('email', $request->user()->email)
                ->delete();
        }

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
        // Issue #266: the four sweeps and the user delete above were five
        // independently autocommitted statements, and each sweep's snapshot
        // SELECT stayed open long after it. Two shapes died here:
        // (1) atomicity — a throw in a late loop (the #102 notification
        // sweep blowing max_allowed_packet on a per-subtree fan-out is the
        // real-world trigger) left the earlier loops' destruction COMMITTED:
        // account alive, its posts, replies and threads permanently gone,
        // images unlinked, owner still logged in to the husk.
        // (2) the window — this request authenticates from the still-live
        // session for its whole duration, and a second tab's POST /alerts
        // could commit after a sweep's snapshot SELECT but before
        // $user->delete(); the FK cascade then destroyed that row WITHOUT
        // firing its model hooks — orphaning its just-stored image,
        // skipping the #57/#115/#121 sweeps, leaving bells on a dead post.
        // One transaction closes (1) — any throw rolls the whole teardown
        // back — and its first statement takes the users row with
        // lockForUpdate() to close (2) as far as a dialect allows: on MySQL
        // the parent-row X lock makes a rival child INSERT wait on its FK
        // check until after the delete, where it dies as an honest FK
        // error instead of cascading event-lessly. On sqlite the lock
        // compiles to a no-op, so each class is additionally swept to a
        // FIXED POINT — the re-query catches any late commit the snapshot
        // missed and destroys it through its own hooks, the outcome the
        // race pins in AccountDeletionRaceTest exercise.
        // Auth::logout() moves after the commit: a torn-down-but-not-yet
        // committed account must not log its owner out of a session whose
        // rows the rollback just restored. #266 also CONCEDED a residual
        // there — disk unlinks already executed inside hooks cannot roll
        // back (rows come back with broken image links, the recoverable
        // half) — and Issue #309 closes exactly that. The ledger arms before
        // the transaction, the #289 current reads capture instead of unlink
        // (WHICH is unchanged), drain after a real commit, discard on throw
        // — rolled-back rows KEEP their files. A plain try/finally cannot
        // tell the two outcomes apart, so drain sits on the success path and
        // discard on the catch; armed is scoped to this method's dynamic
        // extent, which is why every other delete caller keeps unlinking
        // in-hook. If the drain itself throws (disk full), the content is
        // already committed and gone: the rethrow surfaces a 500, and the
        // stale paths are the same recoverable half #309's docblock
        // documents for a rolled-back capture — file on disk, no row.
        // The other residual stands: a rival INSERT the MySQL lock blocks
        // surfaces in the rival tab as an FK error, not a silent cascade.
        DeferredFileUnlinks::arm();

        try {
            DB::transaction(function () use ($user): void {
                // Lock point: SELECT ... FOR UPDATE on the parent row, BEFORE
                // anything else in the transaction can be observed. pluck() so
                // no model is hydrated and no retrieved event can fire.
                User::whereKey($user->id)->lockForUpdate()->pluck('id');

                foreach ([Alert::class, Experience::class, Comment::class, SupportRequest::class] as $class) {
                    do {
                        $rows = $class::where('user_id', $user->id)->get();
                        $rows->each->delete();
                    } while ($rows->isNotEmpty());
                }

                $user->delete();

                // The logout below still holds THIS model instance in the
                // guard, and SessionGuard::logout() cycles the remember token
                // via EloquentUserProvider::updateRememberToken() -> save().
                // After delete() the instance reads exists=false with its key
                // still set, so that save() goes through performInsert() and
                // RESURRECTED the just-deleted user row (verified by trace:
                // the exact regression that pinned this line). Restoring the
                // update-side semantics makes the cycle a 0-row UPDATE against
                // the dead id — correct anyway: the account is gone, there is
                // no token row left to rotate, and any stale cookie now finds
                // no user to authenticate.
                $user->exists = true;
            });
        } catch (\Throwable $e) {
            DeferredFileUnlinks::discard();

            throw $e;
        }

        DeferredFileUnlinks::drain();

        Auth::logout();

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
