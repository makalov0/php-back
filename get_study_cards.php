<?php
// get_study_cards.php - Get study cards for a category
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

include 'connect_db.php';

// Validate required parameter
if (!isset($_GET['category_id']) || empty($_GET['category_id'])) {
    echo json_encode(array(
        'success' => false,
        'message' => 'Category ID is required'
    ));
    exit;
}

$category_id = intval($_GET['category_id']);

if ($category_id <= 0) {
    echo json_encode(array(
        'success' => false,
        'message' => 'Invalid category ID'
    ));
    exit;
}

try {
    // First verify the category exists
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
    
    // Get study cards for the category (randomized order)
    $stmt = $conn->prepare("SELECT id, category_id, word, definition, created_at FROM study_cards WHERE category_id = ? ORDER BY RAND()");
    $stmt->bind_param("i", $category_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $cards = array();
    while($row = $result->fetch_assoc()) {
        $cards[] = array(
            'id' => (int)$row['id'],
            'category_id' => (int)$row['category_id'],
            'word' => $row['word'],
            'definition' => $row['definition'],
            'created_at' => $row['created_at']
        );
    }
    
    echo json_encode(array(
        'success' => true,
        'cards' => $cards,
        'category' => $category_info['name'],
        'count' => count($cards)
    ));
    
    $stmt->close();
} catch(Exception $e) {
    echo json_encode(array(
        'success' => false,
        'message' => 'Error fetching study cards: ' . $e->getMessage()
    ));
}

$conn->close();
?>