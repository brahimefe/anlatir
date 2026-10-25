<?php
require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/layout.php';
require_ogrenci(); // ders içerikleri yalnızca giriş yapan öğrenciye ve eğitmene açık

$acik = (kur_aktif() && !is_admin()) ? kur_hafta() : 8;
$simdi = kur_hafta();
page_head('Dersler', 'dersler');
?>
<div class="wrap narrow section stack">
  <h1>Dersler</h1>
  <ol class="week-list">
    <?php foreach (weeks() as $n => $w): ?>
      <li>
        <?php if ($n <= $acik): ?>
          <a href="hafta.php?n=<?= $n ?>" class="<?= $n === $simdi ? 'now' : '' ?>">
            <span class="num">Hafta <?= $n ?></span>
            <span><strong><?= e($w['baslik']) ?></strong><span class="muted small"><?= e($w['kisa']) ?></span></span>
            <span aria-hidden="true">→</span>
          </a>
        <?php else: ?>
          <span class="locked"><span class="num">Hafta <?= $n ?></span><span><strong><?= e($w['baslik']) ?></strong><span class="muted small">Ders işlenince açılır</span></span><span></span></span>
        <?php endif; ?>
      </li>
    <?php endforeach; ?>
  </ol>
</div>
<?php page_foot();
