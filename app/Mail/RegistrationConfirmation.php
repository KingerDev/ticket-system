<?php

namespace App\Mail;

use App\Models\Guest;
use App\Models\Registration;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RegistrationConfirmation extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $registration;

    /**
     * Hosť, ktorému správa ide. Null = kontaktná osoba, ktorá dostane
     * prehľad celej rezervácie; ostatní hostia vidia len svoje údaje.
     */
    public ?Guest $recipient;

    /**
     * Create a new message instance.
     */
    public function __construct(Registration $registration, ?Guest $recipient = null)
    {
        $this->registration = $registration->load('guests');
        $this->recipient = $recipient;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $replyTo = config('mail.reply_to.address');

        return new Envelope(
            subject: $this->registration->isWaitlisted()
                ? 'Registrácia na Beánie – zoznam náhradníkov'
                : 'Potvrdenie rezervácie na Beánie',
            // Bez toho by odpoveď hosťa skončila na odosielacej adrese,
            // ktorá poštu neprijíma.
            replyTo: $replyTo ? [new Address($replyTo, config('mail.reply_to.name') ?? '')] : [],
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.registration-confirmation',
            with: [
                'guests' => $this->recipient
                    ? collect([$this->recipient])
                    : $this->registration->guests,
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
