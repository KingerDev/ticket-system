<?php

namespace App\Console\Commands;

use App\Models\ActivityLog;
use App\Models\Guest;
use App\Models\Registration;
use App\Models\Table;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Vyčistenie skúšobných dát pred ostrým spustením registrácií.
 *
 * Zámerne nemaže používateľské účty ani rozloženie sály – tie sa nastavujú
 * raz a po skúške sa nemenia. Na úplné vyprázdnenie slúži migrate:fresh.
 */
class ResetTestData extends Command
{
    use ConfirmableTrait;

    protected $signature = 'ples:reset
                            {--keep-log : Ponechať záznam činnosti}
                            {--force : Nepýtať sa na potvrdenie}';

    protected $description = 'Zmaže skúšobné registrácie, hostí a záznam činnosti. Účty a sálu ponechá.';

    public function handle(): int
    {
        $this->newLine();
        $this->line('  <options=bold>Vyčistenie skúšobných dát</>');
        $this->newLine();

        $keepLog = (bool) $this->option('keep-log');

        $zmaze = [
            'registrácie'     => Registration::count(),
            'hostia'          => Guest::count(),
            'úlohy vo fronte' => DB::table('jobs')->count() + DB::table('failed_jobs')->count(),
        ];

        if (! $keepLog) {
            $zmaze['záznamy činnosti'] = ActivityLog::count();
        }

        $ponecha = [
            'používateľské účty' => User::count(),
            'stoly v sále'       => Table::count(),
        ];

        if ($keepLog) {
            $ponecha['záznamy činnosti'] = ActivityLog::count();
        }

        $this->line('  <fg=red>Zmaže sa</>');
        foreach ($zmaze as $nazov => $pocet) {
            $this->line('    <fg=red>✖</> ' . Str::padRight($nazov, 20) . $pocet);
        }

        $this->newLine();
        $this->line('  <fg=green>Zostane</>');
        foreach ($ponecha as $nazov => $pocet) {
            $this->line('    <fg=green>✔</> ' . Str::padRight($nazov, 20) . $pocet);
        }

        $this->newLine();

        if (array_sum($zmaze) === 0) {
            $this->line('  <fg=gray>Niet čo mazať, databáza je už čistá.</>');
            $this->newLine();

            return self::SUCCESS;
        }

        // V produkcii si vyžiada potvrdenie, pokiaľ nie je --force.
        if (! $this->confirmToProceed('Táto akcia je nevratná.')) {
            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm('Naozaj zmazať? Vrátiť sa to nedá.', false)) {
            $this->line('  <fg=gray>Zrušené, nič sa nezmazalo.</>');
            $this->newLine();

            return self::FAILURE;
        }

        // TRUNCATE zároveň vynuluje počítadlo ID, takže číslovanie rezervácií
        // začne opäť od PLES-0001.
        Schema::disableForeignKeyConstraints();

        DB::table('guests')->truncate();
        DB::table('registrations')->truncate();
        DB::table('jobs')->truncate();
        DB::table('failed_jobs')->truncate();

        if (! $keepLog) {
            DB::table('activity_logs')->truncate();
        }

        Schema::enableForeignKeyConstraints();

        $this->newLine();
        $this->line('  <fg=green;options=bold>Hotovo.</> Sála aj účty zostali nedotknuté.');
        $this->line('  <fg=gray>Ďalšia registrácia dostane číslo PLES-0001.</>');
        $this->newLine();

        return self::SUCCESS;
    }
}
