# ITASSEL — API Backend

API REST Laravel de la plateforme de gestion des doléances citoyennes du Ministère des Sports (Algérie).

Ce dépôt contient uniquement le back-end. L’interface d’administration et le formulaire citoyen sont une application frontend séparée.

## Stack technique

- **Laravel 12**
- **Laravel Sanctum 4** — authentification par jeton
- **MySQL**
- **PHPUnit 11** — suite de tests

## Fonctionnalités principales

- Dépôt public d’une doléance et suivi citoyen (code de vérification, réponse à un complément)
- Authentification des administrateurs (connexion, invitation, mot de passe oublié)
- Tableau de bord et liste des doléances, filtrés selon le service
- Traitement des dossiers : statuts, réponses, notes internes, pièces jointes, compléments, reclassement
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

Pour les comptes de démonstration, définissez `ADMIN_DEMO_PASSWORD` dans `.env` (obligatoire hors `local` et `testing`).

## Lancer en développement

```bash
php artisan serve
```

L’API est disponible par défaut sur `http://127.0.0.1:8000`. Les routes métier sont préfixées par `/api`.

## Tests

```bash
php artisan test
```

## Authentification

L’API utilise Laravel Sanctum avec des **jetons Bearer**, sans cookies de session.

Après `POST /api/admin/login`, envoyez le jeton dans l’en-tête :

```http
Authorization: Bearer <jeton>
```

## Frontend

Le dépôt de l’interface (React) : [lien du repo frontend]
