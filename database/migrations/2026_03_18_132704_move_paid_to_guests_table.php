<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('guests', function (Blueprint $table) {
            $table->boolean('paid')->default(false)->after('note');
            $table->timestamp('paid_at')->nullable()->after('paid');
        });

        Schema::table('registrations', function (Blueprint $table) {
            $table->dropColumn(['paid', 'paid_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('guests', function (Blueprint $table) {
            $table->dropColumn(['paid', 'paid_at']);
        });

        Schema::table('registrations', function (Blueprint $table) {
            $table->boolean('paid')->default(false);
            $table->timestamp('paid_at')->nullable();
        });
    }
};
