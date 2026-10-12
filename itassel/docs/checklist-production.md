# Checklist de mise en production — API ITASSEL

À vérifier avant d’exposer l’API sur le réseau du Ministère.

## Variables obligatoires

Renseigner un `.env` à partir de `.env.example` (ne jamais versionner `.env`).

- [ ] `APP_KEY` unique, généré sur le serveur (`php artisan key:generate`), jamais commité
- [ ] `ITASSEL_DEMO_PASSWORD` — obligatoire hors `local` et `testing` (comptes démo du seeder uniquement)
- [ ] `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`
- [ ] `MAIL_MAILER`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME`

## Application

- [ ] `APP_ENV=production`
- [ ] `APP_DEBUG=false`
- [ ] `APP_URL` en `https://` (URL publique de l’API)
- [ ] `LOG_LEVEL=warning` (ou `error`)

## Déploiement

Le déploiement ne relance pas les seeders : ils ne doivent pas écraser les libellés, permissions ou comptes modifiés par les administrateurs.

```bash
php artisan migrate --force && php artisan config:cache && php artisan route:cache
```

`php artisan db:seed --force` est réservé à l'installation initiale (une seule fois). Le super administrateur de production se crée avec `php artisan itassel:creer-super-admin`, pas avec les comptes `demo.*@itassel.dz`.

## Ne jamais livrer

- `.env`
- `storage/logs`
- `storage/app/private`
- `vendor`
- `database/*.sqlite`
- `dist`

Ne jamais zipper `.env`, les logs, les bases sqlite, `vendor` ni `dist`.

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
- [ ] En-tête `X-Suivi-Token` autorisé si `allowed_headers` n’est pas `*`

## Base de données

- [ ] Compte MySQL **dédié** à l’application, **sans** droits d’administration (`GRANT`, `SUPER`, `FILE`, etc.)
- [ ] Droits limités à la base ITASSEL (`SELECT`, `INSERT`, `UPDATE`, `DELETE` — et `CREATE`/`ALTER` uniquement le temps des migrations)
- [ ] `DB_PASSWORD` fort, distinct des autres services
- [ ] Sauvegarde quotidienne de la base, testée par une restauration périodique
- [ ] Les tests PHPUnit tournent sur MySQL (`itassel_test` uniquement, même moteur qu’en production). Copier `.env.testing.example` vers `.env.testing`, puis `composer test:db` et `composer test`. La base de développement n’est pas utilisée.

## Serveur PHP / Laravel

- [ ] `php artisan config:cache`
- [ ] `php artisan route:cache`
- [ ] Cron du scheduler : `* * * * * cd /chemin/vers/itassel && php artisan schedule:run >> /dev/null 2>&1` (chaque minute) — `sanctum:prune-expired` et `queue:prune-failed`
- [ ] `php artisan itassel:verifier-production` se termine sans KO

## File d'attente

Les emails (sauf le renvoi manuel) partent par un job `EnvoyerEmailNotification`. Le code OTP de suivi utilise la file `prioritaire`.

- [ ] `QUEUE_CONNECTION=database`
- [ ] Tables déjà migrées ici ; sur un serveur neuf : `php artisan queue:table && php artisan migrate`
- [ ] Superviseur (systemd ou Supervisor) : `php artisan queue:work --queue=prioritaire,default --tries=3 --max-time=3600`
- [ ] `php artisan queue:restart` à chaque déploiement
- [ ] Surveiller `php artisan queue:failed` et relancer avec `php artisan queue:retry all`
- [ ] Permissions : `storage/` et `bootstrap/cache` accessibles en écriture uniquement par l’utilisateur du pool PHP ; le reste du code en lecture
- [ ] HTTPS terminé (reverse proxy ou serveur) pour que HSTS soit émis

## Secrets et messagerie

- [ ] Faire tourner le mot de passe d'application SMTP (il a fuité dans une archive)
- [ ] Rotation des secrets à chaque changement d’équipe ou incident : `APP_KEY` (invalide les cookies chiffrés), mot de passe d’application mail régénéré, `DB_PASSWORD`
- [ ] `MAIL_PASSWORD` stocké uniquement dans l’environnement du serveur, jamais dans le dépôt
- [ ] Vérifier qu’aucun `.env` de production n’est versionné (`.gitignore` contient `.env`)

## Contrôles fonctionnels de sécurité

- [ ] Un jeton de plus de 8 h est refusé (401 « Session expirée »)
- [ ] Cinq échecs de connexion verrouillent le compte 15 minutes
- [ ] Le formulaire public exige un jeton frais et refuse le champ piège `site_web`
- [ ] Le dépôt public refuse toute `piece_jointe` (422). En réponse à un complément, une pièce jointe n’est acceptée que si l’agent l’a exigée (`piece_exigee`)
- [ ] L’aperçu des pièces jointes est en `inline` ; le téléchargement (`attachment`) exige `pieces_jointes.telecharger`
- [ ] Les JPEG/PNG déposés sont ré-encodés (plus d’EXIF GPS)

## Changements d'API pour le front

- **Référence** : format `ITS-AAAA-NNNNNN` (année + 6 chiffres), ex. `ITS-2026-000137`.
- **Suivi citoyen** : `GET /api/suivi/dossier` et `POST /api/suivi/repondre-complement` lisent le jeton dans l’en-tête `X-Suivi-Token` (plus dans l’URL ni le corps). `POST /api/suivi/verifier-code` continue de renvoyer `jeton_session` dans le JSON.
- **Pièces jointes** : permission `pieces_jointes.telecharger` (super_admin uniquement par défaut). Un `admin_service` reçoit 403 sur `GET /api/admin/pieces-jointes/{id}/telecharger` et conserve l’aperçu. `GET /api/admin/me` expose `permissions` pour masquer le bouton côté front.
- **Dépôt public** : `description` max. 5000 caractères ; `telephone` sans espaces, motif `(\+213|0)([5-7]…|[2-4]…)` ; `nom` / `prenom` lettres (latines ou arabes), espaces, tirets et apostrophes uniquement. Les valeurs « Tous les domaines » et « Toutes natures » ne sont plus proposées par `GET /api/referentiels` et sont refusées (422) à la création.
