<?php
// get_category_by_name.php - Get category information by name
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
if (!isset($_GET['category_name']) || empty(trim($_GET['category_name']))) {
    echo json_encode(array(
        'success' => false, 
        'message' => 'Category name is required'
    ));
    exit;
}

$category_name = trim($_GET['category_name']);

try {
    // Search for category by name (case-insensitive)
    $stmt = $conn->prepare("SELECT id, name, icon, color, created_at FROM categories WHERE LOWER(name) = LOWER(?)");
    $stmt->bind_param("s", $category_name);
    $stmt->execute();
    $result = $stmt->get_result();
    $category = $result->fetch_assoc();
    $stmt->close();
    
    if ($category) {
        // Get additional statistics for this category
        $stats_stmt = $conn->prepare("
            SELECT 
                COUNT(*) as total_cards,
                MIN(created_at) as first_card_created,
                MAX(created_at) as last_card_created
            FROM study_cards 
            WHERE category_id = ?
        ");
        $stats_stmt->bind_param("i", $category['id']);
        $stats_stmt->execute();
        $stats_result = $stats_stmt->get_result();
        $card_stats = $stats_result->fetch_assoc();
        $stats_stmt->close();
        
        // Get total users who have studied cards from this category
        $users_stmt = $conn->prepare("
            SELECT COUNT(DISTINCT up.user_id) as total_students
            FROM user_progress up
            JOIN study_cards sc ON up.card_id = sc.id
            WHERE sc.category_id = ?
        ");
        $users_stmt->bind_param("i", $category['id']);
        $users_stmt->execute();
        $users_result = $users_stmt->get_result();
        $user_stats = $users_result->fetch_assoc();
        $users_stmt->close();
        
        echo json_encode(array(
            'success' => true,
            'category' => array(
                'id' => (int)$category['id'],
                'name' => $category['name'],
                'icon' => $category['icon'],
                'color' => $category['color'],
                'created_at' => $category['created_at'],
                'total_cards' => (int)$card_stats['total_cards'],
                'total_students' => (int)$user_stats['total_students'],
                'first_card_created' => $card_stats['first_card_created'],
                'last_card_created' => $card_stats['last_card_created']
            )
        ));
    } else {
        // Category not found - provide suggestions for similar names
        $suggestion_stmt = $conn->prepare("
            SELECT name 
            FROM categories 
            WHERE name LIKE CONCAT('%', ?, '%') 
            OR SOUNDEX(name) = SOUNDEX(?)
            LIMIT 3
        ");
        $suggestion_stmt->bind_param("ss", $category_name, $category_name);
        $suggestion_stmt->execute();
        $suggestion_result = $suggestion_stmt->get_result();
        
        $suggestions = array();
        while($row = $suggestion_result->fetch_assoc()) {
            $suggestions[] = $row['name'];
        }
        $suggestion_stmt->close();
        
        echo json_encode(array(
            'success' => false,
            'message' => 'Category not found',
            'searched_name' => $category_name,
            'suggestions' => $suggestions,
            'available_categories' => $this->getAllCategoryNames($conn)
        ));
    }
    
} catch(Exception $e) {
    echo json_encode(array(
        'success' => false,
        'message' => 'Error searching for category: ' . $e->getMessage()
    ));
}

// Helper function to get all category names for fallback
function getAllCategoryNames($conn) {
    try {
        $result = $conn->query("SELECT name FROM categories ORDER BY name ASC");
        $names = array();
        while($row = $result->fetch_assoc()) {
            $names[] = $row['name'];
        }
        return $names;
    } catch(Exception $e) {
        return array();
    }
}

$conn->close();
?>