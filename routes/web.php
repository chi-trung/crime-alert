<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\AlertController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\WantedListController;
use App\Http\Controllers\NewsController;
use App\Http\Controllers\ExperienceController;
use App\Http\Controllers\NotificationController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ChatbotController;
use App\Http\Controllers\LikeController;
use App\Http\Controllers\SupportRequestController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    $user = auth()->user();
    // Thống kê phân loại cảnh báo cho tất cả user (chỉ lấy cảnh báo đã duyệt)
    $alertsForStats = \App\Models\Alert::where('status', 'approved')->get();
    $alertTypes = ["Cướp giật", "Trộm cắp", "Lừa đảo", "Bạo lực"];
    $typeCountsAdmin = array_fill_keys($alertTypes, 0);
    $typeCountsAdmin['Khác'] = 0;
    foreach ($alertsForStats as $alert) {
        $type = trim($alert->type ?? '');
        if (in_array($type, $alertTypes)) {
            $typeCountsAdmin[$type]++;
        } else {
            $typeCountsAdmin['Khác']++;
        }
    }
    $totalAlertsAll = array_sum($typeCountsAdmin);
    $typePercentsAdmin = [];
    $sum = 0;
    $keys = array_keys($typeCountsAdmin);
    $lastKey = end($keys);
    foreach ($typeCountsAdmin as $type => $count) {
        if ($type === $lastKey) {
            $typePercentsAdmin[$type] = 100 - $sum;
        } else {
            $percent = $totalAlertsAll > 0 ? round($count / $totalAlertsAll * 100) : 0;
            $typePercentsAdmin[$type] = $percent;
            $sum += $percent;
        }
    }
    if ($user->isAdmin) {
        $totalAlerts = \App\Models\Alert::count();
        $pendingAlerts = \App\Models\Alert::where('status', 'pending')->count();
        $approvedAlerts = \App\Models\Alert::where('status', 'approved')->count();
        $rejectedAlerts = \App\Models\Alert::where('status', 'rejected')->count();
        $totalUsers = \App\Models\User::count();
        $latestAlerts = \App\Models\Alert::orderByDesc('created_at')->take(5)->get();
        $latestPending = \App\Models\Alert::where('status', 'pending')->orderByDesc('created_at')->take(5)->get();
        $latestAlert = \App\Models\Alert::orderByDesc('created_at')->first();
        $pendingExperiences = \App\Models\Experience::where('status', 'pending')->orderByDesc('created_at')->get();
        $latestPendingExperience = \App\Models\Experience::where('status', 'pending')->orderByDesc('created_at')->first();
        $latestExperience = \App\Models\Experience::orderByDesc('created_at')->first();
        $latestSupportRequest = \App\Models\SupportRequest::with('user')->latest()->first();

        // Thống kê cảnh báo theo tháng (số liệu thật)
        $currentYear = now()->year;
        $currentMonth = now()->month;
        $lastMonth = $currentMonth == 1 ? 12 : $currentMonth - 1;
        $lastMonthYear = $currentMonth == 1 ? $currentYear - 1 : $currentYear;

        // Tổng cảnh báo tháng này & tháng trước
        $totalAlertsThisMonth = \App\Models\Alert::whereYear('created_at', $currentYear)->whereMonth('created_at', $currentMonth)->count();
        $totalAlertsLastMonth = \App\Models\Alert::whereYear('created_at', $lastMonthYear)->whereMonth('created_at', $lastMonth)->count();
        // Chờ duyệt
        $pendingThisMonth = \App\Models\Alert::where('status', 'pending')->whereYear('created_at', $currentYear)->whereMonth('created_at', $currentMonth)->count();
        $pendingLastMonth = \App\Models\Alert::where('status', 'pending')->whereYear('created_at', $lastMonthYear)->whereMonth('created_at', $lastMonth)->count();
        // Đã duyệt
        $approvedThisMonth = \App\Models\Alert::where('status', 'approved')->whereYear('created_at', $currentYear)->whereMonth('created_at', $currentMonth)->count();
        $approvedLastMonth = \App\Models\Alert::where('status', 'approved')->whereYear('created_at', $lastMonthYear)->whereMonth('created_at', $lastMonth)->count();
        // Từ chối
        $rejectedThisMonth = \App\Models\Alert::where('status', 'rejected')->whereYear('created_at', $currentYear)->whereMonth('created_at', $currentMonth)->count();
        $rejectedLastMonth = \App\Models\Alert::where('status', 'rejected')->whereYear('created_at', $lastMonthYear)->whereMonth('created_at', $lastMonth)->count();
        // Tổng user
        $totalUsersThisMonth = \App\Models\User::whereYear('created_at', $currentYear)->whereMonth('created_at', $currentMonth)->count();
        $totalUsersLastMonth = \App\Models\User::whereYear('created_at', $lastMonthYear)->whereMonth('created_at', $lastMonth)->count();

        // Hàm tính phần trăm thay đổi
        $percentChange = function($current, $last) {
            if ($last == 0) return $current > 0 ? 100 : 0;
            return round((($current - $last) / $last) * 100);
        };
        $totalAlertsPercent = $percentChange($totalAlertsThisMonth, $totalAlertsLastMonth);
        $pendingPercent = $percentChange($pendingThisMonth, $pendingLastMonth);
        $approvedPercent = $percentChange($approvedThisMonth, $approvedLastMonth);
        $rejectedPercent = $percentChange($rejectedThisMonth, $rejectedLastMonth);
        $totalUsersPercent = $percentChange($totalUsersThisMonth, $totalUsersLastMonth);

        // Thống kê cảnh báo theo tháng (số liệu thật)
        $alertsCreated = \App\Models\Alert::selectRaw('MONTH(created_at) as month, COUNT(*) as count')
            ->whereYear('created_at', $currentYear)
            ->groupBy('month')
            ->pluck('count', 'month')
            ->toArray();
        $alertsApproved = \App\Models\Alert::selectRaw('MONTH(created_at) as month, COUNT(*) as count')
            ->where('status', 'approved')
            ->whereYear('created_at', $currentYear)
            ->groupBy('month')
            ->pluck('count', 'month')
            ->toArray();
        $createdData = [];
        $approvedData = [];
        for ($i = 1; $i <= 12; $i++) {
            $createdData[] = $alertsCreated[$i] ?? 0;
            $approvedData[] = $alertsApproved[$i] ?? 0;
        }

        $latestNews = \App\Models\News::orderByDesc('published_at')->orderByDesc('id')->take(3)->get();
        $hotWanted = \App\Models\WantedPerson::orderByDesc('id')->take(3)->get();
        $topExperiences = \App\Models\Experience::where('status', 'approved')
            ->withCount('comments')
            ->orderByDesc('comments_count')
            ->orderByDesc('created_at')
            ->take(3)
            ->get();
        $topAlerts = \App\Models\Alert::where('status', 'approved')
            ->withCount('comments')
            ->orderByDesc('comments_count')
            ->orderByDesc('created_at')
            ->take(3)
            ->get();

        return view('dashboard', [
            'totalAlerts' => $totalAlerts,
            'pendingAlerts' => $pendingAlerts,
            'approvedAlerts' => $approvedAlerts,
            'rejectedAlerts' => $rejectedAlerts,
            'totalUsers' => $totalUsers,
            'latestAlert' => $latestAlert,
            'latestAlerts' => $latestAlerts,
            'latestPending' => $latestPending,
            'createdData' => $createdData,
            'approvedData' => $approvedData,
            'totalAlertsPercent' => $totalAlertsPercent ?? 0,
            'pendingPercent' => $pendingPercent ?? 0,
            'approvedPercent' => $approvedPercent ?? 0,
            'rejectedPercent' => $rejectedPercent ?? 0,
            'totalUsersPercent' => $totalUsersPercent ?? 0,
            'latestNews' => $latestNews,
            'hotWanted' => $hotWanted,
            'topExperiences' => $topExperiences,
            'topAlerts' => $topAlerts,
            'myExperience' => null,
            'pendingExperiences' => $pendingExperiences,
            'latestPendingExperience' => $latestPendingExperience,
            'latestExperience' => $latestExperience,
            'typePercents' => $typePercentsAdmin,
            'typePercentsAdmin' => $typePercentsAdmin,
            'latestSupportRequest' => $latestSupportRequest,
        ]);
    } else {
        $user = auth()->user();
        $currentYear = now()->year;
        $currentMonth = now()->month;
        // Lấy cảnh báo của user/tháng này (CHỈ ĐÃ DUYỆT)
        $myAlertsThisMonth = \App\Models\Alert::where('user_id', $user->id)
            ->where('status', 'approved')
            ->whereYear('created_at', $currentYear)
            ->whereMonth('created_at', $currentMonth)
            ->orderByDesc('created_at')
            ->get();
        // Lấy kinh nghiệm của user/tháng này
        $myExperiencesThisMonth = \App\Models\Experience::where('user_id', $user->id)
            ->whereYear('created_at', $currentYear)
            ->whereMonth('created_at', $currentMonth)
            ->orderByDesc('created_at')
            ->get();
        // Tổng bài viết/tháng này
        $totalPosts = $myAlertsThisMonth->count() + $myExperiencesThisMonth->count();
        // Đã duyệt/tháng này
        $totalApprovedPosts = $myAlertsThisMonth->where('status', 'approved')->count() + $myExperiencesThisMonth->where('status', 'approved')->count();
        // Chuẩn hóa type/tháng này
        $alertTypes = ["Cướp giật", "Trộm cắp", "Lừa đảo", "Bạo lực"];
        $typeCounts = array_fill_keys($alertTypes, 0);
        $typeCounts['Khác'] = 0;
        foreach ($myAlertsThisMonth as $alert) {
            $type = trim($alert->type ?? '');
            if (in_array($type, $alertTypes)) {
                $typeCounts[$type]++;
            } else {
                $typeCounts['Khác']++;
            }
        }
        $myTotal = array_sum($typeCounts);
        $typePercents = [];
        $sum = 0;
        $keys = array_keys($typeCounts);
        $lastKey = end($keys);
        foreach ($typeCounts as $type => $count) {
            if ($type === $lastKey) {
                $typePercents[$type] = 100 - $sum;
            } else {
                $percent = $myTotal > 0 ? round($count / $myTotal * 100) : 0;
                $typePercents[$type] = $percent;
                $sum += $percent;
            }
        }
        $monthLabel = now()->format('m/Y');
        $myExperience = \App\Models\Experience::where('user_id', $user->id)->orderByDesc('created_at')->first();
        $myAlerts = \App\Models\Alert::where('user_id', $user->id)->orderByDesc('created_at')->get();
        $latestSupportRequest = \App\Models\SupportRequest::where('user_id', $user->id)->latest()->first();
        $latestNews = \App\Models\News::orderByDesc('published_at')->orderByDesc('id')->take(3)->get();
        $hotWanted = \App\Models\WantedPerson::orderByDesc('id')->take(3)->get();
        $topExperiences = \App\Models\Experience::where('status', 'approved')
            ->withCount('comments')
            ->orderByDesc('comments_count')
            ->orderByDesc('created_at')
            ->take(3)
            ->get();
        $topAlerts = \App\Models\Alert::where('status', 'approved')
            ->withCount('comments')
            ->orderByDesc('comments_count')
            ->orderByDesc('created_at')
            ->take(3)
            ->get();
        // Cảnh báo mới nhất (toàn bộ của user)
        $myLatest = $myAlerts->first();
        return view('dashboard', [
            'myTotal' => $myTotal,
            'myApproved' => $myAlertsThisMonth->where('status', 'approved')->count(),
            'myLatest' => $myLatest,
            'latestNews' => $latestNews,
            'hotWanted' => $hotWanted,
            'topExperiences' => $topExperiences,
            'topAlerts' => $topAlerts,
            'typePercents' => $typePercentsAdmin,
            'monthLabel' => $monthLabel,
            'myExperience' => $myExperience,
            'myExperiencesThisMonth' => $myExperiencesThisMonth,
            'myAlerts' => $myAlerts,
            'totalPosts' => $totalPosts,
            'totalApprovedPosts' => $totalApprovedPosts,
            'latestSupportRequest' => $latestSupportRequest,
        ]);
    }
})->middleware(['auth', 'verified'])->name('dashboard');

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
    Route::get('/experiences/{experience}/edit', [\App\Http\Controllers\ExperienceController::class, 'edit'])->name('experiences.edit');
    Route::put('/experiences/{experience}', [\App\Http\Controllers\ExperienceController::class, 'update'])->name('experiences.update');
    Route::delete('/experiences/{experience}', [\App\Http\Controllers\ExperienceController::class, 'destroy'])->name('experiences.destroy');
    Route::post('/like', [LikeController::class, 'store'])->name('like.store');
    Route::post('/like/unlike', [LikeController::class, 'destroy'])->name('like.destroy');
    // Hỗ trợ trực tuyến - user
    Route::get('/support', [SupportRequestController::class, 'index'])->name('support.index');
    Route::get('/support/create', [SupportRequestController::class, 'create'])->name('support.create');
    Route::post('/support', [SupportRequestController::class, 'store'])->name('support.store');
    Route::get('/support/{supportRequest}', [SupportRequestController::class, 'show'])->name('support.show');
    Route::post('/support/{supportRequest}/message', [SupportRequestController::class, 'sendMessage'])->name('support.sendMessage');
    Route::get('/support/{supportRequest}/messages', [SupportRequestController::class, 'messagesAjax'])->middleware('auth');
});

// Hỗ trợ trực tuyến - admin
Route::middleware(['auth', 'admin'])->group(function() {
    Route::get('/admin/support', [SupportRequestController::class, 'adminIndex'])->name('admin.support.index');
    Route::post('/admin/support/{supportRequest}/close', [SupportRequestController::class, 'close'])->name('admin.support.close');
    Route::delete('/admin/support/{supportRequest}', [SupportRequestController::class, 'destroy'])->name('admin.support.destroy');
});

Route::get('/test-map', function() {
    return 'Test map route OK';
});

Route::get('/news', [NewsController::class, 'index'])->name('news.index');
Route::view('/fraud-alerts', 'fraud_alerts.index')->name('fraud_alerts.index');
Route::get('/experiences', [ExperienceController::class, 'index'])->name('experiences.index');
Route::get('/experiences/create', [ExperienceController::class, 'create'])->middleware('auth')->name('experiences.create');
Route::post('/experiences', [ExperienceController::class, 'store'])->middleware('auth')->name('experiences.store');
Route::get('/experiences/{experience}', [ExperienceController::class, 'show'])->name('experiences.show');
Route::middleware(['auth', 'can:admin'])->group(function() {
    Route::get('/admin/experiences', [ExperienceController::class, 'adminIndex'])->name('admin.experiences');
    Route::post('/admin/experiences/{experience}/approve', [ExperienceController::class, 'approve'])->name('admin.experiences.approve');
    Route::post('/admin/experiences/{experience}/reject', [ExperienceController::class, 'reject'])->name('admin.experiences.reject');
    Route::delete('/admin/experiences/{experience}', [ExperienceController::class, 'destroy'])->name('admin.experiences.destroy');
});
Route::view('/community-alerts', 'community_alerts.index')->name('community_alerts.index');
Route::get('/wanted-list', [WantedListController::class, 'index'])->name('wanted_list.index');
Route::get('/my-history', [App\Http\Controllers\ProfileController::class, 'myHistory'])->middleware(['auth'])->name('my-history');
Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index')->middleware('auth');
Route::get('/notifications/read/{id}', [NotificationController::class, 'read'])->name('notifications.read')->middleware('auth');
Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.readAll')->middleware('auth');
Route::post('/chatbot/gemini', [ChatbotController::class, 'askGemini'])->name('chatbot.gemini');
Route::post('/chatbot/openai', [App\Http\Controllers\ChatbotController::class, 'askOpenAI'])->name('chatbot.openai');
Route::post('/chatbot/deepseek', [App\Http\Controllers\ChatbotController::class, 'askDeepSeek'])->name('chatbot.deepseek');
Route::post('/chatbot/openrouter', [App\Http\Controllers\ChatbotController::class, 'askOpenRouter'])
    ->middleware('allow.cors')
    ->name('chatbot.openrouter');
Route::get('/notifications/unread', [NotificationController::class, 'unreadAjax'])->name('notifications.unread')->middleware('auth');

require __DIR__.'/auth.php';
