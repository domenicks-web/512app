<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Aviso de que a senha mudou, com data e hora. `$byAdmin` quando foi um
 * admin que trocou pelo painel (aí todos os aparelhos caem, não só os outros).
 */
class PasswordChangedMail extends Mailable
{
    public function __construct(public User $user, public CarbonInterface $changedAt, public bool $byAdmin = false) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Sua senha no 512 foi alterada');
    }

    public function content(): Content
    {
        return new Content(view: 'mail.password-changed');
    }
}
