<?php
/**
 * SIMPLE PHP TEST - NO DEPENDENCIES
 * Untuk cek apakah PHP berjalan di hosting
 */

// Display all errors
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<!DOCTYPE html>";
echo "<html><head><title>Simple PHP Test</title></head><body>";
echo "<h1>✅ PHP is Working!</h1>";
echo "<p>PHP Version: " . phpversion() . "</p>";
echo "<p>Server: " . $_SERVER['SERVER_SOFTWARE'] . "</p>";
echo "<p>Document Root: " . $_SERVER['DOCUMENT_ROOT'] . "</p>";
echo "<hr>";

// Test 1: Can we load config.php?
echo "<h2>Test 1: Load config.php</h2>";
try {
    require_once 'config.php';
    echo "<p style='color:green;'>✅ config.php loaded successfully</p>";
    echo "<p>DB_HOST: " . DB_HOST . "</p>";
    echo "<p>DB_USER: " . DB_USER . "</p>";
    echo "<p>DB_NAME: " . DB_NAME . "</p>";
    echo "<p>DB_PASS length: " . strlen(DB_PASS) . " characters</p>";
    
    // Check for trailing space in password
    if (DB_PASS !== trim(DB_PASS)) {
        echo "<p style='color:red; font-weight:bold;'>⚠️ WARNING: DB_PASS has trailing/leading spaces!</p>";
        echo "<p>Raw: '" . DB_PASS . "' (length: " . strlen(DB_PASS) . ")</p>";
        echo "<p>Trimmed: '" . trim(DB_PASS) . "' (length: " . strlen(trim(DB_PASS)) . ")</p>";
    } else {
        echo "<p style='color:green;'>✅ DB_PASS looks clean (no extra spaces)</p>";
    }
} catch (Exception $e) {
    echo "<p style='color:red;'>❌ ERROR loading config.php:</p>";
    echo "<pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
    echo "</body></html>";
    exit;
}

echo "<hr>";

// Test 2: Can we connect to database?
echo "<h2>Test 2: Database Connection</h2>";
try {
    $db = Database::getInstance()->getConnection();
    echo "<p style='color:green;'>✅ Database connected successfully!</p>";
} catch (Exception $e) {
    echo "<p style='color:red;'>❌ Database connection FAILED:</p>";
    echo "<pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
    echo "<p><strong>Kemungkinan penyebab:</strong></p>";
    echo "<ul>";
    echo "<li>Password database salah (ada spasi?)</li>";
    echo "<li>Database belum dibuat di cPanel</li>";
    echo "<li>User tidak punya akses ke database</li>";
    echo "</ul>";
    echo "</body></html>";
    exit;
}

echo "<hr>";

// Test 3: Can we load auth.php?
echo "<h2>Test 3: Load auth.php</h2>";
try {
    require_once 'auth.php';
    echo "<p style='color:green;'>✅ auth.php loaded successfully</p>";
} catch (Exception $e) {
    echo "<p style='color:red;'>❌ ERROR loading auth.php:</p>";
    echo "<pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}

echo "<hr>";
echo "<h2>✅ All Tests Completed</h2>";
echo "<p>Jika sampai sini berarti PHP, config.php, dan auth.php OK.</p>";
echo "<p>Masalah mungkin di process-login.php atau login.php</p>";
echo "<p><a href='login.php'>Try Login Page</a></p>";
echo "</body></html>";
?>
