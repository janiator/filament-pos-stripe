<!DOCTYPE html>
<html lang="nb">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Z-Rapport - {{ $session->session_number }}</title>
    <style>
        @include('reports.partials.z-report-styles')
    </style>
</head>
<body>
    @include('reports.partials.z-report-body', ['report' => $report, 'session' => $session])
</body>
</html>
