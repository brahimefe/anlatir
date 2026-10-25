<?php
/*
 * ANLATIR SEMİNER — AYARLAR
 * Şifre ve API anahtarı BU DOSYADA DEĞİL, sunucudaki config.local.php dosyasında durur
 * (örnek: config.local.ornek.php). config.local.php depoya girmez; bu dosyadaki her ayarı
 * orada yeniden yazarak değiştirebilirsiniz. İki dosya da tarayıcıdan açılamaz (.htaccess korur).
 */
$ayar = [
    // 1) Eğitmen paneli şifresi — en az 12 karakter, başka yerde kullanmadığınız bir şifre
    'egitmen_sifre'    => '',   // config.local.php içinde tanımlanır

    // 2) Yapay zekâ araçları: false = kapalı (Haber Masası ve otomatik dipnot kontrolü görünmez)
    //    Açmak için true yapın ve aşağıya OpenAI API anahtarınızı yazın.
    'yapay_zeka'       => false,
    'openai_api_key'   => '',   // config.local.php içinde tanımlanır (sk-... ile başlar)

    // --- İsterseniz değiştirebilirsiniz ---
    'egitmen_adi'      => 'Prof. Dr. İbrahim Efe',
    'egitmen_rumuz'    => 'Yoka Rtık',
    'model_text'       => 'gpt-4o-mini',   // metin araçları
    'model_audio'      => 'whisper-1',     // ses kaydını yazıya dökme

    'daily_text_limit'  => 20,   // öğrenci başına günlük metin aracı kullanımı
    'daily_audio_limit' => 5,    // öğrenci başına günlük ses dökümü
    'daily_submit_limit'=> 10,   // öğrenci başına günlük gönderim
    'max_audio_mb'      => 25,
    'max_photo_mb'      => 10,
    'max_slides_mb'     => 40,
    'max_chars'         => 12000,

    // Kur kapandıktan kaç gün sonra hesaplar ve yayımlanmamış gönderiler silinsin (KVKK)
    'saklama_gun'       => 60,
];

if (is_file(__DIR__ . '/config.local.php')) {
    $ayar = array_replace($ayar, (array)require __DIR__ . '/config.local.php');
}
return $ayar;
