import { useCallback, useEffect, useRef, useState } from 'react';

const STORAGE_KEY = 'kds.sound';

type AudioState = 'on' | 'off' | 'blocked';

/**
 * Plays a two-tone chime when an order id appears that was not on the screen
 * before. Browsers only allow sound after someone has touched the page, so
 * the hook reports "blocked" until the first tap or key press.
 *
 * Returns the ids that arrived in the last few seconds (for highlighting),
 * the sound state and a toggle.
 */
export function useOrderChime(ids: string[]): { fresh: Set<string>; sound: AudioState; toggle: () => void } {
    const context = useRef<AudioContext | null>(null);
    const known = useRef<Set<string> | null>(null);
    const [enabled, setEnabled] = useState(() => readPreference());
    const [unlocked, setUnlocked] = useState(false);
    const [fresh, setFresh] = useState<Set<string>>(new Set());

    const play = useCallback(() => {
        const audio = context.current;

        if (!audio || audio.state !== 'running') return;

        // Two rising notes, repeated once: audible over an extractor fan without being a siren.
        [0, 0.18, 0.5, 0.68].forEach((offset, index) => {
            const oscillator = audio.createOscillator();
            const gain = audio.createGain();
            const start = audio.currentTime + offset;

            oscillator.type = 'triangle';
            oscillator.frequency.value = index % 2 === 0 ? 880 : 1320;
            gain.gain.setValueAtTime(0.0001, start);
            gain.gain.exponentialRampToValueAtTime(0.5, start + 0.02);
            gain.gain.exponentialRampToValueAtTime(0.0001, start + 0.16);
            oscillator.connect(gain).connect(audio.destination);
            oscillator.start(start);
            oscillator.stop(start + 0.18);
        });
    }, []);

    // Create / resume the audio context on the first interaction with the page.
    useEffect(() => {
        const unlock = () => {
            const AudioCtor = window.AudioContext ?? (window as unknown as { webkitAudioContext?: typeof AudioContext }).webkitAudioContext;

            if (!AudioCtor) return;

            context.current ??= new AudioCtor();
            void context.current.resume().then(() => setUnlocked(context.current?.state === 'running'));
        };

        window.addEventListener('pointerdown', unlock);
        window.addEventListener('keydown', unlock);

        return () => {
            window.removeEventListener('pointerdown', unlock);
            window.removeEventListener('keydown', unlock);
        };
    }, []);

    const key = ids.join('|');

    useEffect(() => {
        // The first render only records what is already on screen.
        if (known.current === null) {
            known.current = new Set(ids);

            return;
        }

        const arrived = ids.filter((id) => !known.current!.has(id));
        known.current = new Set(ids);

        if (arrived.length === 0) return;

        if (enabled) play();

        setFresh((current) => new Set([...current, ...arrived]));
        const timer = window.setTimeout(() => setFresh((current) => new Set([...current].filter((id) => !arrived.includes(id)))), 12000);

        return () => window.clearTimeout(timer);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [key]);

    const toggle = useCallback(() => {
        setEnabled((current) => {
            const next = !current;

            try {
                window.localStorage.setItem(STORAGE_KEY, next ? 'on' : 'off');
            } catch {
                // Preference simply is not remembered.
            }

            if (next) window.setTimeout(play, 50);

            return next;
        });
    }, [play]);

    return { fresh, sound: !enabled ? 'off' : unlocked ? 'on' : 'blocked', toggle };
}

function readPreference(): boolean {
    try {
        return window.localStorage.getItem(STORAGE_KEY) !== 'off';
    } catch {
        return true;
    }
}
