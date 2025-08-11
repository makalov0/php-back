<?php
// login.php
session_start();
include 'connect_db.php';

// Enable CORS for Flutter app
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Only POST method allowed']);
    exit();
}

if (isset($_POST['login'])) {
    $username_or_email = trim($_POST['username_or_email']);
    $password = $_POST['password'];

    // Validate input
    if (empty($username_or_email) || empty($password)) {
        echo json_encode(['success' => false, 'message' => 'Username/email and password are required']);
        exit();
    }

    try {
        // Check if user exists by username or email
        $stmt = $conn->prepare("SELECT id, username, email, password FROM users WHERE username = ? OR email = ?");
        $stmt->bind_param("ss", $username_or_email, $username_or_email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $row = $result->fetch_assoc();

            // Verify password
            if (password_verify($password, $row['password'])) {
                $_SESSION['id'] = $row['id'];
                $_SESSION['username'] = $row['username'];
                $_SESSION['email'] = $row['email'];

                echo json_encode([
                    'success' => true,
                    'message' => 'Login successful',
                    'user' => [
                        'id' => $row['id'],
                        'username' => $row['username'],
                        'email' => $row['email']
                    ]
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Invalid username/email or password']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid username/email or password']);
        }

        $stmt->close();
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }

} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}

$conn->close();
?>