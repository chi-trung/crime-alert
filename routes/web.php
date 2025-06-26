<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\AlertController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\WantedListController;
use App\Http\Controllers\NewsController;
use App\Http\Controllers\ExperienceController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    $user = auth()->user();
    if ($user->isAdmin) {
        $totalAlerts = \App\Models\Alert::count();
        $pendingAlerts = \App\Models\Alert::where('status', 'pending')->count();
        $approvedAlerts = \App\Models\Alert::where('status', 'approved')->count();
        $rejectedAlerts = \App\Models\Alert::where('status', 'rejected')->count();
        $totalUsers = \App\Models\User::count();
        $latestAlerts = \App\Models\Alert::orderByDesc('created_at')->take(5)->get();
        $latestPending = \App\Models\Alert::where('status', 'pending')->orderByDesc('created_at')->take(5)->get();
        $latestAlert = \App\Models\Alert::orderByDesc('created_at')->first();

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
        ]);
    } else {
        $myAlerts = \App\Models\Alert::where('user_id', $user->id)->get();
        $myApproved = $myAlerts->where('status', 'approved')->count();
        $myTotal = $myAlerts->count();
        $myLatest = $myAlerts->sortByDesc('created_at')->first();
        return view('dashboard', [
            'myTotal' => $myTotal,
            'myApproved' => $myApproved,
            'myLatest' => $myLatest
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
    Route::delete('/admin/experiences/{experience}', [ExperienceController::class, 'destroy'])->name('admin.experiences.destroy');
});
Route::view('/community-alerts', 'community_alerts.index')->name('community_alerts.index');
Route::get('/wanted-list', [WantedListController::class, 'index'])->name('wanted_list.index');

require __DIR__.'/auth.php';
