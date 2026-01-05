<!DOCTYPE html>
<html lang="nb">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Z-Rapport - {{ $session->session_number }}</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body style="padding: 20px; background-color: #f9fafb;">
    @include('filament.resources.pos-reports.modals.z-report', ['session' => $session, 'report' => $report])
</body>
</html>




