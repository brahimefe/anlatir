<?php
require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/layout.php';
require __DIR__ . '/inc/canta.php';
require_ogrenci(); // ders içerikleri yalnızca giriş yapan öğrenciye ve eğitmene açık

$n = max(1, min(8, (int)($_GET['n'] ?? 1)));
$w = week($n);
$acik = (kur_aktif() && !is_admin()) ? kur_hafta() : 8;
if ($n > $acik) {
    flash('Bu hafta henüz açılmadı.', 'warn');
    redirect('dersler.php');
}
$araclar = [
    'karsilastir' => ['masa.php?arac=karsilastir', 'Haber yaz ve kaynakla karşılaştır'],
    'denetle'     => ['masa.php?arac=denetle', 'Haberi denetle'],
    'ses'         => ['masa.php?arac=ses', 'Ses kaydını yazıya dök'],
];
$kurA = kur_aktif();
$ozet = null;
if (is_admin() && $kurA) {
    // eğitmen için bu haftanın teslim özeti
    $onayli = array_filter(kullanicilar($kurA['id']), function ($x) {
        return ($x['durum'] ?? '') === 'onayli';
    });
    $gonderen = [];
    $bekleyen = 0;
    foreach (haberler(function ($x) use ($kurA, $n) {
        return ($x['kur'] ?? '') === $kurA['id'] && (int)($x['hafta'] ?? 0) === $n && ($x['durum'] ?? '') !== 'taslak';
    }) as $x) {
        $gonderen[$x['yazar_id'] ?? ''] = 1;
        $bekleyen += $x['durum'] === 'bekliyor' ? 1 : 0;
    }
    $ozet = [count($gonderen), count($onayli), $bekleyen];
}
page_head('Hafta ' . $n . ' · ' . $w['baslik'], 'dersler');
?>
<div class="wrap section stack">
  <p class="crumbs"><a href="dersler.php">Dersler</a> / Hafta <?= $n ?></p>
  <div class="row between">
    <div class="stack" style="gap:6px">
      <h1><?= e($w['baslik']) ?></h1>
      <p class="muted">Hafta <?= $n ?> / 8 · <?= e($w['kisa']) ?></p>
    </div>
    <div class="row">
      <?php if ($n > 1): ?><a class="btn btn-sm" href="hafta.php?n=<?= $n - 1 ?>">‹ Önceki</a><?php endif; ?>
      <?php if ($n < $acik): ?><a class="btn btn-sm" href="hafta.php?n=<?= $n + 1 ?>">Sonraki ›</a><?php endif; ?>
    </div>
  </div>

  <div class="grid-2">
    <div class="stack">
      <section class="card stack">
        <h2>Dersin özeti</h2>
        <ul class="bullets"><?php foreach ($w['ozet'] as $m): ?><li><?= e($m) ?></li><?php endforeach; ?></ul>
      </section>
      <?php if ($w['foto']): ?>
      <section class="photo-box stack">
        <h2 style="font-size:22px"><?= e($w['foto']['baslik']) ?></h2>
        <ul class="bullets"><?php foreach ($w['foto']['maddeler'] as $m): ?><li><?= e($m) ?></li><?php endforeach; ?></ul>
      </section>
      <?php endif; ?>
      <section class="card stack">
        <h2>Atölye görevi</h2>
        <ol class="steps"><?php foreach ($w['atolye'] as $m): ?><li><?= e($m) ?></li><?php endforeach; ?></ol>
      </section>
    </div>
    <aside class="stack">
      <section class="card stack" style="border-color:var(--accent)">
        <span class="kicker">Bu haftanın ödevi</span>
        <p><?= e($w['odev']) ?></p>
        <p class="help"><?= ($w['odev_turu'] ?? 'haber') === 'metin' ? 'Yazılı ödev · yalnızca eğitmen görür' : 'Haber · onaylanırsa yayımlanır' ?></p>
        <?php if ($st = son_teslim_yazi($kurA, $n)): ?><p class="small"><strong>Son teslim:</strong> <?= e($st) ?> <span class="help">(bir sonraki ders saati)</span></p><?php endif; ?>
        <?php if ($ozet): ?>
          <p class="small"><strong>Gönderen:</strong> <?= $ozet[0] ?> / <?= $ozet[1] ?><?= $ozet[2] ? ' · <strong>' . $ozet[2] . '</strong> incelenmeyi bekliyor' : '' ?></p>
          <a class="btn btn-primary" href="egitmen.php?sekme=gonderiler&amp;takip=<?= $n ?>#takip">Ödev takibini aç</a>
        <?php elseif (!is_admin()): ?>
          <a class="btn btn-primary" href="gonder.php?hafta=<?= $n ?>">Ödevi gönder</a>
        <?php endif; ?>
      </section>
      <?php if (ai_acik() && $w['arac'] && isset($araclar[$w['arac']])): ?>
      <section class="card stack">
        <span class="kicker">Haber Masası</span>
        <strong style="font-family:var(--serif);font-size:21px"><?= e($araclar[$w['arac']][1]) ?></strong>
        <a class="btn" href="<?= $araclar[$w['arac']][0] ?>">Aracı aç →</a>
      </section>
      <?php endif; ?>
      <?php $ornk = array_filter(array_map('haber_yukle', $w['ornekler'] ?? [])); if ($ornk): ?>
      <section class="card stack">
        <span class="kicker">Bu haftanın örnek haberi</span>
        <ul class="list-plain stack" style="gap:10px">
          <?php foreach ($ornk as $o): ?><li><a href="haber.php?id=<?= e($o['id']) ?>" style="font-family:var(--serif);font-size:19px;font-weight:600;text-decoration:none"><?= e($o['baslik']) ?></a><br><span class="tag <?= $o['etiket'] === 'Hatalı örnek' ? 'tag-bad' : ($o['etiket'] === 'İyi örnek' ? 'tag-ok' : 'tag-blue') ?>"><?= e($o['etiket']) ?></span> <span class="help"><?= e($o['neden']) ?></span></li><?php endforeach; ?>
        </ul>
      </section>
      <?php endif; ?>
      <?php if (!is_admin() && $kurA && canli($kurA['id'])['aktif']): ?><a class="btn btn-primary" href="canli.php">Canlı derse katıl</a><?php endif; ?>
    </aside>
  </div>
  <?php if (!empty($w['canta'])) { canta_kart($w['canta']); } ?>
</div>
<?php page_foot();
