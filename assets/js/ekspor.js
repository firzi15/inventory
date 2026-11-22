/**
 * Ekspor laporan ke Excel (.xlsx) dan PDF.
 * Isi diambil dari tabel yang tampil di halaman, dibersihkan dulu: tanpa kolom Aksi / checkbox,
 * tanpa tombol, ikon, dan emoji. PDF dicetak dari dokumen bersih sendiri, bukan dari halaman aplikasi.
 *
 *   eksporExcel(namaFile, [idTabel])   eksporPdf(judul, [idTabel])
 * Tanpa idTabel: semua tabel yang tampil di area konten (kecuali di dalam modal / data-no-export).
 */
(function () {
    var EMOJI = /[←-⇿⌀-⏿①-➿⤀-⥿⬀-⯿️‍]|[\uD83C-\uD83E][\uDC00-\uDFFF]/g;

    function areaKonten() {
        return document.querySelector('.content-wrapper') || document.querySelector('.app-main-container') || document.body;
    }

    function tabelTarget(id) {
        if (id) {
            var t = document.getElementById(id);
            return t ? [t] : [];
        }
        return Array.prototype.filter.call(areaKonten().querySelectorAll('table'), function (t) {
            return !t.closest('.modal-backdrop') && !t.closest('[data-no-export]') && !t.hasAttribute('data-no-export')
                && t.rows.length > 0 && t.offsetParent !== null;
        });
    }

    function judulTabel(t, i) {
        var teks = '';
        var kartu = t.closest('.card');
        if (kartu && kartu.firstElementChild && !kartu.firstElementChild.contains(t)) {
            teks = kartu.firstElementChild.textContent || '';
        }
        teks = bersihTeks(teks).split('  ')[0];
        return teks || ('Data ' + (i + 1));
    }

    function bersihTeks(s) {
        return String(s || '').replace(EMOJI, '').replace(/\s+/g, ' ').trim();
    }

    function teksSel(td) {
        var c = td.cloneNode(true);
        // isian form diambil nilainya (mis. harga di tabel master), bukan dibuang
        var asli = td.querySelectorAll('input, select, textarea');
        Array.prototype.forEach.call(c.querySelectorAll('input, select, textarea'), function (e, i) {
            var o = asli[i], tipe = (e.getAttribute('type') || '').toLowerCase(), v = '';
            if (tipe === 'checkbox' || tipe === 'radio' || tipe === 'hidden' || tipe === 'button' || tipe === 'submit') { e.remove(); return; }
            if (e.tagName === 'SELECT') v = o && o.selectedOptions && o.selectedOptions[0] ? o.selectedOptions[0].textContent : '';
            else v = o ? o.value : '';
            e.replaceWith(document.createTextNode(' ' + v + ' '));
        });
        Array.prototype.forEach.call(c.querySelectorAll('button, svg, i, input, select, textarea, script, style, .no-print, [data-lucide]'), function (e) { e.remove(); });
        return bersihTeks(c.textContent);
    }

    // "Rp 1,234,567" / "-Rp 1,000" / "Rp -1,000" menjadi angka (format lama "Rp 1.234.567" juga dikenali); selain itu tetap teks
    function nilai(teks) {
        var m = teks.match(/^(-?)\s*Rp\s*(-?)\s*(\d{1,3}(?:,\d{3})+|\d+)(?:\.(\d+))?$/);
        if (m) {
            var a = parseFloat(m[3].replace(/,/g, '') + (m[4] ? '.' + m[4] : ''));
            return (m[1] || m[2]) ? -a : a;
        }
        m = teks.match(/^(-?)\s*Rp\s*(-?)\s*([\d.]+)(?:,(\d+))?$/);
        if (!m) return teks;
        var n = parseFloat(m[3].replace(/\./g, '') + (m[4] ? '.' + m[4] : ''));
        return (m[1] || m[2]) ? -n : n;
    }

    // Kolom yang dibuang: Aksi / Opsi dan kolom checkbox
    function kolomBuang(t) {
        var buang = {};
        var head = t.tHead && t.tHead.rows.length ? t.tHead.rows[t.tHead.rows.length - 1] : t.rows[0];
        var idx = 0;
        Array.prototype.forEach.call(head.cells, function (th) {
            var label = bersihTeks(th.textContent).toLowerCase();
            var hapus = /^(aksi|action|opsi|edit|hapus)$/.test(label) || th.querySelector('input[type=checkbox]') || th.classList.contains('no-print');
            for (var s = 0; s < (th.colSpan || 1); s++) { if (hapus) buang[idx] = true; idx++; }
        });
        return buang;
    }

    // grid: baris sel teks; bagian: asal tiap baris (THEAD / TBODY / TFOOT) agar Excel bisa memisahkan judul, isi, dan total
    function keGrid(t) {
        var buang = kolomBuang(t), grid = [], bagian = [];
        Array.prototype.forEach.call(t.rows, function (tr) {
            if (tr.classList.contains('tt-hidden') || tr.style.display === 'none' || tr.classList.contains('no-print')) return;
            if (tr.parentNode && tr.parentNode.style && tr.parentNode.style.display === 'none') return;
            var baris = [], idx = 0;
            Array.prototype.forEach.call(tr.cells, function (td) {
                var teks = teksSel(td);
                for (var s = 0; s < (td.colSpan || 1); s++) {
                    if (!buang[idx]) baris.push(s === 0 ? teks : '');
                    idx++;
                }
            });
            if (baris.some(function (v) { return v !== ''; })) {
                grid.push(baris);
                bagian.push(tr.parentNode && tr.parentNode.tagName ? tr.parentNode.tagName.toUpperCase() : 'TBODY');
            }
        });
        return { grid: grid, bagian: bagian };
    }

    function kumpulkan(id) {
        var hasil = [];
        tabelTarget(id).forEach(function (t, i) {
            var g = keGrid(t);
            if (g.grid.length) hasil.push({ judul: judulTabel(t, i), grid: g.grid, bagian: g.bagian, adaHead: !!(t.tHead && t.tHead.rows.length) });
        });
        // halaman dengan satu tabel: nama sheet memakai judul halaman
        var h1 = document.querySelector('.page-title');
        if (hasil.length === 1 && h1) hasil[0].judul = bersihTeks(h1.textContent);
        return hasil;
    }

    function peringatan(pesan) {
        if (window.showAppAlert) window.showAppAlert({ title: 'Ekspor', text: pesan, icon: 'info' });
        else alert(pesan);
    }

    // Pustaka Excel (+/- 930 KB) dimuat hanya saat tombol ekspor dipakai, bukan di setiap halaman
    var muatanXlsx = null;
    function muatXlsx() {
        if (typeof XLSX !== 'undefined') return Promise.resolve();
        if (!muatanXlsx) {
            muatanXlsx = new Promise(function (ok, gagal) {
                var s = document.createElement('script');
                s.src = 'assets/js/xlsx.full.min.js';
                s.onload = ok;
                s.onerror = function () { muatanXlsx = null; gagal(new Error('gagal memuat')); };
                document.head.appendChild(s);
            });
        }
        return muatanXlsx;
    }

    // ExcelJS (+/- 950 KB) untuk ekspor berbentuk tabel Excel (Format as Table); juga dimuat hanya saat dipakai
    var muatanExcelJs = null;
    function muatExcelJs() {
        if (typeof ExcelJS !== 'undefined') return Promise.resolve();
        if (!muatanExcelJs) {
            muatanExcelJs = new Promise(function (ok, gagal) {
                var s = document.createElement('script');
                s.src = 'assets/js/exceljs.min.js';
                s.onload = ok;
                s.onerror = function () { muatanExcelJs = null; gagal(new Error('gagal memuat')); };
                document.head.appendChild(s);
            });
        }
        return muatanExcelJs;
    }

    window.eksporExcel = function (nama, id) {
        var data = kumpulkan(id);
        if (!data.length) { peringatan('Tidak ada data yang tampil untuk diekspor.'); return; }
        var file = nama + '_' + new Date().toISOString().slice(0, 10) + '.xlsx';
        // Utama: tabel Excel berformat. Cadangan: cara lama (data polos) bila ExcelJS gagal dimuat atau gagal menulis.
        var caraLama = function () {
            muatXlsx().then(function () { eksporExcelSiap(nama, id); }, function () { peringatan('Pustaka Excel gagal dimuat, coba lagi.'); });
        };
        muatExcelJs().then(function () {
            return window.eksporBuatWorkbook(data).xlsx.writeBuffer().then(function (buf) {
                var blob = new Blob([buf], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
                var a = document.createElement('a');
                a.href = URL.createObjectURL(blob);
                a.download = file;
                document.body.appendChild(a);
                a.click();
                setTimeout(function () { URL.revokeObjectURL(a.href); a.remove(); }, 1500);
                if (window.showToast) window.showToast('Berhasil mengekspor ' + file, 'success');
            });
        }).catch(caraLama);
    };

    // Angka biasa tanpa nol di depan ("13", "-250") menjadi angka; kode seperti "007" atau "261117-00644" tetap teks
    function nilaiExcel(teks) {
        var v = nilai(teks);
        if (typeof v === 'number') return v;
        if (/^-?(0|[1-9]\d{0,14})$/.test(v)) return parseInt(v, 10);
        // ribuan dengan koma, mis. "48,000" (nominal tanpa Rp)
        if (/^-?\d{1,3}(,\d{3})+$/.test(v)) return parseInt(v.replace(/,/g, ''), 10);
        // desimal format Indonesia, mis. "1.234,50" (berat kg, 1-2 angka di belakang koma)
        if (/^-?\d{1,3}(\.\d{3})*,\d{1,2}$/.test(v)) return parseFloat(v.replace(/\./g, '').replace(',', '.'));
        // "Rp" tidak ikut ke Excel, mis. "Rp 48,000/kg" menjadi "48,000/kg"
        if (/Rp/.test(v)) v = v.replace(/Rp\s*/g, '').trim();
        return v === '' ? null : v;
    }

    /**
     * Susun workbook ExcelJS: tiap tabel halaman menjadi satu sheet berisi tabel Excel (header berwarna, filter, baris belang).
     * Baris tfoot (total) diletakkan tepat di bawah tabel dengan huruf tebal.
     */
    window.eksporBuatWorkbook = function (data) {
        var wb = new ExcelJS.Workbook();
        wb.creator = 'AR_BAROKAH';
        wb.created = new Date();
        var dipakai = {};
        data.forEach(function (d, iTabel) {
            var head = [], isi = [], total = [];
            d.grid.forEach(function (r, i) {
                var b = (d.bagian && d.bagian[i]) || (i === 0 && d.adaHead ? 'THEAD' : 'TBODY');
                if (b === 'THEAD') head = r; else if (b === 'TFOOT') total.push(r); else isi.push(r);
            });
            var ncol = Math.max(head.length, 1);
            isi.concat(total).forEach(function (r) { ncol = Math.max(ncol, r.length); });
            var pad = function (r) { var x = r.slice(0, ncol); while (x.length < ncol) x.push(''); return x; };

            // judul kolom wajib unik dan tidak kosong di tabel Excel
            var kolom = [], seen = {};
            pad(head).forEach(function (h, c) {
                var n = bersihTeks(h) || ('Kolom ' + (c + 1)), dasar = n, k = 2;
                while (seen[n.toLowerCase()]) n = dasar + ' ' + (k++);
                seen[n.toLowerCase()] = 1;
                kolom.push({ name: n, filterButton: true });
            });
            var baris = isi.map(function (r) { return pad(r).map(nilaiExcel); });
            if (!baris.length) baris = [pad([]).map(function () { return null; })];

            var judul = d.judul.replace(/[\\\/?*\[\]:]/g, ' ').slice(0, 28) || 'Data', nmSheet = judul, k2 = 2;
            while (dipakai[nmSheet.toLowerCase()]) nmSheet = judul.slice(0, 26) + ' ' + (k2++);
            dipakai[nmSheet.toLowerCase()] = 1;

            var ws = wb.addWorksheet(nmSheet, { views: [{ state: 'frozen', ySplit: 1 }] });
            ws.addTable({
                name: 'Tabel' + (iTabel + 1), ref: 'A1', headerRow: true, totalsRow: false,
                style: { theme: 'TableStyleMedium2', showRowStripes: true },
                columns: kolom, rows: baris
            });
            baris.forEach(function (r, ri) {
                r.forEach(function (v, ci) { if (typeof v === 'number') ws.getCell(ri + 2, ci + 1).numFmt = Number.isInteger(v) ? '#,##0' : '#,##0.00'; });
            });

            // baris total dari tfoot, tepat di bawah tabel
            var rr = baris.length + 2;
            total.forEach(function (r) {
                var row = ws.getRow(rr++);
                pad(r).map(nilaiExcel).forEach(function (v, ci) {
                    var cell = row.getCell(ci + 1);
                    cell.value = v;
                    cell.font = { bold: true };
                    cell.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FFDDEBF7' } };
                    cell.border = { top: { style: 'thin', color: { argb: 'FF1F4E78' } } };
                    if (typeof v === 'number') cell.numFmt = Number.isInteger(v) ? '#,##0' : '#,##0.00';
                });
            });

            // lebar kolom mengikuti isi terpanjang
            kolom.forEach(function (c, ci) {
                var w = c.name.length + 4;
                baris.concat(total.map(function (r) { return pad(r); })).forEach(function (r) {
                    var v = r[ci];
                    var pj = v === null || v === undefined ? 0 : (typeof v === 'number' ? v.toLocaleString('id-ID').length : String(v).length);
                    w = Math.max(w, pj + 2);
                });
                ws.getColumn(ci + 1).width = Math.min(Math.max(w, 8), 50);
            });
        });
        return wb;
    };

    function eksporExcelSiap(nama, id) {
        var data = kumpulkan(id);
        if (!data.length) { peringatan('Tidak ada data yang tampil untuk diekspor.'); return; }
        var wb = XLSX.utils.book_new(), dipakai = {};
        data.forEach(function (d) {
            var aoa = d.grid.map(function (r) { return r.map(nilai); });
            var ws = XLSX.utils.aoa_to_sheet(aoa);
            var lebar = [];
            aoa.forEach(function (r) {
                r.forEach(function (v, c) { lebar[c] = Math.max(lebar[c] || 8, Math.min(String(v).length + 2, 50)); });
            });
            ws['!cols'] = lebar.map(function (w) { return { wch: w }; });
            var judul = d.judul.replace(/[\\\/?*\[\]:]/g, ' ').slice(0, 28) || 'Data', n = judul, k = 2;
            while (dipakai[n.toLowerCase()]) n = judul.slice(0, 26) + ' ' + (k++);
            dipakai[n.toLowerCase()] = 1;
            XLSX.utils.book_append_sheet(wb, ws, n);
        });
        var file = nama + '_' + new Date().toISOString().slice(0, 10) + '.xlsx';
        XLSX.writeFile(wb, file);
        if (window.showToast) window.showToast('Berhasil mengekspor ' + file, 'success');
    }

    function esc(s) {
        return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

    window.eksporPdf = function (judul, id) {
        var data = kumpulkan(id);
        if (!data.length) { peringatan('Tidak ada data yang tampil untuk diekspor.'); return; }
        var html = '<!doctype html><html><head><meta charset="utf-8"><title>' + esc(judul) + '</title><style>' +
            '@page{size:A4 landscape;margin:10mm}' +
            'body{font-family:Arial,Helvetica,sans-serif;color:#111;font-size:10.5px;margin:0}' +
            'h1{font-size:16px;margin:0 0 2px}.tgl{font-size:10px;color:#555;margin-bottom:12px}' +
            'h2{font-size:12px;margin:16px 0 6px}' +
            'table{border-collapse:collapse;width:100%;margin-bottom:8px}' +
            'th,td{border:1px solid #bbb;padding:4px 6px;text-align:left;vertical-align:top}' +
            'th{background:#eee;font-weight:700}td.n,th.n{text-align:right}' +
            'tr{page-break-inside:avoid}thead{display:table-header-group}' +
            '</style></head><body><h1>' + esc(judul) + '</h1><div class="tgl">Dicetak ' + esc(new Date().toLocaleString('id-ID')) + '</div>';
        data.forEach(function (d) {
            if (data.length > 1) html += '<h2>' + esc(d.judul) + '</h2>';
            html += '<table>';
            d.grid.forEach(function (r, ri) {
                var tag = (ri === 0 && d.adaHead) ? 'th' : 'td';
                html += '<tr>' + r.map(function (v) {
                    var num = typeof nilai(v) === 'number' || /^-?[\d.,]+%?$/.test(v);
                    return '<' + tag + (num ? ' class="n"' : '') + '>' + esc(v) + '</' + tag + '>';
                }).join('') + '</tr>';
            });
            html += '</table>';
        });
        html += '</body></html>';

        var f = document.createElement('iframe');
        f.style.cssText = 'position:fixed;right:0;bottom:0;width:0;height:0;border:0;';
        document.body.appendChild(f);
        var doc = f.contentWindow.document;
        doc.open(); doc.write(html); doc.close();
        setTimeout(function () {
            f.contentWindow.focus();
            f.contentWindow.print();
            setTimeout(function () { f.remove(); }, 2000);
        }, 250);
    };

    // Kompatibel dengan tombol lama
    window.exportTableToExcel = function (id, nama) { window.eksporExcel(nama, id); };

    /**
     * Salin (Ctrl+C) dari halaman: nominal "Rp 1,234,567" / "-Rp 1,000" / "Rp -1,000" disalin sebagai angka polos
     * ("1234567", "-1000") tanpa Rp, koma, atau titik, sehingga langsung terbaca angka saat ditempel ke Excel.
     * Tampilan di layar tidak berubah. Isi kolom input tidak diubah.
     */
    var POLA_RP = /(?:(-)\s*)?Rp\s*(?:(-)\s*)?(\d{1,3}(?:[.,]\d{3})+|\d+)(?![\d.,]*\d)/g;
    function polosRp(teks) {
        return teks.replace(POLA_RP, function (_, m1, m2, angka) {
            return (m1 || m2 ? '-' : '') + angka.replace(/[.,]/g, '');
        });
    }
    window.salinPolosRp = polosRp;
    document.addEventListener('copy', function (e) {
        var ae = document.activeElement;
        if (ae && (ae.tagName === 'INPUT' || ae.tagName === 'TEXTAREA' || ae.isContentEditable)) return;
        var sel = window.getSelection();
        if (!sel || sel.isCollapsed || !sel.rangeCount || !e.clipboardData) return;
        var teks = sel.toString();
        if (!/Rp/.test(teks)) return;

        var wadah = document.createElement('div');
        for (var i = 0; i < sel.rangeCount; i++) wadah.appendChild(sel.getRangeAt(i).cloneContents());
        // Sel tabel yang terpotong (mis. hanya td) dibungkus tabel agar Excel tetap memecahnya per kolom
        if (wadah.querySelector('td, th') && !wadah.querySelector('table')) {
            var tb = document.createElement('table');
            if (wadah.querySelector('tr')) { var body = document.createElement('tbody'); while (wadah.firstChild) body.appendChild(wadah.firstChild); tb.appendChild(body); }
            else { var tr = document.createElement('tr'); while (wadah.firstChild) tr.appendChild(wadah.firstChild); tb.appendChild(tr); }
            wadah.appendChild(tb);
        }
        var jalan = document.createTreeWalker(wadah, NodeFilter.SHOW_TEXT, null), n, simpul = [];
        while ((n = jalan.nextNode())) simpul.push(n);
        simpul.forEach(function (t) { if (/Rp/.test(t.nodeValue)) t.nodeValue = polosRp(t.nodeValue); });
        // "Rp" dan angka kadang terpisah elemen (mis. <span>Rp</span> 1,234): gabungkan per sel lalu bersihkan
        Array.prototype.forEach.call(wadah.querySelectorAll('td, th'), function (c) {
            var isi = c.textContent;
            if (/^\s*-?\s*Rp\s*-?\s*[\d.,]+\s*$/.test(isi)) c.textContent = polosRp(isi.trim());
        });

        e.clipboardData.setData('text/plain', polosRp(teks));
        e.clipboardData.setData('text/html', wadah.innerHTML);
        e.preventDefault();
    });
})();
