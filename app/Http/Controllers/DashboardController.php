<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\DashboardFilterRequest;
use App\Models\Course;
use App\Models\YearLevel;
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
    ) {}

    public function index(Request $request): RedirectResponse
    {
        return redirect()->route($this->dashboardRouteService->resolve($request->user()));
    }

    public function psychometrician(DashboardFilterRequest $request): View
    {
        $filters = $request->validated();

        return view('psychometrician.dashboard', [
            ...$this->dashboardService->psychometricianStats($filters),
            'filters' => $filters,
            'courses' => Course::query()->where('status', Course::STATUS_ACTIVE)->orderBy('course_code')->get(),
            'yearLevels' => YearLevel::query()->where('status', YearLevel::STATUS_ACTIVE)->orderBy('display_order')->get(),
        ]);
    }

    public function guidanceCounselor(): View
    {
        return view('guidance-counselor.dashboard', $this->dashboardService->guidanceCounselorStats());
    }
}
