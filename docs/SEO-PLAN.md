# SEO-PLAN.md — Wotoyi : SEO, GEO, SEO local, SEA

> Auteur : [SEO-GEO] · Date : 2026-11-16 · Statut : proposition, **en attente de validation CEO**.
> Légende : ✅ vérifié (source ou code lu) · ⚠ hypothèse / non vérifié · ⏸ non testé · 👤 décision ou action humaine.
> Cadre : `DECISIONS.md` (pilote Dakar, ouverture cible semaine du 11/01/2027, **pas de publicité payante avant conversion organique mesurée**, FR + wolof partiel d'abord ; EN, AR et mode sombre après G2).

---

## 0. Constats honnêtes avant le plan

| # | Constat | Statut |
|---|---|---|
| 1 | **Aucune donnée de volume de recherche** n'a pu être obtenue (pas d'accès Search Console, Keyword Planner ni Trends depuis cet environnement). Les clusters ci-dessous sont construits sur la logique d'intention et la structure du marché, **pas sur des volumes**. | ⚠ |
| 2 | Le site n'est pas en ligne, le domaine n'est pas acheté, Search Console/GA ne sont pas configurés : **aucune mesure de base**. | ✅ (`DECISIONS.md` §1) |
| 3 | Le marché de l'annonce auto au Sénégal a des acteurs installés. Un article de blog d'agence (Kolonell, 18/05/2026) cite **Coinafrique, Expat-Dakar et Senauto** ; ses chiffres d'audience ne sont pas sourcés → ne pas les réutiliser. WhatsApp et le bouche-à-oreille restent des canaux majeurs (même source, non sourcée). | ✅ cité / ⚠ chiffres |
| 4 | Un site neuf sans autorité ne se classera pas vite sur « voiture occasion Dakar » face à ces acteurs. **Stratégie réaliste** : (a) articles pratiques locaux à faible concurrence, (b) fiches annonces et pages concessionnaires propres techniquement, (c) liens/partage par les concessionnaires partenaires, (d) GEO (citations dans les assistants IA). | ⚠ jugement |
| 5 | **Google a retiré** : le rich result *Vehicle listing* (documentation supprimée le 9/09/2025, « no longer shown in Google Search results ») et les rich results *FAQ* (plus affichés depuis le 7/05/2026). Les balisages `Car`/`Offer` et `FAQPage` restent du schema.org valide et utile pour la compréhension, mais **n'apporteront pas d'extraits enrichis** : ne pas en faire un argument de ROI. | ✅ sources §10 |
| 6 | Google indique (juin 2026) que `llms.txt` et autres fichiers « pour IA » **ne sont pas utilisés par Google Search** et n'aident ni ne nuisent. Utile seulement pour d'autres systèmes → priorité basse. | ✅ source §10 (relais d'un site tiers, doc Google à relire 👤) |

---

## 1. Clusters de mots-clés et de sujets

Méthode : intention (informationnelle / transactionnelle / locale) × étape du parcours. **Volumes : inconnus ⚠.** Vocabulaire : « voiture », « auto », « véhicule » ; jamais « car » pour une voiture (au Sénégal : bus) ; l'URL technique `/car/` du plugin est un point à corriger (§3).

Étape de validation des volumes (👤 propriétaire ou `growth-marketer`, avant G1) : ouvrir un compte Google Ads gratuit (sans campagne) → Planificateur de mots clés, Pays = Sénégal, langue FR ; compléter par Google Trends et les suggestions de la barre de recherche. Remplir la colonne « Volume » ci-dessous. Tant que c'est vide, la priorité est **jugée, pas mesurée**.

### Cluster A — Acheter (transactionnel + local) · priorité haute
| Requête cible (⚠ non mesurée) | Page | Type |
|---|---|---|
| voiture occasion Dakar / voitures à vendre Dakar | `/voitures-a-vendre/` (catalogue) + page « acheter une voiture à Dakar » | Catégorie + landing |
| [marque] occasion Dakar (Toyota, Hyundai, Kia, Nissan, Peugeot, Renault, Suzuki…) | Pages marque (filtre indexable) | Catégorie |
| [marque] [modèle] occasion Sénégal | Pages modèle quand ≥ 3 annonces réelles | Catégorie |
| concessionnaire auto Dakar / vendeur de voitures Dakar | `/concessionnaires/` + fiches concessionnaires | Annuaire |
| prix voiture occasion Dakar FCFA | Guide de prix (à écrire **avec des données réelles de nos annonces**, pas avant) | Article |

### Cluster B — Louer · priorité haute (⚠ intention touristique/expat à vérifier)
| Requête | Page |
|---|---|
| location voiture Dakar / louer une voiture à Dakar | `/voitures-a-louer/` + landing « louer une voiture à Dakar » |
| location voiture avec chauffeur Dakar | Article + filtre « avec chauffeur » si le plugin le gère ⏸ |
| location voiture aéroport Dakar / AIBD | Article (à écrire après avoir des loueurs partenaires) |
| caution / assurance location voiture Sénégal | Article (livré : lot 1, n° 3) |

### Cluster C — Achat sécurisé / confiance · priorité haute (différenciation)
| Requête | Page |
|---|---|
| comment acheter une voiture d'occasion à Dakar | Article lot 1, n° 1 |
| arnaque voiture occasion Sénégal / annonce voiture arnaque | Article lot 1, n° 5 |
| inspection voiture avant achat / expertise véhicule Dakar | Article lot 1, n° 4 (service **pas encore disponible**, voir règles §9) |
| vérifier kilométrage / historique d'une voiture | Article + page « limites de l'inspection » |
| voiture importée d'Europe Sénégal (âge, dédouanement) | Article, **uniquement avec sources officielles** (douane) 👤 |

### Cluster D — Démarches administratives · priorité moyenne (forte demande probable ⚠, faible concurrence de qualité ⚠)
| Requête | Page |
|---|---|
| carte grise Sénégal changement de propriétaire | Article lot 1, n° 2 (prudent, à vérifier) |
| immatriculation véhicule Sénégal / plaques | Article (suite) |
| visite technique Sénégal | Article (suite) |
| assurance auto Sénégal obligatoire | Article (suite ; base : loi 74-33) |

### Cluster E — Professionnels (acquisition du SaaS) · priorité moyenne
| Requête | Page |
|---|---|
| vendre sa voiture / vendre plus vite en ligne | Article lot 1, n° 6 (cible concessionnaires) |
| logiciel/CRM concessionnaire, site pour concessionnaire Sénégal | Landing « devenir concessionnaire partenaire » |
| photos de voiture pour annonce, description d'annonce auto | Articles pratiques (B2B) |

### Cluster F — Entretien et propriété (long terme, après liquidité) · priorité basse
Entretien, pièces, assurance, garages de confiance par quartier. Ne pas commencer avant d'avoir les garages partenaires.

### Cluster G — Wolof / multilingue · après G2
Requêtes en wolof : le volume est **inconnu et probablement très faible** ⚠ (peu de recherche Google en wolof écrit). Le wolof sert surtout la **marque, la confiance et WhatsApp**, pas le trafic Google ; ne pas surinvestir en SEO wolof. Anglais (expats, diaspora : « used cars Dakar », « car rental Dakar ») et arabe : après G2.

---

## 2. Architecture du site et maillage interne

### URL cibles (FR)
```
/                                     accueil (Organization + WebSite)
/voitures-a-vendre/                   catalogue vente          (actuel : /cars-for-sale/)
/voitures-a-louer/                    catalogue location       (actuel : /cars-for-rent/)
/voitures/                            tout le stock            (actuel : /cars/)
/voiture/<slug-annonce>/              fiche annonce            (actuel : /car/<slug>/)
/concessionnaires/                    annuaire                 (actuel : /dealers/)
/concessionnaire/<slug>/              fiche concessionnaire    (actuel : /dealer/<slug>/)
/acheter-une-voiture-a-dakar/         landing (content/pages/fr)
/louer-une-voiture-a-dakar/           landing
/devenir-concessionnaire-partenaire/  landing B2B
/blog/<slug>/                         articles (WordPress posts)
/blog/categorie/<cluster>/            hubs par cluster
/quartiers/<quartier>/                pages locales (Plateau, Almadies, Mermoz, Ouakam, Yoff, Parcelles Assainies, Pikine, Guédiawaye, Rufisque…) — seulement avec contenu réel
/a-propos/ /contact/ /mentions-legales/ /faq/
```
⚠ Les slugs actuels sont **en anglais et codés en dur** (`Marketplace::presets()` et `register_rewrites()`), et `car/` est le mot qui désigne un bus au Sénégal. Changer = tâche pour `wp-plugin-engineer` + redirections 301 + changement avant la mise en ligne (après, chaque changement coûte du classement). Recommandation : **le faire avant G1**, pendant que rien n'est indexé. 👤 décision CEO.

### Pages marque/modèle
- Page marque indexable (`/voitures/?brand=toyota` aujourd'hui) : les filtres utilisateur sont `noindex` par code (voir §3). **Gap** : il faut des URLs propres indexables pour marque et marque+modèle (`/voitures-a-vendre/toyota/`), avec un seuil : indexer seulement si ≥ 3 annonces actives (éviter les pages vides = contenu mince).

### Maillage interne (règles)
1. Accueil → 3 catalogues, annuaire, 3 articles « confiance » les plus importants.
2. Fiche annonce → page marque → catalogue → landing « acheter à Dakar » ; bloc « Avant d'acheter » → articles n° 1, 4, 5 ; fil d'Ariane (existe).
3. Fiche concessionnaire → ses annonces → catalogue ; lien vers son site/Google Maps/WhatsApp.
4. Article → 1 landing transactionnelle + 2 autres articles du même cluster + 1 lien vers une page catalogue pertinente (ancres descriptives, pas « cliquez ici »).
5. Landing → articles de réassurance (achat sécurisé, inspection) ; hubs de blog → articles du cluster.
6. Pas de page orpheline : contrôle mensuel (crawl Screaming Frog gratuit ≤ 500 URL ou export WP).
7. Annonces vendues/louées : conserver la page (`noindex` déjà géré par le code), lien vers annonces similaires.

---

## 3. Checklist SEO technique : ce que le code fait déjà et les écarts (lus dans le code le 16/11)

Fichiers lus : `central/plugins/automotive-marketplace-core/includes/class-seo.php`, `class-sitemaps.php`, `class-marketplace.php`, thème `central/themes/autobuzy/`.

### Déjà en place ✅ (lecture de code, **non exécuté** ⏸)
- Titre + meta description + canonical + Open Graph pour catalogue, fiche, annuaire, fiche concessionnaire (`SEO::meta`, `head`). Pagination `?pg=N` : canonical par page + titre « Page N ».
- `noindex,follow` : catalogue avec filtres utilisateur, fiches non actives/réservées.
- JSON-LD : `Organization` + `WebSite`/`SearchAction` (accueil), `Car` + `Offer` (fiche : marque, modèle, année, km, carburant, boîte, prix, devise, disponibilité, images), `AutoDealer` (logo, téléphone, adresse, sameAs = site web), `BreadcrumbList` (fiche, concessionnaire), `ItemList` (catalogue).
- Sitemaps : `wp-sitemap.xml` (cœur WP) avec fournisseurs `amcvehicles` et `amcdealers` (+ coupure de fraîcheur).
- Politique de canonical configurable (`canonical_policy` : `self` par défaut ou `dealer`). Défaut `self` = bon pour la marketplace.

### Écarts (gaps uniquement)
| # | Écart | Impact | Correctif proposé (agent) | Vérification |
|---|---|---|---|---|
| T1 | **Aucun `hreflang`** ni `x-default` dans le code (grep : 0 occurrence). | Moyen maintenant (FR seul), haut dès EN/AR/wolof | Émettre `<link rel="alternate" hreflang>` quand ≥ 2 langues sont publiées pour une URL ; `fr`, `wo`, `en`, `ar`, `x-default` = FR. Ne **pas** en émettre tant qu'une langue n'existe pas (liens vers des 404). (`wp-plugin-engineer`) | Vue source + validateur hreflang |
| T2 | Slugs anglais codés en dur (`/cars-for-sale/`, `/car/…`) | Haut (SEO FR + marque) | Slugs FR configurables avant G1 + 301 (§2) (`wp-plugin-engineer`) | `curl -I` ancienne → nouvelle URL = 301 |
| T3 | Titres/descriptions des catalogues = chaînes anglaises traduites par i18n ; **description catalogue** « Browse N vehicles… » : à relire en FR (≤ 155 car.) ; pas de nom de marque dans le titre (« | Wotoyi ») sauf via le thème ⏸ | Moyen | Vérifier le rendu FR réel ; ajouter le suffixe de marque | Vue source des 4 types de page |
| T4 | **Pas de balisage `Article`** pour les posts de blog ; les articles utilisent le `canonical` natif de WP mais **pas d'OG ni de JSON-LD** (la classe SEO renvoie `null` hors vues marketplace) | Moyen | Générer `Article` + `FAQPage` + `BreadcrumbList` pour `post` (plugin SEO du cœur ou petit snippet) ; ou plugin tiers léger si le CEO l'accepte (règle : limiter les plugins) | Rich Results Test / validateur schema.org |
| T5 | `Organization` minimale (nom, URL) : pas de `logo`, `sameAs` (réseaux), `contactPoint` ; pas de `LocalBusiness`/adresse pour Wotoyi | Moyen (GEO + entité) | Compléter avec réglages existants (`company_phone`, `company_whatsapp`, `company_address`) | JSON-LD valide |
| T6 | `AutoDealer` sans `geo`, `openingHours`, `address` complète si absente ; `sameAs` seulement le site web | Moyen (local) | Ajouter champs si données présentes (pas de valeurs inventées) | Validateur |
| T7 | `BreadcrumbList` absent sur catalogue et annuaire | Faible | Ajouter | Validateur |
| T8 | Pas de `robots.txt` géré (WP virtuel par défaut) : à vérifier qu'il référence le sitemap et bloque `/manager/`, espace pro, recherche interne, `?q=` | Moyen | `wp_robots`/filtre `robots_txt` | `curl /robots.txt` |
| T9 | Pages marque/modèle indexables absentes (§2) | Haut | Voir §2 (seuil ≥ 3 annonces) | Sitemap + `site:` |
| T10 | `Car` : pas de `vehicleIdentificationNumber`, ni `bodyType`/`color` : **ne les ajouter que si la donnée existe** ; ne jamais exposer le numéro de châssis publiquement sans décision 👤 | Faible | Selon champs disponibles | — |
| T11 | Image OG par défaut absente pour accueil/blog | Faible | Image de marque 1200×630 | Débogueur de partage |
| T12 | Mesure : Search Console, GA4 (ou alternative), événements clic WhatsApp/téléphone = **non instrumentés** (NS impossible à mesurer) | **Haut** | Tâche T-P1-07 existante ; ajouter UTM sur les liens partagés par les concessionnaires | Événements visibles en temps réel |
| T13 | Core Web Vitals : **jamais mesurés** (⏸). LCP < 2,5 s, CLS < 0,1, INP < 200 ms (seuils du skill) à vérifier sur mobile 3G/4G après mise en préproduction | Haut | `data-performance-engineer` ; PageSpeed Insights + CrUX quand il y aura du trafic | Rapport avant/après |
| T14 | Prévoir : redirections 301 propres, page 404 utile, pagination `rel` non nécessaire (Google ne l'utilise plus) | Faible | — | — |

Contrôle avant G1 (👤 `qa-engineer`) : `site:` n'est utile qu'après indexation ; avant, vérifier à la main : un seul canonical par page, un seul H1, aucune page de staging indexable (`noindex` global tant que l'environnement n'est pas la production).

---

## 4. Plan GEO (être cité par les assistants IA)

Principe retenu (Google, juin 2026 : pas de balisage spécial requis, il faut du contenu utile, explorable, structuré ✅ source §10) : **le GEO = bon SEO + faits citables + entité claire.** Aucune promesse de citation (on ne la contrôle pas).

1. **Entité claire** : « Wotoyi » = marketplace automobile à Dakar. Même nom, même description, mêmes liens partout (site, Google Business Profile, réseaux, annuaires). Page « À propos » factuelle : qui, où, depuis quand, comment les concessionnaires sont sélectionnés (👤 décrire le processus réel, ne pas inventer de « vérification »), contact. Vérifier la marque/le nom (R7).
2. **Phrases citables** en tête d'article : réponse directe en 2 à 3 phrases, puis détail. FAQ avec questions réelles et réponses courtes. Listes numérotées pour les procédures.
3. **Faits datés et sourcés** : chaque information juridique/tarifaire = source + date de consultation. Les assistants IA privilégient les pages qui citent leurs sources. Chiffres jamais inventés.
4. **Données propres que personne d'autre n'a** (le vrai levier à terme) : « prix moyens observés dans les annonces Wotoyi » (mensuels, avec échantillon et méthode, **uniquement quand on a ≥ 30 annonces réelles par segment** ⚠), délais de réponse des concessionnaires. À publier sous forme de tableau HTML.
5. **HTML rapide, contenu sans JS obligatoire** ; tableaux HTML ; pas de contenu seulement dans des images/PDF.
6. **Robots IA** : autoriser GPTBot, ClaudeBot, PerplexityBot, Google-Extended etc. dans `robots.txt` si on veut être cité (décision 👤 ; noms de user-agents à vérifier dans la doc de chaque éditeur ⚠).
7. **`llms.txt`** : optionnel, coût quasi nul (fichier statique à la racine : description, liens vers pages clés, règles d'usage des données). **N'aide pas Google** (§0.6). À faire en dernier.
8. **Présence hors site** (les assistants citent ce qu'ils trouvent) : Google Business Profile, Facebook/Instagram/TikTok/LinkedIn, annuaires (au-senegal.com, expat-dakar : ⚠ à vérifier s'ils acceptent une inscription), mentions presse locale via communiqué de lancement 👤.
9. **Mesure GEO** (manuelle, mensuelle) : 20 questions-types posées à ChatGPT, Gemini, Perplexity, Claude (« où acheter une voiture d'occasion à Dakar ? », « comment faire une carte grise au Sénégal ? ») → noter : Wotoyi cité ? lien ? exactitude ? Journal dans `docs/` ou tableur. Référrers IA dans GA4 (⚠ attribution partielle).
10. **Honnêteté** : ne rien écrire que l'on ne veut pas voir répété (ex. « inspection garantie »). Les règles de l'inspection (§9) s'appliquent à tout le contenu.

---

## 5. SEO local

### Google Business Profile (GBP) pour Wotoyi 👤
- Éligibilité : une fiche exige un **lieu réel** ou une zone de service ; les bureaux virtuels et boîtes postales sont refusés ; le nom doit être le nom réel (sans mots-clés ajoutés) ✅ (aide Google « Guidelines for representing your business »). Wotoyi n'a **peut-être pas** de bureau à Dakar (propriétaire au Maroc) : la fiche n'est possible que s'il existe un lieu staffé qui reçoit du public ou un modèle de zone de service légitime. **Ne pas créer de fiche avec une adresse fictive** (suspension). Décision 👤 après D2/D7.
- Si éligible : catégorie principale pertinente (⚠ choisir dans la liste Google), zone de service Dakar, WhatsApp/téléphone identiques au site (NAP), photos réelles, publications hebdomadaires (nouveautés de stock), réponse aux avis.

### GBP des concessionnaires (levier fort, hors de notre contrôle)
- Chaque concessionnaire possède/crée **sa** fiche (on ne la crée pas à sa place sans son accord écrit ✅ règle Google : un tiers doit avoir le consentement du propriétaire). Les « agents de génération de leads » ne sont pas éligibles à une fiche : Wotoyi ne doit **pas** créer de fiches pour des concessionnaires dont elle n'est pas le représentant.
- Service offert (offre « partenaire fondateur », `sales-partnerships`) : checklist d'une page : nom exact, adresse précise, horaires, téléphone, catégorie, photos, lien vers sa page Wotoyi (UTM), demande d'avis aux acheteurs après vente.
- **NAP identique** entre GBP, fiche Wotoyi, site du concessionnaire et réseaux ; le plugin doit afficher la même adresse/téléphone que ceux saisis par le concessionnaire (cohérence = champ source unique).

### Pages locales (quartiers)
Créer `/quartiers/<quartier>/` **uniquement** si on a ≥ 3 concessionnaires/garages réels dans la zone et un contenu propre (accès, repères, stock de la zone). Sinon = contenu mince. Ordre probable (⚠ à valider par la carte des partenaires) : Dakar-Plateau/Médina, Mermoz-Sacré-Cœur, Almadies/Ngor/Ouakam, Parcelles Assainies/Grand Yoff, Pikine/Guédiawaye, Rufisque, Mbao/Diamniadio.

### Avis
Flux simple : message WhatsApp post-vente avec lien d'avis GBP du concessionnaire. Aucun avis acheté ni échangé contre un avantage (politique Google ⚠ à relire).

---

## 6. Plan SEA (Google Ads / Meta) : **conditionnel**, petit budget

**Règle du CEO (`DECISIONS.md` §7.7) : aucun budget publicitaire avant une conversion mesurée en organique.** Ce plan est donc prêt mais **gelé** jusqu'au déclencheur ci-dessous. Aucun coût par clic ni budget n'est supposé : tous les chiffres sont à fixer après mesure.

### Déclencheurs (tous requis)
1. Événements de conversion fiables (clic WhatsApp, clic téléphone, formulaire) mesurés depuis ≥ 2 semaines ✅/⏸ (T12).
2. ≥ 50 annonces réelles à jour et ≥ 5 concessionnaires actifs (sinon on paie pour des clics sur un site vide).
3. Budget approuvé 👤 (D3 ne couvre que domaine + hébergement).

### Structure de test (Google Ads) ⚠ tout est hypothèse à valider
| Campagne | Objectif | Ciblage | Mots-clés (exact/phrase) | Conversion |
|---|---|---|---|---|
| S1 Acheter-Dakar | contacts acheteurs | Dakar (rayon/ville), FR | cluster A (marque + occasion + Dakar) | clic WhatsApp |
| S2 Louer-Dakar | contacts loueurs | Dakar + aéroport ⚠ | cluster B | clic WhatsApp/appel |
| S3 Marque-Wotoyi | protéger la marque | — | « wotoyi » | — |
| S4 B2B concessionnaires | inscriptions partenaires | Dakar | cluster E | formulaire partenaire |
Règles : 1 objectif par campagne ; mots-clés négatifs dès le départ (emploi, gratuit, pièces, « car » bus) ; appel et WhatsApp en extensions ; pas de Performance Max au départ (pas assez de données) ; budget minimal par campagne et durée fixe (ex. 14 jours) **à définir par `growth-marketer` + `finance-analyst`** ; on coupe ce qui n'a pas de contact après le seuil fixé d'avance.
Éligibilité des annonces véhicules/Merchant Center pour le Sénégal : ⚠ non vérifiée.

### Structure de test (Meta : Facebook/Instagram, WhatsApp) ⚠
- Annonces « Click-to-WhatsApp » vers le numéro de la plateforme ; **WhatsApp Business** : opt-in et fenêtre de 24 h à respecter (skill `whatsapp-ai-agent`).
- Créatifs : vraies photos d'annonces, prix en FCFA, FR d'abord, wolof parlé en vidéo courte après relecture native 👤.
- Audiences : Dakar + diaspora (⚠ pays à choisir) ; 2 audiences × 2 créatifs maximum au début.
- Piste B2B : vidéo de démo + formulaire « devenir partenaire ».

### Indicateurs et décision
Coût par contact qualifié (en FCFA), coût par concessionnaire inscrit, taux de contact par visite. Règle : le coût par contact payé doit rester sous la valeur d'un contact pour le concessionnaire (inconnue ⚠, issue des entretiens `mom-test`) ; sinon on arrête et on reste sur l'organique.

---

## 7. SEO multilingue (FR / wo / en / ar)

- **Ordre** (cohérent avec `DECISIONS.md`) : FR maintenant ; wolof partiel (pages clés + relecture native 👤) ; EN et AR après G2.
- **Structure d'URL recommandée** : FR à la racine (`/…`), autres en sous-dossiers : `/wo/…`, `/en/…`, `/ar/…` (simple à héberger, autorité partagée). Pas de sous-domaines.
- **hreflang** : codes `fr`, `wo`, `en`, `ar` (+ `x-default` → FR). Balises **réciproques et auto-référencées** sur chaque page ; une seule méthode (balises `<link>` OU sitemap). Ne publier un `hreflang` que si la page cible existe, est traduite (pas du FR copié) et indexable. ⚠ Le code ISO `wo` est valide, mais la prise en charge de Google pour le wolof (interface, détection de langue) est **non vérifiée** : tester avec la détection de langue de Search Console quand les pages existent.
- **Template des métadonnées** : les articles du lot 1 contiennent `hreflang` en placeholders (`wo`, `en`, `ar`) à remplir **seulement** à la publication de chaque traduction.
- **Arabe** : MSA, `dir="rtl"`, `lang="ar"` ; polices avec glyphes arabes ; chiffres cohérents. **Wolof** : orthographe CLAD (ë, ñ, ŋ), 👤 relecture native obligatoire, jamais « vérifié » sans elle.
- **Contenu local, pas traduction littérale** : les articles juridiques (carte grise…) ne se traduisent qu'après relecture et vérification des sources.
- **`og:locale`** : le code émet `get_locale()` (ex. `fr_FR`) ✅ ; pour le wolof, `wo_SN` ⚠ à valider.
- **Moteur de traduction** : à ne pas automatiser (pas de plugin de traduction auto qui crée des pages de mauvaise qualité).

---

## 8. Calendrier éditorial : 12 semaines, 2 articles par semaine (24 articles)

Hypothèse : début lundi **30/11/2026** (publication en préproduction `noindex` tant que le site n'est pas en ligne ; mise en ligne du lot avant l'ouverture, semaine du 11/01/2027). Ramadan ≈ début février 2027 ⚠ (date à vérifier) : prévoir un contenu adapté et un rythme moindre. **Les sujets marqués ★ = livrés en lot 1 (`content/blog/fr/`).**

| Sem. | Dates (lun.) | Article A (cluster) | Article B (cluster) |
|---|---|---|---|
| 1 | 30/11 | ★ Acheter une voiture d'occasion à Dakar : checklist (C) | ★ Éviter les arnaques en ligne (C) |
| 2 | 07/12 | ★ Pourquoi une inspection avant achat (C) | ★ Carte grise : démarches (D, prudent) |
| 3 | 14/12 | ★ Louer une voiture à Dakar : caution, assurance (B) | ★ Concessionnaires : vendre plus vite en ligne (E) |
| 4 | 21/12 | Visite technique au Sénégal : à quoi s'attendre (D) | Négocier le prix d'une voiture d'occasion à Dakar (A) |
| 5 | 28/12 | Assurance auto au Sénégal : les bases (D) | Acheter une voiture importée : ce qu'il faut vérifier (C) |
| 6 | 04/01 | Voiture avec ou sans chauffeur à Dakar (B) | Photos d'annonce auto : guide pour concessionnaires (E) |
| 7 | 11/01 | **Lancement pilote** : annonce, présentation de Wotoyi et des concessionnaires partenaires (marque) | Choisir sa voiture à Dakar : citadine, SUV, 4×4 (A) |
| 8 | 18/01 | Comment lire une annonce auto : signaux d'alerte (C) | Louer à l'aéroport AIBD : conseils (B) |
| 9 | 25/01 | Diesel, essence, hybride à Dakar : que choisir ? (A) | Rédiger une description d'annonce qui convertit (E) |
| 10 | 01/02 | Entretien de base d'une voiture d'occasion (F, léger) | Répondre vite sur WhatsApp : scripts pour concessionnaires (E) |
| 11 | 08/02 | Papiers à exiger à l'achat : modèle de mini-contrat de vente (C ; 👤 relecture juridique) | Premier relevé : « Ce que nous observons dans les annonces à Dakar » (données réelles ou **reporté**) (A/GEO) |
| 12 | 15/02 | Voitures à Dakar en temps de Ramadan : conseils (B/A, ⚠ à confirmer) | Bilan + refonte des 6 meilleurs articles (boucle §11) |

Règles éditoriales : 900 à 1 500 mots ; une réponse directe en début d'article ; FAQ de 4 à 6 questions réelles ; sources en fin d'article ; date de mise à jour visible ; relecture `localization-writer` + 👤 pour tout contenu juridique ; chaque article = 1 requête cible principale + 3 à 5 secondaires ; pas de contenu généré en masse.

Calendrier traductions : après chaque article FR validé → wolof (versions courtes, articles « confiance » d'abord) puis EN/AR après G2.

---

## 9. Règles de contenu à ne jamais enfreindre

1. **Inspection** : ne jamais garantir le kilométrage réel ni l'absence d'accident, ni affirmer qu'un outil lit le km de toutes les marques. Formule obligatoire dans tout contenu parlant d'inspection (reprise dans l'article n° 4). Le service **n'existe pas encore** dans le code ni dans l'offre : tout texte public doit dire « prévu / bientôt » tant que `DECISIONS.md` ne dit pas le contraire ; pas de prix.
2. **Droit, taxes, démarches** : source officielle ou fiable citée + date ; sinon « à vérifier auprès de … ». Pas de montant non sourcé.
3. **« Voiture » / « auto »**, jamais « car » pour un véhicule dans les textes marketing.
4. **Pas de promesse de « vendeurs vérifiés »** tant que le processus réel de vérification n'est pas défini et appliqué 👤 (le code et `docs/` ne le décrivent pas à ce jour ⚠).
5. **Pas de faux avis, pas de statistiques de marché inventées, pas de chiffres de concurrents non sourcés.**
6. Données personnelles : aucune plaque, numéro de téléphone ou photo de personne sans consentement (CDP).

---

## 10. Sources consultées (16/11/2026)

- Google Search Central, *Vehicle listing (Car) structured data* (page signalant le retrait, documentation supprimée le 9/09/2025) : https://developers.google.cn/search/docs/appearance/structured-data/vehicle-listing?hl=en — ✅ lu via WebFetch ; version `.cn` du site officiel, à reconfirmer sur developers.google.com 👤.
- Retrait des rich results FAQ (7/05/2026 ; FAQPage reste du schema valide) : https://www.techwyse.com/news/ai-search/google-faq-rich-results-deprecated-2026 — ⚠ **source secondaire** (blog d'agence) ; à reconfirmer dans la doc Google.
- Google et `llms.txt` (15/06/2026) : https://www.techwyse.com/news/ai-search/google-llms-txt-no-ranking-benefit-june-2026 — ⚠ source secondaire, idem.
- Google Business Profile, règles de représentation : https://support.google.com/business/answer/3038177 — ✅ source officielle.
- Marché des annonces auto au Sénégal (Coinafrique, Expat-Dakar, Senauto ; arnaques courantes ; chiffres non sourcés) : https://kolonell.com/fr/blog/vente-voiture-occasion-senegal-plateformes-2026 — ⚠ blog d'agence, usage qualitatif seulement.
- Démarches carte grise (2015) : https://www.seneplus.com/article/comment-enregistrer-sa-voiture-au-s%C3%A9n%C3%A9gal — ⚠ ancien, à vérifier.
- Titres sécurisés / plaques liées au véhicule (2018) : https://osiris.sn/transports-terrestres-des-titres-numerises-a-partir-de-juillet.html — ⚠ ancien.
- Assurance obligatoire : loi n° 74-33 du 18/07/1974 : https://www.vie-publique.sn/documents/7898/loi-n-1974-33-du-18-juillet-1974-instituant-obligation-assurance-circulation-vehicules-terrestres-moteur — ✅ page officielle (résumé lu ; texte intégral non lu).
- Location (fourchettes de prix indicatives, mai 2026) : https://au-senegal.com/louer-une-voiture-au-senegal-prix-conseils-et-erreurs-a-eviter,18326.html — ⚠ annuaire privé.

---

## 11. Indicateurs (KPI) et boucle d'amélioration

### KPI (cibles à poser après 4 semaines de données ; aucune cible inventée ici)
| KPI | Outil | Fréquence | Lien avec la métrique étoile |
|---|---|---|---|
| Pages indexées / soumises (sitemaps) | Search Console | hebdo | base |
| Impressions, clics, CTR, position moyenne (requêtes clusters A à E) | Search Console | hebdo | demande |
| Clics WhatsApp/téléphone/formulaire (par page source) | GA4 ou équivalent + événements | hebdo | **NS** |
| Contacts par source (organique, direct, partage concessionnaire, IA, payant) | UTM + GA4 | hebdo | NS |
| Core Web Vitals mobile (LCP, CLS, INP) | PSI/CrUX | mensuel | qualité |
| Citations IA (20 questions-types) | manuel | mensuel | GEO |
| Articles publiés vs calendrier | calendrier | hebdo | exécution |
| Fiches GBP concessionnaires créées/optimisées, avis reçus | GBP | mensuel | local |
| Backlinks/mentions obtenus | Search Console (liens) | mensuel | autorité |

### Boucle d'amélioration du contenu (mensuelle, 2 h)
1. **Exporter** de Search Console (28 derniers jours) : requêtes × pages.
2. **Trier** : (a) pages avec impressions mais CTR faible → réécrire title/meta ; (b) requêtes en positions 8 à 20 → enrichir l'article (section manquante, FAQ, tableau, exemple local) ; (c) requêtes sans page dédiée → nouvel article au calendrier ; (d) pages sans impression après 60 jours → fusionner, rediriger ou supprimer ; (e) cannibalisation (2 pages, même requête) → fusionner.
3. **Vérifier les faits** : relire sources et dates des articles juridiques/tarifs (trimestriel au minimum) ; mettre à jour « Dernière mise à jour ».
4. **Mettre à jour** les 5 articles à plus fort potentiel ; republier avec date modifiée **seulement si le contenu change vraiment**.
5. **Questions réelles** : ajouter à la FAQ les questions reçues par WhatsApp et formulaire (anonymisées) → source de contenu authentique.
6. **Journaliser** : tableur « changement · date · hypothèse · résultat à 28 jours » ; garder ce qui marche, arrêter le reste.
7. **Rapport au CEO** : 5 lignes (clics, contacts, 3 gains, 3 problèmes, décisions 👤).

---

## 12. Tâches et responsables

| Id proposé | Tâche | Agent | Dépend de |
|---|---|---|---|
| SEO-01 | Valider volumes (Planificateur de mots clés) et compléter §1 | growth-marketer / 👤 | compte Ads gratuit |
| SEO-02 | Slugs FR + 301 avant G1 (T2) | wp-plugin-engineer (+ code-reviewer, qa) | décision CEO 👤 |
| SEO-03 | hreflang (T1) conditionnel | wp-plugin-engineer | langues publiées |
| SEO-04 | JSON-LD `Article`/`FAQPage`/`BreadcrumbList` pour posts, `Organization` complète, `AutoDealer` enrichi (T4 à T7) | wp-plugin-engineer | — |
| SEO-05 | `robots.txt` + OG par défaut (T8, T11) | wp-plugin-engineer / wp-theme-engineer | — |
| SEO-06 | Pages marque/modèle indexables (T9) | wp-plugin-engineer + software-architect | — |
| SEO-07 | Mesure : événements + Search Console + UTM (T12) | wp-plugin-engineer + growth-marketer | T-P1-07 |
| SEO-08 | Core Web Vitals (T13) | data-performance-engineer | préproduction |
| SEO-09 | Traduction wolof/EN/AR des contenus du lot 1 | localization-writer + 👤 | validation FR |
| SEO-10 | Relecture juridique des articles n° 2, 3, 4 | legal-compliance | — |
| SEO-11 | GBP Wotoyi (si éligible) + checklist GBP concessionnaires | 👤 + sales-partnerships | D2, D7 |
| SEO-12 | `llms.txt` (optionnel) | seo-geo-specialist | site en ligne |
