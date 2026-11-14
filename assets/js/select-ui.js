/**
 * Dropdown bergaya untuk semua <select> (menggantikan menu bawaan browser).
 * - <select> asli tetap ada (disembunyikan) sebagai sumber nilai, jadi form dan kode lama tidak berubah.
 * - Opt-out: atribut data-native pada <select>. Tidak diterapkan pada select multiple / size > 1.
 * - Select yang ditambah belakangan (modal, baris dinamis) otomatis ikut; perubahan nilai lewat kode ikut tersinkron.
 */
(function () {
    if (window.enhanceSelect) return;

    var CHEVRON = '<svg class="ss-chev" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>';
    var CHECK = '<svg class="ss-check" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>';

    var popup = null, current = null, items = [], aktif = -1, cari = null;

    // Sinkronkan tampilan saat nilai diubah lewat kode (select.value = ..., selectedIndex = ...)
    ['value', 'selectedIndex'].forEach(function (prop) {
        var d = Object.getOwnPropertyDescriptor(HTMLSelectElement.prototype, prop);
        if (!d || !d.set) return;
        Object.defineProperty(HTMLSelectElement.prototype, prop, {
            configurable: true,
            get: d.get,
            set: function (v) { d.set.call(this, v); sync(this); }
        });
    });

    function layak(sel) {
        return !(sel.multiple || sel.size > 1 || sel.hasAttribute('data-native') || sel.dataset.shadcnEnhanced ||
            sel.closest('.swal2-container') || sel.closest('[data-native]'));
    }

    function sync(sel) {
        var tr = sel.__ss;
        if (!tr) return;
        var opt = sel.options[sel.selectedIndex];
        var teks = opt ? opt.textContent.replace(/\s+/g, ' ').trim() : '';
        var kosong = !sel.value;
        tr.querySelector('.ss-text').textContent = teks || ' ';
        tr.classList.toggle('ss-placeholder', kosong);
        tr.disabled = sel.disabled;
        tr.title = sel.title || '';
        tr.hidden = sel.hidden;
        if (sel.style.display === 'none') tr.style.display = 'none';
        else if (tr.style.display === 'none') tr.style.display = '';
    }

    function enhance(sel) {
        if (!sel || sel.__ss || !layak(sel)) return;
        // hasil clone: buang sisa tombol / kelas dari elemen asal
        var prev = sel.previousElementSibling;
        if (prev && prev.classList.contains('ss-trigger') && prev.__sel !== sel) prev.remove();
        sel.classList.remove('ss-native');

        var tr = document.createElement('button');
        tr.type = 'button';
        tr.className = (sel.className + ' ss-trigger').trim();
        tr.style.cssText = sel.style.cssText;
        tr.innerHTML = '<span class="ss-text"></span>' + CHEVRON;
        tr.__sel = sel;
        sel.__ss = tr;
        sel.parentNode.insertBefore(tr, sel);
        sel.classList.add('ss-native');
        sel.tabIndex = -1;

        tr.addEventListener('click', function (e) {
            e.preventDefault(); e.stopPropagation();
            if (current === sel) tutup(); else buka(sel);
        });
        tr.addEventListener('keydown', function (e) {
            if (e.key === 'ArrowDown' || e.key === 'ArrowUp' || e.key === 'Enter' || e.key === ' ') {
                e.preventDefault(); buka(sel);
            }
        });
        sel.addEventListener('change', function () { sync(sel); });
        new MutationObserver(function () { sync(sel); })
            .observe(sel, { attributes: true, childList: true, subtree: true, characterData: true });
        sync(sel);
    }

    function pasang(sel, opt, wadah) {
        var el = document.createElement('div');
        el.className = 'ss-item' + (opt.value === sel.value ? ' selected' : '') + (opt.disabled ? ' disabled' : '') + (!opt.value ? ' ss-empty' : '');
        var s = document.createElement('span');
        s.textContent = opt.textContent.replace(/\s+/g, ' ').trim() || ' ';
        el.appendChild(s);
        if (opt.value === sel.value && opt.value) el.insertAdjacentHTML('beforeend', CHECK);
        el.__opt = opt;
        if (!opt.disabled) {
            el.addEventListener('click', function (e) {
                e.preventDefault(); e.stopPropagation();
                pilih(sel, opt);
            });
        }
        el.addEventListener('mousemove', function () { setAktif(items.indexOf(el)); });
        wadah.appendChild(el);
        items.push(el);
    }

    function pilih(sel, opt) {
        var berubah = sel.value !== opt.value;
        sel.value = opt.value;
        var tr = sel.__ss;
        tutup();
        if (tr) tr.focus();
        if (berubah) {
            sel.dispatchEvent(new Event('input', { bubbles: true }));
            sel.dispatchEvent(new Event('change', { bubbles: true }));
        }
    }

    function setAktif(i) {
        var tampil = items.filter(function (e) { return e.style.display !== 'none' && !e.classList.contains('disabled'); });
        items.forEach(function (e) { e.classList.remove('active'); });
        if (i < 0 || !items[i]) { aktif = -1; return; }
        aktif = i;
        items[i].classList.add('active');
        if (items[i].scrollIntoView) items[i].scrollIntoView({ block: 'nearest' });
        void tampil;
    }

    function geser(arah) {
        var n = items.length, i = aktif;
        for (var k = 0; k < n; k++) {
            i = (i + arah + n) % n;
            var e = items[i];
            if (e.style.display !== 'none' && !e.classList.contains('disabled')) { setAktif(i); return; }
        }
    }

    function posisi() {
        if (!popup || !current) return;
        var tr = current.__ss, r = tr.getBoundingClientRect();
        var lebar = Math.max(r.width, 180);
        var kiri = Math.min(Math.max(8, r.left), window.innerWidth - lebar - 8);
        popup.style.minWidth = lebar + 'px';
        popup.style.left = kiri + 'px';
        var tinggi = Math.min(popup.scrollHeight, 300);
        var bawah = window.innerHeight - r.bottom - 8, atas = r.top - 8;
        if (bawah < Math.min(tinggi, 200) && atas > bawah) {
            popup.style.maxHeight = Math.min(300, atas) + 'px';
            popup.style.top = 'auto';
            popup.style.bottom = (window.innerHeight - r.top + 4) + 'px';
        } else {
            popup.style.maxHeight = Math.min(300, bawah) + 'px';
            popup.style.bottom = 'auto';
            popup.style.top = (r.bottom + 4) + 'px';
        }
    }

    function buka(sel) {
        if (sel.disabled) return;
        tutup();
        current = sel;
        items = []; aktif = -1;
        popup = document.createElement('div');
        popup.className = 'ss-popup';
        popup.addEventListener('mousedown', function (e) { if (e.target !== cari) e.preventDefault(); });

        var jumlah = sel.options.length;
        cari = null;
        if (jumlah > 10) {
            cari = document.createElement('input');
            cari.type = 'text';
            cari.className = 'ss-search';
            cari.placeholder = 'Cari...';
            cari.addEventListener('input', function () {
                var q = cari.value.trim().toLowerCase();
                items.forEach(function (e) {
                    e.style.display = (!q || e.textContent.toLowerCase().indexOf(q) !== -1) ? '' : 'none';
                });
                popup.querySelectorAll('.ss-group').forEach(function (g) {
                    var ada = false, n = g.nextElementSibling;
                    while (n && n.classList.contains('ss-item')) { if (n.style.display !== 'none') ada = true; n = n.nextElementSibling; }
                    g.style.display = ada ? '' : 'none';
                });
                var pertama = items.findIndex(function (e) { return e.style.display !== 'none' && !e.classList.contains('disabled'); });
                setAktif(pertama);
            });
            popup.appendChild(cari);
        }
        var daftar = document.createElement('div');
        daftar.className = 'ss-list';
        Array.prototype.forEach.call(sel.children, function (c) {
            if (c.tagName === 'OPTGROUP') {
                var g = document.createElement('div');
                g.className = 'ss-group';
                g.textContent = c.label;
                daftar.appendChild(g);
                Array.prototype.forEach.call(c.children, function (o) { pasang(sel, o, daftar); });
            } else if (c.tagName === 'OPTION') {
                pasang(sel, c, daftar);
            }
        });
        popup.appendChild(daftar);
        document.body.appendChild(popup);
        sel.__ss.classList.add('open');
        posisi();
        var terpilih = items.findIndex(function (e) { return e.classList.contains('selected'); });
        setAktif(terpilih);
        if (cari) cari.focus();
    }

    function tutup() {
        if (popup) { popup.remove(); popup = null; }
        if (current && current.__ss) current.__ss.classList.remove('open');
        current = null; items = []; aktif = -1; cari = null;
    }

    document.addEventListener('mousedown', function (e) {
        if (!popup) return;
        if (popup.contains(e.target) || (current && current.__ss.contains(e.target))) return;
        tutup();
    }, true);
    document.addEventListener('keydown', function (e) {
        if (!popup) return;
        if (e.key === 'Escape') { var t = current && current.__ss; tutup(); if (t) t.focus(); e.stopPropagation(); }
        else if (e.key === 'ArrowDown') { e.preventDefault(); geser(1); }
        else if (e.key === 'ArrowUp') { e.preventDefault(); geser(-1); }
        else if (e.key === 'Enter') { e.preventDefault(); if (items[aktif]) pilih(current, items[aktif].__opt); }
        else if (e.key === 'Tab') { tutup(); }
    }, true);
    window.addEventListener('resize', function () { if (popup) posisi(); });
    window.addEventListener('scroll', function (e) {
        if (popup && !popup.contains(e.target)) tutup();
    }, true);

    function semua(root) {
        if (root.tagName === 'SELECT') enhance(root);
        if (root.querySelectorAll) Array.prototype.forEach.call(root.querySelectorAll('select'), enhance);
    }

    function mulai() {
        semua(document.body);
        new MutationObserver(function (muts) {
            muts.forEach(function (m) {
                Array.prototype.forEach.call(m.addedNodes, function (n) { if (n.nodeType === 1) semua(n); });
            });
        }).observe(document.body, { childList: true, subtree: true });
    }

    window.enhanceSelect = enhance;
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', mulai);
    else mulai();
})();
