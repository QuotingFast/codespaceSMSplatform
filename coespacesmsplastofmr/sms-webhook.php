<?php
require_once('includes/config.php');

// Get POST data from Twilio (or other SMS provider)
$from = $_POST['From'] ?? '';
$body = $_POST['Body'] ?? '';
$message_sid = $_POST['MessageSid'] ?? '';

// Log all incoming messages
$log_file = fopen('sms_log.txt', 'a');
fwrite($log_file, date('Y-m-d H:i:s') . " | From: $from | Body: $body | SID: $message_sid\n");
fclose($log_file);

// Process opt-outs
if (trim(strtoupper($body)) === 'STOP') {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($conn->connect_error) {
        error_log("Database connection failed: " . $conn->connect_error);
        http_response_code(500);
        exit;
    }
    
    // Clean the phone number for matching
    $clean_phone = preg_replace('/\D/', '', $from);
    
    // Mark the user as opted out
    $stmt = $conn->prepare("UPDATE leads SET opted_out = 1, opted_out_date = NOW() WHERE phone = ? OR phone = ?");
    $stmt->bind_param("ss", $clean_phone, $from);
    $stmt->execute();
    $stmt->close();
    
    // Optionally, respond to confirm opt-out
    header('Content-Type: text/xml');
    echo '<?xml version="1.0" encoding="UTF-8"?>';
    echo '<Response><Message>You have successfully opted out of messages from QuotingFast.io. You will receive no further messages.</Message></Response>';
    
    $conn->close();
} else {
    // For any non-STOP message, provide a default response
    header('Content-Type: text/xml');
    echo '<?xml version="1.0" encoding="UTF-8"?>';
    echo '<Response><Message>Thank you for your message. For questions about your insurance quote, please call us at 1-844-511-7954.</Message></Response>';
}
?>