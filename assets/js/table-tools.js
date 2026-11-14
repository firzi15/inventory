/**
 * Table Tools — pencarian cepat (filter) & pengurutan kolom (sort) otomatis
 * untuk semua tabel data (.table-custom / .table) di area konten utama.
 *
 * Opt-out : tambahkan atribut data-no-tools pada <table> (atau induknya).
 * Sort    : klik judul kolom (asc -> desc -> kembali ke urutan awal).
 *           Nilai khusus per sel: atribut data-sort pada <td>.
 * Catatan : filter bekerja pada baris yang sedang tampil di halaman (client-side).
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

        var state = { col: -1, dir: 0, query: '' };
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

        function applyFilter() {
            var q = state.query.trim().toLowerCase();
            var groups = buildGroups().groups, shown = 0;
            withoutObserving(function () {
                groups.forEach(function (g) {
                    var text = g.rows.map(function (r) { return r.textContent; }).join(' ').toLowerCase();
                    var ok = !q || text.indexOf(q) !== -1;
                    g.rows.forEach(function (r) { r.classList.toggle('tt-hidden', !ok); });
                    if (ok) shown++;
                });
            });
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
            input.addEventListener('input', function () { state.query = input.value; applyFilter(); });
            input.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') { input.value = ''; state.query = ''; applyFilter(); }
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
