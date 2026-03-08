<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ContactUsMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $senderName;
    public string $senderEmail;
    public string $body;

    public function __construct(string $senderName, string $senderEmail, string $body)
    {
        $this->senderName = $senderName;
        $this->senderEmail = $senderEmail;
        $this->body = $body;
    }

    public function build(): self
    {
        return $this->subject('Contact Us: Message from ' . $this->senderName)
            ->replyTo($this->senderEmail, $this->senderName)
            ->view('emails.contact-us');
    }
}
