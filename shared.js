/* Al-Falah Marketing — shared front-end */

function getSavedLang() {
  try {
    var s = localStorage.getItem("alfalah-lang");
    if (s === "fr" || s === "en") return s;
  } catch (e) {}
  return "en";
}

function applyLangToDocument(lang) {
  if (lang !== "fr" && lang !== "en") lang = "en";
  if (document.body) document.body.setAttribute("data-lang", lang);
  if (document.documentElement) document.documentElement.setAttribute("lang", lang);
  try {
    localStorage.setItem("alfalah-lang", lang);
  } catch (e) {}
  return lang;
}

function updateLangButtons(lang) {
  var en = document.getElementById("btn-en");
  var fr = document.getElementById("btn-fr");
  var active =
    "bg-mint-500 text-white transition min-h-[48px] min-w-[48px] inline-flex items-center justify-center px-3 rounded-md";
  var idle =
    "text-slate-400 hover:text-white transition min-h-[48px] min-w-[48px] inline-flex items-center justify-center px-3 rounded-md";
  if (en) {
    en.className = "px-2.5 py-1.5 " + (lang === "en" ? active : idle);
    en.setAttribute("aria-pressed", lang === "en" ? "true" : "false");
  }
  if (fr) {
    fr.className = "px-2.5 py-1.5 " + (lang === "fr" ? active : idle);
    fr.setAttribute("aria-pressed", lang === "fr" ? "true" : "false");
  }
  // Also style any secondary toggles
  document.querySelectorAll("[data-lang-btn]").forEach(function (btn) {
    var l = btn.getAttribute("data-lang-btn");
    btn.setAttribute("aria-pressed", l === lang ? "true" : "false");
    if (l === lang) {
      btn.classList.add("bg-mint-500", "text-white");
      btn.classList.remove("text-slate-400");
    } else {
      btn.classList.remove("bg-mint-500", "text-white");
      btn.classList.add("text-slate-400");
    }
  });
}

function updatePlaceholders(lang) {
  document.querySelectorAll("[data-placeholder-en]").forEach(function (el) {
    el.placeholder =
      lang === "fr"
        ? el.getAttribute("data-placeholder-fr") || el.getAttribute("data-placeholder-en")
        : el.getAttribute("data-placeholder-en");
  });
}

function updateSeo(lang) {
  var titleEn = document.querySelector('meta[name="title-en"]');
  var titleFr = document.querySelector('meta[name="title-fr"]');
  if (titleEn && titleFr) {
    document.title = lang === "fr" ? titleFr.content : titleEn.content;
  }
  var descEn = document.querySelector('meta[name="description-en"]');
  var descFr = document.querySelector('meta[name="description-fr"]');
  var desc = document.querySelector('meta[name="description"]');
  if (desc && descEn && descFr) {
    desc.setAttribute("content", lang === "fr" ? descFr.content : descEn.content);
  }
}

function setLang(lang) {
  lang = applyLangToDocument(lang);
  updateLangButtons(lang);
  updatePlaceholders(lang);
  updateSeo(lang);
  // Notify page scripts if any
  try {
    document.dispatchEvent(new CustomEvent("alfalah:lang", { detail: { lang: lang } }));
  } catch (e) {}
}

// Apply saved language as early as possible once body exists
(function initLang() {
  function run() {
    var lang = getSavedLang();
    applyLangToDocument(lang);
    updateLangButtons(lang);
    updatePlaceholders(lang);
    updateSeo(lang);
  }
  if (document.body) run();
  else document.addEventListener("DOMContentLoaded", run);
  document.addEventListener("DOMContentLoaded", function () {
    // Re-sync after full DOM (buttons may render late)
    var lang = getSavedLang();
    applyLangToDocument(lang);
    updateLangButtons(lang);
    updatePlaceholders(lang);
    updateSeo(lang);
  });
})();


function apiBase() {
  const path = window.location.pathname || "";
  if (path.indexOf("/blog/") !== -1 || path.indexOf("/services/") !== -1) return "../api";
  return "api";
}

function formVal(form, ...names) {
  for (const n of names) {
    if (form[n] && form[n].value) return form[n].value.trim();
  }
  return "";
}

async function submitNewsletter(e) {
  e.preventDefault();
  const form = e.target;
  const email = formVal(form, "email", "EMAIL");
  const lang = document.body.getAttribute("data-lang") || "en";
  const btn = form.querySelector('button[type="submit"]');
  if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
    alert(lang === "fr" ? "Veuillez entrer un email valide." : "Please enter a valid email.");
    return false;
  }
  if (btn) {
    btn.disabled = true;
    btn.dataset.prevText = btn.innerHTML;
  }

  const cfg = window.ALFALAH_MAILCHIMP || {};
  const action = (cfg.actionUrl || "").trim();

  function showSuccess(msg) {
    alert(
      msg ||
        (lang === "fr"
          ? "Merci ! Verifiez votre boite mail pour confirmer l'inscription (double opt-in)."
          : "Thank you! Check your inbox to confirm your subscription (double opt-in).")
    );
    form.reset();
  }
  function showError(msg) {
    alert(
      msg ||
        (lang === "fr"
          ? "Impossible de s'inscrire pour le moment. Reessayez ou contactez-nous."
          : "Unable to subscribe right now. Please try again or contact us.")
    );
  }
  function restoreBtn() {
    if (btn) {
      btn.disabled = false;
      if (btn.dataset.prevText) btn.innerHTML = btn.dataset.prevText;
    }
  }

  // Primary: Mailchimp JSONP (static site safe — no API key in browser)
  if (action && action.indexOf("list-manage.com") !== -1) {
    try {
      var jsonUrl = action
        .replace("/subscribe/post?", "/subscribe/post-json?")
        .replace("/subscribe/post&", "/subscribe/post-json&");
      if (jsonUrl.indexOf("post-json") === -1) {
        jsonUrl = action.replace("/post?", "/post-json?");
      }
      var u = new URL(jsonUrl);
      u.searchParams.set("EMAIL", email);
      var cb = "mc_cb_" + Date.now();
      u.searchParams.set("c", cb);

      await new Promise(function (resolve) {
        var settled = false;
        function done() {
          if (settled) return;
          settled = true;
          restoreBtn();
          resolve();
        }
        window[cb] = function (data) {
          try { delete window[cb]; } catch (err) {}
          if (script && script.parentNode) script.parentNode.removeChild(script);
          var msg = (data && (data.msg || data.detail)) || "";
          var result = (data && data.result) || "";
          if (result === "success" || /thank you|confirm|almost finished|check your email/i.test(msg)) {
            showSuccess(
              lang === "fr"
                ? "Merci ! Si la confirmation est activee, verifiez votre email pour valider l'inscription."
                : "Thank you! If confirmation is enabled, check your email to complete signup."
            );
          } else if (/already subscribed|is already/i.test(msg)) {
            showSuccess(
              lang === "fr" ? "Cet email est deja inscrit. Merci !" : "This email is already subscribed. Thank you!"
            );
          } else {
            showError(msg || null);
          }
          done();
        };
        var script = document.createElement("script");
        script.src = u.toString();
        script.onerror = function () {
          showError(null);
          done();
        };
        document.body.appendChild(script);
        setTimeout(function () {
          if (window[cb]) {
            try { delete window[cb]; } catch (err) {}
            done();
          }
        }, 12000);
      });
      return false;
    } catch (err) {
      console.warn("Mailchimp error", err);
      showError(null);
      restoreBtn();
      return false;
    }
  }

  // Fallback: local PHP API if Mailchimp not configured
  try {
    const res = await fetch(apiBase() + "/newsletter.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ email: email, lang: lang }),
    });
    const data = await res.json().catch(function () {
      return { ok: false };
    });
    if (data.ok) showSuccess(data.message);
    else {
      showError(
        lang === "fr"
          ? "Mailchimp n'est pas encore configure. Ajoutez actionUrl dans shared.js."
          : "Mailchimp is not configured yet. Add actionUrl in shared.js."
      );
    }
  } catch (err) {
    showError(
      lang === "fr"
        ? "Mailchimp n'est pas encore configure. Ajoutez actionUrl dans shared.js."
        : "Mailchimp is not configured yet. Add actionUrl in shared.js."
    );
  }
  restoreBtn();
  return false;
}

async function submitContact(e) {
  e.preventDefault();
  const form = e.target;
  const lang = document.body.getAttribute("data-lang") || "en";
  const btn = form.querySelector('button[type="submit"]');
  if (btn) btn.disabled = true;

  const first = formVal(form, "firstName", "first_name");
  const last = formVal(form, "lastName", "last_name");
  const name = formVal(form, "name") || (first + " " + last).trim();
  const email = formVal(form, "email");
  const phone = formVal(form, "phone");
  const message = formVal(form, "message");
  const source = form.getAttribute("data-source") || "contact";

  const payload = { name: name, email: email, phone: phone, message: message, source: source };

  const waText = encodeURIComponent(
    "Hello Al-Falah Marketing,\n\nName: " +
      name +
      "\nEmail: " +
      email +
      "\nPhone: " +
      (phone || "N/A") +
      "\n\nProject:\n" +
      (message || "N/A")
  );
  const waUrl = "https://wa.me/224611351302?text=" + waText;

  try {
    const res = await fetch(apiBase() + "/contact.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(payload),
    });
    const data = await res.json().catch(function () {
      return { ok: false };
    });
    if (data.ok && data.whatsapp_url) {
      const openWa = confirm(
        lang === "fr"
          ? "Message enregistré ! Ouvrir WhatsApp pour finaliser ?"
          : "Message saved! Open WhatsApp to finish?"
      );
      if (openWa) window.open(data.whatsapp_url, "_blank");
      form.reset();
    } else {
      window.open(data.whatsapp_url || waUrl, "_blank");
      form.reset();
    }
  } catch (err) {
    window.open(waUrl, "_blank");
    form.reset();
  }
  if (btn) btn.disabled = false;
  return false;
}

function submitConsult(e) {
  return submitContact(e);
}

function toggleMobileMenu(forceClose) {
  const m = document.getElementById("mobile-menu");
  const btn = document.getElementById("mobile-menu-btn") || document.querySelector('[onclick*="toggleMobileMenu"]');
  if (!m) return;
  const willOpen = forceClose === true ? false : m.classList.contains("hidden");
  if (forceClose === true) {
    m.classList.add("hidden");
  } else {
    m.classList.toggle("hidden");
  }
  const isOpen = !m.classList.contains("hidden");
  document.body.classList.toggle("mobile-nav-open", isOpen);
  document.body.style.overflow = isOpen ? "hidden" : "";
  if (btn) {
    btn.setAttribute("aria-expanded", isOpen ? "true" : "false");
    const openIcon = btn.querySelector(".menu-icon-open");
    const closeIcon = btn.querySelector(".menu-icon-close");
    if (openIcon && closeIcon) {
      openIcon.classList.toggle("hidden", isOpen);
      closeIcon.classList.toggle("hidden", !isOpen);
    }
  }
  if (window.lucide) {
    try { lucide.createIcons(); } catch (e) {}
  }
}

function toggleAccordion(id) {
  const content = document.getElementById(id);
  const icon = document.getElementById(id + "-icon");
  if (!content) return;
  const isOpen = !content.classList.contains("hidden");
  document.querySelectorAll("[data-accordion]").forEach(function (el) {
    el.classList.add("hidden");
  });
  document.querySelectorAll("[data-accordion-icon]").forEach(function (el) {
    el.style.transform = "rotate(0deg)";
  });
  if (!isOpen) {
    content.classList.remove("hidden");
    if (icon) icon.style.transform = "rotate(180deg)";
  }
}

function filterPortfolio(cat) {
  document.querySelectorAll("[data-filter-btn]").forEach(function (btn) {
    btn.classList.remove("bg-mint-500", "text-white");
    btn.classList.add("border", "border-dark-600");
  });
  const active = document.querySelector('[data-filter-btn="' + cat + '"]');
  if (active) {
    active.classList.add("bg-mint-500", "text-white");
    active.classList.remove("border", "border-dark-600");
  }
  document.querySelectorAll("[data-portfolio-item]").forEach(function (item) {
    const itemCat = item.getAttribute("data-portfolio-item");
    if (cat === "all" || itemCat === cat) item.classList.remove("hidden");
    else item.classList.add("hidden");
  });
}

function acceptCookies() {
  try {
    localStorage.setItem("alfalah-cookies", "1");
  } catch (e) {}
  const b = document.getElementById("cookie-banner");
  if (b) b.classList.add("hidden-banner");
}

function rejectCookies() {
  try {
    localStorage.setItem("alfalah-cookies", "0");
  } catch (e) {}
  const b = document.getElementById("cookie-banner");
  if (b) b.classList.add("hidden-banner");
}

function openModal(name) {
  const el = document.getElementById("modal-" + name);
  if (el) {
    el.classList.remove("hidden");
    document.body.classList.add("modal-open");
  }
  if (window.lucide) lucide.createIcons();
}

function closeModal(name) {
  const el = document.getElementById("modal-" + name);
  if (el) el.classList.add("hidden");
  document.body.classList.remove("modal-open");
}

document.addEventListener("DOMContentLoaded", function () {
  try {
    if (localStorage.getItem("alfalah-cookies") !== null) {
      const b = document.getElementById("cookie-banner");
      if (b) b.classList.add("hidden-banner");
    }
  } catch (e) {}
  if (window.lucide) {
    try {
      lucide.createIcons();
    } catch (e) {}
  }
  const mobile = document.getElementById("mobile-menu");
  if (mobile) {
    mobile.querySelectorAll("a").forEach(function (a) {
      a.addEventListener("click", function () {
        if (typeof toggleMobileMenu === "function") toggleMobileMenu(true);
        else mobile.classList.add("hidden");
      });
    });
  }
  // Close mobile menu on resize to desktop
  window.addEventListener("resize", function () {
    if (window.innerWidth >= 1024 && typeof toggleMobileMenu === "function") {
      toggleMobileMenu(true);
    }
  });
  // Escape closes mobile menu
  document.addEventListener("keydown", function (e) {
    if (e.key === "Escape" && typeof toggleMobileMenu === "function") {
      toggleMobileMenu(true);
    }
  });
});
