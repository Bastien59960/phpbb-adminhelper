# Plan / état courant — bastien59960/adminhelper

**Mise à jour :** 2026-04-13  
**Cible actuelle :** 1.0.6 (livré)

## Livré

### v1.0.1 – v1.0.5

- recherche membre par email dans l'ACP
- durcissement des emails ACP et désinscription RFC 8058
- logs de désinscription et outils de restauration
- correctif `cron_lock` + watchdog
- affichage complet des posts et PJ non-inline dans la recherche par auteur
- notes de modération sur les posts
- module IA pièces jointes :
  - case de déclaration manuelle "image générée par IA"
  - scan batch ACP / CLI
  - détection forte à l'upload et sur existant
  - affichage public d'un avertissement sous l'image
  - affichage du provider seulement si détecté automatiquement

### v1.0.6 — forum_gate (2026-04-13)

- table `phpbb3_adminhelper_forum_gate` (forum_id, guest_hidden, min_posts_member)
- master switch `gate_enabled` (désactivé par défaut — activation consciente depuis l'ACP)
- héritage parent→enfant : absence de ligne = suit le parent ; défaut global = 1 message
- forum de présentation (id=14) exempté d'office par la migration
- deux contrôles indépendants par forum :
  - visibilité aux visiteurs non connectés (invités)
  - seuil de messages approuvés pour les membres inscrits
- admins et fondateurs jamais bloqués
- messages d'erreur phpBB natifs avec liens cliquables (inscription / connexion / forum de présentation)
- ACP : tableau arborescent de tous les forums, explication en ligne des deux colonnes, labels autonomes ("Caché aux invités (forcer)", "Visible aux invités (forcer)", "Suit le parent")
- deux événements hookés : `core.viewforum_modify_page_title` + `core.viewtopic_before_f_read_check`
- chargement des règles en une seule requête par page (cache statique en propriété)

## Décisions produit actées

- Le marquage IA général peut être manuel.
- Le provider IA ne doit pas être saisi manuellement.
- `Gemini` et `ChatGPT` sont affichés seulement si le fichier les prouve.
- Si la source n'est pas prouvée automatiquement, l'extension reste sur l'avertissement générique.

## Points de vigilance

- garder la page de publication fluide, surtout avec GeoExplorer
- conserver la compatibilité brouillons / édition / upload
- éviter les faux positifs sur les photos réelles contenant des traces C2PA non concluantes

## Prochaine cible : v1.0.7

### Objectif

Bloquer l'accès aux forums (viewforum + viewtopic) pour les membres inscrits qui n'ont pas
encore atteint un seuil de messages configurable par forum. Les invités ne sont jamais bloqués.

### Étapes de développement

#### Étape 1 — Migration + table DB
Fichier : `migrations/release_1_0_6.php`
- Crée `phpbb3_adminhelper_forum_gate` :
  - `forum_id` INT UNSIGNED NOT NULL PRIMARY KEY
  - `guest_hidden` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0
  - `min_posts_member` MEDIUMINT(8) UNSIGNED NOT NULL DEFAULT 1
- Insère configs :
  - `bastien59960_adminhelper_gate_presentation_forum_id` = 14
  - `bastien59960_adminhelper_gate_default_min_posts` = 1
  - `bastien59960_adminhelper_gate_default_guest_hidden` = 0
- Insère ligne `(forum_id=14, guest_hidden=0, min_posts_member=0)` dans `forum_gate`
  (forum de présentation exempté d'office sur les deux champs)
- Enregistre mode ACP `forum_gate` dans `phpbb3_modules`

#### Étape 2 — Template ACP
Fichier : `adm/style/acp_adminhelper_forum_gate.html`
- Section config globale (en haut) :
  - Forum de présentation : `<select>` avec liste des forums
  - Seuil membres par défaut : `<input type="number">`
  - Masquer invités par défaut : `<input type="checkbox">`
- Tableau arborescent de tous les forums (indentation = profondeur) :
  - Colonne « Masquer aux invités » : checkbox (vide = héritage affiché en gris)
  - Colonne « Min messages membres » : input number (vide = héritage, 0 = exempté, N = seuil)
- Valeurs héritées affichées en placeholder grisé pour guidance visuelle
- Bouton de sauvegarde global (un seul POST pour tout le tableau)

#### Étape 3 — Logique ACP
Fichier : `acp/main_module.php`, mode `forum_gate`
- `GET` : charge toute la table `forum_gate` + arborescence forums + config
- `POST` : upsert/delete sur `forum_gate` selon les valeurs soumises (vide = supprimer la ligne)
- Invalide le cache phpBB après sauvegarde

#### Étape 4 — Event listener (gate check)
Fichier : `event/listener.php`
- Ajouter dans `getSubscribedEvents()` :
  ```php
  'core.viewforum_modify_page_title'   => 'forum_gate_check',
  'core.viewtopic_before_f_read_check' => 'forum_gate_check',
  ```
- Méthode `forum_gate_check($event)` :
  1. Court-circuit si admin/fondateur (`user_type` + `acl_get('a_')`)
  2. Charger les règles `forum_gate` + configs (cache statique en propriété privée)
  3. Résoudre `effective_rule($forum_id)` → `(guest_hidden, min_posts_member)`
     par remontée de `parent_id` ; si racine sans règle → utiliser les défauts config
  4. **Si invité** (`is_anonymous`) ET `guest_hidden == 1`
     → `trigger_error(lang('FORUM_GATE_GUEST_BLOCKED', url_register, url_login))`
  5. **Si membre** ET `user_posts < min_posts_member`
     → `trigger_error(lang('FORUM_GATE_MEMBER_BLOCKED', min_posts_member, url_presentation))`
- Méthode privée `load_gate_rules()` : une requête, résultat mis en cache statique
- Méthode privée `resolve_parent_chain($forum_id, $gate_rules)` : remontée arborescente

#### Étape 5 — Chaînes de langue
Fichiers : `language/fr/acp/info_acp_adminhelper.php` + `language/en/...`
- `FORUM_GATE_BLOCKED` : message avec `sprintf` pour min_posts + URL forum de présentation
- `ACP_FORUM_GATE_TITLE`, `ACP_FORUM_GATE_EXPLAIN`
- `ACP_FORUM_GATE_PRESENTATION_FORUM`, `ACP_FORUM_GATE_MIN_POSTS`
- `ACP_FORUM_GATE_INHERITED` (placeholder grisé pour valeur héritée)
- `ACP_FORUM_GATE_DEFAULT_MIN_POSTS` (label config seuil membres global, défaut = 1)
- `ACP_FORUM_GATE_DEFAULT_GUEST_HIDDEN` (label config masquer invités par défaut)
- `ACP_FORUM_GATE_GUEST_HIDDEN` (en-tête colonne "Masquer aux invités")
- `FORUM_GATE_GUEST_BLOCKED` (message invité bloqué, avec liens inscription/connexion)
- `FORUM_GATE_MEMBER_BLOCKED` (message membre bloqué, avec lien forum de présentation)
- `ACP_FORUM_GATE_SAVED`

### Ordre recommandé

Étape 1 → Étape 5 → Étape 4 → Étape 3 → Étape 2  
(Migration d'abord pour avoir la table, langue tôt pour coder le message, event avant ACP pour
pouvoir tester en dur avant que l'interface soit prête.)

### Points de vigilance

- **Défaut global = 1** : dès l'activation de l'extension, TOUS les forums (sauf ceux avec règle
  explicite `min_posts=0`) requièrent 1 message minimum. Activer en production uniquement après
  avoir vérifié que le forum de présentation (id 14) a bien sa ligne `min_posts=0` en DB.
- **User posts** : phpBB stocke `user_posts` dans `phpbb3_users`. Ce compteur n'inclut que les
  posts « approuvés » (modération désactivée ou post validé). C'est exactement ce qu'on veut.
- **Admins** : tester `$user->data['user_type'] == USER_FOUNDER` ET `$auth->acl_get('a_')`.
- **Forum de présentation** : la migration insère `min_posts=0` pour forum_id=14. Si le forum de
  présentation change d'id, mettre à jour manuellement la table ET la config.
- **Cache arborescence** : ne pas charger `phpbb3_forums` entier à chaque page — utiliser la
  propriété statique ou le cache phpBB.
- **Encodage URL** dans `trigger_error` : générer l'URL avec `append_sid()`.
- **Mode posting** (`posting.php`) : PAS de gate check — sinon le membre ne peut pas poster sa
  présentation. L'event `core.viewforum_modify_page_title` ne se déclenche pas sur posting.php,
  et `core.viewtopic_before_f_read_check` non plus. Aucun patch spécifique nécessaire.

## Suite probable (post v1.0.6)

- optimiser encore la page de publication si d'autres cas lents apparaissent
- compléter les traductions `de/es/it` pour les chaînes IA récentes
- enrichir les heuristiques auto uniquement avec des signatures fiables et vérifiables
