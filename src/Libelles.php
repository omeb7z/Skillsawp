<?php
declare(strict_types=1);

/** Valeurs des énumérations et leurs libellés affichés. */
final class Libelles
{
    public const NIVEAUX = ['DEBUTANT' => 'Débutant', 'INTERMEDIAIRE' => 'Intermédiaire', 'AVANCE' => 'Avancé'];
    public const MODES = ['PRESENTIEL' => 'Présentiel', 'DISTANCE' => 'À distance'];
    public const MOTIFS_LITIGE = [
        'ABSENCE' => "Absence de l'autre participant",
        'DESACCORD' => 'Désaccord sur le déroulement',
        'CONFIRMATION_MANQUANTE' => 'Confirmation manquante',
        'ADMINISTRATIF' => "Dossier ouvert par l'administration",
    ];
    public const ETATS_RESERVATION = [
        'EN_ATTENTE' => ['En attente', 'attente'],
        'REFUSEE' => ['Refusée', 'refus'],
        'ACCEPTEE' => ['Acceptée', 'ok'],
        'ANNULEE' => ['Annulée', 'neutre'],
        'REALISEE' => ['Réalisée', 'fait'],
        'EN_LITIGE' => ['En litige', 'litige'],
    ];
    public const ETATS_OFFRE = [
        'BROUILLON' => ['Brouillon', 'neutre'],
        'PUBLIEE' => ['Publiée', 'ok'],
        'ARCHIVEE' => ['Archivée', 'refus'],
    ];
    public const TYPES_MOUVEMENT = [
        'BIENVENUE' => ['Bienvenue', "Crédits offerts à l'inscription"],
        'BLOCAGE' => ['Blocage', 'Réservé pour une séance acceptée'],
        'LIBERATION' => ['Libération', 'Séance annulée : crédits rendus'],
        'TRANSFERT_SORTANT' => ['Paiement', 'Séance suivie et validée'],
        'TRANSFERT_ENTRANT' => ['Gain', 'Séance animée et validée'],
        'RESTITUTION' => ['Restitution', 'Litige réglé en votre faveur'],
    ];
    /** Effet de chaque type de mouvement sur (disponible, bloqué) — RG16 */
    public const EFFETS = [
        'BIENVENUE' => [1, 0],
        'BLOCAGE' => [-1, 1],
        'LIBERATION' => [1, -1],
        'RESTITUTION' => [1, -1],
        'TRANSFERT_SORTANT' => [0, -1],
        'TRANSFERT_ENTRANT' => [1, 0],
    ];
}
