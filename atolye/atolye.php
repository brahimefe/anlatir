<?php
require __DIR__ . '/inc/baslat.php';

$hata = '';
$sifre_ayarli = !in_array(trim((string)$AYAR['atolye_sifresi']), array('', 'BURAYA-SIFRENIZI-YAZIN'), true);

if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sifre'])) {
  $_SESSION['deneme'] = isset($_SESSION['deneme']) ? (int)$_SESSION['deneme'] : 0;
  $_SESSION['son_deneme'] = isset($_SESSION['son_deneme']) ? (int)$_SESSION['son_deneme'] : 0;
  $gelen_anahtar = isset($_POST['anahtar']) ? (string)$_POST['anahtar'] : '';
  if (!hash_equals($_SESSION['anahtar'], $gelen_anahtar)) {
    $hata = 'Oturum yenilendi. Lütfen tekrar deneyin.';
  } elseif (!$sifre_ayarli) {
    $hata = 'Atölye şifresi henüz ayarlanmadı (inc/config.php).';
  } elseif ($_SESSION['deneme'] >= 5 && time() - $_SESSION['son_deneme'] < 60) {
    $hata = 'Çok fazla hatalı deneme. Bir dakika sonra tekrar deneyin.';
  } elseif (hash_equals((string)$AYAR['atolye_sifresi'], (string)$_POST['sifre'])) {
    session_regenerate_id(true);
    $_SESSION['atolye'] = true;
    $_SESSION['giris_zamani'] = time();
    $_SESSION['deneme'] = 0;
    header('Location: atolye.php');
    exit;
  } else {
    usleep(700000);
    $_SESSION['deneme']++;
    $_SESSION['son_deneme'] = time();
    $hata = 'Şifre yanlış.';
  }
}
$girisli = !empty($_SESSION['atolye']);
?>
<!doctype html>
<html lang="tr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>Kahramanın Tercihi · Atölye</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,400;0,9..144,600;1,9..144,400&family=Instrument+Sans:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/site.css">
<link rel="stylesheet" href="assets/atolye.css?v=5">
</head>
<body>
<?php if (!$girisli): ?>
  <div class="kapi" style="height:100%;overflow:auto">
    <form method="post" action="atolye.php" novalidate>
      <div class="sutun" style="gap:10px">
        <a class="u-logo" href="../atolyeler.php">anlatır<span>.</span></a>
        <h1 class="h2" style="font-size:40px">Kahramanın Tercihi</h1>
        <p style="margin:0;font-size:17px;line-height:1.5;color:var(--ikincil)">Atölye yalnızca yürütücüye açıktır. Devam etmek için şifreyi girin.</p>
      </div>
      <div class="sutun" style="gap:8px">
        <label for="sifre" style="font-size:16px;color:var(--metin2)">Atölye şifresi</label>
        <input id="sifre" name="sifre" type="password" autocomplete="current-password" required autofocus<?= $hata ? ' aria-invalid="true" aria-describedby="sifreHata"' : '' ?>>
        <?php if ($hata): ?><div id="sifreHata" role="alert" style="font-size:15px;color:var(--uyari)"><?= e($hata) ?></div><?php endif; ?>
      </div>
      <input type="hidden" name="anahtar" value="<?= e($_SESSION['anahtar']) ?>">
      <button class="btn ana" type="submit" style="justify-content:center">Atölyeyi aç</button>
      <a href="../atolyeler.php" style="align-self:center;font-size:15px;color:var(--ikincil)">Atölyelere dön</a>
    </form>
  </div>
<?php else: ?>
  <div id="sahne" aria-live="polite"></div>
  <script>
    window.ATOLYE = <?= json_encode(array('veri' => $VERI, 'anahtar' => $_SESSION['anahtar'], 'yz' => false), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
  </script>
  <script src="assets/atolye.js?v=10"></script>
<?php endif; ?>
</body>
</html>
