# content/ : contenus SEO de lancement Wotoyi

Auteur : [SEO-GEO] · 2026-11-16 · statut : **en attente de validation CEO**. Plan complet : `docs/SEO-PLAN.md`.

## Structure
```
content/blog/fr/<slug>.md     6 articles (WordPress : articles / posts)
content/pages/fr/<slug>.md    4 pages (WordPress : pages)
```
Chaque fichier : bloc d'en-tête YAML (`title` ≤ 60, `meta_description` ≤ 155, `slug`, `cluster`, `target_keyword`, `hreflang`, `schema`, `status`, `review`) puis contenu Markdown.

## Correspondance avec WordPress
| Champ du fichier | Dans WordPress |
|---|---|
| `title` | Titre SEO (balise `<title>`) ; le H1 du corps est le titre affiché de l'article |
| `meta_description` | Meta description (réglage SEO de la page/article) |
| `slug` | Identifiant URL (permalien) ; articles sous `/blog/<slug>/` (structure de permaliens à régler, voir `docs/SEO-PLAN.md` §2) |
| `cluster` | Catégorie de l'article (`/blog/categorie/…`) |
| `published` | Date de publication planifiée (calendrier §8 du plan) |
| `hreflang` | **Ne pas publier tant qu'une traduction n'existe pas.** Voir ci-dessous |
| `schema` | JSON-LD à générer (Article, FAQPage, BreadcrumbList) : **pas encore géré par le code** (écart T4 du plan) |
| `status`, `review` | Notes internes, à ne pas publier |
| Sections « Sources » | À conserver visibles en bas de l'article |

Pages : créer une page WordPress par fichier ; `accueil.md` = page d'accueil statique (Réglages > Lecture). Les liens `[…](/voitures-a-vendre/)` supposent les URLs cibles FR du plan ; **les URLs actuelles du plugin sont anglaises** (`/cars-for-sale/`…). Adapter les liens si les slugs ne sont pas changés avant la mise en ligne.

## Avant toute publication (👤)
1. **Légal** (`legal-compliance`) : articles carte grise, inspection, location ; formule « Wotoyi n'est pas partie à la vente / met en relation » (marquée ⚠ dans les pages).
2. **Carte grise** : appeler/visiter la DTT, confirmer pièces, lieux, tarifs ; mettre à jour l'article et sa date. Sources actuelles : 2015 et 2018 (non officielles).
3. **Location** : confirmer les fourchettes de prix citées (source privée, mai 2026) ou les retirer.
4. **Inspection** : le service n'existe pas ; ne publier aucune offre ni prix. Conserver intact le bloc « Limites d'une inspection ».
5. **« Vérifié »** : n'utiliser le mot qu'une fois le processus de vérification des concessionnaires défini.
6. **Landing partenaire** : ne rien publier avant la décision D10 (conditions du pilote).
7. Tout contenu reste **hors index** (`noindex`) tant que le site n'est pas en production.

## À faire ensuite : `localization-writer`
1. Wolof (priorité : articles « confiance » n° 1, 4, 5, puis pages acheter/louer) : orthographe CLAD, ton naturel de Dakar, version adaptée plutôt que traduction mot à mot. **Marquer 👤 : relecture par un locuteur natif obligatoire** (décision D9), ne jamais écrire « vérifié » sans elle.
2. Anglais et arabe (MSA, RTL) : **après G2**. L'arabe exige `dir="rtl"`, `lang="ar"` et une police avec glyphes arabes.
3. Garder : « voiture/auto » (jamais « car »), prix `1 500 000 FCFA`, placeholders et liens, sources en bas (les sources restent en langue d'origine).
4. Pour chaque traduction publiée : remplir le champ `hreflang` de **toutes** les versions (réciproque + auto-référencé + `x-default` = FR). Ne rien remplir avant.
5. Ne pas traduire les passages juridiques tant que la version FR n'est pas vérifiée (point 2 ci-dessus).
6. Mettre à jour les fichiers `.l10n.php` uniquement pour les chaînes d'interface, pas pour ces contenus.

## Contrôles effectués (16/11/2026)
- Longueurs vérifiées par script : titres ≤ 60 car., meta ≤ 155 car., corps des articles de 950 à 1 360 mots (le plus court : carte grise, ≈ 950 mots, volontairement bref faute de faits vérifiables).
- JSON-LD, rendu WordPress, liens internes : ⏸ non testés (le site n'est pas en ligne).
