// Dibuka dari pintasan di halaman Penerimaan / Pengeluaran: gulir ke baris produk di Master Harga,
// sorot barisnya, dan taruh kursor di kolom pemasok / mitra yang dimaksud.
(function () {
    var q = new URLSearchParams(location.search);
    var item = q.get('item');
    if (!item) return;
    var pihak = q.get('pihak');
    function jalan() {
        var baris = document.getElementById('hrow-' + item);
        if (!baris) return;
        baris.classList.add('fokus-harga');
        var input = null;
        if (pihak) {
            input = baris.querySelector('input[name="harga[' + pihak + '][' + item + ']"], input[name="harga_pemasok[' + pihak + '][' + item + ']"]');
        }
        if (!input) input = baris.querySelector('input[type="number"]');
        baris.scrollIntoView({ block: 'center' });
        if (input) { input.focus({ preventScroll: true }); input.select(); input.classList.add('fokus-harga-input'); }
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', jalan); else jalan();
})();
