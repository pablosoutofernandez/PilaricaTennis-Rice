/**
 * Pantalla de entrada al torneo para el público: eliges quién eres y una ruleta
 * descubre con quién te ha tocado jugar. Solo sale la primera vez en cada navegador.
 *
 * @param {{ key: string, players: Array<{ name: string, partner: string, number: ?number, group: ?string }> }} config
 */
window.tournamentEntry = ({ key, players }) => ({
    open: false,
    step: 'welcome',
    chosen: '',
    player: null,
    slices: [],
    confetti: [],

    init() {
        // Con ?entrada en la dirección vuelve a salir aunque ya se haya visto.
        this.open = new URLSearchParams(window.location.search).has('entrada') || !hasEntered(key);
        this.lockScroll(this.open);
        this.$watch('open', (open) => this.lockScroll(open));
    },

    lockScroll(locked) {
        document.documentElement.classList.toggle('overflow-hidden', locked);
    },

    close() {
        rememberEntry(key);
        this.open = false;
    },

    discover() {
        this.player = players.find((player) => player.name === this.chosen) ?? null;
        if (!this.player) {
            return;
        }

        const isTaken = (name) => [this.player.name, this.player.partner].some((taken) => sameName(taken, name));
        const others = shuffle(
            players.map((player) => player.name).filter((name) => !isTaken(name) && !sameName(name, DECOY)),
        );
        // El señuelo va justo antes del compañero: la ruleta casi se para en él, pero cae en el bueno.
        const decoy = isTaken(DECOY) ? (others.pop() ?? '¿?') : DECOY;
        const names = others.slice(0, 8);
        names.splice(Math.floor(Math.random() * (names.length + 1)), 0, this.player.partner, decoy);
        let slices = names;
        while (slices.length < 6) {
            slices = slices.concat(names);
        }
        this.slices = slices;
        this.step = 'spin';
        // El sonido tiene que prepararse en el propio clic, o el móvil lo bloquea.
        clicker = new Clicker();

        this.$nextTick(() => {
            this.$refs.wheel.style.transform = 'rotate(0deg)';
            this.$refs.needle.style.rotate = '0deg';
            setTimeout(() => this.spin(), 350);
        });
    },

    spin() {
        const angle = 360 / this.slices.length;
        // Bajo la aguja las casillas van hacia atrás: tras el señuelo llega el compañero por este borde.
        const border = (this.slices.indexOf(this.player.partner) + 1) * angle;
        const trajectory = spinTrajectory(border);

        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            this.$refs.wheel.style.transform = `rotate(${trajectory.rotationAt(trajectory.duration)}deg)`;
            setTimeout(() => this.reveal(), 600);

            return;
        }

        const needle = new Needle(angle);
        const start = performance.now();
        let last = start;
        const frame = (now) => {
            const elapsed = (now - start) / 1000;
            const rotation = trajectory.rotationAt(elapsed);
            this.$refs.wheel.style.transform = `rotate(${rotation}deg)`;
            if (needle.update(rotation, (now - last) / 1000)) {
                clicker.play();
            }
            this.$refs.needle.style.rotate = `${needle.angle}deg`;
            last = now;

            if (elapsed < trajectory.duration + 1.2) {
                requestAnimationFrame(frame);
            }
            if (elapsed >= trajectory.duration + 0.7) {
                this.reveal();
            }
        };
        requestAnimationFrame(frame);
    },

    reveal() {
        if (this.step !== 'spin') {
            return;
        }
        this.step = 'reveal';
        const colors = ['#f9822b', '#eef56a', '#ffffff', '#ffbe85', '#e2ec2f'];
        this.confetti = Array.from({ length: 70 }, (_, index) => ({
            style: `left:${Math.random() * 100}%;background:${colors[index % colors.length]};`
                + `--delay:${Math.random() * 0.6}s;--duration:${2.2 + Math.random() * 1.8}s;`
                + `--drift:${(Math.random() - 0.5) * 30}vw;`,
        }));
    },

    pinStyle(index) {
        return `transform: rotate(${index * (360 / this.slices.length) - 90}deg)`;
    },

    sliceStyle(index) {
        const angle = 360 / this.slices.length;
        return `transform: rotate(${index * angle + angle / 2 - 90}deg)`;
    },

    sliceLight(index) {
        return this.sliceColor(index) !== 'var(--color-brand-500)';
    },

    sliceColor(index) {
        const palette = ['var(--color-brand-500)', 'var(--color-ball-300)'];
        // Con un número impar de porciones, la última no repite color con la primera.
        if (this.slices.length % 2 === 1 && index === this.slices.length - 1) {
            return 'var(--color-brand-200)';
        }

        return palette[index % 2];
    },

    wheelStyle() {
        const angle = 360 / this.slices.length;
        const stops = this.slices.map((_, index) => `${this.sliceColor(index)} ${index * angle}deg ${(index + 1) * angle}deg`);

        return `background: conic-gradient(${stops.join(', ')});`;
    },
});

/** El nombre en el que la ruleta está a punto de caer, para darle emoción. */
const DECOY = 'Nacho';

const enteredInMemory = new Set();

function sameName(first, second) {
    return first.localeCompare(second, 'es', { sensitivity: 'base' }) === 0;
}

/**
 * Giro de la ruleta en función del tiempo, con velocidad siempre continua para que parezca real:
 * un pequeño impulso hacia atrás, una frenada larga, la aguja que la frena casi del todo
 * contra el tope del señuelo y, al saltarlo, un último empujón que la deja en el compañero.
 *
 * @param {number} border Ángulo de la ruleta (desde arriba) del borde entre el señuelo y el compañero.
 */
function spinTrajectory(border) {
    const windUp = { time: 0.35, distance: 10 };
    const glide = { time: 6, endSpeed: 18 };
    const fight = { distance: NEEDLE_REACH, endSpeed: 1.2 };
    const settle = { time: 0.9, distance: 7 };

    // Giro en el que la aguja cruza el borde: siempre unas seis vueltas.
    const crossing = 360 * Math.ceil((2200 + border) / 360) - border;
    const glideDistance = crossing - fight.distance + windUp.distance;
    const startSpeed = ((glideDistance - glide.endSpeed * glide.time) * 4) / glide.time + glide.endSpeed;
    fight.time = fight.distance / (fight.endSpeed + (glide.endSpeed - fight.endSpeed) / 3);

    const phases = [
        // Hacia atrás y suelta: ease-out hasta -windUp.distance.
        [windUp.time, (t) => -windUp.distance * (1 - (1 - t / windUp.time) ** 2)],
        // Frenada larga: la velocidad cae como (1 - t/T)³ hasta glide.endSpeed.
        [glide.time, (t) => -windUp.distance + glide.endSpeed * t
            + ((startSpeed - glide.endSpeed) * glide.time / 4) * (1 - (1 - t / glide.time) ** 4)],
        // Contra la aguja: casi se para justo en el tope.
        [fight.time, (t) => crossing - fight.distance + fight.endSpeed * t
            + ((glide.endSpeed - fight.endSpeed) * fight.time / 3) * (1 - (1 - t / fight.time) ** 3)],
        // Salta el tope: la aguja la empuja un poco y se para dentro del compañero.
        [settle.time, (t) => crossing + settle.distance * (1 - (1 - t / settle.time) ** 3)],
    ];
    const duration = phases.reduce((total, [time]) => total + time, 0);

    return {
        duration,
        rotationAt(elapsed) {
            let t = Math.max(elapsed, 0);
            for (const [time, position] of phases) {
                if (t <= time) {
                    return position(t);
                }
                t -= time;
            }

            return crossing + settle.distance;
        },
    };
}

let clicker = null;

/** Grados antes de cada tope en los que este ya empuja la aguja. */
const NEEDLE_REACH = 8;

/** Aguja con muelle: la doblan los topes y, al soltarse, rebota hasta pararse. */
class Needle {
    constructor(sliceAngle) {
        this.sliceAngle = sliceAngle;
        this.angle = 0;
        this.speed = 0;
        this.previousPeg = null;
    }

    /** Devuelve true si acaba de pasar un tope (para el clic). */
    update(rotation, seconds) {
        const underNeedle = ((-rotation % 360) + 360) % 360;
        const toPeg = underNeedle % this.sliceAngle;
        const peg = Math.floor(underNeedle / this.sliceAngle);
        const target = toPeg < NEEDLE_REACH ? -28 * (1 - toPeg / NEEDLE_REACH) : 0;

        // Muelle amortiguado, en pasos pequeños para que no se descontrole si un fotograma tarda.
        const steps = Math.max(1, Math.ceil(Math.min(seconds, 0.1) / 0.004));
        const dt = Math.min(seconds, 0.1) / steps;
        for (let i = 0; i < steps; i++) {
            const acceleration = 900 * (target - this.angle) - 26 * this.speed;
            this.speed += acceleration * dt;
            this.angle += this.speed * dt;
        }
        // La aguja no atraviesa el tope que la está empujando.
        if (target < 0) {
            this.angle = Math.min(this.angle, target);
        }

        const passed = this.previousPeg !== null && peg !== this.previousPeg;
        this.previousPeg = peg;

        return passed;
    }
}

/** Clic corto de la aguja contra cada tope; si el navegador no deja sonar, no pasa nada. */
class Clicker {
    constructor() {
        this.lastAt = 0;
        try {
            this.context = new (window.AudioContext || window.webkitAudioContext)();
            const length = Math.floor(this.context.sampleRate * 0.012);
            this.buffer = this.context.createBuffer(1, length, this.context.sampleRate);
            const data = this.buffer.getChannelData(0);
            for (let i = 0; i < length; i++) {
                data[i] = (Math.random() * 2 - 1) * (1 - i / length) ** 3;
            }
            this.context.resume();
        } catch {
            this.context = null;
        }
    }

    play() {
        if (!this.context || this.context.currentTime - this.lastAt < 0.03) {
            return;
        }
        this.lastAt = this.context.currentTime;
        const source = this.context.createBufferSource();
        const filter = this.context.createBiquadFilter();
        const volume = this.context.createGain();
        source.buffer = this.buffer;
        filter.type = 'bandpass';
        filter.frequency.value = 2400;
        volume.gain.value = 0.35;
        source.connect(filter).connect(volume).connect(this.context.destination);
        source.start();
    }
}

function hasEntered(key) {
    try {
        if (window.localStorage.getItem(key)) {
            return true;
        }
    } catch {
        // Sin almacenamiento (modo privado...): se recuerda mientras dure la visita.
    }

    return enteredInMemory.has(key);
}

function rememberEntry(key) {
    enteredInMemory.add(key);
    try {
        window.localStorage.setItem(key, '1');
    } catch {
        // Ver hasEntered().
    }
}

function shuffle(items) {
    const copy = [...items];
    for (let index = copy.length - 1; index > 0; index--) {
        const other = Math.floor(Math.random() * (index + 1));
        [copy[index], copy[other]] = [copy[other], copy[index]];
    }

    return copy;
}
