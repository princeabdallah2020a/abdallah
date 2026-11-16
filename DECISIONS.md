# DECISIONS.md — Wotoyi

> Journal des décisions. Une entrée = contexte · options · décision · responsable · date de revue.
> Légende : ✅ vérifié/mesuré · ⚠ hypothèse · ⏸ non testé · 👤 décision du propriétaire requise.
> Les exécutants lisent ce fichier au lieu de refaire la réflexion. Backlog associé : `TASKS.md`.

---

## 2026-11-16 — Cadrage T1 (17 nov. 2026 – 28 fév. 2027)

**Auteur** : [CEO] (`ceo-strategist`, session Fable) · **Statut** : **option B rejetée par le propriétaire le 16/11 (D1, D5)** ; § 3, 4, 5, 6, 7, 9 et 10 remplacés par l'entrée « Décision du propriétaire : lancement complet + 4 langues » plus bas ; § 1 et § 8 toujours valables · **Type** : porte à double sens (réversible à chaque jalon), sauf marque/domaine/juridique (sens unique → 👤).

### 1. Contexte (état réel au 16/11/2026)

| Fait | Statut | Source |
|---|---|---|
| Phase 1 (site, catalogue, fiches, SEO, API lecture) testée : 127/127 + 32/32 | ✅ documenté, sur SQLite uniquement, non relancé aujourd'hui ⏸ | `docs/06-roadmap.md` |
| Phases 2 à 6 construites, jamais exécutées (contrôle de syntaxe seulement) | ✅ | `docs/06-roadmap.md` |
| Aucun test sur MySQL/MariaDB, e-mails non testés, pas de test de charge | ✅ | `docs/06-roadmap.md` |
| **Module inspection absent du code** (aucune occurrence de « inspection » dans `central/` et `dealer/`) | ✅ grep du 16/11 | — |
| **Mode sombre absent** (aucun `prefers-color-scheme` / `data-theme` dans thèmes et design system) | ✅ grep du 16/11 | — |
| **Seul le français est traduit** (pas de wolof, pas d'arabe, pas de RTL réel) | ✅ `languages/` | — |
| **Une annonce n'entre sur la marketplace que par l'API de synchro d'un site concessionnaire** (ou données de démo) : pas de saisie directe | ✅ `Vehicles::upsert` appelé seulement par `class-sync-api.php` et `class-demo-data.php` | — |
| **Clics WhatsApp / téléphone non mesurés** : la métrique étoile du nord n'est pas mesurable aujourd'hui | ✅ pas de table ni d'événement de suivi | `class-database.php` |
| La marque « Wotoyi » n'apparaît nulle part dans le code (tout est « AutoBuzy ») | ✅ grep du 16/11 | — |
| Paiement : fournisseur manuel uniquement (reçu vérifié) ; pas de Wave/Orange Money | ✅ | `docs/05-external-services.md` |
| Domaine non acheté, pas d'hébergement, pas de clé API, IA/WhatsApp/téléphonie éteints | ✅ | brief propriétaire |
| Clients, concessionnaires contactés, entretiens réalisés : **0** | ✅ (aucune trace dans le dépôt) | — |
| QA des phases 2 à 6 lancée ce jour, résultats inconnus | ⏸ | brief |

**Constat franc** : nous avons beaucoup de code et zéro preuve de marché. Le risque n° 1 n'est pas technique, c'est de finir un écosystème complet que personne n'a demandé. Et la demande « lancer vite, qualité excellente, 4 langues, mode sombre, SaaS + inspection + IA » ne tient pas en 15 semaines avec un budget nul et un fondateur salarié basé au Maroc.

### 2. Options étudiées

| Option | Contenu | Coût | Impact | Risque |
|---|---|---|---|---|
| **A. Lancement complet** | Marketplace + sites concessionnaires + abonnements + inspection + IA WhatsApp + 4 langues + mode sombre, public fin février | Très élevé (5 phases à tester et corriger, inspection à construire, hébergement par concessionnaire, API payantes) | Fort en théorie | Très élevé : date intenable, qualité dégradée, dépenses avant toute preuve de demande |
| **B. Pilote étroit à Dakar** (retenue) | Marketplace centrale en ligne, français d'abord, 10 concessionnaires partenaires gratuits, annonces réelles, contacts mesurés, test de paiement « à la main » (inspection concierge + engagement SaaS) | Faible (domaine + hébergement mutualisé ; temps du propriétaire) | Preuve de marché + premiers revenus tests ; base saine pour monétiser au T2 | Moyen : dépend du temps terrain du propriétaire et d'un relais à Dakar |
| **C. Qualité d'abord, clients ensuite** | Trimestre entier en QA + développement du reste, aucun client avant mars | Nul en argent, fort en temps | Aucun apprentissage marché | Élevé : 3 mois de plus sans savoir si un concessionnaire paiera |

### 3. Décision

**Option B.** Justification en une ligne : le seul périmètre déjà testé (phase 1) suffit à livrer la valeur centrale d'une marketplace (des acheteurs qui contactent des concessionnaires), donc on met en ligne ce socle, on recrute l'offre à la main et on teste le paiement avant d'écrire du code de monétisation.

Conséquences assumées :
- **Ce n'est pas un « lancement public » ce trimestre, c'est un pilote.** Ouverture cible : semaine du 11 janvier 2027, conditionnée au jalon G1. Le lancement public avec communication se décide à la revue de fin février.
- **Le pilote ne dépend pas du résultat de la QA des phases 2 à 6.** Périmètre minimal garanti = phase 1 + saisie directe d'annonces par l'équipe + mesure des contacts. Les phases 2, 3 et 4 s'ajoutent seulement si elles passent la QA.
- **Complément (pas remplacement) à la décision « sites concessionnaires indépendants »** : on ajoute une saisie directe d'annonces côté plateforme (« onboarding concierge »). Les sites + CRM concessionnaires restent la cible SaaS, limités à 2 ou 3 partenaires pilotes ce trimestre. 👤
- **Écarts volontaires par rapport aux décisions validées, pour le pilote uniquement** : français + wolof sur les textes acheteurs clés (wolof relu par un locuteur natif 👤) ; anglais, arabe/RTL et mode sombre reportés après G2. 👤
- **Inspection** : vendue « à la main » (WhatsApp, paiement manuel, rapport PDF fait hors plateforme) pour tester la demande et le prix ; le module ne se code que si le test est positif.

### 4. Objectif du trimestre

> **Au 28 février 2027, Wotoyi est en ligne à Dakar en pilote avec 10 concessionnaires partenaires actifs et 150 annonces réelles à jour, livre au moins 30 contacts acheteurs qualifiés par semaine, et a encaissé une première preuve de paiement (3 concessionnaires engagés avec acompte ou 5 inspections payées).**

### 5. Étoile du nord et indicateurs

**Étoile du nord** : contacts acheteurs qualifiés livrés aux concessionnaires par semaine.
Définition : clic WhatsApp + clic téléphone + formulaire « Contacter le vendeur », dédoublonné par visiteur et par annonce sur 24 h, hors robots et hors équipe.

| # | Indicateur | Base au 16/11 | Cible 28/02/2027 | Nature de la cible |
|---|---|---|---|---|
| NS | Contacts qualifiés / semaine | 0 ✅ (rien en ligne ; non instrumenté) | ≥ 30 | ⚠ hypothèse, à recalibrer le 15/12 |
| K1 | Concessionnaires actifs (≥ 1 annonce mise à jour < 14 j) | 0 ✅ | 10 | ⚠ |
| K2 | Annonces réelles en ligne · part mise à jour < 7 j | 0 ✅ | 150 · ≥ 70 % | ⚠ (suppose ~15 annonces par concessionnaire) |
| K3 | Liquidité : part des annonces avec ≥ 1 contact en 14 j | non mesurable ✅ | ≥ 30 % | ⚠ |
| K4 | Preuve de paiement | 0 FCFA ✅ | 3 concessionnaires avec acompte **ou** 5 inspections payées | ⚠ ; aucun objectif de MRR ce trimestre (ce serait inventé) |
| K5 | Qualité en production | 127/127 + 32/32 sur SQLite ✅ documenté ⏸ non relancé | 100 % des tests verts sur MySQL ; 0 faille critique/haute ouverte ; 0 incident de données | engagement interne |
| K6 | Apprentissage : entretiens concessionnaires (méthode `mom-test`) | 0 ✅ | 15 avant le 15/12 | engagement interne |

Règle : tant que la tâche d'instrumentation (T-P1-07) n'est pas livrée, NS et K3 restent « non mesurés » ; on ne publie aucune estimation à la place.

### 6. Les 3 priorités

**P1 — Mettre en production un socle marketplace fiable et mesurable**
- Pourquoi : rien ne se vend sur du code non testé ; un incident de données ou une panne au pilote détruit la confiance, qui est le produit dans l'occasion.
- Terminé quand : QA des phases 1, 2 et 4 exécutée sur MySQL avec preuves ; 0 faille critique/haute ; site en ligne sous la marque Wotoyi (préfixes techniques inchangés) ; saisie directe d'annonces opérationnelle ; contacts mesurés ; sauvegarde + restauration testées ; CGU, confidentialité et démarche CDP traitées.
- Responsables : `qa-engineer` (sonnet), `security-auditor` (opus), `wp-plugin-engineer` (opus), `software-architect` (inherit/Fable), `code-reviewer` (sonnet), `devops-release` (haiku), `legal-compliance` (opus), `wp-theme-engineer` (sonnet).

**P2 — Recruter l'offre : 10 concessionnaires partenaires de Dakar, annonces réelles**
- Pourquoi : dans une marketplace, on règle d'abord le côté contraint, ici l'offre de qualité ; sans annonces réelles et fraîches, aucun acheteur ne revient.
- Terminé quand : 15 entretiens réalisés et synthétisés ; offre « partenaire fondateur » écrite (gratuit pendant le pilote, contre annonces à jour et retour hebdomadaire) ; 10 concessionnaires en ligne avec photos réelles ; routine hebdomadaire de mise à jour tenue 4 semaines de suite.
- Responsables : `sales-partnerships` (sonnet) pour listes, scripts, offre et suivi ; **propriétaire 👤 ou relais à Dakar pour les appels et visites (les agents ne peuvent pas le faire)** ; `customer-ops` (haiku) ; `localization-writer` (sonnet) ; `product-manager` (sonnet).

**P3 — Prouver la demande et le paiement**
- Pourquoi : les deux lignes de revenu (SaaS concessionnaire, inspection) sont des hypothèses ; il faut une preuve de paiement avant de coder la monétisation.
- Terminé quand : contacts mesurés chaque semaine ; canaux gratuits d'acquisition testés (SEO local, partage des annonces par les concessionnaires, groupes sociaux) avec résultat chiffré ; inspection concierge proposée sur les fiches et vendue au moins 5 fois **ou** 3 concessionnaires ayant versé un acompte ; dossier économique à 3 scénarios livré pour décider du T2.
- Responsables : `growth-marketer` (sonnet), `seo-geo-specialist` (sonnet), `finance-analyst` (inherit/Fable), `customer-ops` (haiku), `legal-compliance` (opus) pour le texte de limites de l'inspection.

### 7. Pas maintenant (refusé ce trimestre)

1. Assistant WhatsApp, voix, téléphonie, wolof vocal (phases 5 et 6 voix) : éteints ; pas de vérification Meta, pas de clé API payante.
2. Assistant IA du site : la recherche en langage naturel intégrée (sans fournisseur) suffit pour le pilote.
3. Module inspection en code, rapport PDF automatisé, espace acheteur : seulement après preuve de vente concierge.
4. Passerelles Wave / Orange Money / cartes et renouvellements automatiques : paiement manuel avec reçu.
5. Provisioning automatique des sites concessionnaires ; plus de 3 sites concessionnaires pilotes.
6. Anglais, arabe/RTL, mode sombre, PWA, nouvelles animations : après G2, si les jalons sont tenus.
7. Publicité payante (Google, Meta, TikTok) : aucun budget avant une conversion mesurée en organique.
8. Mali, Burkina Faso, Guinée, Côte d'Ivoire ; autres villes du Sénégal.
9. Annonces mises en avant payantes, commissions : on ne taxe pas un marché qui n'est pas encore liquide.
10. Renommage des préfixes techniques (`abz_`, `dp_`, `amc_`) ; refonte du builder.
11. Les autres projets (Sunu Kesug, SunuDeal, voyage, change) : cette équipe n'y touche pas ce trimestre.
12. Guides PPTX complets : une fiche d'une page par rôle suffit pour le pilote.

### 8. Risques et parades (test de résistance du plan)

| # | Risque | Prob. ⚠ | Impact | Parade | Signal d'alerte |
|---|---|---|---|---|---|
| R1 | **Goulot humain** : fondateur salarié, au Maroc ; la vente aux concessionnaires est relationnelle et se fait sur place | Élevée | Bloquant pour P2 | Relais de confiance à Dakar 👤 ; à défaut, prospection à distance par WhatsApp et cible ramenée à 5 concessionnaires | < 5 entretiens au 30/11 |
| R2 | La QA révèle que les phases 2 à 4 sont cassées | Moyenne à élevée | Retard | Le pilote ne dépend que de la phase 1 + saisie directe ; corrections limitées à 2 semaines, sinon report de la phase concernée | > 10 bugs bloquants au 30/11 |
| R3 | Les concessionnaires ne tiennent pas leurs annonces à jour | Élevée | Annonces périmées, perte de confiance | Mise à jour concierge hebdomadaire par WhatsApp ; la règle de fraîcheur existante masque les annonces périmées | K2 fraîcheur < 50 % |
| R4 | Pas de demande sans budget publicitaire ; le SEO met plusieurs mois ⚠ | Élevée | NS non atteinte | Les concessionnaires partagent leurs liens Wotoyi sur leurs propres canaux ; contenus locaux ; cible NS volontairement modeste | < 10 contacts/semaine au 1/02 |
| R5 | Les concessionnaires veulent un site/CRM, pas des contacts d'un site tiers (ou l'inverse) | Inconnue | Mauvaise priorité | Les 15 entretiens tranchent ; **point de décision le 15/12** : garder le plan ou basculer P2 vers 3 partenaires SaaS | Synthèse des entretiens |
| R6 | Juridique : CGU et confidentialité à l'état de brouillon, formalités CDP non faites, entité qui encaisse non définie (LLC américaine ou structure sénégalaise), responsabilité sur l'inspection | Moyenne | Sens unique | `legal-compliance` + avis d'un professionnel 👤 avant toute collecte de données réelles ou encaissement | Aucune formalité engagée au 18/12 |
| R7 | Marque et domaine « Wotoyi » non vérifiés, domaine non acheté | Moyenne | Sens unique | Vérification de disponibilité semaine 1, puis achat immédiat recommandé 👤 (coût faible, perte du nom irréparable) | Nom pris |
| R8 | Tests uniquement sur SQLite | Certaine | Bugs en production | MariaDB en local (gratuit) dès la semaine 1, puis préproduction sur l'hébergeur réel | Écarts SQLite/MySQL |
| R9 | Dépôt public : code visible de tous, risque de fuite de secrets | Moyenne | Copie, incident | Passer le dépôt en privé 👤 ; contrôle de secrets à chaque livraison | — |
| R10 | Calendrier : fêtes de fin d'année, puis Ramadan vers début février 2027 ⚠ (date à vérifier) | Certaine | Activité commerciale différente | Recrutement des concessionnaires bouclé avant fin janvier | — |
| R11 | Concurrents installés sur les petites annonces auto au Sénégal ⚠ (non cartographiés) | Élevée | Différenciation faible | Cartographie semaine 2 ; positionnement sur la confiance (vendeurs vérifiés, annonces fraîches, inspection) | — |
| R12 | Exigence « ultra-premium, 4 langues, mode sombre » contre vitesse | Certaine | Dérive de périmètre | Écarts écrits au § 3, validés par le propriétaire ; aucune fonctionnalité sans problème client constaté | Tâches hors P1 à P3 |

Hypothèses critiques non testées (à attaquer en premier) : (a) des concessionnaires de Dakar acceptent de publier leur stock chez nous ; (b) ils paieront pour un site/CRM ou des contacts ; (c) des acheteurs paieront une inspection ; (d) le propriétaire dispose de 6 à 8 h par semaine et d'un relais sur place. Si (d) est fausse, l'objectif est irréaliste même réduit.

### 9. Décisions attendues du propriétaire 👤

| # | Décision | Recommandation du CEO | Échéance |
|---|---|---|---|
| D1 | Valider l'objectif « pilote » au lieu d'un lancement complet | Oui | 18/11 |
| D2 | Temps disponible et relais à Dakar (qui appelle et visite les concessionnaires ?) | 6 à 8 h/semaine + 1 relais ; sinon cible ramenée à 5 concessionnaires | 20/11 |
| D3 | Budget du trimestre : domaine + hébergement mutualisé + e-mail transactionnel | Plafond proposé 100 € ⚠ (montant à chiffrer par `finance-analyst`, T-P1-10, avant tout achat) | 27/11 |
| D4 | Acheter wotoyi.com maintenant plutôt qu'après les tests (révise une décision validée) | Oui, dès la vérification de marque (T-P1-09) | 27/11 |
| D5 | Écarts pour le pilote : français + wolof partiel ; anglais, arabe et mode sombre après G2 | Oui | 18/11 |
| D6 | Saisie directe d'annonces côté plateforme, en complément des sites concessionnaires | Oui | 18/11 |
| D7 | Entité qui exploite et encaisse ; budget pour un avis juridique ; formalités CDP | Avis professionnel nécessaire (le CEO n'est pas juriste) | 11/12 |
| D8 | Passer le dépôt GitHub en privé | Oui | 20/11 |
| D9 | Relecteur natif pour le wolof | À désigner | 11/12 |
| D10 | Conditions « partenaire fondateur » (durée de gratuité, contreparties) | Proposition de `sales-partnerships` (T-P2-04), à valider | 27/11 |

### 10. Jalons et revue

| Jalon | Date | Critère go / no-go |
|---|---|---|
| G0 | lun. 30/11/2026 | Résultats QA connus et triés ; MariaDB locale verte sur la phase 1 ; ≥ 8 entretiens ; D1 à D6 tranchées |
| Point pivot | mar. 15/12/2026 | 15 entretiens synthétisés : on garde le plan ou on bascule (R5) ; cibles recalibrées |
| G1 | ven. 18/12/2026 | Go pilote : socle vert en préproduction MySQL, 0 faille critique/haute, minimum juridique, marque Wotoyi, mesure des contacts |
| Ouverture pilote | sem. du 11/01/2027 | Seulement si G1 est franchi ; sinon report daté, écrit ici |
| G2 | lun. 01/02/2027 | 10 concessionnaires en ligne, ≥ 100 annonces, mesure fiable depuis 2 semaines |
| **Revue du trimestre** | **ven. 26/02/2027** | Résultat face à l'objectif, enseignements, décision T2 (monétiser, itérer ou pivoter) |

Cadence : revue hebdomadaire chaque lundi (indicateurs, pipeline, 1 enseignement client, 1 expérience tranchée) ; test de résistance mensuel (15/12, 18/01, 15/02).

**Responsable de la décision** : [CEO]. **Prochaine revue** : G0, 30/11/2026.

### 11. Écarts de documentation à corriger (proposition au propriétaire)

- `CLAUDE.md` § 13 décrit le plugin central comme incluant « paiements, inspection » : l'inspection n'existe pas dans le code ; le paiement est manuel uniquement.
- `CLAUDE.md` § 2.1 présente « modes clair et sombre partout » et « 4 langues » comme décisions validées : ce sont des cibles, pas l'état du code.

---

## 2026-11-16 — Décision du propriétaire : lancement complet + 4 langues

**Auteur** : [CEO] (`ceo-strategist`, session Fable) · **Origine** : décision du propriétaire du 16/11/2026 à 19 h 21 · **Statut** : décision prise (D1, D5) ; plan d'exécution ci-dessous en attente de validation CEO puis des décisions 👤 restantes · **Remplace** : l'option B (« pilote étroit ») du cadrage T1 pour D1 et D5, ainsi que ses § 3, 4, 5, 6, 7, 9 et 10. Le § 1 (état réel du code) et le § 8 (risques R1 à R12) restent valables.

### 1. La décision

Le propriétaire **refuse le pilote** et retient un **lancement complet** : marketplace, comptes et sites concessionnaires, inspection payante, assistant IA, dans **les 4 langues dès l'ouverture (FR, wolof, EN, AR/RTL)**, avec amélioration continue ensuite. Il demande aussi que le pôle SEO/GEO/SEA produise articles, blog et tout le contenu nécessaire. Le CEO exécute cette décision ; son rôle devient de rendre ce lancement aussi sûr et rapide que possible, sans cacher l'état réel.

### 2. Conséquences et risques, dits simplement

Le périmètre passe d'une phase testée à cinq phases jamais exécutées, plus un module inspection qui n'existe pas et une couche multilingue absente du code (aucun sélecteur de langue, aucun `hreflang`, aucune détection de locale, seul `fr_FR` traduit : vérifié par grep le 16/11). Le volume est d'environ 1 730 chaînes françaises (1 035 côté central, 700 côté concessionnaire ; comptage des fichiers `.l10n.php` du 16/11), soit environ 5 200 chaînes à écrire et environ 3 500 à faire relire par des locuteurs natifs (wolof, arabe), avant les textes de l'inspection et de l'IA. Le budget « zéro » ne tient plus : domaine et hébergement sont nécessaires dès fin novembre, puis hébergement des sites concessionnaires, API d'IA, WhatsApp, relecteurs, avis juridique (montants non chiffrés : rien n'est inventé ici, voir T-FIN-01). La charge du propriétaire augmente (relecteurs, inspecteurs à Dakar, vérification Meta, entité juridique) alors que le goulot humain R1 n'a pas changé. La preuve de marché reste à zéro : on construit tout avant de savoir qui paie. Estimation franche du CEO ⚠ : la Release 1 est tenable à sa date si D2, D3, D4 et D9 sont tranchées avant le 27/11 ; les Releases 3 et 4 ont un risque élevé de glisser au-delà de leur date, et la Release 4 dépend d'un délai externe (vérification Meta) que personne ici ne maîtrise. Les résultats de la QA en cours sont inconnus ⏸ ; ils peuvent déplacer tout le calendrier.

### 3. Principe d'exécution : un train de releases dans le lancement complet

Le lancement complet est livré en **4 mises en production datées**, chacune complète, dans les 4 langues, et chacune bloquée par les mêmes portes qualité. **Quand une porte échoue, la date glisse ; la barre ne baisse pas.**

| Release | Date cible | Go / no-go | Périmètre | Correctifs sécurité obligatoires avant | Dépend de |
|---|---|---|---|---|---|
| **Préproduction** (non publique) | ven. 18/12/2026 | — | Hébergeur réel, MySQL, HTTPS, e-mails, sauvegarde et restauration testées ; périmètre R1 complet en code ; 4 langues à 100 % (niveau N1) ; marque Wotoyi | — | D3, D4 |
| **R1 — Marketplace publique + comptes concessionnaires + 4 langues** | mar. 12/01/2027 | ven. 08/01 | Phase 1 (site, catalogue, fiches, SEO) ; phase 2 (formules, inscription, documents, validation, paiement manuel, abonnement) ; saisie directe d'annonces ; mesure des contacts ; sélecteur de langue, `hreflang`, RTL ; blog avec 8 articles ; bouton « Signaler une traduction » ; CGU et confidentialité | M5, M6, L2 (central), L4, L6, L7, L8 ; vérification des dossiers privés sur l'hébergeur (nginx) | Préproduction, D6, D7, D9 |
| **R2 — Sites + CRM concessionnaires + synchro** | mar. 02/02/2027 | ven. 29/01 | Phase 3 (thème AutoDealer, `/manager/`) ; phase 4 (connecteur, synchro signée, contacts vers le CRM) ; mode sombre (tokens) ; 3 premiers sites, puis à la demande | M2, M4, L1, L5, L2 (concessionnaire) | R1, coût par site (T-FIN-01) |
| **R3 — Inspection payante** | mar. 16/02/2027 | ven. 12/02 | Module v1 : bouton sur la fiche, formules et prix administrables, marge calculée, paiement manuel vérifié, statuts, assignation d'un inspecteur, rapport PDF à 4 mentions, espace acheteur | Revue sécurité complète du nouveau module (argent, données, fichiers) | R1, D7, D11 |
| **R4 — Assistant IA WhatsApp (texte)** | mar. 23/02/2027 | ven. 19/02 | Assistant acheteur public + assistant concessionnaire, 4 langues ; notes vocales seulement si un fournisseur de transcription est choisi (D12) | **H1, M1, M3, H2**, L3 ; accord de sous-traitance et mention de confidentialité (T-SEC-07) ; jeu d'évaluation ≥ 30 cas par langue avec injections | R2, D12, vérification Meta |
| Revue du trimestre | ven. 26/02/2027 | — | Résultat face à l'objectif, décision T2 | — | — |

Règles du train :
1. **Gel du code 5 jours ouvrés avant chaque release** ; ensuite, correctifs de bugs uniquement.
2. **Glissement par pas d'une semaine** (mardi suivant), écrit ici avec la cause. Une release en retard ne retarde pas les suivantes si elles n'en dépendent pas (R3 ne dépend que de R1).
3. Tout ce qui n'a pas passé ses portes reste **désactivé en production** (les interrupteurs existent déjà : IA, WhatsApp et téléphonie sont éteints par défaut).
4. Aucun déploiement sans accord écrit du propriétaire 👤 et sauvegarde préalable.
5. La **téléphonie (appels vocaux)** reste éteinte ce trimestre : fournisseur non choisi, règles ARTP/CDP non vérifiées. H2 est corrigé quand même (la voix ne donne jamais accès au CRM).

### 4. Portes qualité (identiques pour chaque release, toutes bloquantes)

| # | Porte | Preuve exigée | Responsable |
|---|---|---|---|
| Q1 | **QA verte** sur la préproduction MySQL pour tout le périmètre livré : 100 % des tests, parcours de bout en bout, mobile + ordinateur, 4 langues dont arabe RTL, chaque rôle | Sorties de commandes + captures, tableau cas · attendu · obtenu | `qa-engineer` |
| Q2 | **0 faille High ou Medium ouverte** sur le périmètre livré ; second passage après correctifs | Rapport daté dans `docs/` | `security-auditor` |
| Q3 | **Langues** : 0 chaîne manquante dans les 4 langues ; variables (`%s`) intactes ; captures RTL ; **textes critiques en wolof et en arabe relus par un locuteur natif (niveau N2)** | Rapport d'extraction + fiche de relecture signée 👤 | `localization-writer` + relecteurs 👤 |
| Q4 | **Juridique** : CGU, confidentialité, consentements, démarche CDP engagée ; pour R3, texte de limites de l'inspection ; pour R4, accord de sous-traitance | Liste de risques traitée ; avis professionnel 👤 là où il est exigé | `legal-compliance` |
| Q5 | **Exploitation** : zips inspectés, restauration prouvée, surveillance active, données de démo retirées | Sortie de `build-release.sh`, `unzip -l`, essai de restauration | `devops-release` |
| Q6 | **Validation CEO** : preuves rouvertes, commande clé relancée, puis accord écrit du propriétaire | Entrée datée ici | `ceo-strategist` puis 👤 |

### 5. Amélioration continue des langues (définition)

« Améliorer en continu » ne veut pas dire « publier n'importe quoi puis corriger ». Le processus :

1. **Glossaire unique** `docs/i18n/GLOSSARY.md` (à créer, T-L10N-02) : termes automobiles et d'interface dans les 4 langues, termes interdits (« car » au Sénégal), format des prix (`1 500 000 FCFA`), ton. Toute traduction s'y conforme ; il est mis à jour à chaque revue.
2. **Trois niveaux par chaîne** : **N0** brouillon (jamais publié) · **N1** rédigé par `localization-writer` + contrôles automatiques (chaînes manquantes, variables, longueur sur mobile, glossaire) : publiable, la langue porte une mention « bêta » · **N2** relu par un locuteur natif 👤 : la mention disparaît quand toute la langue est en N2. Personne n'écrit « vérifié » pour du wolof ou de l'arabe sans relecture native tracée.
3. **Textes critiques** (consentement, CGU et confidentialité, paiement, messages d'erreur de paiement, limites de l'inspection, confirmations de l'IA) : **N2 obligatoire avant publication**. À défaut de relecteur à la date, ces textes s'affichent en français accompagnés de la traduction N1 marquée « traduction indicative » ; **la version française fait foi**.
4. **Relecteurs natifs 👤** : un pour le wolof, un pour l'arabe (D9), travaillant par lots dans l'ordre : textes critiques → parcours acheteur → parcours concessionnaire → reste.
5. **Bouton « Signaler une traduction »** sur chaque page non française : page, langue, texte concerné, suggestion ; aucune donnée personnelle obligatoire ; limitation de débit ; file visible dans `/platform-admin/`.
6. **Revue mensuelle par `localization-writer`** (premier lundi : 01/02/2027, puis 01/03) : signalements traités, phrases mal comprises par l'IA, nouvelles chaînes, glossaire ; tri hebdomadaire des signalements, correction dans la release suivante.
7. **Le coach suit la qualité** dans son rapport nocturne : part de chaînes N2 par langue, signalements ouverts depuis plus de 7 jours, et toute affirmation « vérifié » sans preuve native (classée critique).
8. **IA (R4)** : jeu d'évaluation d'au moins 30 cas réels par langue, taux de réussite publié avant et après chaque changement ; wolof peu sûr → réponse en wolof simple + français.

### 6. Contenu SEO / GEO / SEA (demande du propriétaire)

- **Articles** : `seo-geo-specialist` rédige en français dans `content/` dès maintenant (rien n'est publiable avant R1) : **8 articles prêts pour R1**, puis **2 par semaine** ⚠ (rythme à confirmer selon la capacité de relecture). `localization-writer` livre EN et AR sous 7 jours, wolof pour 1 article sur 2 (limité par la relecture native), avec `hreflang`.
- **Règles de qualité** : faits vérifiables avec source et date ; **aucun prix, statistique ou classement inventé** ; pas de pages quasi identiques générées en masse (risque de pénalité pour contenu produit à grande échelle ⚠, consigne Google à revérifier par `seo-geo-specialist`) ; tout article sur l'inspection respecte la règle absolue du § 7.
- **GEO** : pages factuelles et citables (processus, limites, FAQ réelles), données structurées validées.
- **SEA** : `growth-marketer` prépare un plan prêt à lancer (structure de campagnes, mots-clés, pages d'atterrissage, suivi des conversions). **Aucune dépense** sans budget décidé par le propriétaire (D13) et sans mesure des contacts en production.

### 7. Règles maintenues

- **Inspection** : ne jamais garantir le kilométrage réel ni l'absence d'accident, ne jamais prétendre qu'un outil lit le kilométrage sur toutes les marques. Le rapport distingue **vérifié · anomalie détectée · donnée indisponible · limites de l'inspection**, dans les 4 langues, textes en N2. Aucune vente d'inspection avant D7 (entité qui encaisse, responsabilité) et D11 (inspecteurs).
- **IA** : contenu client = données, jamais instructions ; confirmation construite par le serveur ; validation humaine pour toute action sensible.
- Paiement : fournisseur manuel avec reçu vérifié ; uniquement des partenaires agréés.
- Préfixes techniques (`abz_`, `dp_`, `amc_`) inchangés. Dépôt public : aucun secret.
- Les entretiens concessionnaires (`mom-test`) continuent en parallèle : ils ne bloquent plus le lancement, mais ils décident des prix et des priorités après R1.

### 8. Objectif du trimestre (révisé)

> **Au 28 février 2027, Wotoyi est lancé publiquement à Dakar dans les 4 langues, avec les Releases 1 à 4 en production, chacune ayant passé ses portes qualité ; 10 concessionnaires actifs et 150 annonces réelles à jour ; au moins 30 contacts acheteurs qualifiés par semaine ; premiers encaissements réels (abonnements et inspections).**

Les cibles commerciales ne sont pas relevées : le périmètre a grandi, pas la capacité de vente sur le terrain (D2).

| # | Indicateur | Base au 16/11 | Cible 28/02/2027 | Nature |
|---|---|---|---|---|
| NS | Contacts qualifiés livrés / semaine | 0 ✅ (non instrumenté) | ≥ 30 | ⚠ à recalibrer 4 semaines après R1 (09/02) |
| K1 | Concessionnaires actifs (≥ 1 annonce mise à jour < 14 j) | 0 ✅ | 10 | ⚠ |
| K2 | Annonces réelles · part mise à jour < 7 j | 0 ✅ | 150 · ≥ 70 % | ⚠ |
| K3 | Liquidité : annonces avec ≥ 1 contact en 14 j | non mesurable ✅ | ≥ 30 % | ⚠ |
| K4 | Encaissements réels | 0 FCFA ✅ | ≥ 3 abonnements payés et ≥ 5 inspections payées | ⚠ ; aucun objectif de MRR (ce serait inventé) |
| K5 | Qualité par release | Phase 1 : 127/127 + 32/32 sur SQLite ✅ documenté ⏸ non relancé ; phases 2 à 6 ⏸ | 100 % des tests verts sur MySQL ; 0 High/Medium ouvert sur le périmètre livré ; 0 incident de données | engagement interne |
| K6 | Entretiens concessionnaires | 0 ✅ | 15 avant le 15/12 | engagement interne |
| K7 | Train de releases | 0/4 ✅ | 4/4 livrées avec portes passées ; glissement cumulé mesuré en jours | ⚠ R3 et R4 à risque élevé |
| K8 | Langues | FR 100 %, wolof 0 %, EN source, AR 0 % ✅ | 0 chaîne manquante × 4 langues à chaque release ; textes critiques 100 % N2 ; ≥ 50 % de l'ensemble en N2 pour wolof et arabe ; signalements traités ≤ 7 j | ⚠ dépend de D9 |
| K9 | Contenu | 0 article publié ✅ | 8 articles à R1, puis 2 par semaine ; visites organiques mesurées (pas de cible avant 4 semaines de données) | ⚠ |
| K10 | Dépenses | 0 ✅ | Dépense réelle ≤ budget validé (D3) ; 0 achat sans décision écrite | engagement interne |

### 9. Les 3 priorités (révisées)

1. **P1 — Livrer le train R1 à R4 en passant toutes les portes.** Responsables : ingénierie, qualité et sécurité, `localization-writer`, `legal-compliance`, `devops-release`.
2. **P2 — Recruter l'offre : 10 concessionnaires de Dakar avec annonces réelles** avant et pendant R1 (inchangée ; propriétaire ou relais 👤 pour les appels et visites).
3. **P3 — Créer la demande et encaisser** : contenu SEO/GEO, canaux gratuits, mesure des contacts, premières inspections et premiers abonnements payés.

### 10. Pas maintenant (liste révisée)

Sortent de la liste (désormais dans le trimestre) : assistant WhatsApp texte, module inspection, anglais, arabe/RTL, wolof complet, mode sombre, sites concessionnaires au-delà du pilote.
Restent refusés ce trimestre : téléphonie et appels vocaux ; passerelles automatiques Wave / Orange Money / cartes (paiement manuel avec reçu ; hypothèse du CEO, à rouvrir si le volume le justifie) ; provisioning automatique des sites concessionnaires ; publicité payante sans budget décidé (D13) ; annonces mises en avant payantes et commissions ; PWA et nouvelles animations ; autres pays et autres villes ; renommage des préfixes ; autres projets (Sunu Kesug, SunuDeal, voyage, change).

### 11. Risques ajoutés par cette décision (complètent R1 à R12)

| # | Risque | Prob. ⚠ | Parade | Signal d'alerte |
|---|---|---|---|---|
| R13 | La QA révèle des phases 2 à 6 lourdement cassées : tout le train glisse | Moyenne à élevée | Tri au 30/11 ; correctifs par release, dans l'ordre du train ; glissement écrit | > 10 bugs bloquants sur le périmètre R1 au 30/11 |
| R14 | Pas de relecteur natif à temps : wolof et arabe publiés en N1 | Élevée | Mention « bêta », textes critiques en français faisant foi, bouton de signalement | D9 non tranchée au 27/11 |
| R15 | Module inspection à construire de zéro en 6 semaines, avec une opération réelle (inspecteurs, responsabilité) | Élevée | Version 1 minimale, paiement manuel ; pas de vente sans D7 et D11 | Spécification non validée au 18/12 |
| R16 | Vérification Meta et coûts d'API : délai et dépense hors de notre contrôle | Élevée | Lancer la démarche dès le 04/12 (D12) ; plafond de dépense quotidien ; R4 glisse seule | Vérification non obtenue au 05/02 |
| R17 | Budget : le lancement complet a un coût mensuel récurrent avant tout revenu | Certaine | Chiffrage T-FIN-01 avant tout achat ; K10 | Achat sans décision écrite |
| R18 | Contenu produit en volume par IA : qualité faible, pénalité de référencement, erreurs factuelles | Moyenne | Règles du § 6, sources obligatoires, validation CEO par échantillon | Article sans source |
| R19 | Dispersion : 4 releases + contenu + ventes avec un propriétaire salarié | Élevée | Train séquentiel, une release à la fois en préproduction, liste « Pas maintenant » | Tâches hors train |
| R20 | Ramadan vers le 8 février 2027 ⚠ (date à vérifier) : R2 à R4 tombent pendant | Certaine | Recrutement des concessionnaires bouclé fin janvier ; contenus adaptés | — |

### 12. Décisions du propriétaire 👤 (liste à jour)

Tranchées le 16/11 : **D1** (lancement complet, pas de pilote) · **D5** (4 langues dès l'ouverture, amélioration continue).

| # | Décision attendue | Recommandation du CEO | Échéance |
|---|---|---|---|
| D2 | Temps disponible et relais à Dakar (appels, visites, collecte du stock) | 8 à 10 h par semaine + 1 relais ; sans relais, K1 ramené à 5 | 20/11 |
| D6 | Saisie directe d'annonces côté plateforme, en complément des sites concessionnaires | Oui (sinon aucune annonce possible à R1, les sites n'arrivant qu'à R2) | 18/11 |
| D8 | Passer le dépôt GitHub en privé | Oui | 20/11 |
| D4 | Acheter le domaine maintenant (révise « achat après tests ») | Oui, dès la vérification de marque (T-P1-09, 20/11) | 23/11 |
| D3 | Budget du trimestre : domaine, hébergement central + préproduction, e-mail, sites concessionnaires, API d'IA, WhatsApp, relecteurs, avis juridique | À décider sur le chiffrage T-FIN-01 (livré le 23/11) ; aucun montant avancé avant | 25/11 |
| D7 | Entité qui exploite et encaisse (LLC américaine ou structure sénégalaise), avis juridique, formalités CDP | Avis professionnel nécessaire ; bloquant pour R1 (données personnelles), R3 (encaissement, responsabilité) et la vérification Meta | **27/11** (avancée) |
| D9 | Relecteurs natifs : **un pour le wolof, un pour l'arabe** | À désigner ; sans eux, ces langues sortent en « bêta » | **27/11** (avancée) |
| D10 | Conditions « partenaire fondateur » | Proposition T-P2-04 à valider | 27/11 |
| D12 | IA et WhatsApp : compte Meta Business, numéro, vérification, clé d'API, fournisseur de transcription, plafond de dépense | Lancer la démarche Meta tôt, car le délai est externe | 04/12 |
| D11 | Inspection : inspecteurs ou garages partenaires à Dakar, formules et prix, qui gère les rendez-vous | Prix relevés auprès d'au moins 2 garages 👤 avant de fixer les formules | 18/12 |
| D14 | Mode sombre livré avec R2 (et non R1) | Oui : il ne doit pas retarder R1 (hypothèse du CEO, le propriétaire ne l'a pas précisé) | 18/11 |
| D13 | Budget publicitaire (SEA) | 0 par défaut ; décision sur 2 semaines de conversions organiques mesurées | 26/01 |
| D15 | Accord écrit avant chaque mise en production | Requis à chaque go / no-go | 08/01, 29/01, 12/02, 19/02 |

### 13. Jalons et revue

| Jalon | Date | Critère |
|---|---|---|
| G0 | lun. 30/11/2026 | QA triée ; MariaDB locale verte (phase 1) ; ADR multilingue et ADR saisie directe ; glossaire v1 ; T-SEC-01 et 02 livrés ; D2, D3, D4, D6, D7, D8, D9 tranchées |
| Revue marché | mar. 15/12/2026 | 15 entretiens synthétisés ; cibles et prix recalibrés (n'arrête pas le train) |
| Préproduction | ven. 18/12/2026 | Voir § 3 |
| R1 | mar. 12/01/2027 | Portes Q1 à Q6 |
| R2 | mar. 02/02/2027 | Portes Q1 à Q6 |
| R3 | mar. 16/02/2027 | Portes Q1 à Q6 |
| R4 | mar. 23/02/2027 | Portes Q1 à Q6 |
| Revue du trimestre | ven. 26/02/2027 | Résultat, enseignements, décision T2 |

Cadence : revue hebdomadaire chaque lundi (indicateurs, train, pipeline, 1 enseignement client) ; test de résistance du plan le 15/12, le 18/01 et le 15/02.

**Hypothèses non vérifiées de ce plan** : capacité des agents à corriger les phases 2 à 6 dans les délais (QA inconnue ⏸) ; disponibilité de relecteurs et d'inspecteurs ; délai Meta ; coûts ; date du Ramadan ; rythme de 2 articles par semaine. **Vérifié le 16/11** : absence de couche multilingue, de mode sombre et de module inspection dans le code ; volume de chaînes ; 8 constats High/Medium de l'audit.

**Écart de documentation à corriger (proposition au propriétaire)** : `CLAUDE.md` § 13 indique encore « Cap du trimestre : pilote Dakar » ; à remplacer par « lancement complet par train de releases R1 à R4 ». Le CEO ne modifie pas ce fichier sans accord.

**Responsable** : [CEO]. **Prochaine revue** : G0, 30/11/2026.
