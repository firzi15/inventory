@echo off
setlocal EnableDelayedExpansion
cd /d "%~dp0"
title Ngrok Tunnel - Sistem Barcode Inventory
color 0a

echo =======================================================================
echo          PENGATURAN DAN MENJALANKAN NGROK TUNNEL (PORT 80)
echo =======================================================================
echo.

:: 1. Cek apakah ngrok terpasang di sistem
where ngrok >nul 2>nul
if %errorlevel% neq 0 (
    color 0c
    echo [ERROR] ngrok tidak ditemukan di sistem!
    echo Silakan pastikan ngrok terinstal.
    echo.
    pause
    exit /b
)

:INPUT_TOKEN_CHECK
:: 2. Cek apakah konfigurasi / authtoken sudah ada
ngrok config check >nul 2>nul
if %errorlevel% neq 0 (
    color 0e
    echo [PERHATIAN] Authtoken ngrok belum terpasang.
    echo Buka https://dashboard.ngrok.com/get-started/your-authtoken untuk ambil token.
    echo.
    set /p USER_TOKEN="Masukkan Authtoken Ngrok: "
    if defined USER_TOKEN (
        echo Menyimpan authtoken...
        ngrok config add-authtoken !USER_TOKEN!
    ) else (
        echo Input dibatalkan.
        pause
        exit /b
    )
)

:START_TUNNEL
cls
color 0a
echo =======================================================================
echo   MEMBUKA NGROK TUNNEL KE APACHE XAMPP (PORT 80)...
echo =======================================================================
echo.
echo URL Lokal Project : http://localhost/project/
echo.
echo Jika berhasil terhubung, salin URL pada baris "Forwarding"
echo Contoh: https://xxxxx.ngrok-free.app/project/
echo =======================================================================
echo.

ngrok http 80

:: Jika ngrok berhenti atau error (misal token invalid):
color 0c
echo.
echo =======================================================================
echo [PERINGATAN] Ngrok tertutup atau koneksi gagal!
echo.
echo Jika muncul pesan "ERR_NGROK_107: authtoken is invalid",
echo artinya token akun ngrok Anda ditolak atau telah kadaluarsa/berganti.
echo =======================================================================
echo.
echo Pilihan tindakan:
echo [1] Masukkan / Ganti Authtoken baru
echo [2] Keluar
echo.
set /p PILIHAN="Pilih [1 atau 2]: "

if "%PILIHAN%"=="1" (
    echo.
    set /p NEW_TOKEN="Paste / Masukkan Authtoken baru dari dashboard ngrok: "
    if defined NEW_TOKEN (
        echo Menyimpan authtoken baru...
        ngrok config add-authtoken !NEW_TOKEN!
        goto START_TUNNEL
    )
)

echo.
echo Menutup program...
pause
