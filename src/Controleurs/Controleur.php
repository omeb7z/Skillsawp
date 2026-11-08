<?php
declare(strict_types=1);

/**
 * Base des contrôleurs. Un contrôleur ne contient aucune règle de gestion :
 * il lit le formulaire, appelle la couche métier, puis affiche le résultat
 * ou le message d'erreur métier.
 */
abstract class Controleur
{
    protected Db $db;

    public function __construct()
    {
        $this->db = App::db();
    }

    /**
     * Exécute une opération métier et affiche un message clair dans tous les cas.
     * @return array{0: bool, 1: mixed} [succès, résultat]
     */
    protected function tenter(callable $operation, ?string $succes = null): array
    {
        try {
            $resultat = $operation();
        } catch (AccesRefuse) {
            throw new ErreurHttp(403);
        } catch (ErreurMetier $e) {
            flash('erreur', $e->getMessage());
            return [false, null];
        }
        if ($succes) {
            flash('succes', $succes);
        }
        return [true, $resultat];
    }

    protected function post(string $cle): ?string
    {
        $v = $_POST[$cle] ?? null;
        return is_string($v) ? $v : null;
    }

    protected function postListe(string $cle): array
    {
        $v = $_POST[$cle] ?? [];
        return is_array($v) ? $v : [];
    }

    protected function get(string $cle): string
    {
        $v = $_GET[$cle] ?? '';
        return is_string($v) ? trim($v) : '';
    }

    protected function retour(string $defaut): never
    {
        $ref = (string) ($_SERVER['HTTP_REFERER'] ?? '');
        $candidat = '';
        $parties = $ref !== '' ? parse_url($ref) : false;
        if (is_array($parties) && isset($parties['path']) && is_string($parties['path'])) {
            $hoteRef = strtolower((string) ($parties['host'] ?? ''));
            $hoteLocal = strtolower((string) (parse_url('http://' . ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST)
                ?: ($_SERVER['SERVER_NAME'] ?? '')));
            if ($hoteRef === '' || $hoteLocal === '' || $hoteRef === $hoteLocal) {
                $candidat = $parties['path'];
                if (isset($parties['query']) && is_string($parties['query'])) {
                    $candidat .= '?' . $parties['query'];
                }
            }
        }
        rediriger(chemin_interne($candidat, $defaut));
    }

    protected function svcComptes(): ServiceComptes { return new ServiceComptes($this->db); }
    protected function svcCatalogue(): ServiceCatalogue { return new ServiceCatalogue($this->db); }
    protected function svcOffres(): ServiceOffres { return new ServiceOffres($this->db); }
    protected function svcReservations(): ServiceReservations { return new ServiceReservations($this->db); }
    protected function svcLitiges(): ServiceLitiges { return new ServiceLitiges($this->db); }
    protected function svcEvaluations(): ServiceEvaluations { return new ServiceEvaluations($this->db); }
    protected function svcAdmin(): ServiceAdmin { return new ServiceAdmin($this->db); }
}
