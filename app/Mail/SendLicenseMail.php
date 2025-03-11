<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SendLicenseMail extends Mailable
{
    use Queueable, SerializesModels;

    public $filePath;

    /**
     * Create a new message instance.
     */
    public function __construct($filePath)
    {
        $this->filePath = $filePath;
    }

    /**
     * Build the message.
     */
    public function build()
    {
        return $this->subject('Лицензионный договор')
            ->view('mail.organizationLicense')
            ->attach($this->filePath, [
                'as' => 'ЛИЦЕНЗИОННЫЙ_ДОГОВОР.docx',
                'mime' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            ]);
    }
}
