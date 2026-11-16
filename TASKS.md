# TASKS.md — Wotoyi · T1 (17 nov. 2026 – 28 fév. 2027)

> Source : `DECISIONS.md`, entrée « 2026-11-16 — Cadrage T1 ». Objectif : pilote Dakar, 10 concessionnaires, 150 annonces, ≥ 30 contacts qualifiés/semaine, première preuve de paiement.
> Statuts : ⏸ à faire · ▶ en cours · ✅ validé CEO · 🔁 renvoyé · ❌ abandonné (raison écrite) · 👤 action du propriétaire.
> Règles : tout code passe par `code-reviewer` → `security-auditor` (argent, données, auth, envois de fichiers) → `qa-engineer` → validation CEO. Aucune tâche hors P1 à P3 sans décision écrite. Une tâche = une conversation courte. Chaque agent journalise dans `.claude/coaching/WORKLOG.md`.
> Les agents ne peuvent ni appeler ni visiter : toute tâche marquée 👤 est exécutée par le propriétaire ou son relais à Dakar, avec les supports préparés par les agents.

## Jalons

| Mois | Jalon | Date | Résultat attendu |
|---|---|---|---|
| Novembre | G0 | 30/11/2026 | QA triée, MariaDB locale verte (phase 1), ≥ 8 entretiens, décisions D1 à D6 prises |
| Décembre | Point pivot | 15/12/2026 | 15 entretiens synthétisés, plan confirmé ou basculé, cibles recalibrées |
| Décembre | G1 | 18/12/2026 | Go/no-go pilote : préproduction MySQL verte, 0 faille critique/haute, minimum juridique, marque Wotoyi, mesure des contacts |
| Janvier | Ouverture pilote | sem. du 11/01/2027 | Site en ligne, premiers concessionnaires et annonces réelles |
| Janvier | G2 | 01/02/2027 | 10 concessionnaires, ≥ 100 annonces, 2 semaines de mesure fiable |
| Février | Revue du trimestre | 26/02/2027 | Résultat face à l'objectif, dossier économique, décision T2 |

---

## Semaines 1 et 2 (17 – 29 novembre) : très concret

### Semaine 1 (17 – 22 nov.)

| id | tâche | agent | modèle | dépend de | statut | critère d'acceptation |
|---|---|---|---|---|---|---|
| T-P1-01 | Relancer la suite de la phase 1 sur un site local SQLite (base de référence du jour) | qa-engineer | sonnet | — | ⏸ à faire | Sorties complètes de `php tools/tests/run.php` et `node tools/tests/browser.js` jointes ; nombre de tests réussis/échoués noté ; tout écart avec 127/127 et 32/32 signalé |
| T-P1-02 | Monter un site local sur MariaDB/MySQL (gratuit) et y relancer la suite | qa-engineer | sonnet | T-P1-01 | ⏸ à faire | Même suite exécutée sur MySQL ; tableau des écarts SQLite/MySQL ; procédure de montage écrite dans `docs/INSTALL.md` |
| T-P1-03 | QA manuelle + automatisée de la phase 2 (inscription, documents, validation, paiement manuel, abonnement, expiration) selon `docs/06-roadmap.md` | qa-engineer | sonnet | T-P1-01 | ⏸ à faire (lancée le 16/11) | Tableau cas · attendu · obtenu · statut · preuve ; bugs classés bloquant/important/mineur ; cas ajoutés à `tools/tests/` |
| T-P1-04 | QA de la phase 4 (synchro signée, idempotence, signature altérée → 401, révocation, formulaire « Contacter le vendeur ») et de la phase 3 (site + CRM concessionnaire) | qa-engineer | sonnet | T-P1-01 | ⏸ à faire (lancée le 16/11) | Idem T-P1-03 ; chaque critère d'acceptation de la feuille de route marqué ✅/❌/⏸ avec preuve |
| T-P1-05 | Audit de sécurité du périmètre pilote : formulaires publics, envoi de documents, `/platform-admin/`, API de synchro, cloisonnement entre concessionnaires | security-auditor | opus | — | ⏸ à faire | Liste classée critique/haut/moyen/bas avec fichier:ligne, scénario et correctif ; `docs/SECURITY-REVIEW.md` mis à jour |
| T-P1-06 | ADR « saisie directe d'annonces » : ajout d'annonces et de photos par l'équipe plateforme pour un concessionnaire sans site connecté ; cohabitation avec la synchro (pas d'écrasement, source identifiée) | software-architect | inherit (Fable) | D6 👤 | ⏸ à faire | ADR dans `DECISIONS.md` : options, décision, modèle de données, permissions, non-objectifs ; réutilise `Vehicles::upsert` ; charge estimée |
| T-P1-07 | Spécification de la mesure des contacts (clic WhatsApp, clic téléphone, formulaire) : définition « qualifié », dédoublonnage 24 h, exclusion robots/équipe, tableau hebdomadaire dans `/platform-admin/`, aucune donnée personnelle superflue | product-manager | sonnet | — | ⏸ à faire | User stories + critères Given/When/Then ; validée par `legal-compliance` sur les données collectées |
| T-P1-08 | Inventaire des textes visibles « AutoBuzy » à remplacer par « Wotoyi » (sans toucher aux préfixes, slugs techniques ni noms de tables) | wp-theme-engineer | sonnet | — | ⏸ à faire | Liste fichier:ligne ; plan de remplacement réglable sans code (réglages/customizer) ; aucun changement de code à ce stade |
| T-P1-09 | Vérifier la disponibilité de « Wotoyi » : domaine `.com` et `.sn`, marques (OAPI et bases publiques), comptes sociaux | legal-compliance | opus | — | ⏸ à faire | Résultat par registre avec lien source et date ; ce qui exige un professionnel est nommé ; recommandation acheter / ne pas acheter |
| T-P1-10 | Chiffrer le coût minimal du pilote : domaine, hébergement mutualisé PHP 8.1+/MySQL avec cron et sauvegardes, e-mail transactionnel (offre gratuite si possible) | finance-analyst | inherit (Fable) | — | ⏸ à faire | Tableau à 3 offres avec prix relevés sur les sites officiels (lien + date) ; total trimestre et annuel ; rien d'inventé |
| T-P2-01 | Liste de 40 concessionnaires et vendeurs professionnels de Dakar (nom, quartier, téléphone/WhatsApp public, présence en ligne, taille de stock visible) à partir de sources publiques | sales-partnerships | sonnet | — | ⏸ à faire | Tableur ou tableau Markdown ; chaque ligne avec sa source ; données professionnelles publiques uniquement ; fichier hors dépôt public si coordonnées (voir D8) |
| T-P2-02 | Guide d'entretien `mom-test` (15 min) : comment ils vendent aujourd'hui, où viennent leurs acheteurs, ce qu'ils paient déjà, comment ils gèrent stock et clients ; aucune question sur notre idée | sales-partnerships | sonnet | — | ⏸ à faire | 10 questions sur des faits passés ; grille de prise de notes ; signaux d'engagement à repérer ; version WhatsApp vocale en français, wolof marqué 👤 |
| T-P2-03 | 👤 Décisions D1, D2, D5, D6, D8 (voir `DECISIONS.md` § 9) | propriétaire | — | — | 👤 | Réponses écrites dans `DECISIONS.md` |
| T-P2-03b | 👤 5 premiers entretiens concessionnaires (appel ou visite par le relais) | propriétaire / relais | — | T-P2-01, T-P2-02 | 👤 | 5 grilles remplies, remises à `sales-partnerships` |

### Semaine 2 (23 – 29 nov.)

| id | tâche | agent | modèle | dépend de | statut | critère d'acceptation |
|---|---|---|---|---|---|---|
| T-P1-11 | Tri des résultats QA : pour chaque bug, corriger maintenant / reporter / hors pilote ; décider si les phases 2, 3 et 4 entrent dans le pilote | ceo-strategist | inherit (Fable) | T-P1-03, T-P1-04, T-P1-05 | ⏸ à faire | Décision écrite dans `DECISIONS.md` ; tâches de correction créées ici avec agent et modèle ; périmètre du pilote figé |
| T-P1-12 | Corriger les bugs bloquants et les failles critiques/hautes du périmètre pilote (durée limitée à 2 semaines) | wp-plugin-engineer | opus | T-P1-11 | ⏸ à faire | Chaque correction : test qui échouait puis passe, `php -l`, revue `code-reviewer`, re-test `qa-engineer` avec preuve |
| T-P1-13 | Développer la saisie directe d'annonces selon l'ADR | wp-plugin-engineer | opus | T-P1-06 | ⏸ à faire | Un gestionnaire plateforme crée, modifie, marque vendue et supprime une annonce avec photos pour un concessionnaire ; permissions côté serveur, nonces, contrôle des fichiers envoyés ; tests ajoutés ; visible sur le catalogue |
| T-P1-14 | Développer la mesure des contacts selon T-P1-07 | wp-plugin-engineer | opus | T-P1-07 | ⏸ à faire | Événements enregistrés via la routine de migration versionnée ; tableau hebdomadaire par concessionnaire et par annonce ; limitation de débit ; tests ajoutés ; revue sécurité passée |
| T-P1-15 | Relire CGU et politique de confidentialité (brouillons), formalités CDP (loi 2008-12), mentions et consentement des formulaires | legal-compliance | opus | — | ⏸ à faire | Liste de risques haut/moyen/bas, clauses exactes proposées, démarches CDP avec source officielle, ce qui exige un juriste (D7) |
| T-P2-04 | Offre « partenaire fondateur » : gratuité pendant le pilote, contreparties (stock à jour chaque semaine, retour hebdomadaire, témoignage), ce qui deviendra payant et quand | sales-partnerships | sonnet | T-P2-03b | ⏸ à faire | Une page en français ; aucun prix futur inventé (fourchettes marquées hypothèse, à valider par `finance-analyst`) ; résumé d'accord simple relu par `legal-compliance` |
| T-P2-05 | Scripts de prospection : premier message WhatsApp, relance, appel, visite, réponses aux objections | sales-partnerships | sonnet | T-P2-02 | ⏸ à faire | Français prêt à l'emploi ; wolof rédigé par `localization-writer` et marqué 👤 jusqu'à relecture native |
| T-P2-06 | Cartographie des alternatives des concessionnaires et acheteurs à Dakar (sites d'annonces, réseaux sociaux, bouche-à-oreille) et première ébauche de positionnement | growth-marketer | sonnet | — | ⏸ à faire | Tableau alternative · forces · faiblesses · source vérifiée (lien + date) ; aucune part de marché inventée ; 1 phrase de positionnement à tester en entretien |
| T-P2-07 | 👤 Entretiens 6 à 10 ; décisions D3, D4, D10 | propriétaire / relais | — | T-P1-09, T-P1-10, T-P2-04 | 👤 | ≥ 8 entretiens cumulés au 30/11 ; décisions écrites |
| T-P1-16 | Revue G0 : état QA, sécurité, entretiens, décisions ; mise à jour de ce fichier | ceo-strategist | inherit (Fable) | tout ce qui précède | ⏸ à faire | Entrée datée 30/11 dans `DECISIONS.md` : go / ajustement, avec preuves relues |

---

## Décembre : rendre le socle prêt (G1 le 18/12)

| id | tâche | agent | modèle | dépend de | statut | critère d'acceptation |
|---|---|---|---|---|---|---|
| T-P1-17 | Passage à la marque Wotoyi sur tout ce qui est visible (textes, logo, titres, e-mails, JSON-LD, fichiers de langue) | wp-theme-engineer | sonnet | T-P1-08, D4 | ⏸ à faire | Aucune occurrence visible de « AutoBuzy » sur les pages publiques (preuve par recherche + captures) ; préfixes et tables inchangés ; suite de tests verte |
| T-P1-18 | Textes français du pilote : relecture, « auto / voiture » au lieu de « car », prix au format `1 500 000 FCFA` ; wolof sur les textes acheteurs clés (accueil, recherche, fiche, contact) | localization-writer | sonnet | T-P1-17 | ⏸ à faire | Fichiers `.l10n.php` régénérés par `tools/dev/i18n-extract.php` ; 0 chaîne manquante en français ; wolof marqué 👤 tant que D9 n'a pas relu |
| T-P1-19 | Performance mobile du catalogue et de la fiche sur hébergement mutualisé (requêtes, cache, images) | data-performance-engineer | sonnet | T-P1-12 | ⏸ à faire | Mesures avant/après (nombre de requêtes, TTFB, LCP, CLS) en profil mobile lent ; aucune régression des tests |
| T-P1-20 | SEO du pilote : titres, descriptions, canonicals, sitemap, JSON-LD validé, pages Dakar ; site de préproduction en `noindex` | seo-geo-specialist | sonnet | T-P1-17 | ⏸ à faire | Rapport problème · impact · correctif · vérification ; JSON-LD validé ; préproduction non indexable, production indexable |
| T-P1-21 | Préproduction sur l'hébergement réel (MySQL) : installation, cron, e-mail transactionnel, HTTPS, sauvegarde et **restauration testée** | devops-release | haiku (escalade sonnet si blocage) | D3, T-P1-12 | 👤 achat hébergement puis ⏸ | Zips construits par `tools/dev/build-release.sh`, inspectés (ni `.git`, ni tests, ni secrets) ; étapes de `docs/DEPLOYMENT.md` suivies et corrigées ; restauration prouvée |
| T-P1-22 | QA complète en préproduction, mobile et ordinateur, rôles administrateur / gestionnaire plateforme / visiteur, envoi réel d'e-mails | qa-engineer | sonnet | T-P1-21 | ⏸ à faire | 100 % des tests verts sur MySQL ; parcours visiteur → contact vérifié de bout en bout avec captures ; contact compté dans le tableau de mesure |
| T-P1-23 | Contre-audit de sécurité avant ouverture | security-auditor | opus | T-P1-22 | ⏸ à faire | 0 faille critique/haute ouverte ; risques résiduels écrits dans `docs/SECURITY-REVIEW.md` |
| T-P1-24 | Fiches d'une page : « ajouter et mettre à jour une annonce » (équipe), « votre vitrine Wotoyi » (concessionnaire) | docs-trainer | haiku | T-P1-13 | ⏸ à faire | Étapes numérotées, une action par étape, uniquement des fonctions testées ✅ |
| T-P2-08 | Synthèse des 15 entretiens : problèmes réels, dépenses actuelles, signaux d'engagement, segments ; recommandation garder/basculer | product-manager | sonnet | T-P2-07 + entretiens 11 à 15 👤 | ⏸ à faire | Citations et faits, pas d'opinions ; tableau par concessionnaire ; livrée avant le 15/12 |
| T-P2-09 | Point pivot du 15/12 : confirmer ou modifier P2/P3, recalibrer les cibles NS et K1 à K4 | ceo-strategist | inherit (Fable) | T-P2-08 | ⏸ à faire | Entrée datée dans `DECISIONS.md` ; test de résistance du plan refait |
| T-P2-10 | Processus d'accueil concierge : collecte du stock et des photos par WhatsApp, contrôle qualité des photos, mise en ligne sous 48 h, routine hebdomadaire de mise à jour | customer-ops | haiku | T-P1-13 | ⏸ à faire | Fiche qui fait quoi, quand, avec quel statut ; modèles de messages en français (wolof 👤) ; indicateur hebdomadaire de fraîcheur |
| T-P2-11 | 👤 Signer les 5 premiers partenaires fondateurs et collecter leur stock | propriétaire / relais | — | T-P2-04, T-P2-05 | 👤 | 5 accords écrits (message WhatsApp accepté) ; stock et photos reçus |
| T-P3-01 | Conception du test « inspection concierge » : formule unique, prix à tester, inspecteur partenaire, parcours manuel (demande par WhatsApp, paiement manuel, rapport PDF hors plateforme) | finance-analyst | inherit (Fable) | T-P2-08 | ⏸ à faire | Dossier au format `business-case` ; coût de l'inspecteur relevé auprès d'au moins 2 garages 👤 (pas d'estimation inventée) ; marge calculée avec formule ; seuil de succès défini à l'avance |
| T-P3-02 | Texte de limites de l'inspection et modèle de rapport (vérifié · anomalie détectée · donnée indisponible · limites) ; jamais de garantie de kilométrage ni d'absence d'accident | legal-compliance | opus | T-P3-01 | ⏸ à faire | Clauses exactes ; modèle de rapport ; points à faire valider par un juriste (D7) |
| T-P1-25 | Revue G1 : go / no-go de l'ouverture du pilote | ceo-strategist | inherit (Fable) | T-P1-22, T-P1-23, T-P1-15 | ⏸ à faire | Liste de contrôle G1 remplie avec preuves rouvertes par le CEO ; décision datée ; en cas de no-go, nouvelle date et causes |

## Janvier : ouvrir et remplir (ouverture sem. du 11/01, G2 le 01/02)

| id | tâche | agent | modèle | dépend de | statut | critère d'acceptation |
|---|---|---|---|---|---|---|
| T-P1-26 | Mise en production (domaine, HTTPS, cron, sauvegardes quotidiennes, surveillance de disponibilité gratuite) | devops-release | haiku | T-P1-25, accord écrit 👤 | ⏸ à faire | Accord explicite du propriétaire avant déploiement ; sauvegarde prise avant ; suite de contrôle post-déploiement verte ; données de démo retirées |
| T-P2-12 | Mise en ligne du stock des 5 premiers partenaires par la saisie directe | customer-ops (processus) + 👤 saisie | haiku | T-P1-26, T-P2-11 | ⏸ à faire | Chaque annonce : photos réelles, prix, kilométrage affiché tel que déclaré par le vendeur, contact vérifié ; délai de mise en ligne mesuré |
| T-P2-13 | 👤 Partenaires 6 à 10 signés et mis en ligne | propriétaire / relais | — | T-P2-12 | 👤 | 10 concessionnaires actifs (K1) ; ≥ 100 annonces au 01/02 |
| T-P2-14 | Sites + CRM concessionnaires pour 2 ou 3 partenaires volontaires, **uniquement si** les phases 3 et 4 ont passé la QA (T-P1-11) | devops-release, qa-engineer | haiku, sonnet | T-P1-11, D3 | ⏸ à faire (conditionnelle) | Site installé, connecté par code, annonce publiée → visible sur la marketplace, contact reçu dans le CRM ; coût par site chiffré |
| T-P3-03 | Canaux gratuits : kit de partage pour concessionnaires (lien et visuel de leur vitrine Wotoyi), publications sur les canaux sociaux pertinents, liens suivis par canal | growth-marketer | sonnet | T-P1-26, T-P2-06 | ⏸ à faire | Un objectif mesurable par canal ; liens balisés ; rapport hebdomadaire visites → contacts par canal ; repères externes marqués hypothèse |
| T-P3-04 | Revue hebdomadaire des indicateurs (NS, K1 à K6) chaque lundi | ceo-strategist | inherit (Fable) | T-P1-14 | ⏸ à faire | Tableau daté ✅ mesuré / ⚠ estimé ; 1 enseignement client ; 1 expérience tranchée (arrêter, garder, amplifier) |
| T-P3-05 | Ajouter « Demander une inspection » sur les fiches (lien WhatsApp ou formulaire existant, sans nouveau module) et lancer le test | wp-theme-engineer + customer-ops | sonnet, haiku | T-P3-01, T-P3-02, D7 | ⏸ à faire | Bouton réglable sans code ; demandes comptées ; parcours manuel documenté ; texte de limites affiché avant paiement |
| T-P1-27 | Revue G2 | ceo-strategist | inherit (Fable) | T-P2-13 | ⏸ à faire | Entrée datée 01/02 ; décision sur le report ou non de l'anglais, de l'arabe et du mode sombre |

## Février : prouver la demande et le paiement (revue le 26/02)

| id | tâche | agent | modèle | dépend de | statut | critère d'acceptation |
|---|---|---|---|---|---|---|
| T-P3-06 | Test d'engagement SaaS : proposer aux partenaires l'offre payante post-pilote avec acompte | sales-partnerships + 👤 | sonnet | T-P2-13, T-P3-07 | ⏸ à faire | Offre écrite ; résultat compté en acomptes réellement versés (pas en promesses) ; raisons des refus notées |
| T-P3-07 | Prix et formules SaaS à tester, à partir des entretiens et de ce que les concessionnaires paient déjà | finance-analyst | inherit (Fable) | T-P2-08 | ⏸ à faire | 2 ou 3 formules en FCFA ; chaque chiffre ✅ source ou ⚠ hypothèse ; coût d'hébergement par site inclus |
| T-P3-08 | Mesure de l'adéquation côté concessionnaires : contacts reçus jugés utiles, ventes attribuées, question « très déçu si Wotoyi disparaissait » | product-manager + 👤 | sonnet | 4 semaines de pilote | ⏸ à faire | Réponses des 10 partenaires ; résultats bruts, sans interprétation flatteuse |
| T-P3-09 | Dossier économique T2 : 3 scénarios, seuil de rentabilité, coût par concessionnaire actif | finance-analyst | inherit (Fable) | T-P3-06, T-P3-05 | ⏸ à faire | Format `business-case` complet ; formules visibles ; données mesurées séparées des hypothèses |
| T-P1-28 | Si G2 tenu : mode sombre par les tokens du design system ; anglais ; arabe/RTL à cadrer pour le T2 | ux-ui-designer, frontend-engineer, localization-writer | sonnet | T-P1-27 | ⏸ à faire (conditionnelle) | Tokens d'abord dans `shared/design-system/` ; contraste AA ; tests navigateur clair/sombre ; aucun retard sur P2/P3 |
| T-P3-10 | Revue du trimestre et cadrage T2 | ceo-strategist | inherit (Fable) | tout | ⏸ à faire | Entrée datée 26/02 dans `DECISIONS.md` : résultat face à l'objectif, écarts expliqués, décision monétiser / itérer / pivoter |

---

## Hors backlog ce trimestre

Voir `DECISIONS.md` § 7 « Pas maintenant ». Toute demande qui y figure est refusée ou renvoyée à la revue du 26/02, sauf décision écrite du propriétaire.

---

## Corrections de l'audit sécurité du 16/11 (`docs/SECURITY-AUDIT-2026-11-16.md`) — rattachées à P1

Règle : tout ce qui touche le pilote (marketplace, adhésion, paiement manuel, synchro) est corrigé **avant G1 (18/12)** ; les correctifs IA/voix (H1, H2, M1, M3) sont obligatoires **avant toute activation WhatsApp/voix** (hors périmètre du trimestre).

| id | tâche | agent | modèle | dépend de | statut | critère d'acceptation |
|---|---|---|---|---|---|---|
| T-SEC-01 | M6 : réglages (paiement, secrets) réservés à `manage_options` + `Audit::log` + e-mail au propriétaire sur changement `pay_*` | wp-plugin-engineer | opus | — | ⏸ à faire | POST `settings_save` en platform manager → refusé ; en admin → ligne d'audit |
| T-SEC-02 | M5 : chiffrer la clé Anthropic (`Crypto::encrypt`, `Settings::secret`) + migration | wp-plugin-engineer | opus | — | ⏸ à faire | `wp option get amc_settings` ne montre pas `sk-ant-` |
| T-SEC-03 | M2 : limite de plan et modération dans `patch_status` ; colonne `hidden_by_staff` (409 `amc_moderated`) | wp-plugin-engineer | opus | — | ⏸ à faire | Test : limite pleine → PUT non publié → PATCH actif = 409 ; annonce masquée par l'équipe non republiable |
| T-SEC-04 | M4 : HTTPS obligatoire au handshake (sauf WP_DEBUG), Idempotency-Key signée, `X-AMC-Delivery` sur `/ai/execute` | wp-plugin-engineer | opus | — | ⏸ à faire | handshake `http://` → 400 ; requête rejouée rejetée |
| T-SEC-05 | L1, L4, L5, L6 (clickjacking CRM, anti-bot, droits documents, flood signatures) | wp-plugin-engineer | sonnet | T-SEC-01 | ⏸ à faire | Un test par point dans `tools/tests/` |
| T-SEC-06 | L7 : exclure examples/scripts/fixtures du SDK dans les zips | devops-release | haiku | — | ⏸ à faire | `unzip -l` sans ces dossiers |
| T-SEC-07 | L2 + accord de sous-traitance Anthropic + mention politique de confidentialité | legal-compliance | opus | — | ⏸ à faire | Liste des tables couvertes par l'effacement ; clause rédigée ; points pour avocat 👤 |
| T-SEC-08 | H1, M1, H2, M3 (confirmation construite par le serveur, oui exact, voix sans accès CRM, OTP WhatsApp) | ai-agent-engineer | opus | — | ⏸ hors trimestre (bloquant avant activation IA) | Jeu d'évaluation avec injection ; tests unitaires `yes_no()` |
| T-SEC-09 | Seconde revue sécurité après correctifs, puis QA | security-auditor → qa-engineer | opus → sonnet | T-SEC-01…07 | ⏸ à faire | 0 High/Medium ouvert sur le périmètre pilote |

