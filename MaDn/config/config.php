<?php
// Grundkonfiguration
define('DB_PATH', __DIR__ . '/../database/game.db');
define('BASE_URL', 'http://localhost/mensch-aergere-dich-nicht');
define('ASSETS_URL', BASE_URL . '/assets');

// Spiel-Einstellungen
define('MAX_PLAYERS', 4);
define('MIN_PLAYERS', 2);
define('GAME_TIMEOUT', 300); // 5 Minuten Inaktivität

// Session-Einstellungen
ini_set('session.gc_maxlifetime', 3600);
session_start();
?>