# Fayyaz Travels CRM System

A comprehensive Customer Relationship Management (CRM) system built for Fayyaz Travels, featuring ticket management, real-time messaging, notifications, and Google OAuth authentication.

## 📋 Table of Contents

- [Overview](#overview)
- [Features](#features)
- [Technology Stack](#technology-stack)
- [Installation](#installation)
- [Configuration](#configuration)
- [Database Schema](#database-schema)
- [API Documentation](#api-documentation)
- [File Structure](#file-structure)
- [Authentication](#authentication)
- [Deployment](#deployment)
- [Troubleshooting](#troubleshooting)
- [Contributing](#contributing)

## 🎯 Overview

The Fayyaz Travels CRM is a web-based application designed to manage customer relationships, handle travel bookings, and streamline business operations. The system provides a comprehensive solution for ticket management, real-time communication, and user administration.

### Key Components

- **Authentication System**: Google OAuth integration via Auth0
- **Ticket Management**: Three types of tickets (Estimate, Supplier, General)
- **Real-time Messaging**: Live chat functionality with file sharing
- **Notification System**: Real-time notifications for ticket updates
- **Dashboard**: Comprehensive overview with statistics and quick actions
- **User Management**: Role-based access control (Admin/User)

## ✨ Features

### 🔐 Authentication & Security

- Google OAuth integration via Auth0
- Session management with secure cookies
- Role-based access control
- SSL/TLS support for production

### 🎫 Ticket Management

- **Estimate Tickets**: Handle travel package estimates and quotations
- **Supplier Tickets**: Manage supplier payments and invoices
- **General Tickets**: Handle miscellaneous requests and issues
- Priority levels: LOW, MEDIUM, HIGH, URGENT
- Status tracking: OPEN, IN_PROGRESS, RESOLVED, CLOSED
- File attachment support
- Estimated completion time tracking

### 💬 Real-time Communication

- Live messaging system
- File sharing (images, documents, audio)
- Message read status tracking
- Server-Sent Events (SSE) for real-time updates
- User online/offline status

### 🔔 Notification System

- Real-time notifications
- Email-style notification center
- Read/unread status tracking
- Multiple notification types (info, success, warning, error)

### 📊 Dashboard & Analytics

- Ticket statistics overview
- Quick action buttons
- Recent activity timeline
- User-specific data filtering

## 🛠 Technology Stack

### Backend

- **PHP 8.2**: Server-side scripting
- **MySQL**: Database management
- **Apache**: Web server
- **Composer**: Dependency management

### Frontend

- **Bootstrap 5.3.0**: CSS framework
- **jQuery 3.6.0**: JavaScript library
- **Font Awesome 6.0.0**: Icons
- **Bootstrap Icons 1.11.3**: Additional icons

### Libraries & Dependencies

- **Auth0 PHP SDK**: OAuth authentication
- **Medoo**: Database ORM
- **Guzzle HTTP**: HTTP client
- **PSR-7**: HTTP message interfaces

### Development Tools

- **Docker**: Containerization
- **Composer**: Package management

## 🚀 Installation

### Prerequisites

- PHP 8.2 or higher
- MySQL 5.7 or higher
- Apache web server
- Composer
- SSL certificate (for production)

### Step 1: Clone the Repository

```bash
git clone <repository-url>
cd crm
```

### Step 2: Install Dependencies

```bash
composer install
```

### Step 3: Database Setup

```bash
# Create database
mysql -u root -p -e "CREATE DATABASE crm CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# Import database schema
mysql -u root -p crm < database/tickets.sql
mysql -u root -p crm < database/notifications.sql
mysql -u root -p crm < database/ticket_comments.sql
```

### Step 4: Configure Environment

1. Copy `config.php` and update with your settings
2. Update database credentials in `database.php`
3. Configure Auth0 settings in `config.php`

### Step 5: Set Permissions

```bash
chmod -R 755 .
chmod -R 777 logs/
chmod -R 777 assets/uploads/
chmod -R 777 assets/user_avatars/
```

### Step 6: Docker Deployment (Optional)

```bash
# Build and run with Docker
docker build -t fayyaz-crm .
docker run -p 80:80 fayyaz-crm
```

## ⚙️ Configuration

### Environment Configuration (`config.php`)

```php
// Development/Production mode
define('IS_DEVELOPMENT', true);

// SSL Configuration
define('SSL_VERIFY', !IS_DEVELOPMENT);
define('SSL_CERT_PATH', __DIR__ . '/certs/cacert.pem');

// Auth0 Configuration
define('AUTH0_DOMAIN', 'your-domain.auth0.com');
define('AUTH0_CLIENT_ID', 'your-client-id');
define('AUTH0_CLIENT_SECRET', 'your-client-secret');
define('AUTH0_REDIRECT_URI', 'https://your-domain.com/callback.php');
define('AUTH0_COOKIE_SECRET', 'your-secret-key');
```

### Database Configuration (`database.php`)

```php
$database = new Medoo([
    'type' => 'mysql',
    'host' => 'localhost',
    'database' => 'crm',
    'username' => 'your-username',
    'password' => 'your-password',
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'port' => 3306,
    'prefix' => '',
    'logging' => true,
    'error' => PDO::ERRMODE_EXCEPTION
]);
```

## 🗄️ Database Schema

### Core Tables

#### Users Table

- `id`: Primary key
- `auth0_id`: Auth0 user identifier
- `email`: User email
- `name`: User full name
- `is_admin`: Admin privileges flag
- `created_at`: Account creation timestamp

#### Ticket Tables

Three main ticket types with similar structure:

**Estimate Tickets** (`estimate_tickets`)

- Customer information (name, address, email, contact)
- Package details and pricing
- Service dates and consultant information
- Priority and status tracking

**Supplier Tickets** (`supplier_tickets`)

- Payment and invoice management
- Currency handling
- File attachments (invoices, payment proofs)
- Due date tracking

**General Tickets** (`general_tickets`)

- General issue tracking
- Supporting documentation
- Subtype categorization

#### Notifications Table (`notifications`)

- User-specific notifications
- Ticket association
- Read/unread status
- Multiple notification types

#### Messages Table (`messages`)

- Real-time messaging system
- File attachment support
- Read status tracking
- Timestamp management

## 📡 API Documentation

### Authentication Endpoints

#### Login

- **URL**: `/login.php`
- **Method**: GET
- **Description**: Initiates Google OAuth flow
- **Redirect**: Auth0 login page

#### Callback

- **URL**: `/callback.php`
- **Method**: GET
- **Description**: Handles OAuth callback
- **Redirect**: Dashboard or login

#### Logout

- **URL**: `/logout.php`
- **Method**: GET
- **Description**: Clears session and redirects to Auth0 logout

### Ticket Management APIs

#### Create Ticket

- **URL**: `/api/create-ticket.php`
- **Method**: POST
- **Parameters**: Ticket type, priority, status, details
- **Response**: JSON with ticket ID and status

#### Get Ticket

- **URL**: `/api/get-ticket.php`
- **Method**: GET
- **Parameters**: Ticket ID, type
- **Response**: JSON with ticket details

#### Update Ticket

- **URL**: `/api/update-ticket.php`
- **Method**: POST
- **Parameters**: Ticket ID, updates
- **Response**: JSON with update status

### Messaging APIs

#### Send Message

- **URL**: `/api/messages_send.php`
- **Method**: POST
- **Parameters**: Recipient, message, attachments
- **Response**: JSON with message ID

#### Get Messages

- **URL**: `/api/messages_list.php`
- **Method**: GET
- **Parameters**: User ID, limit, offset
- **Response**: JSON with message list

#### Real-time Updates

- **URL**: `/api/messages_sse.php`
- **Method**: GET
- **Description**: Server-Sent Events for real-time updates

### User Management APIs

#### User List

- **URL**: `/api/user_list.php`
- **Method**: GET
- **Response**: JSON with user list

## 📁 File Structure

```
crm/
├── api/                    # API endpoints
│   ├── create-ticket.php
│   ├── get-ticket.php
│   ├── update-ticket.php
│   ├── messages_send.php
│   ├── messages_list.php
│   ├── messages_sse.php
│   ├── user_list.php
│   └── mark_messages_read.php
├── assets/                 # Static assets
│   ├── css/
│   │   ├── style.css
│   │   ├── dashboard.css
│   │   └── login.css
│   ├── images/
│   ├── uploads/           # File uploads
│   └── user_avatars/      # User profile images
├── components/             # Reusable UI components
│   ├── navbar.php
│   ├── sidebar.php
│   └── bottom_navbar.php
├── database/              # Database schema files
│   ├── tickets.sql
│   ├── notifications.sql
│   └── ticket_comments.sql
├── functions/             # Helper functions
│   └── notifications.php
├── logs/                  # Application logs
│   └── auth.log
├── vendor/                # Composer dependencies
├── config.php            # Configuration file
├── database.php          # Database connection
├── function.php          # Utility functions
├── login.php             # Authentication
├── dashboard.php         # Main dashboard
├── tickets.php           # Ticket management
├── messages.php          # Messaging system
├── notifications.php     # Notification center
├── timeline.php          # Activity timeline
├── callback.php          # OAuth callback
├── logout.php            # Logout handler
├── composer.json         # Dependencies
├── Dockerfile            # Docker configuration
└── README.md            # This file
```

## 🔐 Authentication

### Auth0 Integration

The system uses Auth0 for Google OAuth authentication:

1. **Login Flow**:

   - User clicks "Sign in with Google"
   - Redirected to Auth0 login page
   - User authenticates with Google
   - Auth0 redirects to callback URL
   - System creates/updates user in database
   - User redirected to dashboard

2. **Session Management**:

   - Secure session cookies
   - Automatic session validation
   - Role-based access control

3. **Security Features**:
   - SSL/TLS encryption
   - Secure cookie handling
   - Session timeout management
   - CSRF protection

## 🚀 Deployment

### Production Deployment

#### 1. Server Requirements

- PHP 8.2+
- MySQL 5.7+
- Apache with mod_rewrite
- SSL certificate

#### 2. Environment Setup

```bash
# Set production mode
sed -i 's/IS_DEVELOPMENT.*/IS_DEVELOPMENT", false);/' config.php

# Enable SSL verification
sed -i 's/SSL_VERIFY.*/SSL_VERIFY", true);/' config.php
```

#### 3. Security Configuration

```apache
# .htaccess for security
RewriteEngine On
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]

# Security headers
Header always set X-Content-Type-Options nosniff
Header always set X-Frame-Options DENY
Header always set X-XSS-Protection "1; mode=block"
```

#### 4. Database Optimization

```sql
-- Create indexes for performance
CREATE INDEX idx_tickets_user_id ON estimate_tickets(user_id);
CREATE INDEX idx_tickets_status ON estimate_tickets(status);
CREATE INDEX idx_notifications_user_id ON notifications(user_id);
CREATE INDEX idx_messages_recipient ON messages(recipient_id);
```

### Docker Deployment

```bash
# Build production image
docker build -t fayyaz-crm:latest .

# Run with environment variables
docker run -d \
  -p 80:80 \
  -p 443:443 \
  -e DB_HOST=your-db-host \
  -e DB_NAME=crm \
  -e DB_USER=your-db-user \
  -e DB_PASS=your-db-pass \
  fayyaz-crm:latest
```

## 🔧 Troubleshooting

### Common Issues

#### 1. Authentication Problems

**Issue**: Users can't log in
**Solution**:

- Check Auth0 configuration in `config.php`
- Verify redirect URI matches Auth0 settings
- Check SSL certificate validity
- Review auth logs in `logs/auth.log`

#### 2. Database Connection Errors

**Issue**: Database connection fails
**Solution**:

- Verify database credentials in `database.php`
- Check MySQL service status
- Ensure database exists and user has permissions
- Test connection manually

#### 3. File Upload Issues

**Issue**: Files not uploading
**Solution**:

- Check directory permissions (755 for directories, 644 for files)
- Verify upload_max_filesize in php.ini
- Check available disk space
- Review error logs

#### 4. Real-time Features Not Working

**Issue**: Messages not updating in real-time
**Solution**:

- Check Server-Sent Events configuration
- Verify browser supports SSE
- Check network connectivity
- Review JavaScript console for errors

### Log Files

- **Authentication**: `logs/auth.log`
- **Apache**: `/var/log/apache2/error.log`
- **PHP**: `/var/log/php_errors.log`

### Performance Optimization

1. **Database**: Add indexes for frequently queried columns
2. **Caching**: Implement Redis for session storage
3. **CDN**: Use CDN for static assets
4. **Compression**: Enable gzip compression
5. **Monitoring**: Set up application monitoring

## 🤝 Contributing

### Development Setup

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Test thoroughly
5. Submit a pull request

### Coding Standards

- Follow PSR-12 coding standards
- Add comments for complex logic
- Use meaningful variable names
- Write unit tests for new features

### Testing

```bash
# Run PHP syntax check
find . -name "*.php" -exec php -l {} \;

# Test database connection
php check_db.php

# Validate configuration
php -r "require 'config.php'; echo 'Config OK';"
```

## 📄 License

Copyright (c) 2025 | Inncelerator

This project is proprietary software developed for Fayyaz Travels.

## 📞 Support

For technical support or questions:

- Email: manish.inncelerator@gmail.com
- Phone: +91-8303095447
- Documentation: https://docs.fayyaztravels.com

---

**Version**: 1.0.0  
**Last Updated**: January 2025  
**Author**: Manish Shahi (Inncelerator)
