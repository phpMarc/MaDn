<?php
/**
 * Debug-Script für Ludo Realtime System
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>🔍 Ludo Debug</title>
    <style>
        body { font-family: monospace; background: #1a1a1a; color: #00ff00; padding: 20px; }
        .test { margin: 10px 0; padding: 10px; border: 1px solid #333; }
        .success { border-color: #00ff00; background: #001100; }
        .error { border-color: #ff0000; background: #110000; color: #ff6666; }
        .warning { border-color: #ffaa00; background: #111100; color: #ffcc66; }
        pre { background: #000; padding: 10px; overflow-x: auto; }
    </style>
</head>
<body>
    <h1>🔍 Ludo Realtime System Debug</h1>
    
    <?php
    
    // Test 1: Verzeichnisstruktur
    echo "<div class='test'>";
    echo "<h3>📁 Verzeichnisstruktur</h3>";
    $dirs = ['classes', 'api', 'js'];
    foreach ($dirs as $dir) {
        $exists = is_dir($dir);
        echo "<div class='" . ($exists ? 'success' : 'error') . "'>";
        echo ($exists ? '✅' : '❌') . " $dir/";
        if ($exists) {
            $files = scandir($dir);
            echo " (" . (count($files) - 2) . " Dateien)";
        }
        echo "</div>";
    }
    echo "</div>";
    
    // Test 2: Wichtige Dateien
    echo "<div class='test'>";
    echo "<h3>📄 Wichtige Dateien</h3>";
    $files = [
        'classes/Database.php',
        'api/realtime.php', 
        'api/game_actions.php',
        'api/chat.php',
        'ludo_realtime.db'
    ];
    foreach ($files as $file) {
        $exists = file_exists($file);
        echo "<div class='" . ($exists ? 'success' : 'error') . "'>";
        echo ($exists ? '✅' : '❌') . " $file";
        if ($exists) {
            echo " (" . round(filesize($file) / 1024, 2) . " KB)";
        }
        echo "</div>";
    }
    echo "</div>";
    
    // Test 3: Database-Klasse
    echo "<div class='test'>";
    echo "<h3>🗄️ Database-Verbindung</h3>";
    try {
        if (file_exists('classes/Database.php')) {
            require_once 'classes/Database.php';
            $db = Database::getInstance();
            $conn = $db->getConnection();
            
            echo "<div class='success'>✅ Database-Klasse geladen</div>";
            echo "<div class='success'>✅ SQLite-Verbindung erfolgreich</div>";
            
            // Tabellen prüfen
            $stmt = $conn->query("SELECT name FROM sqlite_master WHERE type='table'");
            $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
            echo "<div class='success'>✅ Tabellen gefunden: " . implode(', ', $tables) . "</div>";
            
        } else {
            echo "<div class='error'>❌ Database.php nicht gefunden</div>";
        }
    } catch (Exception $e) {
        echo "<div class='error'>❌ Database-Fehler: " . $e->getMessage() . "</div>";
    }
    echo "</div>";
    
    // Test 4: API-Endpunkte testen
    echo "<div class='test'>";
    echo "<h3>🌐 API-Endpunkte testen</h3>";
    
    $apiTests = [
        'realtime.php?action=ping&game_id=test-game-123',
        'game_actions.php',
        'chat.php'
    ];
    
    foreach ($apiTests as $endpoint) {
        $url = 'api/' . $endpoint;
        echo "<h4>Testing: $url</h4>";
        
        if (file_exists('api/' . explode('?', $endpoint)[0])) {
            echo "<div class='success'>✅ Datei existiert</div>";
            
            // Syntax-Check
            $output = [];
            $return_var = 0;
            exec("php -l api/" . explode('?', $endpoint)[0] . " 2>&1", $output, $return_var);
            
            if ($return_var === 0) {
                echo "<div class='success'>✅ PHP-Syntax OK</div>";
            } else {
                echo "<div class='error'>❌ PHP-Syntax-Fehler:</div>";
                echo "<pre>" . implode("\n", $output) . "</pre>";
            }
            
        } else {
            echo "<div class='error'>❌ Datei nicht gefunden</div>";
        }
    }
    echo "</div>";
    
    // Test 5: Direkte API-Aufrufe
    echo "<div class='test'>";
    echo "<h3>🔗 Direkte API-Tests</h3>";
    
    // Ping-Test
    echo "<h4>Ping-Test:</h4>";
    try {
        $_GET = ['action' => 'ping', 'game_id' => 'test-game-123'];
        ob_start();
        include 'api/realtime.php';
        $response = ob_get_clean();
        
        $json = json_decode($response, true);
        if ($json && isset($json['success']) && $json['success']) {
            echo "<div class='success'>✅ Ping erfolgreich</div>";
            echo "<pre>" . json_encode($json, JSON_PRETTY_PRINT) . "</pre>";
        } else {
            echo "<div class='error'>❌ Ping fehlgeschlagen</div>";
            echo "<pre>$response</pre>";
        }
    } catch (Exception $e) {
        echo "<div class='error'>❌ Ping-Fehler: " . $e->getMessage() . "</div>";
    }
    
    // Game Action Test
    echo "<h4>Game Action Test:</h4>";
    try {
        $_POST = [
            'action' => 'roll_dice',
            'game_id' => 'test-game-123',
            'player_id' => 'Debug-Spieler'
        ];
        $_GET = [];
        
        ob_start();
        include 'api/game_actions.php';
        $response = ob_get_clean();
        
        $json = json_decode($response, true);
        if ($json && isset($json['success']) && $json['success']) {
            echo "<div class='success'>✅ Game Action erfolgreich</div>";
            echo "<pre>" . json_encode($json, JSON_PRETTY_PRINT) . "</pre>";
        } else {
            echo "<div class='error'>❌ Game Action fehlgeschlagen</div>";
            echo "<pre>$response</pre>";
        }
    } catch (Exception $e) {
        echo "<div class='error'>❌ Game Action Fehler: " . $e->getMessage() . "</div>";
    }
    
    echo "</div>";
    
    // Test 6: JavaScript-Konsole simulieren
    echo "<div class='test'>";
    echo "<h3>🔧 JavaScript-Test</h3>";
    echo "<button onclick='testAPI()'>🧪 API direkt testen</button>";
    echo "<div id='jsResults'></div>";
    echo "</div>";
    
    ?>
    
    <script>
    async function testAPI() {
        const results = document.getElementById('jsResults');
        results.innerHTML = '<h4>JavaScript API-Tests:</h4>';
        
        // Test 1: Ping
        try {
            const response = await fetch('api/realtime.php?action=ping&game_id=test-game-123');
            const data = await response.json();
            
            results.innerHTML += `<div style="color: #00ff00;">✅ Ping: ${JSON.stringify(data)}</div>`;
        } catch (error) {
            results.innerHTML += `<div style="color: #ff0000;">❌ Ping-Fehler: ${error.message}</div>`;
        }
        
        // Test 2: Game Action
        try {
            const formData = new FormData();
            formData.append('action', 'roll_dice');
            formData.append('game_id', 'test-game-123');
            formData.append('player_id', 'JS-Test-Spieler');
            
            const response = await fetch('api/game_actions.php', {
                method: 'POST',
                body: formData
            });
            const data = await response.json();
            
            results.innerHTML += `<div style="color: #00ff00;">✅ Game Action: ${JSON.stringify(data)}</div>`;
        } catch (error) {
            results.innerHTML += `<div style="color: #ff0000;">❌ Game Action Fehler: ${error.message}</div>`;
        }
        
        // Test 3: Realtime Poll
        try {
            const response = await fetch('api/realtime.php?action=poll&game_id=test-game-123&player_id=JS-Test-Spieler');
            const data = await response.json();
            
            results.innerHTML += `<div style="color: #00ff00;">✅ Realtime Poll: ${data.players ? data.players.length : 0} Spieler gefunden</div>`;
        } catch (error) {
            results.innerHTML += `<div style="color: #ff0000;">❌ Realtime Poll Fehler: ${error.message}</div>`;
        }
    }
    </script>
    
    <div class="test">
        <h3>🎯 Nächste Schritte</h3>
        <div class="warning">
            1. Führe das Debug-Script aus<br>
            2. Klicke "API direkt testen"<br>
            3. Prüfe die Browser-Konsole (F12)<br>
            4. Schau in die PHP-Error-Logs<br>
        </div>
    </div>
    
</body>
</html>
