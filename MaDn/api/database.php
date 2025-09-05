<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Einfache Datei-basierte Datenbank für Demo
class GameDatabase {
    private $dataDir;
    
    public function __construct() {
        $this->dataDir = __DIR__ . '/../data/games/';
        if (!is_dir($this->dataDir)) {
            mkdir($this->dataDir, 0777, true);
        }
    }
    
    public function getGameFile($gameId) {
        return $this->dataDir . preg_replace('/[^a-zA-Z0-9_-]/', '', $gameId) . '.json';
    }
    
    public function gameExists($gameId) {
        return file_exists($this->getGameFile($gameId));
    }
    
    public function loadGame($gameId) {
        $file = $this->getGameFile($gameId);
        if (!file_exists($file)) {
            return null;
        }
        
        $data = file_get_contents($file);
        return json_decode($data, true);
    }
    
    public function saveGame($gameId, $gameData) {
        $file = $this->getGameFile($gameId);
        $gameData['last_updated'] = date('Y-m-d H:i:s');
        return file_put_contents($file, json_encode($gameData, JSON_PRETTY_PRINT)) !== false;
    }
    
    public function createGame($gameId) {
        $gameData = [
            'game_id' => $gameId,
            'created_at' => date('Y-m-d H:i:s'),
            'last_updated' => date('Y-m-d H:i:s'),
            'game_state' => 'waiting', // waiting, running, finished
            'current_player' => null,
            'winner' => null,
            'players' => [],
            'board' => $this->initializeBoard(),
            'events' => [],
            'messages' => [],
            'turn_order' => [],
            'dice_value' => 0
        ];
        
        return $this->saveGame($gameId, $gameData);
    }
    
    private function initializeBoard() {
        // Leeres Brett - Figuren werden dynamisch hinzugefügt
        return [
            'figures' => []
        ];
    }
    
    public function addPlayer($gameId, $playerName) {
        $game = $this->loadGame($gameId);
        if (!$game) {
            return false;
        }
        
        // Prüfen ob Spieler bereits existiert
        foreach ($game['players'] as $player) {
            if ($player['player_name'] === $playerName) {
                return $game; // Spieler bereits im Spiel
            }
        }
        
        // Prüfen ob Platz frei ist
        if (count($game['players']) >= 4) {
            return false;
        }
        
        // Farbe zuweisen
        $colors = ['red', 'blue', 'green', 'yellow'];
        $usedColors = array_column($game['players'], 'player_color');
        $availableColors = array_diff($colors, $usedColors);
        $playerColor = reset($availableColors);
        
        // Spieler hinzufügen
        $newPlayer = [
            'player_name' => $playerName,
            'player_color' => $playerColor,
            'position' => count($game['players']) + 1,
            'status' => 'online',
            'joined_at' => date('Y-m-d H:i:s'),
            'last_seen' => date('Y-m-d H:i:s'),
            'figures' => [
                ['id' => 1, 'position' => 'home', 'field' => null],
                ['id' => 2, 'position' => 'home', 'field' => null],
                ['id' => 3, 'position' => 'home', 'field' => null],
                ['id' => 4, 'position' => 'home', 'field' => null]
            ]
        ];
        
        $game['players'][] = $newPlayer;
        
        // Event hinzufügen
        $this->addEvent($game, 'player_joined', $playerName, []);
        
        $this->saveGame($gameId, $game);
        return $game;
    }
    
    public function addEvent($game, $eventType, $playerName, $data) {
        $event = [
            'event_type' => $eventType,
            'player_name' => $playerName,
            'timestamp' => date('Y-m-d H:i:s'),
            'data' => json_encode($data)
        ];
        
        $game['events'][] = $event;
        
        // Nur die letzten 50 Events behalten
        if (count($game['events']) > 50) {
            $game['events'] = array_slice($game['events'], -50);
        }
        
        return $game;
    }
    
    public function addMessage($game, $playerName, $message) {
        $playerColor = 'red';
        foreach ($game['players'] as $player) {
            if ($player['player_name'] === $playerName) {
                $playerColor = $player['player_color'];
                break;
            }
        }
        
        $chatMessage = [
            'player_name' => $playerName,
            'player_color' => $playerColor,
            'message' => $message,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        $game['messages'][] = $chatMessage;
        
        // Nur die letzten 100 Nachrichten behalten
        if (count($game['messages']) > 100) {
            $game['messages'] = array_slice($game['messages'], -100);
        }
        
        return $game;
    }
}

function sendJsonResponse($success, $data = [], $error = null) {
    $response = [
        'success' => $success,
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    if ($success) {
        $response = array_merge($response, $data);
    } else {
        $response['error'] = $error;
    }
    
    echo json_encode($response);
    exit;
}

function logError($message) {
    error_log("[Mensch ärgere dich nicht] " . $message);
}
?>
