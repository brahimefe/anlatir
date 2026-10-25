<?php
/* Öğrencinin telefonda gördüğü canlı ders ekranı */
require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/layout.php';

if (!katilim()) {
    redirect('katil.php');
}
page_head('Canlı ders');
?>
<div class="phone" id="canli" data-mode="telefon" data-csrf="<?= e(csrf_token()) ?>">
  <div class="live-top">
    <span><span class="dot" aria-hidden="true"></span><strong id="t-hafta">Bağlanıyor…</strong></span>
    <span class="mono muted" id="t-sayfa"></span>
  </div>
  <p class="flash err hidden" id="baglanti" role="status" style="margin:0">Bağlantı koptu, yeniden deneniyor…</p>
  <p class="flash hidden" id="katilim-uyari" style="margin:0">Katılımın henüz işlenmedi. Salondaki QR'ı okut ya da <a href="katil.php">ders kodunu gir</a>.</p>

  <section id="v-bekle" class="card big-msg stack hidden">
    <h1 style="font-size:28px">Ders henüz başlamadı</h1>
  </section>

  <section id="v-ders" class="stack hidden">
    <div class="slide-box" id="slide"><p class="placeholder">Sunum yükleniyor…</p></div>
  </section>

  <section id="v-soru" class="card stack hidden" aria-live="polite">
    <span class="kicker">Anlık soru · cevaplar anonim</span>
    <h2 id="soru-metin" style="font-size:24px"></h2>
    <form id="soru-form" class="stack">
      <div class="opt-list" id="soru-secenek"></div>
      <button class="btn btn-primary btn-block" type="submit">Cevabı gönder</button>
    </form>
    <p id="soru-tamam" class="hidden"><strong>Cevabın alındı.</strong> <span class="muted">Sonuçlar birazdan ekranda.</span></p>
    <div id="soru-sonuc" class="stack hidden"></div>
  </section>

  <section id="v-oylama" class="card stack hidden" aria-live="polite">
    <span class="row between"><span class="kicker">Haftanın haberi</span><span class="tag tag-gold" id="oy-durum">Oylama açık</span></span>
    <h2 style="font-size:24px">Bu haftanın en iyi haberi hangisi?</h2>
    <form id="oy-form" class="stack">
      <div class="opt-list" id="oy-secenek"></div>
      <button class="btn btn-primary btn-block" type="submit">Oyumu ver</button>
    </form>
    <p id="oy-giris" class="hidden">Oy vermek için <a href="giris.php?donus=canli.php">giriş yap</a>.</p>
    <p id="oy-tamam" class="hidden"><strong>Oyun alındı.</strong></p>
    <p id="oy-sonuc" class="hidden"></p>
    <p class="help">Oylar gizlidir.</p>
  </section>

  <section id="v-bitti" class="stack hidden">
    <div class="card stack">
      <span class="kicker" id="bitti-hafta"></span>
      <h1 style="font-size:30px">Bugünkü ders sona erdi.</h1>
      <p class="muted" id="bitti-tesekkur"></p>
    </div>
    <div class="card stack" style="border-color:var(--accent)">
      <span class="kicker">Bu haftanın ödevi</span>
      <p id="bitti-odev"></p>
      <?php if (!is_admin()): ?><a class="btn btn-primary" id="bitti-gonder" href="gonder.php">Ödevi gönder</a><?php endif; ?>
      <a id="bitti-ders" href="dersler.php">Ders sayfasını aç →</a>
    </div>
    <div class="card" id="bitti-sonraki"></div>
  </section>

  <p class="small muted" style="text-align:center">Sunuma bağlısın · <strong id="t-rumuz"><?= e(katilim()['rumuz']) ?></strong> · <a href="katil.php?degistir=1">Rumuzu değiştir</a></p>
</div>
<?php page_foot(['assets/lib/pdf.min.js', 'assets/canli.js?v=5']);
