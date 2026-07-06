<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;

/**
 * Reports hub: a landing page listing every report accessible to the
 * current user's role, adapting dynamically rather than being hardcoded.
 */
class ReportController extends Controller
{
    public function index(): View
    {
        return view('reports.index');
    }
}
