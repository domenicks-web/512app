<x-mail-layout title="Confirme seu email" :message="$message" :user="$user->hasCompletedProfile() ? $user : null">
    <p style="margin:0 0 22px;font-size:15px;line-height:1.6;font-weight:600;color:#5a6069;">
        Falta só confirmar que este endereço é seu. Toque no botão abaixo. O
        link vale por <strong style="color:#2b2f36;">{{ $minutes }} minutos</strong>, e
        dá pra continuar jogando enquanto isso.
    </p>
    <table role="presentation" cellspacing="0" cellpadding="0" border="0">
        <tr>
            <td bgcolor="#f24d4d" style="background:#f24d4d;border-radius:999px;">
                <a href="{{ $url }}" style="display:inline-block;padding:16px 30px;font-family:Inter,-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;font-size:16px;font-weight:800;color:#ffffff;text-decoration:none;border-radius:999px;">Confirmar email</a>
            </td>
        </tr>
    </table>
    <p style="margin:24px 0 0;padding-top:18px;border-top:1px solid #eceef0;font-size:13px;line-height:1.55;font-weight:600;color:#9aa0a8;">
        Não criou conta no 512? Pode ignorar este email.
    </p>
</x-mail-layout>
