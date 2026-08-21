<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guests', function (Blueprint $table) {
            // Termín zadáva organizátor pri odosielaní pripomienky, preto je
            // uložený pri hosťovi a nie ako jedno globálne nastavenie.
            $table->timestamp('payment_deadline_at')->nullable()->after('paid_at');
            $table->timestamp('reminder_sent_at')->nullable()->after('payment_deadline_at');
            $table->timestamp('final_notice_sent_at')->nullable()->after('reminder_sent_at');

            // Storno je príznak, nie zmazanie – pri spore o platbu sa hosť
            // musí dať obnoviť aj s pôvodnými údajmi.
            $table->timestamp('cancelled_at')->nullable()->after('final_notice_sent_at');

            $table->index('cancelled_at');
            $table->index('payment_deadline_at');
        });
    }

    public function down(): void
    {
        Schema::table('guests', function (Blueprint $table) {
            $table->dropIndex(['cancelled_at']);
            $table->dropIndex(['payment_deadline_at']);
            $table->dropColumn([
                'payment_deadline_at',
                'reminder_sent_at',
                'final_notice_sent_at',
                'cancelled_at',
            ]);
        });
    }
};
