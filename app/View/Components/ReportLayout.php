<?php

declare(strict_types=1);

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

/**
 * Minimal, dompdf-safe layout for print-optimized report views. Unlike
 * AppLayout, this renders no sidebar/navigation chrome and avoids
 * Tailwind/flexbox, since the same template is also rendered through
 * dompdf for PDF export.
 */
class ReportLayout extends Component
{
    public function __construct(public string $title = 'Report')
    {
    }

    /**
     * Get the view / contents that represents the component.
     */
    public function render(): View
    {
        return view('layouts.report');
    }
}
