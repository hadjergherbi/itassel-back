# Tableau de bord — export PDF

Réservé au Super administrateur (`tableau_de_bord.global`).

## `GET /api/admin/tableau-de-bord/export`

Authentification : jeton Bearer Sanctum.

| Paramètre | Règle |
|-----------|--------|
| `periode` | optionnel, `30j`, `3m`, `6m` ou `annee`. Défaut : `6m`. |

Réponse : fichier `application/pdf`, A4 portrait, `Content-Disposition: attachment`.

Nom : `tableau-de-bord_{periode}_{AAAA-MM-JJ}.pdf`.

Le document ne contient que des agrégats (KPI, services, statuts, natures, mois, issues). Aucun nom, email ou téléphone de citoyen.

Une ligne de journal `export_pdf` est créée : « Statistiques du tableau de bord — période {code} ».

| Code | Cas |
|------|-----|
| 200 | PDF |
| 401 | sans jeton |
| 403 | rôle sans `tableau_de_bord.global` |
| 422 | `periode` inconnue |

L’écran JSON reste `GET /api/admin/tableau-de-bord` (`tableau_de_bord.voir`).
