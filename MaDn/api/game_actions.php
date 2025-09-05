<?php
require_once 'database.php';

$db = new GameDatabase();

// POST-Daten verarbeiten
$action = $_POST['action'] ?? '';
$gameId = $_POST['game_id'] ?? '';
$playerName = $_POST['player_name'] ?? '';
$playerId = $_POST['player_id'] ?? $playerName;

if (empty($action) || empty($gameId)) {
    sendJsonResponse(false, [], 'Aktion und Spiel-ID sind erforderlich');
}

switch ($action) {
    case 'join_game':
        handleJoinGame($db, $gameId, $playerName);
        break;
        
    case 'start_game':
        handleStartGame($db, $gameId, $playerId);
        break;
        
    case 'roll_dice':
        handleRollDice($db, $gameId, $playerId);
        break;
        
    case 'move_figure':
        handleMoveFigure($db, $gameId, $playerId);
        break;
        
    default:
        sendJsonResponse(false, [], 'Unbekannte Aktion: ' . $action);
}

function handleJoinGame($db, $gameId, $playerName) {
    if (empty($playerName)) {
        sendJsonResponse(false, [], 'Spielername ist erforderlich');
    }
    
    try {
        // Spiel erstellen falls es nicht existiert
        if (!$db->gameExists($gameId)) {
            if (!$db->createGame($gameId)) {
                sendJsonResponse(false, [], 'Fehler beim Erstellen des Spiels');
            }
        }
        
        // Spieler hinzufügen
        $game = $db->addPlayer($gameId, $playerName);
        if (!$game) {
            sendJsonResponse(false, [], 'Spiel ist voll oder Fehler beim Beitreten');
        }
        
        sendJsonResponse(true, [
            'message' => 'Erfolgreich dem Spiel beigetreten',
            'game_id' => $gameId,
            'player_name' => $playerName,
            'players_count' => count($game['players']),
            'game_state' => $game['game_state']
        ]);
        
    } catch (Exception $e) {
        logError("Join Game Error: " . $e->getMessage());
        sendJsonResponse(false, [], 'Serverfehler beim Beitreten');
    }
}

function handleStartGame($db, $gameId, $playerId) {
    try {
        $game = $db->loadGame($gameId);
        if (!$game) {
            sendJsonResponse(false, [], 'Spiel nicht gefunden');
        }
        
        if ($game['game_state'] !== 'waiting') {
            sendJsonResponse(false, [], 'Spiel läuft bereits oder ist beendet');
        }
        
        if (count($game['players']) < 2) {
            sendJsonResponse(false, [], 'Mindestens 2 Spieler erforderlich');
        }
        
        // Spiel starten
        $game['game_state'] = 'running';
        $game['current_player'] = $game['players'][0]['player_name'];
        $game['turn_order'] = array_column($game['players'], 'player_name');
        
        // Event hinzufügen
        $game = $db->addEvent($game, 'game_started', $playerId, []);
        
        $db->saveGame($gameId, $game);
        
        sendJsonResponse(true, [
            'message' => 'Spiel gestartet',
            'current_player' => $game['current_player']
        ]);
        
    } catch (Exception $e) {
        logError("Start Game Error: " . $e->getMessage());
        sendJsonResponse(false, [], 'Fehler beim Starten des Spiels');
    }
}

function handleRollDice($db, $gameId, $playerId) {
    try {
        $game = $db->loadGame($gameId);
        if (!$game) {
            sendJsonResponse(false, [], 'Spiel nicht gefunden');
        }
        
        if ($game['game_state'] !== 'running') {
            sendJsonResponse(false, [], 'Spiel läuft nicht');
        }
        
        if ($game['current_player'] !== $playerId) {
            sendJsonResponse(false, [], 'Du bist nicht dran');
        }
        
        // Würfeln
        $diceValue = rand(1, 6);
        $game['dice_value'] = $diceValue;
        
        // Event hinzufügen
        $game = $db->addEvent($game, 'dice_rolled', $playerId, ['dice_value' => $diceValue]);
        
        $db->saveGame($gameId, $game);
        
        sendJsonResponse(true, [
            'message' => 'Würfel geworfen',
            'dice_value' => $diceValue
        ]);
        
    } catch (Exception $e) {
        logError("Roll Dice Error: " . $e->getMessage());
        sendJsonResponse(false, [], 'Fehler beim Würfeln');
    }
}

function handleMoveFigure($db, $gameId, $playerId) {
    try {
        $game = $db->loadGame($gameId);
        if (!$game) {
            sendJsonResponse(false, [], 'Spiel nicht gefunden');
        }
        
        if ($game['game_state'] !== 'running') {
            sendJsonResponse(false, [], 'Spiel läuft nicht');
        }
        
        if ($game['current_player'] !== $playerId) {
            sendJsonResponse(false, [], 'Du bist nicht dran');
        }
        
        $moveData = json_decode($_POST['data'] ?? '{}', true);
        
        // Vereinfachte Bewegung - in echtem Spiel würde hier komplexe Validierung stehen
        $figureId = $moveData['figure_id'] ?? 1;
        $toPosition = $moveData['to_position'] ?? 0;
        $diceValue = $moveData['dice_value'] ?? $game['dice_value'];
        
        // Event hinzufügen
        $game = $db->addEvent($game, 'figure_moved', $playerId, [
            'figure_id' => $figureId,
            'to_position' => $toPosition,
            'dice_value' => $diceValue
        ]);
        
        // Nächster Spieler (außer bei 6)
        if ($diceValue !== 6) {
            $currentIndex = array_search($game['current_player'], $game['turn_order']);
            $nextIndex = ($currentIndex + 1) % count($game['turn_order']);
            $game['current_player'] = $game['turn_order'][$nextIndex];
        }
        
        $game['dice_value'] = 0; // Würfel zurücksetzen
        
        $db->saveGame($gameId, $game);
        
        sendJsonResponse(true, [
            'message' => 'Figur bewegt',
            'next_player' => $game['current_player']
        ]);
        
    } catch (Exception $e) {
        logError("Move Figure Error: " . $e->getMessage());
        sendJsonResponse(false, [], 'Fehler beim Bewegen der Figur');
    }
}
?>
