<x-mail-layout title="Sua senha foi alterada" :message="$message" :user="$user">
    <p style="margin:0 0 22px;font-size:15px;line-height:1.6;font-weight:600;color:#5a6069;">
        A senha da sua conta no 512 foi trocada{{ $byAdmin ? ' por um admin do grupo' : ($byReset ? ' pelo link de "esqueci a senha"' : '') }} em
        <strong style="color:#2b2f36;">{{ $changedAt->timezone(config('game.display_timezone'))->format('d/m') }} às {{ $changedAt->timezone(config('game.display_timezone'))->format('H:i') }}</strong>.
        @if ($byAdmin)
            Todos os aparelhos foram desconectados: entre de novo com a senha nova que o admin te passar.
        @elseif ($byReset)
            Todos os aparelhos foram desconectados: entre de novo com a senha nova.
        @else
            Os outros aparelhos foram desconectados.
        @endif
    </p>
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
        <tr>
            <td bgcolor="#fde8e8" style="background:#fde8e8;border-radius:16px;padding:14px 18px;font-family:Inter,-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;font-size:14px;line-height:1.5;font-weight:800;color:#c0342f;">
                {{ $byAdmin ? 'Não pediu essa troca? Fale com um admin do grupo o quanto antes.' : 'Não foi você? Fale com um admin do grupo o quanto antes.' }}
            </td>
        </tr>
    </table>
</x-mail-layout>
