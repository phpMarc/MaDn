<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Intelligenter Pfad zur Database-Klasse
$possiblePaths = [
    __DIR__ . '/../classes/Database.php',
    __DIR__ . '/classes/Database.php',
    'classes/Database.php'
];

$databaseLoaded = false;
foreach ($possiblePaths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $databaseLoaded = true;
        break;
    }
}

if (!$databaseLoaded) {
    http_response_code(500);
    echo json_encode(['error' => 'Database-Klasse nicht gefunden', 'success' => false]);
    exit();
}

function sendResponse($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data);
    exit();
}

function sendError($message, $status = 400) {
    sendResponse(['error' => $message, 'success' => false], $status);
}

function ensureGameExists($db, $gameId) {
    // Prüfen ob Spiel existiert, falls nicht -> erstellen
    $stmt = $db->prepare("SELECT id FROM games WHERE id = ?");
    $stmt->execute([$gameId]);
    
    if (!$stmt->fetch()) {
        $stmt = $db->prepare("
            INSERT INTO games (id, game_name, game_mode, game_state, created_at, updated_at)
            VALUES (?, ?, 'classic', 'waiting', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
        ");
        $gameName = 'Auto-Spiel ' . substr($gameId, -6);
        $stmt->execute([$gameId, $gameName]);
    }
}

$action = $_GET['action'] ?? '';
$gameId = $_GET['game_id'] ?? '';
$playerId = $_GET['player_id'] ?? '';
$since = $_GET['since'] ?? '1970-01-01 00:00:00';

if (empty($action) || empty($gameId)) {
    sendError('action und game_id erforderlich');
}

try {
    $db = Database::getInstance()->getConnection();
    
    // Spiel automatisch erstellen falls es nicht existiert
    ensureGameExists($db, $gameId);
    
    switch ($action) {
        case 'poll':
            // Spieler-Status aktualisieren falls Player-ID gegeben
            if (!empty($playerId)) {
                if (!is_numeric($playerId)) {
                    // Spieler anhand Name finden oder erstellen
                    $stmt = $db->prepare("SELECT id FROM players WHERE game_id = ? AND player_name = ? LIMIT 1");
                    $stmt->execute([$gameId, $playerId]);
                    $player = $stmt->fetch();
                    
                    if (!$player) {
                        // Neuen Spieler erstellen
                        $colors = ['red', 'blue', 'green', 'yellow'];
                        $stmt = $db->prepare("SELECT COUNT(*) as count FROM players WHERE game_id = ?");
                        $stmt->execute([$gameId]);
                        $playerCount = $stmt->fetch()['count'];
                        
                        $color = $colors[$playerCount % 4];
                        $position = $playerCount + 1;
                        
                        $stmt = $db->prepare("
                            INSERT INTO players (game_id, player_name, player_color, position, last_seen)
                            VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP)
                        ");
                        $stmt->execute([$gameId, $playerId, $color, $position]);
                        $playerId = $db->lastInsertId();
                    } else {
                        $playerId = $player['id'];
                    }
                }
                
                if ($playerId) {
                    $stmt = $db->prepare("
                        UPDATE players 
                        SET last_seen = CURRENT_TIMESTAMP, player_status = 'online'
                        WHERE id = ?
                    ");
                    $stmt->execute([$playerId]);
                }
            }
            
            // Neue Moves seit letztem Poll
            $stmt = $db->prepare("
                SELECT gm.*, p.player_name, p.player_color
                FROM game_moves gm
                JOIN players p ON gm.player_id = p.id
                WHERE gm.game_id = ? AND gm.created_at > ?
                ORDER BY gm.created_at ASC
            ");
            $stmt->execute([$gameId, $since]);
            $moves = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Neue Chat-Nachrichten
            $stmt = $db->prepare("
                SELECT gc.*, p.player_name, p.player_color
                FROM game_chat gc
                JOIN players p ON gc.player_id = p.id
                WHERE gc.game_id = ? AND gc.created_at > ?
                ORDER BY gc.created_at ASC
            ");
            $stmt->execute([$gameId, $since]);
            $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Spiel-Info
            $stmt = $db->prepare("SELECT * FROM games WHERE id = ?");
            $stmt->execute([$gameId]);
            $gameInfo = $stmt->fetch();
            
            // Online-Spieler (letzte 30 Sekunden aktiv)
            $stmt = $db->prepare("
                SELECT *, 
                       (strftime('%s', 'now') - strftime('%s', last_seen)) as seconds_ago,
                       CASE 
                           WHEN (strftime('%s', 'now') - strftime('%s', last_seen)) < 30 THEN 'online'
                           WHEN (strftime('%s', 'now') - strftime('%s', last_seen)) < 300 THEN 'away'
                           ELSE 'offline'
                       END as status
                FROM players 
                WHERE game_id = ?
                ORDER BY last_seen DESC
            ");
            $stmt->execute([$gameId]);
            $players = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            sendResponse([
                'success' => true,
                'timestamp' => date('Y-m-d H:i:s'),
                'moves' => $moves,
                'messages' => $messages,
                'game_info' => $gameInfo,
                'players' => $players,
                'has_updates' => count($moves) > 0 || count($messages) > 0,
                'debug_player_id' => $playerId
            ]);
            break;
            
        case 'ping':
            sendResponse([
                'success' => true,
                'timestamp' => date('Y-m-d H:i:s'),
                'server_time' => time(),
                'latency_test' => microtime(true)
            ]);
            break;
            
        default:
            sendError('Unbekannte Aktion: ' . $action);
    }
    
} catch (Exception $e) {
    error_log("Realtime API Fehler: " . $e->getMessage());
    sendError('Server-Fehler: ' . $e->getMessage(), 500);
}
?>
