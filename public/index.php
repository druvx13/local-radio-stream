<?php
// public/index.php

// Define a base path for the application
define('BASE_PATH', dirname(__DIR__)); // Points to the project root

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Load bootstrap
require_once BASE_PATH . '/app/core/bootstrap.php';

// Basic router logic
$action = $_GET['action'] ?? 'showPlayer'; // Default action

// Simple routing for Phase 1
// API actions will be fully implemented in ApiController in a later phase
if ($action === 'showPlayer') {
    // Ensure $dbConnection is available from bootstrap.php
    if (!isset($dbConnection)) {
        // Handle error: $dbConnection not set
        // This might mean an issue in bootstrap.php
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'error',
            'message' => 'Critical error: Database connection not established in bootstrap.'
        ]);
        exit;
    }
    $pageController = new App\Controllers\PageController($dbConnection);
    $pageController->showPlayerPage();
} elseif ($action === 'getPlaylist') {
    $apiController = new App\Controllers\ApiController($dbConnection);
    $apiController->getPlaylist();
} elseif ($action === 'uploadSong') {
    $apiController = new App\Controllers\ApiController($dbConnection);
    $apiController->uploadSong();
} elseif ($action === 'updateSongMetadata') {
    $apiController = new App\Controllers\ApiController($dbConnection);
    $apiController->updateSongMetadata();
} else {
    header("HTTP/1.0 404 Not Found");
    // In a real app, you'd include a nice 404 template page here
    echo "Page not found.";
    exit;
}
?>
