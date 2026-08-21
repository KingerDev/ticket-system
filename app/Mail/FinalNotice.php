<?php

namespace App\Mail;

use App\Models\Registration;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/** Posledná výzva pred stornom rezervácie. */
class FinalNotice extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @param  Collection<int, \App\Models\Guest>  $guests  hostia, ktorých sa e-mail týka
     */
    public function __construct(
        public Registration $registration,
        public Collection $guests,
        public ?Carbon $deadline = null,
    ) {
    }

    public function envelope(): Envelope
    {
        $replyTo = config('mail.reply_to.address');

        return new Envelope(
            subject: 'Posledná výzva: rezervácia ' . $this->registration->reservation_number . ' čoskoro prepadne',
            replyTo: $replyTo ? [new Address($replyTo, config('mail.reply_to.name') ?? '')] : [],
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.final-notice');
    }
}
