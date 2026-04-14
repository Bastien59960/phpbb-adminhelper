# Bastien59960 Admin Helper — Extension phpBB 3.3+

[English](README.md)

Admin Helper regroupe plusieurs outils pratiques d'administration et de modération phpBB dans une seule extension :

- durcissement des emails ACP avec désinscription RFC 8058
- journaux d'audit des désinscriptions et outils de restauration
- fiabilisation du `cron_lock`
- affichage complet des posts et des PJ non-inline dans la recherche par auteur
- notes de modération internes sur les posts
- déclaration et détection automatique des images générées par IA sur les pièces jointes
- portail d'accès aux forums : restreindre la visibilité d'un forum/sous-forum selon le nombre de messages

## Fonctionnalités en un coup d'œil

| Fonctionnalité | Emplacement |
|---|---|
| Recherche membre par email | ACP › Utilisateurs et groupes |
| Durcissement email de masse + désinscription one-click | ACP › Général › Email |
| Logs et compteurs de désinscription | ACP › Extensions › Admin Helper |
| Watchdog et libération sûre du `cron_lock` | arrière-plan |
| Corps complet des posts sur recherche par auteur | `search.php?author_id=N&sr=posts` |
| Notes de modération sur les posts | viewtopic — modérateurs uniquement |
| Images générées par IA | publication + ACP |
| Portail d'accès (contrôle par nombre de messages) | ACP › Extensions › Admin Helper › Forum gate |

## Portail d'accès aux forums (Forum gate)

`ACP > Extensions > Admin Helper > Forum gate`

Restreint l'accès à certains forums et sous-forums en fonction du nombre de messages du membre.

- Activation/désactivation globale via un interrupteur principal.
- Règles configurables par forum : nombre minimum de messages, masquage optionnel pour les invités.
- Les règles sont **héritées des forums parents** — déplacer un sous-forum dans la hiérarchie phpBB applique automatiquement la règle du parent, sans intervention manuelle.
- Deux messages distincts sont affichés aux membres bloqués :
  - **0 message** : invitation à rédiger un message de présentation (lien vers le forum de présentation configurable).
  - **≥ 1 message mais en dessous du seuil** : message simple indiquant le nombre exact de messages requis.
- Les administrateurs et fondateurs ne sont jamais bloqués.

## Fonction IA sur les pièces jointes

`ACP > Extensions > Admin Helper > Images générées par IA`

Admin Helper peut marquer les pièces jointes image comme générées par IA et afficher un avertissement public sous l'image sur le forum.

- Une case manuelle permet au posteur de déclarer qu'une image a été générée par IA.
- Les signatures techniques fortes sont détectées automatiquement à l'upload et pendant les scans batch.
- Quand la détection est fiable, la case est cochée et verrouillée automatiquement.
- La page de publication / modification ne lance pas de scan massif ; elle réutilise seulement l'état déjà stocké et des métadonnées légères des pièces jointes.
- La source IA détectée n'est affichée publiquement que lorsqu'elle est identifiée automatiquement à partir du fichier lui-même.
- Les signaux forts actuellement exploités incluent C2PA / Content Credentials, les métadonnées d'outils IA connus, et les prompts/paramètres conservés dans le fichier.
- Les images existantes peuvent être rescannées en masse depuis l'ACP ou en CLI.

### Portée actuelle de la détection

- `Gemini` peut être identifié quand les marqueurs C2PA Google/Gemini sont présents.
- `ChatGPT` peut être identifié quand les marqueurs C2PA ou métadonnées OpenAI / ChatGPT sont présents.
- Le marquage générique "image générée par IA" peut toujours être déclaré manuellement par le posteur.
- Aucune source IA n'est attribuée manuellement par l'extension. Si le fichier ne prouve pas la source, le forum n'affiche que l'avertissement IA générique.

### CLI

```bash
php bin/phpbbcli.php adminhelper:attachment-ai-scan --batch=1000 --max-seconds=0
```

## Données stockées

| Table | Rôle |
|---|---|
| `adminhelper_unsubscribe_log` | Journal d'audit des désinscriptions |
| `adminhelper_mod_notes` | Notes de modération internes sur les posts |
| `adminhelper_attachment_ai` | État IA des pièces jointes, statut de scan, source détectée |
| `adminhelper_forum_gate` | Règles d'accès par forum (nombre de messages, masquage invités) |

## Prérequis

- PHP `>= 7.1.3`
- phpBB `>= 3.3.0`
- MariaDB / MySQL

## Installation

1. Copier `bastien59960/adminhelper` dans `ext/`.
2. Activer l'extension :

```bash
php bin/phpbbcli.php extension:enable bastien59960/adminhelper
php bin/phpbbcli.php cache:purge
```

## Mise à jour

Après mise à jour des fichiers :

```bash
php bin/phpbbcli.php db:migrate -n
php bin/phpbbcli.php cache:purge
```

## Désinstallation

```bash
php bin/phpbbcli.php extension:disable bastien59960/adminhelper
php bin/phpbbcli.php extension:purge bastien59960/adminhelper
```

## Couverture langues

Couverture complète : `fr`, `en`, `de`, `es`, `it`

## Auteur

**Bastien** (`bastien59960`)
