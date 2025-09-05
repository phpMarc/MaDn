Eine Online Multiplayer Version von Mensch ärgere dich nicht.

Struktur:
|── index.php                 # Hauptseite / Lobby
├── game.php                  # Spielseite
├── api/
│   ├── create_game.php       # Spiel erstellen
│   ├── join_game.php         # Spiel beitreten
│   ├── game_state.php        # Spielstand abrufen
│   ├── make_move.php         # Zug machen
│   └── poll_updates.php      # Updates abfragen
├── classes/
│   ├── Database.php          # DB-Verbindung
│   ├── Game.php              # Spiellogik
│   ├── Player.php            # Spieler-Management
│   ├── AI.php                # KI-Logik
│   └── Template.php          # Template-System
├── templates/
│   ├── header.php
│   ├── footer.php
│   ├── lobby.php
│   └── game_board.php
├── assets/
│   ├── css/
│   │   ├── style.css
│   │   └── themes.css
│   ├── js/
│   │   ├── game.js
│   │   ├── lobby.js
│   └── images/
├── database/
│   └── game.db              # SQLite Datenbank
└── config/
    └── config.php           # Konfiguration
