<?php
/*
 * 8 haftalık ders içeriği. Metinleri buradan düzenleyebilirsiniz.
 * 'arac'    : yapay zekâ açıksa o haftanın Haber Masası aracı (karsilastir, denetle, ses) ya da null
 * 'foto'    : o haftanın fotoğraf bölümü (yoksa null)
 * 'ornekler': o haftanın sayfasında gösterilecek örnek haberler (inc/ornekler.php içindeki id'ler)
 * 'sorular' : sunucu ekranında tek dokunuşla telefonlara gönderilecek hazır sorular
 * 'odev_turu': 'metin' = yalnızca eğitmenin gördüğü yazılı ödev, 'haber' = yayına aday haber formu
 * 'canta'  : hafta sayfasında yazdırılabilir saha kartı (kaynak, saha, kontrol; inc/canta.php) ya da null
 */
return [
    1 => [
        'baslik' => 'Haberin anatomisi',
        'kisa'   => '5N1K ve ters piramit',
        'ozet'   => [
            'Haber nedir: yeni, yayımlanmamış, olağandışı ve ilgi çekici bilgi; merak edilen her şey haber değildir.',
            'Bir olayı haber yapan altı değer: güncellik, etki, ünlü kişi, yakınlık, sıra dışılık, çatışma.',
            'Söylenen ile olan: “uyardı” haberi, “oldu” haberinden zayıftır.',
            '5N1K ve ilk cümle: en önemli bir iki soruyu yaklaşık 20 kelimede cevapla.',
            'Ters piramit: okurun sıradaki sorusu, sıradaki paragraftır.',
            'Haber ile yorum: doğrulanabilir olan haberdir; “tarihî”, “skandal”, “itiraf etti” yorum taşır.',
        ],
        'atolye' => [
            'Aynı olayı işleyen üç farklı sitenin haberini okuyun.',
            'Altı sorunun cevabının altını çizin; hangisi ilk cümlede?',
            'Yorum kelimelerini daire içine alın; haber ve yorum cümlelerini ayırın.',
        ],
        'foto'   => null,
        'odev'   => 'Beş haber fikri yaz. Her biri için iki cümle: Neden şimdi? Neden önemli?',
        'odev_turu' => 'metin',
        'canta'  => null,
        'arac'   => null,
        'ornekler' => ['ornek-ulasim-zam', 'ornek-skandal-zam'],
        'sorular' => [
            ['“Şehirde toplu taşıma ücretlerine yüzde 30 zam yapıldı.” Bu haberi en güçlü yapan değer hangisi?', ['Etki', 'Yakınlık', 'Güncellik', 'Çatışma']],
            ['“Uzmanlar kuraklık konusunda uyardı” ile “Baraj doluluğu yüzde 25’e düştü”. Hangisi daha güçlü haber?', ['Uyardı', 'Düştü', 'İkisi de aynı']],
            ['“Tarihî yasa Meclis’ten geçti.” Bu başlık haber mi, yorum mu?', ['Haber', 'Yorum', 'İkisi de']],
            ['Haberde varsayılan fiil hangisidir?', ['Dedi', 'İtiraf etti', 'İddia etti', 'Vurguladı']],
        ],
    ],
    2 => [
        'baslik' => 'Kaynak ve bilgi toplama',
        'kisa'   => 'Resmî kaynaklar, bilgi edinme hakkı',
        'ozet'   => [
            'Birincil kaynak: olayı yaşayan, kararı veren ya da belgeyi yazan.',
            'Resmî kaynaklar: belediye meclis kararları, kurum duyuruları, Resmî Gazete.',
            'Bilgi Edinme Hakkı Kanunu ile kurumdan yazılı bilgi isteyebilirsiniz.',
            'Basın bülteni haber değildir; haberin ham maddesidir.',
        ],
        'atolye' => [
            'Grubunuza verilen bülteni okuyun.',
            'Bültenden 4–6 cümlelik bir haber yazın.',
            'Haberinizi bültenle cümle cümle karşılaştırın: her bilgi bültende var mı?',
            'İşaretlenen cümleleri grupça tartışın: bu bilgi nereden geldi?',
        ],
        'foto'   => null,
        'odev'   => 'Haber fikirlerinden biri için kaynak listesi hazırla: kiminle konuşacaksın, hangi belgeye bakacaksın?',
        'odev_turu' => 'metin',
        'canta'  => 'kaynak',
        'arac'   => 'karsilastir',
        'ornekler' => ['ornek-yeni-park', 'ornek-dev-kres'],
        'sorular' => [
            ['"Park Mart\'ta açılacak" bilgisinin birincil kaynağı hangisi?', ['Mahalle muhtarı', 'Belediyenin resmî açıklaması', 'WhatsApp grubu']],
            ['Basın bülteni nedir?', ['Hazır haber', 'Haberin ham maddesi', 'Reklam']],
        ],
    ],
    3 => [
        'baslik' => 'Sahada muhabirlik',
        'kisa'   => 'Röportaj, ses kaydı, ilk fotoğraf',
        'ozet'   => [
            'Soru hazırlama: açık ve kapalı sorular.',
            'Gözlem notu: gördüğünüzü yorumdan ayırın.',
            'Telefonla ses kaydı; kayıt için izin.',
            'İsim, unvan ve alıntının doğru yazımı.',
        ],
        'atolye' => [
            'Eşinizle röportaj yapın; biri muhabir, biri kaynak olsun.',
            'Kaydı yazıya dökün (telefonun deşifre özelliğiyle ya da elle).',
            'Dökümden kısa bir haber yazın; her alıntının yanına kaydın dakikasını not edin.',
        ],
        'foto'   => [
            'baslik' => 'Fotoğraf 1 · Haber fotoğrafı nedir?',
            'maddeler' => [
                'Haber fotoğrafı olayı gösterir; süslemez.',
                'Telefonla çekim: yatay kadraj, ışığı arkana al, yakınlaş.',
                'Tanınabilir kişiyi çekmeden önce izin iste; çocukları ve mağdurları çekme.',
            ],
        ],
        'odev'   => 'Gerçek bir kişiyle, izin alarak röportaj yap. Kaydı yazıya dök, kısa bir haber yaz ve bir fotoğrafla birlikte gönder.',
        'odev_turu' => 'haber',
        'canta'  => 'saha',
        'arac'   => 'ses',
        'ornekler' => [],
        'sorular' => [
            ['Röportaj kaydına başlamadan önce ne yapmalısın?', ['Kaydı gizlice başlatmak', 'Kayıt için izin istemek', 'Soruları ezberlemek']],
            ['Hangisi açık uçlu bir soru?', ['Park ne zaman açılacak?', 'Parktan memnun musunuz?', 'Bu park mahalleye ne kazandıracak?']],
        ],
    ],
    4 => [
        'baslik' => 'Haber yazımı',
        'kisa'   => 'Başlık, spot, alıntı',
        'ozet'   => [
            'Başlık, spot ve gövde.',
            'Doğrudan ve dolaylı alıntı; kaynağı cümlenin içinde vermek.',
            'Sayıların ve tarihlerin yazımı.',
            'Tıklama tuzağı ile bilgilendirici başlık arasındaki fark.',
        ],
        'atolye' => [
            'Röportajınızdan kendi haberinizi yazın.',
            'Haberinizi kaynağınızla cümle cümle karşılaştırın.',
            'Araç sorularına göre haberinizi düzeltin.',
        ],
        'foto'   => null,
        'odev'   => '300–400 kelimelik kısa bir haber yaz; her bilginin kaynağını ayrı satırda ver.',
        'odev_turu' => 'haber',
        'canta'  => 'kontrol',
        'arac'   => 'karsilastir',
        'ornekler' => ['ornek-dev-kres'],
        'sorular' => [
            ['Hangisi bilgilendirici bir başlık?', ['Bunu görenler şaşırdı!', 'Yeni park Mart ayında açılacak', 'Müjde geldi']],
            ['Spotun görevi nedir?', ['Haberi iki satırda özetlemek', 'Okuru meraklandırmak', 'Yazarı tanıtmak']],
        ],
    ],
    5 => [
        'baslik' => 'Doğrulama ve yanlış bilgi',
        'kisa'   => 'İletileri ve görselleri sorgulama',
        'ozet'   => [
            'Yanlış bilginin türleri: abartma, bağlamdan koparma, yanlış atıf, uydurma.',
            'WhatsApp gruplarında ve sosyal medyada dolaşan iletiler nasıl sorgulanır?',
            'Tarih, kaynak ve yer kontrolü.',
        ],
        'atolye' => [
            'Yayımlanmış bir yerel haberi dayandığı bültenle karşılaştırın.',
            'Haberdeki her cümleyi bültende arayın: var, eksik, çarpıtılmış ya da yok.',
            'Bulduklarınızı grupça karşılaştırın: en sık hangi hata yapılmış?',
        ],
        'foto'   => [
            'baslik' => 'Fotoğraf 2 · Görsel doğrulama',
            'maddeler' => [
                'Tersine görsel arama: fotoğraf daha önce yayımlanmış mı?',
                'Fotoğraftaki ipuçları: tabela, hava, mevsim, gölge.',
                'Yapay zekâyla üretilmiş görsel haber fotoğrafı olarak kullanılamaz.',
            ],
        ],
        'odev'   => 'Dolaşımdaki bir iletiyi ya da fotoğrafı seç; doğru mu, yanlış mı, nasıl anladığını adım adım yaz.',
        'odev_turu' => 'haber',
        'canta'  => null,
        'arac'   => 'denetle',
        'ornekler' => ['ornek-su-kesintisi'],
        'sorular' => [
            ['Bir fotoğrafın daha önce yayımlanıp yayımlanmadığını nasıl anlarsın?', ['Paylaşan kişiye sorarak', 'Tersine görsel aramayla', 'Beğeni sayısına bakarak']],
            ['"İki yıl önceki duyurunun yeniden dolaşıma girmesi" hangi tür yanlış bilgidir?', ['Uydurma', 'Bağlamdan koparma', 'Yanlış atıf']],
        ],
    ],
    6 => [
        'baslik' => 'Editörlük, hukuk ve etik',
        'kisa'   => 'Yayın kararı ve sorumluluk',
        'ozet'   => [
            'Editörün görevleri: kısaltma, düzeltme, başlık, yayın kararı.',
            'Sosyal medyada paylaşanın sorumluluğu: hakaret, kişilik hakları, özel hayat.',
            'Masumiyet karinesi ve kişisel veriler.',
            'Halkı yanıltıcı bilgiyi alenen yayma suçu (TCK 217/A).',
            'Cevap ve düzeltme (tekzip) hakkı.',
        ],
        'atolye' => [
            'Bir arkadaşınızın haberini editör gözüyle okuyun.',
            'Gerçek paylaşım ve tekzip örneklerinde "bu cümle yayımlanır mı?" sorusunu tartışın.',
        ],
        'foto'   => [
            'baslik' => 'Fotoğraf 3 · Seçim, altyazı ve telif',
            'maddeler' => [
                'Üç fotoğraftan hangisi haberi en iyi anlatır? Seçim bir editörlük kararıdır.',
                'Altyazı: ne, nerede, ne zaman; fotoğrafı çekenin adı.',
                'İnternetten bulunan fotoğraf kullanılamaz; fotoğrafın da sahibi vardır.',
            ],
        ],
        'odev'   => 'Editör notlarına göre haberini ve fotoğraf altyazını düzeltip yeniden gönder.',
        'odev_turu' => 'haber',
        'canta'  => null,
        'arac'   => null,
        'ornekler' => [],
        'sorular' => [
            ['İnternette bulduğun bir fotoğrafı haberinde kullanabilir misin?', ['Evet, kaynak yazarsam', 'Hayır, sahibinin izni olmadan', 'Evet, haber amaçlıysa']],
            ['Bir şüpheliden haberde nasıl söz edilir?', ['Adıyla ve fotoğrafıyla', 'Baş harfleriyle, suçlu ilan etmeden', 'Hiç söz edilmez']],
        ],
    ],
    7 => [
        'baslik' => 'Yapay zekâ ve platformlar',
        'kisa'   => 'Bir haberi üç biçimde yazmak',
        'ozet'   => [
            'Yapay zekânın kullanılabileceği ve kullanılmaması gereken işler.',
            'Yapay zekâ kullanımını okura beyan etmek.',
            'WhatsApp, Instagram, X ve haber sitelerine yazarken farklar.',
        ],
        'atolye' => [
            'Haberinizi web, sosyal medya ve 60 saniyelik video metni olarak yeniden yazın.',
            'Final haberinizin her bilgisinin kaynağını kontrol edin.',
        ],
        'foto'   => [
            'baslik' => 'Fotoğraf 4 · Platforma göre görsel',
            'maddeler' => [
                'Yatay, kare, dikey: aynı fotoğrafı platforma göre kırpmak.',
                'Görme engelli okurlar için görsel açıklaması (alt metin) yazmak.',
            ],
        ],
        'odev'   => 'Final haberini fotoğrafı, altyazısı ve kaynaklarıyla tamamla.',
        'odev_turu' => 'haber',
        'canta'  => null,
        'arac'   => 'karsilastir',
        'ornekler' => [],
        'sorular' => [
            ['Yapay zekâ hangi işte kullanılmamalı?', ['Yazım denetimi', 'Kaynağı olmayan bilgi üretmek', 'Dökümü hızlandırmak']],
            ['Görsel açıklaması (alt metin) kimin için yazılır?', ['Arama motorları', 'Görme engelli okurlar', 'Editör']],
        ],
    ],
    8 => [
        'baslik' => 'Final: sunum ve yayın',
        'kisa'   => 'Haberini sun',
        'ozet'   => [
            'Her katılımcı haberini 5 dakikada sunar: konu, kaynaklar, en zor doğrulama anı.',
            'Akran ve eğitmen geri bildirimi.',
            'Yayına seçilen haberler.',
        ],
        'atolye' => [
            'Final haberinizi sunun.',
            'Arkadaşlarınızın haberlerine kaynak ve doğruluk açısından geri bildirim verin.',
        ],
        'foto'   => null,
        'odev'   => 'Final haberini gönder.',
        'odev_turu' => 'haber',
        'canta'  => null,
        'arac'   => null,
        'ornekler' => [],
        'sorular' => [
            ['Bu seminerde en çok işine yarayan hangisiydi?', ['Kaynak bulmak', 'Röportaj ve ses kaydı', 'Doğrulama', 'Fotoğraf']],
        ],
    ],
];
