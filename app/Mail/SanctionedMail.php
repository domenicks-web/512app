<?php

declare(strict_types=1);

namespace App\Mail;

use App\Enums\SanctionType;
use App\Models\Sanction;
use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Aviso de suspensão (com o prazo) ou de banimento, sempre com o motivo. */
class SanctionedMail extends Mailable
{
    public function __construct(public User $user, public Sanction $sanction) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->sanction->type === SanctionType::Ban
            ? 'Sua conta no 512 foi banida'
            : 'Sua conta no 512 foi suspensa');
    }

    public function content(): Content
    {
        return new Content(view: 'mail.sanctioned');
    }
}
