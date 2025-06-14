<?php
// app/controllers/ApiController.php
namespace App\Controllers;

class ApiController {
    private $db;
    private $uploadDirRoot;
    private $uploadDirPublic;

    private const MAX_TITLE_LENGTH = 255;
    private const MAX_ARTIST_LENGTH = 255;
    private const MAX_LYRICS_LENGTH = 65535;

    private const MAX_SONG_FILE_SIZE = 64 * 1024 * 1024; // 64MB
    private const MAX_COVER_FILE_SIZE = 5 * 1024 * 1024; // 5MB

    public function __construct($dbConnection) {
        $this->db = $dbConnection;
        $this->uploadDirRoot = BASE_PATH . '/public/uploads/';
        $this->uploadDirPublic = 'uploads/';
    }

    private function sendJsonResponse($data, $statusCode = 200) {
        http_response_code($statusCode);
        header("Content-Type: application/json");
        echo json_encode($data);
        exit;
    }

    // Updated to return an array [?string $errorMessage, ?int $statusCode]
    private function validateFileUploadError($fileKey, $maxSize, $isOptional = false): ?array {
        if (!isset($_FILES[$fileKey]) || $_FILES[$fileKey]['error'] === UPLOAD_ERR_NO_FILE) {
            return $isOptional ? null : ["No file received for '$fileKey'.", 400];
        }

        $error = $_FILES[$fileKey]['error'];
        if ($error !== UPLOAD_ERR_OK) {
            switch ($error) {
                case UPLOAD_ERR_INI_SIZE:
                case UPLOAD_ERR_FORM_SIZE:
                    return [ucfirst($fileKey) . " file is too large (exceeds server or form limit). Max: " . ($maxSize / 1024 / 1024) . "MB.", 413]; // Payload Too Large
                case UPLOAD_ERR_PARTIAL:
                    return [ucfirst($fileKey) . " file was only partially uploaded.", 400];
                default:
                    return ["Unknown upload error for '$fileKey' (code: $error).", 500]; // Potentially a server issue
            }
        }
        if ($_FILES[$fileKey]['size'] == 0 && !$isOptional) { // Empty file check, unless optional and not provided
             return [ucfirst($fileKey) . " received an empty file.", 400];
        }
        if ($_FILES[$fileKey]['size'] > $maxSize) {
            return [ucfirst($fileKey) . " file exceeds maximum allowed size of " . round($maxSize / 1024 / 1024, 2) . "MB.", 413]; // Payload Too Large
        }
        return null; // No error
    }

    private function optimizeCoverImage(string $filePath, string $mimeType): bool {
        if (!extension_loaded('gd') || !function_exists('gd_info')) {
            error_log('GD library is not available for image optimization. Skipping optimization.');
            return false;
        }

        $source_image = null;
        switch ($mimeType) {
            case 'image/jpeg':
                $source_image = @imagecreatefromjpeg($filePath);
                break;
            case 'image/png':
                $source_image = @imagecreatefrompng($filePath);
                break;
            case 'image/gif':
                $source_image = @imagecreatefromgif($filePath);
                break;
            default:
                error_log("Unsupported MIME type for optimization: {$mimeType} for file {$filePath}");
                return false;
        }

        if (!$source_image) {
            error_log("Failed to create image resource from file: {$filePath} with MIME: {$mimeType}");
            return false;
        }

        $original_width = imagesx($source_image);
        $original_height = imagesy($source_image);

        $max_width = defined('COVER_ART_MAX_WIDTH') ? COVER_ART_MAX_WIDTH : 500;
        $max_height = defined('COVER_ART_MAX_HEIGHT') ? COVER_ART_MAX_HEIGHT : 500;

        // If image is already smaller than max dimensions, just re-save with quality settings
        // Or decide not to touch it at all to save processing, though re-saving can strip metadata or optimize.
        // For this implementation, we'll re-save to apply quality/compression settings.
        // if ($original_width <= $max_width && $original_height <= $max_height) {
        //     // Potentially just re-save to apply quality, or return true if no processing needed
        // }

        $ratio = $original_width / $original_height;
        $new_width = $max_width;
        $new_height = $max_height;

        if ($new_width / $new_height > $ratio) {
            $new_width = $new_height * $ratio;
        } else {
            $new_height = $new_width / $ratio;
        }

        // Ensure new dimensions are at least 1px and integers
        $new_width = max(1, round($new_width));
        $new_height = max(1, round($new_height));

        $destination_image = imagecreatetruecolor($new_width, $new_height);
        if (!$destination_image) {
            error_log("Failed to create true color image for resizing: {$filePath}");
            imagedestroy($source_image);
            return false;
        }

        if ($mimeType == 'image/png') {
            imagealphablending($destination_image, false);
            imagesavealpha($destination_image, true);
            $transparent = imagecolorallocatealpha($destination_image, 255, 255, 255, 127);
            if ($transparent === false) { // Check if color allocation failed
                 error_log("Failed to allocate transparent color for PNG: {$filePath}");
                 // Continue without filling if transparent color fails, or handle as error
            } else {
                imagefilledrectangle($destination_image, 0, 0, $new_width, $new_height, $transparent);
            }
        }

        if (!imagecopyresampled($destination_image, $source_image, 0, 0, 0, 0, $new_width, $new_height, $original_width, $original_height)) {
            error_log("Failed to resample image: {$filePath}");
            imagedestroy($source_image);
            imagedestroy($destination_image);
            return false;
        }

        $saveResult = false;
        switch ($mimeType) {
            case 'image/jpeg':
                $quality = defined('COVER_ART_JPEG_QUALITY') ? COVER_ART_JPEG_QUALITY : 75;
                $saveResult = imagejpeg($destination_image, $filePath, $quality);
                break;
            case 'image/png':
                $compression = defined('COVER_ART_PNG_COMPRESSION') ? COVER_ART_PNG_COMPRESSION : 6;
                $saveResult = imagepng($destination_image, $filePath, $compression);
                break;
            case 'image/gif':
                $saveResult = imagegif($destination_image, $filePath);
                break;
        }

        if (!$saveResult) {
            error_log("Failed to save optimized image: {$filePath}");
        }

        imagedestroy($source_image);
        imagedestroy($destination_image);

        return $saveResult;
    }

    public function getPlaylist() {
        // No direct user input that modifies the query, so less risk here.
        // Errors are primarily DB related.
        $songs = [];
        $sql = "SELECT id, title, file, cover, artist, lyrics, uploaded_at FROM songs ORDER BY uploaded_at DESC";

        $result = $this->db->query($sql);
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                if (!empty($row['file'])) {
                    $row['file'] = str_starts_with($row['file'], $this->uploadDirPublic) ? $row['file'] : $this->uploadDirPublic . $row['file'];
                }
                if (!empty($row['cover'])) {
                    $row['cover'] = str_starts_with($row['cover'], $this->uploadDirPublic) ? $row['cover'] : $this->uploadDirPublic . $row['cover'];
                }
                $songs[] = $row;
            }
            // Success, use sendJsonResponse (already does)
            $this->sendJsonResponse(['status' => 'success', 'data' => ['songs' => $songs]]);
        } else {
            // Database error
            throw new \App\Exceptions\DatabaseException("Could not retrieve playlist due to a database error.", $this->db->errno, null, ['query' => $sql, 'db_error' => $this->db->error]);
        }
    }

    public function uploadSong() {
        // Rate Limiting (existing logic)
        $userIpAddress = $_SERVER['REMOTE_ADDR'];
        $rateLimitWindow = defined('UPLOAD_RATE_LIMIT_WINDOW') ? UPLOAD_RATE_LIMIT_WINDOW : 3600;
        $rateLimitCount = defined('UPLOAD_RATE_LIMIT_COUNT') ? UPLOAD_RATE_LIMIT_COUNT : 10;
        // ... (cleanup and check logic as before) ...
        $cleanupSql = "DELETE FROM upload_attempts WHERE attempt_timestamp < (NOW() - INTERVAL ? SECOND)";
        $stmtCleanup = $this->db->prepare($cleanupSql);
        if ($stmtCleanup) {
            $stmtCleanup->bind_param("i", $rateLimitWindow);
            $stmtCleanup->execute();
            $stmtCleanup->close();
        } else { error_log("Rate limiting cleanup statement failed: " . $this->db->error); }

        $currentAttempts = 0;
        $checkSql = "SELECT COUNT(*) as attempt_count FROM upload_attempts WHERE ip_address = ? AND attempt_timestamp > (NOW() - INTERVAL ? SECOND)";
        $stmtCheck = $this->db->prepare($checkSql);
        if ($stmtCheck) {
            $stmtCheck->bind_param("si", $userIpAddress, $rateLimitWindow);
            $stmtCheck->execute();
            $result = $stmtCheck->get_result();
            $row = $result->fetch_assoc();
            $stmtCheck->close();
            $currentAttempts = $row['attempt_count'] ?? 0;
        } else { error_log("Rate limiting check statement failed: " . $this->db->error); }

        if ($currentAttempts >= $rateLimitCount) {
            // No specific exception for 429, custom handler will default to 500 if not set.
            // Or, create a RateLimitException or use a generic AppException with code 429.
            // For now, rely on custom handler to catch generic \Exception or specific ones.
            // $this->sendJsonResponse is being replaced, so we need to throw.
            // Let's make a specific AppException for this.
            throw new \App\Exceptions\AppException('Too many upload attempts. Please try again later.', 429);
        }
        // Log attempt
        $logAttemptSql = "INSERT INTO upload_attempts (ip_address) VALUES (?)";
        $stmtLog = $this->db->prepare($logAttemptSql);
        if ($stmtLog) { $stmtLog->bind_param("s", $userIpAddress); $stmtLog->execute(); $stmtLog->close(); }
        else { error_log("Rate limiting attempt logging failed: " . $this->db->error); }


        // CSRF Check
        if (!isset($_SESSION['csrf_token'])) {
            throw new \App\Exceptions\AppException('CSRF token not found in session. Please refresh the page.', 403);
        }
        if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
            throw new \App\Exceptions\AppException('CSRF token validation failed. Please refresh and try again.', 403);
        }
        unset($_SESSION['csrf_token']);

        // Text Input Validation
        $validationErrors = [];
        $title = trim($_POST['title'] ?? '');
        $artist = trim($_POST['artist'] ?? '');
        $rawLyrics = $_POST['lyrics'] ?? '';

        if (empty($title) && !(isset($_FILES['song']['name']) && !empty($_FILES['song']['name']))) {
            $validationErrors['title'] = 'Title is required if song filename is not available.';
        }
        if (empty($title) && isset($_FILES['song']['name'])) {
            $title = pathinfo($_FILES['song']['name'], PATHINFO_FILENAME);
        }
        if (empty($artist)) {
            $artist = 'Unknown Artist';
        }

        if (mb_strlen($title) > self::MAX_TITLE_LENGTH) {
            $validationErrors['title'] = 'Title exceeds maximum length of ' . self::MAX_TITLE_LENGTH . ' characters.';
        }
        if (mb_strlen($artist) > self::MAX_ARTIST_LENGTH) {
            $validationErrors['artist'] = 'Artist name exceeds maximum length of ' . self::MAX_ARTIST_LENGTH . ' characters.';
        }
        if (mb_strlen($rawLyrics) > self::MAX_LYRICS_LENGTH) {
            $validationErrors['lyrics'] = 'Lyrics exceed maximum length of ' . self::MAX_LYRICS_LENGTH . ' characters.';
        }

        if (!empty($validationErrors)) {
            throw new \App\Exceptions\ValidationException("Validation failed for text inputs.", 400, null, $validationErrors);
        }
        $sanitizedLyrics = htmlspecialchars($rawLyrics, ENT_QUOTES, 'UTF-8');

        // Song File Upload Validation
        $songUploadValidationResult = $this->validateFileUploadError('song', self::MAX_SONG_FILE_SIZE, false);
        if ($songUploadValidationResult !== null) {
            throw new \App\Exceptions\FileUploadException($songUploadValidationResult[0], $songUploadValidationResult[1]);
        }

        // Directory Creation
        if (!is_dir($this->uploadDirRoot)) {
            if (!mkdir($this->uploadDirRoot, 0777, true)) {
                error_log("Failed to create upload directory: " . $this->uploadDirRoot);
                throw new \App\Exceptions\AppException('Server error: Could not create upload directory.', 500);
            }
        }

        // Song File Processing
        $songOriginalName = $_FILES['song']['name'];
        $songFileExtension = strtolower(pathinfo($songOriginalName, PATHINFO_EXTENSION));
        $songSanitizedBaseName = preg_replace("/[^a-zA-Z0-9_\-\s]/", "", pathinfo($songOriginalName, PATHINFO_FILENAME));
        if (empty($songSanitizedBaseName)) $songSanitizedBaseName = 'uploaded_song';
        $songDbFileName = $songSanitizedBaseName . '_' . time() . '.' . $songFileExtension;
        $targetSongFsPath = $this->uploadDirRoot . $songDbFileName;

        $allowedSongExtensions = ['mp3', 'aac', 'm4a', 'ogg'];
        if (!in_array($songFileExtension, $allowedSongExtensions)) {
            throw new \App\Exceptions\FileUploadException('Invalid song format. Allowed formats: MP3, AAC, M4A, OGG.', 415);
        }

        if (!move_uploaded_file($_FILES['song']['tmp_name'], $targetSongFsPath)) {
            error_log("Error moving uploaded song file to: " . $targetSongFsPath);
            throw new \App\Exceptions\AppException('Server error: Could not save the uploaded song file.', 500);
        }

        $songMimeType = mime_content_type($targetSongFsPath);
        $allowedSongMimeTypes = ['audio/mpeg', 'audio/aac', 'audio/mp4', 'audio/ogg'];
        if (!in_array($songMimeType, $allowedSongMimeTypes)) {
            unlink($targetSongFsPath);
            throw new \App\Exceptions\FileUploadException("Invalid file content: Song must be MP3, AAC, M4A, or OGG. Detected MIME: {$songMimeType}", 415);
        }

        // Cover Image Handling
        $coverDbFileName = '';
        $targetCoverFsPath = '';
        $coverUploadIssueMessage = ''; // This will be part of success message, not an exception for cover.

        if (isset($_FILES['cover']) && $_FILES['cover']['error'] !== UPLOAD_ERR_NO_FILE) {
            $coverUploadValidationResult = $this->validateFileUploadError('cover', self::MAX_COVER_FILE_SIZE, true);
            if ($coverUploadValidationResult !== null) {
                $coverUploadIssueMessage = "Cover upload issue: " . $coverUploadValidationResult[0];
            } else {
                // ... (cover processing logic as before, setting $coverUploadIssueMessage on specific failures)
                $coverOriginalName = $_FILES['cover']['name'];
                $coverFileExtension = strtolower(pathinfo($coverOriginalName, PATHINFO_EXTENSION));
                $coverSanitizedBaseName = preg_replace("/[^a-zA-Z0-9_\-\s]/", "", pathinfo($coverOriginalName, PATHINFO_FILENAME));
                if (empty($coverSanitizedBaseName)) $coverSanitizedBaseName = 'uploaded_cover';
                $coverDbFileName = $coverSanitizedBaseName . '_' . time() . '.' . $coverFileExtension;
                $targetCoverFsPath = $this->uploadDirRoot . $coverDbFileName;

                $allowedCoverExtensions = ['jpg', 'jpeg', 'png', 'gif'];
                if (!in_array($coverFileExtension, $allowedCoverExtensions)) {
                    $coverUploadIssueMessage = "Invalid cover image extension. Allowed: " . implode(', ', $allowedCoverExtensions) . ".";
                    $coverDbFileName = '';
                } else {
                    if (!move_uploaded_file($_FILES['cover']['tmp_name'], $targetCoverFsPath)) {
                        error_log("Error moving uploaded cover file to: " . $targetCoverFsPath);
                        $coverUploadIssueMessage = "Could not save cover image.";
                        $coverDbFileName = '';
                    } else {
                        $coverMimeType = mime_content_type($targetCoverFsPath);
                        $allowedCoverMimeTypes = ['image/jpeg', 'image/png', 'image/gif'];
                        if (!in_array($coverMimeType, $allowedCoverMimeTypes)) {
                            unlink($targetCoverFsPath);
                            $coverUploadIssueMessage = "Invalid cover image content type. Detected: " . $coverMimeType . ".";
                            $coverDbFileName = '';
                        } else {
                            if (!$this->optimizeCoverImage($targetCoverFsPath, $coverMimeType)) {
                                error_log("Cover image optimization failed for {$targetCoverFsPath}. Original will be used.");
                                // $coverUploadIssueMessage can be appended here if optimization failure is critical to report
                            }
                        }
                    }
                }
            }
        }

        // Database Insertion
        $titleDb = $this->db->real_escape_string($title);
        $artistDb = $this->db->real_escape_string($artist);
        $lyricsDb = $this->db->real_escape_string($sanitizedLyrics);

        $stmt = $this->db->prepare("INSERT INTO songs (title, file, cover, artist, lyrics) VALUES (?, ?, ?, ?, ?)");
        if (!$stmt) {
            error_log("Database statement preparation error: " . $this->db->error);
            if (file_exists($targetSongFsPath)) unlink($targetSongFsPath);
            if (!empty($targetCoverFsPath) && file_exists($targetCoverFsPath) && !empty($coverDbFileName)) unlink($targetCoverFsPath);
            throw new \App\Exceptions\DatabaseException('Server error: Could not prepare to save song details.');
        }

        $stmt->bind_param("sssss", $titleDb, $songDbFileName, $coverDbFileName, $artistDb, $lyricsDb);

        if ($stmt->execute()) {
            $songId = $this->db->insert_id;
            $successMessage = 'File uploaded successfully';
            if(!empty($coverUploadIssueMessage)) {
                $successMessage .= ' (Note: ' . $coverUploadIssueMessage . ')';
            }
            $this->sendJsonResponse([ // Keep sendJsonResponse for success
                'status' => 'success',
                'message' => $successMessage,
                'data' => [ /* ... song data ... */ ]
            ], 201);
        } else {
            error_log("Database execution error: " . $stmt->error);
            if (file_exists($targetSongFsPath)) unlink($targetSongFsPath);
            if (!empty($targetCoverFsPath) && file_exists($targetCoverFsPath) && !empty($coverDbFileName)) unlink($targetCoverFsPath);
            throw new \App\Exceptions\DatabaseException('Server error: Could not save song details to database.');
        }
        $stmt->close();
    }

    public function updateSongMetadata() {
        // CSRF Check
        if (!isset($_SESSION['csrf_token'])) {
             throw new \App\Exceptions\AppException('CSRF token not found in session. Please refresh the page.', 403);
        }
        $requestData = json_decode(file_get_contents('php://input'), true);
        if (!$requestData) {
            throw new \App\Exceptions\ValidationException('Invalid JSON payload.', 400);
        }

        if (!isset($requestData['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $requestData['csrf_token'])) {
            throw new \App\Exceptions\AppException('CSRF token validation failed. Please refresh and try again.', 403);
        }
        unset($_SESSION['csrf_token']);

        // Input Validation
        $validationErrors = [];
        if (!isset($requestData['song_id']) || !filter_var($requestData['song_id'], FILTER_VALIDATE_INT)) {
            $validationErrors['song_id'] = 'Invalid or missing song ID.';
        }
        $songId = (int)($requestData['song_id'] ?? 0);

        if (!isset($requestData['title']) || empty(trim($requestData['title']))) {
            $validationErrors['title'] = 'Title cannot be empty.';
        }
        $title = trim($requestData['title'] ?? '');

        if (!isset($requestData['artist']) || empty(trim($requestData['artist']))) {
            $validationErrors['artist'] = 'Artist cannot be empty.';
        }
        $artist = trim($requestData['artist'] ?? '');

        $lyrics = trim($requestData['lyrics'] ?? '');

        if (mb_strlen($title) > self::MAX_TITLE_LENGTH) {
            $validationErrors['title'] = 'Title exceeds maximum length of ' . self::MAX_TITLE_LENGTH . ' characters.';
        }
        if (mb_strlen($artist) > self::MAX_ARTIST_LENGTH) {
            $validationErrors['artist'] = 'Artist name exceeds maximum length of ' . self::MAX_ARTIST_LENGTH . ' characters.';
        }
        if (mb_strlen($lyrics) > self::MAX_LYRICS_LENGTH) {
            $validationErrors['lyrics'] = 'Lyrics exceed maximum length of ' . self::MAX_LYRICS_LENGTH . ' characters.';
        }

        if (!empty($validationErrors)) {
            throw new \App\Exceptions\ValidationException("Validation failed for metadata update.", 400, null, $validationErrors);
        }

        $sanitizedLyrics = htmlspecialchars($lyrics, ENT_QUOTES, 'UTF-8');
        $titleDb = $this->db->real_escape_string($title);
        $artistDb = $this->db->real_escape_string($artist);
        $lyricsDb = $this->db->real_escape_string($sanitizedLyrics);

        // Database Update
        $stmt = $this->db->prepare("UPDATE songs SET title = ?, artist = ?, lyrics = ? WHERE id = ?");
        if (!$stmt) {
            error_log("DB statement preparation error (updateSongMetadata): " . $this->db->error);
            throw new \App\Exceptions\DatabaseException('Server error: Could not prepare to update song details.');
        }

        $stmt->bind_param("sssi", $titleDb, $artistDb, $lyricsDb, $songId);
        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                $this->sendJsonResponse(['status' => 'success', 'message' => 'Song metadata updated successfully.']);
            } else {
                $checkExistStmt = $this->db->prepare("SELECT id FROM songs WHERE id = ?");
                if($checkExistStmt) {
                    $checkExistStmt->bind_param("i", $songId);
                    $checkExistStmt->execute();
                    $result = $checkExistStmt->get_result();
                    $checkExistStmt->close();
                    if ($result->num_rows === 0) {
                        throw new \App\Exceptions\NotFoundException('Song not found with the given ID.', 404);
                    }
                } else {
                     error_log("DB statement preparation error (check song exists): " . $this->db->error);
                }
                $this->sendJsonResponse(['status' => 'success', 'message' => 'No changes detected in song metadata.']);
            }
        } else {
            error_log("DB execution error (updateSongMetadata): " . $stmt->error);
            throw new \App\Exceptions\DatabaseException('Server error: Could not update song details in database.');
        }
        $stmt->close();
    }
}
?>
