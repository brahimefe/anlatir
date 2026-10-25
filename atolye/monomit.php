<?php
require __DIR__ . '/inc/baslat.php';

$campbell = [
  [0, 'Macera çağrısı', 'The Call to Adventure', 'Kahraman alışık olduğu dünyanın dışına çağrılır. Bir haberci, bir kaza ya da bir keşif kaderi harekete geçirir.', 'Hogwarts mektupları', 'Maceraya çağrı'],
  [0, 'Çağrının reddi', 'Refusal of the Call', 'Korku, görev duygusu ya da rahatlık kahramanı geri tutar. Reddediş, çağrının bedelini görünür kılar.', 'Harry: "Ben sadece Harry\'yim."', 'Çağrının reddi'],
  [0, 'Doğaüstü yardım', 'Supernatural Aid', 'Çağrıyı kabul eden kahramana koruyucu bir figür, çoğu zaman yaşlı bir bilge, tılsım ya da rehberlik sunar.', 'Hagrid, Gandalf', 'Akıl hocasıyla karşılaşma'],
  [0, 'İlk eşiğin aşılması', 'The Crossing of the First Threshold', 'Kahraman, bekçilerin koruduğu sınırı geçerek bilinmeyen bölgeye adım atar. Geri dönüşü olmayan karardır.', '9¾ peronu', 'İlk eşiğin geçilmesi'],
  [0, 'Balinanın karnı', 'Belly of the Whale', 'Kahraman bilinmeyenin içine yutulur. Eski benliğin simgesel ölümü, yeniden doğuşun başlangıcıdır.', 'Miles\'ın Alchemax\'a sızması', 'En derin mağaraya yaklaşma'],
  [1, 'Sınavlar yolu', 'The Road of Trials', 'Bir dizi sınav ve görev. Kahraman bunları çoğu zaman yardımcılarının desteğiyle aşar ya da kimi zaman başarısız olur.', 'Hogwarts\'taki ilk yıl', 'Sınavlar, dostlar, düşmanlar'],
  [1, 'Tanrıçayla karşılaşma', 'The Meeting with the Goddess', 'Kahraman koşulsuz bir sevgi ya da bütünlük deneyimiyle karşılaşır; Campbell bunu hayatın bütününü kavramanın simgesi olarak görür.', 'Koruyucu, besleyici bir figürle karşılaşma', 'Büyük sınav'],
  [1, 'Ayartma', 'Woman as Temptress', 'Kahramanı yolundan alıkoyan maddi ya da bedensel çekimler. Campbell\'ın özgün terimi bugün cinsiyetçi bulunduğu için genellikle ayartma olarak genelleştirilir.', 'Kolay yolu seçme isteği', 'Büyük sınav'],
  [1, 'Babayla uzlaşma', 'Atonement with the Father', 'Kahraman hayatındaki en büyük otoriteyle yüzleşir ve onunla uzlaşır. Güç ilişkisi dönüşür.', 'Miles ve babası Jefferson', 'Büyük sınav'],
  [1, 'Yücelme', 'Apotheosis', 'Kahraman eski sınırlarını aşar; yeni bir kavrayışa, bir tür yüce bilince ulaşır.', 'Miles\'ın "inancın sıçraması"', 'Ödül'],
  [1, 'Nihai ödül', 'The Ultimate Boon', 'Yolculuğun amacı elde edilir: bir iksir, bir bilgi ya da topluluğu iyileştirecek bir armağan.', 'Felsefe Taşı', 'Ödül'],
  [2, 'Dönüşün reddi', 'Refusal of the Return', 'Ödülü bulan kahraman sıradan dünyaya dönmek istemeyebilir; yeni dünyanın çekimi güçlüdür.', 'Hogwarts\'tan ayrılmak istememek', 'Dönüş yolu'],
  [2, 'Büyülü kaçış', 'The Magic Flight', 'Ödülü koruyanlardan kaçış. Dönüş yolculuğu kendi başına bir macera olabilir.', 'Kovalamaca sahneleri', 'Dönüş yolu'],
  [2, 'Dışarıdan kurtarılma', 'Rescue from Without', 'Kahraman dönüşte yardıma muhtaçtır; dış dünyadan gelen kurtarıcılar onu geri getirir.', 'Kartalların Frodo ile Sam\'i kurtarması', 'Dönüş yolu'],
  [2, 'Dönüş eşiğinin aşılması', 'The Crossing of the Return Threshold', 'Öğrenileni sıradan dünyaya taşımak ve orada paylaşmak. Campbell\'a göre en zor aşamalardan biridir.', 'Frodo\'nun Shire\'a dönüşü', 'Yeniden diriliş'],
  [2, 'İki dünyanın efendisi', 'Master of the Two Worlds', 'Kahraman hem iç hem dış dünyada, hem maddi hem manevi alanda denge kurar.', 'Miles: öğrenci ve Örümcek-Adam', 'İksirle dönüş'],
  [2, 'Yaşama özgürlüğü', 'Freedom to Live', 'Ölüm korkusundan kurtulan kahraman anı yaşar; geçmişe ya da geleceğe takılmaz.', 'Miles: "Maskeyi herkes takabilir."', 'İksirle dönüş'],
];
$voglerCampbell = [
  'Ayrı bir aşama değil; yolculuk çağrıyla başlar', 'Macera çağrısı', 'Çağrının reddi', 'Doğaüstü yardım', 'İlk eşiğin aşılması',
  'Sınavlar yolu', 'Balinanın karnı', 'Tanrıçayla karşılaşma, ayartma, babayla uzlaşma', 'Yücelme, nihai ödül',
  'Dönüşün reddi, büyülü kaçış, dışarıdan kurtarılma', 'Dönüş eşiğinin aşılması', 'İki dünyanın efendisi, yaşama özgürlüğü',
];
$filmTablosu = [
  ['Merdiven altındaki dolap', "Brooklyn'de yeni okul, grafiti", "Shire'da huzurlu hayat"],
  ['Hogwarts mektupları', "Örümcek ısırığı, Peter'ın anahtarı", 'Gandalf: Tek Yüzük'],
  ["\"Ben sadece Harry'yim.\"", 'Güçlerinden korku', '"Al onu!"'],
  ['Hagrid, Dumbledore', 'Peter B. Parker', 'Gandalf, Aragorn'],
  ['9¾ peronu', "Alchemax'a sızma", 'Sam: "Bir adım daha…"'],
  ['Seçmen Şapka, Ron, Hermione, Malfoy, trol', 'Doc Ock, Gwen ve ekip', 'Kara Süvariler, Kardeşlik, Moria'],
  ['Kapaktan iniş, dev satranç', "Aaron'un gerçeği ve ölümü", "Mordor, Shelob'un ini"],
  ['Quirrell ve Voldemort', 'Ekibin onu geride bırakması', 'Hüküm Dağı'],
  ["Taş Harry'nin cebinde", 'Babasının sözleri, kendi kostümü', 'Yüzüğün yok oluşu'],
  ['Quirrell ile boğuşma', 'İnancın sıçraması', 'Kartalların kurtarışı'],
  ['Revirde uyanış', 'Kingpin ile son dövüş', "Minas Tirith'te uyanış"],
  ['Kupa ve aidiyet', "Brooklyn'in Örümcek-Adam'ı", "Shire'a değişmiş dönüş"],
];
?>
<!doctype html>
<html lang="tr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Monomit: Tek Hikâye · anlatır</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,400;0,9..144,600;1,9..144,400&family=Instrument+Sans:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/site.css">
</head>
<body>
<header class="ust-cubuk">
  <div class="kap">
    <a class="logo" href="../atolyeler.php">anlatır<span>.</span></a>
    <nav class="menu" aria-label="Sayfa menüsü">
      <a href="#kokenler">Kökenler</a>
      <a href="#asamalar">17 aşama</a>
      <a href="#filmler">Filmlerde</a>
      <a href="#elestiri">Eleştiriler</a>
      <a class="soluk" href="../atolyeler.php">← Atölyeler</a>
    </nav>
  </div>
</header>

<main>
<section class="kap m-giris">
  <div style="flex:1;display:flex;flex-direction:column;gap:26px">
    <div class="etiket">Kahramanın yolculuğu</div>
    <h1>Monomit: <em>tek hikâye</em></h1>
    <p>Joseph Campbell'a göre mitler, masallar ve dinsel anlatılar farklı kılıklarda aynı hikâyeyi anlatır: kahraman bilinen dünyadan ayrılır, sınanır ve değişmiş olarak döner. Bu sayfa, o hikâyenin yapısını, kökenlerini ve sınırlarını ele alıyor.</p>
    <div class="m-sayilar">
      <div><b>1949</b><span>Kahramanın Sonsuz Yolculuğu</span></div>
      <div><b>3</b><span>hareket</span></div>
      <div><b>17</b><span>aşama (Campbell)</span></div>
      <div><b>12</b><span>aşama (Vogler)</span></div>
    </div>
  </div>
  <svg width="400" height="400" viewBox="0 0 400 400" role="img" aria-label="Monomit dairesi: Ayrılış, Erginlenme, Dönüş">
    <circle cx="200" cy="200" r="160" fill="none" stroke="#3A3228" stroke-width="2"/>
    <line x1="20" y1="200" x2="380" y2="200" stroke="#E8A33D" stroke-width="2" stroke-dasharray="8 6"/>
    <text x="200" y="120" text-anchor="middle" font-family="Instrument Sans, sans-serif" font-size="15" fill="#8C7F6C">Bilinen dünya</text>
    <text x="200" y="292" text-anchor="middle" font-family="Instrument Sans, sans-serif" font-size="15" fill="#8C7F6C">Bilinmeyen dünya</text>
    <text x="282" y="170" text-anchor="middle" font-family="Fraunces, serif" font-size="24" font-weight="600" fill="#F4EEE3">Ayrılış</text>
    <text x="200" y="250" text-anchor="middle" font-family="Fraunces, serif" font-size="24" font-weight="600" fill="#F4EEE3">Erginlenme</text>
    <text x="118" y="170" text-anchor="middle" font-family="Fraunces, serif" font-size="24" font-weight="600" fill="#F4EEE3">Dönüş</text>
    <circle cx="360" cy="200" r="12" fill="#E8A33D"/><circle cx="40" cy="200" r="8" fill="#8C7A5E"/>
  </svg>
</section>

<section class="kap m-bolum" id="kokenler">
  <div><div class="etiket">Kökenler</div><h2>Kavram nereden geliyor?</h2></div>
  <div class="izgara4">
    <div class="kutu"><div class="ust">Terim</div><h3>James Joyce</h3><p>"Monomyth" sözcüğü Joyce'un <i>Finnegans Wake</i> (1939) romanından. Campbell onu tüm kahraman anlatılarının ortak yapısı için kullanır.</p></div>
    <div class="kutu"><div class="ust">Yapı</div><h3>Arnold van Gennep</h3><p><i>Geçiş Ritleri</i> (1909): ayrılma, eşik ve yeniden bütünleşme. Campbell'ın üçlü yapısı bu ritüel şemasının anlatıya uyarlanmış hâlidir.</p></div>
    <div class="kutu"><div class="ust">Figürler</div><h3>Carl Gustav Jung</h3><p>Arketipler: bilge yaşlı adam, gölge, anne. Campbell'ın mentor ve düşman figürleri büyük ölçüde Jung'un kavramlarına yaslanır.</p></div>
    <div class="kutu"><div class="ust">Sinema</div><h3>Lucas ve Vogler</h3><p>George Lucas, <i>Yıldız Savaşları</i>'nda (1977) Campbell'dan etkilendiğini söyler. Christopher Vogler'in <i>The Writer's Journey</i> kitabı yapıyı senaryo şablonuna çevirir.</p></div>
  </div>
</section>

<section class="kap m-bolum">
  <div><div class="etiket">Üç hareket</div><h2>Ayrılış · Erginlenme · Dönüş</h2></div>
  <div class="izgara3">
    <div class="hareket"><h3>Ayrılış</h3><p>Kahraman alışık olduğu dünyada bir eksiklik ya da çağrıyla karşılaşır. Tereddüt eder, yardım bulur ve bilinmeyene açılan eşiği geçer.</p><small>van Gennep: ayrılma</small></div>
    <div class="hareket"><h3>Erginlenme</h3><p>Bilinmeyen dünyada sınavlar, dostlar ve düşmanlar vardır. Kahraman en büyük korkusuyla yüzleşir; eski benliği geride kalır, bir ödül ya da kavrayış kazanır.</p><small>van Gennep: eşik (liminal) evre</small></div>
    <div class="hareket"><h3>Dönüş</h3><p>Kahraman kazandığını sıradan dünyaya taşır. Dönüş de bir sınavdır: öğrenileni topluluğa aktarmak, yolculuğun asıl anlamını belirler.</p><small>van Gennep: yeniden bütünleşme</small></div>
  </div>
</section>

<section class="kap m-bolum" id="asamalar">
  <div style="display:flex;justify-content:space-between;align-items:flex-end;gap:16px;flex-wrap:wrap">
    <div><div class="etiket">Campbell'ın 17 aşaması</div><h2>Bir aşamaya tıklayın</h2></div>
    <div style="font-size:16px;color:var(--soluk)">Türkçe adlar açıklama amaçlıdır; yayımlanmış çeviriyle birebir örtüşmeyebilir.</div>
  </div>
  <div class="kasif">
    <div class="kasif-sol" id="kasifSol"></div>
    <div class="kasif-sag" aria-live="polite">
      <div style="display:flex;justify-content:space-between;align-items:center;gap:12px">
        <div class="etiket" id="kHareket"></div>
        <div style="display:flex;gap:8px">
          <button class="yuvarlak" id="kGeri" aria-label="Önceki aşama">‹</button>
          <button class="yuvarlak ana" id="kIleri" aria-label="Sonraki aşama">›</button>
        </div>
      </div>
      <h3 id="kAd"></h3>
      <div class="en" id="kEn"></div>
      <p id="kAciklama"></p>
      <div class="kasif-alt">
        <div><span>ÖRNEK</span><b id="kOrnek"></b></div>
        <div><span>VOGLER'DE</span><b id="kVogler"></b></div>
      </div>
    </div>
  </div>
</section>

<section class="kap m-bolum">
  <div>
    <div class="etiket">Campbell'dan Vogler'e</div>
    <h2>17 aşama, 12 aşama</h2>
  </div>
  <p class="aciklama">Vogler, Campbell'ın mitolojik şemasını senaryo yazımı için 12 aşamaya indirir. Atölyede filmleri bu 12 aşamayla çözümlüyoruz.</p>
  <div class="tablo-kap"><table class="tablo">
    <thead><tr><th style="width:70px">No</th><th>Vogler (12)</th><th>Campbell karşılığı</th></tr></thead>
    <tbody>
    <?php foreach ($VERI['vogler'] as $i => $v): ?>
      <tr><td style="color:var(--soluk);font-weight:400"><?= $i + 1 ?></td><td><?= e($v[0]) ?></td><td><?= e($voglerCampbell[$i]) ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</section>

<section class="kap m-bolum" id="filmler">
  <div><div class="etiket">Üç filmde monomit</div><h2>Aynı yapı, üç kahraman</h2></div>
  <div class="tablo-kap"><table class="tablo">
    <thead><tr><th>Aşama</th>
      <?php foreach ($VERI['filmler'] as $f): ?><th class="film"><?= e($f['ad']) ?></th><?php endforeach; ?>
    </tr></thead>
    <tbody>
    <?php foreach ($filmTablosu as $i => $satir): ?>
      <tr><td><?= ($i + 1) . ' · ' . e($VERI['vogler'][$i][3]) ?></td><td><?= e($satir[0]) ?></td><td><?= e($satir[1]) ?></td><td><?= e($satir[2]) ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</section>

<section class="kap m-bolum" id="elestiri">
  <div><div class="etiket">Eleştiriler ve sınırlar</div><h2>Her hikâye gerçekten aynı mı?</h2></div>
  <div class="izgara4">
    <div class="kutu" style="background:transparent;border:0;padding:0"><h3>Evrensellik iddiası</h3><p>Farklı kültürlerden seçilen örnekler ortak yapıya uydurulur; uymayan anlatılar dışarıda kalır, farklılıklar düzleşir.</p></div>
    <div class="kutu" style="background:transparent;border:0;padding:0"><h3>Erkek merkezlilik</h3><p>Maureen Murdock, <i>The Heroine's Journey</i> (1990) ile kadın kahramanın yolculuğunu ayrı bir yapı olarak önerir.</p></div>
    <div class="kutu" style="background:transparent;border:0;padding:0"><h3>Bireycilik</h3><p>Tek bir seçilmiş kahramana odaklanır; kolektif eylemi ve topluluk anlatılarını görünmez kılabilir.</p></div>
    <div class="kutu" style="background:transparent;border:0;padding:0"><h3>Kalıplaşma</h3><p>Hollywood'da senaryo şablonuna dönüşmesi anlatıları öngörülebilir kılar; yapı bir mercek olmaktan çıkıp reçeteye dönüşür.</p></div>
  </div>
  <div class="not-kutu"><b>Neden yine de kullanışlı?</b> Monomit, anlatıları çözümlemek için bir mercek sunar: bir hikâyenin nerede durduğunu, kahramanın neyi seçtiğini ve neyin bedelini ödediğini görünür kılar. Atölyede onu bir kural olarak değil, bu mercek olarak kullanıyoruz.</div>
</section>

<section class="kap kapanis-satir">
  <div><div class="buyuk">Her hikâye bir <em>tercih</em> anlatır.</div><div style="font-size:18px;color:var(--ikincil);margin-top:8px">Tercih dönemi de bir eşiktir.</div></div>
  <a class="dugme" href="../atolyeler.php">Atölyeler</a>
</section>
</main>

<script>
(function () {
  var S = <?= json_encode($campbell, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
  var HAREKET = ['Ayrılış', 'Erginlenme', 'Dönüş'];
  var sec = 0;
  var sol = document.getElementById('kasifSol');
  function kur() {
    sol.innerHTML = '';
    HAREKET.forEach(function (h, hi) {
      var grup = document.createElement('div');
      var baslik = document.createElement('h4'); baslik.textContent = h; grup.appendChild(baslik);
      var dizi = document.createElement('div'); dizi.className = 'hap-dizi';
      S.forEach(function (x, i) {
        if (x[0] !== hi) return;
        var b = document.createElement('button');
        b.type = 'button'; b.className = 'hap' + (i === sec ? ' secili' : '');
        b.textContent = (i + 1) + ' · ' + x[1];
        b.addEventListener('click', function () { sec = i; ciz(); });
        dizi.appendChild(b);
      });
      grup.appendChild(dizi); sol.appendChild(grup);
    });
  }
  function ciz() {
    var x = S[sec];
    document.getElementById('kHareket').textContent = HAREKET[x[0]] + ' · aşama ' + (sec + 1) + ' / 17';
    document.getElementById('kAd').textContent = x[1];
    document.getElementById('kEn').textContent = x[2];
    document.getElementById('kAciklama').textContent = x[3];
    document.getElementById('kOrnek').textContent = x[4];
    document.getElementById('kVogler').textContent = x[5];
    kur();
  }
  document.getElementById('kGeri').addEventListener('click', function () { sec = (sec + 16) % 17; ciz(); });
  document.getElementById('kIleri').addEventListener('click', function () { sec = (sec + 1) % 17; ciz(); });
  ciz();
})();
</script>
</body>
</html>
