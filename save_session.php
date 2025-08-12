<?php
// save_session.php - Save complete study session results
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

include 'connect_db.php';

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array(
        'success' => false, 
        'message' => 'POST method required'
    ));
    exit;
}

// Get and validate input parameters
$user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : null;
$category_id = isset($_POST['category_id']) ? intval($_POST['category_id']) : null;
$total_cards = isset($_POST['total_cards']) ? intval($_POST['total_cards']) : 0;
$remembered_count = isset($_POST['remembered_count']) ? intval($_POST['remembered_count']) : 0;

// Validate required fields
if (!$user_id || $user_id <= 0) {
    echo json_encode(array(
        'success' => false, 
        'message' => 'Valid User ID is required'
    ));
    exit;
}

if (!$category_id || $category_id <= 0) {
    echo json_encode(array(
        'success' => false, 
        'message' => 'Valid Category ID is required'
    ));
    exit;
}

if ($total_cards < 0 || $remembered_count < 0) {
    echo json_encode(array(
        'success' => false, 
        'message' => 'Card counts must be non-negative numbers'
    ));
    exit;
}

if ($remembered_count > $total_cards) {
    echo json_encode(array(
        'success' => false, 
        'message' => 'Remembered count cannot exceed total cards'
    ));
    exit;
}

try {
    // Verify user exists
    $user_check = $conn->prepare("SELECT username FROM users WHERE id = ?");
    $user_check->bind_param("i", $user_id);
    $user_check->execute();
    $user_result = $user_check->get_result();
    
    if ($user_result->num_rows === 0) {
        echo json_encode(array(
            'success' => false,
            'message' => 'User not found'
        ));
        $user_check->close();
        $conn->close();
        exit;
    }
    
    $user_info = $user_result->fetch_assoc();
    $user_check->close();
    
    // Verify category exists
    $category_check = $conn->prepare("SELECT name FROM categories WHERE id = ?");
    $category_check->bind_param("i", $category_id);
    $category_check->execute();
    $category_result = $category_check->get_result();
    
    if ($category_result->num_rows === 0) {
        echo json_encode(array(
            'success' => false,
            'message' => 'Category not found'
        ));
        $category_check->close();
        $conn->close();
        exit;
    }
    
    $category_info = $category_result->fetch_assoc();
    $category_check->close();
    
    // Insert study session record
    $stmt = $conn->prepare("INSERT INTO study_sessions (user_id, category_id, total_cards, remembered_count, session_date) VALUES (?, ?, ?, ?, NOW())");
    $stmt->bind_param("iiii", $user_id, $category_id, $total_cards, $remembered_count);
    
    if ($stmt->execute()) {
        $session_id = $conn->insert_id;
        $accuracy = $total_cards > 0 ? round(($remembered_count / $total_cards) * 100, 1) : 0;
        
        echo json_encode(array(
            'success' => true,
            'message' => 'Study session saved successfully',
            'session_id' => $session_id,
            'summary' => array(
                'user' => $user_info['username'],
                'category' => $category_info['name'],
                'total_cards' => $total_cards,
                'remembered_count' => $remembered_count,
                'missed_count' => $total_cards - $remembered_count,
                'accuracy_percentage' => $accuracy
            )
        ));
    } else {
        echo json_encode(array(
            'success' => false,
            'message' => 'Failed to save study session: ' . $stmt->error
        ));
    }
    
    $stmt->close();
    
} catch(Exception $e) {
    echo json_encode(array(
        'success' => false,
        'message' => 'Error saving study session: ' . $e->getMessage()
    ));
}

$conn->close();
?>