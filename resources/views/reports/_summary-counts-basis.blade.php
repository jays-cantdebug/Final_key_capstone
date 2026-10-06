{{-- Which levels the Assessment Summary Report's severity counts use (see ReportService::assessmentSummaryData()). Shared by the on-screen report and its print/PDF. --}}
@if ($countsBasis === \App\Services\ReportService::COUNTS_BASIS_REVIEWED)
    Counts use the reviewed classification (the Psychometrician's correction where one was made), the same basis as the flag totals.
@else
    Severity counts use the AI's classification before review. The flag totals use the reviewed classification, so they can differ where the Psychometrician corrected a level.
@endif
