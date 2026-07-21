@props(['counts' => []])

@php
    // Same hex values as the "color" side of the Severity Badge palette
    // (see components/badge.blade.php and reports/print/assessment-summary.blade.php)
    // so the chart bars, badges, and print report all agree visually.
    $colors = [
        'Normal' => '#27500A',
        'Mild' => '#0C447C',
        'Moderate' => '#633806',
        'Severe' => '#712B13',
        'Extremely Severe' => '#791F1F',
    ];

    $values = array_values($counts);
    $max = max(1, ...($values ?: [0]));
    $barAreaHeight = 110;
@endphp

{{-- Table-based bar chart (no SVG, no flexbox/grid) so it renders
     identically in the browser and inside DomPDF's print/PDF output. --}}
<table {{ $attributes->merge(['style' => 'width:100%;border-collapse:collapse;']) }}>
    <tr>
        @foreach ($counts as $severity => $count)
            @php $barHeight = $count > 0 ? max(4, (int) round(($count / $max) * $barAreaHeight)) : 0; @endphp
            <td style="width:20%;height:{{ $barAreaHeight + 20 }}px;text-align:center;vertical-align:bottom;padding:0 4px;">
                <div style="font-size:11px;font-weight:bold;color:#2C2C2A;">{{ $count }}</div>
                <div style="height:{{ $barHeight }}px;background-color:{{ $colors[$severity] ?? '#999999' }};width:28px;margin:2px auto 0;"></div>
            </td>
        @endforeach
    </tr>
    <tr>
        @foreach ($counts as $severity => $count)
            <td style="width:20%;text-align:center;font-size:9px;color:#555555;padding-top:4px;">{{ $severity }}</td>
        @endforeach
    </tr>
</table>
