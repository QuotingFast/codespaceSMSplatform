<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>PHP Error Check</h1>";
echo "<p>PHP Version: " . phpversion() . "</p>";

// Try correct database connection
$db_host = 'db5017900795.hosting-data.io';
$db_user = 'dbu5548463';
$db_pass = 'Qf9544201788$';
$db_name = 'dbs14255734';

try {
    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
    if ($conn->connect_error) {
        echo "<p style='color:red'>Database connection failed: " . $conn->connect_error . "</p>";
    } else {
        echo "<p style='color:green'>Database connection successful!</p>";
        
        // Check tables
        $tables = ['leads', 'webhook_logs'];
        foreach ($tables as $table) {
            $result = $conn->query("SHOW TABLES LIKE '$table'");
            if ($result->num_rows > 0) {
                echo "<p style='color:green'>Table '$table' exists!</p>";
            } else {
                echo "<p style='color:red'>Table '$table' does not exist!</p>";
            }
        }
        
        // Create test lead for debugging
        $test_id = 123456;
        $check = $conn->query("SELECT id FROM leads WHERE id = $test_id");
        if ($check->num_rows == 0) {
            $sql = "INSERT INTO leads (id, name, phone, email, data, created_at) VALUES 
                   ($test_id, 'Test User', '+15551234567', 'test@example.com', '{\"contact\":{\"first_name\":\"Test\",\"last_name\":\"User\"}}', NOW())";
            if ($conn->query($sql) === TRUE) {
                echo "<p style='color:green'>Test lead created with ID: $test_id</p>";
            } else {
                echo "<p style='color:red'>Error creating test lead: " . $conn->error . "</p>";
            }
        } else {
            echo "<p style='color:blue'>Test lead already exists with ID: $test_id</p>";
        }
        
        // Check URL rewrite capability
        echo "<p>Testing URL rewriting: <a href='/htaccess-test'>/htaccess-test</a> (should work if .htaccess is configured correctly)</p>";
        
        // Test quote link
        echo "<p>Testing quote link: <a href='/123456'>/123456</a> (should display quote page)</p>";
        
        $conn->close();
    }
} catch (Exception $e) {
    echo "<p style='color:red'>Error: " . $e->getMessage() . "</p>";
}

// Check if .htaccess exists
if (file_exists('.htaccess')) {
    echo "<p style='color:green'>.htaccess file exists!</p>";
} else {
    echo "<p style='color:red'>.htaccess file does not exist!</p>";
}

// Check directory permissions
echo "<h2>Directory Permissions:</h2>";
$dirs = ['.', 'includes'];
foreach ($dirs as $dir) {
    if (is_dir($dir)) {
        echo "<p>$dir: " . substr(sprintf('%o', fileperms($dir)), -4) . "</p>";
    } else {
        echo "<p style='color:red'>Directory '$dir' not found!</p>";
    }
}

// Check if includes/config.php exists
if (file_exists('includes/config.php')) {
    echo "<p style='color:green'>includes/config.php exists!</p>";
} else {
    echo "<p style='color:red'>includes/config.php does not exist!</p>";
}

// Display server information
echo "<h2>Server Information:</h2>";
echo "<pre>";
echo "Server Software: " . $_SERVER['SERVER_SOFTWARE'] . "\n";
echo "Document Root: " . $_SERVER['DOCUMENT_ROOT'] . "\n";
echo "Script Filename: " . $_SERVER['SCRIPT_FILENAME'] . "\n";
echo "Request URI: " . $_SERVER['REQUEST_URI'] . "\n";
echo "</pre>";
?>