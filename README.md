# Local Radio Stream Application 🎧

> **Personal-use only radio streaming application** combining PHP/MySQL backend with JavaScript/HTML5 frontend

---

## 📌 Project Overview
This is a **personal-use-only radio streaming application** combining a **PHP/MySQL backend** with a **JavaScript/HTML5 frontend**. It enables users to:
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
├── index.php           # All-in-one PHP/HTML/JS application
├── .htaccess           # Apache configuration
├── /uploads            # Media storage (777 permissions)
│   ├── song1.mp3       # MP3 files
│   └── cover1.jpg      # Album art
└── README.md           # This documentation
```

### 4. PHP Configuration
Edit database credentials in `index.php`:
```php
$host = "localhost"; // Database host
$db   = "loco_music";    // Database name
$user = "root";          // Database user
$pass = "";               // Database password
```

### 5. Directory Permissions
```bash
mkdir -p uploads/
chmod 777 uploads/
```

---

## 🔒 License (MIT)
**Copyright (c) 2025 druvx13**

Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the "Software"), to deal in the Software under the following conditions:

1. **Attribution**: You must give appropriate credit, provide a link to the license, and indicate if changes were made.
2. **Modifications**: Any modified versions must be clearly marked as such and maintain this license notice.
3. **Non-Commercial Use**: This Software may not be used for commercial purposes (monetized websites, apps, or services).
4. **No Warranty**: The Software is provided "as is" without warranty of any kind.

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
<FilesMatch "\.(env|ini|log|sql|bak|sh)$">
    Order Allow,Deny
    Deny from all
</FilesMatch>
```
- Prevents directory listing
- Blocks access to configuration/backup files

### 3. PHP Settings
```apache
<IfModule mod_php7.c>
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

### 5. Performance Optimization
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
- **MP3 validation** by file extension only
- **Cover art support** (JPG/PNG/GIF)
- **Lyrics storage** in database

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

### 1. Database Credential Exposure
- Credentials hardcoded in PHP script:
  ```php
  $host = "localhost";
  $user = "root";
  $pass = "";
  ```
- Immediate risk of database compromise if source code is exposed

### 2. Insecure File Uploads
- **MP3 validation**: Only checks file extension (`.mp3`)
- **Cover image validation**: Only checks file extension (`.jpg`, `.jpeg`, `.png`, `.gif`)
- No content-type verification or file sanitization

### 3. SQL Injection Risk
- Prepared statements used for inserts but not for all queries
- No input sanitization for search or filtering functions

### 4. CSRF Vulnerability
- Upload form lacks CSRF token protection
- Attackers can forge requests to upload malicious files

### 5. XSS Vulnerability
- User-provided lyrics directly displayed without sanitization
- Potential for script injection through lyrics field

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

**⚠️ WARNING:** This software is **strictly for personal use**. Any attempt to deploy it in public production environments will result in **immediate legal action** under international copyright and intellectual property laws.
