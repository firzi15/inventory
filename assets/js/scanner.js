/**
 * Barcode Scanner Listener & Web Audio Beeper
 * Menangani input barcode dari USB/Bluetooth scanner genggam
 * Memainkan nada beep sukses/gagal secara mandiri menggunakan Web Audio API.
 */

class BarcodeScannerHandler {
    constructor() {
        this.audioCtx = null;
        this.initAudio();
    }

    initAudio() {
        try {
            const AudioContext = window.AudioContext || window.webkitAudioContext;
            if (AudioContext) {
                this.audioCtx = new AudioContext();
            }
        } catch (e) {
            console.warn('Web Audio API not supported', e);
        }
    }

    /**
     * Mainkan nada "BEEP" sukses (Frekuensi tinggi jernih 880 Hz)
     */
    playSuccessBeep() {
        if (!this.audioCtx) return;
        if (this.audioCtx.state === 'suspended') this.audioCtx.resume();

        const osc = this.audioCtx.createOscillator();
        const gain = this.audioCtx.createGain();
        osc.type = 'sine';
        osc.frequency.setValueAtTime(880, this.audioCtx.currentTime); // A5 note

        gain.gain.setValueAtTime(0.15, this.audioCtx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.001, this.audioCtx.currentTime + 0.12);

        osc.connect(gain);
        gain.connect(this.audioCtx.destination);

        osc.start();
        osc.stop(this.audioCtx.currentTime + 0.12);
    }

    /**
     * Mainkan nada "BUZZ" peringatan/error (Frekuensi rendah 220 Hz ganda)
     */
    playErrorBuzz() {
        if (!this.audioCtx) return;
        if (this.audioCtx.state === 'suspended') this.audioCtx.resume();

        const osc = this.audioCtx.createOscillator();
        const gain = this.audioCtx.createGain();
        osc.type = 'sawtooth';
        osc.frequency.setValueAtTime(220, this.audioCtx.currentTime);

        gain.gain.setValueAtTime(0.2, this.audioCtx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.001, this.audioCtx.currentTime + 0.35);

        osc.connect(gain);
        gain.connect(this.audioCtx.destination);

        osc.start();
        osc.stop(this.audioCtx.currentTime + 0.35);
    }
}

window.scannerAudio = new BarcodeScannerHandler();
