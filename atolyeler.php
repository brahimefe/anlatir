<?php
/* Atölyeler: uygulamalı atölyelerin listesi. Her atölye kendi klasöründe, kendi şifresiyle çalışır. */
require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/layout.php';

$atolyeler = [
    [
        'ad'    => 'Kahramanın Tercihi',
        'metin' => 'Monomit üzerinden üniversite tercihini konuşan, alkışla seçilen film ve salonla yazılan bir kahraman hikâyesi.',
        'kunye' => 'TercihFest · İbn Haldun Üniversitesi',
        'adres' => 'atolye/',
    ],
];

page_head('Atölyeler', 'atolyeler');
?>
<div class="wrap section stack" style="gap:28px">
  <div class="stack" style="gap:6px">
    <h1>Atölyeler</h1>
    <p class="muted" style="margin:0;font-size:19px">Hikâye anlatımını deneyerek öğrenmek için kısa, uygulamalı oturumlar.</p>
  </div>

  <div class="grid">
    <?php foreach ($atolyeler as $a): ?>
    <article class="card stack" style="gap:14px">
      <h2 style="margin:0"><?= e($a['ad']) ?></h2>
      <p style="margin:0;line-height:1.55"><?= e($a['metin']) ?></p>
      <p class="muted" style="margin:0;font-size:15px"><?= e($a['kunye']) ?></p>
      <a class="btn btn-primary" href="<?= e($a['adres']) ?>" style="align-self:flex-start;margin-top:auto">Atölyeyi aç →</a>
    </article>
    <?php endforeach; ?>
    <article class="card stack" style="gap:10px;border-style:dashed;background:transparent">
      <p class="muted" style="margin:0;font-size:14px;letter-spacing:.08em">YAKINDA</p>
      <p class="muted" style="margin:0">Sıradaki atölye</p>
    </article>
  </div>
</div>
<?php page_foot();
