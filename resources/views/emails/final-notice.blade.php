<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Posledná výzva k rezervácii {{ $registration->reservation_number }}</title>
</head>
<body style="font-family: Arial, sans-serif; color: #333; line-height: 1.6;">
    <div style="max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #eaeaea; border-radius: 5px;">
        <h2 style="color: #c53030;">Posledná výzva — rezervácia {{ $registration->reservation_number }}</h2>

        <p>Dobrý deň, {{ $registration->registrant_name }},</p>

        <p>
            na vašu rezerváciu na Beánie EF UMB 2026 sme vás už raz upozorňovali.
            Úhrada zatiaľ nedorazila.
        </p>

        <h3 style="margin-bottom: 8px;">Stále čakáme na úhradu za:</h3>
        <ul style="margin-top: 0;">
            @foreach($guests as $guest)
                <li><strong>{{ $guest->name }}</strong></li>
            @endforeach
        </ul>

        @if($deadline)
            <div style="background: #fff5f5; border-left: 4px solid #c53030; padding: 12px 16px; margin: 20px 0;">
                <p style="margin: 0;">
                    Ak rezerváciu nedokončíte <strong>do {{ $deadline->format('j. n. Y') }}</strong>,
                    budeme ju musieť <strong>stornovať</strong> a miesta ponúknuť ďalším záujemcom.
                </p>
            </div>
        @endif

        <p>Ak ste medzitým zaplatili alebo potrebujete termín posunúť, ozvite sa nám odpoveďou na túto správu — radi to vyriešime.</p>

        <p style="margin-top: 28px;">Organizátori Beánií EF UMB</p>
    </div>
</body>
</html>
