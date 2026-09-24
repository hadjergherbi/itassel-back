# API — Notes internes

Toutes les routes exigent `Authorization: Bearer <jeton>`, un compte actif et la permission `doleances.notes`, sauf la lecture du dossier (`doleances.voir`) et la liste.

Aucune route `DELETE` : les notes ne sont jamais supprimées (traçabilité).

## Mentionnables

`GET /api/admin/doleances/{reference}/mentionnables?q=`

Throttle : 60 req/min. Accès au dossier identique aux autres actions (sinon `404`).

Retourne les utilisateurs **actifs**, non supprimés, administrateurs du service de la doléance **ou** super administrateurs, **hors** l’utilisateur connecté. `q` filtre nom/prénom (insensible à la casse), limite 8. **Jamais d’e-mail.**

```json
[
  {
    "id_utilisateur": 12,
    "nom": "Kaci",
    "prenom": "Amine",
    "initiales": "AK",
    "libelle_role": "Administrateur · Sport",
    "service": "Sport"
  }
]
```

## Créer une note

`POST /api/admin/doleances/{reference}/notes`

```json
{
  "contenu": "À vérifier avec @Amine avant clôture.",
  "mentions": [12],
  "notifier_email": false,
  "etiquette": "a_verifier"
}
```

| Champ | Règles |
|---|---|
| `contenu` | requis, texte brut, max 2000 |
| `mentions` | optionnel, tableau d’ids distincts, max 5, chacun doit être mentionnable sur **ce** dossier |
| `notifier_email` | optionnel, booléen |
| `etiquette` | `information` \| `a_verifier` \| `urgent` \| `null` |

Mention hors périmètre → `422` `{ "message": "Cette personne ne peut pas être mentionnée sur ce dossier." }`.

Réponse `201` :

```json
{
  "message": "Note ajoutée.",
  "note": {
    "id_note": 41,
    "contenu": "À vérifier avec @Amine avant clôture.",
    "etiquette": "a_verifier",
    "epinglee": false,
    "epinglee_le": null,
    "epinglee_par": null,
    "created_at": "2026-09-24T12:00:00.000000Z",
    "date_creation": "2026-09-24T12:00:00.000000Z",
    "modifiee_le": null,
    "modifiable_jusqu_a": "2026-09-24T12:15:00.000000Z",
    "est_auteur": true,
    "auteur": {
      "id_utilisateur": 4,
      "nom": "Notes",
      "prenom": "Auteur",
      "initiales": "AN",
      "libelle_role": "Administrateur · Sport"
    },
    "mentions": [
      { "id_utilisateur": 12, "nom": "Kaci", "prenom": "Amine" }
    ]
  }
}
```

Chaque mentionné reçoit une notification application (`mention_note`). Si `notifier_email` : e-mail après la réponse, lien `{frontend_url}/admin/doleances/{reference}`.

## Modifier sa note

`PUT /api/admin/doleances/{reference}/notes/{id}`

```json
{
  "contenu": "Version corrigée.",
  "mentions": [12, 18],
  "etiquette": "urgent"
}
```

- Auteur uniquement → sinon `403` « Vous ne pouvez modifier que vos propres notes. »
- Dans les 15 minutes (`created_at` + `notes.modification_minutes`) → sinon `409` « Le délai de modification de 15 minutes est dépassé. »
- Seules les **nouvelles** mentions sont notifiées (pas de renvoi à celles déjà présentes).

Réponse `200` : `{ "message": "Note mise à jour.", "note": { … } }` (même format).

## Épingler / désépingler

`POST /api/admin/doleances/{reference}/notes/{id}/epingler`

Sans corps. Tout agent ayant accès au dossier peut basculer. Maximum 3 notes épinglées par doléance → `422` « 3 notes au maximum peuvent être épinglées. »

Réponse `200` : `{ "message": "Note épinglée.", "note": { … } }` (ou « Note désépinglée. »).

## Détail du dossier

`GET /api/admin/doleances/{reference}` — clé `notes_internes`

Ordre : épinglées d’abord (`epinglee_le`), puis les autres par `created_at` croissant. `modifiable_jusqu_a` n’est renseigné que pour l’auteur pendant le délai. `date_creation` est conservé pour compatibilité. Les notes ne sont **pas** mélangées à `historique`.

## Liste

`GET /api/admin/doleances` — chaque ligne expose `nb_notes` (compteur). Pas de `nb_notes_non_vues`.
