<?php

use App\Http\Controllers\AlertController;
use App\Http\Controllers\ChatbotController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExperienceController;
use App\Http\Controllers\LikeController;
use App\Http\Controllers\NewsController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SupportRequestController;
use App\Http\Controllers\WantedListController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', [DashboardController::class, 'index'])->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::get('/alerts/create', [AlertController::class, 'create'])->name('alerts.create');
    // Issue #165: the three fan-out stores #141/#147 left bare. Each POST
    // writes one bell per admin synchronously (alerts/experiences fan
    // NewPostPendingApprovalNotification, /support fans NewSupportRequest),
    // so an unthrottled verified user grows the admin inbox and the
    // notifications table at request speed — probe on pre-fix main: 45
    // rapid POST /support = 45 threads, 45x-admin bells, zero 429s. Each
    // gets its own named lane (see comments.store below for why the third
    // arg matters); 5/min is generous against a human actually writing a
    // post and caps the fan-out multiplier per user.
    Route::post('/alerts', [AlertController::class, 'store'])->middleware('throttle:5,1,alert-create')->name('alerts.store');
    Route::get('/alerts', [AlertController::class, 'index'])->name('alerts.index');
    Route::get('/alerts/map', [AlertController::class, 'mapView'])->name('alerts.map');
    Route::get('/alerts/{alert}', [AlertController::class, 'show'])->name('alerts.show');
    Route::get('/alerts/{alert}/edit', [AlertController::class, 'edit'])->name('alerts.edit');
    // Issue #237: update() is #225's demote-and-re-bell fan-out endpoint, so
    // it gets the same bound store() received in #165 — its own named lane.
    Route::put('/alerts/{alert}', [AlertController::class, 'update'])->middleware('throttle:5,1,alert-update')->name('alerts.update');
    Route::delete('/alerts/{alert}', [AlertController::class, 'destroy'])->name('alerts.destroy');
    Route::middleware('admin')->group(function () {
        Route::get('/admin/alerts', [AlertController::class, 'adminIndex'])->name('admin.alerts');
        Route::post('/admin/alerts/{alert}/approve', [AlertController::class, 'approve'])->name('admin.alerts.approve');
        Route::post('/admin/alerts/{alert}/reject', [AlertController::class, 'reject'])->name('admin.alerts.reject');
        Route::delete('/admin/alerts/{alert}', [AlertController::class, 'destroy'])->name('admin.alerts.destroy');
        Route::get('/admin/alerts/{alert}/edit', [AlertController::class, 'edit'])->name('admin.alerts.edit');
        Route::put('/admin/alerts/{alert}', [AlertController::class, 'update'])->name('admin.alerts.update');
    });
    // Issue #180: second independent password oracle — the current_password
    // rule runs before any state change, so a wrong guess is a clean 302
    // boolean answer with no throttle. Same 5/min + dedicated 'auth-pw-
    // change' lane as /confirm-password; the two lanes never share a bucket
    // with each other or with #147/#165/#179's lanes.
    Route::post('/profile/change-password', [ProfileController::class, 'changePassword'])->middleware('throttle:5,1,auth-pw-change')->name('profile.changePassword');
    // Issue #141: the only unthrottled user-facing write endpoints. Every
    // comment bells the post author and thread parents, every support
    // message bells the admin side — same class of abuse vector #33 put
    // throttle:20,1 on the chatbot for. Per-user keying comes from
    // ThrottleRequests inside the auth group; no RateLimiter::for needed.
    // Issue #147: the third middleware arg is the bucket-key prefix. Without
    // it ThrottleRequests keys by user id alone and EVERY inline throttle in
    // the app shares one counter (probe: 31st comment 429'd support's first
    // message), so each limiter gets its own named lane.
    Route::post('/comments', [CommentController::class, 'store'])->middleware('throttle:30,1,comments')->name('comments.store');
    Route::put('/comments/{comment}', [CommentController::class, 'update'])->name('comments.update');
    Route::delete('/comments/{comment}', [CommentController::class, 'destroy'])->name('comments.destroy');
    Route::get('/comments/{comment}/edit', [CommentController::class, 'edit'])->name('comments.edit');
    Route::get('/experiences/{experience}/edit', [ExperienceController::class, 'edit'])->name('experiences.edit');
    // Issue #237: same reasoning as alerts.update above — ExperienceController
    // ::update carries the #225 approved->pending re-bell fan-out.
    Route::put('/experiences/{experience}', [ExperienceController::class, 'update'])->middleware('throttle:5,1,experience-update')->name('experiences.update');
    Route::delete('/experiences/{experience}', [ExperienceController::class, 'destroy'])->name('experiences.destroy');
    // Issue #147: unlike #141's deferral of this pair, likes are NOT a
    // bounded primitive: store() fires a fresh LikePostNotification on every
    // *insert*, so an alternating like/unlike cycle re-bells the author
    // without limit (probe: 60 cycles -> 60 bell rows, no 429s). One shared
    // 'like' bucket for both routes caps the cycle at 60/min (~30 full
    // cycles) per user. destroy() also has no verified-email gate — the
    // throttle is its only brake.
    Route::post('/like', [LikeController::class, 'store'])->middleware('throttle:60,1,like')->name('like.store');
    Route::post('/like/unlike', [LikeController::class, 'destroy'])->middleware('throttle:60,1,like')->name('like.destroy');
    // Hỗ trợ trực tuyến - user
    Route::get('/support', [SupportRequestController::class, 'index'])->name('support.index');
    Route::get('/support/create', [SupportRequestController::class, 'create'])->name('support.create');
    // Issue #165: unbounded NewSupportRequest fan-out to every admin —
    // same brake as alerts.store above, own 'support-create' lane so it
    // never shares a counter with the sendMessage lane below.
    Route::post('/support', [SupportRequestController::class, 'store'])->middleware('throttle:5,1,support-create')->name('support.store');
    Route::get('/support/{supportRequest}', [SupportRequestController::class, 'show'])->name('support.show');
    // Issue #141: flood of messages per thread bells the counterpart/admins
    // (NewSupportMessage) — same brake as comments.store above.
    // Issue #147: 'support' lane — see comments.store for why every limiter
    // needs its own prefix.
    Route::post('/support/{supportRequest}/message', [SupportRequestController::class, 'sendMessage'])->middleware('throttle:30,1,support')->name('support.sendMessage');
    // Issue #234: the live-chat poll. Every open tab hits this GET every 3
    // seconds; the feed itself is now bounded (delta via after_id + a
    // latest-100 initial window), but the lane still needs a brake — it was
    // the only support endpoint without one. Own named lane per #147: the
    // third throttle arg is mandatory or the counter is shared with every
    // other inline limiter.
    Route::get('/support/{supportRequest}/messages', [SupportRequestController::class, 'messagesAjax'])->middleware('throttle:30,1,support-poll')->name('support.messages');
});

// Hỗ trợ trực tuyến - admin
Route::middleware(['auth', 'admin'])->group(function () {
    Route::get('/admin/support', [SupportRequestController::class, 'adminIndex'])->name('admin.support.index');
    Route::post('/admin/support/{supportRequest}/close', [SupportRequestController::class, 'close'])->name('admin.support.close');
    Route::delete('/admin/support/{supportRequest}', [SupportRequestController::class, 'destroy'])->name('admin.support.destroy');
});

Route::get('/news', [NewsController::class, 'index'])->name('news.index');
Route::view('/fraud-alerts', 'fraud_alerts.index')->name('fraud_alerts.index');
Route::get('/experiences', [ExperienceController::class, 'index'])->name('experiences.index');
Route::get('/experiences/create', [ExperienceController::class, 'create'])->middleware('auth')->name('experiences.create');
// Issue #165: same fan-out class as POST /alerts and POST /support above
// (every pending experience bells every admin) — the auth middleware here
// is route-local because this pair sits outside the auth group, so the
// 'exp-create' lane rides along with it.
Route::post('/experiences', [ExperienceController::class, 'store'])->middleware(['auth', 'throttle:5,1,exp-create'])->name('experiences.store');
Route::get('/experiences/{experience}', [ExperienceController::class, 'show'])->name('experiences.show');
Route::middleware(['auth', 'can:admin'])->group(function () {
    Route::get('/admin/experiences', [ExperienceController::class, 'adminIndex'])->name('admin.experiences');
    Route::post('/admin/experiences/{experience}/approve', [ExperienceController::class, 'approve'])->name('admin.experiences.approve');
    Route::post('/admin/experiences/{experience}/reject', [ExperienceController::class, 'reject'])->name('admin.experiences.reject');
    Route::delete('/admin/experiences/{experience}', [ExperienceController::class, 'destroy'])->name('admin.experiences.destroy');
});
Route::get('/wanted-list', [WantedListController::class, 'index'])->name('wanted_list.index');
Route::get('/my-history', [ProfileController::class, 'myHistory'])->middleware(['auth'])->name('my-history');
Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index')->middleware('auth');
Route::get('/notifications/read/{id}', [NotificationController::class, 'read'])->name('notifications.read')->middleware('auth');
Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.readAll')->middleware('auth');
Route::post('/chatbot/ask', [ChatbotController::class, 'ask'])
    // Issue #147: explicit 'chatbot' lane — the last bare throttle whose
    // counter would otherwise be the old shared user-id bucket.
    ->middleware(['auth', 'throttle:20,1,chatbot'])
    ->name('chatbot.ask');
Route::get('/notifications/unread', [NotificationController::class, 'unreadAjax'])->name('notifications.unread')->middleware('auth');

require __DIR__.'/auth.php';
