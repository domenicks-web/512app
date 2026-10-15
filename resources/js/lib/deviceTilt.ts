import {
    MOTION_EVENT,
    hasMotionSensor,
    isMotionEnabled,
    motionNeedsPermission,
    requestMotionPermission,
} from '@/lib/motionPreference';

/**
 * Inclinação do celular pelo giroscópio, um leitor só pra página toda. A
 * posição em que o celular está quando o leitor liga é o "reto" (deitado,
 * em pé, tanto faz) e ele se ajusta devagar se a pegada mudar, pra carta
 * não ficar torta. Publica `gx`/`gy` de -1 a 1 pros inscritos e, no
 * `<html>`, `--mx`/`--my` (o foil da grade herda) e a classe `has-gyro`.
 * Só efeito visual: nada aqui decide resultado.
 */
type Tilt = { gx: number; gy: number };
type Listener = (tilt: Tilt) => void;

/** Graus de inclinação pra ir de 0 a 1. */
const RANGE_DEG = 22;
/** Quanto o "reto" anda na direção da pegada atual a cada leitura. */
const DRIFT = 0.004;

const listeners = new Set<Listener>();
let active = false;
let base: { beta: number; gamma: number } | null = null;
let current: Tilt = { gx: 0, gy: 0 };
let frame = 0;

const clamp = (value: number) => Math.min(Math.max(value, -1), 1);

/**
 * Onde o reflexo fica pra uma inclinação. Parado (0, 0), fora da carta,
 * pra não ficar em cima do sprite de quem não quer girar o celular; entra
 * conforme inclina. `shine`: posição da faixa da grade (0% é fora).
 * `x`/`y`: centro do brilho da ficha (y negativo é acima do topo).
 */
export function glareFor(
    gx: number,
    gy: number,
): {
    shine: number;
    x: number;
    y: number;
} {
    const amount = Math.min(Math.hypot(gx, gy), 1);

    return {
        shine: Math.min(Math.max(amount * 50 + gx * 8, 0), 100),
        x: 50 + gx * 50,
        y: -35 + amount * 85,
    };
}

function reducedMotion(): boolean {
    return matchMedia('(prefers-reduced-motion: reduce)').matches;
}

/** Em paisagem o eixo do celular gira junto com a tela. */
function screenAxes(beta: number, gamma: number): [number, number] {
    const angle = screen.orientation?.angle ?? 0;

    if (angle === 90) {
        return [-gamma, beta];
    }

    if (angle === 270 || angle === -90) {
        return [gamma, -beta];
    }

    if (angle === 180) {
        return [-beta, -gamma];
    }

    return [beta, gamma];
}

function publish(): void {
    frame = 0;
    const root = document.documentElement;
    root.style.setProperty('--mx', `${(50 + current.gx * 50).toFixed(1)}%`);
    root.style.setProperty('--my', `${(50 + current.gy * 50).toFixed(1)}%`);
    root.style.setProperty(
        '--shine',
        `${glareFor(current.gx, current.gy).shine.toFixed(1)}%`,
    );
    listeners.forEach((listener) => listener(current));
}

function onOrientation(event: DeviceOrientationEvent): void {
    if (event.beta === null || event.gamma === null) {
        return;
    }

    const [beta, gamma] = screenAxes(event.beta, event.gamma);

    if (base === null) {
        base = { beta, gamma };
        document.documentElement.classList.add('has-gyro');
    }

    base.beta += (beta - base.beta) * DRIFT;
    base.gamma += (gamma - base.gamma) * DRIFT;
    current = {
        gx: clamp((gamma - base.gamma) / RANGE_DEG),
        gy: clamp((beta - base.beta) / RANGE_DEG),
    };

    if (!frame) {
        frame = requestAnimationFrame(publish);
    }
}

/** Voltou pro app: a pegada pode ser outra, recalibra. */
function onVisibility(): void {
    if (document.visibilityState === 'visible') {
        base = null;
    }
}

/** No iPhone a permissão vale por visita: o primeiro toque a renova. */
function renewOnTouch(): void {
    void requestMotionPermission().then((granted) => granted && start());
}

function start(): void {
    if (active) {
        return;
    }

    active = true;
    window.addEventListener('deviceorientation', onOrientation);
    document.addEventListener('visibilitychange', onVisibility);
}

function stop(): void {
    if (!active) {
        return;
    }

    active = false;
    base = null;
    cancelAnimationFrame(frame);
    frame = 0;
    window.removeEventListener('deviceorientation', onOrientation);
    document.removeEventListener('visibilitychange', onVisibility);
    const root = document.documentElement;
    root.classList.remove('has-gyro');
    root.style.removeProperty('--mx');
    root.style.removeProperty('--my');
    root.style.removeProperty('--shine');
    current = { gx: 0, gy: 0 };
    listeners.forEach((listener) => listener(current));
}

function sync(): void {
    if (!hasMotionSensor() || reducedMotion() || !isMotionEnabled()) {
        stop();

        return;
    }

    if (motionNeedsPermission()) {
        document.addEventListener('touchend', renewOnTouch, { once: true });
    }

    // No iPhone, sem permissão nesta visita o evento só não chega.
    start();
}

let installed = false;

/**
 * Liga o leitor (uma vez por página) e segue a preferência: o
 * interruptor de Ajustes e do cadastro liga e desliga na hora.
 */
export function installDeviceTilt(): void {
    if (installed || typeof window === 'undefined') {
        return;
    }

    installed = true;
    window.addEventListener(MOTION_EVENT, sync);
    matchMedia('(prefers-reduced-motion: reduce)').addEventListener(
        'change',
        sync,
    );
    sync();
}

/** Recebe a inclinação a cada quadro. Devolve o cancelamento. */
export function onDeviceTilt(listener: Listener): () => void {
    listeners.add(listener);

    return () => listeners.delete(listener);
}
