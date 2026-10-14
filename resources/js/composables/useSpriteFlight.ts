import { animate, spring } from '@/lib/motion';

/**
 * Animação do handoff ao escolher um parceiro: um clone do sprite escolhido
 * voa em arco (620ms) até o avatar e o avatar dá um pop ao receber. Usada
 * no onboarding e no editar perfil. `onLand` troca o sprite do avatar no
 * momento do pouso.
 */
export function flySpriteTo(
    sprite: HTMLElement,
    target: HTMLElement,
    onLand: () => void,
): void {
    const from = sprite.getBoundingClientRect();
    const to = target.getBoundingClientRect();
    const ghost = sprite.cloneNode(true) as HTMLElement;
    Object.assign(ghost.style, {
        position: 'fixed',
        left: `${from.left}px`,
        top: `${from.top}px`,
        width: `${from.width}px`,
        height: `${from.height}px`,
        margin: '0',
        zIndex: '60',
        pointerEvents: 'none',
        transform: 'none',
    });
    document.body.appendChild(ghost);

    const dx = to.left + to.width / 2 - (from.left + from.width / 2);
    const dy = to.top + to.height / 2 - (from.top + from.height / 2);
    const scale = to.width / from.width;
    const flight = animate(
        ghost,
        [
            { transform: 'none' },
            {
                transform: `translate(${dx * 0.45}px, ${dy * 0.5 - 70}px) scale(1.15) rotate(-24deg)`,
                offset: 0.45,
            },
            {
                transform: `translate(${dx}px, ${dy}px) scale(${scale}) rotate(0deg)`,
            },
        ],
        620,
        { easing: 'cubic-bezier(.45,0,.3,1)', fill: 'forwards' },
    );

    const land = () => {
        ghost.remove();
        onLand();
        animate(
            target,
            [{ transform: 'scale(1.35)' }, { transform: 'none' }],
            520,
            {
                easing: spring(320, 12),
                fill: 'none',
            },
        );
    };

    if (flight) {
        flight.onfinish = land;
    } else {
        land();
    }
}
