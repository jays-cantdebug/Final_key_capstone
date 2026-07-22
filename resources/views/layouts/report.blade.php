<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        body { font-family: Helvetica, Arial, sans-serif; font-size: 12px; color: #2C2C2A; margin: 24px; }
        h1 { font-size: 18px; margin: 0 0 4px 0; color: #1F6B3A; }
        h2 { font-size: 14px; margin: 20px 0 8px 0; color: #1F6B3A; }
        p.subtitle { font-size: 11px; color: #666666; margin: 0 0 16px 0; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { border: 1px solid #cccccc; padding: 6px 8px; text-align: left; font-size: 11px; vertical-align: top; }
        th { background-color: #EAF3EC; color: #1F6B3A; }
        dl { width: 100%; overflow: hidden; margin: 12px 0; }
        dt { float: left; width: 40%; font-weight: bold; color: #555555; font-size: 11px; padding: 3px 0; }
        dd { float: left; width: 60%; margin: 0; font-size: 11px; padding: 3px 0; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 10px; font-weight: bold; }
        .footer { margin-top: 24px; font-size: 10px; color: #999999; }
        .empty { color: #999999; font-style: italic; }
    </style>
</head>
<body>
    <h1>{{ app(\App\Services\SystemSettingService::class)->systemName() }} &mdash; {{ $title }}</h1>
    <p class="subtitle">{{ app(\App\Services\SystemSettingService::class)->schoolName() }}</p>
    <p class="subtitle">Generated on {{ now()->format('M d, Y g:i A') }}</p>

    {{ $slot }}

    <p class="footer">Student DASS Assessment System &mdash; Confidential Report</p>
</body>
</html>
