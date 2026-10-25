/* Eğitmen paneli: kaydedilmemiş değişiklik uyarısı, canlı soru satırı ekleme/silme */
(function () {
  "use strict";
  var kirli = null;
  document.querySelectorAll("form[data-izle]").forEach(function (f) {
    function isaretle() {
      kirli = f;
      var d = f.querySelector("[data-durum]");
      if (d) { d.textContent = "● Kaydedilmemiş değişiklik var"; d.classList.add("kirli"); }
    }
    f.addEventListener("input", isaretle);
    f.addEventListener("change", isaretle);
    f.addEventListener("submit", function () { kirli = null; });
  });
  window.addEventListener("beforeunload", function (e) {
    if (kirli) { e.preventDefault(); e.returnValue = ""; }
  });

  var liste = document.getElementById("soru-listesi"), sablon = document.getElementById("soru-sablon");
  if (liste && sablon) {
    document.querySelector("[data-soru-ekle]").addEventListener("click", function () {
      var i = parseInt(liste.getAttribute("data-sonraki"), 10) || 0;
      liste.setAttribute("data-sonraki", i + 1);
      var div = document.createElement("div");
      div.innerHTML = sablon.innerHTML.replace(/__i__/g, "y" + i);
      var satir = div.firstElementChild;
      liste.appendChild(satir);
      satir.querySelector("input").focus();
      liste.closest("form").dispatchEvent(new Event("input"));
    });
    liste.addEventListener("click", function (ev) {
      var b = ev.target.closest("[data-soru-sil]");
      if (!b) return;
      b.closest(".soru-satir").remove();
      liste.closest("form").dispatchEvent(new Event("input"));
    });
  }
})();
