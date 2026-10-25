<?php
// Ortak başlangıç: ayarlar, veri ve oturum.
ini_set('display_errors', '0');

// config.php sunucuda elle oluşturulur ve depoya girmez (şifre içerir); yoksa örnek ayar kullanılır ve giriş kapalı kalır.
$AYAR = require __DIR__ . (is_file(__DIR__ . '/config.php') ? '/config.php' : '/config.ornek.php');
$VERI = require __DIR__ . '/veri.php';

function https_mi() {
  if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') return true;
  if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') return true;
  return isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443;
}

function e($s) {
  return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

if (session_status() !== PHP_SESSION_ACTIVE) {
  $sure = max(1, (int)(isset($AYAR['oturum_saati']) ? $AYAR['oturum_saati'] : 4)) * 3600;
  ini_set('session.gc_maxlifetime', (string)$sure);
  session_name('anlatir_oturum');
  if (PHP_VERSION_ID >= 70300) {
    session_set_cookie_params(array('lifetime' => 0, 'path' => '/', 'secure' => https_mi(), 'httponly' => true, 'samesite' => 'Lax'));
  } else {
    session_set_cookie_params(0, '/', '', https_mi(), true);
  }
  session_start();
  if (!empty($_SESSION['atolye']) && (time() - (isset($_SESSION['giris_zamani']) ? $_SESSION['giris_zamani'] : 0)) > $sure) {
    $_SESSION = array();
    session_regenerate_id(true);
  }
  if (empty($_SESSION['anahtar'])) {
    $_SESSION['anahtar'] = bin2hex(function_exists('random_bytes') ? random_bytes(16) : openssl_random_pseudo_bytes(16));
  }
}
