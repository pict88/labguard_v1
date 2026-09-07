<?php
// 1. Define the log file path
$logFile = '/home/exam/Desktop/SERVER/Logs.log';

// 2. Define expected parameters
$expectedParams = ['ip', 'module', 'action', 'status', 'password'];

$logDetails = [];

// 3. Loop through expected parameters and capture them
foreach ($expectedParams as $param) {
    if (isset($_POST[$param])) {
        // Strip newlines/tabs from input to prevent log formatting issues
        $cleanValue = preg_replace('/\s+/', ' ', $_POST[$param]); 
        $logDetails[] = "$param=$cleanValue";
    }
}

// 4. Write incoming request details to file log
if (!empty($logDetails)) {
    $timestamp = date('Y-m-d H:i:s');
    $requesterIP = $_SERVER['REMOTE_ADDR']; 
    $logString = "[$timestamp] [ReqIP: $requesterIP] " . implode(', ', $logDetails) . PHP_EOL;
    file_put_contents($logFile, $logString, FILE_APPEND);
}

// 5. Evaluate Authentication
$password = $_POST['password'] ?? '';
$isAuthenticated = ($password === 'pict');

// 6. Database Connection & Status Update Logic
$module    = $_POST['module'] ?? '';
$action    = $_POST['action'] ?? '';
$rawStatus = $_POST['status'] ?? '';
$ip        = $_POST['ip'] ?? $_SERVER['REMOTE_ADDR'];

// Connect to MySQL
$conn = @mysqli_connect('127.0.0.1', 'exam', 'exam', 'Labguard');
if (!$conn) {
    $conn = @mysqli_connect('localhost', 'exam', 'exam', 'Labguard');
}

if ($conn) {
    // Process network module entries
    if ($module === 'network' && !empty($ip)) {
        $dbStatus = '';

        // Status mapping rules
        if ($rawStatus === 'isolated') {
            $dbStatus = 'isolated';
        } elseif ($rawStatus === 'authenticated' || ($action === 'auth' && $isAuthenticated)) {
            $dbStatus = 'offline';
        }

        if (!empty($dbStatus)) {
            $stmt = mysqli_prepare($conn, "INSERT INTO internet (ip, status) VALUES (?, ?) ON DUPLICATE KEY UPDATE status = VALUES(status)");
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, "ss", $ip, $dbStatus);
                if (!mysqli_stmt_execute($stmt)) {
                    file_put_contents($logFile, "[DB ERROR] Query Execute Failed: " . mysqli_stmt_error($stmt) . PHP_EOL, FILE_APPEND);
                }
                mysqli_stmt_close($stmt);
            } else {
                file_put_contents($logFile, "[DB ERROR] Query Prepare Failed: " . mysqli_error($conn) . PHP_EOL, FILE_APPEND);
            }
        }
    }
    mysqli_close($conn);
} else {
    file_put_contents($logFile, "[DB ERROR] MySQL Connection Failed: " . mysqli_connect_error() . PHP_EOL, FILE_APPEND);
}

// 7. Authentication Response
if ($isAuthenticated) {
    echo 200;
} else {
    echo 300;
}
?>
