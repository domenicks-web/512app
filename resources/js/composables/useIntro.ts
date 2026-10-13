import { prefersReducedMotion } from '@/lib/motion';

/**
 * Coordena a abertura (loading) com a página que está por baixo. A abertura
 * toca inteira uma vez por carregamento do app — F5, primeiro acesso, link
 * aberto do zero — em qualquer tela do fluxo de entrada, por mais rápido
 * que a página tenha carregado. Visitas do Inertia não tocam de novo: o
 * estado deste módulo e o layout persistente sobrevivem entre elas.
 *
 * A página chama `waitForIntro()` no `onMounted` (que roda antes do do
 * layout) e só começa a própria entrada quando ela resolve.
 */

type IntroResult = { played: boolean };

let decision: boolean | null = null;
let finished = false;
let flightTarget: Element | null = null;
let releasePage: (() => void) | null = null;
const pageReleased = new Promise<void>((resolve) => {
    releasePage = resolve;
});

/** Decidido uma vez só, no cliente, pelo primeiro que perguntar. */
export function introWillPlay(): boolean {
    if (decision === null) {
        decision = !finished && !prefersReducedMotion();
    }

    return decision && !finished;
}

/**
 * Espera a abertura terminar (se ela for tocar). `target` é pra onde o logo
 * voa no fim; sem alvo, o logo só some.
 */
export async function waitForIntro(
    target: Element | null = null,
): Promise<IntroResult> {
    if (!introWillPlay()) {
        return { played: false };
    }

    flightTarget = target;
    await pageReleased;

    return { played: true };
}

/** Usado pelo layout: o alvo que a página pediu e a liberação dela. */
export function introFlightTarget(): Element | null {
    return flightTarget;
}

export function releasePageAfterIntro(): void {
    releasePage?.();
}

export function markIntroFinished(): void {
    finished = true;
}
