# TASKS.md — Wotoyi · T1 (17 nov. 2026 – 28 fév. 2027)

> Source : `DECISIONS.md`, entrée « 2026-11-16 — Décision du propriétaire : lancement complet + 4 langues » (remplace le plan « pilote »).
> Objectif : lancement public à Dakar en 4 langues (FR, wolof, EN, AR/RTL) par un train de 4 releases, 10 concessionnaires, 150 annonces, ≥ 30 contacts qualifiés/semaine, premiers encaissements.
> Statuts : ⏸ à faire · ▶ en cours · ✅ validé CEO · 🔁 renvoyé · ❌ abandonné (raison écrite) · 👤 action du propriétaire.
> Règles : tout code passe par `code-reviewer` → `security-auditor` (argent, données, auth, IA, fichiers) → `qa-engineer` → validation CEO. Une tâche = une conversation courte. Chaque agent journalise dans `.claude/coaching/WORKLOG.md`.
> **Portes de chaque release (bloquantes)** : Q1 QA verte sur MySQL, 4 langues · Q2 0 faille High/Medium sur le périmètre livré · Q3 0 chaîne manquante + textes critiques wolof/arabe relus par un natif (N2) · Q4 juridique · Q5 exploitation (zips, restauration) · Q6 validation CEO + accord écrit du propriétaire. **Une porte échoue → la date glisse d'une semaine, la barre ne baisse pas.**
> Les agents ne peuvent ni appeler, ni visiter, ni acheter, ni relire en locuteur natif : toute tâche 👤 revient au propriétaire ou à son relais, avec les supports préparés par les agents.
> Les identifiants T-P1/T-P2/T-P3 du plan précédent sont conservés quand la tâche continue.

## Train de releases

| Release | Date | Go / no-go | Périmètre |
|---|---|---|---|
| G0 | lun. 30/11/2026 | — | QA triée, MariaDB locale verte, ADR multilingue + saisie directe, glossaire v1, T-SEC-01/02, décisions D2 à D9 |
| Préproduction | ven. 18/12/2026 | — | Hébergeur réel MySQL ; périmètre R1 complet ; 4 langues à 100 % (N1) ; marque Wotoyi |
| **R1** Marketplace publique + comptes concessionnaires + 4 langues | mar. 12/01/2027 | ven. 08/01 | Phases 1 et 2, saisie directe, mesure des contacts, multilingue, blog (8 articles) |
| **R2** Sites + CRM concessionnaires + synchro | mar. 02/02/2027 | ven. 29/01 | Phases 3 et 4, mode sombre |
| **R3** Inspection payante | mar. 16/02/2027 | ven. 12/02 | Module inspection v1, rapport PDF |
| **R4** Assistant IA WhatsApp (texte) | mar. 23/02/2027 | ven. 19/02 | Phase 5 après H1, M1, M3, H2 ; téléphonie éteinte |
| Revue du trimestre | ven. 26/02/2027 | — | Résultat face à l'objectif, décision T2 |

Gel du code : 5 jours ouvrés avant chaque release (R1 : 05/01 · R2 : 26/01 · R3 : 09/02 · R4 : 16/02).

---

## Semaines 1 et 2 (17 – 29 novembre) → G0

| id | tâche | agent | modèle | dépend de | statut | critère d'acceptation |
|---|---|---|---|---|---|---|
| T-P1-01 | Relancer la suite de la phase 1 sur un site local SQLite (base de référence) | qa-engineer | sonnet | — | ▶ en cours (QA lancée le 16/11, résultats inconnus) | Sorties complètes de `php tools/tests/run.php` et `node tools/tests/browser.js` ; écarts avec 127/127 et 32/32 signalés |
| T-P1-02 | Site local MariaDB/MySQL et relance de la suite | qa-engineer | sonnet | T-P1-01 | ⏸ à faire | Suite exécutée sur MySQL ; tableau des écarts SQLite/MySQL ; procédure dans `docs/INSTALL.md` |
| T-P1-03 | QA de la phase 2 (inscription, documents, validation, paiement manuel, abonnement, expiration) | qa-engineer | sonnet | T-P1-01 | ▶ en cours | Tableau cas · attendu · obtenu · statut · preuve ; bugs classés bloquant/important/mineur ; cas ajoutés à `tools/tests/` |
| T-P1-04 | QA des phases 3 et 4 (site + CRM, synchro signée, idempotence, signature altérée → 401, révocation, « Contacter le vendeur ») | qa-engineer | sonnet | T-P1-01 | ▶ en cours | Chaque critère de `docs/06-roadmap.md` marqué ✅/❌/⏸ avec preuve |
| T-P1-05 | Audit de sécurité des phases 2 à 6 | security-auditor | opus | — | ✅ validé CEO le 16/11 | `docs/SECURITY-AUDIT-2026-11-16.md` : 2 High, 6 Medium, 8 Low (revue de code, non testé en exécution ⏸) |
| T-P1-06 | ADR « saisie directe d'annonces » (équipe plateforme pour un concessionnaire sans site connecté ; cohabitation avec la synchro) | software-architect | inherit (Fable) | D6 👤 | ⏸ à faire | ADR dans `DECISIONS.md` ; réutilise `Vehicles::upsert` ; modèle de données, permissions, non-objectifs, charge |
| T-L10N-01 | **ADR multilingue** : choix de la locale (URL par langue), sélecteur, `hreflang` + `x-default`, locale `wo`, `dir="rtl"`, polices arabes et diacritiques wolof, contenu saisi par les concessionnaires (non traduit : règle d'affichage), e-mails par langue, sans plugin tiers | software-architect | inherit (Fable) | — | ⏸ à faire | ADR dans `DECISIONS.md` : options, décision, impact SEO validé par `seo-geo-specialist`, découpage des tâches ; s'applique aux 5 paquets (central, thème, CRM, connecteur, thème concessionnaire) |
| T-L10N-02 | **Glossaire v1** `docs/i18n/GLOSSARY.md` : termes automobiles et d'interface × 4 langues, termes interdits (« car »), format des prix, ton, niveaux N0/N1/N2, liste des « textes critiques » | localization-writer | sonnet | — | ⏸ à faire | ≥ 150 termes ; wolof (orthographe CLAD) et arabe marqués 👤 jusqu'à relecture native ; aucun terme déclaré « vérifié » sans preuve |
| T-L10N-03 | Fiche de relecture native : format de lot (texte source, traduction, contexte, capture), ordre des lots (critiques → acheteur → concessionnaire → reste), consigne pour les relecteurs | localization-writer | sonnet | T-L10N-02 | ⏸ à faire | Un lot d'essai de 50 chaînes prêt à envoyer ; suivi N1/N2 par chaîne défini (fichier hors code produit) |
| T-P1-07 | Spécification de la mesure des contacts (clic WhatsApp, clic téléphone, formulaire) : « qualifié », dédoublonnage 24 h, exclusion robots/équipe, tableau hebdomadaire | product-manager | sonnet | — | ⏸ à faire | Stories + critères Given/When/Then ; données collectées validées par `legal-compliance` |
| T-L10N-04 | Spécification du bouton « Signaler une traduction » (page, langue, texte, suggestion ; aucune donnée personnelle obligatoire ; limitation de débit ; file dans `/platform-admin/`) | product-manager | sonnet | T-L10N-01 | ⏸ à faire | Stories + critères, dont RTL et mobile |
| T-P1-08 | Inventaire des textes visibles « AutoBuzy » à remplacer par « Wotoyi » (préfixes, slugs techniques et tables inchangés) | wp-theme-engineer | sonnet | — | ⏸ à faire | Liste fichier:ligne ; plan de remplacement réglable sans code |
| T-P1-09 | Disponibilité de « Wotoyi » : domaines `.com` et `.sn`, marques (OAPI, bases publiques), comptes sociaux | legal-compliance | opus | — | ⏸ à faire (pour le 20/11) | Résultat par registre avec lien et date ; recommandation acheter / ne pas acheter |
| T-FIN-01 | **Chiffrage du lancement complet** (remplace T-P1-10) : domaine, hébergement central + préproduction, e-mail transactionnel, coût par site concessionnaire, API d'IA, WhatsApp, transcription, relecteurs, avis juridique | finance-analyst | inherit (Fable) | — | ⏸ à faire (pour le 23/11) | Prix relevés sur les sites officiels (lien + date) ; 3 scénarios mensuels ; total trimestre et annuel ; ce qui est évitable ; rien d'inventé |
| T-P1-15 | CGU, politique de confidentialité, consentements des formulaires, formalités CDP (loi 2008-12), entité qui encaisse | legal-compliance | opus | — | ⏸ à faire (avancée de décembre) | Risques haut/moyen/bas, clauses exactes, démarches CDP avec source officielle, points pour un juriste (D7) |
| T-P2-01 | Liste de 40 concessionnaires et vendeurs professionnels de Dakar (sources publiques) | sales-partnerships | sonnet | — | ⏸ à faire | Chaque ligne avec sa source ; fichier hors dépôt public si coordonnées (D8) |
| T-P2-02 | Guide d'entretien `mom-test` (15 min), faits passés uniquement | sales-partnerships | sonnet | — | ⏸ à faire | 10 questions ; grille de notes ; signaux d'engagement ; wolof 👤 |
| T-P2-04 | Offre « partenaire fondateur » (gratuité de lancement, contreparties, ce qui devient payant et quand) | sales-partnerships | sonnet | T-P2-03b | ⏸ à faire | Une page ; aucun prix futur inventé ; résumé d'accord relu par `legal-compliance` |
| T-P2-05 | Scripts de prospection (WhatsApp, relance, appel, visite, objections) | sales-partnerships | sonnet | T-P2-02 | ⏸ à faire | Français prêt à l'emploi ; wolof marqué 👤 |
| T-P2-06 | Cartographie des alternatives à Dakar et ébauche de positionnement | growth-marketer | sonnet | — | ⏸ à faire | Tableau avec source vérifiée (lien + date) ; aucune part de marché inventée |
| T-SEC-01, T-SEC-02 | Voir tableau sécurité | wp-plugin-engineer | opus | — | ⏸ à faire | Livrés avant G0 |
| T-P2-03 | 👤 Décisions D6, D14 (18/11) · D2, D8 (20/11) · D4 (23/11) · D3 (25/11) · D7, D9, D10 (27/11) | propriétaire | — | T-P1-09, T-FIN-01 | 👤 | Réponses écrites dans `DECISIONS.md` |
| T-P2-03b | 👤 Entretiens concessionnaires 1 à 8 (appel ou visite par le relais) | propriétaire / relais | — | T-P2-01, T-P2-02 | 👤 | 8 grilles remplies au 30/11 |
| T-P1-11 | Tri des résultats QA : corriger avant R1 / avant R2 / reporter, par release | ceo-strategist | inherit (Fable) | T-P1-03, T-P1-04 | ⏸ à faire | Décision dans `DECISIONS.md` ; tâches de correction créées ici par release ; si > 10 bugs bloquants sur R1, nouvelle date écrite |
| T-P1-16 | Revue G0 | ceo-strategist | inherit (Fable) | tout ce qui précède | ⏸ à faire | Entrée datée 30/11 : go / ajustement, preuves relues |

---

## Décembre → préproduction (18/12)

| id | tâche | agent | modèle | dépend de | statut | critère d'acceptation |
|---|---|---|---|---|---|---|
| T-P1-12 | Corriger les bugs bloquants et importants des phases 1 et 2 (périmètre R1) | wp-plugin-engineer | opus | T-P1-11 | ⏸ à faire | Par correction : test qui échouait puis passe, `php -l`, revue, re-test QA avec preuve |
| T-P1-13 | Saisie directe d'annonces selon l'ADR | wp-plugin-engineer | opus | T-P1-06 | ⏸ à faire | Création, modification, vendu, suppression avec photos ; permissions serveur, nonces, contrôle des fichiers ; tests ; visible au catalogue |
| T-P1-14 | Mesure des contacts selon T-P1-07 | wp-plugin-engineer | opus | T-P1-07 | ⏸ à faire | Migration versionnée ; tableau hebdomadaire par concessionnaire et par annonce ; limitation de débit ; tests ; revue sécurité |
| T-L10N-05 | Couche multilingue côté plugin central selon l'ADR (locale, sélecteur, `hreflang`, e-mails par langue, locale `wo`) | wp-plugin-engineer | opus | T-L10N-01 | ⏸ à faire | Les 4 langues commutables sur toutes les pages publiques et espaces connectés ; `hreflang` + `x-default` ; canonique correct par langue ; tests ajoutés |
| T-L10N-06 | RTL et typographie : propriétés logiques dans `shared/design-system/` et le thème central, polices avec glyphes arabes et diacritiques wolof (ë, ñ, ŋ), auto-hébergées | frontend-engineer | sonnet | T-L10N-01 | ⏸ à faire | `tools/dev/sync-design-system.sh` relancé ; captures RTL mobile + ordinateur des pages clés ; 0 débordement ; aucun décalage de mise en page |
| T-L10N-07 | Bouton « Signaler une traduction » selon T-L10N-04 | wp-plugin-engineer | sonnet | T-L10N-04, T-L10N-05 | ⏸ à faire | Signalement enregistré, limité en débit, visible dans `/platform-admin/` ; tests |
| T-P1-17 | Passage à la marque Wotoyi sur tout ce qui est visible | wp-theme-engineer | sonnet | T-P1-08, D4 | ⏸ à faire | Aucune occurrence visible de « AutoBuzy » (recherche + captures) ; préfixes et tables inchangés ; suite verte |
| T-P1-18 | Français du périmètre R1 : relecture, « auto / voiture », prix `1 500 000 FCFA` | localization-writer | sonnet | T-P1-17 | ⏸ à faire | `.l10n.php` régénérés par `tools/dev/i18n-extract.php` ; 0 chaîne manquante |
| T-L10N-08 | **Traductions R1** (plugin central + thème, environ 1 035 chaînes) en EN, wolof, AR, niveau N1 | localization-writer | sonnet | T-L10N-02, T-P1-18 | ⏸ à faire | 0 chaîne manquante × 4 ; variables et HTML intacts ; glossaire respecté ; wolof et arabe marqués 👤 (N1) ; textes critiques listés à part |
| T-L10N-09 | 👤 Relecture native R1 : textes critiques puis parcours acheteur (wolof, arabe) | relecteurs natifs | — | T-L10N-08, D9 | 👤 | Fiches de lot retournées ; corrections intégrées par `localization-writer` ; passage en N2 tracé |
| T-P1-19 | Performance mobile du catalogue et de la fiche sur hébergement mutualisé | data-performance-engineer | sonnet | T-P1-12 | ⏸ à faire | Avant/après (requêtes, TTFB, LCP, CLS) en profil mobile lent, dans les 4 langues |
| T-P1-20 | SEO technique R1 : titres, descriptions, canoniques, `hreflang`, sitemap par langue, JSON-LD validé, pages Dakar ; préproduction en `noindex` | seo-geo-specialist | sonnet | T-P1-17, T-L10N-05 | ⏸ à faire | Rapport problème · impact · correctif · vérification ; production indexable, préproduction non |
| T-CNT-01 | Modèle de blog : type de contenu, gabarits d'article et de liste, données structurées `Article`, traductions liées | wp-theme-engineer | sonnet | T-L10N-01 | ⏸ à faire | Article publiable sans code dans les 4 langues ; `hreflang` entre versions ; tests navigateur |
| T-P1-21 | Préproduction sur l'hébergement réel (MySQL) : installation, cron, e-mails, HTTPS, HSTS, limitation des tentatives sur `wp-login` (L8), dossiers privés inaccessibles par URL directe, sauvegarde et **restauration testée** | devops-release | haiku (escalade sonnet si blocage) | D3, D4, T-P1-12 | 👤 achat puis ⏸ | Zips de `tools/dev/build-release.sh` inspectés ; `docs/DEPLOYMENT.md` suivi et corrigé ; restauration prouvée |
| T-P1-24 | Guides d'une page par rôle (équipe, concessionnaire), en 4 langues | docs-trainer + localization-writer | haiku, sonnet | T-P1-13 | ⏸ à faire | Étapes numérotées ; uniquement des fonctions testées ✅ |
| T-P2-08 | Synthèse des 15 entretiens | product-manager | sonnet | entretiens 👤 | ⏸ à faire | Citations et faits ; livrée avant le 15/12 |
| T-P2-09 | Revue marché du 15/12 : cibles et prix recalibrés (n'arrête pas le train) ; test de résistance | ceo-strategist | inherit (Fable) | T-P2-08 | ⏸ à faire | Entrée datée dans `DECISIONS.md` |
| T-P2-10 | Processus d'accueil : collecte du stock et des photos par WhatsApp, contrôle des photos, mise en ligne sous 48 h, routine hebdomadaire | customer-ops | haiku | T-P1-13 | ⏸ à faire | Fiche qui fait quoi ; modèles de messages FR (wolof 👤) ; indicateur de fraîcheur |
| T-P2-11 | 👤 Entretiens 9 à 15 ; signer les 5 premiers partenaires et collecter leur stock | propriétaire / relais | — | T-P2-04, T-P2-05 | 👤 | 5 accords écrits ; stock et photos reçus |
| T-R3-01 | Spécification du module inspection v1 (parcours, formules et prix administrables, marge, statuts Nouvelle → Livré + Annulée/Remboursée, assignation, rapport, espace acheteur, rôle inspecteur) | product-manager | sonnet | — | ⏸ à faire (pour le 11/12) | Stories + critères, 4 langues, mobile, permissions, cas d'erreur ; coupe v1 justifiée |
| T-R3-02 | ADR inspection : tables, migrations, états, routes, génération PDF sans service externe, stockage privé des rapports | software-architect | inherit (Fable) | T-R3-01 | ⏸ à faire (pour le 18/12) | ADR dans `DECISIONS.md` ; réutilise paiements manuels, fichiers privés, audit, CRM central |
| T-P3-01 | Dossier économique de l'inspection : formules, prix à tester, coût inspecteur, marge | finance-analyst | inherit (Fable) | D11 👤 | ⏸ à faire | Format `business-case` ; coût relevé auprès d'au moins 2 garages 👤 ; formules visibles |
| T-P3-02 | Texte de limites de l'inspection et modèle de rapport (vérifié · anomalie détectée · donnée indisponible · limites) ; jamais de garantie de kilométrage ni d'absence d'accident | legal-compliance | opus | T-R3-01 | ⏸ à faire | Clauses exactes ; points pour un juriste (D7) ; traductions en N2 obligatoires |
| T-R4-01 | 👤 Démarche Meta (compte Business, numéro, vérification), clé d'API, fournisseur de transcription, plafond de dépense (D12) | propriétaire, guidé par integrations-engineer | opus | D7, T-FIN-01 | 👤 (à lancer le 04/12) | Procédure pas à pas tirée de la documentation officielle (lien + date) ; état de la vérification suivi chaque lundi |
| T-R1-01 | Point de préproduction du 18/12 | ceo-strategist | inherit (Fable) | T-P1-21 | ⏸ à faire | Liste des écarts restants avant R1, avec responsable et date |

## Janvier → R1 (12/01) puis R2 (02/02)

| id | tâche | agent | modèle | dépend de | statut | critère d'acceptation |
|---|---|---|---|---|---|---|
| T-P1-22 | QA complète R1 en préproduction : mobile + ordinateur, 4 langues dont RTL, rôles administrateur / gestionnaire / concessionnaire / visiteur, e-mails réels | qa-engineer | sonnet | T-P1-21, T-L10N-08 | ⏸ à faire | 100 % verts sur MySQL ; parcours visiteur → contact et inscription → paiement manuel → activation prouvés avec captures |
| T-P1-23 | Contre-audit de sécurité R1 | security-auditor | opus | T-P1-22, T-SEC R1 | ⏸ à faire | 0 High/Medium ouvert sur R1 ; risques résiduels dans `docs/SECURITY-REVIEW.md` |
| T-P1-25 | **Go / no-go R1 (08/01)** : portes Q1 à Q6 | ceo-strategist puis 👤 | inherit (Fable) | T-P1-22, T-P1-23, T-P1-15, T-L10N-09 | ⏸ à faire | Liste de contrôle remplie, preuves rouvertes ; accord écrit du propriétaire ; en cas de no-go, nouvelle date et causes |
| T-P1-26 | **Mise en production R1 (12/01)** : domaine, HTTPS, cron, sauvegardes quotidiennes, surveillance | devops-release | haiku | T-P1-25 | ⏸ à faire | Sauvegarde avant ; contrôles post-déploiement verts ; données de démo retirées ; IA/WhatsApp/téléphonie éteints |
| T-P2-12 | Mise en ligne du stock des 5 premiers partenaires | customer-ops + 👤 saisie | haiku | T-P1-26, T-P2-11 | ⏸ à faire | Photos réelles, prix, kilométrage affiché tel que déclaré par le vendeur ; délai mesuré |
| T-P2-13 | 👤 Partenaires 6 à 10 signés et en ligne | propriétaire / relais | — | T-P2-12 | 👤 | 10 concessionnaires actifs ; ≥ 100 annonces au 01/02 |
| T-P3-03 | Canaux gratuits : kit de partage pour concessionnaires, publications sociales, liens suivis par canal | growth-marketer | sonnet | T-P1-26, T-P2-06 | ⏸ à faire | Un objectif mesurable par canal ; rapport hebdomadaire visites → contacts |
| T-SEA-01 | Plan SEA prêt à lancer : structure, mots-clés, pages d'atterrissage, suivi des conversions, coût par contact visé | growth-marketer | sonnet | T-P1-14 | ⏸ à faire | Aucun repère inventé (hypothèses marquées) ; **aucune dépense sans D13** |
| T-R2-01 | Corriger les bugs bloquants et importants des phases 3 et 4 | wp-plugin-engineer | opus | T-P1-11 | ⏸ à faire | Idem T-P1-12 |
| T-R2-02 | Traductions R2 (CRM, connecteur, thème concessionnaire, environ 700 chaînes) en EN, wolof, AR + couche multilingue et RTL côté concessionnaire | localization-writer, wp-plugin-engineer, frontend-engineer | sonnet, opus, sonnet | T-L10N-05 | ⏸ à faire | 0 chaîne manquante × 4 ; `/manager/` utilisable en RTL sur mobile (captures) |
| T-R2-03 | 👤 Relecture native R2 : textes critiques puis parcours concessionnaire | relecteurs natifs | — | T-R2-02 | 👤 | Lots retournés ; N2 tracé |
| T-P1-28 | Mode sombre par les tokens du design system (central + concessionnaire) | ux-ui-designer, frontend-engineer | sonnet | D14 | ⏸ à faire | Tokens dans `shared/design-system/` d'abord ; contraste AA ; `prefers-color-scheme` + bascule ; tests navigateur clair/sombre |
| T-R2-04 | Procédure d'installation d'un site concessionnaire (manuelle, liste de contrôle) + coût par site | devops-release, docs-trainer | haiku | T-FIN-01 | ⏸ à faire | Site installé et connecté par code en suivant le guide seul ; durée mesurée |
| T-R2-05 | QA R2 : annonce publiée sur le site concessionnaire → visible sur la marketplace ; prix, réservé, vendu, supprimé ; contact reçu dans le CRM ; critères des phases 3 et 4 | qa-engineer | sonnet | T-R2-01, T-R2-02 | ⏸ à faire | Chaque critère de `docs/06-roadmap.md` ✅ avec preuve, 4 langues |
| T-R2-06 | Contre-audit R2 puis **go / no-go (29/01)** et mise en production (02/02) | security-auditor → ceo-strategist → 👤 → devops-release | opus, inherit, haiku | T-R2-05, T-SEC R2 | ⏸ à faire | Portes Q1 à Q6 ; 3 premiers sites concessionnaires en ligne |
| T-R3-03 | Développer le module inspection v1 selon l'ADR | wp-plugin-engineer | opus | T-R3-02 | ⏸ à faire | Parcours complet demande → paiement manuel vérifié → assignation → rapport PDF livré dans l'espace acheteur ; prix et formules réglables sans code ; marge calculée ; tests |
| T-R3-04 | Écrans inspection (acheteur, inspecteur, administration), 4 langues, RTL, clair/sombre | ux-ui-designer, wp-theme-engineer | sonnet | T-R3-01 | ⏸ à faire | Au plus 3 touches pour demander une inspection ; états vide, chargement, erreur, succès |
| T-R3-05 | Processus inspection : assignation, délais, livraison du rapport, annulation, remboursement, modèles de messages | customer-ops | haiku | T-R3-01, D11 | ⏸ à faire | Fiche qui fait quoi par statut ; modèles en 4 langues (wolof/arabe 👤) |
| T-R4-02 | Correctifs H1, M1, M3, H2, L3 (= T-SEC-08) | ai-agent-engineer | opus | — | ⏸ à faire (à démarrer le 04/01) | Voir tableau sécurité |
| T-P3-04 | Revue hebdomadaire des indicateurs (NS, K1 à K10) et du train, chaque lundi | ceo-strategist | inherit (Fable) | T-P1-14 | ⏸ à faire | Tableau daté ✅ mesuré / ⚠ estimé ; 1 enseignement client ; glissements écrits |

## Février → R3 (16/02), R4 (23/02), revue (26/02)

| id | tâche | agent | modèle | dépend de | statut | critère d'acceptation |
|---|---|---|---|---|---|---|
| T-R3-06 | Traductions inspection (interface, rapport, limites, messages) + 👤 relecture native **intégrale** (tout est « texte critique ») | localization-writer + relecteurs | sonnet | T-R3-03 | ⏸ à faire | 0 chaîne manquante × 4 ; N2 à 100 % ; sinon français faisant foi + mention « traduction indicative » |
| T-R3-07 | Revue sécurité du module (argent, données personnelles, PDF, fichiers, cloisonnement acheteur/inspecteur) puis QA de bout en bout | security-auditor → qa-engineer | opus → sonnet | T-R3-03 | ⏸ à faire | 0 High/Medium ; chaque statut et chaque transition interdite testés ; le rapport ne contient aucune garantie de kilométrage ni d'absence d'accident (test automatique sur le modèle) |
| T-R3-08 | **Go / no-go R3 (12/02)** et mise en production (16/02) | ceo-strategist → 👤 → devops-release | inherit, haiku | T-R3-06, T-R3-07, D7, D11 | ⏸ à faire | Portes Q1 à Q6 ; au moins 1 inspecteur partenaire prêt ; sinon module livré désactivé et date écrite |
| T-R4-03 | Jeu d'évaluation IA : ≥ 30 cas réels par langue (intention, bon outil, refus correct, injections, passage à un humain) ; wolof évalué avec un locuteur natif 👤 | ai-agent-engineer | opus | T-R4-02, D9 | ⏸ à faire | Taux de réussite écrit par langue, avant/après ; cas d'injection de l'audit (H1) réussi ; wolof faible → wolof simple + français |
| T-R4-04 | Intégration WhatsApp Cloud API en conditions réelles (webhook signé, fenêtre de 24 h, modèles approuvés, consentement, code à usage unique M3) | integrations-engineer | opus | T-R4-01 | ⏸ à faire | Documentation officielle citée ; essai avec identifiants réels ; plafond de dépense quotidien actif |
| T-R4-05 | QA R4 selon `docs/06-roadmap.md` phase 5 + contre-audit ; **go / no-go (19/02)** et activation (23/02) | qa-engineer → security-auditor → ceo-strategist → 👤 | sonnet, opus, inherit | T-R4-03, T-R4-04, T-SEC-07 | ⏸ à faire | Portes Q1 à Q6 ; voix et téléphonie restent éteintes ; sinon WhatsApp reste éteint et date écrite |
| T-P3-06 | Premiers abonnements payés : proposer l'offre payante aux partenaires | sales-partnerships + 👤 | sonnet | T-P2-13, T-P3-07 | ⏸ à faire | Résultat compté en paiements réellement vérifiés ; raisons des refus notées |
| T-P3-07 | Prix et formules SaaS, à partir des entretiens et de ce que les concessionnaires paient déjà | finance-analyst | inherit (Fable) | T-P2-08 | ⏸ à faire (pour le 08/01, les formules étant visibles à R1) | 2 ou 3 formules en FCFA ; chaque chiffre ✅ source ou ⚠ hypothèse ; coût par site inclus |
| T-P3-08 | Adéquation côté concessionnaires : contacts jugés utiles, ventes attribuées, question « très déçu » | product-manager + 👤 | sonnet | 4 semaines après R1 | ⏸ à faire | Réponses brutes des partenaires |
| T-P3-09 | Dossier économique T2 : 3 scénarios, seuil de rentabilité, coût par concessionnaire actif | finance-analyst | inherit (Fable) | T-P3-06 | ⏸ à faire | Format `business-case` ; mesuré séparé de l'hypothèse |
| T-P3-10 | Revue du trimestre et cadrage T2 (26/02) | ceo-strategist | inherit (Fable) | tout | ⏸ à faire | Entrée datée : résultat face à l'objectif, glissements expliqués, décision |

---

## Tâches récurrentes — contenu SEO / GEO et langues

| id | tâche | fréquence | agent | modèle | statut | critère d'acceptation |
|---|---|---|---|---|---|---|
| T-CNT-02 | Plan éditorial : 20 sujets classés (intention de recherche, page cible, lien vers catalogue ou inspection) | une fois, pour le 27/11, puis révision mensuelle | seo-geo-specialist | sonnet | ⏸ à faire | Sujets tirés de questions réelles (entretiens, recherches) ; aucun volume de recherche inventé |
| T-CNT-03 | **Articles en français** dans `content/` : 8 prêts pour R1, puis 2 par semaine ⚠ | hebdomadaire (livraison le jeudi) | seo-geo-specialist | sonnet | ▶ en cours | Faits avec source et date ; aucun prix ou statistique inventé ; « auto / voiture », jamais « car » ; titre ≤ 60 caractères, description, liens internes, FAQ réelle ; articles sur l'inspection conformes à la règle « jamais de garantie » |
| T-CNT-04 | **Traductions des articles** : EN et AR sous 7 jours ; wolof pour 1 article sur 2 | hebdomadaire (livraison le mardi) | localization-writer | sonnet | ⏸ à faire | Glossaire respecté ; `hreflang` entre versions ; wolof et arabe publiés avec mention « bêta » tant que non relus 👤 |
| T-CNT-05 | Validation des articles avant publication | hebdomadaire | ceo-strategist (échantillon : 1 article sur 2, sources rouvertes) | inherit (Fable) | ⏸ à faire | ✅ ou 🔁 écrit dans le WORKLOG ; un article sans source n'est pas publié |
| T-CNT-06 | Publication et suivi : indexation, positions, visites organiques, contacts issus du contenu | hebdomadaire après R1 (lundi) | seo-geo-specialist | sonnet | ⏸ à faire | Chiffres ✅ mesurés ; pas de cible avant 4 semaines de données |
| T-CNT-07 | GEO : pages factuelles citables (processus, limites de l'inspection, FAQ), `llms.txt` si utile, contrôle des données structurées | mensuelle | seo-geo-specialist | sonnet | ⏸ à faire | JSON-LD validé ; consigne Google actuelle vérifiée en ligne avant toute règle affirmée |
| T-L10N-10 | Tri des signalements de traduction | hebdomadaire après R1 (lundi) | localization-writer | sonnet | ⏸ à faire | Chaque signalement : corrigé / refusé (raison) / envoyé au relecteur ; délai ≤ 7 jours |
| T-L10N-11 | **Revue mensuelle des langues** : signalements, phrases mal comprises par l'IA, nouvelles chaînes, glossaire, part N2 par langue | mensuelle (01/02/2027, puis 01/03) | localization-writer | sonnet | ⏸ à faire | Rapport dans le WORKLOG : part N2 par langue, signalements ouverts, corrections livrées |
| T-L10N-12 | Suivi qualité des langues dans le rapport nocturne (part N2, signalements > 7 jours, toute mention « vérifié » sans preuve native = critique) | quotidienne | coach-quality-controller | opus | ⏸ à proposer au propriétaire (le CEO ne modifie pas le rôle du coach) | Ligne « Langues » dans chaque rapport |

---

## Corrections de l'audit sécurité du 16/11 (`docs/SECURITY-AUDIT-2026-11-16.md`)

Règle : chaque correctif est livré, revu et retesté **avant la release qui expose le code concerné** ; 0 High/Medium ouvert sur le périmètre livré (porte Q2). Rien de l'IA ou de la voix n'est activé avant T-SEC-08.

| id | tâche | agent | modèle | dépend de | bloque | statut | critère d'acceptation |
|---|---|---|---|---|---|---|---|
| T-SEC-01 | M6 : réglages (paiement, secrets) réservés à `manage_options` + `Audit::log` + e-mail au propriétaire sur changement `pay_*` | wp-plugin-engineer | opus | — | R1 (avant G0) | ⏸ à faire | POST `settings_save` en platform manager → refusé ; en admin → ligne d'audit |
| T-SEC-02 | M5 : chiffrer la clé Anthropic (`Crypto::encrypt`, `Settings::secret`) + migration | wp-plugin-engineer | opus | — | R1 (avant G0) | ⏸ à faire | `wp option get amc_settings` ne montre pas `sk-ant-` |
| T-SEC-03 | M2 : limite de plan et modération dans `patch_status` ; colonne `hidden_by_staff` (409 `amc_moderated`) | wp-plugin-engineer | opus | — | R2 | ⏸ à faire | Test : limite pleine → PUT non publié → PATCH actif = 409 ; annonce masquée par l'équipe non republiable |
| T-SEC-04 | M4 : HTTPS obligatoire au handshake (sauf WP_DEBUG), Idempotency-Key signée, `X-AMC-Delivery` sur `/ai/execute` | wp-plugin-engineer | opus | — | R2 | ⏸ à faire | handshake `http://` → 400 ; requête rejouée rejetée |
| T-SEC-05 | L4, L6 (anti-bot, flood de signatures) avant R1 ; L1, L5 (clickjacking CRM, droits sur les documents) avant R2 | wp-plugin-engineer | sonnet | T-SEC-01 | R1 et R2 | ⏸ à faire | Un test par point dans `tools/tests/` |
| T-SEC-06 | L7 : exclure examples/scripts/fixtures du SDK dans les zips | devops-release | haiku | — | R1 | ⏸ à faire | `unzip -l` sans ces dossiers |
| T-SEC-07 | L2 (effacement et export complets : central avant R1, concessionnaire avant R2) + accord de sous-traitance Anthropic + mention dans la politique de confidentialité (avant R4) | legal-compliance (+ wp-plugin-engineer pour le code) | opus | — | R1, R2, R4 | ⏸ à faire | Liste des tables couvertes par l'effacement ; clause rédigée ; points pour avocat 👤 |
| T-SEC-08 | H1, M1, H2, M3, L3 (confirmation construite par le serveur, oui exact, voix sans accès CRM, code à usage unique WhatsApp, plafond quotidien de l'assistant concessionnaire) | ai-agent-engineer | opus | — | **R4** (dans le trimestre) | ⏸ à faire (à démarrer le 04/01) | Jeu d'évaluation avec injection ; tests unitaires `yes_no()` ; numéro non vérifié sans accès à l'assistant concessionnaire |
| T-SEC-09 | Seconde revue sécurité après correctifs, puis QA, **à chaque release** | security-auditor → qa-engineer | opus → sonnet | correctifs de la release | R1, R2, R3, R4 | ⏸ à faire | 0 High/Medium ouvert sur le périmètre livré ; rapport daté dans `docs/` |
| T-SEC-10 | L8 + point non vérifiable par revue de code : HSTS, limitation `wp-login`, accès direct aux dossiers privés sur l'hébergeur réel (nginx ignore `.htaccess`) | devops-release → security-auditor | haiku → opus | T-P1-21 | R1 | ⏸ à faire | URL directe d'un document privé → refusée en préproduction (preuve `curl`) |

---

## Hors backlog ce trimestre

Voir `DECISIONS.md`, entrée du 16/11 « lancement complet », § 10 : téléphonie et appels vocaux, passerelles de paiement automatiques, provisioning automatique des sites, publicité payante sans budget décidé, annonces mises en avant payantes, PWA, autres pays, renommage des préfixes, autres projets.
