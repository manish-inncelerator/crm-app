# Deployment Guide

## Overview

This guide provides step-by-step instructions for deploying the Fayyaz Travels CRM system in various environments, from development to production.

## Prerequisites

### System Requirements

#### Minimum Requirements
- **PHP**: 8.2 or higher
- **MySQL**: 5.7 or higher
- **Apache**: 2.4 or higher
- **RAM**: 2GB minimum
- **Storage**: 10GB minimum
- **SSL Certificate**: Required for production

#### Recommended Requirements
- **PHP**: 8.2+
- **MySQL**: 8.0+
- **Apache**: 2.4+
- **RAM**: 4GB+
- **Storage**: 50GB+ SSD
- **SSL Certificate**: Valid SSL certificate

### Software Dependencies
- **Composer**: For PHP dependency management
- **Git**: For version control
- **Docker**: For containerized deployment (optional)

## Development Environment Setup

### 1. Local Development

#### Step 1: Install Local Stack
```bash
# Install XAMPP, WAMP, or MAMP
# Or use individual installations:
# - PHP 8.2
# - MySQL 8.0
# - Apache 2.4
```

#### Step 2: Clone Repository
```bash
git clone <repository-url>
cd crm
```

#### Step 3: Install Dependencies
```bash
composer install
```

#### Step 4: Configure Database
```bash
# Create database
mysql -u root -p -e "CREATE DATABASE crm CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# Import schema
mysql -u root -p crm < database/tickets.sql
mysql -u root -p crm < database/notifications.sql
mysql -u root -p crm < database/ticket_comments.sql
```

#### Step 5: Configure Environment
```bash
# Copy and edit configuration files
cp config.php.example config.php
cp database.php.example database.php

# Edit config.php
nano config.php
```

#### Step 6: Set Permissions
```bash
chmod -R 755 .
chmod -R 777 logs/
chmod -R 777 assets/uploads/
chmod -R 777 assets/user_avatars/
```

### 2. Docker Development

#### Step 1: Install Docker
```bash
# Install Docker and Docker Compose
curl -fsSL https://get.docker.com -o get-docker.sh
sh get-docker.sh
```

#### Step 2: Create Docker Compose File
```yaml
# docker-compose.yml
version: '3.8'

services:
  web:
    build: .
    ports:
      - "80:80"
    volumes:
      - .:/var/www/html
    depends_on:
      - db
    environment:
      - DB_HOST=db
      - DB_NAME=crm
      - DB_USER=root
      - DB_PASS=password

  db:
    image: mysql:8.0
    environment:
      MYSQL_ROOT_PASSWORD: password
      MYSQL_DATABASE: crm
    volumes:
      - mysql_data:/var/lib/mysql
      - ./database:/docker-entrypoint-initdb.d
    ports:
      - "3306:3306"

volumes:
  mysql_data:
```

#### Step 3: Run with Docker
```bash
docker-compose up -d
```

## Staging Environment

### 1. Server Preparation

#### Step 1: Server Setup
```bash
# Update system
sudo apt update && sudo apt upgrade -y

# Install required packages
sudo apt install -y apache2 mysql-server php8.2 php8.2-mysql php8.2-curl php8.2-mbstring php8.2-xml php8.2-zip unzip git composer
```

#### Step 2: Configure Apache
```bash
# Enable required modules
sudo a2enmod rewrite
sudo a2enmod ssl
sudo a2enmod headers

# Create virtual host
sudo nano /etc/apache2/sites-available/crm-staging.conf
```

```apache
<VirtualHost *:80>
    ServerName staging.crm.fayyaz.travel
    DocumentRoot /var/www/crm-staging
    
    <Directory /var/www/crm-staging>
        AllowOverride All
        Require all granted
    </Directory>
    
    ErrorLog ${APACHE_LOG_DIR}/crm-staging_error.log
    CustomLog ${APACHE_LOG_DIR}/crm-staging_access.log combined
</VirtualHost>
```

#### Step 3: Configure MySQL
```bash
# Secure MySQL installation
sudo mysql_secure_installation

# Create database and user
sudo mysql -u root -p
```

```sql
CREATE DATABASE crm_staging CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'crm_user'@'localhost' IDENTIFIED BY 'secure_password';
GRANT ALL PRIVILEGES ON crm_staging.* TO 'crm_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

### 2. Application Deployment

#### Step 1: Deploy Code
```bash
# Create application directory
sudo mkdir -p /var/www/crm-staging
sudo chown $USER:$USER /var/www/crm-staging

# Clone repository
git clone <repository-url> /var/www/crm-staging
cd /var/www/crm-staging

# Install dependencies
composer install --no-dev --optimize-autoloader
```

#### Step 2: Configure Application
```bash
# Set production mode
sed -i 's/IS_DEVELOPMENT.*/IS_DEVELOPMENT", false);/' config.php

# Update database configuration
sed -i 's/localhost/127.0.0.1/' database.php
sed -i 's/root/crm_user/' database.php
sed -i 's/Inncelerator@2025@#/secure_password/' database.php
```

#### Step 3: Set Permissions
```bash
# Set proper permissions
sudo chown -R www-data:www-data /var/www/crm-staging
sudo chmod -R 755 /var/www/crm-staging
sudo chmod -R 777 /var/www/crm-staging/logs
sudo chmod -R 777 /var/www/crm-staging/assets/uploads
sudo chmod -R 777 /var/www/crm-staging/assets/user_avatars
```

#### Step 4: Enable Site
```bash
sudo a2ensite crm-staging
sudo systemctl reload apache2
```

## Production Environment

### 1. Server Infrastructure

#### Step 1: Choose Cloud Provider
- **AWS**: EC2 with RDS
- **Google Cloud**: Compute Engine with Cloud SQL
- **Azure**: Virtual Machine with Azure Database
- **DigitalOcean**: Droplet with Managed Database

#### Step 2: Server Specifications
```bash
# Minimum production specs
- CPU: 2 vCPUs
- RAM: 4GB
- Storage: 50GB SSD
- OS: Ubuntu 22.04 LTS
```

### 2. Security Setup

#### Step 1: Firewall Configuration
```bash
# Configure UFW firewall
sudo ufw allow ssh
sudo ufw allow 80
sudo ufw allow 443
sudo ufw enable
```

#### Step 2: SSL Certificate
```bash
# Install Certbot
sudo apt install certbot python3-certbot-apache

# Obtain SSL certificate
sudo certbot --apache -d crm.fayyaz.travel -d www.crm.fayyaz.travel
```

#### Step 3: Security Headers
```apache
# Add to Apache configuration
Header always set X-Content-Type-Options nosniff
Header always set X-Frame-Options DENY
Header always set X-XSS-Protection "1; mode=block"
Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains"
```

### 3. Database Setup

#### Step 1: MySQL Configuration
```bash
# Edit MySQL configuration
sudo nano /etc/mysql/mysql.conf.d/mysqld.cnf
```

```ini
[mysqld]
# Performance settings
innodb_buffer_pool_size = 1G
innodb_log_file_size = 256M
max_connections = 200
query_cache_size = 64M

# Security settings
bind-address = 127.0.0.1
```

#### Step 2: Database Optimization
```sql
-- Create indexes for performance
CREATE INDEX idx_tickets_user_id ON estimate_tickets(user_id);
CREATE INDEX idx_tickets_status ON estimate_tickets(status);
CREATE INDEX idx_notifications_user_id ON notifications(user_id);
CREATE INDEX idx_messages_recipient ON messages(recipient_id);

-- Optimize tables
OPTIMIZE TABLE estimate_tickets;
OPTIMIZE TABLE supplier_tickets;
OPTIMIZE TABLE general_tickets;
```

### 4. Application Deployment

#### Step 1: Deploy with Git
```bash
# Set up deployment user
sudo adduser deploy
sudo usermod -aG www-data deploy

# Clone repository
sudo -u deploy git clone <repository-url> /var/www/crm
cd /var/www/crm

# Install dependencies
sudo -u deploy composer install --no-dev --optimize-autoloader
```

#### Step 2: Environment Configuration
```bash
# Create production configuration
sudo -u deploy cp config.php.example config.php
sudo -u deploy cp database.php.example database.php

# Edit configurations
sudo -u deploy nano config.php
sudo -u deploy nano database.php
```

#### Step 3: Database Migration
```bash
# Import database schema
mysql -u crm_user -p crm < database/tickets.sql
mysql -u crm_user -p crm < database/notifications.sql
mysql -u crm_user -p crm < database/ticket_comments.sql
```

### 5. Monitoring and Logging

#### Step 1: Application Monitoring
```bash
# Install monitoring tools
sudo apt install -y htop iotop nethogs

# Set up log rotation
sudo nano /etc/logrotate.d/crm
```

```conf
/var/www/crm/logs/*.log {
    daily
    missingok
    rotate 30
    compress
    delaycompress
    notifempty
    create 644 www-data www-data
}
```

#### Step 2: Performance Monitoring
```bash
# Install New Relic or similar
# Configure application performance monitoring
# Set up alerting for critical metrics
```

## CI/CD Pipeline

### 1. GitHub Actions

#### Step 1: Create Workflow
```yaml
# .github/workflows/deploy.yml
name: Deploy to Production

on:
  push:
    branches: [ main ]

jobs:
  deploy:
    runs-on: ubuntu-latest
    
    steps:
    - uses: actions/checkout@v3
    
    - name: Setup PHP
      uses: shivammathur/setup-php@v2
      with:
        php-version: '8.2'
    
    - name: Install dependencies
      run: composer install --no-dev --optimize-autoloader
    
    - name: Deploy to server
      uses: appleboy/ssh-action@v0.1.5
      with:
        host: ${{ secrets.HOST }}
        username: ${{ secrets.USERNAME }}
        key: ${{ secrets.KEY }}
        script: |
          cd /var/www/crm
          git pull origin main
          composer install --no-dev --optimize-autoloader
          sudo systemctl reload apache2
```

### 2. Automated Testing

#### Step 1: Unit Tests
```bash
# Install PHPUnit
composer require --dev phpunit/phpunit

# Create test files
mkdir tests
```

#### Step 2: Integration Tests
```php
// tests/DatabaseTest.php
class DatabaseTest extends TestCase
{
    public function testDatabaseConnection()
    {
        // Test database connectivity
    }
    
    public function testTicketCreation()
    {
        // Test ticket creation functionality
    }
}
```

## Backup and Recovery

### 1. Database Backup

#### Step 1: Automated Backups
```bash
#!/bin/bash
# backup.sh

DATE=$(date +%Y%m%d_%H%M%S)
BACKUP_DIR="/backups/database"
DB_NAME="crm"
DB_USER="crm_user"
DB_PASS="secure_password"

# Create backup
mysqldump -u $DB_USER -p$DB_PASS $DB_NAME > $BACKUP_DIR/backup_$DATE.sql

# Compress backup
gzip $BACKUP_DIR/backup_$DATE.sql

# Keep only last 30 days
find $BACKUP_DIR -name "backup_*.sql.gz" -mtime +30 -delete
```

#### Step 2: File Backup
```bash
#!/bin/bash
# file_backup.sh

DATE=$(date +%Y%m%d_%H%M%S)
BACKUP_DIR="/backups/files"
APP_DIR="/var/www/crm"

# Backup uploads and logs
tar -czf $BACKUP_DIR/files_$DATE.tar.gz \
    $APP_DIR/assets/uploads \
    $APP_DIR/assets/user_avatars \
    $APP_DIR/logs
```

### 2. Recovery Procedures

#### Step 1: Database Recovery
```bash
# Restore from backup
mysql -u crm_user -p crm < backup_20250110.sql

# Point-in-time recovery
mysqlbinlog --start-datetime="2025-01-10 10:00:00" \
           --stop-datetime="2025-01-10 11:00:00" \
           mysql-bin.000001 | mysql -u root -p
```

#### Step 2: Application Recovery
```bash
# Restore application files
tar -xzf files_20250110.tar.gz -C /var/www/crm/

# Restore permissions
sudo chown -R www-data:www-data /var/www/crm
sudo chmod -R 755 /var/www/crm
```

## Performance Optimization

### 1. PHP Optimization

#### Step 1: PHP Configuration
```ini
; /etc/php/8.2/apache2/php.ini
memory_limit = 256M
max_execution_time = 30
upload_max_filesize = 50M
post_max_size = 50M
opcache.enable = 1
opcache.memory_consumption = 128
opcache.max_accelerated_files = 4000
```

#### Step 2: Apache Optimization
```apache
# /etc/apache2/mods-available/mpm_prefork.conf
<IfModule mpm_prefork_module>
    StartServers          5
    MinSpareServers       5
    MaxSpareServers      10
    MaxRequestWorkers    150
    MaxConnectionsPerChild   0
</IfModule>
```

### 2. Caching Strategy

#### Step 1: Redis Setup
```bash
# Install Redis
sudo apt install redis-server

# Configure Redis
sudo nano /etc/redis/redis.conf
```

#### Step 2: Application Caching
```php
// Implement Redis caching for sessions and data
$redis = new Redis();
$redis->connect('127.0.0.1', 6379);
```

## Security Hardening

### 1. Server Security

#### Step 1: SSH Hardening
```bash
# Edit SSH configuration
sudo nano /etc/ssh/sshd_config
```

```conf
Port 2222
PermitRootLogin no
PasswordAuthentication no
PubkeyAuthentication yes
AllowUsers deploy
```

#### Step 2: Fail2ban Setup
```bash
# Install Fail2ban
sudo apt install fail2ban

# Configure for Apache and SSH
sudo nano /etc/fail2ban/jail.local
```

### 2. Application Security

#### Step 1: Input Validation
```php
// Implement comprehensive input validation
function validateInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}
```

#### Step 2: CSRF Protection
```php
// Implement CSRF tokens
session_start();
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
```

## Monitoring and Alerting

### 1. System Monitoring

#### Step 1: Install Monitoring Tools
```bash
# Install monitoring packages
sudo apt install -y htop iotop nethogs

# Set up log monitoring
sudo apt install -y logwatch
```

#### Step 2: Set Up Alerts
```bash
# Create monitoring script
#!/bin/bash
# monitor.sh

# Check disk space
DISK_USAGE=$(df / | awk 'NR==2 {print $5}' | sed 's/%//')
if [ $DISK_USAGE -gt 80 ]; then
    echo "High disk usage: $DISK_USAGE%" | mail -s "Server Alert" admin@fyyz.link
fi

# Check memory usage
MEM_USAGE=$(free | awk 'NR==2{printf "%.2f", $3*100/$2}')
if (( $(echo "$MEM_USAGE > 80" | bc -l) )); then
    echo "High memory usage: $MEM_USAGE%" | mail -s "Server Alert" admin@fyyz.link
fi
```

### 2. Application Monitoring

#### Step 1: Error Logging
```php
// Enhanced error logging
function logError($error, $context = []) {
    $logEntry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'error' => $error,
        'context' => $context,
        'user_id' => $_SESSION['user_id'] ?? null,
        'ip' => $_SERVER['REMOTE_ADDR']
    ];
    
    file_put_contents(
        __DIR__ . '/logs/errors.log',
        json_encode($logEntry) . PHP_EOL,
        FILE_APPEND
    );
}
```

#### Step 2: Performance Monitoring
```php
// Performance tracking
function trackPerformance($operation, $startTime) {
    $duration = microtime(true) - $startTime;
    
    $logEntry = [
        'operation' => $operation,
        'duration' => $duration,
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    file_put_contents(
        __DIR__ . '/logs/performance.log',
        json_encode($logEntry) . PHP_EOL,
        FILE_APPEND
    );
}
```

## Troubleshooting

### Common Issues

#### 1. Database Connection Issues
```bash
# Check MySQL status
sudo systemctl status mysql

# Test connection
mysql -u crm_user -p -e "SELECT 1;"

# Check MySQL logs
sudo tail -f /var/log/mysql/error.log
```

#### 2. Apache Issues
```bash
# Check Apache status
sudo systemctl status apache2

# Check Apache logs
sudo tail -f /var/log/apache2/error.log

# Test configuration
sudo apache2ctl configtest
```

#### 3. PHP Issues
```bash
# Check PHP version
php -v

# Check PHP modules
php -m

# Test PHP configuration
php -r "phpinfo();"
```

### Performance Issues

#### 1. Slow Database Queries
```sql
-- Enable slow query log
SET GLOBAL slow_query_log = 'ON';
SET GLOBAL long_query_time = 2;

-- Check slow queries
SELECT * FROM mysql.slow_log ORDER BY start_time DESC LIMIT 10;
```

#### 2. Memory Issues
```bash
# Check memory usage
free -h

# Check PHP memory usage
ps aux | grep php
```

## Maintenance Procedures

### 1. Regular Maintenance

#### Weekly Tasks
```bash
# Update system packages
sudo apt update && sudo apt upgrade -y

# Optimize database tables
mysql -u crm_user -p crm -e "OPTIMIZE TABLE estimate_tickets, supplier_tickets, general_tickets;"

# Clean old log files
find /var/www/crm/logs -name "*.log" -mtime +30 -delete
```

#### Monthly Tasks
```bash
# Review and update SSL certificates
sudo certbot renew

# Update application dependencies
composer update

# Review security logs
sudo fail2ban-client status
```

### 2. Emergency Procedures

#### System Recovery
```bash
# Emergency restart
sudo systemctl restart apache2
sudo systemctl restart mysql

# Check services
sudo systemctl status apache2 mysql
```

#### Data Recovery
```bash
# Emergency database backup
mysqldump -u crm_user -p crm > emergency_backup.sql

# Restore from latest backup
mysql -u crm_user -p crm < backup_$(date +%Y%m%d).sql
```

This comprehensive deployment guide ensures a robust, secure, and scalable production environment for the Fayyaz Travels CRM system. 