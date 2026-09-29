<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('registrations', function (Blueprint $table) {
            // Registrácia, pre ktorú už nebolo dosť voľných miest. Celá skupina
            // čaká, kým ju organizátori nepresunú medzi riadne registrácie.
            $table->timestamp('waitlisted_at')->nullable()->index();
            $table->timestamp('promoted_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('registrations', function (Blueprint $table) {
            $table->dropIndex(['waitlisted_at']);
            $table->dropColumn(['waitlisted_at', 'promoted_at']);
        });
    }
};
