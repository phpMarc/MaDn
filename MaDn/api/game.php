<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Preflight OPTIONS Request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../classes/Game.php';
require_once '../classes/Database.php';

// Session starten für Spieler-Tracking
session_start();

// Hilfsfunktionen
function sendResponse($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data);
    exit();
}

function sendError($message, $status = 400) {
    sendResponse(['error' => $message, 'success' => false], $status);
}

function getSessionId() {
    return session_id();
}

// Request-Daten parsen
$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $_GET['action'] ?? $input['action'] ?? '';

try {
    switch ($action) {
        
        // ==================== SPIEL ERSTELLEN ====================
        case 'create_classic':
            $maxPlayers = $input['max_players'] ?? 4;
            
            $game = new Game();
            $result = $game->createGame($maxPlayers);
            
            sendResponse([
                'success' => true,
                'message' => 'Classic Spiel erstellt',
                'data' => $result
            ]);
            break;
            
        case 'create_teamplay':
            $maxTeams = $input['max_teams'] ?? 4;
            
            $game = new Game();
            $result = $game->createTeamGame($maxTeams);
            
            sendResponse([
                'success' => true,
                'message' => 'Teamplay Spiel erstellt',
                'data' => $result
            ]);
            break;
            
        // ==================== SPIEL BEITRETEN ====================
        case 'join_classic':
            $gameCode = $input['game_code'] ?? '';
            $playerName = $input['player_name'] ?? '';
            
            if (empty($gameCode) || empty($playerName)) {
                sendError('Spielcode und Spielername erforderlich');
            }
            
            $game = Game::findByCode($gameCode);
            if (!$game) {
                sendError('Spiel nicht gefunden');
            }
            
            $result = $game->addPlayer($playerName, getSessionId());
            
            sendResponse([
                'success' => true,
                'message' => 'Erfolgreich beigetreten',
                'data' => $result
            ]);
            break;
            
        case 'join_team':
            $gameCode = $input['game_code'] ?? '';
            $playerName = $input['player_name'] ?? '';
            $teamColor = $input['team_color'] ?? '';
            
            if (empty($gameCode) || empty($playerName) || empty($teamColor)) {
                sendError('Spielcode, Spielername und Team-Farbe erforderlich');
            }
            
            $game = Game::findByCode($gameCode);
            if (!$game) {
                sendError('Spiel nicht gefunden');
            }
            
            $result = $game->addPlayerToTeam($playerName, getSessionId(), $teamColor);
            
            sendResponse([
                'success' => true,
                'message' => 'Erfolgreich Team beigetreten',
                'data' => $result
            ]);
            break;
            
        // ==================== SPIEL STARTEN ====================
        case 'start_game':
            $gameId = $input['game_id'] ?? '';
            
            if (empty($gameId)) {
                sendError('Spiel-ID erforderlich');
            }
            
            $game = new Game($gameId);
            $result = $game->startGame();
            
            sendResponse([
                'success' => true,
                'message' => 'Spiel gestartet',
                'data' => $result
            ]);
            break;
            
        // ==================== SPIELZÜGE ====================
        case 'roll_dice':
            $gameId = $input['game_id'] ?? '';
            $playerId = $input['player_id'] ?? '';
            
            if (empty($gameId) || empty($playerId)) {
                sendError('Spiel-ID und Spieler-ID erforderlich');
            }
            
            $game = new Game($gameId);
            $result = $game->rollDice($playerId);
            
            sendResponse([
                'success' => true,
                'message' => 'Würfel geworfen',
                'data' => $result
            ]);
            break;
            
        case 'move_figure':
            $gameId = $input['game_id'] ?? '';
            $playerId = $input['player_id'] ?? '';
            $figureId = $input['figure_id'] ?? '';
            $fromPosition = $input['from_position'] ?? '';
            $toPosition = $input['to_position'] ?? '';
            
            if (empty($gameId) || empty($playerId) || empty($figureId) || 
                empty($fromPosition) || empty($toPosition)) {
                sendError('Alle Bewegungsparameter erforderlich');
            }
            
            $game = new Game($gameId);
            $result = $game->moveFigure($playerId, $figureId, $fromPosition, $toPosition);
            
            sendResponse([
                'success' => true,
                'message' => 'Figur bewegt',
                'data' => $result
            ]);
            break;
            
        // ==================== SPIEL-INFO ====================
        case 'get_game_info':
            $gameId = $_GET['game_id'] ?? $input['game_id'] ?? '';
            
            if (empty($gameId)) {
                sendError('Spiel-ID erforderlich');
            }
            
            $game = new Game($gameId);
            $result = $game->getGameInfo();
            
            sendResponse([
                'success' => true,
                'data' => $result
            ]);
            break;
            
        case 'find_game':
            $gameCode = $_GET['game_code'] ?? $input['game_code'] ?? '';
            
            if (empty($gameCode)) {
                sendError('Spielcode erforderlich');
            }
            
            $game = Game::findByCode($gameCode);
            if (!$game) {
                sendError('Spiel nicht gefunden', 404);
            }
            
            $result = $game->getGameInfo();
            
            sendResponse([
                'success' => true,
                'data' => $result
            ]);
            break;
            
        // ==================== CHAT ====================
        case 'send_chat':
            $gameId = $input['game_id'] ?? '';
            $playerId = $input['player_id'] ?? '';
            $message = $input['message'] ?? '';
            $isTeamMessage = $input['is_team_message'] ?? false;
            
            if (empty($gameId) || empty($playerId) || empty($message)) {
                sendError('Spiel-ID, Spieler-ID und Nachricht erforderlich');
            }
            
            $game = new Game($gameId);
            $messageId = $game->sendChatMessage($playerId, $message, $isTeamMessage);
            
            sendResponse([
                'success' => true,
                'message' => 'Nachricht gesendet',
                'data' => ['message_id' => $messageId]
            ]);
            break;
            
        case 'get_chat':
            $gameId = $_GET['game_id'] ?? '';
            $playerId = $_GET['player_id'] ?? '';
            $limit = $_GET['limit'] ?? 50;
            
            if (empty($gameId)) {
                sendError('Spiel-ID erforderlich');
            }
            
            $game = new Game($gameId);
            $messages = $game->getChatMessages($playerId, $limit);
            
            sendResponse([
                'success' => true,
                'data' => $messages
            ]);
            break;
            
        // ==================== ENTWICKLER-TOOLS ====================
        case 'debug_game':
            if (!isset($_GET['debug']) || $_GET['debug'] !== 'true') {
                sendError('Debug-Modus nicht aktiviert', 403);
            }
            
            $gameId = $_GET['game_id'] ?? '';
            if (empty($gameId)) {
                sendError('Spiel-ID erforderlich');
            }
            
            $game = new Game($gameId);
            $gameInfo = $game->getGameInfo();
            
            // Zusätzliche Debug-Infos
            $db = Database::getInstance()->getConnection();
            
            // Alle Züge
            $stmt = $db->prepare("
                SELECT gm.*, p.player_name 
                FROM game_moves gm 
                JOIN players p ON gm.player_id = p.id 
                WHERE gm.game_id = ? 
                ORDER BY gm.created_at DESC 
                LIMIT 20
            ");
            $stmt->execute([$gameId]);
            $recentMoves = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Spieler-Status
            $stmt = $db->prepare("
                SELECT p.*, t.virtual_player_color, t.team_name 
                FROM players p 
                LEFT JOIN teams t ON p.team_id = t.id 
                WHERE p.game_id = ?
            ");
            $stmt->execute([$gameId]);
            $playerDetails = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            sendResponse([
                'success' => true,
                'data' => [
                    'game_info' => $gameInfo,
                    'recent_moves' => $recentMoves,
                    'player_details' => $playerDetails,
                    'session_id' => getSessionId()
                ]
            ]);
            break;
            
        // ==================== DEMO-DATEN ====================
        case 'create_demo_classic':
            $game = new Game();
            $gameData = $game->createGame(4);
            
            // Demo-Spieler hinzufügen
            $players = [];
            $playerNames = ['Alice', 'Bob', 'Charlie', 'Diana'];
            
            foreach ($playerNames as $name) {
                $playerData = $game->addPlayer($name, 'demo_' . $name);
                $players[] = $playerData;
            }
            
            $game->startGame();
            
            sendResponse([
                'success' => true,
                'message' => 'Demo Classic Spiel erstellt',
                'data' => [
                    'game' => $gameData,
                    'players' => $players,
                    'game_info' => $game->getGameInfo()
                ]
            ]);
            break;
            
        case 'create_demo_teamplay':
            $game = new Game();
            $gameData = $game->createTeamGame(4);
            
            // Demo-Teams erstellen
            $teams = [];
            $teamSetup = [
                'red' => ['Alice', 'Bob'],
                'blue' => ['Charlie'],
                'green' => ['Diana', 'Eve'],
                'yellow' => ['Frank', 'Grace', 'Henry']
            ];
            
            foreach ($teamSetup as $color => $playerNames) {
                foreach ($playerNames as $name) {
                    $playerData = $game->addPlayerToTeam($name, 'demo_' . $name, $color);
                    $teams[$color][] = $playerData;
                }
            }
            
            $game->startGame();
            
            sendResponse([
                'success' => true,
                'message' => 'Demo Teamplay Spiel erstellt',
                'data' => [
                    'game' => $gameData,
                    'teams' => $teams,
                    'game_info' => $game->getGameInfo()
                ]
            ]);
            break;
            
        default:
            sendError('Unbekannte Aktion: ' . $action, 400);
    }
    
} catch (Exception $e) {
    sendError($e->getMessage(), 500);
}
