<?php
require_once 'database.php';

$db = new GameDatabase();

$gameId = $_GET['game_id'] ?? '';
$since = $_GET['since'] ?? '';

if (empty($gameId)) {
    sendJsonResponse(false, [], 'Spiel-ID ist erforderlich');
}

try {
    $game = $db->loadGame($gameId);
    if (!$game) {
        sendJsonResponse(false, [], 'Spiel nicht gefunden');
    }
    
    // Nur neue Events seit dem letzten Poll senden
    $newEvents = [];
    $newMessages = [];
    
    if (!empty($since)) {
        $sinceTime = strtotime($since);
        
        // Neue Events filtern
        foreach ($game['events'] as $event) {
            if (strtotime($event['timestamp']) > $sinceTime) {
                $newEvents[] = $event;
            }
        }
        
        // Neue Nachrichten filtern
        foreach ($game['messages'] as $message) {
            if (strtotime($message['timestamp']) > $sinceTime) {
                $newMessages[] = $message;
            }
        }
    } else {
        // Beim ersten Poll alle Events der letzten 5 Minuten senden
        $fiveMinutesAgo = time() - 300;
        foreach ($game['events'] as $event) {
            if (strtotime($event['timestamp']) > $fiveMinutesAgo) {
                $newEvents[] = $event;
            }
        }
        
        // Letzte 10 Chat-Nachrichten
        $newMessages = array_slice($game['messages'], -10);
    }
    
    sendJsonResponse(true, [
        'game_info' => [
            'game_id' => $game['game_id'],
            'game_state' => $game['game_state'],
            'current_player' => $game['current_player'],
            'winner' => $game['winner'],
            'dice_value' => $game['dice_value']
        ],
        'players' => $game['players'],
        'events' => $newEvents,
        'messages' => $newMessages,
        'board' => $game['board']
    ]);
    
} catch (Exception $e) {
    logError("Game State Error: " . $e->getMessage());
    sendJsonResponse(false, [], 'Fehler beim Laden des Spielstatus');
}
?>
