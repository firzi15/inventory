// Master Harga (pemasok & mitra): kirim HANYA kolom harga yang berubah.
// Matriks produk x pemasok/mitra bisa ribuan input, sedangkan PHP membatasi jumlah field per request
// (max_input_vars, default 1000) dan membuang sisanya secara diam-diam. Dengan hanya mengirim yang
// berubah, ukuran request kecil dan tidak ada lagi harga yang hilang. Kalau yang berubah masih terlalu
// banyak (mis. isi awal), dikirim bertahap per paket.
(function () {
    var UKURAN_PAKET = 400;

    function angka(v) {
        v = String(v).trim();
        return v === '' ? 0 : parseFloat(v);
    }

    function pasang(form) {
        if (!form) return;
        var kirimBertahap = false;

        form.addEventListener('submit', function (e) {
            if (kirimBertahap) return;
            var inputs = Array.prototype.slice.call(form.querySelectorAll('input[type="number"][name^="harga"]'));
            var berubah = inputs.filter(function (el) { return angka(el.value) !== angka(el.defaultValue); });

            if (!berubah.length) {
                e.preventDefault();
                alert('Tidak ada harga yang diubah.');
                return;
            }

            if (berubah.length <= UKURAN_PAKET) {
                // Cukup satu request: matikan input yang tidak berubah supaya tidak ikut terkirim.
                inputs.forEach(function (el) { if (berubah.indexOf(el) < 0) el.disabled = true; });
                return;
            }

            // Banyak yang berubah: kirim per paket lewat fetch.
            e.preventDefault();
            var alasan = form.querySelector('input[name="alasan_perubahan"]');
            var tombol = form.querySelector('button[type="submit"]');
            if (tombol) tombol.disabled = true;
            var paket = [];
            for (var i = 0; i < berubah.length; i += UKURAN_PAKET) paket.push(berubah.slice(i, i + UKURAN_PAKET));

            var urlAkhir = null;
            var idx = 0;
            (function kirim() {
                if (idx >= paket.length) { location.href = urlAkhir || location.href; return; }
                var fd = new FormData();
                if (alasan) fd.append('alasan_perubahan', alasan.value);
                paket[idx].forEach(function (el) { fd.append(el.name, el.value); });
                fetch(form.action, { method: 'POST', body: fd, credentials: 'same-origin' })
                    .then(function (r) {
                        if (!r.ok) throw new Error('HTTP ' + r.status);
                        urlAkhir = r.url;
                        idx++;
                        if (tombol) tombol.querySelector('span').textContent = 'Menyimpan ' + Math.min(idx * UKURAN_PAKET, berubah.length) + '/' + berubah.length + '...';
                        kirim();
                    })
                    .catch(function (err) {
                        if (tombol) tombol.disabled = false;
                        alert('Gagal menyimpan paket ' + (idx + 1) + ' dari ' + paket.length + ' (' + err.message + '). Paket sebelumnya sudah tersimpan; coba simpan lagi.');
                    });
            })();
        });

        // Tombol Kembali di browser: aktifkan lagi input yang tadi dimatikan.
        window.addEventListener('pageshow', function (ev) {
            if (!ev.persisted) return;
            form.querySelectorAll('input:disabled').forEach(function (el) { el.disabled = false; });
        });
    }

    function init() {
        pasang(document.getElementById('formBulkHargaPemasok'));
        pasang(document.getElementById('formBulkHargaMitra'));
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();
