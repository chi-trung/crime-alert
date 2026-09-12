<?php

namespace App\Http\Controllers;

use App\Services\DashboardStatsService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(private readonly DashboardStatsService $stats) {}

    public function index(Request $request): View
    {
        $user = $request->user();

        return view('dashboard', $user->isAdmin
            ? $this->stats->forAdmin()
            : $this->stats->forUser($user));
    }
}
