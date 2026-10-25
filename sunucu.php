<?php
/* Eğitmenin bilgisayarındaki sunucu ekranı */
require __DIR__ . '/inc/bootstrap.php';
require_admin();
$kur = kur_aktif();
if (!$kur) {
    flash('Önce bir kur açın.', 'warn');
    redirect('egitmen.php');
}
$c = canli($kur['id']);
$varsayilan = $c['aktif'] ? (int)$c['hafta'] : max(1, (int)($kur['hafta'] ?? 1));
$haftalar = [];
foreach (weeks() as $n => $w) {
    $haftalar[$n] = ['baslik' => $w['baslik'], 'sorular' => $w['sorular'], 'atolye' => $w['atolye'], 'sunum' => is_file(sunum_path($n))];
}
?><!doctype html>
<html lang="tr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Sunucu ekranı · anlatır</title>
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500&family=IBM+Plex+Sans:wght@400;500;600&family=Source+Serif+4:opsz,wght@8..60,600;8..60,700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/style.css?v=3">
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 32 32%22%3E%3Crect width=%2232%22 height=%2232%22 rx=%226%22 fill=%22%231C46C7%22/%3E%3Ctext x=%2216%22 y=%2223%22 font-family=%22Georgia%22 font-size=%2220%22 font-weight=%22700%22 fill=%22white%22 text-anchor=%22middle%22%3Ew%3C/text%3E%3C/svg%3E">
</head>
<body class="dark">
<div id="canli" data-mode="sunucu" data-csrf="<?= e(csrf_token()) ?>" data-haftalar="<?= e(json_encode($haftalar, JSON_UNESCAPED_UNICODE)) ?>">
  <div class="p-bar">
    <div class="row" style="gap:16px">
      <a href="egitmen.php" style="color:#AEB4C0;text-decoration:none">← Panel</a>
      <strong id="t-hafta">Ders başlamadı</strong>
      <span class="timer" id="t-sure"></span>
      <span id="t-bagli" class="muted-inv"></span>
      <span class="mono" style="color:#fff">Ders kodu: <strong id="t-kod"><?= e(ders_kodu($kur['id'])) ?></strong></span>
    </div>
    <div class="row" style="gap:10px">
      <a class="btn btn-sm" href="projeksiyon.php" target="anlatir-projeksiyon">Sunumu aç</a>
      <button class="btn btn-sm" type="button" id="qr-btn">QR'ı gizle</button>
      <button class="btn btn-sm btn-primary hidden" type="button" id="bitir-btn">Dersi bitir</button>
    </div>
  </div>
  <p class="flash err hidden" id="baglanti" style="margin:12px 20px">Bağlantı koptu, yeniden deneniyor…</p>

  <section id="v-baslat" class="hidden" style="max-width:640px;margin:40px auto;padding:0 20px">
    <div class="p-panel">
      <h2>Dersi başlat</h2>
      <label for="hafta-sec" style="color:#E8EAEE">Hafta</label>
      <select id="hafta-sec">
        <?php foreach ($haftalar as $n => $w): ?>
          <option value="<?= $n ?>"<?= $n === $varsayilan ? ' selected' : '' ?>>Hafta <?= $n ?> · <?= e($w['baslik']) ?><?= $w['sunum'] ? '' : ' (sunum yüklenmedi)' ?></option>
        <?php endforeach; ?>
      </select>
      <button class="btn btn-primary" type="button" id="baslat-btn">Dersi başlat</button>
      <p class="small" style="color:#AEB4C0">Başlattığınızda katılımcıların telefonlarında sunum açılır. “Sunumu aç” ile açılan pencereyi salondaki ekrana taşıyıp F11 ile tam ekran yapın. Hafta 2 ya da sonrasını başlattığınızda kayıt formu kendiliğinden kapanır.</p>
    </div>
  </section>

  <div class="presenter hidden" id="v-ders">
    <div class="stack">
      <div class="p-slide" id="p-slide"><p class="muted-inv" style="padding:40px">Bu hafta için sunum yüklenmemiş. Sunumu Eğitmen panelinden yükleyebilirsiniz; soru ve oylama sunumsuz da çalışır.</p></div>
      <div class="p-nav">
        <button class="btn" type="button" id="geri" aria-label="Önceki slayt">‹</button>
        <span id="t-sayfa">–</span>
        <button class="btn" type="button" id="ileri" aria-label="Sonraki slayt">›</button>
      </div>
      <p class="small" style="text-align:center;color:#8A909C">Klavyede ← → tuşlarıyla da ilerleyebilirsiniz.</p>
    </div>
    <div class="stack">
      <section class="p-panel">
        <h2>Sonraki slayt</h2>
        <div class="p-next" id="p-next"><p class="small" style="color:#8A909C">—</p></div>
      </section>

      <section class="p-panel" id="soru-panel">
        <h2>Anlık soru</h2>
        <div id="soru-aktif" class="stack hidden">
          <strong id="soru-metin"></strong>
          <div id="soru-sonuc" class="stack"></div>
          <span class="small" id="soru-sayi" style="color:#AEB4C0"></span>
          <div class="row">
            <button class="btn btn-sm btn-primary" type="button" id="sonuc-btn">Sonucu göster</button>
            <button class="btn btn-sm" type="button" id="kapat-btn">Soruyu kapat</button>
          </div>
        </div>
        <div id="soru-yeni" class="stack">
          <div class="stack" id="hazir-sorular" style="gap:8px"></div>
          <details>
            <summary style="cursor:pointer;color:#C9CED8">Kendi sorunu yaz</summary>
            <form id="soru-form" class="stack" style="margin-top:10px;gap:8px">
              <input type="text" name="metin" placeholder="Soru" required>
              <input type="text" name="secenekler[]" placeholder="Seçenek 1" required>
              <input type="text" name="secenekler[]" placeholder="Seçenek 2" required>
              <input type="text" name="secenekler[]" placeholder="Seçenek 3 (isteğe bağlı)">
              <input type="text" name="secenekler[]" placeholder="Seçenek 4 (isteğe bağlı)">
              <button class="btn btn-sm btn-primary" type="submit">Soruyu telefonlara gönder</button>
            </form>
          </details>
        </div>
      </section>

      <section class="p-panel">
        <h2>Haftanın haberi</h2>
        <div id="oy-alan" class="stack"></div>
        <div class="row">
          <button class="btn btn-sm btn-primary" type="button" id="oy-baslat">Oylamayı başlat</button>
          <button class="btn btn-sm hidden" type="button" id="oy-bitir">Oylamayı bitir</button>
          <button class="btn btn-sm hidden" type="button" id="oy-kapat">Ekrandan kaldır</button>
        </div>
        <p class="small" style="color:#8A909C">Finalistleri Eğitmen panelinden seçin (en az 2, en fazla 3).</p>
      </section>

      <section class="p-panel">
        <h2>Bağlı katılımcılar</h2>
        <div id="bagli-liste" class="small" style="display:flex;flex-wrap:wrap;gap:6px"></div>
        <p class="small" style="color:#8A909C">Uygunsuz bir rumuzu değiştirmek için üzerine tıklayın.</p>
      </section>

      <section class="p-panel">
        <h2>Ders notları</h2>
        <ol class="steps small" id="notlar"></ol>
      </section>
    </div>
  </div>
</div>
<script src="assets/lib/pdf.min.js"></script>
<script src="assets/canli.js?v=5"></script>
</body>
</html>
