<?php
/* Salondaki ekrana yansıtılan pencere */
require __DIR__ . '/inc/bootstrap.php';
require_admin();
if (!kur_aktif()) {
    redirect('egitmen.php');
}
?><!doctype html>
<html lang="tr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Sunum · anlatır</title>
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500;600&family=IBM+Plex+Sans:wght@400;500;600&family=Source+Serif+4:opsz,wght@8..60,600;8..60,700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/style.css?v=3">
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 32 32%22%3E%3Crect width=%2232%22 height=%2232%22 rx=%226%22 fill=%22%231C46C7%22/%3E%3Ctext x=%2216%22 y=%2223%22 font-family=%22Georgia%22 font-size=%2220%22 font-weight=%22700%22 fill=%22white%22 text-anchor=%22middle%22%3Ew%3C/text%3E%3C/svg%3E">
</head>
<body class="proj">
<div id="canli" data-mode="projeksiyon" data-csrf="<?= e(csrf_token()) ?>">
  <div class="proj-stage" id="stage">
    <div class="proj-welcome" id="karsilama">
      <span class="logo" style="font-size:5vh">anlatır</span>
      <h1 id="k-h1">Derse katıl</h1>
      <div id="qr-buyuk"></div>
      <span class="mono" style="font-size:3vh;color:var(--muted)" id="url-buyuk"></span>
      <span class="c" id="kod-buyuk"></span>
    </div>
    <canvas id="proj-canvas" class="hidden"></canvas>
  </div>
  <div class="proj-qr hidden" id="qr-kose">
    <div id="qr-kucuk"></div>
    <div class="stack" style="gap:4px"><span class="t">Derse katıl</span><span class="u" id="url-kucuk"></span><span class="c" id="kod-kucuk"></span></div>
  </div>
  <div class="proj-overlay hidden" id="ov-soru"></div>
  <div class="proj-overlay hidden" id="ov-oylama"></div>
  <div class="proj-overlay hidden" id="ov-bitti"></div>
  <span class="proj-page" id="t-sayfa"></span>
</div>
<script src="assets/lib/pdf.min.js"></script>
<script src="assets/lib/qrcode.js"></script>
<script src="assets/canli.js?v=5"></script>
<script>
document.addEventListener("keydown", function (e) {
  if (e.key === "f" || e.key === "F") { if (!document.fullscreenElement) document.documentElement.requestFullscreen().catch(function () {}); else document.exitFullscreen(); }
});
</script>
</body>
</html>
