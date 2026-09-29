<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Uvoľnilo sa miesto – rezervácia {{ $registration->reservation_number }}</title>
</head>
<body style="font-family: Arial, sans-serif; color: #333; line-height: 1.6;">
    <div style="max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #eaeaea; border-radius: 5px; overflow-wrap: break-word;">
        <h2 style="color: #2f855a;">Máte miesto na Beánie EF UMB 2026</h2>

        <p>Dobrý deň, {{ $registration->registrant_name }},</p>

        <p>
            uvoľnilo sa miesto, takže vaša registrácia <strong>{{ $registration->reservation_number }}</strong>
            už nie je medzi náhradníkmi — je z nej riadna rezervácia.
        </p>

        <h3 style="margin-bottom: 8px;">Hostia:</h3>
        <ul style="margin-top: 0;">
            @foreach($registration->guests->whereNull('cancelled_at') as $guest)
                <li><strong>{{ $guest->name }}</strong></li>
            @endforeach
        </ul>

        <p><strong>Ďalšie kroky:</strong></p>
        @include('emails.partials.payment')
        <p>Po úhrade vám pridelíme miesta pri stole a vydáme lístky.</p>

        <p>
            @include('emails.partials.reply', [
                'withReply'    => 'Ak už o miesto nemáte záujem, dajte nám vedieť odpoveďou na tento e-mail, nech ho môžeme ponúknuť ďalším.',
                'withoutReply' => 'Ak už o miesto nemáte záujem, dajte vedieť organizátorom, nech ho môžeme ponúknuť ďalším.',
            ])
        </p>

        <p style="margin-top: 28px;">Tešíme sa na vás,<br>organizátori Beánií EF UMB</p>
    </div>
</body>
</html>
