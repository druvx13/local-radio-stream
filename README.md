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
- **PHP 8.0+** with mysqli extension enabled
- **MySQL 5.6+** database server
- **Apache/Nginx** web server
- **777 permissions** on `/uploads` directory

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

### 5. Directory Permissions
```bash
mkdir -p uploads/
chmod 777 uploads/
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
- **MP3-only support** with browser-native `<audio>` element
- **Progressive loading** with time/duration display
- **Bitrate detection** (default: 128kbps)

### 2. Playlist Management
- **Reverse chronological display** (`ORDER BY uploaded_at DESC`)
- **Shuffle functionality** using Fisher-Yates algorithm
- **Repeat mode** with single-track loop

### 3. Upload System
- **MP3 validation** by file extension and server-side MIME type check (`audio/mpeg`).
- **Cover art support** (JPG/PNG/GIF) with server-side MIME type check.
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
- **Note:** While improved, robust file upload security can be complex. Consider further restrictions or analysis if handling untrusted uploads in a more sensitive environment.

### 3. SQL Injection
- **Partially Mitigated:** Prepared statements are used for database inserts (`uploadSong` action).
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
    - `Content-Security-Policy` (basic setup, allows `unsafe-inline` for compatibility)
- **Recommendation:** For enhanced security, consider refining the `Content-Security-Policy` further, especially by moving inline JavaScript and CSS to external files to remove `'unsafe-inline'`.

---

## 📦 File Structure Map

```
index.php
├── PHP Backend
│   ├── Database Connection
│   ├── API Handlers (getPlaylist, uploadSong)
│   └── Security Checks
├── HTML Structure
│   ├── Header (Radio Logo + Status)
│   ├── Player Controls
│   ├── Playlist Display
│   └── Upload Modal
├── CSS Styles
│   ├── Visualizer Animation
│   ├── Glassmorphism Effects
│   └── Responsive Layouts
└── JavaScript
    ├── Audio Processing
    ├── Playlist Management
    └── UI Interactions
```


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
