<?php
// get_user_stats.php - Get comprehensive user statistics
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
if (!isset($_GET['user_id']) || empty($_GET['user_id'])) {
    echo json_encode(array(
        'success' => false, 
        'message' => 'User ID is required'
    ));
    exit;
}

$user_id = intval($_GET['user_id']);

if ($user_id <= 0) {
    echo json_encode(array(
        'success' => false,
        'message' => 'Invalid user ID'
    ));
    exit;
}

try {
    // Verify user exists
    $user_check = $conn->prepare("SELECT username, email FROM users WHERE id = ?");
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
    
    // Get total study sessions
    $sessions_stmt = $conn->prepare("SELECT COUNT(*) as total_sessions FROM study_sessions WHERE user_id = ?");
    $sessions_stmt->bind_param("i", $user_id);
    $sessions_stmt->execute();
    $sessions_result = $sessions_stmt->get_result();
    $total_sessions = $sessions_result->fetch_assoc()['total_sessions'];
    $sessions_stmt->close();
    
    // Get total unique cards studied
    $cards_stmt = $conn->prepare("SELECT COUNT(DISTINCT card_id) as total_cards_studied FROM user_progress WHERE user_id = ?");
    $cards_stmt->bind_param("i", $user_id);
    $cards_stmt->execute();
    $cards_result = $cards_stmt->get_result();
    $total_cards_studied = $cards_result->fetch_assoc()['total_cards_studied'];
    $cards_stmt->close();
    
    // Get overall accuracy from sessions
    $accuracy_stmt = $conn->prepare("
        SELECT 
            COALESCE(SUM(total_cards), 0) as total_attempted,
            COALESCE(SUM(remembered_count), 0) as total_remembered
        FROM study_sessions 
        WHERE user_id = ?
    ");
    $accuracy_stmt->bind_param("i", $user_id);
    $accuracy_stmt->execute();
    $accuracy_result = $accuracy_stmt->get_result();
    $accuracy_data = $accuracy_result->fetch_assoc();
    $accuracy_stmt->close();
    
    $overall_accuracy = 0;
    if ($accuracy_data['total_attempted'] > 0) {
        $overall_accuracy = round(($accuracy_data['total_remembered'] / $accuracy_data['total_attempted']) * 100, 1);
    }
    
    // Get category-wise statistics
    $category_stats_stmt = $conn->prepare("
        SELECT 
            c.id,
            c.name,
            c.color,
            c.icon,
            COUNT(DISTINCT sc.id) as total_cards_in_category,
            COUNT(DISTINCT up.card_id) as cards_studied,
            COALESCE(SUM(up.remembered), 0) as cards_remembered,
            COALESCE(AVG(up.study_count), 0) as avg_study_count,
            MAX(up.last_studied) as last_studied
        FROM categories c
        LEFT JOIN study_cards sc ON c.id = sc.category_id
        LEFT JOIN user_progress up ON sc.id = up.card_id AND up.user_id = ?
        GROUP BY c.id, c.name, c.color, c.icon
        ORDER BY c.name ASC
    ");
    $category_stats_stmt->bind_param("i", $user_id);
    $category_stats_stmt->execute();
    $category_result = $category_stats_stmt->get_result();
    
    $category_stats = array();
    while($row = $category_result->fetch_assoc()) {
        $mastery_percentage = 0;
        if ($row['cards_studied'] > 0) {
            $mastery_percentage = round(($row['cards_remembered'] / $row['cards_studied']) * 100, 1);
        }
        
        $progress_percentage = 0;
        if ($row['total_cards_in_category'] > 0) {
            $progress_percentage = round(($row['cards_studied'] / $row['total_cards_in_category']) * 100, 1);
        }
        
        $category_stats[] = array(
            'id' => (int)$row['id'],
            'name' => $row['name'],
            'color' => $row['color'],
            'icon' => $row['icon'],
            'total_cards_in_category' => (int)$row['total_cards_in_category'],
            'cards_studied' => (int)$row['cards_studied'],
            'cards_remembered' => (int)$row['cards_remembered'],
            'mastery_percentage' => $mastery_percentage,
            'progress_percentage' => $progress_percentage,
            'avg_study_count' => round($row['avg_study_count'], 1),
            'last_studied' => $row['last_studied']
        );
    }
    $category_stats_stmt->close();
    
    // Get recent study sessions (last 5)
    $recent_sessions_stmt = $conn->prepare("
        SELECT 
            ss.id,
            ss.total_cards,
            ss.remembered_count,
            ss.session_date,
            c.name as category_name,
            c.color as category_color
        FROM study_sessions ss
        JOIN categories c ON ss.category_id = c.id
        WHERE ss.user_id = ?
        ORDER BY ss.session_date DESC
        LIMIT 5
    ");
    $recent_sessions_stmt->bind_param("i", $user_id);
    $recent_sessions_stmt->execute();
    $recent_sessions_result = $recent_sessions_stmt->get_result();
    
    $recent_sessions = array();
    while($row = $recent_sessions_result->fetch_assoc()) {
        $session_accuracy = $row['total_cards'] > 0 ? round(($row['remembered_count'] / $row['total_cards']) * 100, 1) : 0;
        
        $recent_sessions[] = array(
            'id' => (int)$row['id'],
            'category_name' => $row['category_name'],
            'category_color' => $row['category_color'],
            'total_cards' => (int)$row['total_cards'],
            'remembered_count' => (int)$row['remembered_count'],
            'accuracy' => $session_accuracy,
            'session_date' => $row['session_date']
        );
    }
    $recent_sessions_stmt->close();
    
    // Get study streak (consecutive days with sessions)
    $streak_stmt = $conn->prepare("
        SELECT DATE(session_date) as study_date 
        FROM study_sessions 
        WHERE user_id = ? 
        GROUP BY DATE(session_date) 
        ORDER BY study_date DESC
    ");
    $streak_stmt->bind_param("i", $user_id);
    $streak_stmt->execute();
    $streak_result = $streak_stmt->get_result();
    
    $study_dates = array();
    while($row = $streak_result->fetch_assoc()) {
        $study_dates[] = $row['study_date'];
    }
    $streak_stmt->close();
    
    // Calculate current streak
    $current_streak = 0;
    $today = date('Y-m-d');
    $yesterday = date('Y-m-d', strtotime('-1 day'));
    
    if (!empty($study_dates)) {
        $latest_date = $study_dates[0];
        if ($latest_date === $today || $latest_date === $yesterday) {
            $current_streak = 1;
            $check_date = date('Y-m-d', strtotime($latest_date . ' -1 day'));
            
            for ($i = 1; $i < count($study_dates); $i++) {
                if ($study_dates[$i] === $check_date) {
                    $current_streak++;
                    $check_date = date('Y-m-d', strtotime($check_date . ' -1 day'));
                } else {
                    break;
                }
            }
        }
    }
    
    // Compile final response
    $response = array(
        'success' => true,
        'user_info' => array(
            'id' => $user_id,
            'username' => $user_info['username'],
            'email' => $user_info['email']
        ),
        'stats' => array(
            'total_sessions' => (int)$total_sessions,
            'total_cards_studied' => (int)$total_cards_studied,
            'total_cards_attempted' => (int)$accuracy_data['total_attempted'],
            'total_cards_remembered' => (int)$accuracy_data['total_remembered'],
            'overall_accuracy' => $overall_accuracy,
            'current_streak' => $current_streak,
            'category_stats' => $category_stats,
            'recent_sessions' => $recent_sessions
        )
    );
    
    echo json_encode($response);
    
} catch(Exception $e) {
    echo json_encode(array(
        'success' => false,
        'message' => 'Error fetching user statistics: ' . $e->getMessage()
    ));
}

$conn->close();
?>