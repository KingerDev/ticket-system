<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Potvrdenie rezervácie na Beánie</title>
</head>
<body style="font-family: Arial, sans-serif; color: #333; line-height: 1.6;">
    {{-- Zalamovanie dlhých slov: text bez medzier (napr. odkaz) by inak roztiahol e-mail do šírky. --}}
    <div style="max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #eaeaea; border-radius: 5px; word-wrap: break-word; overflow-wrap: break-word; word-break: break-word;">
        <h2 style="color: #2b6cb0;">Potvrdenie rezervácie na Beánie</h2>

        @if($recipient)
            <p>Dobrý deň, {{ $recipient->name }},</p>
            <p>
                {{ $registration->registrant_name }} vás prihlásil(a) na Beánie EF UMB 2026.
                Rezervácia má číslo <strong>{{ $registration->reservation_number }}</strong>.
            </p>
            <h3>Vaše údaje:</h3>
        @else
            <p>Dobrý deň, {{ $registration->registrant_name }},</p>
            <p>Vaša rezervácia s číslom <strong>{{ $registration->reservation_number }}</strong> bola úspešne vytvorená.</p>
            <h3>Zoznam hostí:</h3>
        @endif

        <ul style="padding-left: 20px;">
            @foreach($guests as $guest)
                <li style="margin-bottom: 10px;">
                    <strong>{{ $guest->name }}</strong>
                    @if($guest->allergen_names)
                        <br><span style="color: #e53e3e; font-size: 0.9em;">Alergény: {{ $guest->allergen_names }}</span>
                    @endif
                    @if($guest->diet_label)
                        <br><span style="color: #2f855a; font-size: 0.9em;">Strava: {{ $guest->diet_label }}</span>
                    @endif
                    @if($guest->allergen_note)
                        <br><span style="color: #e53e3e; font-size: 0.9em;">Doplnenie k alergiám: {!! nl2br(e($guest->allergen_note)) !!}</span>
                    @endif
                    @if($guest->note)
                        <br><span style="color: #718096; font-size: 0.9em;">Odkaz pre organizátorov: {!! nl2br(e($guest->note)) !!}</span>
                    @endif
                </li>
            @endforeach
        </ul>

        <hr style="border: none; border-top: 1px solid #eaeaea; margin: 20px 0;">

        <p><strong>Ďalšie kroky:</strong></p>
        @include('emails.partials.payment')
        <p>Po úhrade vám pridelíme miesta pri stole a vydáme lístky.</p>
        @if($recipient)
            <p>
                @include('emails.partials.reply', [
                    'withReply'    => "Ak niečo nesedí, ozvite sa odpoveďou na tento e-mail alebo kontaktujte {$registration->registrant_name}.",
                    'withoutReply' => "Ak niečo nesedí, kontaktujte {$registration->registrant_name} alebo organizátorov.",
                ])
            </p>
        @endif

        <p style="margin-top: 30px; font-size: 0.9em; color: #718096;">
            Tešíme sa na Vás!
        </p>
    </div>
</body>
</html>
