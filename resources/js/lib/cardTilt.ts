import type { Directive } from 'vue';
import { onDeviceTilt } from '@/lib/deviceTilt';

/**
 * `v-card-tilt="ligado"`: a carta inclina seguindo o mouse e passa pro CSS
 * onde o cursor está (`--mx`/`--my` em %, `--tilt` 0→1), pro holo deslizar
 * e o brilho acompanhar. Só com mouse de verdade (hover + ponteiro fino) e
 * sem movimento reduzido: no toque a carta fica parada e o holo corre
 * sozinho pelo CSS. Nada roda enquanto o mouse não está em cima.
 *
 * `v-card-tilt.gyro`: no celular, a luz da arte da ficha (foil e brilho)
 * segue o giroscópio (`deviceTilt.ts`), com o mesmo `--mx`/`--my`/`--tilt`.
 * Sem girar o bloco: ele é parte do modal, e o sprite não se mexe (pedido
 * do usuário). A grade não usa: lá só o foil segue o celular, pelo CSS.
 */
const MAX_DEG = 8;
// O sobe/encolhe da carta (`translate`/`scale` das classes) segue com a
// mesma transição; só o `transform` do tilt muda de ritmo.
const LIFT = 'translate 0.2s ease-out, scale 0.2s ease-out';

type TiltElement = HTMLElement & {
    __tilt?: {
        enabled: boolean;
        frame: number;
        onMove: (event: PointerEvent) => void;
        onEnter: () => void;
        onLeave: () => void;
    };
    __gyro?: () => void;
};

function canTilt(): boolean {
    return (
        matchMedia('(hover: hover) and (pointer: fine)').matches &&
        !matchMedia('(prefers-reduced-motion: reduce)').matches
    );
}

function reset(el: TiltElement): void {
    el.style.transition = `transform 0.55s cubic-bezier(0.22, 1, 0.36, 1), ${LIFT}`;
    el.style.transform = '';
    el.style.setProperty('--tilt', '0');
    el.style.setProperty('--mx', '50%');
    el.style.setProperty('--my', '50%');
}

function followGyro(el: TiltElement): () => void {
    return onDeviceTilt(({ gx, gy }) => {
        if (gx === 0 && gy === 0) {
            reset(el);

            return;
        }

        el.style.setProperty('--tilt', '1');
        el.style.setProperty('--mx', `${(50 + gx * 50).toFixed(1)}%`);
        el.style.setProperty('--my', `${(50 + gy * 50).toFixed(1)}%`);
    });
}

export const vCardTilt: Directive<TiltElement, boolean> = {
    mounted(el, binding) {
        // `.gyro` é só do celular: com mouse a arte da ficha fica como era.
        if (binding.modifiers.gyro) {
            if (!canTilt() && binding.value) {
                el.__gyro = followGyro(el);
            }

            return;
        }

        if (!canTilt()) {
            return;
        }

        const state: NonNullable<TiltElement['__tilt']> = {
            enabled: binding.value,
            frame: 0,
            onEnter: () => {
                if (state.enabled) {
                    el.style.transition = `transform 0.12s ease-out, ${LIFT}`;
                }
            },
            onMove: (event) => {
                if (!state.enabled || state.frame) {
                    return;
                }

                const { clientX, clientY } = event;
                state.frame = requestAnimationFrame(() => {
                    state.frame = 0;
                    const box = el.getBoundingClientRect();
                    const x = Math.min(
                        Math.max((clientX - box.left) / box.width, 0),
                        1,
                    );
                    const y = Math.min(
                        Math.max((clientY - box.top) / box.height, 0),
                        1,
                    );
                    el.style.transform = `perspective(700px) rotateX(${((0.5 - y) * 2 * MAX_DEG).toFixed(2)}deg) rotateY(${((x - 0.5) * 2 * MAX_DEG).toFixed(2)}deg)`;
                    el.style.setProperty('--tilt', '1');
                    el.style.setProperty('--mx', `${(x * 100).toFixed(1)}%`);
                    el.style.setProperty('--my', `${(y * 100).toFixed(1)}%`);
                });
            },
            onLeave: () => {
                cancelAnimationFrame(state.frame);
                state.frame = 0;
                reset(el);
            },
        };

        el.__tilt = state;
        el.addEventListener('pointerenter', state.onEnter);
        el.addEventListener('pointermove', state.onMove);
        el.addEventListener('pointerleave', state.onLeave);
    },
    updated(el, binding) {
        if (el.__tilt) {
            el.__tilt.enabled = binding.value;
        }
    },
    beforeUnmount(el) {
        el.__gyro?.();
        delete el.__gyro;
        const state = el.__tilt;

        if (!state) {
            return;
        }

        cancelAnimationFrame(state.frame);
        el.removeEventListener('pointerenter', state.onEnter);
        el.removeEventListener('pointermove', state.onMove);
        el.removeEventListener('pointerleave', state.onLeave);
        delete el.__tilt;
    },
};
