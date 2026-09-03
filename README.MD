## What is Automaid?
Automaid is a laundry pick up and delivery platform that connects customers with drivers and laundry outlets through mobile apps, featuring real-time booking, map tracking, payment gateways, and an admin panel for managing operations. The platform supports multiple ride types (Bike, Car, Premium), city-wise fare configuration, driver matching algorithms, commission management, promo codes, and customer support tools to provide a complete laundry booking solution.

## Components
1. Admin Panel - Complete administrative interface built with Laravel and Filament for managing customers, drivers, rides, fee configurations, and platform operations.
2. Backend API - API backend built with Laravel Framework 11.0 that powers mobile applications for both customers and drivers.

## Getting Started
The documentation is organized into several sections:

1. Admin Panel Setup - Instructions for setting up and configuring the Laravel Filament admin panel
2. Backend API Setup - Guide for setting up and configuring the Laravel backend API
3. Support - How to get help and contact information. 

## Prerequisites:

For Backend (Admin Panel & API)
1. PHP Version: backend is built using Laravel Framework 11.0, so you need PHP version minimum 8.4 or higher installed on your server.
2. Database: Mysql 8.7 
3. Composer: PHP dependency manager
4. Node.js & NPM: Required because Filament uses Vite for compiling CSS and JavaScript assets. Filament requires Node.js for asset compilation during development and building production assets.

For Mobile Application Development
1. Flutter Version : Flutter stable channel 3.41.4 SDK version
2. Java Version : JDK version 17(for Android development)
Note:You can use either a VPS server or shared hosting for the backend, depending on your requirements. For production environments with high traffic, a VPS server is recommended for better performance and control.

## Server Requirements &Extensions
Automaid requires specific PHP extensions to function properly. The installation process will check for these requirements automatically.

RequiredPHPExtensions:
- PHP 8.4+ (minimum version)
- PDO - PHP Data Objects
- PDO_MySQL - MySQL database driver
- OpenSSL - For secure connections
- Mbstring - Multibyte string handling
- Tokenizer - Token parsing
- XML - XML processing
- Ctype - Character type checking
- JSON - JSON encoding/decoding
- BCMath - Arbitrary precision mathematics
- Fileinfo - File information
- GD or Imagick - Image processing
- Zip - Archive handling
- Curl - HTTP client
Note:The pdo_mysql extension is also required for database connectivity.

If PHP version is lower than 8.4 or any extension is not installed, it will be highlighted in Red color. You need to ensure that your server meets all requirements before proceeding.

## PermissionScreen
Makesure these folders have read & write permissions:

- storage/ - logs, cache, and uploaded files
- storage/app/ - application storage
- storage/framework/ - framework cache and sessions
- storage/logs/ - application logs
- bootstrap/cache/ - bootstrap cache files

Final Installation Screen
If every thing is configured successfully, your e-Taxi admin panel has been installed successfully!

## Access the Admin Panel
Navigate to:

http://your-domain.com/admin/login
