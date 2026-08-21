<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Pripomienka k rezervácii {{ $registration->reservation_number }}</title>
</head>
<body style="font-family: Arial, sans-serif; color: #333; line-height: 1.6;">
    <div style="max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #eaeaea; border-radius: 5px;">
        <h2 style="color: #2b6cb0;">Pripomienka k rezervácii na Beánie EF UMB 2026</h2>

        <p>Dobrý deň, {{ $registration->registrant_name }},</p>

        <p>
            ďakujeme za prihlásenie na Beánie. Vaša rezervácia s číslom
            <strong>{{ $registration->reservation_number }}</strong> zatiaľ nie je dokončená —
            chýba úhrada za {{ $guests->count() === 1 ? 'jedného hosťa' : $guests->count() . ' hostí' }}.
        </p>

        <h3 style="margin-bottom: 8px;">Čaká sa na úhradu za:</h3>
        <ul style="margin-top: 0;">
            @foreach($guests as $guest)
                <li><strong>{{ $guest->name }}</strong></li>
            @endforeach
        </ul>

        @if($deadline)
            <div style="background: #fffaf0; border-left: 4px solid #dd6b20; padding: 12px 16px; margin: 20px 0;">
                <p style="margin: 0;">
                    Rezerváciu, prosím, dokončite <strong>do {{ $deadline->format('j. n. Y') }}</strong>.
                    Po tomto termíne ju musíme uvoľniť pre ďalších záujemcov.
                </p>
            </div>
        @endif

        <p>Ak ste už zaplatili a tento e-mail vám prišiel omylom, dajte nám vedieť odpoveďou na túto správu.</p>

        <p style="margin-top: 28px;">Tešíme sa na vás,<br>organizátori Beánií EF UMB</p>
    </div>
</body>
</html>
