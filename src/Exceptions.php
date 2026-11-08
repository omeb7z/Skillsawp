<?php
declare(strict_types=1);

/** Violation d'une règle de gestion : message affichable à l'utilisateur. */
class ErreurMetier extends RuntimeException
{
    public function __construct(string $message, public readonly ?string $regle = null)
    {
        parent::__construct($message);
    }
}

/** L'utilisateur n'a pas le droit d'agir sur cette ressource (→ HTTP 403). */
class AccesRefuse extends ErreurMetier {}

/** Erreur HTTP à afficher avec une page dédiée (400, 403, 404). */
class ErreurHttp extends RuntimeException
{
    public function __construct(public readonly int $codeHttp)
    {
        parent::__construct((string) $codeHttp);
    }
}
