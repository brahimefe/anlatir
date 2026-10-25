<?php
require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/layout.php';
page_head('KVKK aydınlatma metni');
?>
<div class="wrap narrow section stack">
  <h1>Kişisel verilerin işlenmesine ilişkin aydınlatma metni</h1>
  <div class="card stack">
    <h2>Veri sorumlusu</h2>
    <p><?= e(cfg('egitmen_adi')) ?> (anlatir.com)</p>
    <h2>İşlenen veriler</h2>
    <p>Kayıtta: ad soyad, rumuz, e-posta adresi ve şifrenin geri çevrilemez özeti. Kullanımda: gönderdiğin haber, ödev ve fotoğraflar; oylama ve anlık soru cevapları (anonim olarak gösterilir); oturum bilgisi. Canlı derse yalnızca kodla katılırsan yalnızca seçtiğin rumuz işlenir.</p>
    <h2>Amaç ve hukuki sebep</h2>
    <p>Veriler seminerin yürütülmesi, gönderilerin değerlendirilmesi ve seçilen haberlerin yayımlanması amacıyla, sözleşmenin ifası ve meşru menfaat hukuki sebeplerine dayanılarak işlenir.</p>
    <h2>Aktarım</h2>
    <?php if (ai_acik()): ?>
    <p>Haber Masası araçlarına yapıştırdığın metinler, yüklediğin ses kayıtları ve gönderdiğin haber metinleri, işlenmek üzere yurt dışında bulunan OpenAI hizmetine iletilir. Ses kayıtları sunucuda saklanmaz. Bu araçlara başkalarına ait kişisel veri içeren metin yapıştırmaman önerilir.</p>
    <?php else: ?>
    <p>Verilerin üçüncü kişilere aktarılmaz.</p>
    <?php endif; ?>
    <p>Site, Hostinger'ın sunucularında barındırılır.</p>
    <h2>Saklama süresi</h2>
    <p>Kur tamamlandıktan sonra hesaplar kapatılır; hesap bilgileri ve yayımlanmamış gönderiler <?= (int)cfg('saklama_gun', 60) ?> gün içinde silinir. Yayımlanan haberler, seçtiğin imzayla arşivde kalır.</p>
    <h2>Hakların</h2>
    <p>6698 sayılı Kanun'un 11. maddesi uyarınca verilerine erişme, düzeltilmesini ya da silinmesini isteme haklarına sahipsin. Başvurunu eğitmene iletebilirsin.</p>
  </div>
  <p class="help">Bu metin bir taslaktır; yayımlamadan önce bir hukukçuya kontrol ettiriniz.</p>
</div>
<?php page_foot();
