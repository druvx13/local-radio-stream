<?php
// app/core/bootstrap.php

// Error Reporting Configuration
ini_set('display_errors', '0'); // Do not display errors to the user
ini_set('display_startup_errors', '0'); // Do not display startup errors
ini_set('log_errors', '1'); // Log errors

// Define a secure error log path
// BASE_PATH is defined in public/index.php and points to the project root.
$logDir = BASE_PATH . '/logs';
if (!is_dir($logDir)) {
    // Attempt to create it if it doesn't exist.
    // Use @ to suppress errors if mkdir fails; custom error handler should catch issues if logging fails.
    @mkdir($logDir, 0755, true);
}
$errorLogPath = $logDir . '/php_errors.log';
ini_set('error_log', $errorLogPath);

// Set error reporting level (report all errors initially during development, can be adjusted for production)
error_reporting(E_ALL);

// Rate Limiting Configuration for Uploads
define('UPLOAD_RATE_LIMIT_COUNT', 10); // Max uploads per window
define('UPLOAD_RATE_LIMIT_WINDOW', 3600); // Time window in seconds (1 hour = 3600 seconds)

// Image Optimization Configuration
define('COVER_ART_MAX_WIDTH', 500); // Max width for cover art in pixels
define('COVER_ART_MAX_HEIGHT', 500); // Max height for cover art in pixels
define('COVER_ART_JPEG_QUALITY', 75); // JPEG quality (0-100, default is often 75)
define('COVER_ART_PNG_COMPRESSION', 6); // PNG compression level (0-9, 0 is no compression, 9 is max)

// Custom Error Handler
function customErrorHandler($errno, $errstr, $errfile, $errline) {
    $timestamp = date("Y-m-d H:i:s (T)");
    $logMessage = "[{$timestamp}] PHP Error: [Code {$errno}] {$errstr} in {$errfile} on line {$errline}" . PHP_EOL;

    error_log($logMessage); // Log to the configured error_log file

    // Determine if it's an API request context
    $isApiRequest = isset($_GET['action']) ||
                    (isset($_SERVER['HTTP_ACCEPT']) && strpos(strtolower($_SERVER['HTTP_ACCEPT']), 'application/json') !== false);

    if (!headers_sent()) {
        http_response_code(500); // Internal Server Error
        if ($isApiRequest) {
            header('Content-Type: application/json');
            echo json_encode([
                'status' => 'error',
                'message' => 'A server error occurred. Please try again later or contact support if the issue persists.'
                // Optionally, include an error reference ID for tracking, e.g., 'ref' => uniqid()
            ]);
        } else {
            // For HTML contexts - display a user-friendly HTML error page
            // This should be a simple static HTML page or a minimal PHP include to avoid further errors.
            // Example: include BASE_PATH . '/templates/errors/500.html';
            echo "<h1>Oops! Something went wrong on our end.</h1><p>We are sorry for the inconvenience. Please try again later. If the problem continues, please contact support.</p>";
        }
    }
    // For critical errors like E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR,
    // the script will terminate after the error handler. For notices/warnings, it might continue
    // unless we explicitly exit. It's often safer to exit for most unhandled errors in production.
    // However, set_error_handler doesn't catch fatal errors. Those are for register_shutdown_function.
    // For simplicity in this step, we'll let PHP decide based on error type or explicitly exit for specific severities if needed.
    // For now, exiting on any handled error to be safe during development/refactoring.
    exit;
}
set_error_handler('customErrorHandler');

// Custom Exception Handler
function customExceptionHandler($exception) {
    $timestamp = date("Y-m-d H:i:s (T)");
    // Detailed log message including stack trace
    $logMessage = "[{$timestamp}] PHP Uncaught Exception: " . $exception->getMessage() . " (Code: " . $exception->getCode() . ")" . PHP_EOL;
    $logMessage .= "In " . $exception->getFile() . " on line " . $exception->getLine() . PHP_EOL;
    $logMessage .= "Stack trace:" . PHP_EOL . $exception->getTraceAsString() . PHP_EOL;

    error_log($logMessage);

    $isApiRequest = isset($_GET['action']) ||
                    (isset($_SERVER['HTTP_ACCEPT']) && strpos(strtolower($_SERVER['HTTP_ACCEPT']), 'application/json') !== false);

    if (!headers_sent()) {
        http_response_code(500);
        if ($isApiRequest) {
            header('Content-Type: application/json');
            echo json_encode([
                'status' => 'error',
                'message' => 'A critical server error (exception) occurred. Please try again later.'
            ]);
        } else {
            // Example: include BASE_PATH . '/templates/errors/exception.html';
            echo "<h1>Oops! A critical application error occurred.</h1><p>We are sorry for the inconvenience. Please try again later.</p>";
        }
    }
    exit;
}
set_exception_handler('customExceptionHandler');

// --- Existing bootstrap code from here ---

// Autoloader (simple PSR-4 style implementation)
spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $base_dir = BASE_PATH . '/app/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    if (file_exists($file)) {
        require $file;
    }
});

// Load configuration (db credentials etc.)
if (!file_exists(BASE_PATH . '/db_config.php')) {
    // This specific check might be redundant if the custom error handler is robust enough
    // to catch the warning/error from require_once if file is missing.
    // However, it provides a clearer early exit message for a critical setup issue.
    $errorMessage = 'Critical Setup Error: Database configuration file (db_config.php) not found in the project root. Please create it by copying db_config.sample.php and filling in your credentials.';
    error_log("[".date("Y-m-d H:i:s (T)")."] ".$errorMessage); // Also log this specific setup error

    if (!headers_sent()) {
        http_response_code(500);
        // Check context for JSON response
        $isApiRequestForDbConfig = isset($_GET['action']) ||
                                   (isset($_SERVER['HTTP_ACCEPT']) && strpos(strtolower($_SERVER['HTTP_ACCEPT']), 'application/json') !== false);
        if ($isApiRequestForDbConfig) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => $errorMessage]);
        } else {
            echo "<h1>Critical Setup Error</h1><p>" . htmlspecialchars($errorMessage) . "</p>";
        }
    }
    exit;
}
require_once BASE_PATH . '/db_config.php';

// Database Connection
$dbConnection = new mysqli(DB_HOST, DB_USERNAME, DB_PASSWORD, DB_NAME);

if ($dbConnection->connect_error) {
    $errorMessage = 'Database connection failed: (' . $dbConnection->connect_errno . ') ' . $dbConnection->connect_error;
    error_log("[".date("Y-m-d H:i:s (T)")."] ".$errorMessage);

    if (!headers_sent()) {
        http_response_code(500);
        $isApiRequestForDbConn = isset($_GET['action']) ||
                                 (isset($_SERVER['HTTP_ACCEPT']) && strpos(strtolower($_SERVER['HTTP_ACCEPT']), 'application/json') !== false);
        if ($isApiRequestForDbConn) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => $errorMessage]);
        } else {
            echo "<h1>Database Connection Error</h1><p>Could not connect to the database. Please check server logs.</p>";
        }
    }
    exit;
}

if (!$dbConnection->set_charset("utf8mb4")) {
    $errorMessage = 'Error setting database character set: ' . $dbConnection->error;
    error_log("[".date("Y-m-d H:i:s (T)")."] ".$errorMessage);

    if (!headers_sent()) {
        http_response_code(500);
        $isApiRequestForCharset = isset($_GET['action']) ||
                                  (isset($_SERVER['HTTP_ACCEPT']) && strpos(strtolower($_SERVER['HTTP_ACCEPT']), 'application/json') !== false);
        if ($isApiRequestForCharset) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => $errorMessage]);
        } else {
             echo "<h1>Database Configuration Error</h1><p>Error setting character set. Please check server logs.</p>";
        }
    }
    exit;
}
?>
