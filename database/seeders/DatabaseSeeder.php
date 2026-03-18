<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        \App\Models\User::factory()->create([
            'name' => 'Admin User',
            'email' => 'admin@ples.sk',
            'password' => bcrypt('admin123'),
        ]);

        $hallConfig = \App\Models\HallConfig::create([
            'num_rows' => 3,
            'tables_per_row' => [4, 4, 4],
            'seats_per_table' => 8,
            'locked' => false,
        ]);

        $rowLabels = ['A', 'B', 'C'];
        foreach ($hallConfig->tables_per_row as $rowIndex => $tableCount) {
            $rowLabel = $rowLabels[$rowIndex];
            for ($i = 1; $i <= $tableCount; $i++) {
                \App\Models\Table::create([
                    'name' => $rowLabel . $i,
                    'row_label' => $rowLabel,
                    'position_in_row' => $i,
                    'capacity' => $hallConfig->seats_per_table,
                ]);
            }
        }
    }
}
