<x-mail-layout title="Confirme seu email novo">
    <p style="margin:0 0 22px;font-size:15px;line-height:1.55;font-weight:600;color:#5a6069;">
        Oi, {{ $user->nickname }}! Você pediu pra usar este endereço na sua conta do 512.
        Toque no botão pra confirmar. O link vale por {{ $minutes }} minutos.
    </p>
    <table role="presentation" cellspacing="0" cellpadding="0">
        <tr>
            <td style="background:#f24d4d;border-radius:999px;box-shadow:0 10px 24px rgba(242,77,77,0.42);">
                <a href="{{ $url }}" style="display:inline-block;padding:16px 28px;font-size:16px;font-weight:800;color:#ffffff;text-decoration:none;">Confirmar email novo</a>
            </td>
        </tr>
    </table>
    <p style="margin:22px 0 0;font-size:13px;line-height:1.5;font-weight:600;color:#9aa0a8;">
        Não foi você? Pode ignorar este email. Nada muda na conta sem esse clique.
    </p>
</x-mail-layout>
