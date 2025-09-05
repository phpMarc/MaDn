<?php
require_once 'Database.php';

class Game {
    private $db;
    private $gameId;
    private $gameCode;
    private $gameMode = 'classic'; // 'classic' oder 'teamplay'
    private $boardSize = 40;
    private $homeFields = 4;
    private $figuresPerPlayer = 4;
    
    private $playerColors = ['red', 'blue', 'green', 'yellow'];
    private $startPositions = [0, 10, 20, 30];
    
    public function __construct($gameId = null) {
        $this->db = Database::getInstance()->getConnection();
        if ($gameId) {
            $this->gameId = $gameId;
            $this->loadGame();
        }
    }
    
    /**
     * Classic Spiel erstellen
     */
    public function createGame($maxPlayers = 4) {
        return $this->createGameInternal('classic', $maxPlayers, $maxPlayers);
    }
    
    /**
     * Teamplay Spiel erstellen
     */
    public function createTeamGame($maxTeams = 4) {
        $gameData = $this->createGameInternal('teamplay', null, $maxTeams);
        
        // Teams für alle Farben erstellen
        for ($i = 0; $i < $maxTeams; $i++) {
            $this->createTeam($this->playerColors[$i], $i);
        }
        
        return $gameData;
    }
    
    /**
     * Interne Spiel-Erstellung
     */
    private function createGameInternal($mode, $maxPlayers = null, $maxTeams = null) {
        $this->gameCode = $this->generateGameCode();
        $this->gameMode = $mode;
        
        $stmt = $this->db->prepare("
            INSERT INTO games (game_code, game_mode, max_players, max_teams, status) 
            VALUES (?, ?, ?, ?, 'waiting')
        ");
        $stmt->execute([$this->gameCode, $mode, $maxPlayers, $maxTeams]);
        
        $this->gameId = $this->db->lastInsertId();
        $this->initializeGameState();
        
        return [
            'game_id' => $this->gameId,
            'game_code' => $this->gameCode,
            'game_mode' => $mode
        ];
    }
    
    /**
     * Team erstellen
     */
    private function createTeam($color, $position, $teamName = null) {
        if (!$teamName) {
            $teamName = 'Team ' . ucfirst($color);
        }
        
        $stmt = $this->db->prepare("
            INSERT INTO teams (game_id, virtual_player_color, virtual_player_position, team_name) 
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$this->gameId, $color, $position, $teamName]);
        
        return $this->db->lastInsertId();
    }
    
    /**
     * Spieler zu Classic Game hinzufügen
     */
    public function addPlayer($playerName, $sessionId, $isAI = false) {
        if ($this->gameMode !== 'classic') {
            throw new Exception('Verwende addPlayerToTeam() für Teamplay-Spiele');
        }
        
        $playerCount = $this->getPlayerCount();
        $maxPlayers = $this->getMaxPlayers();
        
        if ($playerCount >= $maxPlayers) {
            throw new Exception('Spiel ist bereits voll');
        }
        
        $position = $this->getNextPlayerPosition();
        $color = $this->playerColors[$position];
        
        $stmt = $this->db->prepare("
            INSERT INTO players (game_id, player_name, player_color, position, is_ai, session_id) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$this->gameId, $playerName, $color, $position, $isAI ? 1 : 0, $sessionId]);
        
        $playerId = $this->db->lastInsertId();
        
        if ($this->getPlayerCount() >= 2) {
            $this->updateGameStatus('ready');
        }
        
        return [
            'player_id' => $playerId,
            'color' => $color,
            'position' => $position,
            'mode' => 'classic'
        ];
    }
    
    /**
     * Spieler zu Team hinzufügen
     */
    public function addPlayerToTeam($playerName, $sessionId, $teamColor, $isAI = false) {
        if ($this->gameMode !== 'teamplay') {
            throw new Exception('Verwende addPlayer() für Classic-Spiele');
        }
        
        // Team finden
        $team = $this->getTeamByColor($teamColor);
        if (!$team) {
            throw new Exception('Team nicht gefunden');
        }
        
        // Prüfen ob Team voll ist
        $teamPlayerCount = $this->getTeamPlayerCount($team['id']);
        if ($teamPlayerCount >= $team['max_team_size']) {
            throw new Exception('Team ist bereits voll');
        }
        
        // Nächste Position im Team finden
        $teamPosition = $this->getNextTeamPosition($team['id']);
        
        $stmt = $this->db->prepare("
            INSERT INTO players (game_id, team_id, player_name, team_position, is_ai, session_id) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$this->gameId, $team['id'], $playerName, $teamPosition, $isAI ? 1 : 0, $sessionId]);
        
        $playerId = $this->db->lastInsertId();
        
        // Prüfen ob genug Teams/Spieler für Start vorhanden sind
        if ($this->getActiveTeamCount() >= 2 && $this->getTotalPlayerCount() >= 2) {
            $this->updateGameStatus('ready');
        }
        
        return [
            'player_id' => $playerId,
            'team_id' => $team['id'],
            'team_color' => $teamColor,
            'team_position' => $teamPosition,
            'mode' => 'teamplay'
        ];
    }
    
    /**
     * Spielzustand initialisieren
     */
    private function initializeGameState() {
        $initialState = [
            'board' => $this->createEmptyBoard(),
            'players' => [],
            'current_player' => 0,
            'current_virtual_player' => 0,
            'dice_value' => null,
            'game_phase' => 'waiting',
            'winner' => null,
            'round_number' => 1,
            'turn_number' => 1,
            'current_turn_info' => null
        ];
        
        $stmt = $this->db->prepare("
            INSERT INTO game_state (game_id, board_state, round_number, turn_number) 
            VALUES (?, ?, 1, 1)
        ");
        $stmt->execute([$this->gameId, json_encode($initialState)]);
    }
    
    /**
     * Leeres Spielbrett erstellen
     */
    private function createEmptyBoard() {
        $board = [
            'fields' => array_fill(0, $this->boardSize, null), // Hauptfelder
            'start_areas' => [ // Startbereiche (4 Figuren pro Spieler)
                'red' => [null, null, null, null],
                'blue' => [null, null, null, null],
                'green' => [null, null, null, null],
                'yellow' => [null, null, null, null]
            ],
            'home_areas' => [ // Zielbereiche
                'red' => [null, null, null, null],
                'blue' => [null, null, null, null],
                'green' => [null, null, null, null],
                'yellow' => [null, null, null, null]
            ]
        ];
        
        // Alle Figuren in Startbereiche setzen
        foreach ($this->playerColors as $color) {
            for ($i = 0; $i < $this->figuresPerPlayer; $i++) {
                $board['start_areas'][$color][$i] = $color . '_' . $i;
            }
        }
        
        return $board;
    }
    
    /**
     * Spiel starten
     */
    public function startGame() {
        if ($this->gameMode === 'classic') {
            return $this->startClassicGame();
        } else {
            return $this->startTeamGame();
        }
    }
    
    /**
     * Classic Game starten
     */
    private function startClassicGame() {
        $playerCount = $this->getPlayerCount();
        if ($playerCount < 2) {
            throw new Exception('Mindestens 2 Spieler erforderlich');
        }
        
        $gameState = $this->getGameState();
        $gameState['game_phase'] = 'playing';
        $gameState['current_player'] = 0;
        
        $this->updateGameState($gameState);
        $this->updateGameStatus('playing');
        
        return $this->getGameInfo();
    }
    
    /**
     * Team Game starten
     */
    private function startTeamGame() {
        $activeTeams = $this->getActiveTeamCount();
        if ($activeTeams < 2) {
            throw new Exception('Mindestens 2 aktive Teams erforderlich');
        }
        
        $gameState = $this->getGameState();
        $gameState['game_phase'] = 'playing';
        $gameState['current_virtual_player'] = 0;
        $gameState['round_number'] = 1;
        $gameState['turn_number'] = 1;
        
        // Aktuellen Spieler für ersten Zug setzen
        $currentTurnInfo = $this->calculateCurrentPlayer();
        $gameState['current_turn_info'] = $currentTurnInfo;
        
        $this->updateGameState($gameState);
        $this->updateGameStatus('playing');
        
        return $this->getGameInfo();
    }
    
    /**
     * Aktuellen Spieler berechnen (für Teamplay)
     */
    private function calculateCurrentPlayer() {
        $gameState = $this->getGameState();
        $currentVirtualPlayer = $gameState['current_virtual_player'] ?? 0;
        $roundNumber = $gameState['round_number'] ?? 1;
        
        // Team für aktuellen virtuellen Spieler finden
        $team = $this->getTeamByPosition($currentVirtualPlayer);
        if (!$team) {
            throw new Exception('Team nicht gefunden');
        }
        
        // Aktuellen realen Spieler im Team berechnen
        $teamPlayers = $this->getTeamPlayers($team['id']);
        if (empty($teamPlayers)) {
            throw new Exception('Keine Spieler im Team');
        }
        
        $playerIndex = ($roundNumber - 1) % count($teamPlayers);
        $currentPlayer = $teamPlayers[$playerIndex];
        
        return [
            'virtual_player' => $currentVirtualPlayer,
            'virtual_color' => $team['virtual_player_color'],
            'team_id' => $team['id'],
            'real_player_id' => $currentPlayer['id'],
            'real_player_name' => $currentPlayer['player_name'],
            'round_number' => $roundNumber
        ];
    }
    
    /**
     * Würfeln
     */
    public function rollDice($playerId) {
        $gameState = $this->getGameState();
        
        if (!$this->isPlayerTurn($playerId)) {
            throw new Exception('Du bist nicht dran');
        }
        
        if ($gameState['dice_value'] !== null) {
            throw new Exception('Bereits gewürfelt - bitte Zug machen');
        }
        
        $diceValue = rand(1, 6);
        $gameState['dice_value'] = $diceValue;
        
        // Move-Log erstellen
        $this->logMove($playerId, 'dice_roll', ['dice_value' => $diceValue], $diceValue);
        
        $this->updateGameState($gameState);
        
        return [
            'dice_value' => $diceValue,
            'current_turn' => $this->getCurrentTurnInfo()
        ];
    }
    
    /**
     * Figur bewegen
     */
    public function moveFigure($playerId, $figureId, $fromPosition, $toPosition) {
        $gameState = $this->getGameState();
        $virtualColor = $this->getVirtualPlayerColor($playerId);
        
        // Validierungen
        if (!$this->isPlayerTurn($playerId)) {
            throw new Exception('Du bist nicht dran');
        }
        
        if ($gameState['dice_value'] === null) {
            throw new Exception('Erst würfeln!');
        }
        
        if (!$this->isValidMove($virtualColor, $figureId, $fromPosition, $toPosition, $gameState['dice_value'])) {
            throw new Exception('Ungültiger Zug');
        }
        
        // Zug ausführen
        $gameState = $this->executeMove($gameState, $virtualColor, $figureId, $fromPosition, $toPosition);
        
        // Move-Log erstellen
        $moveData = [
            'figure_id' => $figureId,
            'from' => $fromPosition,
            'to' => $toPosition,
            'dice_value' => $gameState['dice_value']
        ];
        $this->logMove($playerId, 'move_figure', $moveData, $gameState['dice_value']);
        
        // Prüfen ob Spieler gewonnen hat
        if ($this->checkWin($virtualColor, $gameState)) {
            $gameState['winner'] = $virtualColor;
            $gameState['game_phase'] = 'finished';
            $this->updateGameStatus('finished');
        }
        
        // Nächster Spieler (außer bei 6 gewürfelt)
        if ($gameState['dice_value'] !== 6) {
            $gameState = $this->advanceToNextTurn($gameState);
        }
        
        $gameState['dice_value'] = null; // Würfel zurücksetzen
        
        $this->updateGameState($gameState);
        
        return [
            'success' => true,
            'game_state' => $gameState,
            'current_turn' => $this->getCurrentTurnInfo()
        ];
    }
    
    /**
     * Zum nächsten Zug wechseln
     */
    private function advanceToNextTurn($gameState) {
        if ($this->gameMode === 'classic') {
            $gameState['current_player'] = $this->getNextPlayer($gameState['current_player']);
        } else {
            // Teamplay: Nächster virtueller Spieler
            $activeTeams = $this->getActiveTeamCount();
            $gameState['current_virtual_player'] = ($gameState['current_virtual_player'] + 1) % $activeTeams;
            
            // Wenn wir wieder bei Spieler 0 sind, neue Runde
            if ($gameState['current_virtual_player'] === 0) {
                $gameState['round_number']++;
            }
            
            $gameState['turn_number']++;
            $gameState['current_turn_info'] = $this->calculateCurrentPlayer();
        }
        
        return $gameState;
    }
    
    /**
     * Zug validieren
     */
    private function isValidMove($playerColor, $figureId, $fromPosition, $toPosition, $diceValue) {
        $gameState = $this->getGameState();
        $board = $gameState['board'];
        
        // Figur aus Startbereich holen (nur mit 6)
        if (strpos($fromPosition, 'start_') === 0) {
            if ($diceValue !== 6) return false;
            
            $startField = $this->startPositions[array_search($playerColor, $this->playerColors)];
            return $toPosition === 'field_' . $startField;
        }
        
        // Normale Bewegung auf dem Brett
        if (strpos($fromPosition, 'field_') === 0) {
            $currentField = (int)str_replace('field_', '', $fromPosition);
            $targetField = ($currentField + $diceValue) % $this->boardSize;
            
            // Prüfen ob Zielfeld erreicht wird (Eingang zum Zielbereich)
            $homeEntrance = $this->getHomeEntrance($playerColor);
            if ($this->shouldEnterHome($currentField, $diceValue, $homeEntrance)) {
                $homeIndex = $diceValue - ($homeEntrance - $currentField) - 1;
                return $toPosition === 'home_' . $homeIndex && $homeIndex < $this->homeFields;
            }
            
            return $toPosition === 'field_' . $targetField;
        }
        
        // Bewegung im Zielbereich
        if (strpos($fromPosition, 'home_') === 0) {
            $currentHome = (int)str_replace('home_', '', $fromPosition);
            $targetHome = $currentHome + $diceValue;
            
            return $targetHome < $this->homeFields && $toPosition === 'home_' . $targetHome;
        }
        
        return false;
    }
    
    /**
     * Zug ausführen
     */
    private function executeMove($gameState, $playerColor, $figureId, $fromPosition, $toPosition) {
        $board = $gameState['board'];
        
        // Figur von alter Position entfernen
        if (strpos($fromPosition, 'start_') === 0) {
            $startIndex = (int)str_replace('start_', '', $fromPosition);
            $board['start_areas'][$playerColor][$startIndex] = null;
        } elseif (strpos($fromPosition, 'field_') === 0) {
            $fieldIndex = (int)str_replace('field_', '', $fromPosition);
            $board['fields'][$fieldIndex] = null;
        } elseif (strpos($fromPosition, 'home_') === 0) {
            $homeIndex = (int)str_replace('home_', '', $fromPosition);
            $board['home_areas'][$playerColor][$homeIndex] = null;
        }
        
        // Figur auf neue Position setzen
        if (strpos($toPosition, 'field_') === 0) {
            $fieldIndex = (int)str_replace('field_', '', $toPosition);
            
            // Prüfen ob gegnerische Figur geschlagen wird
            if ($board['fields'][$fieldIndex] !== null) {
                $this->sendFigureHome($board, $board['fields'][$fieldIndex]);
            }
            
            $board['fields'][$fieldIndex] = $figureId;
        } elseif (strpos($toPosition, 'home_') === 0) {
            $homeIndex = (int)str_replace('home_', '', $toPosition);
            $board['home_areas'][$playerColor][$homeIndex] = $figureId;
        }
        
        $gameState['board'] = $board;
        return $gameState;
    }
    
    /**
     * Geschlagene Figur zurück in Startbereich
     */
    private function sendFigureHome($board, $figureId) {
        $figureColor = explode('_', $figureId)[0];
        
        // Ersten freien Platz im Startbereich finden
        for ($i = 0; $i < $this->figuresPerPlayer; $i++) {
            if ($board['start_areas'][$figureColor][$i] === null) {
                $board['start_areas'][$figureColor][$i] = $figureId;
                break;
            }
        }
    }
    
    /**
     * Prüfen ob Spieler gewonnen hat
     */
    private function checkWin($playerColor, $gameState) {
        $homeArea = $gameState['board']['home_areas'][$playerColor];
        
        // Alle 4 Zielfelder müssen besetzt sein
        for ($i = 0; $i < $this->figuresPerPlayer; $i++) {
            if ($homeArea[$i] === null) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Hilfsfunktionen für Teamplay
     */
    private function getTeamByColor($color) {
        $stmt = $this->db->prepare("
            SELECT * FROM teams 
            WHERE game_id = ? AND virtual_player_color = ? AND is_active = 1
        ");
        $stmt->execute([$this->gameId, $color]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    private function getTeamByPosition($position) {
        $stmt = $this->db->prepare("
            SELECT * FROM teams 
            WHERE game_id = ? AND virtual_player_position = ? AND is_active = 1
        ");
        $stmt->execute([$this->gameId, $position]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    private function getTeamPlayers($teamId) {
        $stmt = $this->db->prepare("
            SELECT * FROM players 
            WHERE team_id = ? 
            ORDER BY team_position
        ");
        $stmt->execute([$teamId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    private function getTeamPlayerCount($teamId) {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM players WHERE team_id = ?");
        $stmt->execute([$teamId]);
        return $stmt->fetchColumn();
    }
    
    private function getActiveTeamCount() {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM teams 
            WHERE game_id = ? AND is_active = 1
        ");
        $stmt->execute([$this->gameId]);
        return $stmt->fetchColumn();
    }
    
    private function getTotalPlayerCount() {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM players WHERE game_id = ?");
        $stmt->execute([$this->gameId]);
        return $stmt->fetchColumn();
    }
    
    private function getNextTeamPosition($teamId) {
        $stmt = $this->db->prepare("
            SELECT team_position FROM players 
            WHERE team_id = ? 
            ORDER BY team_position
        ");
        $stmt->execute([$teamId]);
        $usedPositions = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        for ($i = 0; $i < 4; $i++) {
            if (!in_array($i, $usedPositions)) {
                return $i;
            }
        }
        
        throw new Exception('Team ist voll');
    }
    
    private function getVirtualPlayerColor($playerId) {
        if ($this->gameMode === 'classic') {
            $stmt = $this->db->prepare("SELECT player_color FROM players WHERE id = ?");
            $stmt->execute([$playerId]);
            return $stmt->fetchColumn();
        } else {
            $stmt = $this->db->prepare("
                SELECT t.virtual_player_color 
                FROM players p 
                JOIN teams t ON p.team_id = t.id 
                WHERE p.id = ?
            ");
            $stmt->execute([$playerId]);
            return $stmt->fetchColumn();
        }
    }
    
    private function isPlayerTurn($playerId) {
        if ($this->gameMode === 'classic') {
            $gameState = $this->getGameState();
            $playerPosition = $this->getPlayerPosition($playerId);
            return $gameState['current_player'] === $playerPosition;
        } else {
            $currentTurn = $this->getCurrentTurnInfo();
            return $currentTurn['real_player_id'] === $playerId;
        }
    }
    
    private function getCurrentTurnInfo() {
        if ($this->gameMode === 'classic') {
            $gameState = $this->getGameState();
            $currentPlayer = $this->getPlayerByPosition($gameState['current_player']);
            return [
                'player_id' => $currentPlayer['id'],
                'player_name' => $currentPlayer['player_name'],
                'color' => $currentPlayer['player_color'],
                'mode' => 'classic'
            ];
        } else {
            $gameState = $this->getGameState();
            return $gameState['current_turn_info'] ?? $this->calculateCurrentPlayer();
        }
    }
    
    private function logMove($playerId, $moveType, $moveData, $diceValue = null) {
        $gameState = $this->getGameState();
        $virtualColor = $this->getVirtualPlayerColor($playerId);
        $teamId = null;
        
        if ($this->gameMode === 'teamplay') {
            $stmt = $this->db->prepare("SELECT team_id FROM players WHERE id = ?");
            $stmt->execute([$playerId]);
            $teamId = $stmt->fetchColumn();
        }
        
        $stmt = $this->db->prepare("
            INSERT INTO game_moves 
            (game_id, player_id, team_id, virtual_player_color, move_type, move_data, dice_value, round_number, turn_number) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $this->gameId,
            $playerId,
            $teamId,
            $virtualColor,
            $moveType,
            json_encode($moveData),
            $diceValue,
            $gameState['round_number'] ?? 1,
            $gameState['turn_number'] ?? 1
        ]);
    }
    
    /**
     * Allgemeine Hilfsfunktionen
     */
    private function generateGameCode() {
        return strtoupper(substr(md5(uniqid()), 0, 6));
    }
    
    private function loadGame() {
        $stmt = $this->db->prepare("SELECT * FROM games WHERE id = ?");
        $stmt->execute([$this->gameId]);
        $game = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($game) {
            $this->gameCode = $game['game_code'];
            $this->gameMode = $game['game_mode'];
        }
    }
    
    private function getPlayerCount() {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM players WHERE game_id = ?");
        $stmt->execute([$this->gameId]);
        return $stmt->fetchColumn();
    }
    
    private function getMaxPlayers() {
        $stmt = $this->db->prepare("SELECT max_players FROM games WHERE id = ?");
        $stmt->execute([$this->gameId]);
        return $stmt->fetchColumn();
    }
    
    private function getNextPlayerPosition() {
        $stmt = $this->db->prepare("SELECT position FROM players WHERE game_id = ? ORDER BY position");
        $stmt->execute([$this->gameId]);
        $usedPositions = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        for ($i = 0; $i < 4; $i++) {
            if (!in_array($i, $usedPositions)) {
                return $i;
            }
        }
        
        throw new Exception('Keine freie Position verfügbar');
    }
    
    private function getGameState() {
        $stmt = $this->db->prepare("SELECT board_state FROM game_state WHERE game_id = ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([$this->gameId]);
        $result = $stmt->fetchColumn();
        
        return json_decode($result, true);
    }
    
    private function updateGameState($gameState) {
        $stmt = $this->db->prepare("
            INSERT INTO game_state (game_id, board_state, current_turn_info, round_number, turn_number, updated_at) 
            VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
        ");
        $stmt->execute([
            $this->gameId, 
            json_encode($gameState),
            json_encode($gameState['current_turn_info'] ?? null),
            $gameState['round_number'] ?? 1,
            $gameState['turn_number'] ?? 1
        ]);
        
        // Spiel-Timestamp aktualisieren
        $stmt = $this->db->prepare("UPDATE games SET updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$this->gameId]);
    }
    
    private function updateGameStatus($status) {
        $stmt = $this->db->prepare("UPDATE games SET status = ? WHERE id = ?");
        $stmt->execute([$status, $this->gameId]);
    }
    
    private function getPlayerColor($playerId) {
        if ($this->gameMode === 'classic') {
            $stmt = $this->db->prepare("SELECT player_color FROM players WHERE id = ?");
            $stmt->execute([$playerId]);
            return $stmt->fetchColumn();
        } else {
            return $this->getVirtualPlayerColor($playerId);
        }
    }
    
    private function getPlayerPosition($playerId) {
        $stmt = $this->db->prepare("SELECT position FROM players WHERE id = ?");
        $stmt->execute([$playerId]);
        return $stmt->fetchColumn();
    }
    
    private function getPlayerByPosition($position) {
        $stmt = $this->db->prepare("SELECT * FROM players WHERE game_id = ? AND position = ?");
        $stmt->execute([$this->gameId, $position]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    private function getNextPlayer($currentPlayer) {
        $playerCount = $this->getPlayerCount();
        return ($currentPlayer + 1) % $playerCount;
    }
    
    private function getHomeEntrance($playerColor) {
        $position = array_search($playerColor, $this->playerColors);
        return $this->startPositions[$position];
    }
    
    private function shouldEnterHome($currentField, $diceValue, $homeEntrance) {
        $targetField = $currentField + $diceValue;
        return $targetField >= $homeEntrance && $currentField < $homeEntrance;
    }
    
        /**
     * Öffentliche Info-Methoden
     */
    public function getGameInfo() {
        $gameState = $this->getGameState();
        $info = [
            'game_id' => $this->gameId,
            'game_code' => $this->gameCode,
            'game_mode' => $this->gameMode,
            'state' => $gameState,
            'current_turn' => $this->getCurrentTurnInfo()
        ];
        
        if ($this->gameMode === 'teamplay') {
            $info['teams'] = $this->getGameTeams();
        } else {
            $info['players'] = $this->getGamePlayers();
        }
        
        return $info;
    }
    
    private function getGameTeams() {
        $stmt = $this->db->prepare("
            SELECT t.*, 
                   GROUP_CONCAT(p.player_name) as player_names,
                   COUNT(p.id) as player_count
            FROM teams t 
            LEFT JOIN players p ON t.id = p.team_id 
            WHERE t.game_id = ? AND t.is_active = 1 
            GROUP BY t.id 
            ORDER BY t.virtual_player_position
        ");
        $stmt->execute([$this->gameId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    private function getGamePlayers() {
        $stmt = $this->db->prepare("
            SELECT * FROM players 
            WHERE game_id = ? 
            ORDER BY position
        ");
        $stmt->execute([$this->gameId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Spiel per Code finden
     */
    public static function findByCode($gameCode) {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT id FROM games WHERE game_code = ?");
        $stmt->execute([$gameCode]);
        $gameId = $stmt->fetchColumn();
        
        if ($gameId) {
            return new Game($gameId);
        }
        
        return null;
    }
    
    /**
     * Chat-Nachricht senden
     */
    public function sendChatMessage($playerId, $message, $isTeamMessage = false) {
        $stmt = $this->db->prepare("
            INSERT INTO game_chat (game_id, player_id, message, is_team_message) 
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$this->gameId, $playerId, $message, $isTeamMessage ? 1 : 0]);
        
        return $this->db->lastInsertId();
    }
    
    /**
     * Chat-Nachrichten abrufen
     */
    public function getChatMessages($playerId = null, $limit = 50) {
        if ($this->gameMode === 'teamplay' && $playerId) {
            // Für Teamplay: Alle öffentlichen + eigene Team-Nachrichten
            $stmt = $this->db->prepare("
                SELECT gc.*, p.player_name, t.virtual_player_color
                FROM game_chat gc
                JOIN players p ON gc.player_id = p.id
                LEFT JOIN teams t ON p.team_id = t.id
                WHERE gc.game_id = ? 
                AND (gc.is_team_message = 0 
                     OR (gc.is_team_message = 1 AND p.team_id = (
                         SELECT team_id FROM players WHERE id = ?
                     )))
                ORDER BY gc.created_at DESC 
                LIMIT ?
            ");
            $stmt->execute([$this->gameId, $playerId, $limit]);
        } else {
            // Für Classic: Alle Nachrichten
            $stmt = $this->db->prepare("
                SELECT gc.*, p.player_name, p.player_color
                FROM game_chat gc
                JOIN players p ON gc.player_id = p.id
                WHERE gc.game_id = ? AND gc.is_team_message = 0
                ORDER BY gc.created_at DESC 
                LIMIT ?
            ");
            $stmt->execute([$this->gameId, $limit]);
        }
        
        return array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC));
    }
} 

