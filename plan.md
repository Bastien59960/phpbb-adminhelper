# Plan / état courant — bastien59960/adminhelper

**Mise à jour :** 2026-03-27  
**Cible actuelle :** 1.0.5

## Déjà livré

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

## Décisions produit actées

- Le marquage IA général peut être manuel.
- Le provider IA ne doit pas être saisi manuellement.
- `Gemini` et `ChatGPT` sont affichés seulement si le fichier les prouve.
- Si la source n'est pas prouvée automatiquement, l'extension reste sur l'avertissement générique.

## Points de vigilance

- garder la page de publication fluide, surtout avec GeoExplorer
- conserver la compatibilité brouillons / édition / upload
- éviter les faux positifs sur les photos réelles contenant des traces C2PA non concluantes

## Suite probable

- optimiser encore la page de publication si d'autres cas lents apparaissent
- compléter les traductions `de/es/it` pour les chaînes IA récentes
- enrichir les heuristiques auto uniquement avec des signatures fiables et vérifiables
