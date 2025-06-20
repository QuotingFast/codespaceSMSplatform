<?php
// Simple webhook debugging tool
$input = file_get_contents('php://input');
file_put_contents('webhook-log.txt', date('Y-m-d H:i:s') . " - Received webhook:\n" . $input . "\n\n", FILE_APPEND);
echo json_encode(['status' => 'logged']);
?>