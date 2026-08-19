<?php

namespace Database\Seeders;

use App\Models\HallConfig;
use App\Models\Table;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /** Predvolené rozloženie sály, ak ešte žiadne neexistuje. */
    private const DEFAULT_ROWS            = 3;
    private const DEFAULT_TABLES_PER_ROW  = [4, 4, 4];
    private const DEFAULT_SEATS_PER_TABLE = 8;

    /**
     * Seeder je idempotentný – dá sa spustiť opakovane bez duplicít.
     *
     * Zámerne nepoužíva model factories: fakerphp/faker je require-dev, takže
     * v produkčnom obraze (composer install --no-dev) neexistuje a factory by
     * spadla na "Call to undefined function fake()".
     */
    public function run(): void
    {
        $this->heading('Seed databázy');

        $this->seedAdmin();
        $this->seedHall();

        $this->line('');
    }

    private function seedAdmin(): void
    {
        $this->section('Administrátorský účet');

        $email = (string) config('admin.email');
        $name  = (string) config('admin.name');

        if ($email === '') {
            $this->bad('e-mail', 'ADMIN_EMAIL je prázdny – účet nebol vytvorený');

            return;
        }

        $existing = User::where('email', $email)->first();
        $password = config('admin.password');
        $generated = null;

        if (blank($password)) {
            if ($existing) {
                // Bez hesla v prostredí nechávame to súčasné – nechceme ho
                // pri každom seede prepísať a odstrihnúť sa od prístupu.
                $password = null;
            } else {
                $password = $generated = Str::password(16, true, true, false);
            }
        }

        $user = $existing ?: new User();
        $user->name  = $name;
        $user->email = $email;
        // Prvý účet musí byť super admin, inak by nebolo možné zakladať ďalších.
        $user->is_super_admin = true;

        if ($password !== null) {
            // Model má cast 'password' => 'hashed', hashuje sa automaticky.
            $user->password = $password;
        }

        // email_verified_at nie je vo $fillable, preto priame priradenie.
        $user->email_verified_at ??= now();
        $user->save();

        $this->good($existing ? 'aktualizovaný' : 'vytvorený', $email);
        $this->good('rola', 'super administrátor');

        if ($generated !== null) {
            $this->warn('heslo', 'vygenerované: ' . $generated);
            $this->note('Zobrazuje sa len teraz – ulož si ho. Nabudúce nastav ADMIN_PASSWORD.');
        } elseif ($password === null) {
            $this->warn('heslo', 'nezmenené (ADMIN_PASSWORD nie je nastavené)');
        } else {
            $this->good('heslo', 'nastavené z ADMIN_PASSWORD');

            if (Str::length($password) < 12) {
                $this->note('ADMIN_PASSWORD má menej než 12 znakov – zváž dlhšie heslo.');
            }
        }
    }

    private function seedHall(): void
    {
        $this->section('Rozloženie sály');

        if (HallConfig::exists()) {
            $this->skip('konfigurácia', 'už existuje – ponechaná bez zmeny');
            $this->skip('stoly', Table::count() . ' v databáze');

            return;
        }

        $hallConfig = HallConfig::create([
            'num_rows'        => self::DEFAULT_ROWS,
            'tables_per_row'  => self::DEFAULT_TABLES_PER_ROW,
            'seats_per_table' => self::DEFAULT_SEATS_PER_TABLE,
            'locked'          => false,
        ]);

        $created = 0;
        $skipped = 0;

        foreach ($hallConfig->tables_per_row as $rowIndex => $tableCount) {
            $rowLabel = $this->rowLabel($rowIndex);

            for ($position = 1; $position <= $tableCount; $position++) {
                $table = Table::firstOrCreate(
                    ['name' => $rowLabel . $position],
                    [
                        'row_label'        => $rowLabel,
                        'position_in_row'  => $position,
                        'capacity'         => $hallConfig->seats_per_table,
                    ]
                );

                $table->wasRecentlyCreated ? $created++ : $skipped++;
            }
        }

        $this->good('konfigurácia', sprintf(
            'radov: %d · miest pri stole: %d',
            $hallConfig->num_rows,
            $hallConfig->seats_per_table
        ));
        $this->good('stoly', 'vytvorených: ' . $created . ($skipped ? ", preskočených: {$skipped}" : ''));
    }

    /** Označenie radu: 0 => A, 1 => B, … a ďalej A1, B1, … pri viac než 26 radoch. */
    private function rowLabel(int $index): string
    {
        return $index < 26
            ? chr(65 + $index)
            : chr(65 + ($index % 26)) . intdiv($index, 26);
    }

    // --- výpis ---------------------------------------------------------------

    private function heading(string $text): void
    {
        $this->line('');
        $this->line("  <options=bold>{$text}</>");
    }

    private function section(string $text): void
    {
        $this->line('');
        $this->line("  <fg=gray>{$text}</>");
    }

    private function good(string $label, string $value): void
    {
        $this->row('<fg=green>✔</>', $label, $value);
    }

    private function warn(string $label, string $value): void
    {
        $this->row('<fg=yellow>!</>', $label, "<fg=yellow>{$value}</>");
    }

    private function bad(string $label, string $value): void
    {
        $this->row('<fg=red>✖</>', $label, "<fg=red>{$value}</>");
    }

    private function skip(string $label, string $value): void
    {
        $this->row('<fg=gray>–</>', $label, "<fg=gray>{$value}</>");
    }

    private function note(string $text): void
    {
        $this->line("      <fg=gray>{$text}</>");
    }

    private function row(string $icon, string $label, string $value): void
    {
        $this->line(sprintf('  %s %s %s', $icon, Str::padRight($label, 16), $value));
    }

    /** Seeder sa dá spustiť aj mimo konzoly (testy), vtedy sa nevypisuje nič. */
    private function line(string $text): void
    {
        $this->command?->getOutput()->writeln($text);
    }
}
