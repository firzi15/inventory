/**
 * Web Serial API Driver untuk Timbangan Digital (RS-232 / USB)
 * Mendukung pembacaan timbangan industri (AND, Cas, Gigatech, dll)
 * Disertai simulator bobot untuk pengujian cepat tanpa hardware timbangan.
 */

class WeighScaleDriver {
    constructor() {
        this.port = null;
        this.reader = null;
        this.isConnected = false;
        this.onWeightCallback = null;
    }

    /**
     * Daftarkan callback saat berat baru diterima
     */
    onWeight(callback) {
        this.onWeightCallback = callback;
    }

    /**
     * Hubungkan ke port Serial/USB melalui dialog browser
     */
    async connectSerial(baudRate = 9600) {
        if (!('serial' in navigator)) {
            alert('Web Serial API tidak didukung pada browser ini. Gunakan Google Chrome atau Microsoft Edge versi terbaru.');
            return false;
        }

        try {
            this.port = await navigator.serial.requestPort();
            await this.port.open({ baudRate: baudRate });
            this.isConnected = true;
            this.readStream();
            return true;
        } catch (err) {
            console.error('Koneksi Timbangan Gagal:', err);
            return false;
        }
    }

    /**
     * Membaca stream data serial dari timbangan digital
     */
    async readStream() {
        const textDecoder = new TextDecoderStream();
        const readableStreamClosed = this.port.readable.pipeTo(textDecoder.writable);
        this.reader = textDecoder.readable.getReader();

        let buffer = '';
        try {
            while (true) {
                const { value, done } = await this.reader.read();
                if (done) break;
                if (value) {
                    buffer += value;
                    const lines = buffer.split('\r\n');
                    buffer = lines.pop(); // Sisakan potongan terakhir

                    for (const line of lines) {
                        this.parseWeightLine(line);
                    }
                }
            }
        } catch (err) {
            console.warn('Aliran serial terputus:', err);
        } finally {
            this.reader.releaseLock();
        }
    }

    /**
     * Parsing baris data timbangan (misal: "ST,GS,+0024.35kg" atau "24.35")
     */
    parseWeightLine(rawLine) {
        // Cari angka desimal dalam string
        const match = rawLine.match(/([0-9]+\.[0-9]+|[0-9]+)/);
        if (match && this.onWeightCallback) {
            const weightVal = parseFloat(match[0]);
            if (!isNaN(weightVal) && weightVal > 0) {
                this.onWeightCallback(weightVal, rawLine);
            }
        }
    }

    /**
     * Simulasi timbangan untuk kemudahan testing
     */
    simulateWeight(weightKg) {
        if (this.onWeightCallback) {
            this.onWeightCallback(parseFloat(weightKg), 'SIMULASI_TIMBANGAN');
        }
    }
}

window.weighScale = new WeighScaleDriver();
