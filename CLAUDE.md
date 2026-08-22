# Tambacounda Cosmetix — Règles permanentes du projet

Ce fichier documente les règles d'architecture et de gouvernance qui s'appliquent à
l'ensemble du projet, pour toute personne ou tout agent qui y contribue.

## Architecture cible

- **Laravel 13** est le backend et le cerveau métier de l'application. Toute la
  logique métier (produits, commandes, stock, paiements, permissions) vit côté
  Laravel — jamais côté frontend.
- **Filament 5** est le back-office d'administration, construit au-dessus de Laravel.
- **Sanctum** gère l'authentification (API tokens / SPA auth).
- **Spatie Laravel Permission** gère les rôles et permissions.
- **PostgreSQL / Supabase** est la base de données principale. Le développement
  local utilise une instance PostgreSQL locale ; Supabase est la cible de production.
- **Redis / Predis** assure le cache et les queues.
- **Next.js** est le frontend, consommateur de l'API Laravel — il n'a jamais
  d'accès direct à la base de données ni aux secrets serveur.

## Règles de sécurité et d'intégrité

- Ne jamais exposer les secrets Supabase (clés `SUPABASE_SERVICE_ROLE_KEY`, URL
  de connexion directe à la base, credentials) côté client, dans un commit, ou
  dans un fichier accessible au frontend.
- Ne jamais modifier directement les données métier critiques (produits, stock,
  commandes, paiements) depuis Next.js. Toute écriture métier passe exclusivement
  par l'API Laravel, qui applique les règles de validation, de permission et de
  cohérence.
- Le stock et les paiements doivent obligatoirement être traités de façon
  **transactionnelle et idempotente** — toute opération qui modifie du stock ou
  déclenche un paiement doit pouvoir être rejouée sans effet de bord et doit
  être englobée dans une transaction cohérente.

## Gouvernance du projet

- Le projet avance par phases (voir la roadmap). Chaque phase a des critères de
  sortie explicites.
- Ne jamais passer à la phase suivante sans que les critères de sortie de la
  phase en cours aient été vérifiés et validés.
- Toute opération destructive (suppression de fichiers, de tables, de données,
  réinitialisation Git, migration destructive, action sur Supabase en
  production) nécessite une confirmation explicite avant exécution.
