<?php
// api/create_game.php - Neues Spiel erstellen
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

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$gameId = $_POST['game_id'] ?? $_GET['game_id'] ?? '';

try {
    $db = Database::getInstance()->getConnection();
    
    switch ($action) {
        case 'create_or_join':
            if (empty($gameId)) {
                sendError('game_id erforderlich');
            }
            
            // Prüfen ob Spiel existiert
            $stmt = $db->prepare("SELECT * FROM games WHERE id = ?");
            $stmt->execute([$gameId]);
            $game = $stmt->fetch();
            
            if (!$game) {
                // Spiel erstellen
                $stmt = $db->prepare("
                    INSERT INTO games (id, game_name, game_mode, game_state, created_at, updated_at)
                    VALUES (?, ?, 'classic', 'waiting', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
                ");
                $gameName = 'Spiel ' . substr($gameId, -6);
                $stmt->execute([$gameId, $gameName]);
                
                sendResponse([
                    'success' => true,
                    'action' => 'game_created',
                    'game_id' => $gameId,
                    'game_name' => $gameName,
                    'message' => 'Neues Spiel erstellt'
                ]);
            } else {
                sendResponse([
                    'success' => true,
                    'action' => 'game_joined',
                    'game_id' => $gameId,
                    'game_name' => $game['game_name'],
                    'game_state' => $game['game_state'],
                    'message' => 'Spiel beigetreten'
                ]);
            }
            break;
            
        case 'check_game':
            if (empty($gameId)) {
                sendError('game_id erforderlich');
            }
            
            $stmt = $db->prepare("SELECT * FROM games WHERE id = ?");
            $stmt->execute([$gameId]);
            $game = $stmt->fetch();
            
            sendResponse([
                'success' => true,
                'exists' => $game !== false,
                'game' => $game
            ]);
            break;
            
        default:
            sendError('Unbekannte Aktion: ' . $action);
    }
    
} catch (Exception $e) {
    error_log("Create Game Fehler: " . $e->getMessage());
    sendError('Server-Fehler: ' . $e->getMessage(), 500);
}
?>
