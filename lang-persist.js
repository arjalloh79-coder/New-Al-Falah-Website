/* Al-Falah Marketing — language persistence (standalone, loads after shared.js) */
(function (w, d) {
  "use strict";
  var KEY = "alfalah-lang";

  function getLang() {
    try {
      var s = localStorage.getItem(KEY);
      if (s === "fr" || s === "en") return s;
    } catch (e) {}
    return "en";
  }

  function apply(lang) {
    if (lang !== "fr" && lang !== "en") lang = "en";
    if (d.body) d.body.setAttribute("data-lang", lang);
    if (d.documentElement) d.documentElement.setAttribute("lang", lang);
    try {
      localStorage.setItem(KEY, lang);
    } catch (e) {}
    var en = d.getElementById("btn-en");
    var fr = d.getElementById("btn-fr");
    var on =
      "px-2.5 py-1.5 bg-mint-500 text-white transition min-h-[48px] min-w-[48px] inline-flex items-center justify-center px-3 rounded-md";
    var off =
      "px-2.5 py-1.5 text-slate-400 hover:text-white transition min-h-[48px] min-w-[48px] inline-flex items-center justify-center px-3 rounded-md";
    if (en) {
      en.className = lang === "en" ? on : off;
      en.setAttribute("aria-pressed", lang === "en" ? "true" : "false");
    }
    if (fr) {
      fr.className = lang === "fr" ? on : off;
      fr.setAttribute("aria-pressed", lang === "fr" ? "true" : "false");
    }
    d.querySelectorAll("[data-placeholder-en]").forEach(function (el) {
      el.placeholder =
        lang === "fr"
          ? el.getAttribute("data-placeholder-fr") || el.getAttribute("data-placeholder-en")
          : el.getAttribute("data-placeholder-en");
    });
    var te = d.querySelector('meta[name="title-en"]');
    var tf = d.querySelector('meta[name="title-fr"]');
    if (te && tf) d.title = lang === "fr" ? tf.content : te.content;
    return lang;
  }

  // Override global setLang used by onclick handlers
  w.setLang = function (lang) {
    apply(lang);
  };

  function boot() {
    apply(getLang());
  }

  if (d.readyState === "loading") {
    d.addEventListener("DOMContentLoaded", boot);
  } else {
    boot();
  }
  // Re-apply shortly after in case other scripts reset body attrs
  setTimeout(boot, 50);
  setTimeout(boot, 300);
})(window, document);
