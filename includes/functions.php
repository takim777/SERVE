<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/flash.php';

/**
 * Menghasilkan URL relatif/absolut yang konsisten terhadap BASE_URL.
 */
function url(string $path = ''): string
{
    $cleanPath = ltrim($path, '/');
    if ($cleanPath === '') {
        return BASE_URL . '/';
    }
    return BASE_URL . '/' . $cleanPath;
}

/**
 * Melakukan escaping output HTML secara aman.
 */
function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Mengarahkan pengguna ke URL tertentu lalu menghentikan eksekusi skrip.
 */
function redirect(string $path): never
{
    $target = str_starts_with($path, 'http://') || str_starts_with($path, 'https://')
        ? $path
        : url($path);

    header('Location: ' . $target);
    exit;
}

/**
 * Memformat angka menjadi format Rupiah.
 */
function formatRupiah(float|int|string $amount): string
{
    return 'Rp ' . number_format((float) $amount, 0, ',', '.');
}

/**
 * Memformat tanggal atau datetime ke format tanggal/waktu yang rapi.
 */
function formatTanggal(?string $datetime, bool $withTime = false): string
{
    if (empty($datetime)) {
        return '-';
    }

    $timestamp = strtotime($datetime);
    if ($timestamp === false) {
        return e($datetime);
    }

    return $withTime
        ? date('d/m/Y H:i', $timestamp)
        : date('d/m/Y', $timestamp);
}

/**
 * Mengembalikan label bahasa Indonesia untuk role pengguna.
 */
function labelRole(?string $role): string
{
    return match ($role) {
        'admin'     => 'Administrator',
        'penyedia'  => 'Penyedia Jasa',
        'pelanggan' => 'Pelanggan',
        default     => 'Pengguna',
    };
}