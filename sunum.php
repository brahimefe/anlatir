<?php
/* Sunum PDF'ini yalnızca eğitmene ve o an canlı derse bağlı katılımcılara verir */
require __DIR__ . '/inc/bootstrap.php';

$hafta = max(1, min(8, (int)($_GET['h'] ?? 0)));
$izin = is_admin();
if (!$izin) {
    $kur = kur_aktif();
    if ($kur && katilim()) {
        $c = canli($kur['id']);
        $izin = $c['aktif'] && (int)$c['hafta'] === $hafta;
    }
}
session_write_close();
$path = sunum_path($hafta);
if (!$izin || !is_file($path)) {
    http_response_code($izin ? 404 : 403);
    exit;
}
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="sunum.pdf"');
header('Cache-Control: no-store');
header('Content-Length: ' . filesize($path));
readfile($path);
