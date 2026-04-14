# PRD — bastien59960/adminhelper

**Dernière mise à jour :** 2026-04-13
**Extension :** `ext/bastien59960/adminhelper`  
**Version courante :** 1.0.6

## Objectif

Fournir une extension d'administration phpBB polyvalente qui centralise plusieurs besoins concrets du forum :

- sécuriser et tracer les emails ACP
- fiabiliser les tâches cron
- améliorer la recherche par auteur
- donner aux modérateurs des outils internes sur les posts
- déclarer et détecter les images générées par IA sur les pièces jointes

## Fonctionnalités livrées

### 1. Recherche membre par email dans l'ACP

Ajout d'un champ de recherche par adresse email dans la gestion des membres.

### 2. Durcissement des emails ACP

- contenu HTML optionnel avec fallback texte
- pied de désinscription
- mode d'envoi compatible gros volumes
- support RFC 8058 (`List-Unsubscribe`, one-click)
- liens signés HMAC à expiration

### 3. Gestion et audit des désinscriptions

- interception des requêtes signées
- page de confirmation et action one-click
- journal d'audit en base
- restauration admin des notifications forum
- nettoyage des notifications obsolètes

### 4. Fiabilisation du `cron_lock`

- remplacement du listener cron phpBB par une variante avec `try/finally`
- watchdog shell pour les locks orphelins

### 5. Recherche par auteur sans troncature

- rechargement du `post_text` complet
- rendu des PJ non-inline dans les résultats de recherche

### 6. Notes de modération sur les posts

- bouton d'action réservé modérateurs/admins
- note interne unique par post
- page récapitulative "Posts à modérer"

### 7. Images générées par IA sur les pièces jointes

#### Objectif fonctionnel

Permettre de :

- déclarer manuellement qu'une image jointe a été générée par IA
- détecter automatiquement certaines origines IA fortes à l'upload ou en scan batch
- avertir publiquement les lecteurs sous l'image
- afficher la source IA seulement quand elle est prouvée automatiquement

#### Règles produit

- Le marquage IA général peut être manuel ou automatique.
- La source IA (`Gemini`, `ChatGPT`, etc.) n'est jamais attribuée manuellement par l'extension.
- Si aucune preuve technique fiable n'est trouvée, seul l'avertissement IA générique est affiché.
- Une détection forte verrouille la case côté publication.
- L'intégration doit rester compatible avec les brouillons et le flux GeoExplorer.

#### Détection technique actuelle

Sources fortes prises en charge :

- C2PA / Content Credentials
- métadonnées de générateurs IA connus
- prompts / paramètres IA conservés dans les fichiers

Sources actuellement observées et validées sur le forum :

- `Gemini` via marqueurs Google Generative AI / `trainedAlgorithmicMedia`
- `ChatGPT` via marqueurs OpenAI / ChatGPT / C2PA

#### Interfaces livrées

- case IA sur les images lors de la publication / modification
- avertissement public sous l'image sur le forum
- ligne "Source IA détectée : …" si un provider est identifié automatiquement
- module ACP de suivi et scan batch
- commande CLI `adminhelper:attachment-ai-scan`

## Schéma de données

| Table | Rôle |
|---|---|
| `adminhelper_unsubscribe_log` | audit des désinscriptions |
| `adminhelper_mod_notes` | notes internes par post |
| `adminhelper_attachment_ai` | état IA des pièces jointes image |

### `adminhelper_attachment_ai`

Colonnes principales :

- `attach_id`
- `post_id`
- `user_id`
- `is_ai_generated`
- `is_forced`
- `ai_provider`
- `scan_status`
- `detection_source`
- `detection_reason`
- `created_at`
- `updated_at`

## Migrations

| Fichier | Contenu |
|---|---|
| `release_1_0_1.php` | logs de désinscription |
| `release_1_0_2.php` | compléments UCP / logs |
| `release_1_0_3.php` | notes de modération |
| `release_1_0_4.php` | table et ACP de suivi IA pièces jointes |
| `release_1_0_5.php` | stockage du provider IA détecté |

## Fichiers clés

```text
event/listener.php
cron/safe_cron_runner_listener.php
controller/mod_notes_controller.php
service/attachment_ai_manager.php
console/attachment_ai_scan.php
acp/main_module.php
adm/style/acp_adminhelper_attachment_ai.html
migrations/release_1_0_4.php
migrations/release_1_0_5.php
styles/all/template/event/posting_attach_body_file_list_after.html
styles/all/template/adminhelper_ai_posting.js
styles/all/template/event/attachment_file_append.html
```

## Correctifs

### `get_requested_attachment_ids()` — accès `$_POST` direct (2026-03-30)

**Symptôme :** Erreur 503 "Illegal use of $_POST" lors de la prévisualisation d'un post existant
après ajout d'une pièce jointe. Le flux *soumettre sans prévisualiser* ne déclenchait pas l'erreur
car l'event `inject_attachment_ai_posting_vars` n'est appelé que sur les pages de type posting
(preview inclus), pas sur le traitement final.

**Cause :** `get_requested_attachment_ids()` lisait `$_POST['attachment_data']` directement.
phpBB remplace `$_POST` par un objet proxy `deactivated_super_global` ; tout accès direct lève une
exception fatale.

**Correctif :** Remplacement par `$this->request->variable('attachment_data', [['attach_id' => 0]], true)`.
Le template `[['attach_id' => 0]]` indique à phpBB de parser un tableau de tableaux en castant
`attach_id` en entier. Le troisième argument `true` cible explicitement POST.

### Normalisation des entités HTML dans les URLs BBCode (2026-04-06)

**Symptôme :** Des liens `[url=]` et `[img]` dans des posts affichaient des URLs cassées contenant
`&amp;quot;` ou `&amp;amp;amp;` au lieu d'un `&` ou `"` simple. Les liens étaient non-cliquables
ou pointaient vers une adresse invalide.

**Cause :** Copier-coller depuis du HTML rendu (pages web, apps de messagerie, Word) où `&` est
déjà encodé en `&amp;`. À chaque rebond supplémentaire (WhatsApp → copier → forum), un niveau
d'encodage s'ajoute. phpBB/s9e TextFormatter stocke ensuite le tout en XML (ajoutant encore un
niveau), résultant en `&amp;amp;amp;` voire 7 niveaux d'encodage sur des cas extrêmes.

**Posts affectés (corrigés manuellement en base) :**
- post_id 233609 (topic 21422) — URL CDN tbauctions avec `?&imageFormat=webp` (3 niveaux)
- post_id 234623 (topic 21548) — URL download forum avec `?id=X&mode=view` (3 niveaux)
- post_id 233615 (topic 21423) — URL Google Maps avec 7 niveaux (corrigé par l'admin)

**Correctif préventif :** Hook `core.submit_post_before` dans `event/listener.php` :
méthodes `normalize_post_urls()`, `fix_bbcode_url_encoding()`, `decode_entities_url()`.
Avant toute soumission de post, les BBCodes `[url=…]`, `[img]…[/img]`, `[url]…[/url]`
sont passés dans une boucle `html_entity_decode()` jusqu'à stabilité, supprimant tous les
niveaux de sur-encodage. **Aucun fichier phpBB core modifié** — tout est dans cette extension.

## 8. Contrôle d'accès des forums par nombre de messages (`forum_gate`) — v1.0.6 livré

**Statut :** En conception — v1.0.6

### Problème

Le forum veut forcer les nouveaux inscrits à se présenter avant d'accéder à l'ensemble du contenu.
Les invités non-connectés voient tout librement. Mais un membre fraîchement inscrit qui n'a pas
encore posté de présentation ne devrait pas pouvoir consulter les sections « riches » du forum.

### Comportement cible

| Profil | Accès |
|---|---|
| Invité (non connecté) | Accès libre partout |
| Membre inscrit avec `user_posts >= min_posts` | Accès libre |
| Membre inscrit avec `user_posts < min_posts` | Bloqué → message d'erreur phpBB natif avec lien vers le forum de présentation |
| Admin / fondateur | Jamais bloqué |

**Héritage** : si un sous-forum n'a pas de règle propre, il hérite de son parent le plus proche qui
en a une. Si aucun parent n'a de règle, le forum est libre d'accès.

**Cas particulier :** `min_posts = 0` sur un forum = exemption explicite (ignore la règle du parent).
Utilisé pour le forum de présentation lui-même.

### Architecture technique

#### Base de données

Nouvelle table `phpbb3_adminhelper_forum_gate` :

```sql
forum_id         INT(10) UNSIGNED NOT NULL    -- PK, forum ciblé
guest_hidden     TINYINT(1) UNSIGNED NOT NULL DEFAULT 0
                 -- 0 = visible aux invités
                 -- 1 = caché aux invités (aucun accès anonyme)
min_posts_member MEDIUMINT(8) UNSIGNED NOT NULL DEFAULT 1
                 -- 0 = exempté (pas de restriction pour ce forum)
                 -- N > 0 = N messages minimum requis pour les membres
```

Pas de colonne `inherit` — l'absence de ligne signifie « héritage du parent ».
Quand une ligne existe, les **deux champs** sont considérés explicitement définis.

#### Résolution de l'héritage (runtime)

```
effective_rule(forum_id) → (guest_hidden, min_posts_member):
    si forum_id dans forum_gate → retourner (row.guest_hidden, row.min_posts_member)
    parent_id = phpbb3_forums[forum_id].parent_id
    si parent_id == 0 → retourner (default_guest_hidden=0, default_min_posts=1)
    retourner effective_rule(parent_id)
```

**Défauts globaux :**
- `gate_default_guest_hidden = 0` : invités voient tout par défaut (l'admin doit activer)
- `gate_default_min_posts_member = 1` : membres doivent avoir au moins 1 message

Configurables en ACP. Toute la table `forum_gate` est chargée en une requête au premier check.
La map des parents phpBB est obtenue via `phpbb3_forums` (requête légère ou cache statique).

#### Événements phpBB hookés

| Événement | Disponible dans | Usage |
|---|---|---|
| `core.viewforum_modify_page_title` | `viewforum.php` | `$forum_id` + `$forum_data` disponibles → blocage avant rendu |
| `core.viewtopic_before_f_read_check` | `viewtopic.php` | `$forum_id` + `$topic_data` disponibles → blocage symétrique |

Dans les deux handlers :
1. Vérifier `$user->data['is_registered']` — si invité, sortir sans rien faire
2. Vérifier `$user->data['user_type']` — si `USER_FOUNDER` ou admin, sortir
3. Résoudre `effective_min_posts($forum_id)`
4. Si `effective_min_posts == 0`, sortir
5. Si `$user->data['user_posts'] < effective_min_posts` → `trigger_error(FORUM_GATE_BLOCKED)`

#### Message d'erreur

Chaîne FR `FORUM_GATE_BLOCKED` :
> « Pour accéder à cette section, vous devez avoir posté au moins **%d message(s)**.
> Commencez par [vous présenter dans le forum de présentation](%s) — votre accès sera ouvert dès
> votre message validé. »

Deux messages distincts selon le profil bloqué :

- **Invité bloqué** (`FORUM_GATE_GUEST_BLOCKED`) :
  > « Cette section est réservée aux membres. [Inscrivez-vous](%s) ou [connectez-vous](%s). »

- **Membre bloqué** (`FORUM_GATE_MEMBER_BLOCKED`) :
  > « Pour accéder à cette section, vous devez avoir posté au moins **%d message(s)**.
  > Commencez par [vous présenter dans le forum de présentation](%s). »

`trigger_error()` affiche nativement la page d'erreur phpBB avec le cadre complet du forum.

#### Module ACP

Nouveau mode `forum_gate` dans le module ACP existant (`acp/main_module.php`).

Interface : tableau de tous les forums avec indentation arborescente, **deux colonnes par forum** :

| Colonne | Type UI | Valeurs |
|---|---|---|
| **Invités** | Checkbox | ☐ = visible (valeur héritée affichée en gris), ☑ = caché |
| **Min messages membres** | Input number | vide = hérité (affiché grisé), 0 = exempté, N = seuil |

Une ligne vide dans le tableau (pas de case cochée, input vide) supprime la règle explicite
→ le forum revient en mode héritage du parent.

Section config globale en haut :
- Forum de présentation (select forum_id) — lien dans le message d'erreur
- Seuil membres par défaut (input, défaut = 1)
- Masquer aux invités par défaut (checkbox, défaut = non coché)

Template : `adm/style/acp_adminhelper_forum_gate.html`.

#### Performances

- Table `forum_gate` ≤ nombre de forums → chargement unique en mémoire
- Parcours héritage ≤ 5–6 niveaux max (arborescence forum typique)
- Aucune requête supplémentaire sur les pages sans forum (index, profil, etc.)
- Check inactif si `$user->data['is_anonymous']` (invité) → court-circuit immédiat

### Migration

`release_1_0_6.php` :
- Crée `phpbb3_adminhelper_forum_gate` (colonnes `forum_id`, `guest_hidden`, `min_posts_member`)
- Ajoute config `bastien59960_adminhelper_gate_presentation_forum_id` = 14
- Ajoute config `bastien59960_adminhelper_gate_default_min_posts` = 1
- Ajoute config `bastien59960_adminhelper_gate_default_guest_hidden` = 0
- Insère ligne `(forum_id=14, guest_hidden=0, min_posts_member=0)` — forum de présentation exempté
- Enregistre le mode ACP `forum_gate`

### Fichiers livrés

```
migrations/release_1_0_6.php              — migration appliquée en production
adm/style/acp_adminhelper_forum_gate.html — template ACP arbre des forums
language/fr/acp/info_acp_adminhelper.php  — chaînes ACP (ACP_FORUM_GATE_*)
language/fr/info_acp_adminhelper.php      — chaînes front-end (FORUM_GATE_*_BLOCKED)
language/en/acp/info_acp_adminhelper.php  — idem EN
language/en/info_acp_adminhelper.php      — idem EN
acp/main_info.php                         — mode forum_gate déclaré
acp/main_module.php                       — handle_forum_gate_mode() + helpers
event/listener.php                        — forum_gate_check() sur viewforum + viewtopic
```

### DB installée

```sql
-- Table créée :
phpbb3_adminhelper_forum_gate (forum_id PK, guest_hidden BOOL, min_posts_member UINT)

-- Configs insérées (valeurs à l'installation) :
bastien59960_adminhelper_gate_enabled                 = 0   ← master switch (désactivé par défaut)
bastien59960_adminhelper_gate_presentation_forum_id   = 14  ← "Voyageurs présentez-vous !"
bastien59960_adminhelper_gate_default_min_posts       = 1
bastien59960_adminhelper_gate_default_guest_hidden    = 0

-- Règle initiale insérée :
(forum_id=14, guest_hidden=0, min_posts_member=0)  ← forum de présentation exempté
```

### Activation

ACP → Admin Helper → **Contrôle d'accès forums** → cocher "Activer le contrôle d'accès" → Enregistrer.

### Décisions prises

- **Invités non bloqués** : ne pas casser l'indexation par les moteurs de recherche.
- **Pas de modale JS** : `trigger_error()` phpBB natif = message clair, compatible avec tous les
  navigateurs, pas de dépendance JS supplémentaire.
- **Défaut global = 1** : tous les forums sont protégés par défaut sans configuration manuelle.
  L'admin n'a qu'à gérer les exceptions (forum de présentation, forums VIP à seuil plus élevé).
- **Héritage implicite** (absence de ligne) : plus simple que stocker `inherit=true`, et élimine
  les incohérences si on supprime un forum intermédiaire.
- **`min_posts = 0` = exemption** : permet de "couper" la chaîne d'héritage pour les sous-forums
  libres dans un parent restreint (ex : forum de présentation dans une catégorie restreinte).
- **Migration insère d'office** `min_posts=0` pour le forum de présentation (id 14) : sinon un
  nouveau membre avec 0 messages ne peut même pas poster sa présentation.
- **Admins jamais bloqués** : check sur `user_type` phpBB natif.

## Contraintes et non-objectifs

- Pas d'attribution manuelle du moteur IA.
- Pas de dépendance à une API externe.
- Pas de confiance dans un simple indice faible comme la seule présence du mot `c2pa`.
- Les chaînes AI les plus récentes sont documentées et maintenues en priorité en `fr` et `en`.
