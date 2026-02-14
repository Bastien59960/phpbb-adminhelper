# Admin Helper — Extension phpBB

Extension pour phpBB 3.3 qui ajoute un champ de recherche par **adresse email** dans la page de gestion des membres de l'ACP.

## Fonctionnalité

Dans **ACP > Membres et Groupes > Gérer les utilisateurs**, un champ "Rechercher par email" est ajouté sous le champ "Saisissez le nom d'utilisateur". Cela permet de trouver un membre directement par son email sans passer par le popup de recherche avancée.

### Avant
![Seul le champ "nom d'utilisateur" est disponible](https://img.shields.io/badge/Recherche-par%20pseudo%20uniquement-red)

### Après
![Recherche par pseudo OU par email](https://img.shields.io/badge/Recherche-par%20pseudo%20%2B%20email-green)

## Installation

1. Copier le dossier `bastien59960/adminhelper` dans `/ext/` de votre forum phpBB
2. Aller dans **ACP > Personnaliser > Extensions**
3. Activer **Admin Helper**

```
ext/
└── bastien59960/
    └── adminhelper/
        ├── composer.json
        ├── ext.php
        ├── config/
        │   └── services.yml
        ├── event/
        │   └── listener.php
        ├── adm/style/event/
        │   └── acp_overall_footer_after.html
        └── language/
            ├── en/
            │   └── info_acp_adminhelper.php
            └── fr/
                └── info_acp_adminhelper.php
```

## Compatibilité

- **phpBB** : 3.3.0 et supérieur
- **PHP** : 7.1.3 et supérieur

## Fonctionnement technique

L'extension utilise deux mécanismes phpBB :

1. **Template event** `acp_overall_footer_after` : injecte un script JavaScript qui ajoute le champ email dans le formulaire de sélection d'utilisateur de l'ACP (le bloc `S_SELECT_USER` n'a pas de template event dédié)
2. **Event listeners** :
   - `core.common` : intercepte le POST du champ email, effectue la recherche en base de données, et injecte le `user_id` trouvé via `request::overwrite()` avant que le module ACP ne traite la requête
   - `core.adm_page_header_after` : active l'injection du JavaScript dans le footer ACP

Aucune modification de la base de données n'est nécessaire — l'extension utilise la colonne `user_email` existante de la table `phpbb_users`.

## Licence

[GPLv2](https://opensource.org/licenses/GPL-2.0)
