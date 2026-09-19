# Déploiement de l'API sur le VPS

Cible : VPS Hostinger, Nginx et PHP-FPM sur la même machine, application
dans `/var/www/tambacounda-cosmetix/tambacounda-cosmetix-api`.

Le déploiement courant se fait avec `./deploy.sh`. Ce document décrit
l'installation initiale, à faire **une seule fois**, dans cet ordre.

## Deux processus de fond sont indispensables

Sans eux, l'application semble fonctionner mais certaines choses ne se
produisent jamais.

| Service | Rôle | Sans lui |
|---|---|---|
| `tambacounda-queue` | Traite la file Redis | Aucune notification WhatsApp (commande, paiement, stock bas) ; le cache du frontend n'est jamais vidé automatiquement |
| `tambacounda-scheduler.timer` | Lance `schedule:run` chaque minute | `payments:expire-stale` ne tourne pas : une tentative Wave abandonnée laisse sa commande bloquée et son stock réservé |

---

## Étape 1 — Vérifier les trois hypothèses des fichiers fournis

```bash
cd /var/www/tambacounda-cosmetix/tambacounda-cosmetix-api

which php                                  # attendu : /usr/bin/php
stat -c '%U %G' storage bootstrap/cache    # attendu : www-data www-data
systemctl list-units --type=service | grep -i fpm   # attendu : php8.3-fpm.service
```

Si un résultat diffère :

| Résultat différent | Correction |
|---|---|
| PHP ailleurs que `/usr/bin/php` | Remplacer le chemin dans `ExecStart` des deux fichiers `.service` |
| Propriétaire autre que `www-data` | Remplacer `User=` et `Group=` dans les deux `.service` |
| Service FPM d'une autre version | Utiliser ce nom à l'étape 3 et lancer `PHP_FPM_SERVICE=<nom> ./deploy.sh` |

## Étape 2 — Installer les deux unités systemd

```bash
sudo cp deploy/tambacounda-queue.service /etc/systemd/system/
sudo cp deploy/tambacounda-scheduler.service /etc/systemd/system/
sudo cp deploy/tambacounda-scheduler.timer /etc/systemd/system/

sudo systemctl daemon-reload
sudo systemctl enable --now tambacounda-queue.service
sudo systemctl enable --now tambacounda-scheduler.timer
```

## Étape 3 — Autoriser le rechargement de PHP-FPM sans mot de passe

Remplacer `deploy` par l'utilisateur qui lance le déploiement, et la
version de PHP par celle de l'étape 1 :

```bash
sudo visudo -f /etc/sudoers.d/tambacounda-deploy
```

```
deploy ALL=(root) NOPASSWD: /bin/systemctl reload php8.3-fpm
```

Sans cette règle, le déploiement continue et affiche un avertissement,
mais l'OPcache peut continuer à servir l'ancien code.

## Étape 4 — Variables d'environnement

| Variable | Valeur |
|---|---|
| `APP_ENV` / `APP_DEBUG` | `production` / `false` |
| `QUEUE_CONNECTION` | `redis` |
| `FRONTEND_URL` | l'URL publique du frontend Vercel |
| `FRONTEND_REVALIDATE_SECRET` | le même secret que `REVALIDATE_SECRET` dans Vercel (`openssl rand -hex 32`) |

Le secret doit exister **des deux côtés avant le déploiement**, sinon le
vidage du cache renverra 401.

Après toute modification du `.env`, la configuration est en cache et les
workers gardent l'ancienne en mémoire :

```bash
php artisan config:clear && php artisan config:cache
sudo systemctl restart tambacounda-queue.service
```

## Étape 5 — Déployer

```bash
cd /var/www/tambacounda-cosmetix/tambacounda-cosmetix-api
./deploy.sh
```

Le script s'arrête à la première erreur. Le site est en maintenance
pendant les migrations seulement, et ressort de maintenance même en cas
d'échec.

---

## Vérifier que tout tourne vraiment

### Le worker

```bash
systemctl is-active tambacounda-queue.service         # attendu : active
journalctl -u tambacounda-queue.service -n 20 --no-pager
php artisan queue:monitor redis:default --max=25      # file qui ne s'accumule pas
```

Test réel de bout en bout — modifier le prix d'un produit dans Filament,
puis, dans les 15 secondes :

```bash
journalctl -u tambacounda-queue.service --since '1 min ago' --no-pager | grep RevalidateFrontendCatalog
grep frontend.revalidate.done storage/logs/laravel.log | tail -1
```

Le nouveau prix doit apparaître sur le site sans attendre 5 minutes.

### Le planificateur

```bash
systemctl list-timers tambacounda-scheduler.timer --no-pager   # NEXT à moins d'une minute, LAST récent
journalctl -u tambacounda-scheduler.service -n 10 --no-pager
php artisan schedule:list
```

### En cas de problème

| Symptôme | Piste |
|---|---|
| `tambacounda-queue` en `failed` | `journalctl -u tambacounda-queue.service -n 50` : chemin de PHP, droits sur `storage/`, Redis injoignable |
| File qui grossit | Le worker ne tourne pas, ou les jobs échouent : `php artisan queue:failed` |
| Cache du frontend jamais vidé | Secret différent entre les deux côtés (401), ou `FRONTEND_URL` faux : `php artisan catalog:revalidate-frontend` affiche l'erreur exacte |
| Ancien code servi après déploiement | PHP-FPM non rechargé (étape 3) |

## Après une modification des fichiers `.service` ou `.timer`

```bash
sudo systemctl daemon-reload
sudo systemctl restart tambacounda-queue.service
```

`deploy.sh` n'en a pas besoin : `php artisan queue:restart` suffit à faire
repartir les workers sur le nouveau code.
