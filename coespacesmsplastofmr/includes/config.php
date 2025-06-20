<?php
// Include configuration
$include_path = 'includes/config.php';
if (file_exists($include_path)) {
    require_once($include_path);
} else {
    // Fallback to direct DB config if includes/config.php doesn't exist
    define('DB_HOST', 'db5017900795.hosting-data.io');
    define('DB_USER', 'dbu5548463');
    define('DB_PASS', 'Qf9544201788$');
    define('DB_NAME', 'dbs14255734');
}

// Simple security check
$authorized = false;

// Password check
$password = isset($_POST['password']) ? $_POST['password'] : (isset($_GET['p']) ? $_GET['p'] : '');
if ($password === 'quoting2025') {
    $authorized = true;
    setcookie('qf_auth', md5('quoting2025' . 'salt'), time() + 86400, '/');
} elseif (isset($_COOKIE['qf_auth']) && $_COOKIE['qf_auth'] === md5('quoting2025' . 'salt')) {
    $authorized = true;
}

// If not authorized, show login form
if (!$authorized) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Dashboard Login | QuotingFast.io</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    </head>
    <body class="bg-light">
        <div class="container mt-5">
            <div class="row justify-content-center">
                <div class="col-md-6">
                    <div class="card shadow">
                        <div class="card-header bg-primary text-white">
                            <h4 class="mb-0">Dashboard Login</h4>
                        </div>
                        <div class="card-body">
                            <form method="post" action="">
                                <div class="mb-3">
                                    <label for="password" class="form-label">Password</label>
                                    <input type="password" class="form-control" id="password" name="password" required>
                                </div>
                                <button type="submit" class="btn btn-primary">Login</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Show basic dashboard header first
echo '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | QuotingFast.io</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container-fluid py-4">
        <div class="row mb-4">
            <div class="col">
                <h1>QuotingFast.io Dashboard</h1>
                <p class="text-muted">Lead Management System</p>
            </div>
            <div class="col-auto">
                <a href="dashboard.php" class="btn btn-outline-primary me-2">Refresh</a>
                <a href="/" class="btn btn-primary">Home</a>
            </div>
        </div>';

// Try database connection
try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        echo '<div class="alert alert-danger">Database connection failed: ' . $conn->connect_error . '</div>';
    } else {
        echo '<div class="alert alert-success">Database connected successfully!</div>';
        
        // Check if leads table exists and create it if it doesn't
        $table_exists = $conn->query("SHOW TABLES LIKE 'leads'");
        if ($table_exists->num_rows == 0) {
            echo '<div class="alert alert-warning">Creating leads table...</div>';
            
            // Create leads table
            $create_sql = "CREATE TABLE leads (
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
            )";
            
            if ($conn->query($create_sql) === TRUE) {
                echo '<div class="alert alert-success">Leads table created successfully!</div>';
            } else {
                echo '<div class="alert alert-danger">Error creating leads table: ' . $conn->error . '</div>';
            }
        }
        
        // Create webhook_logs table if it doesn't exist
        $table_exists = $conn->query("SHOW TABLES LIKE 'webhook_logs'");
        if ($table_exists->num_rows == 0) {
            $create_sql = "CREATE TABLE webhook_logs (
                id INT(11) NOT NULL AUTO_INCREMENT,
                data TEXT,
                created_at DATETIME DEFAULT NULL,
                PRIMARY KEY (id)
            )";
            
            if ($conn->query($create_sql) === TRUE) {
                echo '<div class="alert alert-success">Webhook logs table created!</div>';
            }
        }
        
        // Basic stats
        $stats = [
            'total_leads' => 0,
            'leads_today' => 0,
            'webhook_logs' => 0
        ];
        
        // Count leads
        $result = $conn->query("SELECT COUNT(*) as total FROM leads");
        if ($result) {
            $row = $result->fetch_assoc();
            $stats['total_leads'] = $row['total'];
        }
        
        // Today's leads
        $today = date('Y-m-d');
        $result = $conn->query("SELECT COUNT(*) as total FROM leads WHERE DATE(created_at) = '$today'");
        if ($result) {
