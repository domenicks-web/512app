<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Str;

/** Vai pro email antigo depois da troca, com o novo mascarado. */
class EmailChangedMail extends Mailable
{
    public string $maskedEmail;

    public function __construct(public User $user, string $newEmail)
    {
        $local = Str::before($newEmail, '@');
        $this->maskedEmail = Str::substr($local, 0, 1).'***@'.Str::after($newEmail, '@');
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Seu email no 512 foi alterado');
    }

    public function content(): Content
    {
        return new Content(view: 'mail.email-changed');
    }
}
