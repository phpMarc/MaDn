<?php
require_once '../config/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (isset($input['theme']) && in_array($input['theme'], ['light', 'dark'])) {
        $_SESSION['theme'] = $input['theme'];
        echo json_encode(['success' => true, 'theme' => $input['theme']]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid theme']);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
}
?>
