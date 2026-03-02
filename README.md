# Admin Helper - Extension phpBB

Extension phpBB 3.3 pour administrer plus facilement les membres et les envois email, avec gestion de desinscription conforme RFC8058.

## Fonctions principales

### 1) Recherche membre par email dans l'ACP

Dans `ACP > Membres et Groupes > Gerer les utilisateurs`, l'extension ajoute un champ de recherche par adresse email.

### 2) Emails de masse admin (ACP)

Dans `ACP > General > Client communication > Email`:

- Edition texte + HTML (multipart/alternative)
- Ajout d'un footer de desinscription
- En-tetes one-click:
  - `List-Unsubscribe`
  - `List-Unsubscribe-Post: List-Unsubscribe=One-Click`
- Envoi unitaire pour one-click (chunk size force a 1)
- Throttle anti-burst `500ms` entre messages dans le process queue

### 3) Desinscription distincte par type

Le token de desinscription prend en charge deux types :

- `massmail`: desinscription des emails de masse administrateur
- `forum_notify`: desinscription des notifications email forum

Chaque type a son propre traitement, son propre texte de confirmation, et son propre logging.

### 4) Logs ACP + compteurs globaux

Nouvelle page ACP:

- `ACP > Extensions > Admin Helper > Logs desinscription`

Elle affiche:

- Compteurs globaux:
  - Membres inscrits/desinscrits emails de masse
  - Membres inscrits/desinscrits notifications forum (email)
- Journal detaille:
  - date, type, statut, user, email, HTTP, methode, IP, expiration token, user-agent

### 5) Action admin "Annuler" (restauration)

Sur les lignes de log `forum_notify`, un bouton ACP permet de re-activer les notifications email pour un membre qui s'est trompe.

Effets de l'action:

- remet `user_notify = 1`
- convertit `user_notify_type` `IM -> BOTH` si necessaire
- remet `notify = 1` dans `user_notifications` pour `notification.method.email`
- ecrit un event de log `admin_restored`

## Schema et migrations

- `release_1_0_1`: creation table `*_adminhelper_unsubscribe_log` + module ACP
- `release_1_0_2`: ajout colonne `unsubscribe_type` (+ index)

## Installation / mise a jour

1. Copier `ext/bastien59960/adminhelper`
2. Activer l'extension dans l'ACP
3. Lancer les migrations:

```bash
php bin/phpbbcli.php db:migrate
php bin/phpbbcli.php cache:purge
```

## Compatibilite

- phpBB `>= 3.3.0`
- PHP `>= 7.1.3`

## Langues

Clés de langue fournies pour:

- `fr`, `en`, `de`, `es`, `it`

## Licence

GPL-2.0-only
