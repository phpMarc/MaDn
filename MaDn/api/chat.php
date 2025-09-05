<?php
require_once 'database.php';

$db = new GameDatabase();

$action = $_POST['action'] ?? '';
$gameId = $_POST['game_id'] ?? '';
$playerId = $_POST['player_id'] ?? '';
$message = $_POST['message'] ?? '';

if ($action === 'send_message') {
    if (empty($gameId) || empty($playerId) || empty($message)) {
        sendJsonResponse(false, [], 'Alle Felder sind erforderlich');
    }
    
    try {
        $game = $db->loadGame($gameId);
        if (!$game) {
            sendJsonResponse(false, [], 'Spiel nicht gefunden');
        }
        
        // Nachricht hinzufügen
        $game = $db->addMessage($game, $playerId, $message);
        $db->saveGame($gameId, $game);
        
        sendJsonResponse(true, [
            'message' => 'Nachricht gesendet'
        ]);
        
    } catch (Exception $e) {
        logError("Chat Error: " . $e->getMessage());
        sendJsonResponse(false, [], 'Fehler beim Senden der Nachricht');
    }
} else {
    sendJsonResponse(false, [], 'Unbekannte Chat-Aktion');
}
?>
