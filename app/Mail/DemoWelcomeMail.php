<?php

// app/Mail/DemoWelcomeMail.php
namespace App\Mail;

use App\Models\Client;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class DemoWelcomeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Client $client) {}

    public function build()
    {
        return $this->subject('Добро пожаловать в shamCRM — демо уже в работе')
            ->view('mail.demo-welcome')
            ->with(['client' => $this->client]);

    }
}

