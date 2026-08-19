<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Predvolený administrátorský účet
    |--------------------------------------------------------------------------
    |
    | Hodnoty číta `DatabaseSeeder` pri zakladaní prvého účtu. Sú tu, a nie
    | priamo cez env() v seederi, pretože produkčný entrypoint spúšťa
    | `php artisan config:cache` – po ňom env() mimo config/ vracia null.
    |
    | ADMIN_PASSWORD nastav v prostredí kontajnera. Ak ho nenastavíš, seeder
    | pri zakladaní účtu vygeneruje náhodné heslo a raz ho vypíše.
    |
    */

    'name'     => env('ADMIN_NAME', 'Administrátor'),
    'email'    => env('ADMIN_EMAIL', 'admin@ples.sk'),
    'password' => env('ADMIN_PASSWORD'),

];
