/* SkillSwap Campus — service worker minimal.
   Il ne met en cache que la page de secours et les ressources publiques.
   Les pages authentifiées et les réponses PHP ne sont jamais conservées. */
"use strict";

const VERSION_CACHE = "skillswap-public-v3";
const BASE = new URL("./", self.location).pathname;
const PAGE_HORS_LIGNE = BASE + "offline.html";
const RESSOURCES_PUBLIQUES = [
  PAGE_HORS_LIGNE,
  BASE + "manifest.webmanifest",
  BASE + "css/skillswap.css",
  BASE + "js/skillswap.js",
  BASE + "icons/icon-192.png",
  BASE + "icons/icon-512.png",
  BASE + "icons/icon-maskable-512.png",
  BASE + "icons/apple-touch-icon.png",
  BASE + "icons/favicon-32.png"
];

function estRessourcePublique(chemin) {
  return chemin === PAGE_HORS_LIGNE
    || chemin === BASE + "manifest.webmanifest"
    || chemin.startsWith(BASE + "css/")
    || chemin.startsWith(BASE + "js/")
    || chemin.startsWith(BASE + "icons/");
}

self.addEventListener("install", function (event) {
  event.waitUntil((async function () {
    const cache = await caches.open(VERSION_CACHE);
    await Promise.all(RESSOURCES_PUBLIQUES.map(async function (url) {
      try {
        const reponse = await fetch(url, { cache: "reload" });
        if (reponse.ok) await cache.put(url, reponse);
      } catch (erreur) {
        /* Une ressource manquante ne doit pas empêcher l'installation. */
      }
    }));
    await self.skipWaiting();
  })());
});

self.addEventListener("activate", function (event) {
  event.waitUntil((async function () {
    const cachesConnues = await caches.keys();
    await Promise.all(cachesConnues
      .filter(function (nom) { return nom.indexOf("skillswap-") === 0 && nom !== VERSION_CACHE; })
      .map(function (nom) { return caches.delete(nom); }));
    await self.clients.claim();
  })());
});

self.addEventListener("fetch", function (event) {
  const requete = event.request;
  if (requete.method !== "GET") return;

  const url = new URL(requete.url);
  if (url.origin !== self.location.origin) return;

  /* Navigation : toujours le réseau. En cas de panne, seule la page de
     secours statique est renvoyée — jamais une page connectée. */
  if (requete.mode === "navigate" || requete.destination === "document") {
    event.respondWith((async function () {
      try {
        return await fetch(requete);
      } catch (erreur) {
        const cache = await caches.open(VERSION_CACHE);
        const secours = await cache.match(PAGE_HORS_LIGNE);
        return secours || new Response(
          "<!doctype html><html lang=\"fr\"><meta charset=\"utf-8\"><title>Hors ligne</title><p>Vous êtes hors ligne.</p></html>",
          { status: 503, headers: { "Content-Type": "text/html; charset=utf-8" } }
        );
      }
    })());
    return;
  }

  if (!estRessourcePublique(url.pathname)) return;

  event.respondWith((async function () {
    const cache = await caches.open(VERSION_CACHE);
    const dejaEnCache = await cache.match(requete, { ignoreSearch: true });
    if (dejaEnCache) return dejaEnCache;
    try {
      const reponse = await fetch(requete);
      if (reponse.ok) await cache.put(requete, reponse.clone());
      return reponse;
    } catch (erreur) {
      return new Response("Ressource indisponible hors ligne.", {
        status: 504,
        headers: { "Content-Type": "text/plain; charset=utf-8" }
      });
    }
  })());
});
