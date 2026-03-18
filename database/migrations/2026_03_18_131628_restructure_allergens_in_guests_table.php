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
            $table->dropColumn('allergens');
            $table->json('allergen_ids')->nullable()->after('email');
            $table->boolean('is_vegan')->default(false)->after('allergen_ids');
            $table->boolean('is_vegetarian')->default(false)->after('is_vegan');
            $table->text('allergen_note')->nullable()->after('is_vegetarian');
            $table->text('note')->nullable()->after('allergen_note');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('guests', function (Blueprint $table) {
            $table->dropColumn(['allergen_ids', 'is_vegan', 'is_vegetarian', 'allergen_note', 'note']);
            $table->text('allergens')->nullable();
        });
    }
};
