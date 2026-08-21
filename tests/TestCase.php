<?php

namespace Tests;

use App\Models\Guest;
use App\Models\HallConfig;
use App\Models\Registration;
use App\Models\Table;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /** Administrátor bez práv na správu používateľov. */
    protected function admin(array $attributes = []): User
    {
        return $this->makeUser(array_merge(['is_super_admin' => false], $attributes));
    }

    /** Super administrátor – správa účtov a auditný log. */
    protected function superAdmin(array $attributes = []): User
    {
        return $this->makeUser(array_merge(['is_super_admin' => true], $attributes));
    }

    private function makeUser(array $attributes): User
    {
        static $counter = 0;
        $counter++;

        $user = User::create(array_merge([
            'name'           => 'Testovací Používateľ ' . $counter,
            'email'          => "pouzivatel{$counter}@ef.umb.sk",
            'password'       => 'HesloHeslo123',
            'is_super_admin' => false,
        ], $attributes));

        $user->forceFill(['email_verified_at' => now()])->save();

        return $user;
    }

    /** Sála s tromi radmi po štyroch stoloch, osem miest pri stole. */
    protected function hall(int $rows = 3, int $tablesPerRow = 4, int $seats = 8): HallConfig
    {
        $config = HallConfig::create([
            'num_rows'        => $rows,
            'tables_per_row'  => array_fill(0, $rows, $tablesPerRow),
            'seats_per_table' => $seats,
            'locked'          => false,
        ]);

        foreach (range(0, $rows - 1) as $rowIndex) {
            foreach (range(1, $tablesPerRow) as $position) {
                Table::create([
                    'name'            => chr(65 + $rowIndex) . $position,
                    'row_label'       => chr(65 + $rowIndex),
                    'position_in_row' => $position,
                    'capacity'        => $seats,
                ]);
            }
        }

        return $config;
    }

    /** Rezervácia aj s hosťami; $guests je pole atribútov. */
    protected function reservation(array $guests = [['name' => 'Jana Nováková']], array $attributes = []): Registration
    {
        static $counter = 0;
        $counter++;

        $registration = Registration::create(array_merge([
            'reservation_number' => 'PLES-' . str_pad((string) $counter, 4, '0', STR_PAD_LEFT),
            'registrant_name'    => $guests[0]['name'] ?? 'Jana Nováková',
            'registrant_email'   => $guests[0]['email'] ?? 'jana@email.sk',
        ], $attributes));

        foreach ($guests as $guest) {
            $registration->guests()->create($guest);
        }

        return $registration->fresh('guests');
    }

    /** Hosť so vydaným lístkom a prideleným miestom. */
    protected function seatedGuest(string $ticketCode = '007', array $attributes = []): Guest
    {
        if (! Table::exists()) {
            $this->hall();
        }

        return $this->reservation([array_merge([
            'name'          => 'Jana Nováková',
            'email'         => 'jana@email.sk',
            'ticket_code'   => $ticketCode,
            'ticket_issued' => true,
            'table_id'      => Table::first()->id,
            'seat_number'   => 4,
        ], $attributes)])->guests->first();
    }
}
