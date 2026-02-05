<!DOCTYPE html>
<html lang="nb">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>X-Rapport - {{ $session->session_number }}</title>
</head>
<body style="padding: 20px; background-color: #f9fafb;">
    @include('filament.resources.pos-reports.modals.x-report', ['session' => $session, 'report' => $report])
</body>
</html>




