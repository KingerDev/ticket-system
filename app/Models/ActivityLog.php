<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

/**
 * Auditný záznam – kto, kedy a čo zmenil v administrácii.
 *
 * Zapisuje sa výhradne cez ActivityLog::record(), aby mali všetky záznamy
 * rovnaký tvar a nedalo sa zabudnúť na kópiu mena používateľa.
 */
class ActivityLog extends Model
{
    protected $guarded = [];

    protected $casts = [
        'properties' => 'array',
    ];

    /** Slovenské pomenovania akcií pre výpis a filter. */
    public const ACTIONS = [
        'auth.login'            => 'Prihlásenie',
        'auth.logout'           => 'Odhlásenie',
        'guest.updated'         => 'Úprava hosťa',
        'guest.deleted'         => 'Odstránenie hosťa',
        'guest.seat_assigned'   => 'Pridelenie miesta',
        'guest.paid_toggled'    => 'Zmena platby',
        'guest.ticket_issued'   => 'Vydanie lístka',
        'guest.checked_in'      => 'Zápis pri vstupe',
        'guest.reminder_sent'   => 'Pripomienka platby',
        'guest.final_notice_sent' => 'Posledná výzva',
        'guest.cancelled'       => 'Storno rezervácie',
        'guest.restored'        => 'Obnovenie rezervácie',
        'registration.contact_updated' => 'Úprava kontaktu',
        'registration.deleted'  => 'Odstránenie rezervácie',
        'hall.updated'          => 'Zmena rozloženia sály',
        'user.created'          => 'Vytvorenie používateľa',
        'user.updated'          => 'Úprava používateľa',
        'user.deleted'          => 'Odstránenie používateľa',
    ];

    /**
     * Zapíše záznam o akcii prihláseného používateľa.
     *
     * @param  array<string, mixed>  $properties  napr. ['pred' => [...], 'po' => [...]]
     */
    public static function record(
        string $action,
        string $description,
        ?Model $subject = null,
        array $properties = [],
        ?User $actor = null,
    ): self {
        $actor ??= Auth::user();

        return self::create([
            'user_id'      => $actor?->id,
            'user_name'    => $actor?->name ?? 'systém',
            'user_email'   => $actor?->email,
            'action'       => $action,
            'description'  => $description,
            'subject_type' => $subject ? $subject::class : null,
            'subject_id'   => $subject?->getKey(),
            'properties'   => $properties ?: null,
            'ip_address'   => request()->ip(),
        ]);
    }

    /**
     * Rozdiel medzi pôvodnými a novými hodnotami – do logu ide len to,
     * čo sa naozaj zmenilo, nie celý model.
     *
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @return array<string, array{pred: mixed, po: mixed}>
     */
    public static function diff(array $before, array $after): array
    {
        $changes = [];

        foreach ($after as $key => $newValue) {
            $oldValue = $before[$key] ?? null;

            if ($oldValue != $newValue) {
                $changes[$key] = ['pred' => $oldValue, 'po' => $newValue];
            }
        }

        return $changes;
    }

    public function getActionLabelAttribute(): string
    {
        return self::ACTIONS[$this->action] ?? $this->action;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
