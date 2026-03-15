<?php

namespace App\Mail;

use App\Models\Event;
use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class TicketsPurchasedMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public Order $order,
        public Event $event,
        public Collection $tickets,
        public array $attachmentsData,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Tus boletos de ' . $this->event->name,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.tickets-purchased',
            with: [
                'order' => $this->order,
                'event' => $this->event,
                'tickets' => $this->tickets,
                'downloadBaseUrl' => rtrim((string) config('app.url'), '/'),
            ],
        );
    }

    public function attachments(): array
    {
        return array_map(fn(array $attachment) => Attachment::fromData(
            fn() => $attachment['data'],
            $attachment['name'],
        )->withMime('application/pdf'), $this->attachmentsData);
    }
}
