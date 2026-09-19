#!/usr/bin/env bash
#
# Déploiement de l'API sur le VPS (Nginx + PHP-FPM sur la même machine).
#
#   cd /var/www/tambacounda-cosmetix/tambacounda-cosmetix-api && ./deploy.sh
#
# À lancer avec l'utilisateur propriétaire des fichiers (celui qui peut
# écrire dans storage/ et bootstrap/cache/), pas root : sinon les fichiers
# créés ici deviennent inaccessibles à PHP-FPM.
#
# Prérequis (voir deploy/README.md) :
#   - services systemd tambacounda-queue et tambacounda-scheduler.timer ;
#   - FRONTEND_URL et FRONTEND_REVALIDATE_SECRET dans .env (même secret que
#     REVALIDATE_SECRET dans Vercel).
#
# Variables ajustables :
#   PHP_FPM_SERVICE : nom du service PHP-FPM à recharger (vide = ne rien faire).

set -euo pipefail

APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PHP_FPM_SERVICE="${PHP_FPM_SERVICE:-php8.3-fpm}"

cd "$APP_DIR"

echo "==> Récupération du code"
# Le dépôt contient l'API et le frontend : git pull s'exécute à sa racine.
git -C "$(git rev-parse --show-toplevel)" pull --ff-only

echo "==> Dépendances PHP"
composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist

# Mode maintenance pendant les migrations uniquement. Le trap le lève même
# si une étape échoue, pour ne jamais laisser le site fermé.
echo "==> Mode maintenance"
trap 'php artisan up >/dev/null 2>&1 || true' EXIT
php artisan down --retry=15

echo "==> Migrations"
php artisan migrate --force

echo "==> Caches Laravel"
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan filament:optimize

php artisan up
trap - EXIT

echo "==> Redémarrage des workers"
# Les workers terminent leur job en cours puis repartent avec le nouveau code.
php artisan queue:restart

# Recharge PHP-FPM pour vider l'OPcache (sinon l'ancien code reste servi).
# Nécessite une règle sudo sans mot de passe, voir deploy/README.md.
if [[ -n "$PHP_FPM_SERVICE" ]] && command -v systemctl >/dev/null; then
  echo "==> Rechargement de $PHP_FPM_SERVICE"
  sudo -n systemctl reload "$PHP_FPM_SERVICE" \
    || echo "AVERTISSEMENT : $PHP_FPM_SERVICE non rechargé (droits sudo ?), l'OPcache peut servir l'ancien code." >&2
fi

echo "==> Vidage du cache du catalogue côté frontend"
# Un déploiement peut changer ce que l'API renvoie sans qu'aucun modèle soit
# enregistré. Non bloquant : en cas d'échec, le cache expire seul sous 5 min.
php artisan catalog:revalidate-frontend \
  || echo "AVERTISSEMENT : cache du frontend non vidé, il expirera sous 5 minutes." >&2

echo "==> Déploiement terminé"
