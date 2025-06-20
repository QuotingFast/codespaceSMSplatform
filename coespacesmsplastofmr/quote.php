<?php
require_once('includes/config.php');
require_once('includes/functions.php');

// Process the Lead ID
$lead_id = isset($_GET['id']) ? preg_replace('/\D/', '', $_GET['id']) : '';
if(!$lead_id || strlen($lead_id) != 6) {
    header("Location: /");
    exit("Invalid lead ID");
}

// Get lead data
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    error_log("Connection failed: " . $conn->connect_error);
    die("We're experiencing technical difficulties. Please try again later.");
}

// Fetch lead information
$stmt = $conn->prepare("SELECT * FROM leads WHERE id = ?");
$stmt->bind_param("s", $lead_id);
$stmt->execute();
$result = $stmt->get_result();
$lead = $result->fetch_assoc();
$stmt->close();

// Track this page view
$stmt = $conn->prepare("UPDATE leads SET page_views = page_views + 1, last_viewed = NOW() WHERE id = ?");
$stmt->bind_param("s", $lead_id);
$stmt->execute();
$stmt->close();
$conn->close();

if (!$lead) {
    header("Location: /");
    exit("Lead not found");
}

// Parse lead data
$data = json_decode($lead['data'], true);
$contact = isset($data['contact']) ? $data['contact'] : [];
$name = isset($contact['first_name']) ? htmlspecialchars($contact['first_name']) : 'there';
$drivers = isset($data['data']['drivers']) && is_array($data['data']['drivers']) ? $data['data']['drivers'] : [];
$vehicles = isset($data['data']['vehicles']) && is_array($data['data']['vehicles']) ? $data['data']['vehicles'] : [];

// Check for DUI violations to determine routing
$has_dui = false;
$has_insurance = false;
$is_allstate = false;

// Check if any driver has DUI violations
if (!empty($drivers)) {
    foreach ($drivers as $driver) {
        if (!empty($driver['major_violations'])) {
            foreach ($driver['major_violations'] as $violation) {
                if (stripos($violation['description'], 'drunk') !== false || 
                    stripos($violation['description'], 'dui') !== false || 
                    stripos($violation['description'], 'alcohol') !== false) {
                    $has_dui = true;
                    break 2; // Break both loops once DUI found
                }
            }
        }
    }
}

// Check current insurance
if (isset($data['data']['current_policy']) && !empty($data['data']['current_policy'])) {
    $has_insurance = true;
    // Check if Allstate
    if (isset($data['data']['current_policy']['company']) && 
        stripos($data['data']['current_policy']['company'], 'allstate') !== false) {
        $is_allstate = true;
    }
}

// Address formatting
$address = [];
if(isset($contact['address'])) $address[] = htmlspecialchars($contact['address']);
if(isset($contact['address2']) && !empty($contact['address2'])) $address[] = htmlspecialchars($contact['address2']);
if(isset($contact['city'])) $address[] = htmlspecialchars($contact['city']);
if(isset($contact['state'])) $address[] = htmlspecialchars($contact['state']);
if(isset($contact['zip_code'])) $address[] = htmlspecialchars($contact['zip_code']);
$address_str = implode(', ', $address);

// Generate common discounts if not present
$discounts = ['Safe Driver', 'Multi-Car', 'Homeowner', 'Good Student', 'Defensive Driver', 'Paperless Billing', 'Auto-Pay'];
if (count($vehicles) > 1) $discounts[] = 'Multi-Vehicle';
if (count($drivers) > 1) $discounts[] = 'Multi-Driver';

// Shuffle discounts to make them appear personalized
shuffle($discounts);
$discounts = array_slice($discounts, 0, 5); // Show just 5 random ones

// Get the user's state for Ringba tracking
$user_state = isset($contact['state']) ? $contact['state'] : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Personalized Insurance Quote | QuotingFast.io</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/css/styles.css">
    <link rel="icon" href="/favicon.ico">
    <style>
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
        body { background-color: #f8f9fa; color: #2c3e50; }
        .quote-container { max-width: 800px; margin: 0 auto; background: #fff; border-radius: 15px; box-shadow: 0 8px 30px rgba(0,0,0,0.1); padding: 2rem; }
        .lead-id-badge { background: #e9f2fe; color: #0d6efd; border-radius: 30px; padding: 0.3rem 1rem; display: inline-block; font-weight: 600; }
        .pending-box { background: #fff8e6; border-left: 5px solid #ffc107; padding: 1.5rem; margin: 2rem 0; border-radius: 8px; }
        .call-btn { background: #28a745; color: #fff; font-size: 1.4rem; border-radius: 50px; padding: 1rem 2.5rem; box-shadow: 0 5px 15px rgba(40,167,69,0.3); transition: all 0.3s; animation: pulse 2s infinite; border: none; }
        .call-btn:hover { background: #218838; transform: translateY(-2px); box-shadow: 0 8px 20px rgba(40,167,69,0.4); color: white; }
        .section-title { color: #0d6efd; border-bottom: 2px solid #e9f2fe; padding-bottom: 0.5rem; margin-top: 2rem; font-weight: 600; }
        .vehicle-card { background: #f8f9fa; border-radius: 10px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); padding: 1.2rem; margin-bottom: 1rem; border-left: 4px solid #0d6efd; }
        .driver-card { background: #f5f9ff; border-radius: 10px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); padding: 1rem; margin-bottom: 0.8rem; border-left: 4px solid #20c997; }
        .discount-badge { background: #e9f7f0; color: #20c997; padding: 0.5rem 1rem; border-radius: 50px; margin-bottom: 0.8rem; display: inline-block; font-weight: 500; }
        .top-banner { background: linear-gradient(135deg, #0d6efd, #0099ff); color: white; padding: 2rem 0; margin-bottom: 2rem; }
        .discount-alert { color: #d63384; font-weight: bold; }
        .quote-details { margin: 2rem 0; }
        .discount-box { position: relative; }
        .discount-star { position: absolute; top: -10px; left: -10px; background: #ffc107; color: #212529; padding: 0.3rem 0.6rem; border-radius: 50px; font-weight: bold; font-size: 0.8rem; transform: rotate(-15deg); }
        .highlight-section { border: 2px dashed #ffc107; padding: 1rem; margin: 1.5rem 0; border-radius: 10px; background: #fffdf5; }
        .expiry-timer { font-size: 1.1rem; font-weight: bold; color: #dc3545; margin: 1rem 0; }
        .logo-container { margin: 1rem 0; }
        .quote-logo { max-height: 70px; }
    </style>
</head>
<body>
<div class="top-banner">
    <div class="container text-center">
        <div class="logo-container">
            <img src="/images/logo-white.png" class="quote-logo" alt="QuotingFast.io Logo">
        </div>
        <h1 class="display-6 fw-bold">Your Personalized Insurance Quote</h1>
        <div class="lead-id-badge mt-2">Quote ID: <?= $lead_id ?></div>
    </div>
</div>

<div class="container mb-5">
    <div class="quote-container shadow">
        <div class="text-center">
            <h2 class="fw-bold mb-2">Hi <?= $name ?>, your quote is ready!</h2>
            <p class="lead mb-3">Review your personalized insurance options below</p>
            <div class="expiry-timer">
                Your quote expires in: <span id="countdown">24h 0m 0s</span>
            </div>
        </div>
        
        <div class="quote-details">
            <h3 class="section-title">Home Address</h3>
            <p><?= !empty($address_str) ? $address_str : 'Address information pending' ?></p>
            
            <h3 class="section-title">Drivers</h3>
            <div class="row">
                <?php if(count($drivers)): ?>
                    <?php foreach($drivers as $d): ?>
                    <div class="col-md-6 mb-2">
                        <div class="driver-card">
                            <strong><?= htmlspecialchars($d['first_name'] . ' ' . $d['last_name']) ?></strong>
                            <?php if(!empty($d['birth_date'])): ?>
                                <div>DOB: <?= htmlspecialchars($d['birth_date']) ?></div>
                            <?php endif; ?>
                            <div>License State: <?= htmlspecialchars($d['license_state']) ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="col-12">No driver information available</div>
                <?php endif; ?>
            </div>
            
            <h3 class="section-title">Vehicles</h3>
            <div class="row">
                <?php if(count($vehicles)): ?>
                    <?php foreach($vehicles as $v): ?>
                    <div class="col-md-6 mb-3">
                        <div class="vehicle-card">
                            <h4><?= htmlspecialchars($v['year'] . ' ' . $v['make'] . ' ' . $v['model']) ?></h4>
                            <?php if(!empty($v['submodel'])): ?>
                                <div><?= htmlspecialchars($v['submodel']) ?></div>
                            <?php endif; ?>
                            <?php if(!empty($v['vin'])): ?>
                                <div class="small text-muted">VIN: <?= htmlspecialchars($v['vin']) ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="col-12">No vehicle information available</div>
                <?php endif; ?>
            </div>
            
            <div class="highlight-section">
                <h3 class="section-title">Discounts You May Qualify For</h3>
                <div class="row">
                    <?php foreach($discounts as $index => $discount): ?>
                    <div class="col-md-6 mb-2">
                        <div class="discount-box">
                            <?php if($index == 0): ?>
                                <span class="discount-star">Top!</span>
                            <?php endif; ?>
                            <div class="discount-badge">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-check-circle-fill me-2" viewBox="0 0 16 16">
                                    <path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0zm-3.97-3.03a.75.75 0 0 0-1.08.022L7.477 9.417 5.384 7.323a.75.75 0 0 0-1.06 1.06L6.97 11.03a.75.75 0 0 0 1.079-.02l3.992-4.99a.75.75 0 0 0-.01-1.05z"/>
                                </svg>
                                <?= htmlspecialchars($discount) ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="text-center mt-3">
                    <div class="discount-alert">Additional discounts available! Call now to verify eligibility.</div>
                </div>
            </div>
        </div>
        
        <div class="pending-box">
            <h4 class="fw-bold mb-2">Your Quote is Ready for Final Approval!</h4>
            <p>To finalize your quote and confirm all available discounts, a licensed agent needs to verify a few details. <strong>Call now to maximize your savings!</strong></p>
        </div>
        
        <div class="text-center mt-4">
            <p class="fs-5 fw-bold text-success mb-3">Speak with a licensed agent to finalize your quote</p>
            <button id="main-call-btn" onclick="trackCall()" class="call-btn mb-3 call-link">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" class="bi bi-telephone-fill me-2" viewBox="0 0 16 16">
                    <path fill-rule="evenodd" d="M1.885.511a1.745 1.745 0 0 1 2.61.163L6.29 2.98c.329.423.445.974.315 1.494l-.547 2.19a.678.678 0 0 0 .178.643l2.457 2.457a.678.678 0 0 0 .644.178l2.189-.547a1.745 1.745 0 0 1 1.494.315l2.306 1.794c.829.645.905 1.87.163 2.611l-1.034 1.034c-.74.74-1.846 1.065-2.877.702a18.634 18.634 0 0 1-7.01-4.42 18.634 18.634 0 0 1-4.42-7.009c-.362-1.03-.037-2.137.703-2.877L1.885.511z"/>
                </svg>
                Call Now
            </button>
            <p class="text-muted">A licensed agent is ready to assist you</p>
        </div>
    </div>
    
    <footer class="text-center mt-4">
        <p class="small text-muted">&copy; <?= date('Y') ?> QuotingFast.io &middot; Quote ID: <?= $lead_id ?></p>
    </footer>
</div>

<script>
// Set user variables for Ringba routing
const userInsuranceStatus = "<?= $has_insurance ? 'yes' : 'no' ?>";
const userProviderStatus = "<?= $is_allstate ? 'allstate' : 'other' ?>";
const userDUIStatus = "<?= $has_dui ? 'yes' : 'no' ?>";
const userState = "<?= $user_state ?>";
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="/js/ringba-integration.js"></script>
<script>
// Track when user clicks Call button
function trackCall() {
    try {
        // Log call click event
        fetch('/track-call.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({leadId: '<?= $lead_id ?>'})
        });
    } catch(e) {
        console.error("Tracking error:", e);
    }
    
    // Initialize Ringba routing (the phone link href is dynamically updated by Ringba)
    finalizeRingbaIntegration();
    return true;
}

// Countdown timer logic
document.addEventListener('DOMContentLoaded', function() {
    // Set countdown to 24 hours from now
    var countDownDate = new Date();
    countDownDate.setHours(countDownDate.getHours() + 24);
    
    var x = setInterval(function() {
        var now = new Date().getTime();
        var distance = countDownDate - now;
        
        var hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
        var minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
        var seconds = Math.floor((distance % (1000 * 60)) / 1000);
        
        var timerElement = document.getElementById("countdown");
        if (timerElement) {
            timerElement.innerHTML = hours + "h " + minutes + "m " + seconds + "s";
        }
        
        if (distance < 0) {
            clearInterval(x);
            if (timerElement) {
                timerElement.innerHTML = "EXPIRED";
            }
        }
    }, 1000);
});
</script>
</body>
</html>