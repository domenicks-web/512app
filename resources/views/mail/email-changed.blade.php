<x-mail-layout title="Seu email foi alterado" :message="$message" :user="$user">
    <p style="margin:0 0 22px;font-size:15px;line-height:1.6;font-weight:600;color:#5a6069;">
        O email da sua conta no 512 agora é
        <strong style="color:#2b2f36;">{{ $maskedEmail }}</strong>. Este endereço
        não recebe mais nada do jogo.
    </p>
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
        <tr>
            <td bgcolor="#fde8e8" style="background:#fde8e8;border-radius:16px;padding:14px 18px;font-family:Inter,-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;font-size:14px;line-height:1.5;font-weight:800;color:#c0342f;">
                Não foi você? Fale com um admin do grupo o quanto antes.
            </td>
        </tr>
    </table>
</x-mail-layout>
