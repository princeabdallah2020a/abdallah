# Wotoyi Demo — données FICTIVES (v0.1.0)

Plugin **de démonstration** : il remplit un site WordPress tout neuf avec 4 concessionnaires, 12 véhicules et 4 services **inventés** (contenu en français, mention « démo » partout), pour voir le thème et le marketplace en situation. Il ajoute aussi une liste **Wotoyi > Dealers** en lecture seule.

> Ne jamais présenter ces données comme de vraies annonces. Prix, contacts (`+221 00 000 00 0x`, `@example.com`) et concessionnaires sont fictifs.

## Installation en 5 étapes (sans développeur)

1. **Installez les 3 éléments** (Extensions > Ajouter > Téléverser, et Apparence > Thèmes > Ajouter) : le thème `wotoyi`, le plugin `wotoyi-marketplace` et ce plugin `wotoyi-demo` (chacun en fichier .zip du dossier correspondant). Le dossier du thème doit s’appeler exactement `wotoyi`.
2. **Activez** le thème Wotoyi, puis les plugins **Wotoyi Marketplace** (en premier) et **Wotoyi Demo (DONNEES FICTIVES)**.
3. Allez dans **Outils > Wotoyi Demo**.
4. Cliquez **Charger les données de démo**. Si le site est en « production » (c’est le cas par défaut chez la plupart des hébergeurs, dont InfinityFree), **cochez d’abord la case de confirmation** : sans elle, rien n’est créé.
5. Ouvrez le site : les véhicules et services apparaissent. Pour voir les concessionnaires : menu **Wotoyi > Dealers**. Pour tout effacer : **Outils > Wotoyi Demo > Supprimer les données de démo** (vos vraies données ne sont pas touchées).

Si le message « Le plugin Wotoyi Marketplace doit être installé » s’affiche, activez d’abord le Marketplace. Si les images manquent, vérifiez que le thème s’appelle bien `wotoyi`.

## Ce que fait le plugin (pour l’équipe technique)

- Création via l’API PHP publique du marketplace (`wotoyi_marketplace_local_register_dealer`, `wotoyi_marketplace_local_receive`) : même `Receiver` que la synchronisation signée (validation, versions, idempotence, `sync_log`). Concessionnaires `status = active`, `transport = local`.
- Le **mode local** du marketplace n’est activé que pendant le chargement, puis l’option `wotoyi_mp_settings` est restaurée à l’identique (y compris « option absente »), même en cas d’erreur. Si `WOTOYI_MP_LOCAL_MODE` est défini à `false` dans `wp-config.php`, le chargement est refusé.
- **Marqueur « is_demo »** : le schéma du marketplace n’a pas de colonne `is_demo` et n’a pas été modifié. Une ligne est « démo » si elle appartient à un concessionnaire `transport = 'local'` dont `source_dealer_uuid` commence par `wotoyi-demo-`. La suppression efface ces concessionnaires et ce qui en dépend (véhicules, équipements, services, demandes/leads, favoris, `sync_log`, `sync_nonces`) dans une transaction. Le journal d’audit (append-only) n’est pas modifié (le chargement n’y écrit rien).
- Images : SVG du thème (`wp-content/themes/wotoyi/assets/images/*.svg`), URL filtrable par `wotoyi_demo_image_base_url`.
- Environnement `production` (`wp_get_environment_type()`, valeur par défaut de WordPress) : refus sans confirmation explicite (côté serveur, pas seulement dans l’interface).
- Droits : capacité `wotoyi_manage_marketplace` + nonce sur chaque action (`admin-post.php`).
- Pas de désinstallation destructive : les données de démo restent tant que vous ne cliquez pas sur « Supprimer ».

## Tests

```
bin/check.sh        # php -l + 16 tests (utilise le simulateur WordPress/SQLite du marketplace)
```

Les tests couvrent : chargement → l’API de recherche renvoie les 12 véhicules et 4 services → suppression → zéro ligne démo, lignes réelles intactes (octet pour octet), idempotence, rollback si une suppression échoue, restauration du mode local (y compris après erreur), refus en production sans confirmation, droits, échappement HTML.

## Limites connues

1. **Non testé sur un vrai WordPress / MySQL** : les tests tournent sur un simulateur SQLite (voir README du marketplace). Les écrans wp-admin (menus, nonces, redirections) n’ont été exécutés que sur des fonctions de remplacement. Faire une vraie recette (étapes ci-dessus) avant d’annoncer « prêt ».
2. Atomicité de la suppression : suppose des tables InnoDB (transactions). Sur MyISAM, une erreur en cours de route pourrait laisser une suppression partielle ; relancer « Supprimer » termine le travail.
3. Les badges « inspecté Wotoyi » et « mis en avant » ne sont pas écrits (champs gérés par le marketplace) ; les annonces de démo n’ont donc pas ce badge.
4. Les coordonnées GPS sont approximatives (centre-ville) pour permettre la recherche par distance. « Sans accident » est déduit du champ `accident` du prototype (hypothèse).
5. Les demandes (leads) laissées par des visiteurs sur des annonces de démo sont supprimées avec elles.
6. Textes d’interface en français uniquement ; aucune traduction (wo/ar/en) de la démo.
