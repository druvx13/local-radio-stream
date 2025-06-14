# Local Radio Stream Application 🎧

> A radio streaming application combining PHP/MySQL backend with JavaScript/HTML5 frontend

---

## 📌 Project Overview

### Demo-Screenshot

![Local Radio Stream](https://raw.githubusercontent.com/druvx13/local-radio-stream/refs/heads/main/Local%20Radio%20Stream.png)

[✨Visit Dummy Demo](https://druvx13.github.io/local-radio-stream/demo.html)


This is a radio streaming application combining a **PHP/MySQL backend** with a **JavaScript/HTML5 frontend**. It enables users to:
- Stream MP3 files from a local server
- Upload music with metadata (title, artist, lyrics, cover art)
- Manage playlists dynamically
- Enjoy real-time audio visualization
- Control playback with advanced features

---

## 🛠️ Setup Instructions

### 1. Server Requirements
- **PHP 8.0+** with mysqli extension enabled.
- **GD Library for PHP** (for image optimization of cover art).
- **MySQL 5.6+** database server.
- **Apache/Nginx** web server.
- **777 permissions** on `public/uploads/` directory.

### 2. Database Configuration
```sql
-- Create database
CREATE DATABASE loco_music;
USE loco_music;

-- Create songs table
CREATE TABLE songs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(255) NOT NULL,
  file VARCHAR(255) NOT NULL,
  cover VARCHAR(255),
  artist VARCHAR(255) NOT NULL,
  lyrics TEXT,
  uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create upload_attempts table for rate limiting
CREATE TABLE IF NOT EXISTS upload_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45) NOT NULL, -- Supports IPv4 and IPv6
    attempt_timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX ip_address_idx (ip_address),
    INDEX attempt_timestamp_idx (attempt_timestamp)
);

-- Recommended Indexes for 'songs' table for performance:
CREATE INDEX idx_songs_uploaded_at ON songs (uploaded_at); -- Speeds up playlist sorting
CREATE INDEX idx_songs_title ON songs (title); -- For future title search/filter
CREATE INDEX idx_songs_artist ON songs (artist); -- For future artist search/filter
```

### 3. File Structure
```
/project-root
├── index.php               # All-in-one PHP/HTML/JS application
├── db_config.sample.php    # Sample database configuration
├── db_config.php           # Your database configuration (gitignored)
├── .htaccess               # Apache configuration
├── /uploads                # Media storage (777 permissions)
│   ├── song1.mp3           # MP3 files
│   └── cover1.jpg          # Album art
└── README.md               # This documentation
```

### 4. PHP Configuration
1.  **Copy `db_config.sample.php` to `db_config.php`.**
    ```bash
    cp db_config.sample.php db_config.php
    ```
2.  **Edit `db_config.php` with your actual database credentials:**
    ```php
    define('DB_HOST', 'your_localhost');
    define('DB_USERNAME', 'your_db_user');
    define('DB_PASSWORD', 'your_db_password');
    define('DB_NAME', 'loco_music');
    ?>
    ```
    **Important:** `db_config.php` is included in `.gitignore` and should not be committed to your repository.

    The application also uses constants for rate limiting defined in `app/core/bootstrap.php`:
    - `UPLOAD_RATE_LIMIT_COUNT`: Max uploads per user per time window.
    - `UPLOAD_RATE_LIMIT_WINDOW`: Time window in seconds for rate limiting.

    Additionally, configuration for cover art optimization is in `app/core/bootstrap.php`:
    - `COVER_ART_MAX_WIDTH`: Maximum width for resized cover art.
    - `COVER_ART_MAX_HEIGHT`: Maximum height for resized cover art.
    - `COVER_ART_JPEG_QUALITY`: Quality setting for JPEG optimization.
    - `COVER_ART_PNG_COMPRESSION`: Compression level for PNG optimization.
    These can be adjusted if needed.

### 5. Directory Permissions
```bash
mkdir -p public/uploads/
chmod 777 public/uploads/
```

---

## 🔒 License (MIT)
**Copyright (c) 2024 druvx13**

This project is licensed under the MIT License.

For full license text see [LICENSE](LICENSE.md).

---

## 📁 .htaccess File Explained

### 1. URL Rewriting
```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteBase /
    
    # Route all non-file/directory requests to index.php
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule ^(.*)$ index.php [QSA,L]
</IfModule>
```
- Enables clean URLs by routing API requests to `index.php`
- Supports endpoints like `?action=getPlaylist` through URL rewriting

### 2. Security Settings
```apache
# Disable directory browsing
Options -Indexes

# Security: Disallow remote access to sensitive files
# Includes protection for configuration files like db_config.php
<FilesMatch "\.(env|ini|log|sql|bak|sh|config\.php)$">
    Order Allow,Deny
    Deny from all
</FilesMatch>
```
- Prevents directory listing
- Blocks access to configuration/backup files, including `db_config.php`.

### 3. PHP Settings
```apache
# Note: These settings can often be configured via your hosting control panel,
# which may override .htaccess directives.
<IfModule mod_php.c> # Changed from mod_php7.c for broader compatibility
    php_value upload_max_filesize 64M
    php_value post_max_size 64M
    php_value max_execution_time 300
    php_value max_input_time 300
</IfModule>
```
- Allows large file uploads (64MB MP3 files)
- Increases execution time for uploads

### 4. MIME Types
```apache
<IfModule mod_mime.c>
    AddType audio/mpeg .mp3
    AddType image/jpeg .jpg .jpeg
    AddType image/png .png
    AddType image/gif .gif
</IfModule>
```
- Ensures correct content-type headers for media files

### 5. Added Security Headers
The `.htaccess` file now includes several HTTP security headers to protect against common web vulnerabilities:
- `X-Content-Type-Options "nosniff"`: Prevents MIME-sniffing.
- `X-Frame-Options "SAMEORIGIN"`: Protects against clickjacking.
- `Referrer-Policy "strict-origin-when-cross-origin"`: Controls referrer information.
- `Permissions-Policy`: Restricts usage of sensitive browser features.
- `Content-Security-Policy`: Helps prevent XSS and other injection attacks by defining allowed content sources. (The current policy is basic and allows inline scripts/styles due to project structure).

### 6. Performance Optimization
```apache
<IfModule mod_deflate.c>
    AddOutputFilterByType DEFLATE text/html text/plain text/xml text/css application/x-javascript application/javascript application/json
</IfModule>

<IfModule mod_expires.c>
    ExpiresActive On
    ExpiresByType audio/mpeg "access plus 1 week"
    ExpiresByType image/jpg "access plus 1 week"
    ExpiresByType image/jpeg "access plus 1 week"
    ExpiresByType image/png "access plus 1 week"
    ExpiresByType image/gif "access plus 1 week"
    ExpiresByType text/css "access plus 1 day"
    ExpiresByType application/javascript "access plus 1 day"
</IfModule>
```
- Enables GZIP compression
- Sets caching headers for static assets

### 6. Request Limits
```apache
# Security: Limit file uploads (100MB max request size)
LimitRequestBody 104857600
```
- Prevents excessively large requests

---

## 🎵 Core Features

### 1. Music Streaming System
- **Multi-format audio support** (MP3, AAC, M4A, OGG) with browser-native `<audio>` element.
- **Progressive loading** with time/duration display.
- **Bitrate detection** (default: 128kbps, primarily for MP3s; other formats may vary).

### 2. Playlist Management
- **Reverse chronological display** (`ORDER BY uploaded_at DESC`).
- **Client-side search/filter** for the playlist by song title or artist, with debounced input for real-time filtering.
- **Editable song metadata:** Ability to edit song title, artist, and lyrics via a modal dialog.
- **Lazy loading of cover art images** to improve initial load time and perceived performance, especially for long playlists.
- **Shuffle functionality** using Fisher-Yates algorithm.
- **Repeat mode** with single-track loop.

### 3. Upload System
- **Audio format validation:** Supports MP3, AAC, M4A (AAC in MP4), and OGG. Validation includes file extension and server-side MIME type checks (e.g., `audio/mpeg`, `audio/aac`, `audio/mp4`, `audio/ogg`).
- **Cover art support** (JPG/PNG/GIF) with server-side MIME type check.
- **Automatic server-side optimization** (resize & compress) of uploaded cover art to save storage and improve loading times (requires GD library).
- **Filename sanitization** for uploaded files.
- **Lyrics storage** in database (lyrics are sanitized with `htmlspecialchars` before storage).

### 4. Audio Visualization
- **Web Audio API** integration
- **50-bar frequency analyzer**
- **Waveform-style animation**

### 5. Playback Controls
- Play/Pause toggle
- Previous/Next track
- Volume control
- Time/duration tracking

---

## ⚠️ Security Considerations

Significant improvements have been made, but security is an ongoing process.

### 1. Database Credential Management
- **Improved:** Credentials are now managed in `db_config.php`, which is included in `.gitignore` to prevent accidental versioning.
- **Recommendation:** Ensure `db_config.php` has appropriate file permissions on the server and is not web-accessible.

### 2. File Uploads
- **Improved:**
    - Server-side MIME type validation (`audio/mpeg` for songs; `image/jpeg`, `image/png`, `image/gif` for covers) is now performed. Invalid files are deleted.
    - Filenames are sanitized to remove potentially problematic characters.
    - Uploaded cover images are processed (resized and re-compressed) to standard dimensions and optimized quality, which also helps in standardizing file formats.
- **Upload Rate Limiting:**
    - **Improved:** The `uploadSong` API endpoint now includes IP-based rate limiting to prevent abuse. It limits the number of upload attempts from a single IP address within a defined time window (e.g., 10 uploads per hour).
- **Note:** While improved, robust file upload security can be complex. Consider further restrictions or analysis if handling untrusted uploads in a more sensitive environment.

### 3. SQL Injection
- **Partially Mitigated:** Prepared statements are used for database inserts (`uploadSong` action) and for rate limiting queries.
- **Recommendation:** Review all database queries to ensure prepared statements or proper escaping is used consistently.

### 4. CSRF Vulnerability
- **Improved:** The song upload form (`uploadSong` action) is now protected by CSRF tokens.

### 5. XSS Vulnerability
- **Improved:** Lyrics submitted via the upload form are sanitized using `htmlspecialchars` before being stored in the database and when returned in API responses, mitigating direct XSS vectors through this field.
- **Recommendation:** Always ensure proper output encoding/escaping when rendering any user-supplied content on the frontend, even if sanitized server-side.

### 6. HTTP Security Headers
- **Improved:** The `.htaccess` file now configures several important security headers:
    - `X-Content-Type-Options "nosniff"`
    - `X-Frame-Options "SAMEORIGIN"`
    - `Referrer-Policy "strict-origin-when-cross-origin"`
    - `Permissions-Policy` (basic setup)
    - `Content-Security-Policy` (now stricter, avoiding 'unsafe-inline' for scripts and styles).
- **Recommendation:** For enhanced security, always review and adapt security measures like CSP to your specific hosting environment and application needs.

---

## ⚡ Performance and Asset Minification

For improved performance, the application links to minified versions of its custom CSS and JavaScript files:
- `public/assets/css/style.min.css`
- `public/assets/js/main.min.js`

If you make custom changes to the source files (`style.css` or `main.js`), you should re-minify them. Placeholder `.min` files are provided, which are initially copies of the unminified versions.

**How to Minify:**

**CSS (using an online tool):**
1.  Copy the entire content of `public/assets/css/style.css`.
2.  Go to an online CSS minifier (e.g., search "online CSS minifier" - options include cssminifier.com, Toptal CSS Minifier).
3.  Paste your CSS code into the minifier.
4.  Copy the minified output from the tool.
5.  Paste the minified code into `public/assets/css/style.min.css`, replacing its existing content.

**JavaScript (using an online tool):**
1.  Copy the entire content of `public/assets/js/main.js`.
2.  Go to an online JavaScript minifier (e.g., search "online JavaScript minifier" - options include javascript-minifier.com, Toptal JavaScript Minifier, UglifyJS online).
3.  Paste your JavaScript code into the minifier.
4.  Copy the minified output.
5.  Paste the minified code into `public/assets/js/main.min.js`, replacing its existing content.

**Note for Developers:** If you are actively developing and making frequent changes to CSS or JavaScript, you might find it convenient to temporarily change the links in `templates/player.php` to point directly to the unminified files (e.g., `style.css` and `main.js`). Remember to switch back and re-minify when your changes are stable.

**Utility Functions:**
- The `public/assets/js/main.js` file also includes a `debounce` utility function. This function is available for optimizing event handlers (e.g., for future search input fields) by delaying function execution until after a certain period of inactivity.

---

## 📦 File Structure

The project has been restructured from a single `index.php` file to a more organized layout:

-   `public/`: Web server document root.
    -   `index.php`: Main application entry point (router).
    -   `assets/`: Contains CSS and JS files.
    -   `uploads/`: Storage for uploaded media.
    -   `.htaccess`: Apache configuration for routing and security.
-   `app/`: Core application logic.
    -   `controllers/`: Handles requests and business logic (e.g., `PageController.php`, `ApiController.php`).
    -   `core/`: Bootstrap, database connection, configuration (`bootstrap.php`).
-   `templates/`: HTML templates (e.g., `player.php`).
-   `logs/`: For PHP error logs (should be secured and not web-accessible).
-   `db_config.php`: Database credentials (gitignored).
-   `db_config.sample.php`: Sample for `db_config.php`.
-   `README.md`, `LICENSE.md`, `docs/`: Project documentation.

---

## 📬 Support
For issues or questions:
1. Open a GitHub issue
2. Check project documentation (this README)

---

## 📈 Version History
| Version | Date       | Changes                                      |
|--------|------------|----------------------------------------------|
| 1.0.0  | 09-05-2025 | Initial release with core features           |

---

## 📡 API Endpoints

The application uses a simple routing mechanism via `public/index.php` with an `action` GET parameter. All API responses are in JSON format.

### Get Playlist
-   **Action:** `?action=getPlaylist`
-   **Method:** `GET`
-   **Description:** Retrieves the list of all songs.
-   **Success Response (200 OK):**
    ```json
    {
        "status": "success",
        "data": {
            "songs": [
                {
                    "id": 1,
                    "title": "Song Title",
                    "file": "uploads/song.mp3",
                    "cover": "uploads/cover.jpg",
                    "artist": "Artist Name",
                    "lyrics": "Song lyrics...",
                    "uploaded_at": "YYYY-MM-DD HH:MM:SS"
                }
                // ... more songs
            ]
        }
    }
    ```
-   **Error Response (500 Internal Server Error):**
    ```json
    {
        "status": "error",
        "message": "Could not retrieve playlist due to a server error."
    }
    ```

### Upload Song
-   **Action:** `?action=uploadSong`
-   **Method:** `POST` (Multipart form data)
-   **Description:** Uploads a new song with its metadata.
-   **Form Fields:**
    -   `title` (text, optional - defaults from filename)
    -   `artist` (text, optional - defaults to "Unknown Artist")
    -   `lyrics` (text, optional)
    - `song` (file, required, MP3/AAC/M4A/OGG)
    -   `cover` (file, optional, JPG/PNG/GIF)
    -   `csrf_token` (hidden, required)
-   **Success Response (201 Created):**
    ```json
    {
        "status": "success",
        "message": "File uploaded successfully",
        "data": {
            "song": { /* ... new song object ... */ }
        }
    }
    ```
-   **Error Responses:**
    -   `400 Bad Request`: Validation errors. The response may include an `errors` object with field-specific messages:
        ```json
        {
            "status": "error",
            "message": "Validation failed.",
            "errors": {
                "title": "Title cannot exceed 255 characters."
            }
        }
        ```
    -   `403 Forbidden`: CSRF token failure.
    -   `413 Payload Too Large`: File size exceeds limits.
    -   `415 Unsupported Media Type`: Invalid file MIME type.
    -   `429 Too Many Requests`: Rate limit exceeded.
    -   `500 Internal Server Error`: Server-side processing errors.

### Update Song Metadata
-   **Action:** `?action=updateSongMetadata`
-   **Method:** `POST`
-   **Description:** Updates the metadata (title, artist, lyrics) for an existing song.
-   **Payload (JSON):**
    ```json
    {
        "song_id": 123, // Integer, required
        "title": "New Song Title", // String, required
        "artist": "New Artist Name", // String, required
        "lyrics": "Updated lyrics here...", // String, optional
        "csrf_token": "your_csrf_token_here" // String, required
    }
    ```
-   **Success Response (200 OK):**
    ```json
    {
        "status": "success",
        "message": "Song metadata updated successfully."
        // Or "No changes detected..." if data was identical
    }
    ```
-   **Error Responses:**
    -   `400 Bad Request`: Validation errors. The response may include an `errors` object with field-specific messages (similar to Upload Song endpoint).
    -   `403 Forbidden`: CSRF token failure.
    -   `404 Not Found`: If `song_id` does not exist.
    -   `500 Internal Server Error`: Server-side processing errors.

---
