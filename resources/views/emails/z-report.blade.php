<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Z-Rapport</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
    <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
        <h1 style="color: #1a1a1a; border-bottom: 2px solid #e0e0e0; padding-bottom: 10px;">
            Z-Rapport
        </h1>
        
        <p>Hei,</p>
        
        <p>
            En Z-rapport er generert for POS-sesjon <strong>#{{ $session->session_number }}</strong> 
            ved <strong>{{ $store->name }}</strong>.
        </p>
        
        <div style="background-color: #f5f5f5; padding: 15px; border-radius: 5px; margin: 20px 0;">
            <p style="margin: 5px 0;"><strong>Sesjon #:</strong> {{ $session->session_number }}</p>
            <p style="margin: 5px 0;"><strong>Butikk:</strong> {{ $store->name }}</p>
            <p style="margin: 5px 0;"><strong>Åpnet:</strong> {{ $session->opened_at->format('d.m.Y H:i') }}</p>
            <p style="margin: 5px 0;"><strong>Lukket:</strong> {{ $session->closed_at->format('d.m.Y H:i') }}</p>
            <p style="margin: 5px 0;"><strong>Antall transaksjoner:</strong> {{ $session->transaction_count }}</p>
            <p style="margin: 5px 0;"><strong>Totalbeløp:</strong> {{ number_format($session->total_amount / 100, 2, ',', ' ') }} kr</p>
        </div>
        
        <p>
            Z-rapporten er vedlagt som PDF i denne e-posten.
        </p>
        
        <p style="margin-top: 30px; color: #666; font-size: 0.9em;">
            Dette er en automatisk generert e-post fra POS-systemet.
        </p>
    </div>
</body>
</html>
