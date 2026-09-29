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

/** Náhradník dostal miesto – teraz už môže prísť zaplatiť. */
class WaitlistPromoted extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Registration $registration)
    {
        $this->registration->load('guests');
    }

    public function envelope(): Envelope
    {
        $replyTo = config('mail.reply_to.address');

        return new Envelope(
            subject: 'Uvoľnilo sa miesto – rezervácia ' . $this->registration->reservation_number,
            replyTo: $replyTo ? [new Address($replyTo, config('mail.reply_to.name') ?? '')] : [],
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.waitlist-promoted');
    }
}
