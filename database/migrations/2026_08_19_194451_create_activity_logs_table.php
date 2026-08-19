<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();

            // Používateľa možno neskôr zmažeme, záznam musí zostať dohľadateľný,
            // preto sa meno aj e-mail ukladajú ako kópia k okamihu akcie.
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('user_name');
            $table->string('user_email')->nullable();

            $table->string('action');                       // napr. guest.updated
            $table->string('description');                  // ľudsky čitateľný popis
            $table->string('subject_type')->nullable();     // App\Models\Guest
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('properties')->nullable();         // zmenené hodnoty (pred/po)
            $table->string('ip_address', 45)->nullable();

            $table->timestamps();

            $table->index('action');
            $table->index('created_at');
            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
