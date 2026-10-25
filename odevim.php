<?php
require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/layout.php';

if (is_admin() && !ogrenci()) {
    redirect('egitmen.php');
}
require_ogrenci();
$u = ogrenci();
$liste = haberler(function ($h) use ($u) {
    return ($h['yazar_id'] ?? '') === $u['id'];
});
usort($liste, function ($a, $b) {
    return strcmp($b['guncelleme'] ?? $b['tarih'], $a['guncelleme'] ?? $a['tarih']);
});
$etiket = [
    'taslak'   => ['Taslak · gönderilmedi', ''],
    'bekliyor' => ['Eğitmende', 'tag-blue'],
    'duzeltme' => ['Düzeltme istendi', 'tag-gold'],
    'yayinda'  => ['Yayında', 'tag-ok'],
    'red'      => ['Yayımlanmadı', 'tag-bad'],
    'goruldu'  => ['Eğitmen gördü', 'tag-ok'],
];
$durumAd = ['kaynakli' => ['Kaynaklı', 'ok'], 'kaynak_yok' => ['Kaynak yok', 'bad'], 'yorum' => ['Yorum', 'warn']];

$yeniler = [];
foreach ($liste as $h) {
    if (!empty($h['bildirim'])) {
        $yeniler[$h['id']] = true;
        $h['bildirim'] = false;
        haber_kaydet($h);
    }
}
page_head('Ödevim');
?>
<div class="wrap narrow section stack">
  <div class="row between">
    <div>
      <h1>Ödevim</h1>
      <p class="small"><a href="profil.php">Profilim</a></p>
    </div>
    <a class="btn btn-primary" href="gonder.php">Yeni gönderi</a>
  </div>

  <?php if (!$liste): ?>
    <div class="card"><p>Henüz bir gönderin yok. Bu haftanın ödevi: <?= e(week(kur_hafta())['odev'] ?? '') ?></p></div>
  <?php endif; ?>

  <?php foreach ($liste as $h): [$ad, $cls] = $etiket[$h['durum']] ?? ['', '']; ?>
    <article class="card stack" id="g-<?= e($h['id']) ?>">
      <div class="row between">
        <span class="kicker">Hafta <?= (int)$h['hafta'] ?> · <?= $h['tur'] === 'odev' ? 'Ödev' : 'Haber' ?> · <?= e(tr_date($h['guncelleme'] ?? $h['tarih'], true)) ?></span>
        <span class="row" style="gap:6px">
          <?php if (isset($yeniler[$h['id']])): ?><span class="tag tag-blue">Yeni</span><?php endif; ?>
          <?php if (!empty($h['kazanan'])): ?><span class="tag tag-gold">Haftanın haberi</span><?php elseif (!empty($h['finalist'])): ?><span class="tag tag-gold">Finalist</span><?php endif; ?>
          <span class="tag <?= $cls ?>"><?= e($ad) ?></span>
        </span>
      </div>
      <h2 style="font-size:24px"><?= e($h['baslik'] ?: mb_strimwidth($h['metin'], 0, 70, '…', 'UTF-8')) ?></h2>
      <?php if (!empty($h['egitmen_girdi'])): ?><p class="help" style="margin:0">Bu gönderiyi eğitmeniniz sizin adınıza sisteme girdi.</p><?php endif; ?>
      <?php if (!empty($h['not'])): ?>
        <div class="flash warn" style="margin:0"><strong>Eğitmen notu:</strong> <?= nl2br(e($h['not'])) ?></div>
      <?php endif; ?>
      <?php if (!empty($h['dipnot']['cumleler']) && $h['durum'] !== 'yayinda'): ?>
        <details>
          <summary style="cursor:pointer;font-weight:600">Dipnot kontrolü · <?= kaynaksiz_sayisi($h) ?> bilgi kaynaksız</summary>
          <?php if (!empty($h['dipnot']['ozet'])): ?><p class="verdict" style="margin-top:10px"><?= e($h['dipnot']['ozet']) ?></p><?php endif; ?>
          <ul class="dipnot-list">
            <?php foreach ($h['dipnot']['cumleler'] as $c): [$dl, $dc] = $durumAd[$c['durum']] ?? ['', 'none']; ?>
              <li><span class="chip <?= $dc ?>" style="align-self:flex-start"><?= $dl ?><?= $c['kaynak_no'] ? ' · ' . (int)$c['kaynak_no'] : '' ?></span>
                <span class="stmt"><?= e($c['metin']) ?></span>
                <?php if ($c['soru'] !== ''): ?><span class="why"><strong>Soru:</strong> <?= e($c['soru']) ?></span><?php endif; ?></li>
            <?php endforeach; ?>
          </ul>
        </details>
      <?php endif; ?>
      <div class="row">
        <?php if ($h['durum'] === 'taslak'): ?><a class="btn btn-sm btn-primary" href="gonder.php?id=<?= e($h['id']) ?>">Devam et</a>
        <?php elseif (in_array($h['durum'], ['bekliyor', 'duzeltme'], true)): ?><a class="btn btn-sm" href="gonder.php?id=<?= e($h['id']) ?>">Düzelt</a><?php endif; ?>
        <?php if ($h['durum'] === 'yayinda'): ?><a class="btn btn-sm" href="haber.php?id=<?= e($h['id']) ?>">Haberi gör</a><?php endif; ?>
      </div>
    </article>
  <?php endforeach; ?>
</div>
<?php page_foot();
