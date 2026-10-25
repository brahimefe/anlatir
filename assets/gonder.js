/* Haber gönderme formu */
(function () {
  "use strict";
  var form = document.getElementById("gonder-form");
  if (!form) return;
  var rows = document.getElementById("src-rows");

  function addRow(bilgi, kaynak) {
    var div = document.createElement("div");
    div.className = "src-row";
    div.innerHTML = '<input type="text" name="bilgi[]" placeholder="Bilgi" aria-label="Bilgi">' +
      '<input type="text" name="kaynak[]" placeholder="Kaynak" aria-label="Kaynak">' +
      '<button type="button" class="btn btn-sm" data-sil aria-label="Satırı sil">×</button>';
    div.children[0].value = bilgi || "";
    div.children[1].value = kaynak || "";
    rows.appendChild(div);
    return div;
  }
  document.getElementById("src-ekle").addEventListener("click", function () {
    addRow().children[0].focus();
  });
  rows.addEventListener("click", function (ev) {
    var b = ev.target.closest("[data-sil]");
    if (!b) return;
    var row = b.closest(".src-row");
    if (rows.children.length > 1) row.remove();
    else row.querySelectorAll("input").forEach(function (i) { i.value = ""; });
  });

  /* Ödev türünü seçilen hafta belirler: yazılı ödev ya da haber */
  var hafta = document.getElementById("hafta"), bilgi = document.getElementById("tur-bilgi"), bas = document.getElementById("gonder-baslik");
  function syncTur() {
    var tur = hafta.options[hafta.selectedIndex].getAttribute("data-tur") || "haber";
    form.querySelectorAll(".only-haber").forEach(function (el) { el.hidden = tur !== "haber"; });
    if (bilgi) bilgi.textContent = bilgi.getAttribute("data-" + tur);
    if (bas && bas.textContent !== "Gönderini düzelt") bas.textContent = bas.getAttribute("data-" + tur);
  }
  hafta.addEventListener("change", syncTur);
  syncTur();

  /* Fotoğraf önizleme */
  var foto = document.getElementById("foto"), on = document.getElementById("foto-onizleme");
  foto.addEventListener("change", function () {
    var f = foto.files && foto.files[0];
    if (f && /^image\/(jpeg|png|webp)$/.test(f.type)) { on.src = URL.createObjectURL(f); on.classList.remove("hidden"); }
    else { on.classList.add("hidden"); }
  });

  /* Haber Masası'ndan gelen taslak */
  if (form.getAttribute("data-taslak") === "1") {
    var d = null;
    try { d = JSON.parse(sessionStorage.getItem("wd-taslak") || "null"); sessionStorage.removeItem("wd-taslak"); } catch (e) {}
    if (d) {
      ["baslik", "spot", "metin"].forEach(function (k) {
        var f = form.querySelector("[name=" + k + "]");
        if (f && !f.value && d[k]) f.value = d[k];
      });
      if (Array.isArray(d.kaynaklar) && d.kaynaklar.length) {
        rows.innerHTML = "";
        d.kaynaklar.forEach(function (k) { addRow(k.bilgi, k.kaynak); });
      }
    }
  }

  /* Yapay zekâ beyanı */
  var yzAlan = document.getElementById("yz-aciklama-alan");
  form.querySelectorAll('input[name="yz"]').forEach(function (r) {
    r.addEventListener("change", function () { yzAlan.hidden = !(r.value === "evet" && r.checked); });
  });

  form.addEventListener("submit", function (ev) {
    var b = ev.submitter;
    if (!b) return;
    // düğmenin değeri formla gitsin diye devre dışı bırakmadan önce gizli alana kopyalanır
    var h = document.createElement("input");
    h.type = "hidden"; h.name = b.name; h.value = b.value; form.appendChild(h);
    setTimeout(function () { form.querySelectorAll("button[type=submit]").forEach(function (x) { x.disabled = true; }); }, 0);
    b.textContent = b.value === "taslak" ? "Kaydediliyor…" : "Gönderiliyor…";
  });
})();
