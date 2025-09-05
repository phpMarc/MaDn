<!DOCTYPE html>
<html lang="de" data-theme="<?php echo $current_theme; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $title ?? 'Mensch ärgere dich nicht'; ?></title>
    <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/style.css">
    <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/themes.css">
</head>
<body>
    <header class="main-header">
        <div class="container">
            <h1>🎲 Mensch ärgere dich nicht</h1>
            <div class="header-controls">
                <button id="theme-toggle" class="theme-btn">
                    <?php echo $current_theme === 'dark' ? '☀️' : '🌙'; ?>
                </button>
            </div>
        </div>
    </header>
    <main class="main-content">
