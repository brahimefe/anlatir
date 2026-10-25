<?php
// Atölyenin tüm içerik verisi. Hem sayfa (JavaScript) hem de yapay zekâ istemi bu dosyadan beslenir.
// Metinleri buradan düzenleyebilirsiniz.

return [
  'bolumler' => [
    ['Giriş', 0, 3], ['Film', 3, 4], ['Anlatım', 4, 11], ['Daire', 11, 14],
    ['Ortak kahraman', 14, 23], ['Kahraman kartı', 23, 26], ['Kapanış', 26, 30],
  ],

  'hikaye_satirlari' => [
    'Sıradan bir dünyada yaşayan genç biri var.',
    'Bir gün bir haber gelir ve hayatı değişir.',
    'Önce gitmek istemez.',
    'Bilge biri ona yol gösterir.',
    'Bir sınırı geçer.',
    'Zorluklarla sınanır, en büyük korkusuyla yüzleşir.',
    'Ve değişmiş olarak evine döner.',
  ],

  // Vogler'in 12 aşaması: [ad, açıklama, hareket (0 Ayrılış, 1 Erginlenme, 2 Dönüş), kısa ad]
  'vogler' => [
    ['Olağan dünya', 'Kahramanı gündelik hayatında tanırız. Bir şey eksiktir, ama o henüz bunun farkında değildir.', 0, 'Olağan dünya'],
    ['Maceraya çağrı', 'Bir sorun, bir haber ya da bir fırsat alışılmış düzeni bozar.', 0, 'Çağrı'],
    ['Çağrının reddi', 'Korku ya da şüphe ağır basar; kahraman önce geri çekilir.', 0, 'Çağrının reddi'],
    ['Akıl hocasıyla karşılaşma', 'Bilge bir figür cesaret, bilgi ya da bir araç verir; ama yolu onun yerine yürümez.', 0, 'Akıl hocası'],
    ['İlk eşiğin geçilmesi', 'Kahraman karar verir ve bilinmeyen dünyaya adım atar. Artık geri dönüş yoktur.', 0, 'İlk eşik'],
    ['Sınavlar, dostlar, düşmanlar', 'Yeni dünyanın kuralları öğrenilir; kimin dost, kimin düşman olduğu belli olur.', 1, 'Sınavlar'],
    ['En derin mağaraya yaklaşma', 'Kahraman en büyük tehlikenin bulunduğu yere yaklaşır. Hazırlık ve gerilim artar.', 1, 'Mağaraya yaklaşma'],
    ['Büyük sınav', 'Her şeyin kaybedilebileceği an. Kahraman en büyük korkusuyla yüzleşir.', 1, 'Büyük sınav'],
    ['Ödül', 'Sınavı atlatan kahraman aradığını ele geçirir: bir nesne, bir bilgi ya da bir uzlaşma.', 1, 'Ödül'],
    ['Dönüş yolu', 'Kahraman eve yönelir; ama tehlike henüz bitmemiştir.', 2, 'Dönüş yolu'],
    ['Yeniden diriliş', 'Son ve en zorlu sınav. Kahraman bu kez değişmiş biri olarak ayağa kalkar.', 2, 'Yeniden diriliş'],
    ['İksirle dönüş', 'Kahraman evine döner ve kazandığını topluluğuyla paylaşır.', 2, 'İksirle dönüş'],
  ],
  // Dairedeki konumlar (derece; -90 en üst, 0 sağ, 90 en alt)
  'aci' => [-90, -62, -38, -18, 0, 32, 62, 90, 122, 152, 180, -130],

  // Sahne görselleri: sahneler/<id>-01.jpg … sahneler/<id>-12.jpg (jpg, png ya da webp)
  // 'kaynak' görselin altında künye olarak gösterilir.
  'filmler' => [
    ['id' => 'hp', 'kaynak' => 'Warner Bros. Pictures, 2001', 'ad' => 'Harry Potter ve Felsefe Taşı', 'kisa' => 'Harry Potter', 'etiket' => 'Bir mektup, bir duvar, bir peron', 'sahneler' => [
      "Harry, Dursley'lerin evinde merdiven altındaki dolapta yaşıyor.",
      "Hogwarts'tan mektuplar yağıyor; Dursley'ler engelledikçe mektuplar çoğalıyor.",
      "Hagrid'e \"Ben büyücü olamam, ben sadece Harry'yim\" diyor.",
      "Hagrid onu büyü dünyasına götürüyor; arka planda Dumbledore yol gösteriyor.",
      "Harry, 9¾ peronunun duvarına doğru koşup geçiyor.",
      "Seçmen Şapka, Ron ve Hermione ile dostluk, Malfoy ile düşmanlık, trol.",
      "Üç başlı köpeğin koruduğu kapaktan iniş; Şeytan Kapanı ve dev satranç.",
      "Aynanın önünde Quirrell ve Voldemort ile yüzleşme.",
      "Felsefe Taşı aynadan Harry'nin cebine düşüyor.",
      "Taşı kaptırmamak için Quirrell ile boğuşuyor ve bayılıyor.",
      "Revirde uyanıyor; Dumbledore annesinin sevgisinin onu koruduğunu anlatıyor.",
      "Gryffindor kupayı kazanıyor; Harry yaza dostlarıyla ve bir aidiyet duygusuyla dönüyor.",
    ]],
    ['id' => 'sv', 'kaynak' => 'Sony Pictures Animation, 2018', 'ad' => 'Örümcek-Adam: Örümcek Evreninde', 'kisa' => 'Örümcek-Adam', 'etiket' => 'Kendine inanmayı öğrenen bir lise öğrencisi', 'sahneler' => [
      "Miles Morales, Brooklyn'de yeni okuluna uyum sağlamaya çalışan, grafiti çizen bir lise öğrencisi.",
      "Metro tünelinde bir örümcek onu ısırıyor; ölmek üzere olan Peter Parker ona çarpıştırıcıyı durduracak anahtarı veriyor.",
      "Miles güçlerinden korkuyor; bu yükün kendisine göre olmadığını düşünüyor.",
      "Başka bir evrenden gelen yorgun Peter B. Parker ona salınmayı ve düşmeyi öğretiyor.",
      "Miles, Peter B. ile Alchemax laboratuvarına sızıyor; geri dönüşü olmayan dünyaya adım atıyor.",
      "Doc Ock kovalaması; Gwen ve diğer Örümcek-insanlarla tanışma.",
      "Amcası Aaron'un Prowler olduğunu öğreniyor ve onun ölümüne tanık oluyor.",
      "Ekip onu \"hazır değilsin\" diyerek geride bırakıyor; Miles tek başına kalıyor.",
      "Babasının kapı arkasından söyledikleriyle güç buluyor; kendi kostümünü hazırlıyor.",
      "\"İnancın sıçraması\": gökdelenden atlıyor ve ekibe yetişiyor.",
      "Kingpin ile son dövüş; çarpıştırıcıyı durduruyor ve herkesi evine gönderiyor.",
      "Brooklyn'in yeni Örümcek-Adam'ı olarak ailesine ve şehrine dönüyor.",
    ]],
    ['id' => 'lotr', 'kaynak' => 'New Line Cinema, 2001–2003', 'ad' => 'Yüzüklerin Efendisi', 'kisa' => 'Yüzüklerin Efendisi', 'etiket' => 'Taşıyamayacağı bir yükü üstlenen bir hobbit', 'sahneler' => [
      "Frodo, Shire'da huzurlu bir hayat sürüyor; Bilbo gidince yüzük ona kalıyor.",
      "Gandalf, yüzüğün Tek Yüzük olduğunu açıklıyor: Frodo onu Shire'dan uzaklaştırmalı.",
      "Frodo yüzüğü Gandalf'a vermeye çalışıyor: \"Al onu!\" Gandalf reddediyor.",
      "Gandalf yol gösteriyor; yolda Aragorn da onlara katılıyor.",
      "Mısır tarlasında Sam: \"Bir adım daha atarsam, evden hiç bu kadar uzaklaşmamış olacağım.\"",
      "Kara Süvariler, Ayrıkvadi'de Kardeşliğin kurulması, Moria ve Gandalf'ın düşüşü.",
      "Gollum'un rehberliğinde Mordor'a giriş; Shelob'un ini.",
      "Hüküm Dağı'nda Frodo yüzüğü ateşe atamıyor; Gollum ile boğuşma.",
      "Yüzük ateşe düşüyor; Sauron yıkılıyor.",
      "Hüküm Dağı patlarken kartallar Frodo ile Sam'i kurtarıyor.",
      "Frodo Minas Tirith'te uyanıyor ve dostlarıyla yeniden buluşuyor.",
      "Shire'a dönüyor ama eskisi gibi olamıyor; hikâyesini Sam'e bırakıyor.",
    ]],
  ],

  // Ortak kahraman adımları. Yapay zekâ istemi de bu metinlerden kurulur.
  'adimlar' => [
    ['etiket' => 'Dünya', 'asama' => 'Olağan dünya', 'soru' => 'Kahraman nerede yaşıyor?', 'secenekler' => ["Karadeniz'de bir köy", "2050 İstanbul'u", 'Mars kolonisi']],
    ['etiket' => 'Özellik', 'asama' => 'Olağan dünya', 'soru' => 'En güçlü yanı ne?', 'secenekler' => ['Herkesi dinler', 'Asla pes etmez', 'Başkalarının görmediğini görür']],
    ['etiket' => 'Çağrı', 'asama' => 'Maceraya çağrı', 'soru' => 'Hayatını ne değiştiriyor?', 'secenekler' => ['Gizemli bir mesaj', 'Yaşadığı yere yaklaşan bir tehlike', 'Büyük bir yarışma']],
    ['etiket' => 'Korku', 'asama' => 'Çağrının reddi', 'soru' => 'Onu ne durduruyor?', 'secenekler' => ['Başaramamak', 'Ailesini hayal kırıklığına uğratmak', 'Yalnız kalmak']],
    ['etiket' => 'Mentor', 'asama' => 'Akıl hocasıyla karşılaşma', 'soru' => 'Kim yol gösteriyor?', 'secenekler' => ['Yaşlı bir usta', 'Bir yapay zekâ', 'En yakın arkadaşı']],
    ['etiket' => 'Eşik', 'asama' => 'İlk eşiğin geçilmesi', 'soru' => 'Hangi yolu seçiyor?', 'secenekler' => ['Kimsenin gitmediği yol', 'En güvenli yol', 'Kalbinin gösterdiği yol']],
  ],

  // Kahramanın okuyacağı bölüm yalnızca bu listeden seçilir (İbn Haldun Üniversitesi lisans programları)
  'ihu_bolumleri' => ['Hukuk', 'Siyaset Bilimi ve Uluslararası İlişkiler', 'Psikoloji', 'İşletme', 'İktisat', 'Sosyoloji',
    'Rehberlik ve Psikolojik Danışmanlık', 'Tarih', 'İslami İlimler', 'Yeni Medya ve İletişim', 'Felsefe ve Yapay Zekâ'],

  // Yapay zekâ yanıt vermezse kullanılan şablon kahraman (artık kullanılmıyor; YZ yoksa salonun cevapları gösterilir)
  'sablon' => [
    'adlar' => [['Elif', 'Yusuf', 'Zehra'], ['Defne', 'Kerem', 'Ada'], ['Nehir', 'Arda', 'Umut']],
    'alt_dunya' => ["Karadeniz'de bir köy", "2050 İstanbul'u", 'Mars kolonisi'],
    'alt_ozellik' => ['herkesi dinleyen biri', 'asla pes etmeyen biri', 'görünmeyeni gören biri'],
    'dunya' => [
      '{ad}, Karadeniz\'de sisli bir köyde, dağla denizin arasında yaşıyor.',
      '{ad}, sular yükselmiş 2050 İstanbul\'unda, köprülerin arasında büyüdü.',
      '{ad}, Mars kolonisinde, cam kubbelerin altında doğdu.',
    ],
    'ozellik' => [
      'Herkesin derdini dinleyen biri olarak tanınıyor.',
      'Neye başlasa bitirmeden bırakmıyor.',
      'Başkalarının görmediği ayrıntıları fark ediyor.',
    ],
    'cagri' => [
      'Bir gece kimsenin göndermediği gizemli bir mesaj alıyor.',
      'Yaşadığı yere bir tehlikenin yaklaştığını ilk o fark ediyor.',
      'Hayatını değiştirebilecek büyük bir yarışmaya çağrılıyor.',
    ],
    'korku' => [
      'Ama başaramamaktan korkuyor ve bir süre yerinden kıpırdamıyor.',
      'Ama ailesini hayal kırıklığına uğratmaktan korkuyor ve önce geri çekiliyor.',
      'Ama yolda yalnız kalmaktan korkuyor ve kapıyı kapatıyor.',
    ],
    'mentor' => [
      'Yaşlı bir usta ona "Yolu senin yerine yürüyemem, ama nereden başlayacağını gösterebilirim" diyor.',
      'Bir yapay zekâ ona bütün olasılıkları gösteriyor, ama son kararı ona bırakıyor.',
      'En yakın arkadaşı elini omzuna koyup "Ben yanındayım" diyor.',
    ],
    'esik' => [
      'Sonunda kimsenin gitmediği yolu seçiyor; sınanıyor, düşüyor, kalkıyor ve eve değişmiş olarak dönüyor.',
      'Sonunda en güvenli yolu seçiyor; ama o yolda bile en büyük korkusuyla yüzleşiyor ve eve değişmiş olarak dönüyor.',
      'Sonunda kalbinin gösterdiği yolu seçiyor; sınanıyor ve eve bambaşka biri olarak dönüyor.',
    ],
    'soz' => ['Yol yoksa, ilk adımı ben atarım.', 'Sağlam zemin de cesaret ister.', 'Pusulam içimde; yolu o gösterir.'],
    'bolum' => [
      ['Sosyoloji', 'İnşaat Mühendisliği', 'Görsel İletişim Tasarımı'],
      ['Psikoloji', 'Bilgisayar Mühendisliği', 'Yeni Medya ve İletişim'],
      ['Tıp', 'Uzay ve Havacılık Mühendisliği', 'Mimarlık'],
    ],
    'gerekce' => [
      'İnsanları dinlemek onun gücü; başkalarının hikâyesini anlamayı meslek edinirdi.',
      'Zor bir problem çözülene kadar bırakmayan biri; en çetin sorunlarla uğraşmak isterdi.',
      'Başkalarının görmediğini görmek, onu yeni şeyler tasarlamaya ve anlatmaya yöneltirdi.',
    ],
  ],
];
