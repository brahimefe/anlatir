<?php
require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/layout.php';

if (!ai_acik()) {
    flash(is_admin() ? 'Haber Masası kapalı. Açmak için config.php dosyasında yapay_zeka değerini true yapıp API anahtarını girin.' : 'Haber Masası şu an kullanılmıyor.', 'warn');
    redirect(is_admin() ? 'egitmen.php' : 'index.php');
}
$araclar = [
    'karsilastir' => ['Haber yaz', 'Kaynak metni ve kendi haberinizi yapıştırın. Araç yeni metin yazmaz; her cümlenizi kaynakla eşleştirir ve düzeltmeniz için soru sorar.'],
    'denetle'     => ['Haberi denetle', 'Yayımlanmış bir haberi dayandığı kaynakla karşılaştırın: hangi cümle kaynakta var, hangisi çarpıtılmış?'],
    'ses'         => ['Ses kaydını yazıya dök', 'Telefonla kaydettiğiniz röportajı yükleyin; kayıt dakikalarıyla birlikte yazıya dökülür. Haberi bu dökümden siz yazarsınız ve her alıntının kaynağına kaydın dakikasını eklersiniz.'],
];
$arac = (string)($_GET['arac'] ?? 'karsilastir');
if (!isset($araclar[$arac])) {
    $arac = 'karsilastir';
}

page_head('Haber Masası', 'masa');
$giris = ogrenci() || is_admin();
?>
<div class="wrap section">
  <h1>Haber Masası</h1>
  <nav class="tool-tabs" aria-label="Araçlar">
    <?php foreach ($araclar as $k => $a): ?>
      <a href="masa.php?arac=<?= $k ?>"<?= $k === $arac ? ' aria-current="page"' : '' ?>><?= e($a[0]) ?></a>
    <?php endforeach; ?>
  </nav>
  <p class="muted" style="max-width:46em"><?= e($araclar[$arac][1]) ?></p>

<?php if (!$giris): ?>
  <div class="card stack" style="margin-top:22px;max-width:620px">
    <h2>Araçları kullanmak için giriş yap</h2>
    <p>Haber Masası seminer katılımcılarına açıktır. Kaydın onaylandıysa giriş yap.</p>
    <div class="row"><a class="btn btn-primary" href="giris.php?donus=<?= e(urlencode(here())) ?>">Giriş yap</a><?php if (kayit_acik()): ?><a class="btn" href="kayit.php">Kayıt ol</a><?php endif; ?></div>
  </div>
<?php else:
    $maxMb = (int)cfg('max_audio_mb', 25);
?>
  <div class="masa-grid tool-page" data-csrf="<?= e(csrf_token()) ?>" style="margin-top:22px">
  <?php if ($arac === 'karsilastir'): ?>
    <form class="card stack" data-islem="karsilastir">
      <div class="field"><label for="kaynak">1 · Kaynak metin</label>
        <textarea id="kaynak" name="kaynak" rows="8" placeholder="Basın bülteni, meclis kararı, röportaj dökümü…" required></textarea></div>
      <div class="field"><label for="taslak">2 · Sizin haberiniz</label>
        <textarea id="taslak" name="taslak" rows="8" placeholder="Önce haberinizi kendiniz yazın." required></textarea></div>
      <div class="row"><button class="btn btn-primary" type="submit">Kaynakla karşılaştır</button><span class="status" role="status"></span></div>
    </form>
  <?php elseif ($arac === 'denetle'): ?>
    <form class="card stack" data-islem="denetle">
      <div class="field"><label for="kaynak">1 · Kaynak metin</label>
        <textarea id="kaynak" name="kaynak" rows="8" placeholder="Haberin dayandığı bülten ya da açıklama" required></textarea></div>
      <div class="field"><label for="haber">2 · Yayımlanmış haber</label>
        <textarea id="haber" name="haber" rows="8" placeholder="Denetlemek istediğiniz haber metni" required></textarea></div>
      <div class="row"><button class="btn btn-primary" type="submit">Haberi denetle</button><span class="status" role="status"></span></div>
    </form>
  <?php else: ?>
    <div class="stack">
      <form class="card stack" data-islem="dokum" enctype="multipart/form-data">
        <h2>1 · Ses kaydı</h2>
        <div class="field"><label for="ses">Ses dosyası</label>
          <input id="ses" type="file" name="ses" accept=".mp3,.m4a,.wav,.webm,.mp4,.ogg,.aac,.flac,audio/*" required>
          <span class="help">MP3, M4A, WAV · en fazla <?= $maxMb ?> MB (yaklaşık <?= $maxMb ?> dakika)</span></div>
        <label class="check"><input type="checkbox" name="izin" value="1" required> <span>Bu kaydı, konuşan kişinin izniyle ve haberde kullanılacağını söyleyerek aldım.</span></label>
        <div class="row"><button class="btn btn-primary" type="submit">Yazıya dök</button><span class="status" role="status"></span></div>
        <p class="help">Kayıt yalnızca döküm için işlenir, sunucuda saklanmaz.</p>
      </form>
    </div>
  <?php endif; ?>
    <section class="card stack" aria-live="polite">
      <h2>Sonuç</h2>
      <div class="result"><p class="muted"><?= $arac === 'ses' ? 'Döküm burada görünecek. Yanlış duyulan kelimeleri düzeltip kopyalayabilirsiniz.' : 'Her cümle "kaynakta var", "bağlam eksik", "çarpıtılmış" ya da "kaynakta yok" olarak işaretlenir.' ?></p></div>
    </section>
  </div>
  <p class="help" style="margin-top:16px">Araçların kararlarını da denetleyin; yapay zekâ yanılabilir.</p>
<?php endif; ?>
</div>
<?php page_foot(['assets/masa.js?v=3']);
