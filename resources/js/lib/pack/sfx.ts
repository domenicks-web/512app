import type { Tier } from './types';

/**
 * Sons da abertura, sintetizados com Web Audio (o `class SFX` do
 * protótipo, mesmas frequências e envelopes). Só toca depois de um gesto
 * do jogador (`init` no primeiro toque).
 */
type ToneOptions = {
    to?: number;
    slide?: number;
    type?: OscillatorType;
    g?: number;
    a?: number;
    d?: number;
};
type NoiseOptions = {
    type?: BiquadFilterType;
    q?: number;
    f0?: number;
    f1?: number;
    g?: number;
    a?: number;
    d?: number;
};

const TIER_CODE: Record<Tier, 'c' | 'i' | 'r' | 'l'> = {
    comum: 'c',
    incomum: 'i',
    raro: 'r',
    lendario: 'l',
};

export class Sfx {
    on = true;
    private ctx: AudioContext | null = null;
    private out: GainNode | null = null;
    private noiseBuffer: AudioBuffer | null = null;
    private rumbleNodes: {
        o: OscillatorNode;
        f: BiquadFilterNode;
        g: GainNode;
    } | null = null;

    init(): void {
        try {
            if (!this.ctx) {
                const Context =
                    window.AudioContext ??
                    (
                        window as unknown as {
                            webkitAudioContext?: typeof AudioContext;
                        }
                    ).webkitAudioContext;

                if (!Context) {
                    return;
                }

                this.ctx = new Context();
                this.out = this.ctx.createGain();
                this.out.gain.value = 0.55;
                const compressor = this.ctx.createDynamicsCompressor();
                this.out.connect(compressor);
                compressor.connect(this.ctx.destination);
            }

            if (this.ctx.state === 'suspended') {
                void this.ctx.resume();
            }
        } catch {
            // Sem áudio: a abertura segue muda.
        }
    }

    close(): void {
        try {
            void this.ctx?.close();
        } catch {
            // ignora
        }
    }

    private ok(): boolean {
        return this.ctx !== null && this.out !== null && this.on;
    }

    tone(f: number, dur: number, o: ToneOptions = {}): void {
        if (!this.ok() || !this.ctx || !this.out) {
            return;
        }

        const c = this.ctx;
        const t = c.currentTime + (o.d ?? 0);
        const osc = c.createOscillator();
        const g = c.createGain();
        osc.type = o.type ?? 'sine';
        osc.frequency.setValueAtTime(f, t);

        if (o.to) {
            osc.frequency.exponentialRampToValueAtTime(
                o.to,
                t + (o.slide ?? dur),
            );
        }

        g.gain.setValueAtTime(0.0001, t);
        g.gain.exponentialRampToValueAtTime(o.g ?? 0.2, t + (o.a ?? 0.005));
        g.gain.exponentialRampToValueAtTime(0.0001, t + dur);
        osc.connect(g);
        g.connect(this.out);
        osc.start(t);
        osc.stop(t + dur + 0.05);
    }

    noise(dur: number, o: NoiseOptions = {}): void {
        if (!this.ok() || !this.ctx || !this.out) {
            return;
        }

        const c = this.ctx;
        const t = c.currentTime + (o.d ?? 0);

        if (!this.noiseBuffer) {
            const buffer = c.createBuffer(1, c.sampleRate * 2, c.sampleRate);
            const data = buffer.getChannelData(0);

            for (let i = 0; i < data.length; i++) {
                data[i] = Math.random() * 2 - 1;
            }

            this.noiseBuffer = buffer;
        }

        const source = c.createBufferSource();
        const filter = c.createBiquadFilter();
        const g = c.createGain();
        source.buffer = this.noiseBuffer;
        filter.type = o.type ?? 'lowpass';
        filter.Q.value = o.q ?? 1;
        filter.frequency.setValueAtTime(o.f0 ?? 2000, t);

        if (o.f1) {
            filter.frequency.exponentialRampToValueAtTime(o.f1, t + dur);
        }

        g.gain.setValueAtTime(0.0001, t);
        g.gain.exponentialRampToValueAtTime(o.g ?? 0.2, t + (o.a ?? 0.01));
        g.gain.exponentialRampToValueAtTime(0.0001, t + dur);
        source.connect(filter);
        filter.connect(g);
        g.connect(this.out);
        source.start(t);
        source.stop(t + dur + 0.05);
    }

    rumble(v: number): void {
        if (!this.ctx || !this.out) {
            return;
        }

        if (!this.rumbleNodes) {
            const c = this.ctx;
            const o = c.createOscillator();
            const f = c.createBiquadFilter();
            const g = c.createGain();
            o.type = 'sawtooth';
            o.frequency.value = 40;
            f.type = 'lowpass';
            f.frequency.value = 160;
            g.gain.value = 0;
            o.connect(f);
            f.connect(g);
            g.connect(this.out);
            o.start();
            this.rumbleNodes = { o, f, g };
        }

        const t = this.ctx.currentTime;
        this.rumbleNodes.g.gain.setTargetAtTime(
            this.on ? v * v * 0.2 : 0,
            t,
            0.04,
        );
        this.rumbleNodes.o.frequency.setTargetAtTime(38 + v * 70, t, 0.04);
        this.rumbleNodes.f.frequency.setTargetAtTime(140 + v * 520, t, 0.04);
    }

    tick(v: number): void {
        this.tone(700 + v * 1100, 0.035, {
            type: 'square',
            g: 0.025 + v * 0.045,
        });
    }

    burst(): void {
        this.noise(0.9, { f0: 6000, f1: 120, g: 0.6 });
        this.tone(92, 0.65, { to: 30, g: 0.75 });
        this.tone(1800, 0.25, { to: 300, g: 0.08, type: 'triangle' });
    }

    land(i: number): void {
        this.tone(250 + i * 28, 0.09, { type: 'triangle', g: 0.14, to: 170 });
    }

    pop(i: number, tier: Tier): void {
        const base = 520 + i * 70;
        this.tone(base, 0.14, { to: base * 1.9, slide: 0.07, g: 0.18 });
        this.noise(0.07, { type: 'highpass', f0: 3000, g: 0.05 });

        if (tier !== 'comum') {
            this.tone(base * 2, 0.28, { g: 0.06, d: 0.06, type: 'triangle' });
        }
    }

    whoosh(up: boolean): void {
        this.noise(0.8, {
            type: 'bandpass',
            f0: up ? 300 : 2400,
            f1: up ? 2400 : 250,
            q: 1.2,
            g: 0.14,
        });
    }

    freeze(): void {
        this.tone(150, 0.9, { to: 55, g: 0.2 });
        this.noise(0.5, { f0: 900, f1: 90, g: 0.08 });
    }

    tok(k: number): void {
        this.tone(215 - k * 12, 0.11, { type: 'triangle', g: 0.32, to: 150 });
        this.noise(0.04, { type: 'highpass', f0: 2500, g: 0.06 });
    }

    open(): void {
        this.tone(330, 0.5, {
            to: 880,
            slide: 0.35,
            g: 0.12,
            type: 'triangle',
        });
        this.noise(0.5, { type: 'bandpass', f0: 600, f1: 5200, g: 0.1 });
    }

    rise(): void {
        this.tone(110, 1.5, {
            to: 330,
            slide: 1.4,
            g: 0.08,
            a: 0.3,
            type: 'triangle',
        });
    }

    chord(tier: Tier, shiny: boolean): void {
        const sets = {
            c: [523.25, 659.25, 783.99],
            i: [523.25, 659.25, 783.99],
            r: [523.25, 659.25, 783.99, 1046.5],
            l: [392, 493.88, 587.33, 783.99, 987.77],
        };
        const code = TIER_CODE[tier];
        const length = code === 'l' ? 2.8 : 1.9;
        sets[code].forEach((f, k) => {
            this.tone(f, length, { g: 0.08, d: k * 0.07, a: 0.02 });
            this.tone(f / 2, length, {
                g: 0.045,
                d: k * 0.07,
                type: 'triangle',
                a: 0.03,
            });
        });

        if (code === 'l') {
            this.tone(62, 2.6, { g: 0.35, a: 0.01 });
        }

        if (shiny) {
            [1568, 2093, 2637, 3136, 4186].forEach((f, k) =>
                this.tone(f, 0.55, {
                    type: 'triangle',
                    g: 0.05,
                    d: 0.12 + k * 0.055,
                }),
            );
        }
    }

    glint(): void {
        this.tone(2600 + Math.random() * 900, 0.16, {
            type: 'triangle',
            g: 0.035,
        });
    }

    /**
     * Aviso em tempo real (bolha do dock): plim de duas notas que sobe nas
     * boas notícias e desce na recusa ou na troca que caiu; o destaque do
     * feed toca o acorde da raridade.
     */
    notice(kind: string, tier: Tier | null, shiny: boolean): void {
        if (kind === 'feed_highlight' && tier) {
            this.chord(tier, shiny);

            return;
        }

        const down = kind === 'trade_declined' || kind === 'trade_fallen';
        const [first, second] = down ? [784, 587.33] : [659.25, 987.77];
        this.tone(first, 0.18, { type: 'triangle', g: 0.12 });
        this.tone(second, 0.34, { type: 'triangle', g: 0.12, d: 0.11 });
    }

    summary(): void {
        [659.25, 880, 1318.5].forEach((f, k) =>
            this.tone(f, 1.1, { g: 0.06, d: k * 0.09, a: 0.02 }),
        );
    }
}
