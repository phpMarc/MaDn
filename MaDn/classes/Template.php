<?php
class Template {
    private $variables = [];
    private $theme = 'light';
    
    public function __construct() {
        $this->theme = $_SESSION['theme'] ?? 'light';
    }
    
    public function assign($key, $value) {
        $this->variables[$key] = $value;
    }
    
    public function setTheme($theme) {
        $this->theme = in_array($theme, ['light', 'dark']) ? $theme : 'light';
        $_SESSION['theme'] = $this->theme;
    }
    
    public function render($template, $layout = true) {
        // Variablen für Template verfügbar machen
        extract($this->variables);
        $current_theme = $this->theme;
        
        ob_start();
        
        if ($layout) {
            include __DIR__ . '/../templates/header.php';
        }
        
        include __DIR__ . "/../templates/{$template}.php";
        
        if ($layout) {
            include __DIR__ . '/../templates/footer.php';
        }
        
        return ob_get_clean();
    }
    
    public function display($template, $layout = true) {
        echo $this->render($template, $layout);
    }
}
?>
