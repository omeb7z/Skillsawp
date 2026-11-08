<?php
declare(strict_types=1);

final class ReservationsControleur extends Controleur
{
    public function demander(string $creneau): never
    {
        $u = Securite::exigerEtudiant();
        [$ok, $rid] = $this->tenter(fn() => $this->svcReservations()->demander((int) $u['id'], (int) $creneau, $this->post('message')),
            "Demande envoyée ! Vous serez notifié de la décision de l'intervenant.");
        if ($ok) {
            rediriger("/reservations/$rid");
        }
        $this->retour('/catalogue');
    }

    public function demandes(): never
    {
        $u = Securite::exigerEtudiant();
        vue('reservations/demandes', ['titre' => 'Demandes', 'onglet' => $this->get('onglet') === 'envoyees' ? 'envoyees' : 'recues',
            'recues' => $this->svcReservations()->demandesRecues((int) $u['id']),
            'envoyees' => $this->svcReservations()->demandesEnvoyees((int) $u['id'])]);
    }

    /** Parcours visuel d'une séance : 5 étapes « fait », « courant », « stop » ou « » (à venir). */
    public static function etapes(array $r, string $maintenant, int $nbConf, ?array $litige): array
    {
        $noms = ['Demandée', 'Acceptée', 'Séance', 'Confirmée ×2', 'Heures transférées'];
        $etat = array_fill(0, 5, '');
        $etat[0] = 'fait';
        switch ($r['etat']) {
            case 'EN_ATTENTE':
                $etat[1] = 'courant';
                break;
            case 'REFUSEE':
                [$etat[1], $noms[1]] = ['stop', 'Refusée'];
                break;
            case 'ANNULEE':
                $retiree = $r['annulee_par'] !== null
                    && (int) $r['annulee_par'] === (int) $r['beneficiaire_id']
                    && str_contains((string) ($r['motif_annulation'] ?? ''), 'retirée');
                if ($retiree) {
                    [$etat[1], $noms[1]] = ['stop', 'Retirée'];
                } elseif ($litige) {
                    $etat = ['fait', 'fait', 'fait', 'stop', 'stop'];
                    [$noms[3], $noms[4]] = ['Litige', 'Restituées'];
                } else {
                    $etat[1] = 'fait';
                    [$etat[2], $noms[2]] = ['stop', 'Annulée'];
                }
                break;
            case 'ACCEPTEE':
                $etat[1] = 'fait';
                if ($maintenant < $r['fin']) {
                    $etat[2] = 'courant';
                } else {
                    [$etat[2], $etat[3], $noms[3]] = ['fait', 'courant', "Confirmée $nbConf/2"];
                }
                break;
            case 'EN_LITIGE':
                $etat[1] = $etat[2] = 'fait';
                [$etat[3], $noms[3]] = ['stop', 'Litige ouvert'];
                break;
            case 'REALISEE':
                $etat = array_fill(0, 5, 'fait');
                if ($litige) {
                    $noms[3] = "Réglée par l'admin";
                }
                break;
        }
        return array_map(null, $noms, $etat);
    }

    public function detail(string $id): never
    {
        $u = Securite::exigerEtudiant();
        $uid = (int) $u['id'];
        $r = $this->svcReservations()->reservation((int) $id);
        if ($r === null || !in_array($uid, [(int) $r['beneficiaire_id'], (int) $r['intervenant_id']], true)) {
            throw new ErreurHttp(404);                // RG19 : réservé aux participants
        }
        $maintenant = Horloge::maintenantTxt($this->db);
        $litige = $this->svcReservations()->litigeDe((int) $id);
        $confs = $this->svcReservations()->confirmations((int) $id);
        vue('reservations/detail', [
            'titre' => $r['titre'], 'r' => $r, 'maintenant' => $maintenant, 'jeSuisIntervenant' => $uid === (int) $r['intervenant_id'],
            'etapes' => self::etapes($r, $maintenant, count($confs), $litige), 'confirmations' => $confs, 'litige' => $litige,
            'monEvaluation' => $this->svcEvaluations()->de((int) $id, $uid),
        ]);
    }

    public function agir(string $id, string $action): never
    {
        $u = Securite::exigerEtudiant();
        $uid = (int) $u['id'];
        $rid = (int) $id;
        if ($action === 'signaler') {
            [$ok, $lid] = $this->tenter(fn() => $this->svcLitiges()->signaler($uid, $rid, $this->post('motif'), $this->post('description')),
                "Signalement transmis à l'administration. Les crédits restent bloqués.");
            rediriger($ok ? "/litiges/$lid" : "/reservations/$rid");
        }
        $operations = [
            'accepter' => [fn() => $this->svcReservations()->accepter($uid, $rid),
                'Demande acceptée : les crédits du bénéficiaire sont bloqués et la séance est au planning.'],
            'refuser' => [fn() => $this->svcReservations()->refuser($uid, $rid, $this->post('motif')), 'Demande refusée.'],
            'retirer' => [fn() => $this->svcReservations()->retirer($uid, $rid), 'Demande retirée.'],
            'annuler' => [fn() => $this->svcReservations()->annuler($uid, $rid, $this->post('motif')),
                'Séance annulée : les crédits ont été libérés et le créneau est de nouveau disponible.'],
            'confirmer' => [fn() => $this->svcReservations()->confirmer($uid, $rid), null],
            'evaluer' => [fn() => $this->svcEvaluations()->evaluer($uid, $rid, $this->post('note'), $this->post('commentaire')),
                'Merci pour votre évaluation !'],
        ];
        if (!isset($operations[$action])) {
            throw new ErreurHttp(404);
        }
        [$operation, $message] = $operations[$action];
        [$ok, $resultat] = $this->tenter($operation, $message);
        if ($ok && $action === 'confirmer') {
            flash('succes', $resultat ? 'Les deux confirmations sont réunies : crédits transférés à l\'intervenant !'
                : 'Confirmation enregistrée. En attente de la confirmation de votre partenaire.');
        }
        if (in_array($action, ['accepter', 'refuser'], true) && $this->post('retour') === 'demandes') {
            rediriger('/demandes');
        }
        rediriger("/reservations/$rid");
    }

    public function litige(string $id): never
    {
        $u = Securite::exigerConnexion();
        try {
            $l = $this->svcLitiges()->verifierAcces((int) $id, $u);
        } catch (AccesRefuse) {
            throw new ErreurHttp(403);
        } catch (ErreurMetier) {
            throw new ErreurHttp(404);
        }
        vue('reservations/litige', ['titre' => "Litige n°{$l['id']}", 'l' => $l, 'elements' => $this->svcLitiges()->elements((int) $id)]);
    }

    public function ajouterElement(string $id): never
    {
        $u = Securite::exigerConnexion();
        $this->tenter(fn() => $this->svcLitiges()->ajouterElement($u, (int) $id, $this->post('texte')), 'Élément ajouté au dossier.');
        rediriger("/litiges/$id");
    }
}
