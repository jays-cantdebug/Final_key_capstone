<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Auth\DashboardRouteService;
use App\Services\DashboardService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(
        private readonly DashboardRouteService $dashboardRouteService,
        private readonly DashboardService $dashboardService,
    ) {
    }

    public function index(Request $request): RedirectResponse
    {
        return redirect()->route($this->dashboardRouteService->resolve($request->user()));
    }

    public function psychometrician(): View
    {
        return view('psychometrician.dashboard');
    }

    public function guidanceCounselor(): View
    {
        return view('guidance-counselor.dashboard', $this->dashboardService->guidanceCounselorStats());
    }
}