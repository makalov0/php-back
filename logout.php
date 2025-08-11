<?php
// logout.php
session_start();
include 'connect_db.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Only POST method allowed']);
    exit();
}

if (isset($_POST['logout'])) {
    try {
        session_destroy();
        echo json_encode([
            'success' => true,
            'message' => 'Logout successful'
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Logout error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}

$conn->close();
?>