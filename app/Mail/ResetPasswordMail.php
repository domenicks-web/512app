<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Link do "esqueci a senha", no visual do jogo. O token vem da notificação
 * `ResetPassword` do Laravel; o link leva pra tela que só pede a senha nova.
 */
class ResetPasswordMail extends Mailable
{
    public function __construct(public User $user, public string $url, public int $minutes) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Crie uma senha nova no 512');
    }

    public function content(): Content
    {
        return new Content(view: 'mail.reset-password');
    }
}
