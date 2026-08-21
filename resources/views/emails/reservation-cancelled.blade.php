<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Rezervácia {{ $registration->reservation_number }} bola stornovaná</title>
</head>
<body style="font-family: Arial, sans-serif; color: #333; line-height: 1.6;">
    <div style="max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #eaeaea; border-radius: 5px;">
        <h2 style="color: #4a5568;">Rezervácia {{ $registration->reservation_number }} bola stornovaná</h2>

        <p>Dobrý deň, {{ $registration->registrant_name }},</p>

        <p>
            keďže úhrada nedorazila ani po opakovanej výzve, museli sme vašu rezerváciu na
            Beánie EF UMB 2026 stornovať a miesta uvoľniť ďalším záujemcom.
        </p>

        <h3 style="margin-bottom: 8px;">Stornované miesta:</h3>
        <ul style="margin-top: 0;">
            @foreach($guests as $guest)
                <li>{{ $guest->name }}</li>
            @endforeach
        </ul>

        <div style="background: #f7fafc; border-left: 4px solid #4a5568; padding: 12px 16px; margin: 20px 0;">
            <p style="margin: 0;">
                Ak ide o omyl alebo ste platbu poslali v posledný deň, <strong>odpovedzte na tento e-mail</strong>.
                Ak sú ešte voľné miesta, rezerváciu vieme obnoviť.
            </p>
        </div>

        <p>Mrzí nás to a dúfame, že sa uvidíme nabudúce.</p>

        <p style="margin-top: 28px;">Organizátori Beánií EF UMB</p>
    </div>
</body>
</html>
