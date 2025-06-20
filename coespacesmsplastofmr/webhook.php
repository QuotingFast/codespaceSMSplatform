<?php
// Include configuration or use direct DB config if file doesn't exist
$include_path = 'includes/config.php';
if (file_exists($include_path)) {
    require_once($include_path);
} else {
    define('DB_HOST', 'db5017900795.hosting-data.io');
    define('DB_USER', 'dbu5548463');
    define('DB_PASS', 'Qf9544201788$');
    define('DB_NAME', 'dbs14255734');
    define('SITE_URL', 'https://quotingfast.io');
}

// Check if includes/functions.php exists and include it
$functions_path = 'includes/functions.php';
if (file_exists($functions_path)) {
    require_once($functions_path);
}

// Get POST data (expects JSON)
$input = json_decode(file_get_contents('php://input'), true);

// Log the raw request for debugging
$debug_log = fopen('webhook_debug.log', 'a');
fwrite($debug_log, date('Y-m-d H:i:s') . " - Received webhook\n");
fwrite($debug_log, json_encode($input, JSON_PRETTY_PRINT) . "\n\n");
fclose($debug_log);

// Validate webhook data
if (!$input || empty($input)) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing payload data']);
    exit;
}

// Connect to database
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed: ' . $conn->connect_error]);
    exit;
}

// Create tables if they don't exist
$tables_sql = [
    'leads' => "CREATE TABLE IF NOT EXISTS leads (
        id INT(11) NOT NULL,
        name VARCHAR(255) DEFAULT NULL,
        phone VARCHAR(20) DEFAULT NULL,
        email VARCHAR(255) DEFAULT NULL,
        data TEXT,
        created_at DATETIME DEFAULT NULL,
        last_viewed DATETIME DEFAULT NULL,
        last_call_click DATETIME DEFAULT NULL,
        last_sms_sent DATETIME DEFAULT NULL,
        page_views INT(11) DEFAULT 0,
        call_clicks INT(11) DEFAULT 0,
        sms_count INT(11) DEFAULT 0,
        has_dui TINYINT(1) DEFAULT 0,
        has_insurance TINYINT(1) DEFAULT 0,
        is_allstate TINYINT(1) DEFAULT 0,
        opted_out TINYINT(1) DEFAULT 0,
        PRIMARY KEY (id)
    )",
    'webhook_logs' => "CREATE TABLE IF NOT EXISTS webhook_logs (
        id INT(11) NOT NULL AUTO_INCREMENT,
        data TEXT,
        created_at DATETIME DEFAULT NULL,
        PRIMARY KEY (id)
    )"
];

foreach ($tables_sql as $table => $sql) {
    $conn->query($sql);
}

// Log the raw webhook data
$raw_data = json_encode($input);
$stmt = $conn->prepare("INSERT INTO webhook_logs (data, created_at) VALUES (?, NOW())");
$stmt->bind_param("s", $raw_data);
$stmt->execute();
$log_id = $stmt->insert_id;
$stmt->close();

try {
    // Extract lead information
    $contact = isset($input['contact']) ? $input['contact'] : [];
    
    // Get phone and check format
    $phone = isset($contact['phone']) ? $contact['phone'] : '';
    if (empty($phone)) {
        throw new Exception("Missing required phone number");
    }
    
    // Format phone number properly (add +1 if missing)
    if (substr($phone, 0, 1) !== '+') {
        $phone = '+1' . preg_replace('/\D/', '', $phone);
    }
    
    // Get name fields
    $first_name = isset($contact['first_name']) ? $contact['first_name'] : '';
    $last_name = isset($contact['last_name']) ? $contact['last_name'] : '';
    $name = trim($first_name . ' ' . $last_name);
    $email = isset($contact['email']) ? $contact['email'] : '';
    
    // Generate a unique 6-digit lead ID
    $lead_id = rand(100000, 999999);
    $check = $conn->query("SELECT id FROM leads WHERE id = $lead_id");
    while ($check->num_rows > 0) {
        $lead_id = rand(100000, 999999);
        $check = $conn->query("SELECT id FROM leads WHERE id = $lead_id");
    }
    
    // Store lead in database
    $stmt = $conn->prepare("INSERT INTO leads (id, name, phone, email, data, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
    $stmt->bind_param("issss", $lead_id, $name, $phone, $email, $raw_data);
    $stmt->execute();
    $stmt->close();
    
    // Generate quote link
    $quote_link = SITE_URL . '/' . $lead_id;
    
    // Compose SMS message
    $vehicle = '';
    if (isset($input['data']['vehicles'][0]['year']) && isset($input['data']['vehicles'][0]['make'])) {
        $vehicle = $input['data']['vehicles'][0]['year'] . ' ' . $input['data']['vehicles'][0]['make'];
    }
    
    if (!empty($first_name) && !empty($vehicle)) {
        $sms_body = "Hi $first_name, your quote for your $vehicle is ready! See savings: $quote_link Reply STOP to opt out.";
    } elseif (!empty($first_name)) {
        $sms_body = "Hi $first_name, your insurance quote is ready! See your savings: $quote_link Reply STOP to opt out.";
    } else {
        $sms_body = "Your insurance quote is ready! See your savings: $quote_link Reply STOP to opt out.";
    }
    
    // Ensure SMS is under 160 characters
    if (strlen($sms_body) > 160) {
        $sms_body = substr($sms_body, 0, 156) . "...";
    }
    
    // Send SMS if function exists
    $sms_sent = true;
    if (function_exists('sendSms')) {
        $sms_sent = sendSms($phone, $sms_body);
    }
    
    // Update SMS count
    $stmt = $conn->prepare("UPDATE leads SET sms_count = sms_count + 1, last_sms_sent = NOW() WHERE id = ?");
    $stmt->bind_param("i", $lead_id);
    $stmt->execute();
    $stmt->close();
    
    // Respond to webhook
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'lead_id' => $lead_id,
        'quote_link' => $quote_link,
        'message' => $sms_body
    ]);
    
} catch (Exception $e) {
    // Log error
    error_log("Webhook error: " . $e->getMessage());
    
    // Respond with error
    http_response_code(500);
    echo json_encode([
        'error' => $e->getMessage()
    ]);
} finally {
    $conn->close();
}

// Helper function if not already defined
if (!function_exists('sendSms')) {
    function sendSms($to, $body) {
        // Just log for now since we don't have Twilio credentials
        error_log("Would send SMS to $to: $body");
        return true;
    }
}
?>