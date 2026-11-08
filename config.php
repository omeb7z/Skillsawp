<?php
// Configuration de SkillSwap Campus.
// Variable d'environnement SKILLSWAP_DB : chemin d'une autre base SQLite (facultatif).
return [
    'database' => getenv('SKILLSWAP_DB') ?: __DIR__ . '/data/skillswap.db',
];
