<?php
/**
 * Helper Utilities untuk Sistem Barcode (Vibrant Multi-Color Edition)
 */

namespace App\Core;

class Helper {
    public static function formatKg(float|int|string|null $val, int $decimals = 2): string {
        if ($val === null || $val === '') return '0.00 kg';
        return number_format((float)$val, $decimals, ',', '.') . ' kg';
    }

    public static function formatNumber(float|int|string|null $val, int $decimals = 0): string {
        if ($val === null || $val === '') return '0';
        return number_format((float)$val, $decimals, ',', '.');
    }

    public static function formatRupiah(float|int|string|null $val, string $prefix = 'Rp '): string {
        if ($val === null || $val === '') return $prefix . '0';
        return $prefix . number_format((float)$val, 0, ',', '.');
    }

    public static function formatDateTime(?string $dateStr): string {
        if (empty($dateStr)) return '-';
        $timestamp = strtotime($dateStr);
        if (!$timestamp) return $dateStr;
        return date('d M Y H:i', $timestamp);
    }

    public static function formatDateOnly(?string $dateStr): string {
        if (empty($dateStr)) return '-';
        $timestamp = strtotime($dateStr);
        if (!$timestamp) return $dateStr;
        return date('d/m/Y', $timestamp);
    }

    public static function jsonResponse(array $data, int $statusCode = 200): void {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code($statusCode);
        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }

    public static function renderStatusBadge(string $status): string {
        $statusUpper = strtoupper($status);
        $badgeClass = match ($statusUpper) {
            'AKTIF', 'SUCCESS' => 'badge-success',
            'PENDING'          => 'badge-warning',
            'VOID', 'FAILED'   => 'badge-destructive',
            'KELUAR'           => 'badge-info',
            'DISESUAIKAN'      => 'badge-purple',
            default            => 'badge-secondary'
        };

        $icon = match ($statusUpper) {
            'AKTIF', 'SUCCESS' => '●',
            'KELUAR'           => '↗',
            'VOID', 'FAILED'   => '✕',
            'PENDING'          => '⏳',
            'DISESUAIKAN'      => '✎',
            default            => '•'
        };

        return sprintf('<span class="badge %s">%s %s</span>', $badgeClass, $icon, htmlspecialchars($statusUpper));
    }

    public static function renderMitraBadge(string $kodeMitra, string $namaMitra, string $hexColor = '#0284c7'): string {
        $cleanKode = strtolower(trim($kodeMitra));
        return sprintf(
            '<span class="mitra-tag %s" style="background: %s;"><strong>%s</strong> %s</span>',
            htmlspecialchars($cleanKode),
            htmlspecialchars($hexColor),
            htmlspecialchars($kodeMitra),
            htmlspecialchars($namaMitra)
        );
    }
}
