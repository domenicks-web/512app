<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Vai pro email novo, com o link assinado que confirma a troca. */
class ConfirmNewEmailMail extends Mailable
{
    public function __construct(public User $user, public string $url, public int $minutes) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Confirme seu email novo no 512');
    }

    public function content(): Content
    {
        return new Content(view: 'mail.confirm-new-email');
    }
}
