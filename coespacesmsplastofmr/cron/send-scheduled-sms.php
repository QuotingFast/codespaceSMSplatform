<?php
/**
 * Daily SMS Scheduler - Run this via cron job daily
 * Recommended: Set as a cron job to run every hour
 */
require_once('../includes/config.php');
require_once('../includes/functions.php');

// Connect to database
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get current timestamp
$current_time = date('Y-m-d H:i:s');

// 1. Find all leads that need SMS today
$stmt = $conn->prepare("
    SELECT s.id, s.lead_id, s.message_text, s.sms_type, l.phone, l.name, l.data 
    FROM scheduled_sms s
    JOIN leads l ON s.lead_id = l.id
    WHERE s.is_sent = 0 
    AND s.schedule_time <= ?
    AND l.opted_out = 0 
    ORDER BY s.schedule_time ASC
    LIMIT 50
");
$stmt->bind_param("s", $current_time);
$stmt->execute();
$result = $stmt->get_result();

$sent_count = 0;
$failed_count = 0;

while ($row = $result->fetch_assoc()) {
    // Skip if no phone number
    if (empty($row['phone'])) {
        markSmsAsFailed($conn, $row['id'], "No phone number");
        $failed_count++;
        continue;
    }
    
    // Skip if opted out (double check)
    if (isOptedOut($conn, $row['phone'])) {
        markSmsAsFailed($conn, $row['id'], "Opted out");
        $failed_count++;
        continue;
    }
    
    // Check if we have a pre-formatted message or need to generate one
    $message = $row['message_text'];
    
    // If message is empty, generate based on SMS type and lead data
    if (empty($message)) {
        $lead_data = json_decode($row['data'], true);
        $lead_name = $row['name'] ?? '';
        $first_name = '';
        
        // Extract first name from full name
        if (!empty($lead_name)) {
            $name_parts = explode(' ', $lead_name);
            $first_name = $name_parts[0];
        }
        
        // Generate message based on SMS type (day in sequence)
        $quote_link = SITE_URL . '/' . $row['lead_id'];
        $message = generateFollowupMessage($row['sms_type'], $first_name, $lead_data, $quote_link);
    }
    
    // Make sure message has opt-out notice
    if (stripos($message, 'STOP') === false) {
        $message .= " Reply STOP to opt out.";
    }
    
    // Ensure SMS is under 160 characters
    if (strlen($message) > 160) {
        $message = substr($message, 0, 156) . "...";
    }
    
    // Send SMS
    $sms_sent = sendSms($row['phone'], $message);
    
    if ($sms_sent) {
        // Mark as sent
        $update = $conn->prepare("
            UPDATE scheduled_sms 
            SET is_sent = 1, sent_at = NOW() 
            WHERE id = ?
        ");
        $update->bind_param("i", $row['id']);
        $update->execute();
        $update->close();
        
        // Log in SMS history
        $log = $conn->prepare("
            INSERT INTO sms_history (lead_id, sent_at, message_text, status)
            VALUES (?, NOW(), ?, 'sent')
        ");
        $log->bind_param("is", $row['lead_id'], $message);
        $log->execute();
        $log->close();
        
        // Update lead SMS count
        $update_lead = $conn->prepare("
            UPDATE leads 
            SET sms_count = sms_count + 1, last_sms_sent = NOW() 
            WHERE id = ?
        ");
        $update_lead->bind_param("i", $row['lead_id']);
        $update_lead->execute();
        $update_lead->close();
        
        $sent_count++;
    } else {
        markSmsAsFailed($conn, $row['id'], "SMS send failed");
        $failed_count++;
    }
    
    // Slight pause to avoid hitting rate limits
    usleep(200000); // 0.2 seconds
}

$stmt->close();

// Report results
echo "SMS Scheduler completed: {$sent_count} sent, {$failed_count} failed\n";

// Schedule next batch of SMS for new leads
createFollowupScheduleForNewLeads($conn);

$conn->close();

/**
 * Mark an SMS as failed
 */
function markSmsAsFailed($conn, $smsId, $reason) {
    $notes = "Failed: " . $reason;
    $stmt = $conn->prepare("UPDATE scheduled_sms SET notes = ? WHERE id = ?");
    $stmt->bind_param("si", $notes, $smsId);
    $stmt->execute();
    $stmt->close();
}

/**
 * Generate follow-up messages based on day in sequence
 */
function generateFollowupMessage($smsType, $firstName, $leadData, $quoteLink) {
    // Extract vehicle info if available
    $vehicle = '';
    if (isset($leadData['data']['vehicles'][0]['year']) && isset($leadData['data']['vehicles'][0]['make'])) {
        $vehicle = $leadData['data']['vehicles'][0]['year'] . ' ' . $leadData['data']['vehicles'][0]['make'];
    }
    
    $messages = [
        'day3' => !empty($firstName) ? 
            "Hi {$firstName}, missed your insurance quote? Review & claim savings: {$quoteLink} Reply STOP to opt out." : 
            "Missed your insurance quote? Review & claim savings: {$quoteLink} Reply STOP to opt out.",
            
        'day7' => !empty($firstName) ? 
            "{$firstName}, final step for your insurance quote! See your savings: {$quoteLink} Reply STOP to opt out." : 
            "Final step for your insurance quote! See your savings: {$quoteLink} Reply STOP to opt out.",
            
        'day10' => !empty($firstName) ? 
            "Hi {$firstName}, your quote is expiring soon. View your savings: {$quoteLink} Reply STOP to opt out." : 
            "Your quote is expiring soon. View your savings: {$quoteLink} Reply STOP to opt out.",
            
        'day14' => !empty($firstName) && !empty($vehicle) ? 
            "{$firstName}, still need insurance for your {$vehicle}? See your quote: {$quoteLink} Reply STOP to opt out." : 
            (!empty($firstName) ? 
                "{$firstName}, you may qualify for more savings. Review & claim: {$quoteLink} Reply STOP to opt out." : 
                "You may qualify for more savings. Review & claim: {$quoteLink} Reply STOP to opt out."
            ),
            
        'day21' => !empty($firstName) && !empty($vehicle) ? 
            "{$firstName}, still need insurance for your {$vehicle}? See your quote: {$quoteLink} Reply STOP to opt out." : 
            (!empty($firstName) ? 
                "Hi {$firstName}, unlock discounts! View your quote: {$quoteLink} Reply STOP to opt out." : 
                "Unlock insurance discounts! View your quote: {$quoteLink} Reply STOP to opt out."
            ),
            
        'day30' => !empty($firstName) && !empty($vehicle) ? 
            "Last chance, {$firstName}! Your discounts for your {$vehicle} expire today. See savings: {$quoteLink} Reply STOP to opt out." : 
            (!empty($firstName) ? 
                "Last chance, {$firstName}! Your discounts expire today. See savings: {$quoteLink} Reply STOP to opt out." : 
                "Last chance! Your insurance discounts expire today. See savings: {$quoteLink} Reply STOP to opt out."
            )
    ];
    
    // Default message if type not found
    if (!isset($messages[$smsType])) {
        return !empty($firstName) ? 
            "Hi {$firstName}, check your insurance quote: {$quoteLink} Reply STOP to opt out." : 
            "Check your insurance quote: {$quoteLink} Reply STOP to opt out.";
    }
    
    return $messages[$smsType];
}

/**
 * Create follow-up schedule for new leads without schedules
 */
function createFollowupScheduleForNewLeads($conn) {
    // Find leads without SMS schedules created in the last 3 days
    $stmt = $conn->prepare("
        SELECT l.id, l.created_at
        FROM leads l
        LEFT JOIN scheduled_sms s ON l.id = s.lead_id
        WHERE s.id IS NULL
        AND l.created_at > DATE_SUB(NOW(), INTERVAL 3 DAY)
        AND l.opted_out = 0
        LIMIT 50
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    
    $scheduled_count = 0;
    
    while ($lead = $result->fetch_assoc()) {
        $lead_id = $lead['id'];
        $created_at = $lead['created_at'];
        
        // Schedule follow-up SMS at days 3, 7, 10, 14, 21, and 30
        $schedules = [
            ['days' => 3, 'type' => 'day3'],
            ['days' => 7, 'type' => 'day7'],
            ['days' => 10, 'type' => 'day10'],
            ['days' => 14, 'type' => 'day14'],
            ['days' => 21, 'type' => 'day21'],
            ['days' => 30, 'type' => 'day30']
        ];
        
        foreach ($schedules as $schedule) {
            $schedule_time = date('Y-m-d H:i:s', strtotime($created_at . ' + ' . $schedule['days'] . ' days'));
            
            $insert = $conn->prepare("
                INSERT INTO scheduled_sms (lead_id, schedule_time, is_sent, sms_type)
                VALUES (?, ?, 0, ?)
            ");
            $insert->bind_param("iss", $lead_id, $schedule_time, $schedule['type']);
            $insert->execute();
            $insert->close();
            
            $scheduled_count++;
        }
    }
    
    $stmt->close();
    
    echo "Created {$scheduled_count} scheduled SMS for new leads\n";
}
?>