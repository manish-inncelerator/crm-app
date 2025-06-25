-- Common fields for all tickets
CREATE TABLE
    IF NOT EXISTS estimate_tickets (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        priority ENUM ('LOW', 'MEDIUM', 'HIGH', 'URGENT') NOT NULL,
        status ENUM ('OPEN', 'IN_PROGRESS', 'RESOLVED', 'CLOSED') NOT NULL DEFAULT 'OPEN',
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
        FOREIGN KEY (user_id) REFERENCES users (id)
    );

CREATE TABLE
    IF NOT EXISTS supplier_tickets (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        priority ENUM ('LOW', 'MEDIUM', 'HIGH', 'URGENT') NOT NULL,
        status ENUM ('OPEN', 'IN_PROGRESS', 'RESOLVED', 'CLOSED') NOT NULL DEFAULT 'OPEN',
        travel_date DATE NOT NULL,
        due_date DATE NOT NULL,
        supplier_invoice_currency VARCHAR(10) NOT NULL,
        supplier_local_currency VARCHAR(10) NOT NULL,
        payment_type ENUM ('Deposit', 'Full Payment', 'Balance Payment') NOT NULL,
        bank_details TEXT NOT NULL,
        supplier_invoice_path VARCHAR(255),
        customer_invoice_path VARCHAR(255),
        payment_proof_path VARCHAR(255),
        supplier_message TEXT,
        estimated_time VARCHAR(50),
        created_at DATETIME NOT NULL,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users (id)
    );

CREATE TABLE
    IF NOT EXISTS general_tickets (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        priority ENUM ('LOW', 'MEDIUM', 'HIGH', 'URGENT') NOT NULL,
        status ENUM ('OPEN', 'IN_PROGRESS', 'RESOLVED', 'CLOSED') NOT NULL DEFAULT 'OPEN',
        description TEXT NOT NULL,
        supporting_image_path VARCHAR(255),
        ticket_subtype VARCHAR(100),
        estimated_time VARCHAR(50),
        created_at DATETIME NOT NULL,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users (id)
    );

-- Add estimated_time column to existing tables if they don't have it
ALTER TABLE estimate_tickets
ADD COLUMN IF NOT EXISTS estimated_time VARCHAR(50);

ALTER TABLE supplier_tickets
ADD COLUMN IF NOT EXISTS estimated_time VARCHAR(50);

ALTER TABLE general_tickets
ADD COLUMN IF NOT EXISTS estimated_time VARCHAR(50);