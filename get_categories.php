<?php
// get_categories.php - Get all categories
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

include 'connect_db.php';

try {
    $query = "SELECT id, name, icon, color, created_at FROM categories ORDER BY name ASC";
    $result = $conn->query($query);
    
    if ($result) {
        $categories = array();
        while($row = $result->fetch_assoc()) {
            $categories[] = array(
                'id' => (int)$row['id'],
                'name' => $row['name'],
                'icon' => $row['icon'],
                'color' => $row['color'],
                'created_at' => $row['created_at']
            );
        }
        
        echo json_encode(array(
            'success' => true,
            'categories' => $categories,
            'count' => count($categories)
        ));
    } else {
        echo json_encode(array(
            'success' => false,
            'message' => 'Error fetching categories: ' . $conn->error
        ));
    }
} catch(Exception $e) {
    echo json_encode(array(
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ));
}

$conn->close();
?>