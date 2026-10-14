<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Confirmação do email do cadastro (e do "reenviar" do admin), no visual
 * do jogo. O link assinado vem da notificação `VerifyEmail` do Laravel.
 */
class VerifyEmailMail extends Mailable
{
    public function __construct(public User $user, public string $url, public int $minutes) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Confirme seu email no 512');
    }

    public function content(): Content
    {
        return new Content(view: 'mail.verify-email');
    }
}
