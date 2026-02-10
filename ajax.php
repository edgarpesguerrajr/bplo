<?php
session_start();
require_once 'config.php'; // Required for database connection

// Check if the user is logged in
if (!isset($_SESSION['role'])) {
    echo json_encode(['error' => 'Unauthorized access.']);
    exit;
}

// Get the action from the request
$action = isset($_GET['action']) ? $_GET['action'] : null;

if ($action === 'fetch_user_info') {
    // Example: Fetch user information
    $user_id = $_SESSION['email'];
    
    $query = $conn->prepare("SELECT name, email, role FROM users WHERE email = ?");
    $query->bind_param('s', $user_id);
    $query->execute();
    
    $result = $query->get_result();
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        echo json_encode(['success' => true, 'data' => $user]);
    } else {
        echo json_encode(['success' => false, 'error' => 'User not found.']);
    }
} elseif ($action === 'update_document_status') {
    // Example: Update document status
    $document_id = $_POST['document_id'];
    $status = $_POST['status'];

    $query = $conn->prepare("UPDATE documents SET status = ? WHERE id = ?");
    $query->bind_param('si', $status, $document_id);
    if ($query->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to update document.']);
    }
} else {
    // Unknown action
    echo json_encode(['error' => 'Invalid action.']);
}
?>