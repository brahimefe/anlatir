<?php
// Sahne görsellerini yalnızca şifreyle giriş yapılmış oturuma verir.
// Görseller sahneler/ klasöründedir ve bu klasör doğrudan adresle açılamaz.
require __DIR__ . '/inc/baslat.php';
session_write_close();

if (empty($_SESSION['atolye'])) { http_response_code(403); exit; }
$kod = isset($_GET['f']) ? (string)$_GET['f'] : '';
if (!preg_match('/^(hp|sv|lotr)-(0[1-9]|1[0-2])$/', $kod)) { http_response_code(404); exit; }

$turler = array('jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp');
foreach ($turler as $uzanti => $mime) {
  $yol = __DIR__ . '/sahneler/' . $kod . '.' . $uzanti;
  if (is_file($yol)) {
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . filesize($yol));
    header('Cache-Control: private, max-age=3600');
    header('X-Robots-Tag: noindex');
    readfile($yol);
    exit;
  }
}
http_response_code(404);
