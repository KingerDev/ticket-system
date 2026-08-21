<?php

namespace Tests\Feature;

use App\Mail\RegistrationConfirmation;
use App\Models\Registration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** Slovenské preklady a odosielanie potvrdení. */
class LocalizationAndMailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->setLocale('sk');
    }

    public static function prekladoveKluce(): array
    {
        return [
            ['auth.failed'],
            ['auth.password'],
            ['auth.throttle'],
            ['passwords.sent'],
            ['passwords.reset'],
            ['passwords.token'],
            ['passwords.user'],
            ['passwords.throttled'],
            ['validation.required'],
            ['validation.email'],
            ['validation.confirmed'],
            ['validation.unique'],
        ];
    }

    #[DataProvider('prekladoveKluce')]
    public function test_kluc_ma_slovensky_preklad(string $kluc): void
    {
        $preklad = __($kluc);

        $this->assertNotSame($kluc, $preklad, "kľúč {$kluc} nemá slovenský preklad");
        $this->assertIsString($preklad);
    }

    public function test_ziadny_validacny_kluc_nechyba(): void
    {
        $en = require base_path('vendor/laravel/framework/src/Illuminate/Translation/lang/en/validation.php');
        $sk = require base_path('lang/sk/validation.php');

        $splosti = function (array $pole, string $prefix = '') use (&$splosti): array {
            $out = [];
            foreach ($pole as $k => $v) {
                $kluc = $prefix ? "{$prefix}.{$k}" : (string) $k;
                $out = array_merge($out, is_array($v) ? $splosti($v, $kluc) : [$kluc]);
            }
            return $out;
        };

        $chybajuce = array_diff($splosti($en), $splosti($sk));

        $this->assertSame([], array_values($chybajuce), 'chýbajúce preklady: ' . implode(', ', $chybajuce));
    }

    public function test_hlasky_pouzivaju_slovenske_nazvy_poli(): void
    {
        $validator = validator(
            ['guests' => [['name' => 'Jana', 'email' => 'zle']]],
            ['guests.*.email' => 'email', 'guests.*.allergen_ids' => 'array']
        );
        $validator->setData(['guests' => [['email' => 'zle', 'allergen_ids' => 'nie-pole']]]);

        $hlasky = implode(' ', $validator->errors()->all());

        $this->assertStringContainsString('e-mail hosťa', $hlasky);
        $this->assertStringContainsString('alergény', $hlasky);
    }

    public function test_prihlasovacia_hlaska_je_slovenska(): void
    {
        $this->admin(['email' => 'sef@ef.umb.sk']);

        $this->post(route('login'), ['email' => 'sef@ef.umb.sk', 'password' => 'zle']);

        $this->assertStringContainsString(
            'prihlasovacie údaje',
            session('errors')->first('email')
        );
    }

    public function test_potvrdenie_ma_slovensky_predmet_a_adresu_na_odpoved(): void
    {
        config([
            'mail.reply_to.address' => 'organizatori@kinger.dev',
            'mail.reply_to.name'    => 'Organizátori',
        ]);

        $registration = $this->reservation();
        $obalka = (new RegistrationConfirmation($registration))->envelope();

        $this->assertStringContainsString('Beánie', $obalka->subject);
        $this->assertSame('organizatori@kinger.dev', $obalka->replyTo[0]->address);
    }

    public function test_bez_nastavenej_adresy_na_odpoved_sa_hlavicka_nepridava(): void
    {
        config(['mail.reply_to.address' => null]);

        $obalka = (new RegistrationConfirmation($this->reservation()))->envelope();

        $this->assertSame([], $obalka->replyTo);
    }

    public function test_potvrdenie_obsahuje_cislo_rezervacie_a_hosti(): void
    {
        $registration = $this->reservation([
            ['name' => 'Jana Nováková', 'email' => 'jana@email.sk'],
            ['name' => 'Peter Malý'],
        ]);

        $html = (new RegistrationConfirmation($registration))->render();

        $this->assertStringContainsString($registration->reservation_number, $html);
        $this->assertStringContainsString('Jana Nováková', $html);
        $this->assertStringContainsString('Peter Malý', $html);
    }

    public function test_vypadok_odosielania_nezhodi_registraciu(): void
    {
        Mail::shouldReceive('to->queue')->andThrow(new \RuntimeException('SMTP nedostupné'));

        try {
            $this->post(route('register.store'), [
                'guests' => [['name' => 'Jana Nováková', 'email' => 'jana@email.sk']],
            ]);
        } catch (\Throwable) {
            // Aj keby odoslanie vybuchlo, registrácia už musí byť uložená.
        }

        $this->assertSame(1, Registration::count(), 'registrácia sa uloží pred odoslaním e-mailu');
    }
}
