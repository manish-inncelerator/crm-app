# Database Schema Documentation

## Overview

The Fayyaz Travels CRM system uses MySQL as its primary database. The schema is designed to handle ticket management, user authentication, messaging, and notifications efficiently.

## Database Configuration

### Connection Details
- **Database Type**: MySQL
- **Character Set**: utf8mb4
- **Collation**: utf8mb4_unicode_ci
- **Port**: 3306 (default)

### Connection Parameters
```php
$database = new Medoo([
    'type' => 'mysql',
    'host' => 'localhost',
    'database' => 'crm',
    'username' => 'root',
    'password' => 'Inncelerator@2025@#',
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'port' => 3306,
    'prefix' => '',
    'logging' => true,
    'error' => PDO::ERRMODE_EXCEPTION
]);
```

## Table Structure

### 1. Users Table

**Purpose**: Stores user information and authentication data

```sql
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    auth0_id VARCHAR(255) NOT NULL UNIQUE,
    email VARCHAR(255) NOT NULL UNIQUE,
    name VARCHAR(255) NOT NULL,
    is_admin BOOLEAN NOT NULL DEFAULT FALSE,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_auth0_id (auth0_id),
    INDEX idx_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Fields**:
- `id`: Primary key, auto-incrementing integer
- `auth0_id`: Unique Auth0 user identifier
- `email`: User's email address (unique)
- `name`: User's full name
- `is_admin`: Boolean flag for admin privileges
- `created_at`: Account creation timestamp
- `updated_at`: Last update timestamp

### 2. Estimate Tickets Table

**Purpose**: Manages travel package estimates and quotations

```sql
CREATE TABLE estimate_tickets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    priority ENUM('LOW', 'MEDIUM', 'HIGH', 'URGENT') NOT NULL,
    status ENUM('OPEN', 'IN_PROGRESS', 'RESOLVED', 'CLOSED') NOT NULL DEFAULT 'OPEN',
    customer_name VARCHAR(255) NOT NULL,
    billing_address TEXT NOT NULL,
    email VARCHAR(255) NOT NULL,
    contact_number VARCHAR(50) NOT NULL,
    consultant_name VARCHAR(255) NOT NULL,
    service_date DATE NOT NULL,
    package_details TEXT NOT NULL,
    number_of_persons INT NOT NULL,
    rate_per_person DECIMAL(10, 2) NOT NULL,
    total_amount DECIMAL(10, 2) NOT NULL,
    description TEXT NOT NULL,
    estimate_message TEXT,
    estimated_time VARCHAR(50),
    created_at DATETIME NOT NULL,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_status (status),
    INDEX idx_priority (priority),
    INDEX idx_service_date (service_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Fields**:
- `id`: Primary key
- `user_id`: Foreign key to users table
- `priority`: Ticket priority level
- `status`: Current ticket status
- `customer_name`: Customer's full name
- `billing_address`: Customer's billing address
- `email`: Customer's email address
- `contact_number`: Customer's phone number
- `consultant_name`: Assigned consultant's name
- `service_date`: Planned service date
- `package_details`: Travel package description
- `number_of_persons`: Number of travelers
- `rate_per_person`: Cost per person
- `total_amount`: Total package cost
- `description`: Detailed description
- `estimate_message`: Additional estimate notes
- `estimated_time`: Estimated completion time
- `created_at`: Ticket creation timestamp
- `updated_at`: Last update timestamp

### 3. Supplier Tickets Table

**Purpose**: Manages supplier payments and invoice processing

```sql
CREATE TABLE supplier_tickets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    priority ENUM('LOW', 'MEDIUM', 'HIGH', 'URGENT') NOT NULL,
    status ENUM('OPEN', 'IN_PROGRESS', 'RESOLVED', 'CLOSED') NOT NULL DEFAULT 'OPEN',
    travel_date DATE NOT NULL,
    due_date DATE NOT NULL,
    supplier_invoice_currency VARCHAR(10) NOT NULL,
    supplier_local_currency VARCHAR(10) NOT NULL,
    payment_type ENUM('Deposit', 'Full Payment', 'Balance Payment') NOT NULL,
    bank_details TEXT NOT NULL,
    supplier_invoice_path VARCHAR(255),
    customer_invoice_path VARCHAR(255),
    payment_proof_path VARCHAR(255),
    supplier_message TEXT,
    estimated_time VARCHAR(50),
    created_at DATETIME NOT NULL,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_status (status),
    INDEX idx_due_date (due_date),
    INDEX idx_travel_date (travel_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Fields**:
- `id`: Primary key
- `user_id`: Foreign key to users table
- `priority`: Ticket priority level
- `status`: Current ticket status
- `travel_date`: Travel service date
- `due_date`: Payment due date
- `supplier_invoice_currency`: Currency for supplier invoice
- `supplier_local_currency`: Local currency for supplier
- `payment_type`: Type of payment required
- `bank_details`: Bank account information
- `supplier_invoice_path`: Path to supplier invoice file
- `customer_invoice_path`: Path to customer invoice file
- `payment_proof_path`: Path to payment proof file
- `supplier_message`: Additional supplier notes
- `estimated_time`: Estimated completion time
- `created_at`: Ticket creation timestamp
- `updated_at`: Last update timestamp

### 4. General Tickets Table

**Purpose**: Handles miscellaneous requests and general issues

```sql
CREATE TABLE general_tickets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    priority ENUM('LOW', 'MEDIUM', 'HIGH', 'URGENT') NOT NULL,
    status ENUM('OPEN', 'IN_PROGRESS', 'RESOLVED', 'CLOSED') NOT NULL DEFAULT 'OPEN',
    description TEXT NOT NULL,
    supporting_image_path VARCHAR(255),
    ticket_subtype VARCHAR(100),
    estimated_time VARCHAR(50),
    created_at DATETIME NOT NULL,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_status (status),
    INDEX idx_subtype (ticket_subtype)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Fields**:
- `id`: Primary key
- `user_id`: Foreign key to users table
- `priority`: Ticket priority level
- `status`: Current ticket status
- `description`: Detailed description of the issue
- `supporting_image_path`: Path to supporting image file
- `ticket_subtype`: Categorization of ticket type
- `estimated_time`: Estimated completion time
- `created_at`: Ticket creation timestamp
- `updated_at`: Last update timestamp

### 5. Notifications Table

**Purpose**: Manages user notifications and alerts

```sql
CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    ticket_id INT NOT NULL,
    ticket_type ENUM('estimate', 'supplier', 'general') NOT NULL,
    type ENUM('info', 'success', 'warning', 'error') NOT NULL DEFAULT 'info',
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    is_read BOOLEAN NOT NULL DEFAULT FALSE,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_is_read (is_read),
    INDEX idx_created_at (created_at),
    INDEX idx_ticket (ticket_id, ticket_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Fields**:
- `id`: Primary key
- `user_id`: Foreign key to users table
- `ticket_id`: Associated ticket ID
- `ticket_type`: Type of associated ticket
- `type`: Notification type (info, success, warning, error)
- `title`: Notification title
- `message`: Notification message content
- `is_read`: Read status flag
- `created_at`: Notification creation timestamp

### 6. Messages Table

**Purpose**: Stores real-time messaging data

```sql
CREATE TABLE messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sender_id INT NOT NULL,
    recipient_id INT NOT NULL,
    message TEXT NOT NULL,
    is_read BOOLEAN NOT NULL DEFAULT FALSE,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (recipient_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_sender (sender_id),
    INDEX idx_recipient (recipient_id),
    INDEX idx_created_at (created_at),
    INDEX idx_conversation (sender_id, recipient_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Fields**:
- `id`: Primary key
- `sender_id`: Foreign key to users table (sender)
- `recipient_id`: Foreign key to users table (recipient)
- `message`: Message content
- `is_read`: Read status flag
- `created_at`: Message creation timestamp

### 7. Message Attachments Table

**Purpose**: Stores file attachments for messages

```sql
CREATE TABLE message_attachments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    message_id INT NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_type VARCHAR(100) NOT NULL,
    file_size INT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (message_id) REFERENCES messages(id) ON DELETE CASCADE,
    INDEX idx_message_id (message_id),
    INDEX idx_file_type (file_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Fields**:
- `id`: Primary key
- `message_id`: Foreign key to messages table
- `file_name`: Original file name
- `file_path`: Server file path
- `file_type`: MIME type of file
- `file_size`: File size in bytes
- `created_at`: Attachment creation timestamp

## Indexes and Performance

### Primary Indexes
- All tables have auto-incrementing primary keys
- Foreign key relationships are properly indexed
- Status and priority fields are indexed for filtering

### Composite Indexes
```sql
-- For efficient conversation queries
CREATE INDEX idx_conversation ON messages(sender_id, recipient_id);

-- For ticket filtering by user and status
CREATE INDEX idx_user_status ON estimate_tickets(user_id, status);
CREATE INDEX idx_user_status ON supplier_tickets(user_id, status);
CREATE INDEX idx_user_status ON general_tickets(user_id, status);

-- For notification queries
CREATE INDEX idx_user_read ON notifications(user_id, is_read);
```

### Performance Considerations
1. **Query Optimization**: Use indexed columns in WHERE clauses
2. **Pagination**: Use LIMIT and OFFSET for large result sets
3. **Archiving**: Consider archiving old tickets and messages
4. **Partitioning**: For large datasets, consider table partitioning

## Data Relationships

### One-to-Many Relationships
- **Users → Tickets**: One user can have multiple tickets
- **Users → Messages**: One user can send/receive multiple messages
- **Users → Notifications**: One user can have multiple notifications

### Many-to-Many Relationships
- **Messages**: Users can have conversations with multiple users

## Data Integrity

### Foreign Key Constraints
- All foreign keys have CASCADE DELETE
- Ensures referential integrity
- Prevents orphaned records

### Check Constraints
- Priority and status fields use ENUM for data validation
- Email addresses are validated at application level
- File paths are sanitized before storage

## Backup and Recovery

### Backup Strategy
```bash
# Daily backup script
mysqldump -u root -p crm > backup_$(date +%Y%m%d).sql

# Weekly full backup
mysqldump -u root -p --all-databases > full_backup_$(date +%Y%m%d).sql
```

### Recovery Procedures
```bash
# Restore from backup
mysql -u root -p crm < backup_20250110.sql

# Point-in-time recovery (if using binary logs)
mysqlbinlog --start-datetime="2025-01-10 10:00:00" \
           --stop-datetime="2025-01-10 11:00:00" \
           mysql-bin.000001 | mysql -u root -p
```

## Security Considerations

### Data Protection
1. **Encryption**: Sensitive data should be encrypted at rest
2. **Access Control**: Database user has minimal required privileges
3. **Audit Logging**: Track database access and changes
4. **Input Validation**: All inputs are validated before database operations

### SQL Injection Prevention
- Use prepared statements via Medoo ORM
- Parameterized queries for all user inputs
- Input sanitization at application level

## Migration Scripts

### Adding New Columns
```sql
-- Add estimated_time to existing tables
ALTER TABLE estimate_tickets ADD COLUMN IF NOT EXISTS estimated_time VARCHAR(50);
ALTER TABLE supplier_tickets ADD COLUMN IF NOT EXISTS estimated_time VARCHAR(50);
ALTER TABLE general_tickets ADD COLUMN IF NOT EXISTS estimated_time VARCHAR(50);
```

### Updating Data Types
```sql
-- Update column types if needed
ALTER TABLE users MODIFY COLUMN auth0_id VARCHAR(255) NOT NULL;
ALTER TABLE estimate_tickets MODIFY COLUMN total_amount DECIMAL(10,2) NOT NULL;
```

## Monitoring and Maintenance

### Database Monitoring
```sql
-- Check table sizes
SELECT 
    table_name,
    ROUND(((data_length + index_length) / 1024 / 1024), 2) AS 'Size (MB)'
FROM information_schema.tables 
WHERE table_schema = 'crm'
ORDER BY (data_length + index_length) DESC;

-- Check slow queries
SHOW VARIABLES LIKE 'slow_query_log';
SHOW VARIABLES LIKE 'long_query_time';
```

### Maintenance Tasks
```sql
-- Optimize tables
OPTIMIZE TABLE estimate_tickets;
OPTIMIZE TABLE supplier_tickets;
OPTIMIZE TABLE general_tickets;
OPTIMIZE TABLE messages;
OPTIMIZE TABLE notifications;

-- Analyze table statistics
ANALYZE TABLE users;
ANALYZE TABLE estimate_tickets;
ANALYZE TABLE supplier_tickets;
ANALYZE TABLE general_tickets;
```

## Schema Evolution

### Version Control
- All schema changes are versioned
- Migration scripts are stored in `database/` directory
- Changes are tested in development before production

### Change Management
1. Create migration script
2. Test in development environment
3. Backup production database
4. Apply migration during maintenance window
5. Verify data integrity
6. Update documentation

This comprehensive database schema provides a solid foundation for the CRM system while maintaining performance, security, and scalability. 