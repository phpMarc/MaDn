    </main>
    <footer class="main-footer">
        <div class="container">
            <p>© 2024 Mensch ärgere dich nicht - Online Multiplayer</p>
        </div>
    </footer>
    
    <script src="<?php echo ASSETS_URL; ?>/js/lobby.js"></script>
    <script>
        // Theme Toggle
        document.getElementById('theme-toggle').addEventListener('click', function() {
            const currentTheme = document.documentElement.getAttribute('data-theme');
            const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
            
            fetch('api/set_theme.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({theme: newTheme})
            }).then(() => {
                document.documentElement.setAttribute('data-theme', newTheme);
                this.textContent = newTheme === 'dark' ? '☀️' : '🌙';
            });
        });
    </script>
</body>
</html>
