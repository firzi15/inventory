/**
 * Ikon salin di samping ID barcode pada tabel. Sel yang isinya persis satu ID barcode (mis. BH01-PRD01-261114-00003)
 * diberi tombol kecil; klik menyalin ID ke clipboard. Berjalan juga untuk baris yang ditambahkan lewat JavaScript.
 */
(function () {
    var POLA_ID = /^[A-Z0-9]{2,}-PRD\d+-\d{6}-\d{3,}$/;
    var IKON_SALIN = '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>';
    var IKON_CEK = '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>';

    function salinCadangan(teks) {
        return new Promise(function (ok, gagal) {
            var ta = document.createElement('textarea');
            ta.value = teks; ta.setAttribute('readonly', ''); ta.style.cssText = 'position:fixed;top:-1000px;opacity:0;';
            document.body.appendChild(ta); ta.select();
            try { document.execCommand('copy') ? ok() : gagal(); } catch (e) { gagal(e); } finally { document.body.removeChild(ta); }
        });
    }

    function salin(teks) {
        if (navigator.clipboard && window.isSecureContext) {
            // Bila API clipboard tidak menjawab (izin tertahan) atau ditolak, pakai cara cadangan
            return new Promise(function (ok, gagal) {
                var selesai = false;
                var lanjut = function () { if (selesai) return; selesai = true; salinCadangan(teks).then(ok, gagal); };
                var t = setTimeout(lanjut, 700);
                navigator.clipboard.writeText(teks).then(function () { if (selesai) return; selesai = true; clearTimeout(t); ok(); }, function () { clearTimeout(t); lanjut(); });
            });
        }
        return salinCadangan(teks);
    }

    // Turun ke elemen paling dalam bila sel hanya membungkus satu elemen (spasi dan baris kosong diabaikan)
    function daun(el) {
        for (;;) {
            if (el.children.length === 0) return el;
            if (el.children.length !== 1 || el.children[0].classList.contains('salin-btn')) return null;
            for (var i = 0; i < el.childNodes.length; i++) {
                var n = el.childNodes[i];
                if (n.nodeType === 3 && n.textContent.trim() !== '') return null;
            }
            el = el.children[0];
        }
    }

    function pasang(sel) {
        if (sel.dataset.salinDicek) return;
        sel.dataset.salinDicek = '1';
        var el = daun(sel);
        if (!el) return;
        var id = el.textContent.trim();
        if (!POLA_ID.test(id)) return;
        var b = document.createElement('button');
        b.type = 'button';
        b.className = 'salin-btn no-print';
        b.title = 'Salin ID barcode';
        b.setAttribute('aria-label', 'Salin ID barcode ' + id);
        b.innerHTML = IKON_SALIN;
        b.addEventListener('click', function (e) {
            e.preventDefault(); e.stopPropagation();
            salin(id).then(function () {
                b.classList.add('ok'); b.innerHTML = IKON_CEK; b.title = 'Tersalin';
                setTimeout(function () { b.classList.remove('ok'); b.innerHTML = IKON_SALIN; b.title = 'Salin ID barcode'; }, 1300);
            }).catch(function () {
                b.classList.add('gagal'); b.title = 'Gagal menyalin, salin manual';
                setTimeout(function () { b.classList.remove('gagal'); b.title = 'Salin ID barcode'; }, 1800);
            });
        });
        if (el === sel) sel.appendChild(b); else el.insertAdjacentElement('afterend', b);
    }

    function proses() {
        var sel = document.querySelectorAll('.table-custom td, .table-responsive table td');
        for (var i = 0; i < sel.length; i++) pasang(sel[i]);
    }

    var terjadwal = false;
    function jadwalkan() {
        if (terjadwal) return;
        terjadwal = true;
        (window.requestAnimationFrame || setTimeout)(function () { terjadwal = false; proses(); });
    }

    document.addEventListener('DOMContentLoaded', function () {
        proses();
        try { new MutationObserver(jadwalkan).observe(document.body, { childList: true, subtree: true }); } catch (e) { /* peramban lama: cukup sekali */ }
    });
})();
