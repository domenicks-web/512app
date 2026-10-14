<x-mail-layout title="Seu email foi alterado">
    <p style="margin:0 0 16px;font-size:15px;line-height:1.55;font-weight:600;color:#5a6069;">
        Oi, {{ $user->nickname }}. O email da sua conta no 512 agora é
        <strong style="color:#2b2f36;">{{ $maskedEmail }}</strong>. Este endereço não recebe mais nada do jogo.
    </p>
    <p style="margin:0;padding:14px 18px;border-radius:16px;background:rgba(242,77,77,0.10);font-size:14px;line-height:1.5;font-weight:700;color:#c0342f;">
        Não foi você? Fale com um admin do grupo o quanto antes.
    </p>
</x-mail-layout>
