<?php
// app/controllers/PageController.php
namespace App\Controllers;

class PageController {
    private $db; // In case the page needs DB data directly later, though not used in this version

    public function __construct($dbConnection) {
        $this->db = $dbConnection;
    }

    public function showPlayerPage() {
        // Make CSRF token available for the template.
        // Session should have been started in public/index.php.
        if (empty($_SESSION['csrf_token'])) {
            // This might happen if the session expires or is new.
            // Regenerate if necessary.
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        $csrf_token = $_SESSION['csrf_token']; // Pass this to the template.

        // Path to the template
        // BASE_PATH is defined in public/index.php and points to the project root.
        $templatePath = BASE_PATH . '/templates/player.php';

        if (file_exists($templatePath)) {
            // $csrf_token will be available in the scope of player.php
            // Any other data needed by the template can be prepared here and passed similarly
            // or extracted from $this if set as properties.
            include $templatePath;
        } else {
            // Basic error handling if template is missing
            header("HTTP/1.0 500 Internal Server Error");
            echo "Error: Player template file not found at specified path: " . htmlspecialchars($templatePath);
            // It's good practice to log this error to the server's error log as well.
            error_log("Critical: Player template not found at " . $templatePath);
            exit;
        }
    }
}
?>
