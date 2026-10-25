<?php
/* Sayfa iskeleti: üst menü ve alt bilgi */

function page_head($title, $active = '', $bodyClass = '')
{
    $u = ogrenci();
    $kur = kur_aktif();
    $canli = $kur ? canli($kur['id']) : null;
    $nav = ($u || is_admin()) ? array_filter([
        'masa'     => ai_acik() ? ['masa.php', 'Haber Masası'] : null,
        'haberler' => ['haberler.php', 'Haberler'],
        'kurlar'   => ['arsiv.php', 'Kurlar'],
        'atolyeler' => ['atolyeler.php', 'Atölyeler'],
    ]) : [
        'haberler' => ['haberler.php', 'Haberler'],
        'kurlar'   => ['arsiv.php', 'Kurlar'],
        'atolyeler' => ['atolyeler.php', 'Atölyeler'],
    ];
    ?><!doctype html>
<html lang="tr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?> · anlatır</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500&family=IBM+Plex+Sans:wght@400;500;600&family=Source+Serif+4:opsz,wght@8..60,400;8..60,600;8..60,700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/style.css?v=11">
<?php if (strpos($bodyClass, 'home') !== false): ?><link rel="stylesheet" href="assets/home.css?v=1"><?php endif; ?>
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 32 32%22%3E%3Crect width=%2232%22 height=%2232%22 rx=%226%22 fill=%22%231C46C7%22/%3E%3Ctext x=%2216%22 y=%2223%22 font-family=%22Georgia%22 font-size=%2220%22 font-weight=%22700%22 fill=%22white%22 text-anchor=%22middle%22%3Ea%3C/text%3E%3C/svg%3E">
</head>
<body class="<?= e($bodyClass) ?>">
<a class="skip" href="#icerik">İçeriğe geç</a>
<header class="site-head<?= strpos($bodyClass, 'home') !== false ? ' over' : '' ?>">
  <div class="wrap head-row">
    <a class="logo" href="index.php">anlatır</a>
    <nav class="main-nav" aria-label="Ana menü">
      <?php foreach ($nav as $key => [$href, $label]): ?>
        <a href="<?= $href ?>"<?= $active === $key ? ' aria-current="page"' : '' ?>><?= $label ?></a>
      <?php endforeach; ?>
    </nav>
    <div class="head-user">
      <?php if (is_admin()): ?>
        <?php if (basename($_SERVER['SCRIPT_NAME']) !== 'egitmen.php'): ?><a class="btn btn-sm" href="egitmen.php">Eğitmen paneli</a><?php endif; ?>
        <a class="link-muted" href="cikis.php">Çıkış</a>
      <?php elseif ($u): ?>
        <?php $yeniNot = yeni_not_sayisi($u); ?>
        <a class="btn btn-sm" href="odevim.php"><?= e($u['rumuz']) ?> · Ödevim<?php if ($yeniNot): ?> <span class="badge" aria-label="<?= $yeniNot ?> yeni not"><?= $yeniNot ?></span><?php endif; ?></a>
        <a class="link-muted" href="cikis.php">Çıkış</a>
      <?php else: ?>
        <?php if (kayit_acik()): ?><a class="link-muted" href="kayit.php">Kayıt ol</a><?php endif; ?>
        <a class="btn btn-sm" href="giris.php">Giriş</a>
      <?php endif; ?>
    </div>
  </div>
</header>
<?php if ($duyuru = duyuru()): ?>
<div class="duyuru-bant" role="status"><div class="wrap"><?= e($duyuru) ?></div></div>
<?php endif; ?>
<?php if ($canli && $canli['aktif'] && $active !== 'ana' && basename($_SERVER['SCRIPT_NAME']) !== 'canli.php'): $w = week($canli['hafta']); ?>
<a class="live-strip" href="<?= katilim() ? 'canli.php' : 'katil.php' ?>">
  <span class="wrap live-row">
    <span><span class="dot" aria-hidden="true"></span><strong>Ders şu an canlı</strong> <span class="muted-inv">Hafta <?= (int)$canli['hafta'] ?> · <?= e($w['baslik'] ?? '') ?></span></span>
    <span>Sunumu telefonundan takip et →</span>
  </span>
</a>
<?php endif; ?>
<main id="icerik">
<?php
    $f = flash();
    if ($f): ?>
<div class="wrap"><p class="flash <?= e($f['kind']) ?>" role="status"><?= e($f['msg']) ?></p></div>
<?php endif;
}

function page_foot($scripts = [])
{
    ?>
</main>
<footer class="site-foot">
  <div class="wrap foot-row">
    <span><?= e(genel_ayar('alt_bilgi', 'anlatır')) ?></span>
    <span><a href="kullanim-izni.php">Kullanım izni metni</a> · <a href="kvkk.php">KVKK aydınlatma metni</a></span>
  </div>
</footer>
<?php foreach ($scripts as $s): ?>
<script src="<?= e($s) ?>"></script>
<?php endforeach; ?>
</body>
</html>
<?php
}

/* Haber kartı (ziyaretçiye yönelik: yer, tarih, yazar, kaynak; seminer haftası gösterilmez) */
function haber_meta($h)
{
    $p = [];
    if (($h['yer'] ?? '') !== '') {
        $p[] = $h['yer'];
    }
    if (!empty($h['yayin_tarihi'])) {
        $p[] = tr_date($h['yayin_tarihi']);
    }
    return implode(' · ', $p);
}
function haber_karti($h, $cls = 'card-news')
{
    $foto = foto_url($h);
    $ornek = ($h['tur'] ?? '') === 'ornek';
    $href = 'haber.php?id=' . rawurlencode($h['id']);
    $ust = $ornek ? 'Örnek · kurgusal' : haber_meta($h);
    ?>
<a class="<?= e($cls) ?>" href="<?= e($href) ?>">
  <span class="ph"><img src="<?= e($foto ?: site_gorsel('yer-tutucu-' . (hexdec(substr(md5($h['id']), 0, 2)) % 3 + 1))) ?>" alt="" loading="lazy"></span>
  <span class="cn-body">
    <?php if ($ornek): ?><span class="tag <?= ($h['etiket'] ?? '') === 'Hatalı örnek' ? 'tag-bad' : 'tag-blue' ?>"><?= e($h['etiket'] ?? 'Örnek haber') ?></span>
    <?php elseif (!empty($h['kazanan'])): ?><span class="tag tag-gold">★ Haftanın haberi</span><?php endif; ?>
    <?php if ($ust !== ''): ?><span class="meta" style="font-size:14px"><?= e($ust) ?></span><?php endif; ?>
    <strong><?= e($h['baslik']) ?></strong>
    <span class="meta"><?= e($h['yazar']) ?> · <?= kaynak_sayisi($h) ?> kaynak</span>
  </span>
</a>
<?php
}
