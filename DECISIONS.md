# DECISIONS.md — Wotoyi

> Journal des décisions. Une entrée = contexte · options · décision · responsable · date de revue.
> Légende : ✅ vérifié/mesuré · ⚠ hypothèse · ⏸ non testé · 👤 décision du propriétaire requise.
> Les exécutants lisent ce fichier au lieu de refaire la réflexion. Backlog associé : `TASKS.md`.

---

## 2026-11-16 — Cadrage T1 (17 nov. 2026 – 28 fév. 2027)

**Auteur** : [CEO] (`ceo-strategist`, session Fable) · **Statut** : proposé, en attente de validation propriétaire 👤 · **Type** : porte à double sens (réversible à chaque jalon), sauf marque/domaine/juridique (sens unique → 👤).

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
