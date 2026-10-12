# Utilisateurs et services

Une doléance reste rattachée à **un seul** service (`doleances.id_service`). Seul un compte peut en gérer plusieurs.

## Table pivot `utilisateur_service`

| Colonne | Rôle |
|---------|------|
| `id_utilisateur` | Clé étrangère vers `utilisateurs`, suppression en cascade |
| `id_service` | Clé étrangère vers `services`, suppression en cascade, indexée |
| `created_at` | Date de rattachement |

Clé primaire composite `(id_utilisateur, id_service)`.

La colonne `utilisateurs.id_service` est conservée : c'est le **service principal** (affichage, compatibilité avec le champ `service` de l'API).

## Invariant

- `admin_service` : le pivot n'est pas vide, et `id_service` (le principal) figure dans le pivot.
- `super_admin` : pivot vide et `id_service` nul.

À chaque enregistrement du modèle, le principal est ajouté au pivot s'il manque. Un super administrateur est détaché de tout service. La migration initiale recopie les `id_service` déjà renseignés des administrateurs de service.

Le domaine « Tous les domaines » n'est pas assignable (`Service::regleIdAssignable()`).

## Affectation

Création et modification d'un `admin_service` :

- `ids_services` : 1 à 10 identifiants distincts ;
- `id_service_principal` : optionnel, doit appartenir à la liste, sinon le premier élément devient le principal ;
- l'ancien champ `id_service` reste accepté et vaut `ids_services` d'un seul élément. Si les deux sont envoyés, `ids_services` fait foi.

Passer un compte en `super_admin` vide le pivot. Retirer un service est refusé (422) tant que le compte est responsable de ce service (`services.id_responsable`) ou de doléances ouvertes de ce service, sauf si un remplaçant du même service est désigné. Le journal consigne l'ancienne et la nouvelle liste.

## Visibilité

`Acces::doleancesVisibles` limite un administrateur de service aux doléances dont `id_service` est dans sa liste, **ou** dont la nature est « Toutes natures », **ou** dont le service est « Tous les domaines ». Une liste vide ne renvoie aucune doléance de service. Le détail, les notes, les pièces jointes, les compléments et les réaffectations passent par cet accès.

Le filtre `service` d'un admin n'est appliqué que s'il gère ce service ; sinon la requête répond 403. Sans filtre, il voit tous ses services.

Les notifications internes et les mentions visent les administrateurs actifs qui ont le service de la doléance dans leur pivot. Une demande de réaffectation peut partir de n'importe lequel de ses services ; la destination doit différer du service d'origine. L'acceptation ou le refus revient au super administrateur ou à un administrateur du service proposé.

Les totaux de `vueGlobale` d'un admin sont la somme des doléances de ses services. La suppression d'un service est bloquée tant qu'un utilisateur non supprimé y est rattaché dans le pivot. Si seuls des comptes supprimés y restent, leur principal est reporté sur un autre de leurs services, ou mis à nul.

## Contrat API

`service` reste le service principal (objet, ou `null` / « Tous les services » pour un super administrateur). `services` s'y ajoute :

```json
{
  "service": { "id_service": 1, "nom_service": "Sport" },
  "services": [
    { "id_service": 1, "nom_service": "Sport", "principal": true },
    { "id_service": 4, "nom_service": "Jeunesse", "principal": false }
  ]
}
```

La liste des utilisateurs charge `services` en une requête. Le filtre `service` de cette liste porte sur le pivot. Le filtre `etat` est inchangé.
