# API administrateur de service

Toutes les routes ci-dessous sont préfixées par `/api/admin`, authentifiées (`Authorization: Bearer`) et filtrées par `Acces::doleancesVisibles` : un administrateur de service ne voit que les doléances de son service. Le Super administrateur conserve la vue globale.

## GET `/tableau-de-bord`

Permission : `tableau_de_bord.voir`.

| Paramètre | Valeurs | Défaut |
|-----------|---------|--------|
| `periode` | `30j` · `3m` · `6m` · `annee` | `6m` |

Exemple de réponse (extrait) :

```json
{
  "periode": {
    "code": "6m",
    "date_debut": "2026-03-24",
    "date_fin": "2026-09-24",
    "libelle": "Sur 6 mois"
  },
  "indicateurs": {
    "nouvelles": 4,
    "en_cours": 2,
    "resolues": 3,
    "total": 12,
    "depuis": "2026-01-10",
    "delai_moyen_jours": 8.5,
    "delai_moyen_tendance_jours": -1.2
  },
  "par_mois": [
    { "mois": "2026-09", "libelle": "Sept.", "total": 2, "en_cours": true }
  ],
  "repartition": [{ "code": "nouvelle", "libelle": "Nouvelle", "total": 4 }],
  "repartition_nature": [{ "id_nature": 1, "libelle": "Réclamation", "total": 7 }],
  "priorites": {
    "complements_a_examiner": 1,
    "nouvelles_en_retard": 2,
    "informations_sans_reponse": 1,
    "seuils": { "nouvelle_jours": 5, "information_jours": 15 }
  },
  "dernieres": [
    {
      "reference": "ITS-2026-0001",
      "nom": "Benali",
      "prenom": "Sara",
      "nature": "Réclamation",
      "date_depot": "2026-09-10T00:00:00.000000Z",
      "statut": { "id_statut": 1, "code": "nouvelle", "libelle": "Nouvelle", "couleur": "bleu" },
      "age_jours": 14,
      "niveau_age": "rouge"
    }
  ],
  "mes_reaffectations": [
    {
      "id_reaffectation": 3,
      "reference": "ITS-2026-0004",
      "service_propose": "Jeunesse",
      "service_destination": null,
      "etat": "refusee",
      "motif": "Hors périmètre",
      "motif_refus": "Refus : compétence jeunesse",
      "date_demande": "2026-09-20T10:00:00.000000Z",
      "date_decision": "2026-09-21T09:00:00.000000Z"
    }
  ],
  "mes_reaffectations_en_attente": 0
}
```

`nouvelles` et `en_cours` sont l’état actuel (hors période). `resolues` et `total` portent sur les dépôts de la période. Le Super administrateur reçoit en plus `vue_globale` et `issues`.

## GET `/doleances`

Permission : `doleances.voir`.

Filtres : `statut`, `service`, `q`, `nature`, `natures[]`, `periode` (`7j|30j|3m|6m|annee`), `date_debut`, `date_fin`, `age_min` (≥ 1), `info_sans_reponse` (`0|1`), `a_examiner` (`0|1`), `issue`, `tri`, `sens`, `par_page`.

Écart max entre `date_debut` et `date_fin` : 12 mois. Si `date_fin` < `date_debut` → 422.

`a_examiner=1` ne conserve que les dossiers avec un complément `etat=recu`.

## GET `/doleances/export/apercu`

Permission : `doleances.exporter`.

```json
{
  "total": 64,
  "par_nature": [
    { "id_nature": 1, "libelle": "Réclamation", "total": 38 },
    { "id_nature": 2, "libelle": "Signalement", "total": 0 }
  ],
  "date_debut": "2026-03-01",
  "date_fin": "2026-09-24"
}
```

`par_nature` ignore le filtre nature (toutes les natures, y compris à 0). `total` applique `natures[]` + période.

## GET `/doleances/export`

Permission : `doleances.exporter`.

| Paramètre | Valeurs | Défaut |
|-----------|---------|--------|
| `format` | `csv` · `pdf` | `csv` |
| + mêmes filtres que la liste | | |

- 0 résultat → `422 { "message": "Aucune doléance ne correspond à ces critères." }`
- Nom de fichier : `doleances_{slug_service}_{date_debut}_{date_fin}.csv|pdf` (en-tête `Content-Disposition`, exposé CORS).

## GET `/me`

Champs existants conservés, plus `derniere_connexion` et `connexion_precedente`.

```json
{
  "id_utilisateur": 4,
  "nom": "Kaci",
  "prenom": "Lina",
  "email": "lina@itassel.test",
  "role": "admin_service",
  "service": { "id_service": 1, "nom_service": "Sport" },
  "libelle_role": "Administrateur de service",
  "derniere_connexion": "2026-09-24T11:00:00.000000Z",
  "connexion_precedente": "2026-09-24T10:00:00.000000Z",
  "permissions": [],
  "compteur_nouvelles": 4,
  "notifications_non_lues": 2
}
```

## PUT `/mot-de-passe`

`throttle:5,1`. Corps : `mot_de_passe_actuel`, `mot_de_passe`, `mot_de_passe_confirmation`.

- actuel incorrect → 422 `mot_de_passe_actuel`
- identique à l’actuel → 422 `mot_de_passe`
- succès → `{ "message": "Mot de passe mis à jour." }`, autres jetons Sanctum révoqués

## GET `/mes-notifications`

Pagination Laravel. `par_page` optionnel (1–50, défaut 20). Chaque élément : `id_notification_app`, `evenement`, `titre`, `message`, `lue_le`, `created_at`, `doleance.reference`.
