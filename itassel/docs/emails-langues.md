# Emails citoyens FR / EN / AR

Le dépôt public accepte `langue` : `fr` (défaut), `en` ou `ar`. La valeur est stockée dans `doleances.langue` et appliquée aux 7 emails citoyens via `Mailable::locale()`.

Fichiers : `lang/fr/emails.php`, `lang/en/emails.php`, `lang/ar/emails.php` (mêmes clés). L’arabe est rendu en `dir="rtl"` avec Tahoma/Arial.

Les emails internes (invitation, réinitialisation de mot de passe, notification d’agent) restent en français.

Limite : les libellés de statut et les `message_citoyen` sont des textes configurables en base, saisis en français. Ils apparaissent tels quels dans les emails, sans traduction automatique.
