/**
 * Ludo Realtime Client - Smart Polling System
 * Keine WebSockets, nur cleveres AJAX Polling!
 */

class LudoRealtime {
    constructor(gameId, playerId = null, options = {}) {
        this.gameId = gameId;
        this.playerId = playerId;
        this.lastUpdate = Math.floor(Date.now() / 1000);
        this.isActive = true;
        this.isConnected = false;
        
        // Konfiguration
        this.config = {
            baseInterval: 3000,     // 3 Sekunden normal
            fastInterval: 1000,     // 1 Sekunde bei Aktivität
            heartbeatInterval: 30000, // 30 Sekunden Heartbeat
            maxRetries: 5,
            retryDelay: 2000,
            longPoll: false,        // Experimentell
            ...options
        };
        
        // State
        this.currentInterval = this.config.baseInterval;
        this.retryCount = 0;
        this.pollTimer = null;
        this.heartbeatTimer = null;
        this.lastActivity = Date.now();
        
        // Event Callbacks
        this.callbacks = {
            onConnect: () => {},
            onDisconnect: () => {},
            onGameMove: (move) => {},
            onChatMessage: (message) => {},
            onPlayerJoined: (player) => {},
            onPlayerLeft: (player) => {},
            onPlayerStatusChange: (player) => {},
            onGameUpdate: (gameInfo) => {},
            onGameStarted: (gameInfo) => {},
            onGameFinished: (result) => {},
            onError: (error) => {}
        };
        
        console.log('🚀 Ludo Realtime Client initialisiert');
        console.log(`🎮 Spiel: ${gameId}, Spieler: ${playerId || 'Zuschauer'}`);
        
        this.init();
    }
    
    /**
     * Initialisierung
     */
    init() {
        this.setupVisibilityHandling();
        this.setupActivityTracking();
        this.startPolling();
        this.startHeartbeat();
        
        // Sofort erste Updates holen
        this.pollForUpdates();
    }
    
    /**
     * Event-Handler registrieren
     */
    on(event, callback) {
        if (this.callbacks.hasOwnProperty('on' + this.capitalize(event))) {
            this.callbacks['on' + this.capitalize(event)] = callback;
        } else {
            console.warn(`Unbekanntes Event: ${event}`);
        }
        return this;
    }
    
    /**
     * Polling starten
     */
    startPolling() {
        if (this.pollTimer) {
            clearTimeout(this.pollTimer);
        }
        
        this.pollTimer = setTimeout(() => {
            if (this.isActive) {
                this.pollForUpdates();
            }
            this.startPolling(); // Nächster Cycle
        }, this.currentInterval);
    }
    
    /**
     * Updates vom Server holen
     */
    async pollForUpdates() {
        if (!this.isActive) return;
        
        try {
            const url = `api/realtime.php?action=poll_updates&game_id=${this.gameId}&last_update=${this.lastUpdate}` +
                       (this.playerId ? `&player_id=${this.playerId}` : '');
            
            const response = await fetch(url, {
                method: 'GET',
                headers: {
                    'Cache-Control': 'no-cache'
                }
            });
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            
            const data = await response.json();
            
            if (!data.success) {
                throw new Error(data.error || 'Unbekannter Server-Fehler');
            }
            
            // Verbindung wiederhergestellt
            if (!this.isConnected) {
                this.isConnected = true;
                this.retryCount = 0;
                this.callbacks.onConnect();
                console.log('✅ Verbindung hergestellt');
            }
            
            // Timestamp aktualisieren
            this.lastUpdate = data.timestamp;
            
            // Updates verarbeiten
            if (data.has_updates) {
                this.handleUpdates(data.updates, data.game_info);
                
                // Schnelleres Polling bei Aktivität
                this.setFastPolling();
            } else {
                // Zurück zu normalem Interval
                this.setNormalPolling();
            }
            
            // Server-empfohlenes Interval verwenden
            if (data.polling_interval) {
                this.currentInterval = data.polling_interval;
            }
            
        } catch (error) {
            this.handleConnectionError(error);
        }
    }
    
    /**
     * Updates verarbeiten
     */
    handleUpdates(updates, gameInfo) {
        console.log('📨 Updates erhalten:', updates);
        
        // Spielzüge verarbeiten
        if (updates.moves) {
            updates.moves.forEach(move => {
                this.callbacks.onGameMove(move);
                
                // Spezielle Move-Types
                if (move.move_type === 'player_joined') {
                    this.callbacks.onPlayerJoined(move);
                } else if (move.move_type === 'player_left') {
                    this.callbacks.onPlayerLeft(move);
                }
            });
        }
        
        // Chat-Nachrichten verarbeiten
        if (updates.chat) {
            updates.chat.forEach(message => {
                this.callbacks.onChatMessage(message);
            });
        }
        
        // Spieler-Updates
        if (updates.players) {
            updates.players.forEach(player => {
                this.callbacks.onPlayerStatusChange(player);
            });
        }
        
        // Spiel-Status Updates
        if (updates.game_status) {
            const status = updates.game_status;
            
            if (status.game_state === 'playing' && status.game_state !== this.lastGameState) {
                this.callbacks.onGameStarted(gameInfo);
            } else if (status.game_state === 'finished') {
                this.callbacks.onGameFinished(status);
            }
            
            this.lastGameState = status.game_state;
        }
        
        // Spiel gelöscht
        if (updates.game_deleted) {
            this.callbacks.onError('Spiel wurde gelöscht oder ist nicht mehr verfügbar');
            this.stop();
            return;
        }
        
        // Komplettes Spiel-Update
        if (gameInfo) {
            this.callbacks.onGameUpdate(gameInfo);
        }
    }
    
    /**
     * Verbindungsfehler behandeln
     */
    handleConnectionError(error) {
        console.error('❌ Polling-Fehler:', error);
        
        if (this.isConnected) {
            this.isConnected = false;
            this.callbacks.onDisconnect();
        }
        
        this.retryCount++;
        
        if (this.retryCount <= this.config.maxRetries) {
            // Exponential backoff
            const delay = this.config.retryDelay * Math.pow(2, this.retryCount - 1);
            console.log(`🔄 Retry ${this.retryCount}/${this.config.maxRetries} in ${delay}ms`);
            
            setTimeout(() => {
                this.pollForUpdates();
            }, delay);
        } else {
            console.error('💥 Maximale Retry-Versuche erreicht');
            this.callbacks.onError('Verbindung zum Server verloren');
        }
    }
    
    /**
     * Heartbeat-System
     */
    startHeartbeat() {
        if (!this.playerId) return; // Nur für echte Spieler
        
        this.heartbeatTimer = setInterval(async () => {
            try {
                const response = await fetch('api/realtime.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=heartbeat&game_id=${this.gameId}&player_id=${this.playerId}`
                });
                
                const data = await response.json();
                
                if (data.success) {
                    console.log(`💓 Heartbeat: ${data.online_players} Spieler online`);
                }
                
            } catch (error) {
                console.warn('💔 Heartbeat-Fehler:', error);
            }
        }, this.config.heartbeatInterval);
    }
    
    /**
     * Spieler-Status setzen
     */
    async setPlayerStatus(status) {
        if (!this.playerId) return;
        
        try {
            const response = await fetch('api/realtime.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=set_status&game_id=${this.gameId}&player_id=${this.playerId}&status=${status}`
            });
            
            const data = await response.json();
            return data.success;
            
        } catch (error) {
            console.error('Status-Update Fehler:', error);
            return false;
        }
    }
    
    /**
     * Online-Spieler abrufen
     */
    async getOnlinePlayers() {
        try {
            const response = await fetch(`api/realtime.php?action=get_online_players&game_id=${this.gameId}`);
            const data = await response.json();
            
            if (data.success) {
                return data.players;
            }
            
        } catch (error) {
            console.error('Online-Spieler Fehler:', error);
        }
        
        return [];
    }
    
    /**
     * Letzte Events abrufen
     */
    async getRecentEvents(limit = 20) {
        try {
            const since = new Date(Date.now() - 3600000).toISOString(); // Letzte Stunde
            const response = await fetch(`api/realtime.php?action=get_recent_events&game_id=${this.gameId}&limit=${limit}&since=${since}`);
            const data = await response.json();
            
            if (data.success) {
                return data.events;
            }
            
        } catch (error) {
            console.error('Events-Fehler:', error);
        }
        
        return [];
    }
    
    /**
     * Tab-Sichtbarkeit überwachen
     */
    setupVisibilityHandling() {
        document.addEventListener('visibilitychange', () => {
            const wasActive = this.isActive;
            this.isActive = !document.hidden;
            
            if (this.isActive && !wasActive) {
                console.log('👁️ Tab wieder aktiv - sofortige Updates');
                this.pollForUpdates();
                this.setFastPolling();
            } else if (!this.isActive) {
                console.log('😴 Tab inaktiv - langsameres Polling');
                this.setSlowPolling();
            }
        });
        
        // Fenster-Focus Events
        window.addEventListener('focus', () => {
            if (this.isActive) {
                this.pollForUpdates();
            }
        });
    }
    
    /**
     * Aktivitäts-Tracking
     */
    setupActivityTracking() {
        const activityEvents = ['mousedown', 'mousemove', 'keypress', 'scroll', 'touchstart'];
        
        const updateActivity = () => {
            this.lastActivity = Date.now();
        };
        
        activityEvents.forEach(event => {
            document.addEventListener(event, updateActivity, { passive: true });
        });
        
        // Inaktivität prüfen
        setInterval(() => {
            const inactiveTime = Date.now() - this.lastActivity;
            
            if (inactiveTime > 300000) { // 5 Minuten inaktiv
                this.setSlowPolling();
            }
        }, 60000); // Jede Minute prüfen
    }
    
    /**
     * Polling-Geschwindigkeit anpassen
     */
    setFastPolling() {
        this.currentInterval = this.config.fastInterval;
    }
    
    setNormalPolling() {
        this.currentInterval = this.config.baseInterval;
    }
    
    setSlowPolling() {
        this.currentInterval = this.config.baseInterval * 2; // 6 Sekunden
    }
    
    /**
     * Manuell Updates forcieren
     */
    forceUpdate() {
        console.log('🔄 Manuelles Update angefordert');
        this.pollForUpdates();
    }
    
    /**
     * Verbindungsqualität prüfen
     */
    async checkConnection() {
        const start = Date.now();
        
        try {
            const response = await fetch(`api/realtime.php?action=heartbeat&game_id=${this.gameId}&player_id=${this.playerId || 'test'}`);
            const latency = Date.now() - start;
            
            return {
                connected: response.ok,
                latency: latency,
                quality: latency < 100 ? 'excellent' : latency < 300 ? 'good' : latency < 1000 ? 'fair' : 'poor'
            };
            
        } catch (error) {
            return {
                connected: false,
                latency: -1,
                quality: 'offline',
                error: error.message
            };
        }
    }
    
    /**
     * Debug-Informationen
     */
    getDebugInfo() {
        return {
            gameId: this.gameId,
            playerId: this.playerId,
            isActive: this.isActive,
            isConnected: this.isConnected,
            currentInterval: this.currentInterval,
            lastUpdate: this.lastUpdate,
            retryCount: this.retryCount,
            lastActivity: new Date(this.lastActivity).toLocaleTimeString()
        };
    }
    
    /**
     * Realtime-Client stoppen
     */
    stop() {
        console.log('🛑 Realtime-Client wird gestoppt');
        
        if (this.pollTimer) {
            clearTimeout(this.pollTimer);
            this.pollTimer = null;
        }
        
        if (this.heartbeatTimer) {
            clearInterval(this.heartbeatTimer);
            this.heartbeatTimer = null;
        }
        
        this.isActive = false;
        this.isConnected = false;
    }
    
    /**
     * Hilfsfunktionen
     */
    capitalize(str) {
        return str.charAt(0).toUpperCase() + str.slice(1);
    }
}

// ==================== REALTIME MANAGER ====================

/**
 * Globaler Realtime-Manager für einfache Nutzung
 */
class LudoRealtimeManager {
    constructor() {
        this.clients = new Map();
        this.globalCallbacks = {
            onAnyGameUpdate: () => {},
            onConnectionChange: () => {}
        };
    }
    
    /**
     * Realtime-Client für Spiel erstellen
     */
    connect(gameId, playerId = null, options = {}) {
        if (this.clients.has(gameId)) {
            console.warn(`Realtime-Client für Spiel ${gameId} existiert bereits`);
            return this.clients.get(gameId);
        }
        
        const client = new LudoRealtime(gameId, playerId, options);
        
        // Globale Events weiterleiten
        client.on('connect', () => {
            this.globalCallbacks.onConnectionChange(gameId, 'connected');
        });
        
        client.on('disconnect', () => {
            this.globalCallbacks.onConnectionChange(gameId, 'disconnected');
        });
        
        client.on('gameUpdate', (gameInfo) => {
            this.globalCallbacks.onAnyGameUpdate(gameId, gameInfo);
        });
        
        this.clients.set(gameId, client);
        
        console.log(`🔗 Realtime-Client für Spiel ${gameId} erstellt`);
        return client;
    }
    
    /**
     * Client für Spiel abrufen
     */
    getClient(gameId) {
        return this.clients.get(gameId);
    }
    
    /**
     * Client trennen
     */
    disconnect(gameId) {
        const client = this.clients.get(gameId);
        if (client) {
            client.stop();
            this.clients.delete(gameId);
            console.log(`🔌 Realtime-Client für Spiel ${gameId} getrennt`);
        }
    }
    
    /**
     * Alle Clients trennen
     */
    disconnectAll() {
        this.clients.forEach((client, gameId) => {
            client.stop();
        });
        this.clients.clear();
        console.log('🔌 Alle Realtime-Clients getrennt');
    }
    
    /**
     * Globale Event-Handler
     */
    onAnyGameUpdate(callback) {
        this.globalCallbacks.onAnyGameUpdate = callback;
    }
    
    onConnectionChange(callback) {
        this.globalCallbacks.onConnectionChange = callback;
    }
    
    /**
     * Status aller Verbindungen
     */
    getStatus() {
        const status = {};
        this.clients.forEach((client, gameId) => {
            status[gameId] = client.getDebugInfo();
        });
        return status;
    }
}

// Globale Instanz erstellen
window.LudoRealtime = LudoRealtime;
window.ludoRealtimeManager = new LudoRealtimeManager();

console.log('🚀 Ludo Realtime System geladen');
