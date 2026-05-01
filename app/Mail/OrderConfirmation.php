<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Orders\Order;
use App\Models\Pricing\AppSetting;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderConfirmation extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Order $order,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: $this->companyFrom($this->order->company_id),
            subject: 'Auftragsbestätigung #' . $this->order->id,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.order_confirmation',
        );
    }

    private function companyFrom(?int $companyId): Address
    {
        $addr = AppSetting::get("company.{$companyId}.mail.from_address")
            ?? config('mail.from.address', 'noreply@example.com');
        $name = AppSetting::get("company.{$companyId}.mail.from_name")
            ?? config('mail.from.name', 'Shop');

        return new Address((string) $addr, (string) $name);
    }
}
