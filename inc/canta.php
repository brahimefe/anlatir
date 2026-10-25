<?php
/* Hafta sayfalarındaki yazdırılabilir saha kartları (inc/weeks.php > 'canta') */
function canta_kart($tip)
{
    $basliklar = ['kaynak' => 'Kaynak listesi', 'saha' => 'Sahaya çıkmadan önce', 'kontrol' => 'Haber kontrol listesi'];
    if (!isset($basliklar[$tip])) {
        return;
    }
    ?>
<section class="card stack canta" id="saha-karti">
  <div class="row between">
    <h2><?= $basliklar[$tip] ?></h2>
    <button class="btn btn-sm noprint" type="button" onclick="document.body.classList.add('kart-yazdir');window.print();document.body.classList.remove('kart-yazdir')">Yazdır</button>
  </div>
<?php if ($tip === 'kaynak'): ?>

    
    <div class="table-wrap">
      <table class="table kaynak-tablo">
        <thead><tr><th>Bilgi</th><th>Kaynak</th><th>Nasıl</th><th>Tarih</th><th>Teyit</th></tr></thead>
        <tbody><?php for ($i = 0; $i < 6; $i++): ?><tr><td></td><td></td><td></td><td></td><td>☐</td></tr><?php endfor; ?></tbody>
      </table>
    </div>
    <p class="help">Nasıl: belge · röportaj · telefon · e-posta · gözlem</p>
  
<?php elseif ($tip === 'saha'): ?>
<div class="stack">
    <h3>İzin cümleleri</h3>
    <div class="stack" style="gap:12px">
      <div><strong>Röportaj ve ses kaydı</strong>
        <p class="alinti">"Merhaba, ben ……… . anlatir.com'da yayımlanacak bir haber için sizinle konuşmak istiyorum. Konuşmamızı doğru aktarmak için ses kaydı alabilir miyim? Adınızı ve unvanınızı haberde kullanabilir miyim?"</p></div>
      <div><strong>Fotoğraf</strong>
        <p class="alinti">"Haberde kullanmak için fotoğrafınızı çekebilir miyim? Yüzünüzün görünmesini istemezseniz çekmem."</p></div>
      <div><strong>Adını vermek istemeyen kaynak</strong>
        <p class="alinti">"Adınızı yazmamı istemiyorsanız sizi nasıl tanımlayayım? Örneğin 'mahalle sakini' yazabilir miyim?"</p></div>
      <ul class="bullets small">
        <li>İzni kayıt başlamadan önce al; izin cümlesini de kayda al.</li>
        <li>Çocukların ve mağdurların yüzü tanınır biçimde çekilmez.</li>
      </ul>
    </div>
  </div>
<div class="stack">
    <h3>Röportaj soru şablonu</h3>
    <div class="satirlar">
      <div><span>Konu</span><i></i></div>
      <div><span>Kiminle konuşacağım</span><i></i></div>
      <div><span>Neden bu kişi</span><i></i></div>
    </div>
    <strong>Açık uçlu sorular</strong>
    <div class="satirlar"><div><span>1.</span><i></i></div><div><span>2.</span><i></i></div><div><span>3.</span><i></i></div></div>
    <strong>Teyit soruları</strong>
    <div class="satirlar"><div><span>1.</span><i></i></div><div><span>2.</span><i></i></div></div>
    <strong>Son iki soru</strong>
    <ul class="bullets small"><li>"Eklemek istediğiniz bir şey var mı?"</li><li>"Bu konuda başka kiminle konuşmalıyım?"</li></ul>
  </div>
<?php else: ?>

    
    <ul class="kutular">
      <?php foreach (['Kim?', 'Ne?', 'Nerede?', 'Ne zaman?', 'Nasıl?', 'Neden?', 'En önemli bilgi ilk paragrafta.', 'Her bilginin kaynağı yazılı.', 'Rakamlar kaynakla birebir aynı.', 'Ad, unvan ve alıntılar doğru yazıldı.', 'Yorum ya da abartı içeren cümle yok.', 'Başlık haberi doğru özetliyor.'] as $i => $m): ?>
        <li><label class="check"><input type="checkbox"> <span><?= e($m) ?></span></label></li>
      <?php endforeach; ?>
    </ul>
  
<?php endif; ?>
</section>
<?php
}
