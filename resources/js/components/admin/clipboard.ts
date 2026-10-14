/**
 * Copia texto. `navigator.clipboard` só existe em contexto seguro (https ou
 * localhost); acessando o dev pelo IP da rede cai no `execCommand` antigo.
 */
export async function copyText(text: string): Promise<boolean> {
    try {
        if (window.isSecureContext && navigator.clipboard) {
            await navigator.clipboard.writeText(text);

            return true;
        }
    } catch {
        // Cai no plano B abaixo.
    }

    const field = document.createElement('textarea');
    field.value = text;
    field.setAttribute('readonly', '');
    field.style.position = 'fixed';
    field.style.opacity = '0';
    document.body.appendChild(field);
    field.select();
    const copied = document.execCommand('copy');
    field.remove();

    return copied;
}
