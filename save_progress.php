<?php
// save_progress.php - Save user progress for individual cards
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
$card_id = isset($_POST['card_id']) ? intval($_POST['card_id']) : null;
$remembered = isset($_POST['remembered']) ? ($_POST['remembered'] === 'true' ? 1 : 0) : 0;

// Validate required fields
if (!$user_id || $user_id <= 0) {
    echo json_encode(array(
        'success' => false, 
        'message' => 'Valid User ID is required'
    ));
    exit;
}

if (!$card_id || $card_id <= 0) {
    echo json_encode(array(
        'success' => false, 
        'message' => 'Valid Card ID is required'
    ));
    exit;
}

try {
    // Verify user exists
    $user_check = $conn->prepare("SELECT id FROM users WHERE id = ?");
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
    $user_check->close();
    
    // Verify card exists
    $card_check = $conn->prepare("SELECT id FROM study_cards WHERE id = ?");
    $card_check->bind_param("i", $card_id);
    $card_check->execute();
    $card_result = $card_check->get_result();
    
    if ($card_result->num_rows === 0) {
        echo json_encode(array(
            'success' => false,
            'message' => 'Study card not found'
        ));
        $card_check->close();
        $conn->close();
        exit;
    }
    $card_check->close();
    
    // Check if progress record already exists
    $check_stmt = $conn->prepare("SELECT id, study_count FROM user_progress WHERE user_id = ? AND card_id = ?");
    $check_stmt->bind_param("ii", $user_id, $card_id);
    $check_stmt->execute();
    $existing_result = $check_stmt->get_result();
    $existing = $existing_result->fetch_assoc();
    $check_stmt->close();
    
    if ($existing) {
        // Update existing progress record
        $new_study_count = $existing['study_count'] + 1;
        $update_stmt = $conn->prepare("UPDATE user_progress SET remembered = ?, study_count = ?, last_studied = NOW() WHERE user_id = ? AND card_id = ?");
        $update_stmt->bind_param("iiii", $remembered, $new_study_count, $user_id, $card_id);
        
        if ($update_stmt->execute()) {
            echo json_encode(array(
                'success' => true,
                'message' => 'Progress updated successfully',
                'action' => 'updated',
                'study_count' => $new_study_count
            ));
        } else {
            echo json_encode(array(
                'success' => false,
                'message' => 'Failed to update progress: ' . $update_stmt->error
            ));
        }
        $update_stmt->close();
    } else {
        // Insert new progress record
        $insert_stmt = $conn->prepare("INSERT INTO user_progress (user_id, card_id, remembered, study_count, last_studied) VALUES (?, ?, ?, 1, NOW())");
        $insert_stmt->bind_param("iii", $user_id, $card_id, $remembered);
        
        if ($insert_stmt->execute()) {
            echo json_encode(array(
                'success' => true,
                'message' => 'Progress saved successfully',
                'action' => 'created',
                'study_count' => 1
            ));
        } else {
            echo json_encode(array(
                'success' => false,
                'message' => 'Failed to save progress: ' . $insert_stmt->error
            ));
        }
        $insert_stmt->close();
    }
    
} catch(Exception $e) {
    echo json_encode(array(
        'success' => false,
        'message' => 'Error saving progress: ' . $e->getMessage()
    ));
}

$conn->close();
?>