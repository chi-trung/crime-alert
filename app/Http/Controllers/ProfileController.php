<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Models\Alert;
use App\Models\Experience;
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
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }

    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
            'new_password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);
        $user = $request->user();
        $user->password = bcrypt($request->new_password);
        // A stolen device holding a "remember me" cookie would silently
        // re-authenticate after its session row is dropped below, defeating
        // the invalidation (issue #27). Nulling the token rejects it.
        $user->remember_token = null;
        $user->save();

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
        $myAlerts = Alert::where('user_id', $user->id)->orderByDesc('created_at')->get();
        $myExperiences = Experience::where('user_id', $user->id)->orderByDesc('created_at')->get();

        return view('profile.my_history', compact('myAlerts', 'myExperiences'));
    }
}
