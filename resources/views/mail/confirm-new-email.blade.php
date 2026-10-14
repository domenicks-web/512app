<x-mail-layout title="Confirme seu email novo" :message="$message" :user="$user">
    <p style="margin:0 0 22px;font-size:15px;line-height:1.6;font-weight:600;color:#5a6069;">
        Você pediu pra usar este endereço na sua conta do 512. Toque no botão
        pra confirmar. O link vale por <strong style="color:#2b2f36;">{{ $minutes }} minutos</strong>.
    </p>
    <table role="presentation" cellspacing="0" cellpadding="0" border="0">
        <tr>
            <td bgcolor="#f24d4d" style="background:#f24d4d;border-radius:999px;">
                <a href="{{ $url }}" style="display:inline-block;padding:16px 30px;font-family:Inter,-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;font-size:16px;font-weight:800;color:#ffffff;text-decoration:none;border-radius:999px;">Confirmar email novo</a>
            </td>
        </tr>
    </table>
    <p style="margin:24px 0 0;padding-top:18px;border-top:1px solid #eceef0;font-size:13px;line-height:1.55;font-weight:600;color:#9aa0a8;">
        Não foi você? Pode ignorar este email. Nada muda na conta sem esse clique.
    </p>
</x-mail-layout>
