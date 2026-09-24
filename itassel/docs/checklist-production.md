# Checklist de mise en production — API ITASSEL

À vérifier avant d’exposer l’API sur le réseau du Ministère.

## Application

- [ ] `APP_ENV=production`
- [ ] `APP_DEBUG=false`
- [ ] `APP_URL` en `https://` (URL publique de l’API)
- [ ] `APP_KEY` unique, généré sur le serveur (`php artisan key:generate`), jamais commité
- [ ] `LOG_LEVEL=error` (ou `warning`)

## Session et cookies

- [ ] `SESSION_ENCRYPT=true`
- [ ] `SESSION_SECURE_COOKIE=true`
- [ ] `SESSION_HTTP_ONLY=true`
- [ ] `SESSION_SAME_SITE=lax` (ou `strict` si le front est servi depuis le même site)

## Jetons et CORS

- [ ] `SANCTUM_EXPIRATION=480` (8 heures) — ou une durée plus courte si le RSSI l’exige
- [ ] `CORS_ALLOWED_ORIGINS` limité au domaine du Ministère (pas de `*`, pas de localhost)
- [ ] `ITASSEL_FRONTEND_URL` / `FRONTEND_URL` identiques à l’origine autorisée
- [ ] `SANCTUM_STATEFUL_DOMAINS` limité aux hôtes légitimes si des cookies d’état sont utilisés

## Base de données

- [ ] Compte MySQL **dédié** à l’application, **sans** droits d’administration (`GRANT`, `SUPER`, `FILE`, etc.)
- [ ] Droits limités à la base ITASSEL (`SELECT`, `INSERT`, `UPDATE`, `DELETE` — et `CREATE`/`ALTER` uniquement le temps des migrations)
- [ ] `DB_PASSWORD` fort, distinct des autres services
- [ ] Sauvegarde quotidienne de la base, testée par une restauration périodique

## Serveur PHP / Laravel

- [ ] `php artisan config:cache`
- [ ] `php artisan route:cache`
- [ ] Cron du scheduler : `* * * * * cd /chemin/vers/itassel && php artisan schedule:run >> /dev/null 2>&1` (chaque minute) — nécessaire pour `sanctum:prune-expired`
- [ ] Permissions : `storage/` et `bootstrap/cache` accessibles en écriture uniquement par l’utilisateur du pool PHP ; le reste du code en lecture
- [ ] HTTPS terminé (reverse proxy ou serveur) pour que HSTS soit émis

## Secrets et messagerie

- [ ] Rotation des secrets à chaque changement d’équipe ou incident : `APP_KEY` (invalide les cookies chiffrés), mot de passe d’application mail régénéré, `DB_PASSWORD`
- [ ] `MAIL_PASSWORD` stocké uniquement dans l’environnement du serveur, jamais dans le dépôt
- [ ] Vérifier qu’aucun `.env` de production n’est versionné (`.gitignore` contient `.env`)

## Contrôles fonctionnels de sécurité

- [ ] Un jeton de plus de 8 h est refusé (401 « Session expirée »)
- [ ] Cinq échecs de connexion verrouillent le compte 15 minutes
- [ ] Le formulaire public exige un jeton frais et refuse le champ piège `site_web`
- [ ] Les pièces jointes se téléchargent en `attachment` ; les JPEG/PNG sont ré-encodés (plus d’EXIF GPS)
