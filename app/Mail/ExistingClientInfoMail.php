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

        return $this->subject('Доступ к shamCRM: У Вас уже есть аккаунт')
            ->view('mail.existing-account')
            ->with(['client' => $this->client]);

    }
}
