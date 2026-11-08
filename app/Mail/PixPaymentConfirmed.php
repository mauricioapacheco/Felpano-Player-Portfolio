<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PixPaymentConfirmed extends Mailable
{
    use Queueable, SerializesModels;

    public array $payload;

    public function __construct(array $payload)
    {
        $this->payload = $payload;
    }

    public function build()
    {
        return $this
            ->subject('Confirmação de pagamento PIX - FELPANO')
            ->markdown('emails.pix.confirmed');
    }
}