<?php
require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/layout.php';

$h = haber_yukle($_GET['id'] ?? '');
$u = ogrenci();
$sahibi = $h && $u && ($h['yazar_id'] ?? '') === $u['id'];
if (!$h || ($h['durum'] !== 'yayinda' && !is_admin() && !$sahibi)) {
    http_response_code(404);
    page_head('Haber bulunamadı');
    echo '<div class="wrap section"><h1>Haber bulunamadı</h1><p><a href="haberler.php">Haberlere dön</a></p></div>';
    page_foot();
    exit;
}
$ornek = ($h['tur'] ?? '') === 'ornek';
$foto = foto_url($h);
$kaynaklar = array_values(array_filter((array)$h['kaynaklar'], function ($k) {
    return trim($k['bilgi'] . $k['kaynak']) !== '';
}));
$kur = !$ornek ? kur_bul($h['kur']) : null;

page_head($h['baslik'], 'haberler');
?>
<article class="article">
  <?php if ($h['durum'] !== 'yayinda'): ?><p class="flash warn" style="margin:0">Önizleme: bu gönderi henüz yayımlanmadı.</p><?php endif; ?>
  <div class="row" style="gap:8px">
    <?php if ($ornek): ?>
      <span class="kicker">Örnek haber</span><span class="tag">Örnek · kurgusal</span>
      <span class="tag <?= $h['etiket'] === 'Hatalı örnek' ? 'tag-bad' : 'tag-ok' ?>"><?= e($h['etiket']) ?></span>
    <?php else: ?>
      <?php if (konu_adi($h['konu'] ?? '') !== ''): ?><a class="kicker" href="haberler.php?konu=<?= e($h['konu']) ?>" style="text-decoration:none"><?= e(konu_adi($h['konu'])) ?></a><?php endif; ?>
      <?php if (!empty($h['kazanan'])): ?><span class="tag tag-gold">★ Haftanın haberi</span><?php endif; ?>
    <?php endif; ?>
  </div>
  <h1><?= e($h['baslik']) ?></h1>
  <?php if (($h['spot'] ?? '') !== ''): ?><p class="spot"><?= e($h['spot']) ?></p><?php endif; ?>
  <div class="byline"><?= e($h['yazar']) ?><?= !$ornek && haber_meta($h) !== '' ? ' · ' . e(haber_meta($h)) : '' ?></div>

  <?php if ($ornek && !empty($h['hatalar'])): ?>
    <div class="warnbox">
      <strong>Bu haber hatalı bir örnektir.</strong> Derste neyin yanlış olduğunu bulmak için kullanılır:
      <ul><?php foreach ($h['hatalar'] as $x): ?><li><?= e($x) ?></li><?php endforeach; ?></ul>
    </div>
  <?php endif; ?>

  <?php if ($foto): ?>
    <figure>
      <img src="<?= e($foto) ?>" alt="<?= e($h['alt'] ?? '') ?>">
      <figcaption><?= $ornek ? 'Temsilî fotoğraf. ' : '' ?><?= e($h['altyazi'] ?? '') ?> Fotoğraf: <?= e(($h['foto_kredi'] ?? '') !== '' ? $h['foto_kredi'] : $h['yazar']) ?></figcaption>
    </figure>
  <?php endif; ?>

  <div class="body-text"><?= paragraphs($h['metin']) ?></div>

  <?php if ($kaynaklar || !empty($h['not_okura'])): ?>
  <details class="sources" open>
    <summary><span>Kaynaklar ve doğrulama</span><span class="small muted" style="font-weight:400"><?= count($kaynaklar) ?> bilgi · <?= kaynak_sayisi($h) ?> kaynaklı</span></summary>
    <div class="sources-body">
      <ol class="src-list">
        <?php foreach ($kaynaklar as $i => $k): ?>
          <li><span class="n"><?= $i + 1 ?></span><span><strong><?= e($k['bilgi'] ?: 'Bilgi') ?></strong><br><span class="muted"><?= $k['kaynak'] !== '' ? e($k['kaynak']) : '<span class="err-text">Kaynak belirtilmemiş</span>' ?></span></span></li>
        <?php endforeach; ?>
        <?php if (!empty($h['not_okura'])): ?>
          <li><span class="n"><?= count($kaynaklar) + 1 ?></span><span><strong>Taslaktan çıkarılan bilgi</strong><br><span class="muted"><?= e($h['not_okura']) ?></span></span></li>
        <?php endif; ?>
      </ol>
      <?php if (!$ornek && !empty($h['yayin_tarihi'])): ?>
        <div class="small muted" style="border-top:1px solid var(--line-2);padding-top:12px">Editör onayı · <?= e(tr_date($h['yayin_tarihi'])) ?></div>
      <?php endif; ?>
    </div>
  </details>
  <?php endif; ?>
  <?php if (!$ornek && ($h['yazar_tanitim'] ?? '') !== ''):
      $digerSayi = count(haberler(function ($x) use ($h) {
          return $x['durum'] === 'yayinda' && ($x['yazar_id'] ?? '') === $h['yazar_id'] && $x['id'] !== $h['id'];
      })); ?>
    <aside class="yazar-kutu">
      <span class="kicker">Yazar</span>
      <strong><?= e($h['yazar']) ?></strong>
      <p><?= e($h['yazar_tanitim']) ?></p>
      <?php if ($digerSayi): ?><a href="haberler.php?yazar=<?= e($h['yazar_id']) ?>"><?= e($h['yazar']) ?> imzalı diğer haberler (<?= $digerSayi ?>) →</a><?php endif; ?>
    </aside>
  <?php endif; ?>
  <?php if ($h['durum'] === 'yayinda'): $paylas = base_url() . '/haber.php?id=' . rawurlencode($h['id']); ?>
    <div class="row paylas">
      <a class="btn btn-sm" href="https://wa.me/?text=<?= rawurlencode($h['baslik'] . ' ' . $paylas) ?>" target="_blank" rel="noopener">WhatsApp'ta paylaş</a>
      <button class="btn btn-sm" type="button" data-kopyala="<?= e($paylas) ?>">Bağlantıyı kopyala</button>
    </div>
    <script>
    document.querySelectorAll("[data-kopyala]").forEach(function (b) {
      b.addEventListener("click", function () {
        var t = b.getAttribute("data-kopyala");
        (navigator.clipboard ? navigator.clipboard.writeText(t) : Promise.reject()).then(function () { b.textContent = "Kopyalandı"; }, function () { window.prompt("Bağlantı:", t); });
      });
    });
    </script>
  <?php endif; ?>
  <?php if (!empty($h['yz'])): ?>
    <p class="small muted" style="border-left:3px solid var(--line);padding-left:12px"><strong>Yapay zekâ beyanı:</strong> <?= $h['yz']['kullandi'] ? 'Yazar, bu haberi hazırlarken yapay zekâ aracı kullandığını beyan etti (' . e($h['yz']['aciklama']) . ').' : 'Yazar, bu haberi hazırlarken yapay zekâ aracı kullanmadığını beyan etti.' ?></p>
  <?php endif; ?>
</article>
<?php page_foot();
