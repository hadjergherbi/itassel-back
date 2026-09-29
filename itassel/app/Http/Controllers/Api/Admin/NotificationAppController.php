<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\NotificationApp;
use Illuminate\Http\Request;

class NotificationAppController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate([
            'par_page' => ['nullable', 'integer', 'min:1', 'max:50'],
            'non_lues' => ['nullable'],
        ]);

        $query = $request->user()->notificationsApp()
            ->with([
                'doleance:id_doleance,reference',
                'note:id_note,id_auteur',
                'note.auteur:id_utilisateur,nom,prenom',
            ])
            ->orderByDesc('created_at');

        if ($request->boolean('non_lues')) {
            $query->whereNull('lue_le');
        }

        $parPage = (int) ($data['par_page'] ?? 20);

        return response()->json(
            $query->paginate($parPage)->through(function (NotificationApp $n) {
                $ligne = [
                    'id_notification_app' => $n->id_notification_app,
                    'evenement' => $n->evenement,
                    'titre' => $n->titre,
                    'message' => $n->message,
                    'lue_le' => $n->lue_le,
                    'created_at' => $n->created_at,
                    'id_note' => $n->id_note,
                    'doleance' => $n->doleance
                        ? ['reference' => $n->doleance->reference]
                        : null,
                ];

                if ($n->evenement === 'mention_note') {
                    $ligne['auteur'] = $n->note?->auteur
                        ? [
                            'prenom' => $n->note->auteur->prenom,
                            'nom' => $n->note->auteur->nom,
                        ]
                        : null;
                }

                return $ligne;
            })
        );
    }

    public function compteur(Request $request)
    {
        return response()->json([
            'non_lues' => $request->user()->notificationsApp()->whereNull('lue_le')->count(),
        ]);
    }

    public function lire(Request $request, int $id)
    {
        $notification = NotificationApp::find($id);

        if (! $notification || $request->user()->cannot('update', $notification)) {
            return response()->json(['message' => 'Notification introuvable.'], 404);
        }

        if (! $notification->lue_le) {
            $notification->update(['lue_le' => now()]);
        }

        return response()->json(['message' => 'Notification lue.', 'notification' => $notification]);
    }

    public function lireTout(Request $request)
    {
        $request->user()->notificationsApp()->whereNull('lue_le')->update(['lue_le' => now()]);

        return response()->json(['message' => 'Notifications marquées comme lues.']);
    }
}
