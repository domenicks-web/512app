{{-- Layout dos emails do 512: tabela com CSS inline (clientes de email ignoram <style> e flex). --}}
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>{{ $title }}</title>
</head>
<body style="margin:0;padding:0;background:#eff0f2;font-family:Inter,-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:#2b2f36;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#eff0f2;padding:32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:560px;">
                    <tr>
                        <td style="padding:0 0 18px 4px;">
                            <table role="presentation" cellspacing="0" cellpadding="0">
                                <tr>
                                    <td style="background:#f24d4d;border-radius:14px;padding:10px 16px;box-shadow:0 10px 24px rgba(242,77,77,0.32);">
                                        <span style="display:inline-block;width:18px;height:18px;border-radius:50%;background:#ffffff;border:3px solid #434a53;vertical-align:middle;"></span>
                                        <span style="font-size:22px;font-weight:900;color:#ffffff;letter-spacing:-0.02em;vertical-align:middle;margin-left:8px;">512</span>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="background:#ffffff;border-radius:28px;padding:34px 30px;box-shadow:0 2px 4px rgba(43,47,54,0.05),0 22px 56px rgba(43,47,54,0.12);">
                            <h1 style="margin:0 0 12px;font-size:25px;line-height:1.15;font-weight:900;letter-spacing:-0.025em;color:#2b2f36;">{{ $title }}</h1>
                            {{ $slot }}
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:18px 8px 0;font-size:12.5px;line-height:1.5;font-weight:600;color:#9aa0a8;text-align:center;">
                            Você recebeu este email porque tem conta no 512, o jogo de packs do grupo.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
