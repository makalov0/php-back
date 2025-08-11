<?php
// google_login.php
header('Content-Type: application/json');
include 'db_connect.php'; // Your DB connection file

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['id_token'])) {
    $id_token = $_POST['id_token'];

    // Verify the ID token with Google
    $verify_url = "https://oauth2.googleapis.com/tokeninfo?id_token=" . urlencode($id_token);
    $response = file_get_contents($verify_url);
    if ($response === false) {
        echo json_encode(['success' => false, 'message' => 'Invalid ID token']);
        exit;
    }

    $token_info = json_decode($response, true);

    // Validate client ID (replace with your actual client ID)
    $client_id = 'YOUR_GOOGLE_CLIENT_ID.apps.googleusercontent.com';
    if (!isset($token_info['aud']) || $token_info['aud'] !== $client_id) {
        echo json_encode(['success' => false, 'message' => 'Invalid client ID']);
        exit;
    }

    // Extract user info
    $email = $token_info['email'];
    $google_id = $token_info['sub'];
    $username = $token_info['name'] ?? '';

    // Check if user exists by google_id or email
    $stmt = $conn->prepare("SELECT * FROM users WHERE google_id = ? OR email = ?");
    $stmt->bind_param("ss", $google_id, $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        // Existing user
        $user = $result->fetch_assoc();

        // If google_id missing, update it (user registered without Google before)
        if (empty($user['google_id'])) {
            $update = $conn->prepare("UPDATE users SET google_id = ? WHERE id = ?");
            $update->bind_param("si", $google_id, $user['id']);
            $update->execute();
        }
    } else {
        // New user - insert record
        $insert = $conn->prepare("INSERT INTO users (username, email, google_id, password) VALUES (?, ?, ?, '')");
        $insert->bind_param("sss", $username, $email, $google_id);
        $insert->execute();

        $user_id = $insert->insert_id;
        $user = [
            'id' => $user_id,
            'username' => $username,
            'email' => $email,
        ];
    }

    echo json_encode(['success' => true, 'user' => $user]);
    exit;
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}
?>
