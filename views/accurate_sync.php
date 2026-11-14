<?php
/**
 * Monitoring Antrean & Integrasi REST API Accurate Online (Vibrant & Connected Edition)
 */

use App\Core\Helper;
use App\Config\Config;
use App\Services\AccurateApiService;

$pageTitle = 'Integrasi API Accurate Online - Sistem Barcode Multi-Mitra';
$cfg = Config::getAccurateConfig();
$queueList = AccurateApiService::getQueueList(30);

require_once __DIR__ . '/layout/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Integrasi REST API Accurate Online</h1>
        <p class="page-subtitle">Konfigurasi API Token resmi, otentikasi HMAC-SHA256, dan pemantauan antrean transaksi otomatis</p>
    </div>
    <div style="display: flex; gap: 8px;">
        <a href="Manual_Book_Sistem_Barcode_Inventory_Multi_Mitra.docx" class="btn btn-outline" download style="display: flex; align-items: center; gap: 6px;">
            <i data-lucide="file-text" style="width: 16px; height: 16px; color: var(--aktif-fg);"></i>
            <span>Manual Book</span>
        </a>
        <button type="button" class="btn btn-outline" onclick="openGuideModal()">
            <i data-lucide="help-circle" style="width: 16px; height: 16px;"></i>
            <span>Petunjuk Cara Konek</span>
        </button>
        <button type="button" class="btn btn-primary" id="btnSyncAllPending">
            <i data-lucide="refresh-cw" style="width: 16px; height: 16px;"></i>
            <span>Proses Antrean Pending</span>
        </button>
    </div>
</div>

<!-- 1. STATUS KONEKSI & PANEL PENGATURAN KREDENSIAL ACCURATE -->
<div class="card" style="margin-bottom: 24px;">
    <div class="card-header" style="flex-wrap: wrap; gap: 12px;">
        <div>
            <h2 class="card-title">
                <i data-lucide="key" style="width: 18px; height: 18px; color: var(--primary);"></i>
                <span>Konfigurasi API Token & Kredensial Developer</span>
            </h2>
            <div class="card-description">Otorisasi sistem barcode ke database Accurate Online menggunakan API Token resmi</div>
        </div>
        <div>
            <?php if (!empty($cfg['is_connected']) && !empty($cfg['session_id'])): ?>
                <span class="badge badge-success" style="padding: 6px 12px; font-size: 12px;">
                    <i data-lucide="check-circle-2" style="width: 14px; height: 14px; margin-right: 4px;"></i>
                    Live API Terhubung: <strong><?= htmlspecialchars($cfg['db_alias'] ?: ('ID ' . $cfg['db_id'])) ?></strong>
                </span>
            <?php elseif (!empty($cfg['api_token'])): ?>
                <span class="badge badge-warning" style="padding: 6px 12px; font-size: 12px;">
                    <i data-lucide="clock" style="width: 14px; height: 14px; margin-right: 4px;"></i>
                    Token Tersimpan
                </span>
            <?php else: ?>
                <span class="badge badge-purple" style="padding: 6px 12px; font-size: 12px;">
                    <i data-lucide="shield-alert" style="width: 14px; height: 14px; margin-right: 4px;"></i>
                    Mode Simulasi / Menunggu API Token
                </span>
            <?php endif; ?>
        </div>
    </div>

    <form id="formAccurateConfig" onsubmit="saveAccurateConfig(event)" style="padding: 16px 20px;">
        <div class="grid grid-cols-2" style="gap: 18px; margin-bottom: 16px;">
            <!-- Kolom Kiri: App Key & Signature Secret -->
            <div>
                <div class="form-group" style="margin-bottom: 14px;">
                    <label class="form-label" style="font-weight: 700; font-size: 12.5px;">
                        App Key:
                    </label>
                    <div style="display: flex; gap: 6px;">
                        <input type="text" id="cfgAppKey" class="form-control" value="<?= htmlspecialchars($cfg['app_key']) ?>" readonly style="background: var(--bg-muted); font-family: 'JetBrains Mono', monospace; font-size: 12px; font-weight: 600;">
                        <button type="button" class="btn btn-outline btn-sm" onclick="copyToClipboard('cfgAppKey')" title="Salin App Key">
                            <i data-lucide="copy" style="width: 14px; height: 14px;"></i>
                        </button>
                    </div>
                    <div style="font-size: 11px; color: var(--text-muted); margin-top: 4px;">Gunakan App Key ini saat mencari dan menginstall aplikasi di menu Accurate Store.</div>
                </div>

                <div class="form-group">
                    <label class="form-label" style="font-weight: 700; font-size: 12.5px;">
                        Signature Secret:
                    </label>
                    <div style="display: flex; gap: 6px;">
                        <input type="password" id="cfgSecret" class="form-control" value="<?= htmlspecialchars($cfg['signature_secret']) ?>" readonly style="background: var(--bg-muted); font-family: 'JetBrains Mono', monospace; font-size: 12px; font-weight: 600;">
                        <button type="button" class="btn btn-outline btn-sm" onclick="togglePasswordVisibility('cfgSecret')" title="Lihat Secret">
                            <i data-lucide="eye" style="width: 14px; height: 14px;"></i>
                        </button>
                        <button type="button" class="btn btn-outline btn-sm" onclick="copyToClipboard('cfgSecret')" title="Salin Signature Secret">
                            <i data-lucide="copy" style="width: 14px; height: 14px;"></i>
                        </button>
                    </div>
                    <div style="font-size: 11px; color: var(--text-muted); margin-top: 4px;">Digunakan untuk menghasilkan tanda tangan HMAC-SHA256 pada header setiap request API.</div>
                </div>
            </div>

            <!-- Kolom Kanan: API Token & Database Terpilih -->
            <div>
                <div class="form-group" style="margin-bottom: 14px;">
                    <label class="form-label" style="font-weight: 700; font-size: 12.5px; display: flex; justify-content: space-between;">
                        <span>API Token:</span>
                        <a href="javascript:void(0)" onclick="openGuideModal()" style="font-size: 11px; color: var(--primary); font-weight: 600; text-decoration: underline;">Cara dapatkan token?</a>
                    </label>
                    <textarea id="inputApiToken" name="api_token" class="form-control" rows="2" placeholder="Tempelkan (Paste) API Token panjang dari Accurate Online di sini..." style="font-family: 'JetBrains Mono', monospace; font-size: 12px; resize: vertical;"><?= htmlspecialchars($cfg['api_token']) ?></textarea>
                </div>

                <div class="form-group">
                    <label class="form-label" style="font-weight: 700; font-size: 12.5px;">
                        Database Perusahaan Terhubung:
                    </label>
                    <div style="display: flex; gap: 8px; align-items: center;">
                        <input type="text" id="inputDbAlias" name="db_alias" class="form-control" value="<?= htmlspecialchars($cfg['db_alias'] ?: ($cfg['db_id'] ? 'ID ' . $cfg['db_id'] : '')) ?>" placeholder="Belum ada database dipilih" readonly style="background: var(--bg-muted); font-size: 12.5px; font-weight: 700;">
                        <input type="hidden" id="inputDbId" name="db_id" value="<?= htmlspecialchars($cfg['db_id']) ?>">
                        <button type="button" class="btn btn-outline" id="btnTestConnect" onclick="testAndFetchDatabases()">
                            <i data-lucide="radio" style="width: 15px; height: 15px; color: var(--aktif-fg);"></i>
                            <span>Tes & Pilih DB</span>
                        </button>
                    </div>
                    <div id="dbStatusInfo" style="font-size: 11px; color: var(--text-muted); margin-top: 4px;">
                        Host: <code><?= htmlspecialchars($cfg['host']) ?></code> • Session: <code><?= !empty($cfg['session_id']) ? substr($cfg['session_id'], 0, 16) . '...' : '(belum ada sesi)' ?></code>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tombol Aksi Simpan -->
        <div style="display: flex; justify-content: space-between; align-items: center; border-top: 1px solid var(--border); padding-top: 14px;">
            <div style="font-size: 12px; color: var(--text-muted);">
                <i data-lucide="info" style="width: 14px; height: 14px; display: inline-block; vertical-align: middle;"></i>
                Setelah token dan database tersimpan, setiap penimbangan/pengeluaran otomatis terkirim langsung ke Accurate.
            </div>
            <div style="display: flex; gap: 8px;">
                <button type="submit" class="btn btn-primary" id="btnSaveConfig">
                    <i data-lucide="save" style="width: 16px; height: 16px;"></i>
                    <span>Simpan Pengaturan API</span>
                </button>
            </div>
        </div>
    </form>
</div>

<!-- 2. DAFTAR DATABASE MODAL / LIST CONTAINER (Pop-up saat klik Tes & Pilih DB) -->
<div id="databaseSelectModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.65); z-index: 100; align-items: center; justify-content: center; backdrop-filter: blur(5px);">
    <div style="background-color: var(--bg-card); width: 90%; max-width: 620px; border-radius: var(--radius); padding: 24px; box-shadow: var(--shadow-lg); border: 1.5px solid var(--border);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
            <div>
                <h3 style="margin: 0; font-size: 16px; font-weight: 800; color: var(--text-main);">Daftar Database Accurate Online</h3>
                <p style="margin: 2px 0 0 0; font-size: 12px; color: var(--text-muted);">Pilih database perusahaan yang akan disinkronisasikan dengan sistem gudang</p>
            </div>
            <button type="button" onclick="document.getElementById('databaseSelectModal').style.display='none'" class="btn btn-ghost btn-sm" style="font-size: 20px;">&times;</button>
        </div>

        <div id="dbListLoading" style="text-align: center; padding: 24px; color: var(--text-muted);">
            <i data-lucide="loader-2" class="spin" style="width: 28px; height: 28px; color: var(--primary); margin-bottom: 8px;"></i>
            <div>Menghubungi server Accurate Online & memvalidasi signature...</div>
        </div>

        <div id="dbListContainer" style="display: none; max-height: 320px; overflow-y: auto;">
            <!-- Item database diinjeksi via JavaScript -->
        </div>

        <div style="margin-top: 18px; text-align: right;">
            <button type="button" class="btn btn-outline" onclick="document.getElementById('databaseSelectModal').style.display='none'">Batal</button>
        </div>
    </div>
</div>

<!-- 3. MODAL PETUNJUK CARA MENGHUBUNGKAN ACCURATE (STEP-BY-STEP) -->
<div id="guideModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.65); z-index: 100; align-items: center; justify-content: center; backdrop-filter: blur(5px);">
    <div style="background-color: var(--bg-card); width: 90%; max-width: 720px; border-radius: var(--radius); padding: 24px; box-shadow: var(--shadow-lg); border: 1.5px solid var(--border); max-height: 85vh; overflow-y: auto;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
            <div>
                <h3 style="margin: 0; font-size: 17px; font-weight: 800; color: var(--text-main);">Panduan Menghubungkan API Token Accurate Online</h3>
                <p style="margin: 2px 0 0 0; font-size: 12.5px; color: var(--text-muted);">Langkah mudah mengaktifkan koneksi dari dashboard Accurate Online pengguna</p>
            </div>
            <button type="button" onclick="document.getElementById('guideModal').style.display='none'" class="btn btn-ghost btn-sm" style="font-size: 20px;">&times;</button>
        </div>

        <div style="display: flex; flex-direction: column; gap: 14px; font-size: 13.5px; line-height: 1.6;">
            <div style="display: flex; gap: 12px; background: var(--bg-muted); padding: 12px 16px; border-radius: 8px;">
                <div style="background: var(--primary); color: #fff; width: 28px; height: 28px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 800; flex-shrink: 0;">1</div>
                <div>
                    <strong>Buka Akun Accurate Online</strong>
                    <div style="color: var(--text-muted); font-size: 12.5px;">Buka <a href="https://accurate.id" target="_blank" style="color: var(--primary); font-weight: 600;">https://accurate.id</a> di browser Anda dan buka database perusahaan yang ingin dihubungkan.</div>
                </div>
            </div>

            <div style="display: flex; gap: 12px; background: var(--bg-muted); padding: 12px 16px; border-radius: 8px;">
                <div style="background: var(--primary); color: #fff; width: 28px; height: 28px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 800; flex-shrink: 0;">2</div>
                <div>
                    <strong>Masuk ke Menu Accurate Store</strong>
                    <div style="color: var(--text-muted); font-size: 12.5px;">Pada menu navigasi sebelah kiri, klik menu <strong>Pengaturan</strong> &gt; <strong>Accurate Store</strong>.</div>
                </div>
            </div>

            <div style="display: flex; gap: 12px; background: var(--bg-muted); padding: 12px 16px; border-radius: 8px;">
                <div style="background: var(--primary); color: #fff; width: 28px; height: 28px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 800; flex-shrink: 0;">3</div>
                <div>
                    <strong>Cari Aplikasi Menggunakan App Key</strong>
                    <div style="color: var(--text-muted); font-size: 12.5px;">
                        Cari aplikasi <code>api-warehouse</code> atau gunakan App Key:
                        <div style="margin-top: 4px; display: inline-block; background: #0b1120; color: #38bdf8; padding: 3px 8px; border-radius: 5px; font-family: monospace; font-size: 12px;">f33bdc72-f56f-4393-83f7-2b5ef042dc13</div>
                    </div>
                </div>
            </div>

            <div style="display: flex; gap: 12px; background: var(--bg-muted); padding: 12px 16px; border-radius: 8px;">
                <div style="background: var(--primary); color: #fff; width: 28px; height: 28px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 800; flex-shrink: 0;">4</div>
                <div>
                    <strong>Install & Buat API Token</strong>
                    <div style="color: var(--text-muted); font-size: 12.5px;">Klik tombol <strong>Install Aplikasi</strong>, lalu klik <strong>Buat API Token</strong>. Salin kode token panjang yang muncul.</div>
                </div>
            </div>

            <div style="display: flex; gap: 12px; background: var(--bg-muted); padding: 12px 16px; border-radius: 8px;">
                <div style="background: var(--ok-solid); color: #fff; width: 28px; height: 28px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 800; flex-shrink: 0;">5</div>
                <div>
                    <strong>Tempel Token ke Sistem Gudang</strong>
                    <div style="color: var(--text-muted); font-size: 12.5px;">Tempel token tersebut ke kolom <strong>API Token</strong> di atas, lalu klik <strong>Tes & Pilih DB</strong> untuk menyimpan database. Selesai!</div>
                </div>
            </div>
        </div>

        <div style="margin-top: 20px; text-align: right;">
            <button type="button" class="btn btn-primary" onclick="document.getElementById('guideModal').style.display='none'">Mengerti, Tutup Panduan</button>
        </div>
    </div>
</div>

<!-- 4. TABEL ANTREAN SINKRONISASI -->
<div class="card">
    <div class="card-header">
        <div>
            <h2 class="card-title">
                <i data-lucide="list-ordered" style="width: 18px; height: 18px; color: var(--purple);"></i>
                <span>Antrean Pengiriman Transaksi Gudang ke Accurate</span>
            </h2>
            <div class="card-description">Daftar dokumen transaksi pengeluaran dan mutasi yang siap tersinkron ke Accurate Online</div>
        </div>
        <div style="display: flex; gap: 8px; align-items: center;">
            <?php $eksporTabel = 'tableAccurateSync'; $eksporNama = 'Log_Antrean_Accurate_Online'; include __DIR__ . '/layout/ekspor.php'; ?>
            <span class="badge badge-purple"><?= count($queueList) ?> Dokumen</span>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table-custom" id="tableAccurateSync">
            <thead>
                <tr>
                    <th>ID Antrean</th>
                    <th>No. Transaksi Gudang</th>
                    <th>Mitra Pemilik</th>
                    <th style="text-align: right;">Total Netto</th>
                    <th>Endpoint API Accurate</th>
                    <th>Percobaan</th>
                    <th>Status</th>
                    <th style="text-align: center;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($queueList)): ?>
                    <tr><td colspan="8" style="text-align: center; color: var(--text-muted); padding: 34px;">Belum ada antrean transaksi. Transaksi pengeluaran barang akan otomatis masuk ke sini.</td></tr>
                <?php else: ?>
                    <?php foreach ($queueList as $q): ?>
                    <tr>
                        <td><code>#<?= $q['id'] ?></code></td>
                        <td>
                            <strong style="font-family: 'JetBrains Mono', monospace; font-size: 12.5px; color: var(--text-main);"><?= htmlspecialchars($q['no_transaksi']) ?></strong>
                            <div style="font-size: 11px; color: var(--text-muted);"><?= Helper::formatDateTime($q['created_at']) ?></div>
                        </td>
                        <td>
                            <span class="mitra-tag <?= strtolower($q['kode_mitra']) ?>" style="background: <?= htmlspecialchars($q['warna_identitas'] ?? '#0284c7') ?>; padding: 2px 7px; font-size: 11px;">
                                <?= htmlspecialchars($q['kode_mitra']) ?>
                            </span>
                            <span style="font-size: 12px; margin-left: 5px; font-weight: 600;"><?= htmlspecialchars($q['nama_mitra']) ?></span>
                        </td>
                        <td style="text-align: right; font-weight: 800; color: var(--ok-fg); font-size: 14px;"><?= Helper::formatKg($q['total_kg']) ?></td>
                        <td><code style="font-size: 11px;"><?= htmlspecialchars($q['endpoint']) ?></code></td>
                        <td><strong><?= $q['attempt_count'] ?></strong> / <?= $q['max_attempts'] ?></td>
                        <td>
                            <?php if ($q['status'] === 'SUCCESS'): ?>
                                <span class="badge badge-success">Terkirim</span>
                            <?php elseif ($q['status'] === 'PENDING'): ?>
                                <span class="badge badge-warning">Pending</span>
                            <?php else: ?>
                                <span class="badge badge-destructive">Gagal</span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align: center;">
                            <div style="display: flex; gap: 6px; justify-content: center;">
                                <button type="button" class="btn btn-outline btn-sm" onclick="showPayload(<?= htmlspecialchars(json_encode($q['payload_json'])) ?>)">
                                    JSON
                                </button>
                                <?php if ($q['status'] !== 'SUCCESS'): ?>
                                    <button type="button" class="btn btn-primary btn-sm" onclick="syncQueue(<?= $q['id'] ?>)">
                                        Kirim
                                    </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Dialog JSON Payload Inspector -->
<div id="jsonModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.65); z-index: 100; align-items: center; justify-content: center; backdrop-filter: blur(5px);">
    <div style="background-color: var(--bg-card); width: 90%; max-width: 650px; border-radius: var(--radius); padding: 24px; box-shadow: var(--shadow-lg); border: 1.5px solid var(--border);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
            <h3 style="margin: 0; font-size: 16px; font-weight: 800; color: var(--text-main);">Payload JSON REST API Accurate Online</h3>
            <button type="button" onclick="document.getElementById('jsonModal').style.display='none'" class="btn btn-ghost btn-sm" style="font-size: 18px;">&times;</button>
        </div>
        <pre id="jsonViewer" style="background-color: #09101d; color: #38bdf8; padding: 16px; border-radius: 10px; font-family: 'JetBrains Mono', monospace; font-size: 12px; max-height: 400px; overflow-y: auto; border: 1px solid #1e293b;"></pre>
        <div style="margin-top: 16px; text-align: right;">
            <button type="button" class="btn btn-outline" onclick="document.getElementById('jsonModal').style.display='none'">Tutup</button>
        </div>
    </div>
</div>

<script>
    function openGuideModal() {
        document.getElementById('guideModal').style.display = 'flex';
    }

    function togglePasswordVisibility(fieldId) {
        const input = document.getElementById(fieldId);
        if (input.type === 'password') {
            input.type = 'text';
        } else {
            input.type = 'password';
        }
    }

    function copyToClipboard(fieldId) {
        const input = document.getElementById(fieldId);
        input.select();
        input.setSelectionRange(0, 99999);
        navigator.clipboard.writeText(input.value).then(() => {
            alert('Berhasil disalin ke clipboard: ' + input.value);
        }).catch(() => {
            document.execCommand('copy');
            alert('Berhasil disalin: ' + input.value);
        });
    }

    async function testAndFetchDatabases() {
        const token = document.getElementById('inputApiToken').value.trim();
        if (!token) {
            alert('Harap masukkan API Token terlebih dahulu sebelum melakukan tes koneksi!');
            document.getElementById('inputApiToken').focus();
            return;
        }

        const modal = document.getElementById('databaseSelectModal');
        const loading = document.getElementById('dbListLoading');
        const container = document.getElementById('dbListContainer');

        modal.style.display = 'flex';
        loading.style.display = 'block';
        container.style.display = 'none';
        container.innerHTML = '';

        try {
            const formData = new FormData();
            formData.append('api_token', token);

            const resp = await fetch('index.php?action=test_accurate_api', {
                method: 'POST',
                body: formData
            });
            const data = await resp.json();

            loading.style.display = 'none';

            if (data.success && data.databases && data.databases.length > 0) {
                container.style.display = 'block';
                let html = '<div style="display: flex; flex-direction: column; gap: 8px;">';
                data.databases.forEach(db => {
                    html += `
                        <div style="display: flex; justify-content: space-between; align-items: center; padding: 12px 16px; border: 1px solid var(--border); border-radius: 8px; background: var(--bg-surface); hover: background: var(--bg-hover);">
                            <div>
                                <div style="font-weight: 800; font-size: 14px; color: var(--text-main);">${escapeHtml(db.alias || db.name || 'Database ' + db.id)}</div>
                                <div style="font-size: 11px; color: var(--text-muted); margin-top: 2px;">
                                    ID Database: <code>${db.id}</code> ${db.expiredDate ? '• Exp: ' + db.expiredDate : ''}
                                </div>
                            </div>
                            <button type="button" class="btn btn-primary btn-sm" onclick="selectDatabase('${db.id}', '${escapeHtml(db.alias || db.name || '')}')">
                                <span>Pilih Database Ini</span>
                            </button>
                        </div>
                    `;
                });
                html += '</div>';
                container.innerHTML = html;
            } else {
                container.style.display = 'block';
                container.innerHTML = `
                    <div style="background: rgba(239, 68, 68, 0.1); border: 1px solid var(--bad-solid); color: var(--bad-fg); padding: 16px; border-radius: 8px;">
                        <div style="font-weight: 800; margin-bottom: 4px;">Koneksi Gagal</div>
                        <div style="font-size: 12px;">${escapeHtml(data.message || 'Tidak ada database ditemukan atau token tidak valid.')}</div>
                    </div>
                `;
            }
        } catch (e) {
            loading.style.display = 'none';
            container.style.display = 'block';
            container.innerHTML = `<div style="color: var(--bad-fg); padding: 16px;">Terjadi kesalahan: ${escapeHtml(e.message)}</div>`;
        }
    }

    async function selectDatabase(dbId, dbAlias) {
        document.getElementById('inputDbId').value = dbId;
        document.getElementById('inputDbAlias').value = dbAlias ? `${dbAlias} (ID: ${dbId})` : `Database ID: ${dbId}`;
        document.getElementById('databaseSelectModal').style.display = 'none';

        // Langsung simpan dan buka sesi database
        const token = document.getElementById('inputApiToken').value.trim();
        const btn = document.getElementById('btnSaveConfig');
        btn.disabled = true;
        btn.innerText = 'Menyimpan & Membuka Sesi...';

        try {
            const formData = new FormData();
            formData.append('api_token', token);
            formData.append('db_id', dbId);
            formData.append('db_alias', dbAlias);

            const resp = await fetch('index.php?action=save_accurate_config', {
                method: 'POST',
                body: formData
            });
            const resData = await resp.json();

            if (resData.success) {
                alert('' + resData.message + '\nDatabase: ' + (dbAlias || dbId));
                location.reload();
            } else {
                alert('Peringatan: ' + resData.message);
                btn.disabled = false;
                btn.innerText = 'Simpan Pengaturan API';
            }
        } catch (e) {
            alert('Kesalahan saat menyimpan: ' + e.message);
            btn.disabled = false;
            btn.innerText = 'Simpan Pengaturan API';
        }
    }

    async function saveAccurateConfig(e) {
        e.preventDefault();
        const token = document.getElementById('inputApiToken').value.trim();
        const dbId = document.getElementById('inputDbId').value.trim();
        const dbAlias = document.getElementById('inputDbAlias').value.trim();

        const btn = document.getElementById('btnSaveConfig');
        btn.disabled = true;
        btn.innerText = 'Menyimpan...';

        try {
            const formData = new FormData();
            formData.append('api_token', token);
            formData.append('db_id', dbId);
            formData.append('db_alias', dbAlias);

            const resp = await fetch('index.php?action=save_accurate_config', {
                method: 'POST',
                body: formData
            });
            const data = await resp.json();

            if (data.success) {
                alert('' + data.message);
                location.reload();
            } else {
                alert('Gagal: ' + data.message);
                btn.disabled = false;
                btn.innerText = 'Simpan Pengaturan API';
            }
        } catch (err) {
            alert('Terjadi kesalahan: ' + err.message);
            btn.disabled = false;
            btn.innerText = 'Simpan Pengaturan API';
        }
    }

    function showPayload(jsonStr) {
        try {
            const parsed = JSON.parse(jsonStr);
            document.getElementById('jsonViewer').innerText = JSON.stringify(parsed, null, 2);
        } catch (e) {
            document.getElementById('jsonViewer').innerText = jsonStr;
        }
        document.getElementById('jsonModal').style.display = 'flex';
    }

    async function syncQueue(queueId) {
        try {
            const resp = await fetch('index.php?action=retry_sync&queue_id=' + queueId);
            const data = await resp.json();
            if (data.success) {
                alert('' + data.message + '\nNomor Dokumen Accurate: ' + (data.docNo || '-'));
                location.reload();
            } else {
                alert('Gagal Sinkronisasi: ' + data.message);
            }
        } catch (e) {
            alert('Kesalahan saat mengirim data: ' + e.message);
        }
    }

    document.getElementById('btnSyncAllPending').addEventListener('click', async () => {
        alert('Memproses antrean pending ke Accurate Online...');
        location.reload();
    });

    function escapeHtml(text) {
        if (!text) return '';
        return String(text)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }
</script>

<?php require_once __DIR__ . '/layout/footer.php'; ?>
