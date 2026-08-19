<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Validačné hlášky
    |--------------------------------------------------------------------------
    |
    | Kompletný slovenský preklad predvolených Laravel hlášok. Bez tohto súboru
    | by sa pri APP_LOCALE=sk zobrazovali holé kľúče (napr. "validation.required").
    |
    */

    'accepted'             => 'Pole :attribute musí byť potvrdené.',
    'accepted_if'          => 'Pole :attribute musí byť potvrdené, ak :other je :value.',
    'active_url'           => 'Pole :attribute neobsahuje platnú URL adresu.',
    'after'                => 'Pole :attribute musí obsahovať dátum po :date.',
    'after_or_equal'       => 'Pole :attribute musí obsahovať dátum :date alebo neskorší.',
    'alpha'                => 'Pole :attribute môže obsahovať iba písmená.',
    'alpha_dash'           => 'Pole :attribute môže obsahovať iba písmená, čísla, pomlčky a podčiarkovníky.',
    'alpha_num'            => 'Pole :attribute môže obsahovať iba písmená a čísla.',
    'any_of'               => 'Pole :attribute je neplatné.',
    'array'                => 'Pole :attribute musí byť pole.',
    'ascii'                => 'Pole :attribute môže obsahovať iba jednobajtové alfanumerické znaky a symboly.',
    'before'               => 'Pole :attribute musí obsahovať dátum pred :date.',
    'before_or_equal'      => 'Pole :attribute musí obsahovať dátum :date alebo skorší.',

    'between' => [
        'array'   => 'Pole :attribute musí obsahovať :min až :max položiek.',
        'file'    => 'Súbor :attribute musí mať veľkosť :min až :max kilobajtov.',
        'numeric' => 'Pole :attribute musí byť medzi :min a :max.',
        'string'  => 'Pole :attribute musí mať :min až :max znakov.',
    ],

    'boolean'              => 'Pole :attribute musí mať hodnotu áno alebo nie.',
    'can'                  => 'Pole :attribute obsahuje nepovolenú hodnotu.',
    'confirmed'            => 'Potvrdenie poľa :attribute nesúhlasí.',
    'contains'             => 'V poli :attribute chýba požadovaná hodnota.',
    'current_password'     => 'Zadané heslo je nesprávne.',
    'date'                 => 'Pole :attribute neobsahuje platný dátum.',
    'date_equals'          => 'Pole :attribute musí obsahovať dátum :date.',
    'date_format'          => 'Pole :attribute nezodpovedá formátu :format.',
    'decimal'              => 'Pole :attribute musí mať :decimal desatinných miest.',
    'declined'             => 'Pole :attribute musí byť odmietnuté.',
    'declined_if'          => 'Pole :attribute musí byť odmietnuté, ak :other je :value.',
    'different'            => 'Polia :attribute a :other sa musia líšiť.',
    'digits'               => 'Pole :attribute musí mať :digits číslic.',
    'digits_between'       => 'Pole :attribute musí mať :min až :max číslic.',
    'dimensions'           => 'Obrázok :attribute má neplatné rozmery.',
    'distinct'             => 'Pole :attribute obsahuje duplicitnú hodnotu.',
    'doesnt_contain'       => 'Pole :attribute nesmie obsahovať žiadnu z týchto hodnôt: :values.',
    'doesnt_end_with'      => 'Pole :attribute nesmie končiť na: :values.',
    'doesnt_start_with'    => 'Pole :attribute nesmie začínať na: :values.',
    'email'                => 'Pole :attribute musí obsahovať platnú e-mailovú adresu.',
    'encoding'             => 'Pole :attribute musí byť v kódovaní :encoding.',
    'ends_with'            => 'Pole :attribute musí končiť na: :values.',
    'enum'                 => 'Vybraná hodnota poľa :attribute je neplatná.',
    'exists'               => 'Vybraná hodnota poľa :attribute je neplatná.',
    'extensions'           => 'Súbor :attribute musí mať jednu z prípon: :values.',
    'file'                 => 'Pole :attribute musí obsahovať súbor.',
    'filled'               => 'Pole :attribute musí byť vyplnené.',

    'gt' => [
        'array'   => 'Pole :attribute musí obsahovať viac než :value položiek.',
        'file'    => 'Súbor :attribute musí byť väčší než :value kilobajtov.',
        'numeric' => 'Pole :attribute musí byť väčšie než :value.',
        'string'  => 'Pole :attribute musí mať viac než :value znakov.',
    ],

    'gte' => [
        'array'   => 'Pole :attribute musí obsahovať aspoň :value položiek.',
        'file'    => 'Súbor :attribute musí mať aspoň :value kilobajtov.',
        'numeric' => 'Pole :attribute musí byť aspoň :value.',
        'string'  => 'Pole :attribute musí mať aspoň :value znakov.',
    ],

    'hex_color'            => 'Pole :attribute musí obsahovať platnú farbu v hexadecimálnom formáte.',
    'image'                => 'Pole :attribute musí obsahovať obrázok.',
    'in'                   => 'Vybraná hodnota poľa :attribute je neplatná.',
    'in_array'             => 'Hodnota poľa :attribute sa nenachádza v :other.',
    'in_array_keys'        => 'Pole :attribute musí obsahovať aspoň jeden z kľúčov: :values.',
    'integer'              => 'Pole :attribute musí byť celé číslo.',
    'ip'                   => 'Pole :attribute musí obsahovať platnú IP adresu.',
    'ipv4'                 => 'Pole :attribute musí obsahovať platnú IPv4 adresu.',
    'ipv6'                 => 'Pole :attribute musí obsahovať platnú IPv6 adresu.',
    'json'                 => 'Pole :attribute musí obsahovať platný JSON reťazec.',
    'list'                 => 'Pole :attribute musí byť zoznam.',
    'lowercase'            => 'Pole :attribute môže obsahovať iba malé písmená.',

    'lt' => [
        'array'   => 'Pole :attribute musí obsahovať menej než :value položiek.',
        'file'    => 'Súbor :attribute musí byť menší než :value kilobajtov.',
        'numeric' => 'Pole :attribute musí byť menšie než :value.',
        'string'  => 'Pole :attribute musí mať menej než :value znakov.',
    ],

    'lte' => [
        'array'   => 'Pole :attribute nesmie obsahovať viac než :value položiek.',
        'file'    => 'Súbor :attribute nesmie mať viac než :value kilobajtov.',
        'numeric' => 'Pole :attribute nesmie byť väčšie než :value.',
        'string'  => 'Pole :attribute nesmie mať viac než :value znakov.',
    ],

    'mac_address'          => 'Pole :attribute musí obsahovať platnú MAC adresu.',

    'max' => [
        'array'   => 'Pole :attribute nesmie obsahovať viac než :max položiek.',
        'file'    => 'Súbor :attribute nesmie mať viac než :max kilobajtov.',
        'numeric' => 'Pole :attribute nesmie byť väčšie než :max.',
        'string'  => 'Pole :attribute nesmie mať viac než :max znakov.',
    ],

    'max_digits'           => 'Pole :attribute nesmie mať viac než :max číslic.',
    'mimes'                => 'Súbor :attribute musí byť typu: :values.',
    'mimetypes'            => 'Súbor :attribute musí byť typu: :values.',

    'min' => [
        'array'   => 'Pole :attribute musí obsahovať aspoň :min položiek.',
        'file'    => 'Súbor :attribute musí mať aspoň :min kilobajtov.',
        'numeric' => 'Pole :attribute musí byť aspoň :min.',
        'string'  => 'Pole :attribute musí mať aspoň :min znakov.',
    ],

    'min_digits'           => 'Pole :attribute musí mať aspoň :min číslic.',
    'missing'              => 'Pole :attribute nesmie byť prítomné.',
    'missing_if'           => 'Pole :attribute nesmie byť prítomné, ak :other je :value.',
    'missing_unless'       => 'Pole :attribute nesmie byť prítomné, pokiaľ :other nie je :value.',
    'missing_with'         => 'Pole :attribute nesmie byť prítomné, ak je zadané :values.',
    'missing_with_all'     => 'Pole :attribute nesmie byť prítomné, ak sú zadané :values.',
    'multiple_of'          => 'Pole :attribute musí byť násobkom :value.',
    'not_in'               => 'Vybraná hodnota poľa :attribute je neplatná.',
    'not_regex'            => 'Formát poľa :attribute je neplatný.',
    'numeric'              => 'Pole :attribute musí byť číslo.',

    'password' => [
        'letters'       => 'Heslo :attribute musí obsahovať aspoň jedno písmeno.',
        'mixed'         => 'Heslo :attribute musí obsahovať veľké aj malé písmeno.',
        'numbers'       => 'Heslo :attribute musí obsahovať aspoň jedno číslo.',
        'symbols'       => 'Heslo :attribute musí obsahovať aspoň jeden špeciálny znak.',
        'uncompromised' => 'Zadané heslo sa objavilo v úniku dát. Zvoľte, prosím, iné heslo.',
    ],

    'present'              => 'Pole :attribute musí byť prítomné.',
    'present_if'           => 'Pole :attribute musí byť prítomné, ak :other je :value.',
    'present_unless'       => 'Pole :attribute musí byť prítomné, pokiaľ :other nie je :value.',
    'present_with'         => 'Pole :attribute musí byť prítomné, ak je zadané :values.',
    'present_with_all'     => 'Pole :attribute musí byť prítomné, ak sú zadané :values.',
    'prohibited'           => 'Pole :attribute je zakázané.',
    'prohibited_if'        => 'Pole :attribute je zakázané, ak :other je :value.',
    'prohibited_if_accepted' => 'Pole :attribute je zakázané, ak je :other potvrdené.',
    'prohibited_if_declined' => 'Pole :attribute je zakázané, ak je :other odmietnuté.',
    'prohibited_unless'    => 'Pole :attribute je zakázané, pokiaľ :other nie je :values.',
    'prohibits'            => 'Pole :attribute znemožňuje zadanie poľa :other.',
    'regex'                => 'Formát poľa :attribute je neplatný.',
    'required'             => 'Pole :attribute je povinné.',
    'required_array_keys'  => 'Pole :attribute musí obsahovať kľúče: :values.',
    'required_if'          => 'Pole :attribute je povinné, ak :other je :value.',
    'required_if_accepted' => 'Pole :attribute je povinné, ak je :other potvrdené.',
    'required_if_declined' => 'Pole :attribute je povinné, ak je :other odmietnuté.',
    'required_unless'      => 'Pole :attribute je povinné, pokiaľ :other nie je :values.',
    'required_with'        => 'Pole :attribute je povinné, ak je zadané :values.',
    'required_with_all'    => 'Pole :attribute je povinné, ak sú zadané :values.',
    'required_without'     => 'Pole :attribute je povinné, ak nie je zadané :values.',
    'required_without_all' => 'Pole :attribute je povinné, ak nie je zadané žiadne z :values.',
    'same'                 => 'Polia :attribute a :other sa musia zhodovať.',

    'size' => [
        'array'   => 'Pole :attribute musí obsahovať :size položiek.',
        'file'    => 'Súbor :attribute musí mať :size kilobajtov.',
        'numeric' => 'Pole :attribute musí mať hodnotu :size.',
        'string'  => 'Pole :attribute musí mať :size znakov.',
    ],

    'starts_with'          => 'Pole :attribute musí začínať na: :values.',
    'string'               => 'Pole :attribute musí byť text.',
    'timezone'             => 'Pole :attribute musí obsahovať platné časové pásmo.',
    'unique'               => 'Hodnota poľa :attribute je už použitá.',
    'uploaded'             => 'Súbor :attribute sa nepodarilo nahrať.',
    'uppercase'            => 'Pole :attribute môže obsahovať iba veľké písmená.',
    'url'                  => 'Pole :attribute musí obsahovať platnú URL adresu.',
    'ulid'                 => 'Pole :attribute musí obsahovať platný ULID.',
    'uuid'                 => 'Pole :attribute musí obsahovať platné UUID.',

    /*
    |--------------------------------------------------------------------------
    | Vlastné validačné hlášky
    |--------------------------------------------------------------------------
    */

    'custom' => [
        'attribute-name' => [
            'rule-name' => 'vlastná hláška',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Slovenské názvy polí
    |--------------------------------------------------------------------------
    |
    | Bez nich by hlášky obsahovali názvy stĺpcov z databázy, napr.
    | "Pole guests.0.allergen_note je povinné."
    |
    */

    'attributes' => [
        'name'                    => 'meno a priezvisko',
        'email'                   => 'e-mail',
        'password'                => 'heslo',
        'password_confirmation'   => 'potvrdenie hesla',
        'current_password'        => 'súčasné heslo',
        'allergen_ids'            => 'alergény',
        'allergen_ids.*'          => 'alergén',
        'is_vegan'                => 'vegán',
        'is_vegetarian'           => 'vegetarián',
        'is_teacher'              => 'učiteľ',
        'allergen_note'           => 'doplnenie k alergiám',
        'note'                    => 'odkaz pre organizátorov',
        'registrant_name'         => 'kontaktná osoba',
        'registrant_email'        => 'kontaktný e-mail',
        'guests'                  => 'hostia',
        'guests.*.name'           => 'meno a priezvisko hosťa',
        'guests.*.email'          => 'e-mail hosťa',
        'guests.*.allergen_ids'   => 'alergény',
        'guests.*.allergen_ids.*' => 'alergén',
        'guests.*.is_vegan'       => 'vegán',
        'guests.*.is_vegetarian'  => 'vegetarián',
        'guests.*.allergen_note'  => 'poznámka k alergénom',
        'guests.*.note'           => 'poznámka',
        'num_rows'                => 'počet radov',
        'tables_per_row'          => 'počet stolov v rade',
        'seats_per_table'         => 'počet miest pri stole',
    ],

];
