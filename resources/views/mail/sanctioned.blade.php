@php($isBan = $sanction->type === \App\Enums\SanctionType::Ban)
<x-mail-layout :title="$isBan ? 'Sua conta foi banida' : 'Sua conta foi suspensa'" :message="$message" :user="$user">
    <p style="margin:0 0 22px;font-size:15px;line-height:1.6;font-weight:600;color:#5a6069;">
        @if ($isBan)
            Um admin do grupo baniu a sua conta no 512. O banimento é permanente: você não consegue mais entrar, e os seus espécimes foram libertados.
        @else
            Um admin do grupo suspendeu a sua conta no 512 até
            <strong style="color:#2b2f36;">{{ $sanction->ends_at?->timezone(config('game.display_timezone'))->format('d/m') }} às {{ $sanction->ends_at?->timezone(config('game.display_timezone'))->format('H:i') }}</strong>.
            Até lá você não consegue entrar; seus espécimes continuam com você.
        @endif
    </p>
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
        <tr>
            <td bgcolor="#f4f5f6" style="background:#f4f5f6;border-radius:16px;padding:14px 18px;font-family:Inter,-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;font-size:14px;line-height:1.5;font-weight:700;color:#2b2f36;">
                Motivo: {{ $sanction->reason }}
            </td>
        </tr>
    </table>
</x-mail-layout>
