/**
 * Table Tools — pencarian cepat (filter) & pengurutan kolom (sort) otomatis
 * untuk semua tabel data (.table-custom / .table) di area konten utama.
 *
 * Opt-out : tambahkan atribut data-no-tools pada <table> (atau induknya).
 * Sort    : klik judul kolom (asc -> desc -> kembali ke urutan awal).
 *           Nilai khusus per sel: atribut data-sort pada <td>.
 * Catatan : filter bekerja pada baris yang sedang tampil di halaman (client-side).
 * Halaman : tambahkan data-halaman="25" pada <table> untuk membagi baris per halaman (pilihan jumlah baris tersedia).
             Berlaku bersama pencarian dan pengurutan; saat dicetak semua baris tetap ikut tercetak.
 */
(function () {
    'use strict';

    var MIN_ROWS = 2;

    function qsa(root, sel) { return Array.prototype.slice.call(root.querySelectorAll(sel)); }

    function parseValue(raw) {
        var t = (raw || '').replace(/\s+/g, ' ').trim();
        if (t === '' || t === '-' || t === '—') return { empty: true };

        // Tanggal dd/mm/yyyy [hh:mm] atau yyyy-mm-dd [hh:mm]
        var m = t.match(/^(\d{1,2})[\/\-.](\d{1,2})[\/\-.](\d{4})(?:[ ,T]+(\d{1,2}):(\d{2}))?/);
        if (m) return { num: Date.UTC(+m[3], +m[2] - 1, +m[1], +(m[4] || 0), +(m[5] || 0)), isNum: true, isDate: true };
        m = t.match(/^(\d{4})-(\d{2})-(\d{2})(?:[ T]+(\d{1,2}):(\d{2}))?/);
        if (m) return { num: Date.UTC(+m[1], +m[2] - 1, +m[3], +(m[4] || 0), +(m[5] || 0)), isNum: true, isDate: true };

        // Angka (Rp, Kg, %, format Indonesia 1.234,56 atau 1,234.56)
        var n = t.replace(/^(rp\.?|idr)\s*/i, '').replace(/\s*(kg|pcs|pc|%|ekor|box)\.?$/i, '').replace(/\s/g, '');
        if (/^[-+(]?\d[\d.,]*\)?$/.test(n)) {
            var neg = /^[-(]/.test(n);
            n = n.replace(/[-+()]/g, '');
            var lastDot = n.lastIndexOf('.'), lastComma = n.lastIndexOf(',');
            if (lastDot > -1 && lastComma > -1) {
                if (lastComma > lastDot) n = n.replace(/\./g, '').replace(',', '.');
                else n = n.replace(/,/g, '');
            } else if (lastComma > -1) {
                n = (n.split(',').length > 2 || /,\d{3}$/.test(n)) ? n.replace(/,/g, '') : n.replace(',', '.');
            } else if (lastDot > -1) {
                if (n.split('.').length > 2 || (/\.\d{3}$/.test(n) && !/^0\./.test(n))) n = n.replace(/\./g, '');
            }
            var v = parseFloat(n);
            if (!isNaN(v)) return { num: neg ? -v : v, isNum: true };
        }
        return { str: t.toLowerCase() };
    }

    function cellValue(td) {
        if (!td) return { empty: true };
        var ds = td.getAttribute('data-sort');
        return parseValue(ds !== null ? ds : td.textContent);
    }

    function rowWidth(r) {
        var w = 0;
        for (var i = 0; i < r.cells.length; i++) w += r.cells[i].colSpan || 1;
        return w;
    }

    function cellAt(r, li) {
        var pos = 0;
        for (var i = 0; i < r.cells.length; i++) {
            var span = r.cells[i].colSpan || 1;
            if (li < pos + span) return r.cells[i];
            pos += span;
        }
        return null;
    }

    function setup(table) {
        if (table.__ttDone) return true;
        if (table.hasAttribute('data-no-tools') || table.closest('[data-no-tools]')) return true;
        if (table.classList.contains('pl-table') || table.classList.contains('table-items')) return true;

        var thead = table.tHead, tbody = table.tBodies[0];
        if (!thead || !tbody || !thead.rows.length) return true;
        var headRow = thead.rows[thead.rows.length - 1];
        var ths = qsa(headRow, 'th, td');
        if (!ths.length) return true;
        var cols = rowWidth(headRow);

        // Peta kolom logis tiap header
        var headIdx = [], acc = 0;
        ths.forEach(function (th) { headIdx.push(acc); acc += th.colSpan || 1; });

        function isData(r) { return rowWidth(r) === cols && !(cols > 1 && r.cells.length === 1); }
        function isFooterRow(r) { return /^(grand\s*)?(sub\s*)?(total|jumlah)/i.test((r.cells[0] ? r.cells[0].textContent : '').trim()); }

        // Kelompokkan: tiap baris data + baris pendampingnya (detail/expandable) = satu grup
        function buildGroups() {
            var rows = qsa(tbody, ':scope > tr');
            var groups = [], footers = [], leading = [], cur = null;
            rows.forEach(function (r) {
                if (isFooterRow(r)) { footers.push(r); cur = null; }
                else if (isData(r)) { cur = { rows: [r] }; groups.push(cur); }
                else if (cur) cur.rows.push(r);
                else leading.push(r);
            });
            return { groups: groups, footers: footers, leading: leading };
        }

        var built = buildGroups();
        if (built.groups.length < MIN_ROWS) return false; // tunggu data (mungkin diisi JS)

        table.__ttDone = true;
        table.classList.add('tt-enabled');

        var state = { col: -1, dir: 0, query: '', page: 1, per: parseInt(table.getAttribute('data-halaman'), 10) || 0 };
        var pagerEl = null;
        var busy = false;
        var observer = null;
        var originalOrder = built.groups.map(function (g) { return g.rows[0]; });

        function withoutObserving(fn) {
            busy = true;
            try { fn(); } finally {
                busy = false;
                if (observer) observer.takeRecords(); // buang mutasi buatan sendiri agar tidak berulang
            }
        }

        // ---------- Sort ----------
        var colType = [];
        function detectTypes(groups) {
            for (var c = 0; c < cols; c++) {
                var num = 0, filled = 0;
                groups.forEach(function (g) {
                    var v = cellValue(cellAt(g.rows[0], c));
                    if (v.empty) return;
                    filled++;
                    if (v.isNum) num++;
                });
                colType[c] = filled > 0 && num / filled >= 0.8 ? 'num' : 'str';
            }
        }

        function sortable(th) {
            if ((th.colSpan || 1) > 1) return false;
            var txt = th.textContent.replace(/\s+/g, ' ').trim();
            if (!txt || th.querySelector('input, button, select')) return false;
            if (/^(aksi|opsi|action|actions)$/i.test(txt)) return false;
            return true;
        }

        function place(ordered, info) {
            withoutObserving(function () {
                info.leading.forEach(function (r) { tbody.appendChild(r); });
                ordered.forEach(function (g) { g.rows.forEach(function (r) { tbody.appendChild(r); }); });
                info.footers.forEach(function (r) { tbody.appendChild(r); });
            });
        }

        function applySort() {
            var info = buildGroups();
            var groups = info.groups;
            if (state.col < 0 || state.dir === 0) {
                var pos = new Map();
                originalOrder.forEach(function (r, i) { pos.set(r, i); });
                var restored = groups.slice().sort(function (a, b) {
                    var x = pos.has(a.rows[0]) ? pos.get(a.rows[0]) : 1e9;
                    var y = pos.has(b.rows[0]) ? pos.get(b.rows[0]) : 1e9;
                    return x - y;
                });
                place(restored, info);
                return;
            }
            var c = state.col, dir = state.dir, type = colType[c];
            var keyed = groups.map(function (g, idx) { return { g: g, v: cellValue(cellAt(g.rows[0], c)), i: idx }; });
            keyed.sort(function (a, b) {
                if (a.v.empty && b.v.empty) return a.i - b.i;
                if (a.v.empty) return 1;      // kosong selalu di bawah
                if (b.v.empty) return -1;
                var res;
                if (type === 'num') {
                    var x = a.v.isNum ? a.v.num : -Infinity, y = b.v.isNum ? b.v.num : -Infinity;
                    res = x - y;
                } else {
                    var sa = a.v.str !== undefined ? a.v.str : String(a.v.num);
                    var sb = b.v.str !== undefined ? b.v.str : String(b.v.num);
                    res = sa.localeCompare(sb, 'id', { numeric: true, sensitivity: 'base' });
                }
                if (res === 0) return a.i - b.i;
                return res * dir;
            });
            place(keyed.map(function (k) { return k.g; }), info);
        }

        function updateHeaders() {
            ths.forEach(function (th, i) {
                if (!th.classList.contains('tt-sortable')) return;
                th.removeAttribute('aria-sort');
                th.classList.remove('tt-asc', 'tt-desc');
                if (headIdx[i] === state.col && state.dir !== 0) {
                    th.classList.add(state.dir === 1 ? 'tt-asc' : 'tt-desc');
                    th.setAttribute('aria-sort', state.dir === 1 ? 'ascending' : 'descending');
                }
            });
        }

        function onSort(li) {
            if (state.col !== li) { state.col = li; state.dir = 1; }
            else if (state.dir === 1) state.dir = -1;
            else if (state.dir === -1) { state.dir = 0; state.col = -1; }
            detectTypes(buildGroups().groups);
            applySort();
            updateHeaders();
            if (table.hasAttribute('data-halaman')) { state.page = 1; applyFilter(); }
        }

        ths.forEach(function (th, i) {
            if (!sortable(th)) return;
            var li = headIdx[i];
            th.classList.add('tt-sortable');
            th.setAttribute('tabindex', '0');
            th.setAttribute('role', 'button');
            th.title = 'Klik untuk mengurutkan';
            th.addEventListener('click', function (e) {
                if (e.target.closest('a, input, button, select')) return;
                onSort(li);
            });
            th.addEventListener('keydown', function (e) {
                if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); onSort(li); }
            });
        });

        // ---------- Filter ----------
        var host = table.closest('.table-responsive') || table;
        var scope = table.closest('.card, .table-card, section') || host.parentElement;
        var hasOwnSearch = false;
        if (scope) {
            hasOwnSearch = qsa(scope, 'input[type="search"], input[type="text"]').some(function (inp) {
                return !inp.closest('.tt-toolbar') && !inp.closest('.modal, [id^="modal"]') &&
                    /cari|search|filter/i.test((inp.placeholder || '') + ' ' + (inp.id || '') + ' ' + (inp.name || ''));
            });
        }

        var countEl = null, input = null, noMatch = null;

        // ---------- Halaman (opsional: data-halaman) ----------
        function applyPaging(groups, cocok) {
            var dasar = parseInt(table.getAttribute('data-halaman'), 10);
            if (!(dasar > 0)) return;
            var per = state.per;                       // 0 = tampilkan semua
            var total = cocok.length;
            var maks = per > 0 ? Math.max(1, Math.ceil(total / per)) : 1;
            state.page = Math.min(Math.max(1, state.page), maks);
            var dari = per > 0 ? (state.page - 1) * per : 0;
            var sampai = per > 0 ? dari + per : total;
            withoutObserving(function () {
                groups.forEach(function (g) { g.rows.forEach(function (r) { r.classList.remove('tt-page-hidden'); }); });
                cocok.forEach(function (g, i) {
                    var sembunyi = i < dari || i >= sampai;
                    g.rows.forEach(function (r) { r.classList.toggle('tt-page-hidden', sembunyi); });
                });
            });
            renderPager(dasar, total, maks, dari, Math.min(sampai, total));
        }

        function renderPager(dasar, total, maks, dari, sampai) {
            if (!pagerEl) {
                pagerEl = document.createElement('div');
                pagerEl.className = 'sj-halaman no-print tt-pager';
                var anchor = (table.closest('.table-responsive') || table);
                anchor.parentNode.insertBefore(pagerEl, anchor.nextSibling);
                pagerEl.addEventListener('click', function (e) {
                    var a = e.target.closest('[data-hal]');
                    if (!a) return;
                    e.preventDefault();
                    if (a.classList.contains('mati')) return;
                    state.page = parseInt(a.getAttribute('data-hal'), 10);
                    applyFilter();
                });
                pagerEl.addEventListener('change', function (e) {
                    if (!e.target.classList.contains('tt-per')) return;
                    state.per = parseInt(e.target.value, 10) || 0;
                    state.page = 1;
                    applyFilter();
                });
            }
            // Sedikit baris dan belum diubah pengguna: tidak perlu bilah halaman
            if (total <= dasar && state.per === dasar) { pagerEl.style.display = 'none'; return; }
            pagerEl.style.display = '';
            var h = state.page;
            function t(n, label, aktif, mati) {
                return '<a href="#" class="sj-hal' + (aktif ? ' aktif' : '') + (mati ? ' mati' : '') + '" data-hal="' + n + '">' + label + '</a>';
            }
            var nomor = t(1, '&laquo;', false, h <= 1) + t(h - 1, '&lsaquo;', false, h <= 1);
            var m = Math.max(1, h - 2), a = Math.min(maks, h + 2);
            if (m > 1) nomor += t(1, '1') + (m > 2 ? '<span class="sj-hal mati">&hellip;</span>' : '');
            for (var i = m; i <= a; i++) nomor += t(i, String(i), i === h);
            if (a < maks) nomor += (a < maks - 1 ? '<span class="sj-hal mati">&hellip;</span>' : '') + t(maks, String(maks));
            nomor += t(h + 1, '&rsaquo;', false, h >= maks) + t(maks, '&raquo;', false, h >= maks);
            var opsi = [dasar, dasar * 2, dasar * 4, 0].map(function (v) {
                return '<option value="' + v + '"' + (v === state.per ? ' selected' : '') + '>' + (v ? v + ' baris' : 'Semua') + '</option>';
            }).join('');
            pagerEl.innerHTML =
                '<div class="sj-halaman-info">Menampilkan ' + (total ? dari + 1 : 0) + '-' + sampai + ' dari ' + total + ' baris</div>' +
                '<div class="sj-halaman-nomor">' + nomor + '</div>' +
                '<div class="sj-halaman-per">Tampil <select class="tt-per" data-native>' + opsi + '</select></div>';
        }


        function applyFilter() {
            var q = state.query.trim().toLowerCase();
            var groups = buildGroups().groups, shown = 0;
            var cocok = [];
            withoutObserving(function () {
                groups.forEach(function (g) {
                    var text = g.rows.map(function (r) { return r.textContent; }).join(' ').toLowerCase();
                    var ok = !q || text.indexOf(q) !== -1;
                    g.rows.forEach(function (r) { r.classList.toggle('tt-hidden', !ok); });
                    if (ok) { shown++; cocok.push(g); }
                });
            });
            applyPaging(groups, cocok);
            if (countEl) countEl.textContent = q ? (shown + ' dari ' + groups.length + ' baris') : (groups.length + ' baris');
            if (noMatch) noMatch.style.display = (q && shown === 0) ? '' : 'none';
        }

        if (!hasOwnSearch) {
            var bar = document.createElement('div');
            bar.className = 'tt-toolbar';
            bar.innerHTML =
                '<div class="tt-search">' +
                '<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"></circle><path d="m21 21-4.3-4.3"></path></svg>' +
                '<input type="search" class="tt-input" placeholder="Cari di tabel ini..." aria-label="Cari di tabel">' +
                '</div><span class="tt-count"></span>';
            host.parentNode.insertBefore(bar, host);
            input = bar.querySelector('.tt-input');
            countEl = bar.querySelector('.tt-count');
            input.addEventListener('input', function () { state.query = input.value; state.page = 1; applyFilter(); });
            input.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') { input.value = ''; state.query = ''; state.page = 1; applyFilter(); }
            });

            noMatch = document.createElement('div');
            noMatch.className = 'tt-nomatch';
            noMatch.style.display = 'none';
            noMatch.textContent = 'Tidak ada baris yang cocok dengan pencarian.';
            host.parentNode.insertBefore(noMatch, host.nextSibling);
        }

        detectTypes(built.groups);
        applyFilter();

        // ---------- Konten dinamis (tbody diisi ulang lewat JavaScript) ----------
        observer = new MutationObserver(function () {
            if (busy) return;
            var g = buildGroups().groups;
            originalOrder = g.map(function (x) { return x.rows[0]; });
            detectTypes(g);
            applySort();
            applyFilter();
        });
        observer.observe(tbody, { childList: true });
        return true;
    }

    function init() {
        var scope = document.querySelector('.app-main-container') || document.body;
        qsa(scope, 'table.table-custom, table.table').forEach(function (table) {
            var ready = setup(table);
            if (ready === false && table.tBodies[0]) {
                // Tabel yang baris datanya diisi lewat JavaScript: coba lagi saat tbody berubah
                var mo = new MutationObserver(function () {
                    if (setup(table) !== false) mo.disconnect();
                });
                mo.observe(table.tBodies[0], { childList: true });
            }
        });
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
})();
