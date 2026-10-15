/**
 * Preferência "mexer as cartas com o celular", guardada neste aparelho
 * (a permissão do sensor também é do aparelho). O padrão é ligado onde o
 * sensor não pede permissão e desligado onde pede (iPhone e o Chrome mais
 * novo, que também ganhou o `requestPermission`), pra
 * nunca aparecer um pedido do Safari sem a pessoa ter tocado no
 * interruptor. Sem localStorage, vale o padrão.
 */
const MOTION_KEY = 'motion:enabled';
export const MOTION_EVENT = 'motion-preference';

type PermissionedOrientation = typeof DeviceOrientationEvent & {
    requestPermission?: () => Promise<'granted' | 'denied'>;
};

/** O navegador tem sensor de orientação (e é `https`, sem o qual ele some). */
export function hasMotionSensor(): boolean {
    return (
        typeof window !== 'undefined' &&
        window.isSecureContext &&
        'DeviceOrientationEvent' in window
    );
}

/** iPhone/iPad: o sensor só liga depois do pedido, dentro de um toque. */
export function motionNeedsPermission(): boolean {
    return (
        hasMotionSensor() &&
        typeof (DeviceOrientationEvent as PermissionedOrientation)
            .requestPermission === 'function'
    );
}

/** Pede o sensor. Chamar só dentro de um toque. Fora do iPhone, sempre ok. */
export async function requestMotionPermission(): Promise<boolean> {
    if (!motionNeedsPermission()) {
        return hasMotionSensor();
    }

    try {
        return (
            (await (DeviceOrientationEvent as PermissionedOrientation)
                .requestPermission!()) === 'granted'
        );
    } catch {
        return false;
    }
}

export function isMotionEnabled(): boolean {
    try {
        const stored = localStorage.getItem(MOTION_KEY);

        if (stored !== null) {
            return stored === '1';
        }
    } catch {
        // sem localStorage: cai no padrão
    }

    return !motionNeedsPermission();
}

export function setMotionEnabled(enabled: boolean): void {
    try {
        localStorage.setItem(MOTION_KEY, enabled ? '1' : '0');
    } catch {
        // sem localStorage: vale só nesta visita
    }

    window.dispatchEvent(new Event(MOTION_EVENT));
}

/**
 * Liga ou desliga pelo interruptor (dentro do toque). No iPhone, ligar
 * pede a permissão; negada, fica desligado. Devolve o estado final.
 */
export async function toggleMotion(enabled: boolean): Promise<boolean> {
    const granted = enabled ? await requestMotionPermission() : false;
    setMotionEnabled(granted);

    return granted;
}
