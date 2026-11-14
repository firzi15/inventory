/**
 * Merapikan tabel: sel berisi angka, tanggal, atau kode pendek ditandai agar tidak terpecah ke baris baru
 * (kelas "nw"); teks biasa boleh turun baris sehingga tabel muat di wadahnya tanpa geser mendatar.
 * Juga berjalan untuk baris yang ditambahkan lewat JavaScript.
 */
(function () {
    var POLA = [
        /^-?\s*(Rp\.?\s*)?-?[\d.,]+(\s*(kg|KG|Kg|pcs|Pcs|PCS|%|hari|Hari|box|Box|dus|Dus))?$/,   // angka, rupiah, berat
        /^\d{1,2}[\/\-.]\d{1,2}[\/\-.]\d{2,4}(\s+\d{1,2}[:.]\d{2}(:\d{2})?)?(\s*WIB)?$/,       // tanggal dan jam
        /^\d{4}-\d{2}-\d{2}(\s+\d{2}:\d{2}(:\d{2})?)?$/,
        /^\d{1,2}:\d{2}$/
    ];

    function tandai(sel) {
        if (sel.classList.contains('nw') || sel.dataset.nwDicek) return;
        sel.dataset.nwDicek = '1';
        var t = (sel.textContent || '').replace(/\s+/g, ' ').trim();
        if (!t || t.length > 28) return;
        for (var i = 0; i < POLA.length; i++) {
            if (POLA[i].test(t)) { sel.classList.add('nw'); return; }
        }
    }

    function proses(akar) {
        var sel = (akar || document).querySelectorAll('.table-custom tbody td, .table-responsive table tbody td, .table-custom tfoot td, .table-responsive table tfoot td');
        for (var i = 0; i < sel.length; i++) tandai(sel[i]);
    }

    var terjadwal = false;
    function jadwalkan() {
        if (terjadwal) return;
        terjadwal = true;
        (window.requestAnimationFrame || setTimeout)(function () { terjadwal = false; proses(); });
    }

    document.addEventListener('DOMContentLoaded', function () {
        proses();
        try {
            new MutationObserver(jadwalkan).observe(document.body, { childList: true, subtree: true });
        } catch (e) { /* peramban lama: cukup sekali */ }
    });
})();
