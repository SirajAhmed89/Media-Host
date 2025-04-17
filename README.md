# MediaHost - Personal Media Storage Platform

## Overview
MediaHost is a secure, user-friendly web application that allows users to store, manage, and access their media files (images and videos) online. Built with PHP and modern web technologies, it provides a robust platform for personal media management with features like secure authentication, file upload tracking, and intuitive media organization.

## Features

### User Management
- Secure user registration and login system
- Password reset functionality with email verification
- Session-based authentication
- CSRF protection for enhanced security

### Media Management
- Support for multiple file formats:
  - Images: JPG, JPEG, PNG, WEBP
  - Videos: MP4, MOV
- Drag-and-drop file upload interface
- Real-time upload progress tracking
- Automatic video thumbnail generation
- File organization and search capabilities
- Download functionality for stored media
- Secure file deletion

### Storage Management
- Individual user storage quotas
- Real-time storage usage tracking
- Visual storage meter
- Automatic storage limit enforcement

### Security Features
- Secure password hashing
- CSRF token protection
- Session security
- Input sanitization
- Secure file handling
- Protected file access

### User Interface
- Responsive dashboard design
- Grid-based media gallery
- Search functionality for quick file access
- Progress indicators for uploads
- Intuitive file management controls

## Technical Specifications

### Storage Limits
- Maximum file size: 2GB per file
- Configurable storage quota per user
- Supported video formats: MP4, MOV
- Supported image formats: JPG, JPEG, PNG, WEBP

### Security Measures
- Password Requirements:
  - Minimum length: 8 characters
  - Must include numbers and special characters
- Session timeout protection
- CSRF token validation
- Secure file upload validation
- Protected media access

### Performance Features
- Lazy loading for media gallery
- Optimized thumbnail generation
- Efficient storage management
- Caching control for media files

## Directory Structure
```
mediahost/
├── assets/
│   ├── css/
│   │   ├── main.css
│   │   └── dashboard.css
│   ├── js/
│   │   └── fileUploader.js
│   └── images/
│       └── default_video_thumb.jpg
├── dashboard/
│   ├── index.php
│   ├── upload.php
│   ├── delete.php
│   └── uploads/
├── includes/
│   └── PHPMailer/
├── logs/
├── config.php
├── login.php
├── register.php
├── forgot-password.php
└── logout.php
```

## Installation Requirements

### Server Requirements
- PHP 8.0 or higher
- MySQL 5.7 or higher
- Apache/Nginx web server
- GD Library for PHP (for image processing)
- SMTP server configuration for email functionality

### PHP Extensions
- PDO PHP Extension
- GD PHP Extension
- FileInfo PHP Extension
- OpenSSL PHP Extension

### Database Setup
- MySQL database
- User with appropriate privileges
- Required tables:
  - users
  - media
  - password_resets

## Configuration

### Database Configuration
Configure database connection in `config.php`:
```php
define('DB_HOST', 'your_host');
define('DB_NAME', 'your_database');
define('DB_USER', 'your_username');
define('DB_PASS', 'your_password');
```

### Email Configuration
Configure SMTP settings in `config.php`:
```php
define('SMTP_HOST', 'your_smtp_host');
define('SMTP_PORT', 'your_smtp_port');
define('SMTP_USER', 'your_smtp_username');
define('SMTP_PASS', 'your_smtp_password');
```

### Storage Configuration
Set storage limits in `config.php`:
```php
define('MAX_STORAGE_SIZE', 5368709120); // 5GB in bytes
```

## Security Recommendations
1. Enable HTTPS
2. Set proper file permissions
3. Configure secure session handling
4. Enable error logging
5. Regular security updates
6. Implement rate limiting
7. Use strong password policies

## Usage Guidelines

### User Registration
1. Navigate to the registration page
2. Enter required information
3. Verify email address
4. Complete profile setup

### File Upload
1. Log into dashboard
2. Drag and drop files or click to select
3. Monitor upload progress
4. Verify successful upload

### File Management
1. View files in grid layout
2. Use search to find specific files
3. Download or delete files as needed
4. Monitor storage usage

## Troubleshooting

### Common Issues
1. Upload Failures
   - Check file size limits
   - Verify file format
   - Check storage quota
   - Check server permissions

2. Thumbnail Generation
   - Verify GD library installation
   - Check file permissions
   - Monitor error logs

3. Email Issues
   - Verify SMTP settings
   - Check email templates
   - Monitor email logs

### Error Logging
- Check `/logs/upload_error.log` for upload issues
- Monitor PHP error logs
- Check email sending logs

## Support and Maintenance

### Regular Maintenance
1. Monitor log files
2. Clean up temporary files
3. Update security patches
4. Backup database regularly
5. Check storage usage

### Performance Optimization
1. Optimize database queries
2. Clean up old sessions
3. Monitor server resources
4. Optimize media storage
