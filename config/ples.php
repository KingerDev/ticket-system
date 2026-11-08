<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Kde a kedy sa platí
    |--------------------------------------------------------------------------
    |
    | Platí sa len v hotovosti, osobne u organizátorov. Termíny a miesto
    | organizátori zverejnia neskôr – keď ich budú mať, stačí ich sem napísať
    | (napr. „Po–St 10:00–12:00, vestibul EF UMB“) a objavia sa vo formulári
    | aj vo všetkých e-mailoch. Kým je prázdne, texty hovoria, že ich zverejníme.
    |
    */

    'payment_info' => env('PLES_PAYMENT_INFO'),

    /*
    |--------------------------------------------------------------------------
    | Kontakt na organizátorov
    |--------------------------------------------------------------------------
    |
    | Ukáže sa, keď sú rezervované miesta pre učiteľov obsadené. Ak je
    | prázdne, použije sa MAIL_REPLY_TO_ADDRESS; ak ani to, text adresu
    | nespomenie.
    |
    */

    'contact_email' => env('PLES_CONTACT_EMAIL'),

    /*
    |--------------------------------------------------------------------------
    | Uzavretie rezervácií
    |--------------------------------------------------------------------------
    |
    | Kým je zapnuté, verejný aj učiteľský formulár ukazujú len oznam a server
    | nové rezervácie neprijíma. Hostí môže ďalej pridávať administrátor.
    | Znova otvoriť: PLES_REGISTRATION_CLOSED=false.
    |
    */

    'registration_closed' => env('PLES_REGISTRATION_CLOSED', true),

];
