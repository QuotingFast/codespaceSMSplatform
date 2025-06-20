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
            $row = $result->fetch_assoc();
            $stats['leads_today'] = $row['total'];
        }
        
        // Count webhook logs
        $result = $conn->query("SELECT COUNT(*) as total FROM webhook_logs");
        if ($result) {
            $row = $result->fetch_assoc();
            $stats['webhook_logs'] = $row['total'];
        }
        
        // Display stats
        echo '<div class="row mb-4">
                <div class="col-md-4 mb-3">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">Total Leads</h5>
                            <h2>' . number_format($stats['total_leads']) . '</h2>
                            <p class="text-success mb-0">+' . number_format($stats['leads_today']) . ' today</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">Webhook Logs</h5>
                            <h2>' . number_format($stats['webhook_logs']) . '</h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">Current Time</h5>
                            <h2>' . date('H:i:s') . '</h2>
                            <p class="text-muted mb-0">' . date('Y-m-d') . '</p>
                        </div>
                    </div>
                </div>
            </div>';
        
        // Display recent leads if they exist
        $result = $conn->query("SELECT * FROM leads ORDER BY created_at DESC LIMIT 10");
        if ($result && $result->num_rows > 0) {
            echo '<div class="card mb-4">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Recent Leads</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Name</th>
                                        <th>Phone</th>
                                        <th>Created</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>';
            
            while ($lead = $result->fetch_assoc()) {
                echo '<tr>
                        <td><a href="/' . $lead['id'] . '" target="_blank">' . $lead['id'] . '</a></td>
                        <td>' . htmlspecialchars($lead['name'] ?? '') . '</td>
                        <td>' . htmlspecialchars($lead['phone'] ?? '') . '</td>
                        <td>' . (isset($lead['created_at']) ? date('m/d/Y H:i', strtotime($lead['created_at'])) : '') . '</td>
                        <td>
                            <a href="/quote.php?id=' . $lead['id'] . '" class="btn btn-sm btn-outline-primary" target="_blank">View</a>
                        </td>
                      </tr>';
            }
            
            echo '</tbody>
                </table>
            </div>
            </div>
            </div>';
        } else {
            echo '<div class="alert alert-info">No leads found in database yet. Send some test leads to see them here.</div>';
        }
        
        // Check webhook logs
        $result = $conn->query("SELECT * FROM webhook_logs ORDER BY created_at DESC LIMIT 5");
        if ($result && $result->num_rows > 0) {
            echo '<div class="card mb-4">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Recent Webhook Logs</h5>
                    </div>
                    <div class="card-body">
                        <div class="list-group">';
            
            while ($log = $result->fetch_assoc()) {
                echo '<div class="list-group-item">
                        <div class="d-flex w-100 justify-content-between">
                            <h5 class="mb-1">Log ID: ' . $log['id'] . '</h5>
                            <small>' . (isset($log['created_at']) ? date('m/d/Y H:i:s', strtotime($log['created_at'])) : '') . '</small>
                        </div>
                        <p class="mb-1">Data received</p>
                      </div>';
            }
            
            echo '</div>
                </div>
                </div>';
        }
        
        // Test button section
        echo '<div class="card mb-4">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Tools</h5>
                </div>
                <div class="card-body">
                    <p>Use these tools to test your system:</p>
                    <a href="htaccess-test" class="btn btn-outline-primary me-2">Test URL Rewriting</a>
                    <a href="error-check.php" class="btn btn-outline-primary me-2">Error Check</a>
                    <a href="/123456" class="btn btn-outline-primary">Test Quote Page</a>
                </div>
              </div>';
        
        $conn->close();
    }
} catch (Exception $e) {
    echo '<div class="alert alert-danger">Error: ' . $e->getMessage() . '</div>';
}

echo '</div>
    <footer class="bg-light py-4 text-center mt-4">
        <p class="mb-0 text-muted">&copy; ' . date('Y') . ' QuotingFast.io. All rights reserved.</p>
    </footer>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>';
?>