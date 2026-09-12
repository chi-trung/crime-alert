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
    Route::post('/alerts', [AlertController::class, 'store'])->name('alerts.store');
    Route::get('/alerts', [AlertController::class, 'index'])->name('alerts.index');
    Route::get('/alerts/map', [AlertController::class, 'mapView'])->name('alerts.map');
    Route::get('/alerts/{alert}', [AlertController::class, 'show'])->name('alerts.show');
    Route::get('/alerts/{alert}/edit', [AlertController::class, 'edit'])->name('alerts.edit');
    Route::put('/alerts/{alert}', [AlertController::class, 'update'])->name('alerts.update');
    Route::delete('/alerts/{alert}', [AlertController::class, 'destroy'])->name('alerts.destroy');
    Route::middleware('admin')->group(function () {
        Route::get('/admin/alerts', [AlertController::class, 'adminIndex'])->name('admin.alerts');
        Route::post('/admin/alerts/{alert}/approve', [AlertController::class, 'approve'])->name('admin.alerts.approve');
        Route::post('/admin/alerts/{alert}/reject', [AlertController::class, 'reject'])->name('admin.alerts.reject');
        Route::delete('/admin/alerts/{alert}', [AlertController::class, 'destroy'])->name('admin.alerts.destroy');
        Route::get('/admin/alerts/{alert}/edit', [AlertController::class, 'edit'])->name('admin.alerts.edit');
        Route::put('/admin/alerts/{alert}', [AlertController::class, 'update'])->name('admin.alerts.update');
    });
    Route::post('/profile/change-password', [ProfileController::class, 'changePassword'])->name('profile.changePassword');
    Route::post('/comments', [CommentController::class, 'store'])->name('comments.store');
    Route::put('/comments/{comment}', [CommentController::class, 'update'])->name('comments.update');
    Route::delete('/comments/{comment}', [CommentController::class, 'destroy'])->name('comments.destroy');
    Route::get('/comments/{comment}/edit', [CommentController::class, 'edit'])->name('comments.edit');
    Route::get('/experiences/{experience}/edit', [ExperienceController::class, 'edit'])->name('experiences.edit');
    Route::put('/experiences/{experience}', [ExperienceController::class, 'update'])->name('experiences.update');
    Route::delete('/experiences/{experience}', [ExperienceController::class, 'destroy'])->name('experiences.destroy');
    Route::post('/like', [LikeController::class, 'store'])->name('like.store');
    Route::post('/like/unlike', [LikeController::class, 'destroy'])->name('like.destroy');
    // Hỗ trợ trực tuyến - user
    Route::get('/support', [SupportRequestController::class, 'index'])->name('support.index');
    Route::get('/support/create', [SupportRequestController::class, 'create'])->name('support.create');
    Route::post('/support', [SupportRequestController::class, 'store'])->name('support.store');
    Route::get('/support/{supportRequest}', [SupportRequestController::class, 'show'])->name('support.show');
    Route::post('/support/{supportRequest}/message', [SupportRequestController::class, 'sendMessage'])->name('support.sendMessage');
    Route::get('/support/{supportRequest}/messages', [SupportRequestController::class, 'messagesAjax'])->name('support.messages');
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
Route::post('/experiences', [ExperienceController::class, 'store'])->middleware('auth')->name('experiences.store');
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
    ->middleware(['auth', 'throttle:20,1'])
    ->name('chatbot.ask');
Route::get('/notifications/unread', [NotificationController::class, 'unreadAjax'])->name('notifications.unread')->middleware('auth');

require __DIR__.'/auth.php';
