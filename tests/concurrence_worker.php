<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

/**
 * Processus lancé en parallèle par les tests de concurrence.
 * Usage : php concurrence_worker.php <base> <heure figée> <départ µs> <action> <uid> <rid...>
 * Chaque processus attend l'instant de départ commun, puis exécute l'action :
 * on simule ainsi des double clics ou des requêtes réellement simultanées.
 */
require __DIR__ . '/../src/bootstrap.php';

[, $base, $heure, $depart, $action, $uid] = $argv;
$rids = array_map('intval', array_slice($argv, 6));
$db = Db::connecter($base);
Horloge::figer(new DateTimeImmutable($heure));

while (microtime(true) * 1e6 < (float) $depart) {
    usleep(200);
}
$resultats = [];
foreach ($rids as $rid) {
    try {
        if ($action === 'decider') {
            (new ServiceLitiges($db))->decider((int) $uid, $rid, 'TRANSFERT',
                'Décision concurrente rendue après lecture du dossier.');
        } else {
            $service = new ServiceReservations($db);
            $action === 'confirmer' ? $service->confirmer((int) $uid, $rid) : $service->accepter((int) $uid, $rid);
        }
        $resultats[] = 'ok';
    } catch (ErreurMetier $e) {
        $resultats[] = 'refus';
    }
}
echo implode(',', $resultats);
