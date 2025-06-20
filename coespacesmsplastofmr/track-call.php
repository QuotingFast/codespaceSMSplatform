<?php
require_once('includes/config.php');

header('Content-Type: application/json');

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);
$lead_id = isset($input['leadId']) ? preg_replace('/\D/', '', $input['leadId']) : '';

if (!$lead_id) {
    echo json_encode(['success' => false, 'message' => 'Missing lead ID']);
    exit;
}

// Log the call click in database
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

try {
    $stmt = $conn->prepare("UPDATE leads SET call_clicks = call_clicks + 1, last_call_click = NOW() WHERE id = ?");
    $stmt->bind_param("s", $lead_id);
    $stmt->execute();
    $stmt->close();
    
    echo json_encode(['success' => true, 'message' => 'Call tracked successfully']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error tracking call: ' . $e->getMessage()]);
} finally {
    $conn->close();
}
?>