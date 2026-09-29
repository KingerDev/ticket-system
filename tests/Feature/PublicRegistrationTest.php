<?php

namespace Tests\Feature;

use App\Mail\RegistrationConfirmation;
use App\Models\Guest;
use App\Models\Registration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** Verejný registračný formulár – jediná časť, ktorú vidí široká verejnosť. */
class PublicRegistrationTest extends TestCase
{
    use RefreshDatabase;

    private function guest(array $overrides = []): array
    {
        return array_merge([
            'name'           => 'Jana Nováková',
            'email'          => 'jana@email.sk',
            'allergen_ids'   => [1, 7],
            'is_vegan'       => false,
            'is_vegetarian'  => true,
            'allergen_note'  => 'silná alergia na orechy',
            'note'           => 'chceme sedieť spolu',
        ], $overrides);
    }

    public function test_uvodna_stranka_presmeruje_na_registraciu(): void
    {
        $this->get('/')->assertRedirect(route('register'));
    }

    public function test_formular_sa_zobrazi_bez_prihlasenia(): void
    {
        $this->get(route('register'))->assertOk();
    }

    public function test_registracia_ulozi_hostov_aj_ich_udaje(): void
    {
        Mail::fake();

        $response = $this->post(route('register.store'), [
            'guests' => [
                $this->guest(),
                $this->guest(['name' => 'Peter Malý', 'email' => '', 'allergen_ids' => [], 'is_vegan' => true, 'is_vegetarian' => false]),
            ],
        ]);

        $response->assertRedirect(route('register.success'));

        $this->assertSame(1, Registration::count());
        $this->assertSame(2, Guest::count());

        $registration = Registration::first();
        $this->assertSame('PLES-0001', $registration->reservation_number);
        $this->assertSame('jana@email.sk', $registration->registrant_email);

        $jana = Guest::where('name', 'Jana Nováková')->first();
        $this->assertSame([1, 7], $jana->allergen_ids);
        $this->assertTrue($jana->is_vegetarian);
        $this->assertFalse($jana->is_vegan);
        $this->assertSame('silná alergia na orechy', $jana->allergen_note);

        // Hosť bez e-mailu je v poriadku, ak nie je prvý.
        $peter = Guest::where('name', 'Peter Malý')->first();
        $this->assertNull($peter->email);
        $this->assertTrue($peter->is_vegan);
    }

    public function test_cisla_rezervacii_idu_za_sebou(): void
    {
        Mail::fake();

        foreach (['Jana Nováková', 'Peter Malý', 'Eva Krátka'] as $name) {
            $this->post(route('register.store'), ['guests' => [$this->guest(['name' => $name])]]);
        }

        $this->assertSame(
            ['PLES-0001', 'PLES-0002', 'PLES-0003'],
            Registration::orderBy('id')->pluck('reservation_number')->all()
        );
    }

    public function test_potvrdenie_sa_zaradi_do_fronty_a_neodosiela_synchronne(): void
    {
        Mail::fake();

        $this->post(route('register.store'), ['guests' => [$this->guest()]]);

        Mail::assertQueued(RegistrationConfirmation::class, fn ($mail) => $mail->hasTo('jana@email.sk'));
        Mail::assertNotSent(RegistrationConfirmation::class);
    }

    public function test_meno_je_povinne(): void
    {
        $this->post(route('register.store'), ['guests' => [$this->guest(['name' => ''])]])
            ->assertSessionHasErrors('guests.0.name');

        $this->assertSame(0, Registration::count());
    }

    public function test_samotne_krstne_meno_neprejde(): void
    {
        $this->post(route('register.store'), ['guests' => [$this->guest(['name' => 'Jana'])]])
            ->assertSessionHasErrors(['guests.0.name' => 'Zadajte meno aj priezvisko (napr. Jana Nováková).']);
    }

    public function test_meno_s_diakritikou_a_viacerymi_slovami_prejde(): void
    {
        Mail::fake();

        $this->post(route('register.store'), ['guests' => [$this->guest(['name' => 'Ján Ľuboš Kováč-Novák'])]])
            ->assertSessionHasNoErrors();
    }

    public function test_neplatny_email_neprejde(): void
    {
        $this->post(route('register.store'), ['guests' => [$this->guest(['email' => 'toto-nie-je-email'])]])
            ->assertSessionHasErrors('guests.0.email');
    }

    public function test_alergen_mimo_rozsahu_neprejde(): void
    {
        $this->post(route('register.store'), ['guests' => [$this->guest(['allergen_ids' => [99]])]])
            ->assertSessionHasErrors('guests.0.allergen_ids.0');
    }

    public function test_prilis_dlha_poznamka_neprejde(): void
    {
        $this->post(route('register.store'), ['guests' => [$this->guest(['note' => str_repeat('x', 1001)])]])
            ->assertSessionHasErrors('guests.0.note');
    }

    public function test_prazdny_zoznam_hosti_neprejde(): void
    {
        $this->post(route('register.store'), ['guests' => []])
            ->assertSessionHasErrors('guests');
    }

    public function test_hlasky_su_slovenske(): void
    {
        $this->app->setLocale('sk');

        $response = $this->post(route('register.store'), ['guests' => [$this->guest(['email' => 'zle'])]]);

        $errors = session('errors')->get('guests.0.email');
        $this->assertStringContainsString('platnú e-mailovú adresu', $errors[0]);
    }

    public function test_cislo_rezervacie_nekoliduje_po_zmazani(): void
    {
        Mail::fake();

        // Dve registrácie, prostrednú admin zmaže – ďalšia nesmie dostať to isté číslo.
        $this->post(route('register.store'), ['guests' => [$this->guest(['name' => 'Jana Nováková'])]]);
        $this->post(route('register.store'), ['guests' => [$this->guest(['name' => 'Peter Malý'])]]);

        Registration::where('reservation_number', 'PLES-0001')->first()->delete();

        $this->assertSame(1, Registration::count());

        $this->post(route('register.store'), ['guests' => [$this->guest(['name' => 'Eva Krátka'])]])
            ->assertRedirect(route('register.success'));

        $this->assertSame(2, Registration::count(), 'tretia registrácia sa musí uložiť');
        $this->assertSame(
            2,
            Registration::distinct('reservation_number')->count('reservation_number'),
            'čísla rezervácií sa nesmú opakovať'
        );
    }

    /** @dataProvider neuplneEmaily */
    public function test_email_bez_domeny_neprejde(string $email): void
    {
        $this->post(route('register.store'), ['guests' => [$this->guest(['email' => $email])]])
            ->assertSessionHasErrors('guests.0.email');

        $this->assertSame(0, Registration::count());
    }

    public static function neuplneEmaily(): array
    {
        return [
            'bez koncovky' => ['jana@gmail'],
            'bez domény'   => ['x@y'],
            'krátka koncovka' => ['jana@email.s'],
        ];
    }

    public function test_email_kontaktnej_osoby_je_povinny(): void
    {
        $this->post(route('register.store'), ['guests' => [$this->guest(['email' => ''])]])
            ->assertSessionHasErrors('guests.0.email');

        $this->assertSame(0, Registration::count());
    }

    public function test_hostia_s_emailom_dostanu_vlastne_potvrdenie(): void
    {
        Mail::fake();

        $this->post(route('register.store'), [
            'guests' => [
                $this->guest(),
                $this->guest(['name' => 'Peter Malý', 'email' => 'peter@email.sk']),
                $this->guest(['name' => 'Eva Krátka', 'email' => '']),
                // Rovnaká adresa ako kontaktná osoba – druhý e-mail by bol zbytočný.
                $this->guest(['name' => 'Ján Nový', 'email' => 'JANA@email.sk']),
            ],
        ])->assertSessionHasNoErrors();

        Mail::assertQueuedCount(2);
        Mail::assertQueued(RegistrationConfirmation::class, fn ($mail) => $mail->hasTo('jana@email.sk') && $mail->recipient === null);
        Mail::assertQueued(RegistrationConfirmation::class, fn ($mail) => $mail->hasTo('peter@email.sk') && $mail->recipient?->name === 'Peter Malý');
    }

    public function test_potvrdenie_hosta_neobsahuje_udaje_ostatnych(): void
    {
        Mail::fake();

        $this->post(route('register.store'), [
            'guests' => [
                $this->guest(['allergen_note' => 'tajná alergia']),
                $this->guest(['name' => 'Peter Malý', 'email' => 'peter@email.sk', 'allergen_note' => '']),
            ],
        ]);

        $registration = Registration::first();
        $peter = $registration->guests->firstWhere('name', 'Peter Malý');
        $html = (new RegistrationConfirmation($registration, $peter))->render();

        $this->assertStringContainsString('Peter Malý', $html);
        $this->assertStringContainsString('PLES-0001', $html);
        $this->assertStringNotContainsString('tajná alergia', $html);
    }

    public function test_vegan_a_vegetarian_naraz_neprejde(): void
    {
        $this->post(route('register.store'), ['guests' => [$this->guest(['is_vegan' => true, 'is_vegetarian' => true])]])
            ->assertSessionHasErrors('guests.0.is_vegan');

        $this->assertSame(0, Registration::count());
    }

    public function test_hlaska_pouziva_nazov_pola_z_formulara(): void
    {
        $this->app->setLocale('sk');

        $this->post(route('register.store'), ['guests' => [$this->guest(['note' => str_repeat('x', 1001)])]]);

        $this->assertStringContainsString('odkaz pre organizátorov', session('errors')->first('guests.0.note'));
    }

    public function test_alergeny_sa_ulozia_zoradene(): void
    {
        Mail::fake();

        $this->post(route('register.store'), ['guests' => [$this->guest(['allergen_ids' => [14, 1, 7]])]]);

        $this->assertSame([1, 7, 14], Guest::first()->allergen_ids);
    }

    public function test_email_ukaze_nazvy_alergenov_a_zalomenia_riadkov(): void
    {
        Mail::fake();

        $this->post(route('register.store'), ['guests' => [$this->guest([
            'allergen_ids' => [14, 1],
            'note'         => "prvý riadok\ndruhý <b>riadok</b>",
        ])]]);

        $html = (new RegistrationConfirmation(Registration::first()))->render();

        $this->assertStringContainsString('1. Obilniny s lepkom, 14. Mäkkýše', $html);
        $this->assertStringContainsString("prvý riadok<br />\ndruhý &lt;b&gt;riadok&lt;/b&gt;", $html);
    }

    public function test_stranka_po_odoslani_ukaze_cislo_rezervacie(): void
    {
        Mail::fake();

        $this->followingRedirects()
            ->post(route('register.store'), ['guests' => [$this->guest()]])
            ->assertInertia(fn ($page) => $page
                ->component('Registration/Success')
                ->where('reservation_number', 'PLES-0001'));
    }

    public function test_stranka_po_odoslani_funguje_aj_bez_cisla(): void
    {
        $this->get(route('register.success'))->assertOk();
    }
}
