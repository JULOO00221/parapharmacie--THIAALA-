# Déploiement de l'API sur le VPS

Cible : VPS Hostinger, Nginx et PHP-FPM sur la même machine, application
dans `/var/www/tambacounda-cosmetix/tambacounda-cosmetix-api`.

Le déploiement courant se fait avec `./deploy.sh` (à la racine de l'API).
Ce dossier contient ce qui s'installe **une seule fois**, à la main.

## 1. Deux processus de fond sont indispensables

Sans eux, l'application semble fonctionner mais certaines choses ne se
produisent jamais.

| Service | Rôle | Sans lui |
|---|---|---|
| `tambacounda-queue` | Traite la file Redis | Aucune notification WhatsApp (commande, paiement, stock bas) ; le cache du frontend n'est jamais vidé automatiquement |
| `tambacounda-scheduler.timer` | Lance `schedule:run` chaque minute | `payments:expire-stale` ne tourne pas : une tentative Wave abandonnée laisse sa commande bloquée et son stock réservé |

## 2. Installation

Les unités visent l'utilisateur `www-data` et PHP dans `/usr/bin/php`.
Vérifiez les deux avant d'installer :

```bash
which php                    # si ce n'est pas /usr/bin/php, corrigez ExecStart
stat -c '%U' /var/www/tambacounda-cosmetix/tambacounda-cosmetix-api/storage
```

```bash
cd /var/www/tambacounda-cosmetix/tambacounda-cosmetix-api

sudo cp deploy/tambacounda-queue.service /etc/systemd/system/
sudo cp deploy/tambacounda-scheduler.service /etc/systemd/system/
sudo cp deploy/tambacounda-scheduler.timer /etc/systemd/system/

sudo systemctl daemon-reload
sudo systemctl enable --now tambacounda-queue.service
sudo systemctl enable --now tambacounda-scheduler.timer
```

Vérification :

```bash
systemctl status tambacounda-queue.service
systemctl list-timers tambacounda-scheduler.timer
journalctl -u tambacounda-queue.service -f     # jobs traités, en direct
```

## 3. Rechargement de PHP-FPM par le script de déploiement

`deploy.sh` recharge PHP-FPM pour vider l'OPcache. Pour que ça marche sans
mot de passe, avec l'utilisateur qui lance le déploiement (`deploy` ici) :

```bash
sudo visudo -f /etc/sudoers.d/tambacounda-deploy
```

```
deploy ALL=(root) NOPASSWD: /bin/systemctl reload php8.3-fpm
```

Adaptez la version de PHP. Si le nom du service diffère, lancez le script
ainsi : `PHP_FPM_SERVICE=php8.4-fpm ./deploy.sh`. Sans cette règle, le
déploiement continue et affiche seulement un avertissement.

## 4. Variables d'environnement à vérifier en production

| Variable | Valeur |
|---|---|
| `APP_ENV` / `APP_DEBUG` | `production` / `false` |
| `QUEUE_CONNECTION` | `redis` |
| `FRONTEND_URL` | l'URL publique du frontend Vercel |
| `FRONTEND_REVALIDATE_SECRET` | le même secret que `REVALIDATE_SECRET` dans Vercel (`openssl rand -hex 32`) |

## 5. Après une modification du code de ces unités

```bash
sudo systemctl daemon-reload
sudo systemctl restart tambacounda-queue.service
```

`deploy.sh` n'en a pas besoin : `php artisan queue:restart` suffit à faire
repartir les workers sur le nouveau code.
