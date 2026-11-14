<?php
/**
 * Halaman Login Sistem Barcode Inventory Multi-Mitra
 * Mendukung 3 Role Akses: 1. Nia (Admin), 2. Arif, 3. Operator
 * (Sementara semua role dapat mengakses seluruh modul)
 */

use App\Models\User;

if (!class_exists('App\Models\User')) {
    require_once __DIR__ . '/../app/Models/User.php';
}

$errorMsg = $_GET['error'] ?? null;
$successMsg = $_GET['msg'] ?? null;
User::getAllActive(); // pastikan akun default tersedia
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <script>try { var t = localStorage.getItem('theme'); if (t === 'dark' || t === 'light') document.documentElement.setAttribute('data-theme', t); } catch (e) {}</script>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Masuk Sistem - Barcode Inventory Multi-Mitra</title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/svg+xml" href="assets/img/favicon.svg">
    <link rel="icon" type="image/png" sizes="32x32" href="assets/img/favicon-32x32.png">
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Geist:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Lucide Icons & SweetAlert2 -->
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <!-- CSS Utama -->
    <link rel="stylesheet" href="assets/css/style.css?v=<?= filemtime(__DIR__ . '/../assets/css/style.css') ?>">

    <style>
        .login-page-wrapper {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            background: radial-gradient(circle at 10% 20%, rgba(2, 132, 199, 0.08) 0%, transparent 40%),
                        radial-gradient(circle at 90% 80%, rgba(2, 132, 199, 0.08) 0%, transparent 40%),
                        var(--bg-page);
        }

        .login-card {
            width: 100%;
            max-width: 520px;
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 20px;
            box-shadow: 0 20px 40px -15px rgba(0, 0, 0, 0.1), 0 0 1px 1px var(--border);
            padding: 36px 32px;
            position: relative;
            overflow: hidden;
        }

        .login-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: var(--primary-gradient);
        }

        .login-header {
            text-align: center;
            margin-bottom: 28px;
        }

        .login-logo {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 56px;
            height: 56px;
            border-radius: 14px;
            background: var(--primary-gradient);
            color: #ffffff;
            margin-bottom: 16px;
            box-shadow: 0 8px 16px -4px rgba(2, 132, 199, 0.35);
        }

        .login-title {
            font-size: 22px;
            font-weight: 800;
            color: var(--text-main);
            margin: 0 0 6px;
            letter-spacing: -0.02em;
        }

        .login-subtitle {
            font-size: 13.5px;
            color: var(--text-muted);
            margin: 0;
            line-height: 1.5;
        }

        /* Role Quick Selection */
        .role-cards-container {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-bottom: 24px;
        }

        .role-card-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 16px;
            border-radius: 12px;
            border: 1.5px solid var(--border);
            background: var(--bg-surface);
            transition: all 0.2s ease;
            text-decoration: none;
            cursor: pointer;
        }

        .role-card-item:hover {
            border-color: var(--primary);
            background: var(--bg-hover);
            transform: translateY(-1.5px);
            box-shadow: 0 6px 14px -3px rgba(2, 132, 199, 0.12);
        }

        .role-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .role-avatar {
            width: 38px;
            height: 38px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 14px;
            color: #fff;
            flex-shrink: 0;
        }

        .avatar-nia {
            background: #0369a1;
            box-shadow: 0 4px 10px rgba(124, 58, 237, 0.25);
        }

        .avatar-arif {
            background: #0284c7;
            box-shadow: 0 4px 10px rgba(2, 132, 199, 0.25);
        }

        .avatar-operator {
            background: #059669;
            box-shadow: 0 4px 10px rgba(5, 150, 105, 0.25);
        }

        .role-text-name {
            font-size: 14.5px;
            font-weight: 700;
            color: var(--text-main);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .role-badge {
            font-size: 10.5px;
            font-weight: 700;
            padding: 2px 7px;
            border-radius: 6px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .badge-admin {
            background: rgba(124, 58, 237, 0.12);
            color: var(--aktif-fg);
            border: 1px solid rgba(124, 58, 237, 0.25);
        }

        .badge-arif {
            background: rgba(2, 132, 199, 0.12);
            color: var(--aktif-fg);
            border: 1px solid rgba(2, 132, 199, 0.25);
        }

        .badge-operator {
            background: rgba(5, 150, 105, 0.12);
            color: var(--ok-fg);
            border: 1px solid rgba(5, 150, 105, 0.25);
        }

        .role-text-desc {
            font-size: 11.5px;
            color: var(--text-muted);
            margin-top: 2px;
        }

        .role-btn-enter {
            font-size: 12px;
            font-weight: 700;
            color: var(--primary);
            display: flex;
            align-items: center;
            gap: 4px;
            padding: 6px 12px;
            border-radius: 8px;
            background: rgba(2, 132, 199, 0.06);
            transition: all 0.15s ease;
        }

        .role-card-item:hover .role-btn-enter {
            background: var(--primary);
            color: #ffffff;
        }

        /* Divider */
        .login-divider {
            display: flex;
            align-items: center;
            text-align: center;
            margin: 22px 0;
            color: var(--text-light);
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .login-divider::before,
        .login-divider::after {
            content: '';
            flex: 1;
            border-bottom: 1px solid var(--border);
        }

        .login-divider span {
            padding: 0 14px;
        }

        /* Form Inputs */
        .form-group {
            margin-bottom: 16px;
        }

        .form-label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: var(--text-main);
            margin-bottom: 6px;
        }

        .input-group {
            position: relative;
            display: flex;
            align-items: center;
        }

        .input-icon-left {
            position: absolute;
            left: 12px;
            color: var(--text-muted);
            pointer-events: none;
            display: flex;
            align-items: center;
        }

        .login-input {
            width: 100%;
            height: 42px;
            padding: 8px 12px 8px 38px;
            font-size: 13.5px;
            border: 1.5px solid var(--border);
            border-radius: 10px;
            background: var(--bg-surface);
            color: var(--text-main);
            transition: all 0.2s ease;
            outline: none;
        }

        .login-input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(2, 132, 199, 0.15);
        }

        .btn-toggle-pwd {
            position: absolute;
            right: 12px;
            background: transparent;
            border: none;
            color: var(--text-muted);
            cursor: pointer;
            padding: 4px;
            display: flex;
            align-items: center;
        }

        .btn-toggle-pwd:hover {
            color: var(--text-main);
        }

        .btn-submit-login {
            width: 100%;
            height: 44px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            background: var(--primary-gradient);
            color: #ffffff;
            font-size: 14px;
            font-weight: 700;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.2s ease;
            box-shadow: 0 4px 12px rgba(2, 132, 199, 0.25);
            margin-top: 20px;
        }

        .btn-submit-login:hover {
            opacity: 0.95;
            transform: translateY(-1px);
            box-shadow: 0 6px 16px rgba(2, 132, 199, 0.35);
        }

        /* Note Banner */
        .info-access-banner {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            padding: 12px 14px;
            border-radius: 10px;
            background: rgba(14, 165, 233, 0.08);
            border: 1px solid rgba(14, 165, 233, 0.22);
            color: var(--text-main);
            font-size: 12px;
            line-height: 1.45;
            margin-top: 24px;
        }

        .info-access-banner i {
            color: var(--aktif-fg);
            flex-shrink: 0;
            margin-top: 1px;
        }
    </style>
</head>
<body>
    <div class="login-page-wrapper">
        <div class="login-card">
            <!-- Header Brand -->
            <div class="login-header">
                <div class="login-logo">
                    <i data-lucide="scan-barcode" style="width: 30px; height: 30px;"></i>
                </div>
                <h1 class="login-title">Akses Masuk Sistem</h1>
                <p class="login-subtitle">Sistem Barcode Inventory Multi-Mitra (Catch-Weight)</p>
            </div>

            <?php if (!empty($errorMsg)): ?>
                <div style="background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.3); color: var(--bad-fg); padding: 12px 14px; border-radius: 10px; font-size: 13px; margin-bottom: 20px; display: flex; align-items: center; gap: 8px;">
                    <i data-lucide="alert-circle" style="width: 18px; height: 18px; flex-shrink: 0;"></i>
                    <span><?= htmlspecialchars($errorMsg) ?></span>
                </div>
            <?php endif; ?>

            <?php if (!empty($successMsg)): ?>
                <div style="background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.3); color: var(--ok-fg); padding: 12px 14px; border-radius: 10px; font-size: 13px; margin-bottom: 20px; display: flex; align-items: center; gap: 8px;">
                    <i data-lucide="check-circle" style="width: 18px; height: 18px; flex-shrink: 0;"></i>
                    <span><?= htmlspecialchars($successMsg) ?></span>
                </div>
            <?php endif; ?>

            <!-- FORM MANUAL LOGIN -->
            <form action="index.php?action=login" method="POST" id="manualLoginForm">
                <div class="form-group">
                    <label class="form-label" for="username">Username</label>
                    <div class="input-group">
                        <span class="input-icon-left">
                            <i data-lucide="user" style="width: 17px; height: 17px;"></i>
                        </span>
                        <input type="text" id="username" name="username" class="login-input" placeholder="Masukkan username" required autocomplete="username">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="password">Password</label>
                    <div class="input-group">
                        <span class="input-icon-left">
                            <i data-lucide="lock" style="width: 17px; height: 17px;"></i>
                        </span>
                        <input type="password" id="password" name="password" class="login-input" placeholder="Masukkan password" required autocomplete="current-password">
                        <button type="button" class="btn-toggle-pwd" id="togglePasswordBtn" title="Lihat/Sembunyikan Password">
                            <i data-lucide="eye" id="eyeIcon" style="width: 17px; height: 17px;"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn-submit-login">
                    <i data-lucide="log-in" style="width: 17px; height: 17px;"></i>
                    <span>Masuk ke Sistem</span>
                </button>
            </form>
        </div>
    </div>

    <script>
        // Inisialisasi ikon Lucide
        lucide.createIcons();

        // Toggle Password Visibility
        const toggleBtn = document.getElementById('togglePasswordBtn');
        const pwdInput = document.getElementById('password');
        const eyeIcon = document.getElementById('eyeIcon');

        if (toggleBtn && pwdInput) {
            toggleBtn.addEventListener('click', function() {
                const isPassword = pwdInput.type === 'password';
                pwdInput.type = isPassword ? 'text' : 'password';
                eyeIcon.setAttribute('data-lucide', isPassword ? 'eye-off' : 'eye');
                lucide.createIcons();
            });
        }

        // Terapkan tema gelap/terang sesuai pengaturan sebelumnya
        const savedTheme = localStorage.getItem('theme') || 'light';
        document.documentElement.setAttribute('data-theme', savedTheme);
    </script>
</body>
</html>
