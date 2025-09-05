<?php
/**
 * Ludo Realtime System - SQLite Setup Script
 * Richtet die SQLite-Datenbank automatisch ein
 */

// Error Reporting aktivieren
error_reporting(E_ALL);
ini_set('display_errors', 1);

// SQLite-Konfiguration
$config = [
    'database_file' => 'ludo_realtime.db',
    'database_path' => __DIR__ . '/ludo_realtime.db'
];

// HTML Header
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🛠️ Ludo SQLite Setup</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            margin: 0;
            padding: 20px;
            min-height: 100vh;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(45deg, #ff6b6b, #4ecdc4);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .content {
            padding: 30px;
        }
        .step {
            margin-bottom: 20px;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #4ecdc4;
        }
        .step.success {
            background: #d4edda;
            border-left-color: #28a745;
        }
        .step.error {
            background: #f8d7da;
            border-left-color: #dc3545;
        }
        .step.warning {
            background: #fff3cd;
            border-left-color: #ffc107;
        }
        .code {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 5px;
            font-family: monospace;
            margin: 10px 0;
            overflow-x: auto;
        }
        .btn {
            background: linear-gradient(45deg, #4ecdc4, #44a08d);
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            text-decoration: none;
            display: inline-block;
            margin: 10px 5px;
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🛠️ Ludo SQLite Setup</h1>
            <p>Automatische Einrichtung der SQLite-Datenbank</p>
        </div>
        
        <div class="content">
<?php

if (isset($_POST['setup'])) {
    runSetup($config);
} else {
    showSetupForm($config);
}

function showSetupForm($config) {
    ?>
    <div class="step">
        <h3>📋 SQLite Setup-Konfiguration</h3>
        <p>SQLite-Datenbank wird automatisch erstellt:</p>
        
        <form method="POST">
            <table style="width: 100%; margin: 20px 0;">
                <tr>
                    <td><strong>Datenbankdatei:</strong></td>
                    <td><input type="text" name="database_file" value="<?= htmlspecialchars($config['database_file']) ?>" style="width: 300px; padding: 5px;"></td>
                </tr>
                <tr>
                    <td><strong>Vollständiger Pfad:</strong></td>
                    <td><code><?= htmlspecialchars($config['database_path']) ?></code></td>
                </tr>
                <tr>
                    <td><strong>SQLite Version:</strong></td>
                    <td><code><?= class_exists('PDO') && in_array('sqlite', PDO::getAvailableDrivers()) ? 'Verfügbar ✅' : 'NICHT verfügbar ❌' ?></code></td>
                </tr>
            </table>
            
            <button type="submit" name="setup" class="btn">🚀 SQLite Setup starten</button>
        </form>
    </div>
    
    <div class="step warning">
        <h3>⚠️ Wichtige Hinweise</h3>
        <ul>
            <li>SQLite-Datenbank wird automatisch erstellt</li>
            <li>Bestehende Datenbank wird überschrieben!</li>
            <li>Keine MySQL/MariaDB Installation erforderlich</li>
            <li>Datei-Schreibrechte im Verzeichnis erforderlich</li>
        </ul>
    </div>
    <?php
}

function runSetup($defaultConfig) {
    // Konfiguration aus Form übernehmen
    $config = [
        'database_file' => $_POST['database_file'] ?? $defaultConfig['database_file'],
        'database_path' => __DIR__ . '/' . ($_POST['database_file'] ?? $defaultConfig['database_file'])
    ];
    
    $steps = [];
    
    try {
        // Schritt 1: SQLite-Unterstützung prüfen
        $steps[] = testStep("SQLite-Unterstützung prüfen", function() {
            if (!class_exists('PDO')) {
                throw new Exception('PDO ist nicht verfügbar');
            }
            if (!in_array('sqlite', PDO::getAvailableDrivers())) {
                throw new Exception('SQLite PDO-Treiber ist nicht verfügbar');
            }
            return true;
        });
        
        // Schritt 2: Alte Datenbank löschen (falls vorhanden)
        $steps[] = testStep("Alte Datenbank bereinigen", function() use ($config) {
            if (file_exists($config['database_path'])) {
                unlink($config['database_path']);
            }
            return true;
        });
        
        // Schritt 3: SQLite-Verbindung erstellen
        $steps[] = testStep("SQLite-Datenbank erstellen", function() use ($config) {
            $pdo = new PDO(
                "sqlite:" . $config['database_path'],
                null,
                null,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                ]
            );
            
            // SQLite-spezifische Einstellungen
            $pdo->exec("PRAGMA foreign_keys = ON");
            $pdo->exec("PRAGMA journal_mode = WAL");
            $pdo->exec("PRAGMA synchronous = NORMAL");
            
            return $pdo;
        });
        
        $pdo = $steps[2]['result'];
        
        // Schritt 4: Tabellen erstellen
        $tables = getSQLiteTableDefinitions();
        
        foreach ($tables as $tableName => $sql) {
            $steps[] = testStep("Tabelle '$tableName' erstellen", function() use ($pdo, $sql) {
                $pdo->exec($sql);
                return true;
            });
        }
        
        // Schritt 5: Database-Klasse erstellen
        $steps[] = testStep("Database-Klasse erstellen", function() use ($config) {
            return createSQLiteDatabaseClass($config);
        });
        
        // Schritt 6: Test-Daten einfügen
        $steps[] = testStep("Test-Daten einfügen", function() use ($pdo) {
            return insertTestData($pdo);
        });
        
        // Schritt 7: Verzeichnisse erstellen
        $steps[] = testStep("Verzeichnisse erstellen", function() {
            $dirs = ['classes', 'api', 'js'];
            foreach ($dirs as $dir) {
                if (!is_dir($dir)) {
                    mkdir($dir, 0755, true);
                }
            }
            return true;
        });
        
        // Schritt 8: Berechtigungen setzen
        $steps[] = testStep("Datei-Berechtigungen setzen", function() use ($config) {
            chmod($config['database_path'], 0666);
            return true;
        });
        
        // Ergebnisse anzeigen
        displayResults($steps, $config);
        
    } catch (Exception $e) {
        echo "<div class='step error'>";
        echo "<h3>❌ Setup fehlgeschlagen</h3>";
        echo "<p><strong>Fehler:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
        echo "</div>";
    }
}

function testStep($description, $callback) {
    try {
        $start = microtime(true);
        $result = $callback();
        $duration = round((microtime(true) - $start) * 1000, 2);
        
        return [
            'description' => $description,
            'success' => true,
            'result' => $result,
            'duration' => $duration
        ];
    } catch (Exception $e) {
        return [
            'description' => $description,
            'success' => false,
            'error' => $e->getMessage(),
            'duration' => 0
        ];
    }
}

function getSQLiteTableDefinitions() {
    return [
        'games' => "
            CREATE TABLE games (
                id TEXT PRIMARY KEY,
                game_name TEXT NOT NULL,
                game_mode TEXT DEFAULT 'classic' CHECK(game_mode IN ('classic','teamplay')),
                max_players INTEGER DEFAULT 4,
                game_state TEXT DEFAULT 'waiting' CHECK(game_state IN ('waiting','playing','finished')),
                current_player INTEGER DEFAULT NULL,
                game_data TEXT DEFAULT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ",
        
        'teams' => "
            CREATE TABLE teams (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                game_id TEXT NOT NULL,
                team_name TEXT NOT NULL,
                virtual_player_color TEXT NOT NULL CHECK(virtual_player_color IN ('red','blue','green','yellow')),
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (game_id) REFERENCES games(id) ON DELETE CASCADE
            )
        ",
        
        'players' => "
            CREATE TABLE players (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                game_id TEXT NOT NULL,
                team_id INTEGER DEFAULT NULL,
                player_name TEXT NOT NULL,
                player_color TEXT NOT NULL CHECK(player_color IN ('red','blue','green','yellow')),
                position INTEGER NOT NULL,
                is_ready INTEGER DEFAULT 0,
                player_status TEXT DEFAULT 'online',
                session_id TEXT DEFAULT NULL,
                last_seen DATETIME DEFAULT CURRENT_TIMESTAMP,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (game_id) REFERENCES games(id) ON DELETE CASCADE,
                FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE SET NULL
            )
        ",
        
        'game_moves' => "
            CREATE TABLE game_moves (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                game_id TEXT NOT NULL,
                player_id INTEGER NOT NULL,
                move_type TEXT NOT NULL,
                move_data TEXT DEFAULT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (game_id) REFERENCES games(id) ON DELETE CASCADE,
                FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE
            )
        ",
        
        'game_chat' => "
            CREATE TABLE game_chat (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                game_id TEXT NOT NULL,
                player_id INTEGER NOT NULL,
                message TEXT NOT NULL,
                is_team_message INTEGER DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (game_id) REFERENCES games(id) ON DELETE CASCADE,
                FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE
            )
        "
    ];
}

function createSQLiteDatabaseClass($config) {
    $relativePath = basename($config['database_path']);
    
    $classContent = "<?php
/**
 * SQLite Database Connection Class - Auto-generated by Setup
 */
class Database {
    private static \$instance = null;
    private \$connection;
    
    private \$database_path = __DIR__ . '/../{$relativePath}';
    
    private function __construct() {
        try {
            \$this->connection = new PDO(
                'sqlite:' . \$this->database_path,
                null,
                null,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                ]
            );
            
            // SQLite-spezifische Einstellungen
            \$this->connection->exec('PRAGMA foreign_keys = ON');
            \$this->connection->exec('PRAGMA journal_mode = WAL');
            \$this->connection->exec('PRAGMA synchronous = NORMAL');
            
        } catch (PDOException \$e) {
            throw new Exception('SQLite connection failed: ' . \$e->getMessage());
        }
    }
    
    public static function getInstance() {
        if (self::\$instance === null) {
            self::\$instance = new self();
        }
        return self::\$instance;
    }
    
    public function getConnection() {
        return \$this->connection;
    }
    
    public function getDatabasePath() {
        return \$this->database_path;
    }
}
?>";
    
    if (!is_dir('classes')) {
        mkdir('classes', 0755, true);
    }
    
    return file_put_contents('classes/Database.php', $classContent) !== false;
}

function insertTestData($pdo) {
    // Test-Spiel erstellen
    $pdo->exec("
        INSERT OR REPLACE INTO games (id, game_name, game_mode, game_state) 
        VALUES ('test-game-123', 'Test Spiel', 'classic', 'waiting')
    ");
    
    // Test-Spieler erstellen
    $pdo->exec("
        INSERT OR REPLACE INTO players (id, game_id, player_name, player_color, position) 
        VALUES 
        (1, 'test-game-123', 'Test Spieler 1', 'red', 1),
        (2, 'test-game-123', 'Test Spieler 2', 'blue', 2)
    ");
    
    // Test-Chat-Nachricht
    $pdo->exec("
        INSERT INTO game_chat (game_id, player_id, message) 
        VALUES ('test-game-123', 1, 'Willkommen im Test-Spiel!')
    ");
    
    return true;
}

function displayResults($steps, $config) {
    $successCount = count(array_filter($steps, function($step) { return $step['success']; }));
    $totalCount = count($steps);
    
    echo "<div class='step " . ($successCount === $totalCount ? 'success' : 'error') . "'>";
    echo "<h3>📊 SQLite Setup-Ergebnis</h3>";
    echo "<p><strong>$successCount von $totalCount Schritten erfolgreich</strong></p>";
    echo "</div>";
    
    foreach ($steps as $step) {
        echo "<div class='step " . ($step['success'] ? 'success' : 'error') . "'>";
        echo "<h4>" . ($step['success'] ? '✅' : '❌') . " " . htmlspecialchars($step['description']) . "</h4>";
        
        if ($step['success']) {
            echo "<p>Erfolgreich in {$step['duration']}ms</p>";
        } else {
            echo "<p><strong>Fehler:</strong> " . htmlspecialchars($step['error']) . "</p>";
        }
        echo "</div>";
    }
    
    if ($successCount === $totalCount) {
        echo "<div class='step success'>";
        echo "<h3>🎉 SQLite Setup erfolgreich abgeschlossen!</h3>";
        echo "<p>Das Ludo Realtime System mit SQLite ist jetzt einsatzbereit.</p>";
        echo "<div class='code'>";
        echo "SQLite-Datei: " . basename($config['database_path']) . "<br>";
        echo "Dateigröße: " . (file_exists($config['database_path']) ? round(filesize($config['database_path']) / 1024, 2) . ' KB' : '0 KB') . "<br>";
        echo "Tabellen: " . count(getSQLiteTableDefinitions()) . " erstellt<br>";
        echo "Test-Spiel: test-game-123<br>";
        echo "Foreign Keys: Aktiviert ✅";
        echo "</div>";
        echo "<a href='test_realtime.html' class='btn'>🎮 Test-Interface öffnen</a>";
        echo "<a href='?' class='btn'>🔄 Setup erneut ausführen</a>";
        echo "</div>";
    }
}

?>
        </div>
    </div>
</body>
</html>