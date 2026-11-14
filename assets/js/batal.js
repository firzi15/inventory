/**
 * Pembatalan dokumen dengan alasan wajib.
 * batalDokumen('batal_pengeluaran', { id: 12 }, 'surat jalan keluar TRX-OUT-...')
 */
async function batalDokumen(action, params, namaDokumen) {
    const hasil = await Swal.fire({
        title: 'Batalkan ' + namaDokumen + '?',
        html: '<div style="text-align:left;font-size:13px;color:#475569;">Dokumen tidak dihapus: statusnya menjadi Batal dan keuangan terkait ikut dikoreksi.</div>',
        input: 'textarea',
        inputLabel: 'Alasan pembatalan',
        inputPlaceholder: 'Contoh: salah input, salah mitra, barang retur',
        inputAttributes: { 'aria-label': 'Alasan pembatalan' },
        showCancelButton: true,
        confirmButtonText: 'Batalkan Dokumen',
        cancelButtonText: 'Kembali',
        confirmButtonColor: '#dc2626',
        icon: 'warning',
        preConfirm: (v) => {
            if (!v || v.trim().length < 3) {
                Swal.showValidationMessage('Alasan pembatalan wajib diisi');
                return false;
            }
            return v.trim();
        }
    });
    if (!hasil.isConfirmed) return;

    const fd = new FormData();
    Object.keys(params).forEach(k => fd.append(k, params[k]));
    fd.append('alasan', hasil.value);

    try {
        const resp = await fetch('index.php?action=' + action, { method: 'POST', body: fd });
        const res = await resp.json();
        if (res.success) {
            await Swal.fire({ icon: 'success', title: 'Dibatalkan', text: res.message, confirmButtonText: 'OK' });
            window.location.reload();
        } else {
            Swal.fire({ icon: 'error', title: 'Tidak dapat dibatalkan', text: res.message });
        }
    } catch (e) {
        Swal.fire({ icon: 'error', title: 'Kesalahan jaringan', text: 'Coba lagi beberapa saat.' });
    }
}

/** Hapus master dengan konfirmasi; pengecekan pemakaian dilakukan server. */
async function hapusMaster(action, id, nama) {
    const r = await Swal.fire({
        title: 'Hapus ' + nama + '?',
        text: 'Data dihapus permanen. Hanya bisa jika belum dipakai di menu lain.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Ya, Hapus',
        cancelButtonText: 'Batal',
        confirmButtonColor: '#dc2626'
    });
    if (r.isConfirmed) {
        window.location.href = 'index.php?action=' + action + '&id=' + id;
    }
}

/** Ubah jatuh tempo faktur pemasok (FAKTUR) atau nota mitra (NOTA). */
async function ubahJatuhTempo(jenis, id, tanggalSekarang, nama) {
    const r = await Swal.fire({
        title: 'Ubah jatuh tempo',
        html: '<div style="text-align:left;font-size:13px;color:#475569;margin-bottom:8px;">' + String(nama).replace(/</g, '&lt;') + '</div>',
        input: 'date',
        inputValue: tanggalSekarang,
        showCancelButton: true,
        confirmButtonText: 'Simpan',
        cancelButtonText: 'Batal',
        preConfirm: (v) => {
            if (!v) { Swal.showValidationMessage('Tanggal wajib diisi'); return false; }
            return v;
        }
    });
    if (!r.isConfirmed) return;
    const fd = new FormData();
    fd.append('jenis', jenis);
    fd.append('id', id);
    fd.append('tanggal', r.value);
    try {
        const resp = await fetch('index.php?action=ubah_jatuh_tempo', { method: 'POST', body: fd });
        const res = await resp.json();
        if (res.success) {
            await Swal.fire({ icon: 'success', title: 'Tersimpan', text: res.message });
            window.location.reload();
        } else {
            Swal.fire({ icon: 'error', title: 'Tidak dapat diubah', text: res.message });
        }
    } catch (e) {
        Swal.fire({ icon: 'error', title: 'Kesalahan jaringan', text: 'Coba lagi beberapa saat.' });
    }
}
