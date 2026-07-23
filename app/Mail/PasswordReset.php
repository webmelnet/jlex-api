<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use App\Models\User;

class PasswordReset extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    public $loginUrl;
    public $newPassword;

    public function __construct(User $user, $loginUrl, $newPassword)
    {
        $this->user = $user;
        $this->loginUrl = $loginUrl;
        $this->newPassword = $newPassword;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your Password Has Been Reset',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.password_reset',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}