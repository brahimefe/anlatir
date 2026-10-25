<?php
/* Kur raporu: yazdırılabilir tek sayfa özet (yalnızca eğitmen) */
require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/layout.php';
require_admin();

$kur = kur_bul((string)($_GET['kur'] ?? '')) ?: kur_aktif();
if (!$kur) {
    redirect('egitmen.php');
}
$kid = $kur['id'];
$kapali = ($kur['durum'] ?? '') === 'arsiv';
$users = array_values(array_filter(kullanicilar($kid), function ($u) {
    return in_array($u['durum'], ['onayli', 'kapali'], true);
}));
$katilimci = $users ? count($users) : (int)($kur['istatistik']['katilimci'] ?? 0);
$gonderiler = haberler(function ($h) use ($kid) {
    return $h['kur'] === $kid && $h['durum'] !== 'taslak';
});
$yayin = yayindakiler($kid);
usort($yayin, function ($a, $b) {
    return strcmp($a['yayin_tarihi'] ?? '', $b['yayin_tarihi'] ?? '');
});
$sonHafta = max(1, (int)($kur['hafta'] ?? 1));

/* Haftalara göre teslim */
$teslim = [];
for ($i = 1; $i <= $sonHafta; $i++) {
    $teslim[$i] = ['kisi' => [], 'gec' => 0];
}
foreach ($gonderiler as $g) {
    $hf = (int)$g['hafta'];
    if (isset($teslim[$hf]) && !isset($teslim[$hf]['kisi'][$g['yazar_id']])) {
        $teslim[$hf]['kisi'][$g['yazar_id']] = true;
        if (gec_mi($kur, $g)) {
            $teslim[$hf]['gec']++;
        }
    }
}
$kazananlar = [];
foreach (glob(kur_dir($kid) . '/oylama-h*.json') ?: [] as $f) {
    $o = json_read($f);
    if (!empty($o['kazanan']) && ($h = haber_yukle($o['kazanan']))) {
        $kazananlar[(int)preg_replace('/\D/', '', basename($f))] = $h;
    }
}
ksort($kazananlar);
$sorular = json_read(soru_gecmisi_path($kid), []);

page_head('Kur raporu · ' . $kur['ad'], '', 'rapor');
?>
<div class="wrap narrow section stack rapor">
  <div class="row between noprint">
    <a class="crumbs" href="<?= $kapali ? 'arsiv.php?kur=' . e($kid) : 'egitmen.php' ?>">← Geri</a>
    <button class="btn btn-sm" type="button" onclick="window.print()">Yazdır / PDF</button>
  </div>
  <header class="stack" style="gap:4px">
    <span class="kicker">Kur raporu · <?= e(tr_date(date('c'))) ?></span>
    <h1><?= e($kur['ad']) ?></h1>
    <p class="muted">Dijital Habercilik Semineri · <?= e(tr_date($kur['acilis'] ?? '')) ?> – <?= $kapali ? e(tr_date($kur['kapanis'] ?? '')) : 'devam ediyor' ?> · <?= $sonHafta ?> hafta işlendi</p>
  </header>

  <div class="stats">
    <div class="stat"><b><?= $katilimci ?></b><span>katılımcı</span></div>
    <div class="stat"><b><?= count($gonderiler) ?></b><span>gönderi</span></div>
    <div class="stat"><b><?= count($yayin) ?></b><span>yayımlanan haber</span></div>
    <div class="stat"><b><?= count($kazananlar) ?></b><span>haftanın haberi</span></div>
  </div>

  <section class="stack">
    <h2>Ödev teslimi</h2>
    <table class="table">
      <thead><tr><th>Hafta</th><th>Konu</th><th>Teslim eden</th><th>Geç</th></tr></thead>
      <tbody>
      <?php foreach ($teslim as $hf => $t): $n = count($t['kisi']); ?>
        <tr><td><?= $hf ?></td><td><?= e(week($hf)['baslik']) ?></td><td><?= $n ?><?= $katilimci ? ' / ' . $katilimci . ' (%' . round(100 * $n / $katilimci) . ')' : '' ?></td><td><?= $t['gec'] ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php if (!$users && $kapali): ?><p class="help">Katılımcı hesapları saklama süresi dolduğu için silindi; teslim sayıları yalnızca yayımlanan haberlerden hesaplandı.</p><?php endif; ?>
  </section>

  <?php if ($kazananlar): ?>
  <section class="stack">
    <h2>Haftanın haberi</h2>
    <table class="table">
      <thead><tr><th>Hafta</th><th>Haber</th><th>Yazar</th></tr></thead>
      <tbody><?php foreach ($kazananlar as $hf => $h): ?><tr><td><?= $hf ?></td><td><?= e($h['baslik']) ?></td><td><?= e($h['yazar']) ?></td></tr><?php endforeach; ?></tbody>
    </table>
  </section>
  <?php endif; ?>

  <section class="stack">
    <h2>Yayımlanan haberler</h2>
    <?php if ($yayin): ?>
    <table class="table">
      <thead><tr><th>Tarih</th><th>Haber</th><th>Yazar</th><th>Konu · yer</th><th>Kaynak</th></tr></thead>
      <tbody><?php foreach ($yayin as $h): ?><tr><td><?= e(tr_date($h['yayin_tarihi'] ?? '')) ?></td><td><?= e($h['baslik']) ?></td><td><?= e($h['yazar']) ?></td><td><?= e(trim(konu_adi($h['konu'] ?? '') . ' · ' . ($h['yer'] ?? ''), ' ·')) ?></td><td><?= kaynak_sayisi($h) ?></td></tr><?php endforeach; ?></tbody>
    </table>
    <?php else: ?><p class="muted">Yayımlanan haber yok.</p><?php endif; ?>
  </section>

  <?php if ($sorular): ?>
  <section class="stack">
    <h2>Anlık soru sonuçları</h2>
    <?php foreach ($sorular as $r): $top = max(1, (int)$r['yanit']); ?>
      <div class="rapor-soru">
        <strong><?= e($r['metin']) ?></strong> <span class="help">· Hafta <?= (int)$r['hafta'] ?> · <?= (int)$r['yanit'] ?> cevap</span>
        <ul class="list-plain"><?php foreach ($r['secenekler'] as $i => $sec): $n = (int)($r['sayilar'][$i] ?? 0); ?><li><?= e($sec) ?> — <?= $n ?> (%<?= round(100 * $n / $top) ?>)</li><?php endforeach; ?></ul>
      </div>
    <?php endforeach; ?>
  </section>
  <?php endif; ?>
</div>
<?php page_foot();
