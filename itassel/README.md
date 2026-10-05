# ITASSEL — API Backend

API REST Laravel de la plateforme de gestion des doléances citoyennes du Ministère des Sports (Algérie).

Ce dépôt contient uniquement le back-end. L’interface d’administration et le formulaire citoyen sont une application frontend séparée.

## Stack technique

- **Laravel 12**
- **Laravel Sanctum 4** — authentification par jeton
- **MySQL**
- **PHPUnit 11** — suite de tests

## Fonctionnalités principales

- Dépôt public d’une doléance (sans pièce jointe) et suivi citoyen (code de vérification, réponse à un complément ; pièce jointe acceptée uniquement si l’agent l’a exigée)
- Authentification des administrateurs (connexion, invitation, mot de passe oublié)
- Tableau de bord et liste des doléances, filtrés selon le service
- Traitement des dossiers : statuts, réponses, notes internes, pièces jointes (acceptées uniquement si l’agent les a exigées dans la demande de complément), compléments, reclassement
- Réaffectation d’un dossier d’un service à un autre
- Gestion des utilisateurs, rôles, permissions et services
- Notifications dans l’application et par e-mail
- Journal d’audit
- Export CSV et PDF des doléances et du journal
- Paramètres de référence (natures, qualités, statuts, modèles de message)

## Prérequis

- PHP 8.2 ou supérieur
- Composer
- MySQL

## Installation

```bash
git clone <url-du-depot>
cd itassel
composer install
cp .env.example .env
php artisan key:generate
```

Renseignez les identifiants de la base de données (`DB_*`) dans `.env`, puis :

```bash
php artisan migrate
php artisan db:seed
```

Pour les comptes de démonstration, définissez `ITASSEL_DEMO_PASSWORD` dans `.env` (obligatoire hors `local` et `testing`).

## Lancer en développement

```bash
php artisan serve
```

L’API est disponible par défaut sur `http://127.0.0.1:8000`. Les routes métier sont préfixées par `/api`.

## Tests

Par défaut, PHPUnit utilise SQLite en mémoire (`phpunit.xml`) :

```bash
php artisan test
```

Pour la parité avec MySQL (production), créer une base **dédiée** dont le nom se termine par `_test` — jamais la base `itassel` de développement :

```sql
CREATE DATABASE itassel_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Les identifiants `DB_USERNAME` / `DB_PASSWORD` sont ceux du `.env`. La configuration `phpunit.mysql.xml` force `DB_DATABASE=itassel_test`. Une garde dans `tests/TestCase.php` refuse toute base MySQL qui ne finit pas par `_test`.

```bash
composer test:mysql
```

`php artisan test` ajoute toujours `phpunit.xml`. Le script passe aussi `phpunit.mysql.xml` : PHPUnit avertit que `--configuration` est répété, puis utilise le fichier MySQL. `failOnPhpunitWarning="false"` dans ce fichier évite qu’un code de sortie 1 soit renvoyé pour cet avertissement seul.

`tests/Feature/Phase0SqliteTest.php` (premier cas) ne s’exécute que sur SQLite : il vérifie le chemin mémoire. Le reste de la suite tourne sur les deux moteurs.

## Langues des emails citoyens

Les 7 emails envoyés au citoyen (dépôt, code, statut, complément, annulation, réponse, accusé) suivent `doleances.langue` (`fr`, `en`, `ar`). Les emails internes (invitation, mot de passe, notification d’agent) restent en français.

Les libellés de statut et `message_citoyen` sont des données configurables en base, rédigées en français : ils ne sont pas traduits dans les fichiers `lang/*/emails.php`.

## Authentification

L’API utilise Laravel Sanctum avec des **jetons Bearer**, sans cookies de session.

Après `POST /api/admin/login`, envoyez le jeton dans l’en-tête :

```http
Authorization: Bearer <jeton>
```

## Frontend

Le dépôt de l’interface (React) : [lien du repo frontend]
