<x-mail-layout title="Sua senha foi alterada">
    <p style="margin:0 0 16px;font-size:15px;line-height:1.55;font-weight:600;color:#5a6069;">
        Oi, {{ $user->nickname }}. A senha da sua conta no 512 foi trocada em
        <strong style="color:#2b2f36;">{{ $changedAt->timezone(config('game.display_timezone'))->format('d/m') }} às {{ $changedAt->timezone(config('game.display_timezone'))->format('H:i') }}</strong>.
        Os outros aparelhos foram desconectados.
    </p>
    <p style="margin:0;padding:14px 18px;border-radius:16px;background:rgba(242,77,77,0.10);font-size:14px;line-height:1.5;font-weight:700;color:#c0342f;">
        Não foi você? Fale com um admin do grupo o quanto antes.
    </p>
</x-mail-layout>
