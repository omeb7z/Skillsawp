-- =====================================================================
--  SkillSwap Campus — schéma de la base SQLite
--  Les règles de gestion critiques sont doublées au niveau de la base
--  (CHECK, UNIQUE, index partiels) : même un double clic ou deux requêtes
--  simultanées ne peuvent pas violer RG01, RG03, RG07, RG11, RG14, RG15, RG16.
-- =====================================================================
PRAGMA foreign_keys = ON;

CREATE TABLE utilisateur (
    id                INTEGER PRIMARY KEY,
    nom               TEXT    NOT NULL,
    prenom            TEXT    NOT NULL,
    email             TEXT    NOT NULL UNIQUE COLLATE NOCASE,          -- RG01
    mot_de_passe_hash TEXT    NOT NULL,                                -- jamais en clair
    role              TEXT    NOT NULL CHECK (role IN ('ETUDIANT','ADMIN')),
    filiere           TEXT,
    bio               TEXT,
    actif             INTEGER NOT NULL DEFAULT 1 CHECK (actif IN (0,1)),
    cree_le           TEXT    NOT NULL
);

-- Portefeuille de temps : 1 crédit = 1 heure
CREATE TABLE portefeuille (
    etudiant_id          INTEGER PRIMARY KEY REFERENCES utilisateur(id),
    disponible           INTEGER NOT NULL DEFAULT 0 CHECK (disponible >= 0),  -- RG16
    bloque               INTEGER NOT NULL DEFAULT 0 CHECK (bloque     >= 0),  -- RG16
    bienvenue_attribuee  INTEGER NOT NULL DEFAULT 0 CHECK (bienvenue_attribuee IN (0,1))
);

CREATE TABLE categorie (
    id          INTEGER PRIMARY KEY,
    nom         TEXT NOT NULL UNIQUE COLLATE NOCASE,
    description TEXT,
    couleur     TEXT NOT NULL DEFAULT '#c8553d',
    active      INTEGER NOT NULL DEFAULT 1 CHECK (active IN (0,1))
);

CREATE TABLE competence (
    id           INTEGER PRIMARY KEY,
    nom          TEXT NOT NULL COLLATE NOCASE,
    categorie_id INTEGER NOT NULL REFERENCES categorie(id),
    UNIQUE (nom, categorie_id)
);

-- Compétences proposées / recherchées déclarées dans le profil
CREATE TABLE etudiant_competence (
    etudiant_id   INTEGER NOT NULL REFERENCES utilisateur(id),
    competence_id INTEGER NOT NULL REFERENCES competence(id),
    sens          TEXT    NOT NULL CHECK (sens IN ('PROPOSEE','RECHERCHEE')),
    PRIMARY KEY (etudiant_id, competence_id, sens)
);

CREATE TABLE offre (
    id              INTEGER PRIMARY KEY,
    intervenant_id  INTEGER NOT NULL REFERENCES utilisateur(id),
    titre           TEXT    NOT NULL,
    competence_id   INTEGER REFERENCES competence(id),
    description     TEXT,
    niveau          TEXT CHECK (niveau IN ('DEBUTANT','INTERMEDIAIRE','AVANCE')),
    duree           INTEGER CHECK (duree IN (1,2)),                        -- RG02
    mode            TEXT CHECK (mode IN ('PRESENTIEL','DISTANCE')),
    etat            TEXT NOT NULL DEFAULT 'BROUILLON'
                    CHECK (etat IN ('BROUILLON','PUBLIEE','ARCHIVEE')),    -- RG04
    masquee         INTEGER NOT NULL DEFAULT 0 CHECK (masquee IN (0,1)),
    motif_masquage  TEXT,
    cree_le         TEXT NOT NULL,
    modifie_le      TEXT NOT NULL
);

CREATE TABLE creneau (
    id        INTEGER PRIMARY KEY,
    offre_id  INTEGER NOT NULL REFERENCES offre(id),
    debut     TEXT    NOT NULL,          -- 'YYYY-MM-DD HH:MM'
    fin       TEXT    NOT NULL,
    lieu      TEXT,                      -- RG19 : visible des seuls participants
    lien      TEXT,
    supprime  INTEGER NOT NULL DEFAULT 0 CHECK (supprime IN (0,1)),
    CHECK (fin > debut)
);

CREATE TABLE reservation (
    id                 INTEGER PRIMARY KEY,
    creneau_id         INTEGER NOT NULL REFERENCES creneau(id),
    beneficiaire_id    INTEGER NOT NULL REFERENCES utilisateur(id),
    intervenant_id     INTEGER NOT NULL REFERENCES utilisateur(id),
    cout               INTEGER NOT NULL CHECK (cout IN (1,2)),            -- RG02
    etat               TEXT    NOT NULL DEFAULT 'EN_ATTENTE'
                       CHECK (etat IN ('EN_ATTENTE','REFUSEE','ACCEPTEE',
                                       'ANNULEE','REALISEE','EN_LITIGE')), -- RG09
    message            TEXT,
    motif_refus        TEXT,
    motif_annulation   TEXT,
    annulee_par        INTEGER REFERENCES utilisateur(id),
    cree_le            TEXT NOT NULL,
    decidee_le         TEXT,
    cloturee_le        TEXT,
    CHECK (beneficiaire_id <> intervenant_id)                             -- RG03
);
-- RG07 : un créneau n'a qu'une seule réservation attribuée
CREATE UNIQUE INDEX ux_creneau_attribue
    ON reservation(creneau_id) WHERE etat IN ('ACCEPTEE','EN_LITIGE','REALISEE');
-- RG03 : pas de doublon en attente / acceptée pour le même créneau
CREATE UNIQUE INDEX ux_reservation_doublon
    ON reservation(creneau_id, beneficiaire_id) WHERE etat IN ('EN_ATTENTE','ACCEPTEE');

-- RG11 : chaque participant confirme une seule fois
CREATE TABLE confirmation (
    reservation_id INTEGER NOT NULL REFERENCES reservation(id),
    etudiant_id    INTEGER NOT NULL REFERENCES utilisateur(id),
    confirme_le    TEXT    NOT NULL,
    PRIMARY KEY (reservation_id, etudiant_id)
);

-- Historique des crédits (livret de temps)
CREATE TABLE mouvement (
    id              INTEGER PRIMARY KEY,
    etudiant_id     INTEGER NOT NULL REFERENCES utilisateur(id),
    reservation_id  INTEGER REFERENCES reservation(id),
    type            TEXT    NOT NULL CHECK (type IN ('BIENVENUE','BLOCAGE','LIBERATION',
                                   'TRANSFERT_SORTANT','TRANSFERT_ENTRANT','RESTITUTION')),
    quantite        INTEGER NOT NULL CHECK (quantite > 0),
    effet_disponible INTEGER NOT NULL,
    effet_bloque     INTEGER NOT NULL,
    libelle         TEXT    NOT NULL,
    cree_le         TEXT    NOT NULL
);
-- RG01 : crédits de bienvenue une seule fois
CREATE UNIQUE INDEX ux_mvt_bienvenue ON mouvement(etudiant_id) WHERE type = 'BIENVENUE';
-- RG11/RG14 : un même type de mouvement ne peut exister qu'une fois par réservation
--             → impossible de transférer ou restituer deux fois
CREATE UNIQUE INDEX ux_mvt_unique_par_reservation
    ON mouvement(reservation_id, type) WHERE reservation_id IS NOT NULL;

CREATE TABLE litige (
    id             INTEGER PRIMARY KEY,
    reservation_id INTEGER NOT NULL UNIQUE REFERENCES reservation(id),    -- RG14
    ouvert_par     INTEGER NOT NULL REFERENCES utilisateur(id),
    motif          TEXT NOT NULL CHECK (motif IN ('ABSENCE','DESACCORD',
                                   'CONFIRMATION_MANQUANTE','ADMINISTRATIF')),
    etat           TEXT NOT NULL DEFAULT 'OUVERT' CHECK (etat IN ('OUVERT','REGLE')),
    decision       TEXT CHECK (decision IN ('TRANSFERT','RESTITUTION')),  -- RG13
    justification  TEXT,
    admin_id       INTEGER REFERENCES utilisateur(id),
    ouvert_le      TEXT NOT NULL,
    regle_le       TEXT,
    CHECK ((etat = 'OUVERT' AND decision IS NULL)
        OR (etat = 'REGLE'  AND decision IS NOT NULL AND justification IS NOT NULL))
);

-- Éléments versés au dossier par chaque partie (RG13, RG19)
CREATE TABLE element_litige (
    id         INTEGER PRIMARY KEY,
    litige_id  INTEGER NOT NULL REFERENCES litige(id),
    auteur_id  INTEGER NOT NULL REFERENCES utilisateur(id),
    texte      TEXT    NOT NULL,
    cree_le    TEXT    NOT NULL
);

-- RG15 : une seule évaluation par participant et par réservation
CREATE TABLE evaluation (
    id             INTEGER PRIMARY KEY,
    reservation_id INTEGER NOT NULL REFERENCES reservation(id),
    auteur_id      INTEGER NOT NULL REFERENCES utilisateur(id),
    cible_id       INTEGER NOT NULL REFERENCES utilisateur(id),
    note           INTEGER NOT NULL CHECK (note BETWEEN 1 AND 5),
    commentaire    TEXT,
    cree_le        TEXT NOT NULL,
    UNIQUE (reservation_id, auteur_id)
);

CREATE TABLE notification (
    id              INTEGER PRIMARY KEY,
    destinataire_id INTEGER NOT NULL REFERENCES utilisateur(id),
    message         TEXT    NOT NULL,
    lien            TEXT,
    lue             INTEGER NOT NULL DEFAULT 0,
    cree_le         TEXT    NOT NULL
);

-- Paramètres applicatifs (ex. décalage de l'horloge de démonstration)
CREATE TABLE parametre (
    cle    TEXT PRIMARY KEY,
    valeur TEXT NOT NULL
);

CREATE INDEX ix_offre_etat       ON offre(etat, masquee);
CREATE INDEX ix_creneau_offre    ON creneau(offre_id, debut);
CREATE INDEX ix_reservation_ben  ON reservation(beneficiaire_id, etat);
CREATE INDEX ix_reservation_int  ON reservation(intervenant_id, etat);
CREATE INDEX ix_mouvement_etu    ON mouvement(etudiant_id, cree_le);
CREATE INDEX ix_notification     ON notification(destinataire_id, lue);
CREATE INDEX ix_litige_etat      ON litige(etat, ouvert_le);
CREATE INDEX ix_element_litige   ON element_litige(litige_id, cree_le);

-- =====================================================================
--  Garde-fous de la machine d'état des litiges (RG13, RG14, RG19)
--  La couche métier les applique déjà ; ces déclencheurs empêchent aussi
--  une écriture directe ou une correction manuelle de contourner la règle.
-- =====================================================================

-- Un dossier ne peut naître que sur une séance déjà passée en litige.
CREATE TRIGGER trg_litige_insertion_ouverte
BEFORE INSERT ON litige
FOR EACH ROW
WHEN NEW.etat <> 'OUVERT'
  OR (SELECT etat FROM reservation WHERE id = NEW.reservation_id) <> 'EN_LITIGE'
BEGIN
    SELECT RAISE(ABORT, 'Un litige doit être ouvert sur une séance en litige.');
END;

-- La seule transition autorisée est OUVERT -> REGLE ; un dossier réglé est définitif.
CREATE TRIGGER trg_litige_transition_finale
BEFORE UPDATE OF etat ON litige
FOR EACH ROW
WHEN NEW.etat <> OLD.etat
  AND NOT (OLD.etat = 'OUVERT' AND NEW.etat = 'REGLE')
BEGIN
    SELECT RAISE(ABORT, 'Transition de litige invalide.');
END;

-- Une décision doit être complète : décision, motivation, auteur et date.
CREATE TRIGGER trg_litige_finalisation_complete
BEFORE UPDATE OF etat ON litige
FOR EACH ROW
WHEN NEW.etat = 'REGLE'
  AND (NEW.decision IS NULL OR NEW.justification IS NULL
       OR NEW.admin_id IS NULL OR NEW.regle_le IS NULL)
BEGIN
    SELECT RAISE(ABORT, 'Finalisation de litige incomplète.');
END;

-- Après règlement, la décision et sa motivation ne peuvent plus être modifiées.
CREATE TRIGGER trg_litige_decision_figee
BEFORE UPDATE OF decision, justification, admin_id, regle_le ON litige
FOR EACH ROW
WHEN OLD.etat = 'REGLE'
BEGIN
    SELECT RAISE(ABORT, 'La décision d''un litige réglé est définitive.');
END;

-- On n'ajoute plus d'élément à un dossier clos.
CREATE TRIGGER trg_element_litige_ouvert
BEFORE INSERT ON element_litige
FOR EACH ROW
WHEN (SELECT etat FROM litige WHERE id = NEW.litige_id) <> 'OUVERT'
BEGIN
    SELECT RAISE(ABORT, 'Ce litige est clos : aucun nouvel élément ne peut être ajouté.');
END;

-- Une séance en litige ne peut être clôturée que lorsque le dossier est réglé.
CREATE TRIGGER trg_reservation_finalisation_litige
BEFORE UPDATE OF etat ON reservation
FOR EACH ROW
WHEN OLD.etat = 'EN_LITIGE'
  AND NEW.etat IN ('REALISEE', 'ANNULEE')
  AND EXISTS (SELECT 1 FROM litige WHERE reservation_id = OLD.id AND etat = 'OUVERT')
BEGIN
    SELECT RAISE(ABORT, 'Le litige doit être réglé avant de clôturer la séance.');
END;

-- Un état final de réservation ne peut pas être rouvert.
CREATE TRIGGER trg_reservation_etat_final
BEFORE UPDATE OF etat ON reservation
FOR EACH ROW
WHEN OLD.etat IN ('REALISEE', 'ANNULEE') AND NEW.etat <> OLD.etat
BEGIN
    SELECT RAISE(ABORT, 'Une réservation clôturée est définitive.');
END;
