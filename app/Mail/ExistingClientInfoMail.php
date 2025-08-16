<?php

namespace App\Mail;

use App\Models\Client;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ExistingClientInfoMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Client $client) {}

    public function build()
    {
        $resetUrl = route('password.request', absolute: false);

        if (!$resetUrl || str_contains($resetUrl, 'http') === false) {
            $resetUrl = url('/forgot-password');
        }
        return $this->subject('Доступ к shamCRM: У Вас уже есть аккаунт')
            ->view('mail.existing-account')
            ->with(['client' => $this->client, 'resetUrl' => $resetUrl]);

    }
}
