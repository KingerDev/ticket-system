{{--
    „Odpovedzte na tento e-mail“ má zmysel len s nastavenou adresou Reply-To.
    Bez nej by odpoveď skončila na odosielacej adrese, ktorá poštu neprijíma.
--}}
@if(config('mail.reply_to.address'))
    {{ $withReply }}
@else
    {{ $withoutReply ?? 'Ozvite sa, prosím, priamo organizátorom.' }}
@endif
