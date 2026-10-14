@props(['title', 'message', 'user' => null])
{{--
    Layout dos emails do 512. Feito pra Gmail/Outlook: tudo em tabela, cor
    de fundo também em `bgcolor` (o Gmail ignora estilo do <body>), nada de
    SVG nem flex. A logo e o sprite do parceiro vão embutidos (CID), então
    aparecem mesmo com o app rodando em localhost.
--}}
@php
    $logo = $message->embed(resource_path('images/mail/logo.png'));
    $spritePath = $user?->avatarSpecies?->sprite_path;
    $sprite = $spritePath !== null && is_file(storage_path('app/public/'.$spritePath))
        ? $message->embed(storage_path('app/public/'.$spritePath))
        : null;
@endphp
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light only">
    <meta name="supported-color-schemes" content="light">
    <title>{{ $title }}</title>
</head>
<body style="margin:0;padding:0;background:#eff0f2;" bgcolor="#eff0f2">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" bgcolor="#eff0f2" style="background:#eff0f2;">
        <tr>
            <td align="center" style="padding:36px 14px 40px;">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="max-width:520px;">
                    <tr>
                        <td align="center" style="padding:0 0 26px;">
                            <img src="{{ $logo }}" width="132" height="60" alt="512" style="display:block;width:132px;height:auto;border:0;">
                        </td>
                    </tr>
                    <tr>
                        <td bgcolor="#ffffff" style="background:#ffffff;border-radius:28px;overflow:hidden;border:1px solid #e4e6ea;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                                <tr>
                                    <td bgcolor="#f24d4d" height="8" style="background:#f24d4d;height:8px;line-height:8px;font-size:0;">&nbsp;</td>
                                </tr>
                                <tr>
                                    <td style="padding:30px 32px 34px;font-family:Inter,-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:#2b2f36;">
                                        @if ($user)
                                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin:0 0 18px;">
                                                <tr>
                                                    @if ($sprite)
                                                        <td width="64" valign="middle" style="padding:0 12px 0 0;">
                                                            <table role="presentation" cellspacing="0" cellpadding="0" border="0">
                                                                <tr>
                                                                    <td width="56" height="56" align="center" valign="middle" bgcolor="#f4f5f6" style="width:56px;height:56px;border-radius:28px;background:#f4f5f6;border:2px solid #f24d4d;">
                                                                        <img src="{{ $sprite }}" width="56" height="56" alt="" style="display:block;width:56px;height:56px;border:0;">
                                                                    </td>
                                                                </tr>
                                                            </table>
                                                        </td>
                                                    @endif
                                                    <td valign="middle" style="font-family:Inter,-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;">
                                                        <div style="font-size:16px;font-weight:900;color:#2b2f36;">{{ $user->nickname }}<span style="color:#a3a8b0;">#{{ $user->tag }}</span></div>
                                                        <div style="font-size:12.5px;font-weight:700;color:#9aa0a8;padding-top:2px;">Treinador do 512</div>
                                                    </td>
                                                </tr>
                                            </table>
                                        @endif
                                        <h1 style="margin:0 0 12px;font-size:26px;line-height:1.15;font-weight:900;letter-spacing:-0.025em;color:#2b2f36;">{{ $title }}</h1>
                                        {{ $slot }}
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td align="center" style="padding:22px 12px 0;font-family:Inter,-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;">
                            <div style="font-size:12px;font-weight:900;letter-spacing:0.12em;color:#f24d4d;text-transform:uppercase;">1 em 512</div>
                            <div style="font-size:12.5px;line-height:1.5;font-weight:600;color:#9aa0a8;padding-top:6px;">
                                Você recebeu este email porque tem conta no 512, o jogo de packs do grupo.
                            </div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
