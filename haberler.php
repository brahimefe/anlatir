<?php
require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/layout.php';

if (isset($_GET['kur']) && ($k = kur_bul((string)$_GET['kur']))) {
    redirect('arsiv.php?kur=' . $k['id']);
}
$f = in_array($_GET['f'] ?? '', ['secilen', 'ornek'], true) ? $_GET['f'] : 'tum';
$konu = isset(konular()[$_GET['konu'] ?? '']) ? $_GET['konu'] : '';
$yer = trim((string)($_GET['yer'] ?? ''));
$yazar = safe_id($_GET['yazar'] ?? '');
$sayfa = max(1, (int)($_GET['sayfa'] ?? 1));
$adim = 12;

$suzgecVar = $f !== 'tum' || $konu !== '' || $yer !== '' || $yazar;
$yayin = yayindakiler();
$secilen = array_values(array_filter($yayin, function ($h) {
    return !empty($h['kazanan']);
}));
$yerler = [];
foreach ($yayin as $h) {
    if (($h['yer'] ?? '') !== '') {
        $yerler[tr_lower($h['yer'])] = $h['yer'];
    }
}
asort($yerler);

if ($f === 'ornek') {
    $liste = ornekler();
} else {
    $liste = array_values(array_filter($f === 'secilen' ? $secilen : $yayin, function ($h) use ($konu, $yer, $yazar) {
        return ($konu === '' || ($h['konu'] ?? '') === $konu)
            && ($yer === '' || tr_lower($h['yer'] ?? '') === tr_lower($yer))
            && (!$yazar || ($h['yazar_id'] ?? '') === $yazar);
    }));
}
/* Manşette gösterilenler aşağıdaki listede tekrar edilmez */
if (!$suzgecVar && $f === 'tum' && $secilen) {
    $mansetteki = array_map(function ($h) {
        return $h['id'];
    }, array_slice($secilen, 0, 4));
    $liste = array_values(array_filter($liste, function ($h) use ($mansetteki) {
        return !in_array($h['id'], $mansetteki, true);
    }));
}
$toplam = count($liste);
$goster = array_slice($liste, 0, $sayfa * $adim);
$baslik = $yazar && $liste ? $liste[0]['yazar'] . ' imzalı haberler' : 'Haberler';

function url_ile(array $p)
{
    $q = array_filter(array_merge(['f' => $_GET['f'] ?? '', 'konu' => $_GET['konu'] ?? '', 'yer' => $_GET['yer'] ?? '', 'yazar' => $_GET['yazar'] ?? ''], $p), function ($v) {
        return $v !== '' && $v !== null && $v !== 'tum';
    });
    return 'haberler.php' . ($q ? '?' . http_build_query($q) : '');
}

page_head($baslik, 'haberler');
?>
<div class="wrap section stack" style="gap:28px">
  <h1><?= e($baslik) ?></h1>

  <?php if (!$suzgecVar && $secilen): $m = $secilen[0]; $yan = array_slice($secilen, 1, 3); ?>
  <section class="manset" aria-label="Öne çıkan haberler">
    <a class="manset-ana" href="haber.php?id=<?= e($m['id']) ?>">
      <span class="ph"><img src="<?= e(foto_url($m) ?: site_gorsel('yer-tutucu-1')) ?>" alt=""></span>
      <span class="manset-b">
        <span class="tag tag-gold">★ Haftanın haberi</span>
        <strong><?= e($m['baslik']) ?></strong>
        <?php if (($m['spot'] ?? '') !== ''): ?><span class="muted"><?= e($m['spot']) ?></span><?php endif; ?>
        <span class="meta"><?= e($m['yazar']) ?><?= haber_meta($m) !== '' ? ' · ' . e(haber_meta($m)) : '' ?> · <?= kaynak_sayisi($m) ?> kaynak</span>
      </span>
    </a>
    <?php if ($yan): ?><div class="manset-yan"><?php foreach ($yan as $h) { haber_karti($h); } ?></div><?php endif; ?>
  </section>
  <?php endif; ?>

  <form method="get" class="suzgec" aria-label="Süzgeç">
    <div class="row" style="gap:8px">
      <a class="chip-btn" href="<?= e(url_ile(['f' => ''])) ?>" aria-pressed="<?= $f === 'tum' ? 'true' : 'false' ?>">Tümü</a>
      <a class="chip-btn" href="<?= e(url_ile(['f' => 'secilen'])) ?>" aria-pressed="<?= $f === 'secilen' ? 'true' : 'false' ?>">Seçilen haberler</a>
      <a class="chip-btn" href="haberler.php?f=ornek" aria-pressed="<?= $f === 'ornek' ? 'true' : 'false' ?>">Örnek haberler</a>
    </div>
    <?php if ($f !== 'ornek'): ?>
    <div class="row" style="gap:8px">
      <?php if ($f === 'secilen'): ?><input type="hidden" name="f" value="secilen"><?php endif; ?>
      <?php if ($yazar): ?><input type="hidden" name="yazar" value="<?= e($yazar) ?>"><?php endif; ?>
      <label class="hidden" for="s-konu">Konu</label>
      <select id="s-konu" name="konu" onchange="this.form.submit()"><option value="">Tüm konular</option><?php foreach (konular() as $kk => $ka): ?><option value="<?= $kk ?>"<?= $konu === $kk ? ' selected' : '' ?>><?= e($ka) ?></option><?php endforeach; ?></select>
      <?php if ($yerler): ?>
      <label class="hidden" for="s-yer">Yer</label>
      <select id="s-yer" name="yer" onchange="this.form.submit()"><option value="">Tüm yerler</option><?php foreach ($yerler as $yv): ?><option<?= tr_lower($yer) === tr_lower($yv) ? ' selected' : '' ?>><?= e($yv) ?></option><?php endforeach; ?></select>
      <?php endif; ?>
      <noscript><button class="btn btn-sm">Süz</button></noscript>
    </div>
    <?php endif; ?>
  </form>

  <?php if ($goster): ?>
    <div class="grid"><?php foreach ($goster as $h) { haber_karti($h); } ?></div>
    <?php if ($toplam > count($goster)): ?>
      <a class="btn" href="<?= e(url_ile(['sayfa' => $sayfa + 1])) ?>" style="align-self:center">Daha fazla göster</a>
    <?php endif; ?>
  <?php else: ?>
    <p class="muted">Henüz haber yok.</p>
  <?php endif; ?>
</div>
<?php page_foot();
