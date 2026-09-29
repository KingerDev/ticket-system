<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Stoličky, ktoré si organizátori držia pre seba (napr. pre učiteľov).
        // Nie sú to hostia – nemajú lístok ani check-in, len popis.
        Schema::create('seat_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('table_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('seat_number');
            $table->string('label')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->unique(['table_id', 'seat_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seat_blocks');
    }
};
