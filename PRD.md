# PRD — bastien59960/adminhelper

**Dernière mise à jour :** 2026-03-30
**Extension :** `ext/bastien59960/adminhelper`  
**Version courante :** 1.0.5

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

## Contraintes et non-objectifs

- Pas d'attribution manuelle du moteur IA.
- Pas de dépendance à une API externe.
- Pas de confiance dans un simple indice faible comme la seule présence du mot `c2pa`.
- Les chaînes AI les plus récentes sont documentées et maintenues en priorité en `fr` et `en`.
