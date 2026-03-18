<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Potvrdenie rezervácie na Ples</title>
</head>
<body style="font-family: Arial, sans-serif; color: #333; line-height: 1.6;">
    <div style="max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #eaeaea; border-radius: 5px;">
        <h2 style="color: #2b6cb0;">Potvrdenie rezervácie na Ples</h2>
        <p>Dobrý deň, {{ $registration->registrant_name }},</p>
        <p>Vaša rezervácia s číslom <strong>{{ $registration->reservation_number }}</strong> bola úspešne vytvorená.</p>
        
        <h3>Zoznam hostí:</h3>
        <ul>
            @foreach($registration->guests as $guest)
                <li>
                    <strong>{{ $guest->name }}</strong>
                    @if($guest->allergens_display)
                        <br><span style="color: #e53e3e; font-size: 0.9em;">Alergény: {{ $guest->allergens_display }}</span>
                    @endif
                    @if($guest->note)
                        <br><span style="color: #718096; font-size: 0.9em;">Poznámka: {{ $guest->note }}</span>
                    @endif
                </li>
            @endforeach
        </ul>

        <hr style="border: none; border-top: 1px solid #eaeaea; margin: 20px 0;">

        <p><strong>Ďalšie kroky:</strong></p>
        <p>Pre vyzdvihnutie lístkov a pridelenie miest je potrebné prísť vyplatiť rezerváciu osobne.</p>
        
        <p style="margin-top: 30px; font-size: 0.9em; color: #718096;">
            Tešíme sa na Vás!
        </p>
    </div>
</body>
</html>
