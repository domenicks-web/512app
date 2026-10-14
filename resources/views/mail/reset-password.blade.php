<x-mail-layout title="Esqueceu a senha?" :message="$message" :user="$user->hasCompletedProfile() ? $user : null">
    <p style="margin:0 0 22px;font-size:15px;line-height:1.6;font-weight:600;color:#5a6069;">
        Alguém pediu uma senha nova pra sua conta no 512. Se foi você, toque no
        botão abaixo e escolha a senha nova. O link vale por
        <strong style="color:#2b2f36;">{{ $minutes }} minutos</strong> e só funciona uma vez.
    </p>
    <table role="presentation" cellspacing="0" cellpadding="0" border="0">
        <tr>
            <td bgcolor="#f24d4d" style="background:#f24d4d;border-radius:999px;">
                <a href="{{ $url }}" style="display:inline-block;padding:16px 30px;font-family:Inter,-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;font-size:16px;font-weight:800;color:#ffffff;text-decoration:none;border-radius:999px;">Criar senha nova</a>
            </td>
        </tr>
    </table>
    <p style="margin:24px 0 0;padding-top:18px;border-top:1px solid #eceef0;font-size:13px;line-height:1.55;font-weight:600;color:#9aa0a8;">
        Não pediu? Pode ignorar este email: sua senha continua a mesma.
    </p>
</x-mail-layout>
