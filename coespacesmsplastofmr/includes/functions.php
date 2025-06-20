<?php
/**
 * Generate a normalized phone number
 */
function formatPhoneNumber($phone) {
    // Remove all non-digit characters
    $digits = preg_replace('/\D/', '', $phone);
    
    // Format the phone number
    if (strlen($digits) == 10) {
        return preg_replace("/^(\d{3})(\d{3})(\d{4})$/", "($1) $2-$3", $digits);
    } else {
        return $phone; // Return original if not 10 digits
    }
}

/**
 * Create a 6-digit Lead ID for new leads
 */
function generateLeadId($conn) {
    $attempts = 0;
    $max_attempts = 10;
    
    do {
        // Generate a random 6-digit number
        $lead_id = rand(100000, 999999);
        
        // Check if this ID already exists
        $stmt = $conn->prepare("SELECT id FROM leads WHERE id = ?");
        $stmt->bind_param("s", $lead_id);
        $stmt->execute();
        $stmt->store_result();
        $exists = $stmt->num_rows > 0;
        $stmt->close();
        
        $attempts++;
    } while ($exists && $attempts < $max_attempts);
    
    return $lead_id;
}

/**
 * Send SMS using Twilio
 */
function sendSms($to, $body) {
    try {
        $twilio_sid = TWILIO_SID;
        $twilio_token = TWILIO_TOKEN;
        $from = TWILIO_FROM;
        $url = "https://api.twilio.com/2010-04-01/Accounts/{$twilio_sid}/Messages.json";
        
        $data = http_build_query([
            'From' => $from,
            'To' => $to,
            'Body' => $body
        ]);
        
        $options = [
            'http' => [
                'header'  => "Authorization: Basic " . base64_encode("$twilio_sid:$twilio_token") . "\r\n" .
                            "Content-Type: application/x-www-form-urlencoded\r\n",
                'method'  => 'POST',
                'content' => $data,
                'ignore_errors' => true
            ],
        ];
        
        $context = stream_context_create($options);
        $result = file_get_contents($url, false, $context);
        
        // Log result for debugging
        error_log("Twilio API response: " . $result);
        
        // Check if the request was successful
        $response_data = json_decode($result, true);
        if (isset($response_data['sid'])) {
            return true;
        } else {
            error_log("Twilio error: " . (isset($response_data['message']) ? $response_data['message'] : 'Unknown error'));
            return false;
        }
    } catch (Exception $e) {
        error_log("SMS Exception: " . $e->getMessage());
        return false;
    }
}

/**
 * Check if a phone number has opted out
 */
function isOptedOut($conn, $phone) {
    $clean_phone = preg_replace('/\D/', '', $phone);
    
    $stmt = $conn->prepare("SELECT id FROM leads WHERE (phone = ? OR phone = ?) AND opted_out = 1");
    $stmt->bind_param("ss", $clean_phone, $phone);
    $stmt->execute();
    $stmt->store_result();
    $is_opted_out = $stmt->num_rows > 0;
    $stmt->close();
    
    return $is_opted_out;
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
?>