/* SkillSwap Campus — comportements d'interface, sans dépendance externe. */
(function () {
  "use strict";

  const racine = document.documentElement;
  const CLE_THEME = "skillswap-theme";
  const metaTheme = document.getElementById("meta-theme");

  /* ---------- Thème clair / sombre ---------- */
  const boutonsTheme = Array.from(document.querySelectorAll("[data-theme-toggle]"));

  function themeCourant() {
    return racine.dataset.theme === "dark" ? "dark" : "light";
  }

  function appliquerTheme(theme, memoriser) {
    racine.dataset.theme = theme;
    racine.style.colorScheme = theme;
    if (metaTheme) metaTheme.setAttribute("content", theme === "dark" ? "#0f0e1a" : "#f4f2ec");
    if (memoriser) {
      try { localStorage.setItem(CLE_THEME, theme); } catch (e) {}
    }
    boutonsTheme.forEach(function (bouton) {
      const versClair = theme === "dark";
      bouton.setAttribute("aria-pressed", versClair ? "true" : "false");
      bouton.setAttribute("aria-label", versClair ? "Passer au thème clair" : "Passer au thème sombre");
      const libelle = bouton.querySelector(".theme-libelle");
      if (libelle) libelle.textContent = versClair ? "Thème clair" : "Thème sombre";
    });
  }

  boutonsTheme.forEach(function (bouton) {
    bouton.addEventListener("click", function () {
      appliquerTheme(themeCourant() === "dark" ? "light" : "dark", true);
    });
  });
  appliquerTheme(themeCourant(), false);

  window.addEventListener("storage", function (e) {
    if (e.key === CLE_THEME && (e.newValue === "light" || e.newValue === "dark")) {
      appliquerTheme(e.newValue, false);
    }
  });

  const preferenceSombre = window.matchMedia("(prefers-color-scheme: dark)");
  if (preferenceSombre.addEventListener) {
    preferenceSombre.addEventListener("change", function (e) {
      let choix = null;
      try { choix = localStorage.getItem(CLE_THEME); } catch (err) {}
      if (choix !== "light" && choix !== "dark") appliquerTheme(e.matches ? "dark" : "light", false);
    });
  }

  /* ---------- Menu mobile ---------- */
  const barre = document.getElementById("navigation-principale");
  const boutonOuvrir = document.querySelector("[data-menu-ouvrir]");
  const boutonsFermer = Array.from(document.querySelectorAll("[data-menu-fermer]"));
  const mobile = window.matchMedia("(max-width: 900px)");
  let dernierFocus = null;

  function estMobile() { return mobile.matches; }

  function focusables() {
    if (!barre) return [];
    return Array.from(barre.querySelectorAll("a[href], button:not([disabled]), input:not([disabled]), select, textarea, summary"))
      .filter(function (el) { return el.offsetParent !== null; });
  }

  function ouvrirMenu() {
    if (!barre || !estMobile()) return;
    dernierFocus = document.activeElement;
    document.body.classList.add("menu-ouvert");
    if ("inert" in barre) barre.inert = false;
    if (boutonOuvrir) boutonOuvrir.setAttribute("aria-expanded", "true");
    const fermer = barre.querySelector(".fermer-menu");
    if (fermer) fermer.focus();
  }

  function fermerMenu(rendreFocus) {
    if (!barre) return;
    document.body.classList.remove("menu-ouvert");
    if (boutonOuvrir) boutonOuvrir.setAttribute("aria-expanded", "false");
    if (estMobile()) {
      if ("inert" in barre) barre.inert = true;
      if (rendreFocus && dernierFocus && typeof dernierFocus.focus === "function") dernierFocus.focus();
    }
  }

  if (boutonOuvrir) boutonOuvrir.addEventListener("click", ouvrirMenu);
  boutonsFermer.forEach(function (bouton) {
    bouton.addEventListener("click", function () { fermerMenu(true); });
  });
  if (barre) {
    barre.querySelectorAll("a[href]").forEach(function (lien) {
      lien.addEventListener("click", function () { if (estMobile()) fermerMenu(false); });
    });
  }

  function adapterMenu() {
    if (!barre) return;
    if (estMobile()) {
      if (!document.body.classList.contains("menu-ouvert") && "inert" in barre) barre.inert = true;
      if (boutonOuvrir) boutonOuvrir.hidden = false;
    } else {
      document.body.classList.remove("menu-ouvert");
      if ("inert" in barre) barre.inert = false;
      if (boutonOuvrir) {
        boutonOuvrir.hidden = true;
        boutonOuvrir.setAttribute("aria-expanded", "false");
      }
    }
  }
  adapterMenu();
  if (mobile.addEventListener) mobile.addEventListener("change", adapterMenu);

  document.addEventListener("keydown", function (e) {
    if (!document.body.classList.contains("menu-ouvert")) return;
    if (e.key === "Escape") {
      e.preventDefault();
      fermerMenu(true);
      return;
    }
    if (e.key === "Tab") {
      const elements = focusables();
      if (!elements.length) return;
      const premier = elements[0];
      const dernier = elements[elements.length - 1];
      if (e.shiftKey && document.activeElement === premier) {
        e.preventDefault();
        dernier.focus();
      } else if (!e.shiftKey && document.activeElement === dernier) {
        e.preventDefault();
        premier.focus();
      }
    }
  });

  /* ---------- Validation lisible, champ par champ ---------- */
  function messageAttendu(champ) {
    if (champ.dataset.memeQue) {
      const source = document.getElementById(champ.dataset.memeQue);
      if (source && champ.value !== "" && champ.value !== source.value) {
        return champ.dataset.messageMeme || "Les deux valeurs ne correspondent pas.";
      }
    }
    if (champ.validity.valueMissing) return "Ce champ est obligatoire.";
    if (champ.validity.typeMismatch) {
      if (champ.type === "email") return "Saisissez une adresse électronique valide.";
      if (champ.type === "url") return "Saisissez une adresse complète commençant par http:// ou https://.";
      return "Le format saisi n’est pas valide.";
    }
    if (champ.validity.tooShort) return "Saisissez au moins " + champ.minLength + " caractères.";
    if (champ.validity.tooLong) return "Ne dépassez pas " + champ.maxLength + " caractères.";
    if (champ.validity.rangeUnderflow) return "La valeur minimale est " + champ.min + ".";
    if (champ.validity.rangeOverflow) return "La valeur maximale est " + champ.max + ".";
    if (champ.validity.patternMismatch) return champ.dataset.message || "Le format saisi n’est pas valide.";
    if (champ.validity.badInput) return "Saisissez une valeur valide.";
    return "";
  }

  function groupeDe(champ) {
    if (champ.type !== "radio") return [champ];
    const nom = champ.name;
    if (!nom) return [champ];
    return Array.from(champ.form ? champ.form.querySelectorAll('input[type="radio"][name="' + nom.replace(/"/g, '\\"') + '"]') : [champ]);
  }

  function estInvalide(champ) {
    if (champ.disabled || champ.type === "hidden") return "";
    if (champ.type === "radio") {
      const groupe = groupeDe(champ);
      if (groupe.some(function (r) { return r.required; }) && !groupe.some(function (r) { return r.checked; })) {
        return "Choisissez une option.";
      }
      return "";
    }
    if (champ.hasAttribute("data-mot-de-passe") && champ.value !== ""
      && (champ.value.length < 8 || !/[A-Za-z]/.test(champ.value) || !/\d/.test(champ.value))) {
      return "Le mot de passe doit contenir au moins 8 caractères, dont une lettre et un chiffre.";
    }
    if (champ.dataset.memeQue) {
      const message = messageAttendu(champ);
      if (message) return message;
    }
    if (!champ.checkValidity()) return messageAttendu(champ) || "Vérifiez cette valeur.";
    return "";
  }

  function zoneErreur(champ) {
    if (champ.type === "radio") return champ.closest("fieldset") || champ.closest(".champ");
    return champ.closest(".champ") || champ.parentElement;
  }

  function retirerErreur(champ) {
    const groupe = groupeDe(champ);
    const zones = new Set();
    const idErreur = (champ.id || champ.name) ? (champ.id || champ.name) + "-erreur-js" : "";
    groupe.forEach(function (el) {
      const zone = zoneErreur(el);
      if (zone) zones.add(zone);
    });
    const idsSupprimes = new Set(idErreur ? [idErreur] : []);
    zones.forEach(function (zone) {
      (zone.getAttribute("aria-describedby") || "").split(/\s+/).filter(Boolean).forEach(function (id) {
        const noeud = document.getElementById(id);
        if (noeud && noeud.classList.contains("erreur-champ")) {
          idsSupprimes.add(id);
          noeud.remove();
        }
      });
    });
    [champ].concat(groupe).concat(Array.from(zones)).forEach(function (el) {
      if (!el || !el.getAttribute) return;
      el.removeAttribute("aria-invalid");
      const ids = (el.getAttribute("aria-describedby") || "").split(/\s+/)
        .filter(function (id) { return id && !idsSupprimes.has(id); });
      if (ids.length) el.setAttribute("aria-describedby", ids.join(" "));
      else el.removeAttribute("aria-describedby");
    });
  }

  function afficherErreur(champ, message) {
    const groupe = groupeDe(champ);
    retirerErreur(champ);
    const identifiant = champ.id || champ.name || ("champ-" + Math.random().toString(36).slice(2, 8));
    if (!champ.id) champ.id = identifiant;
    const idErreur = identifiant + "-erreur-js";
    let erreur = document.getElementById(idErreur);
    if (!erreur) {
      erreur = document.createElement("p");
      erreur.className = "erreur-champ";
      erreur.id = idErreur;
      erreur.setAttribute("role", "alert");
      erreur.innerHTML = '<span class="erreur-champ-icone" aria-hidden="true">!</span><span></span>';
      const zone = zoneErreur(champ);
      if (zone) zone.appendChild(erreur);
    }
    const texte = erreur.querySelector("span:last-child");
    if (texte) texte.textContent = message;
    const zones = new Set();
    groupe.forEach(function (el) {
      const zone = zoneErreur(el);
      if (zone) zones.add(zone);
    });
    [champ].concat(groupe).concat(Array.from(zones)).forEach(function (el) {
      if (!el || !el.setAttribute) return;
      el.setAttribute("aria-invalid", "true");
      const ids = (el.getAttribute("aria-describedby") || "").split(/\s+/).filter(Boolean);
      if (ids.indexOf(idErreur) === -1) ids.push(idErreur);
      el.setAttribute("aria-describedby", ids.join(" "));
    });
  }

  function verifierChamp(champ, afficher) {
    const message = estInvalide(champ);
    if (message) {
      if (afficher) afficherErreur(champ, message);
      return false;
    }
    if (afficher) retirerErreur(champ);
    return true;
  }

  function champsUniques(form) {
    const vus = new Set();
    return Array.from(form.querySelectorAll("input, select, textarea")).filter(function (champ) {
      if (champ.disabled || champ.type === "hidden" || !zoneErreur(champ)) return false;
      if (champ.type === "radio") {
        const cle = "radio:" + champ.name;
        if (vus.has(cle)) return false;
        vus.add(cle);
      }
      return true;
    });
  }

  document.querySelectorAll("form").forEach(function (form) {
    if ((form.method || "get").toLowerCase() !== "post" || form.hasAttribute("data-sans-validation")) return;
    form.noValidate = true;

    form.addEventListener("submit", function (e) {
      let premierInvalide = null;
      champsUniques(form).forEach(function (champ) {
        if (!verifierChamp(champ, true) && !premierInvalide) premierInvalide = champ;
      });
      if (premierInvalide) {
        e.preventDefault();
        e.stopImmediatePropagation();
        if (premierInvalide.type === "radio") {
          const groupe = groupeDe(premierInvalide);
          (groupe[0] || premierInvalide).focus();
        } else {
          premierInvalide.focus();
        }
        return;
      }
      if (form.dataset.envoye) {
        e.preventDefault();
        return;
      }
      form.dataset.envoye = "1";
      form.querySelectorAll("button[type=submit], button:not([type])").forEach(function (bouton) {
        bouton.classList.add("occupe");
        window.setTimeout(function () { bouton.disabled = true; }, 0);
      });
    }, true);

    form.addEventListener("focusout", function (e) {
      const champ = e.target;
      if (!champ.matches("input, select, textarea")) return;
      if (champ.type === "hidden" || champ.disabled) return;
      verifierChamp(champ, true);
    });

    form.addEventListener("input", function (e) {
      const champ = e.target;
      if (!champ.matches("input, select, textarea")) return;
      if (champ.getAttribute("aria-invalid") === "true" || (champ.dataset.memeQue && champ.value !== "")) {
        verifierChamp(champ, true);
      }
      if (champ.dataset.memeQue) {
        const confirmation = form.querySelector('[data-meme-que="' + champ.id + '"]');
        if (confirmation && confirmation.getAttribute("aria-invalid") === "true") verifierChamp(confirmation, true);
      }
    });

    form.addEventListener("change", function (e) {
      const champ = e.target;
      if (champ.matches("input, select, textarea")) verifierChamp(champ, true);
      if (champ.type === "radio" && champ.name) {
        const groupe = groupeDe(champ);
        if (groupe.some(function (el) { return el.getAttribute("aria-invalid") === "true"; })) {
          verifierChamp(champ, true);
        }
      }
    });
  });

  /* ---------- Anti double clic hors formulaires validés ---------- */
  document.addEventListener("submit", function (e) {
    const form = e.target;
    if (e.defaultPrevented || form.dataset.envoye) return;
    form.dataset.envoye = "1";
    form.querySelectorAll("button[type=submit], button:not([type])").forEach(function (bouton) {
      bouton.classList.add("occupe");
      window.setTimeout(function () { bouton.disabled = true; }, 0);
    });
  });

  /* ---------- Confirmations explicites ---------- */
  document.addEventListener("click", function (e) {
    const bouton = e.target.closest("[data-confirmer]");
    if (bouton && !window.confirm(bouton.dataset.confirmer)) e.preventDefault();
  });

  /* ---------- Créneau : calcul de l'heure de fin ---------- */
  document.querySelectorAll("[data-duree]").forEach(function (form) {
    const debut = form.querySelector("[name=debut]");
    const fin = form.querySelector("[name=fin]");
    const heures = parseInt(form.dataset.duree, 10);
    if (!debut || !fin || !heures) return;
    debut.addEventListener("change", function () {
      if (!debut.value) return;
      const date = new Date(debut.value);
      date.setHours(date.getHours() + heures);
      const deuxChiffres = function (n) { return String(n).padStart(2, "0"); };
      fin.value = date.getFullYear() + "-" + deuxChiffres(date.getMonth() + 1) + "-" + deuxChiffres(date.getDate())
        + "T" + deuxChiffres(date.getHours()) + ":" + deuxChiffres(date.getMinutes());
    });
  });

  /* ---------- Notation par étoiles accessible ---------- */
  document.querySelectorAll(".notation").forEach(function (zone) {
    const sortie = zone.querySelector("output");
    zone.addEventListener("change", function (e) {
      const libelles = ["", "Décevant", "Moyen", "Bien", "Très bien", "Excellent"];
      if (sortie) sortie.textContent = libelles[e.target.value] || "";
    });
  });

  /* ---------- Accueil : masquer le guide une fois terminé ou fermé ---------- */
  document.querySelectorAll("[data-onboarding]").forEach(function (zone) {
    const cle = "skillswap-onboarding-vu-" + zone.dataset.onboarding;
    let dejaVu = false;
    try { dejaVu = localStorage.getItem(cle) === "1"; } catch (e) {}
    if (dejaVu) {
      zone.hidden = true;
      return;
    }
    const fermer = zone.querySelector("[data-fermer-onboarding]");
    if (fermer) {
      fermer.hidden = false;
      fermer.addEventListener("click", function () {
        zone.hidden = true;
        try { localStorage.setItem(cle, "1"); } catch (e) {}
      });
    }
  });

  /* ---------- Apparition douce des blocs de l'accueil public ---------- */
  const blocsRevelables = Array.from(document.querySelectorAll(
    ".accueil-intro, .accueil-principe, .accueil-chiffres, .accueil-etape, .accueil-bonus, .accueil-pratique-principal, .accueil-carte"
  ));
  if (blocsRevelables.length && !window.matchMedia("(prefers-reduced-motion: reduce)").matches) {
    try {
      blocsRevelables.forEach(function (bloc, index) {
        bloc.classList.add("a-reveler");
        bloc.style.transitionDelay = Math.min(index * 35, 210) + "ms";
      });
      function revelerBloc(bloc) {
        bloc.classList.add("est-revele");
        window.setTimeout(function () {
          bloc.classList.remove("a-reveler", "est-revele");
          bloc.style.transitionDelay = "";
        }, 950);
      }
      if ("IntersectionObserver" in window) {
        const observateur = new IntersectionObserver(function (entrees) {
          entrees.forEach(function (entree) {
            if (!entree.isIntersecting) return;
            revelerBloc(entree.target);
            observateur.unobserve(entree.target);
          });
        }, { rootMargin: "0px 0px -6% 0px", threshold: .08 });
        blocsRevelables.forEach(function (bloc) {
          if (bloc.getBoundingClientRect().top < window.innerHeight * .96) {
            window.requestAnimationFrame(function () { revelerBloc(bloc); });
          } else {
            observateur.observe(bloc);
          }
        });
      } else {
        blocsRevelables.forEach(revelerBloc);
      }
    } catch (e) {
      blocsRevelables.forEach(function (bloc) { bloc.classList.add("est-revele"); });
    }
  }

  /* ---------- Installation PWA : enregistrement du service worker ---------- */
  const baseApplication = document.body.dataset.base || "/";
  if ("serviceWorker" in navigator
      && (location.protocol === "https:" || location.hostname === "localhost" || location.hostname === "127.0.0.1")) {
    window.addEventListener("load", function () {
      navigator.serviceWorker.register(baseApplication + "sw.js", { scope: baseApplication })
        .catch(function () { /* L'application reste utilisable sans service worker. */ });
    });
  }
})();
