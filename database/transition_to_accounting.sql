-- Create suppliers table
CREATE TABLE IF NOT EXISTS suppliers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    onboarded BOOLEAN NOT NULL DEFAULT FALSE,
    contract_signed BOOLEAN NOT NULL DEFAULT FALSE,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert some default suppliers
INSERT INTO suppliers (name, onboarded, contract_signed) VALUES 
('Muhibbah', TRUE, TRUE),
('CTM', TRUE, TRUE),
('Flywire', TRUE, TRUE),
('Tazapay', TRUE, TRUE),
('Airwallex', TRUE, TRUE)
ON DUPLICATE KEY UPDATE name=VALUES(name);

-- Alter estimate_tickets
ALTER TABLE estimate_tickets 
ADD COLUMN IF NOT EXISTS booking_reference VARCHAR(50) AFTER id,
ADD COLUMN IF NOT EXISTS owner_id INT AFTER user_id,
ADD COLUMN IF NOT EXISTS expected_timeline DATETIME AFTER updated_at,
ADD COLUMN IF NOT EXISTS delay_reason TEXT AFTER expected_timeline,
ADD COLUMN IF NOT EXISTS supplier_id INT AFTER owner_id;

-- Alter supplier_tickets
ALTER TABLE supplier_tickets 
ADD COLUMN IF NOT EXISTS booking_reference VARCHAR(50) AFTER id,
ADD COLUMN IF NOT EXISTS owner_id INT AFTER user_id,
ADD COLUMN IF NOT EXISTS expected_timeline DATETIME AFTER updated_at,
ADD COLUMN IF NOT EXISTS delay_reason TEXT AFTER expected_timeline,
ADD COLUMN IF NOT EXISTS supplier_id INT AFTER owner_id;

-- Alter general_tickets
ALTER TABLE general_tickets 
ADD COLUMN IF NOT EXISTS booking_reference VARCHAR(50) AFTER id,
ADD COLUMN IF NOT EXISTS owner_id INT AFTER user_id,
ADD COLUMN IF NOT EXISTS expected_timeline DATETIME AFTER updated_at,
ADD COLUMN IF NOT EXISTS delay_reason TEXT AFTER expected_timeline;

-- Update status ENUMs (requires re-defining the column)
-- Since MySQL doesn't support easy ALTER for ENUM without re-definition, we'll do it carefully
ALTER TABLE estimate_tickets MODIFY COLUMN status ENUM('SUBMITTED', 'OPEN', 'IN_PROGRESS', 'PENDING_APPROVAL', 'APPROVED', 'REJECTED', 'PAID', 'OVERDUE', 'CLOSED') NOT NULL DEFAULT 'OPEN';
ALTER TABLE supplier_tickets MODIFY COLUMN status ENUM('SUBMITTED', 'OPEN', 'IN_PROGRESS', 'PENDING_APPROVAL', 'APPROVED', 'REJECTED', 'PAID', 'OVERDUE', 'CLOSED') NOT NULL DEFAULT 'OPEN';
ALTER TABLE general_tickets MODIFY COLUMN status ENUM('SUBMITTED', 'OPEN', 'IN_PROGRESS', 'PENDING_APPROVAL', 'APPROVED', 'REJECTED', 'PAID', 'OVERDUE', 'CLOSED') NOT NULL DEFAULT 'OPEN';

-- Add foreign keys
ALTER TABLE estimate_tickets ADD CONSTRAINT fk_estimate_owner FOREIGN KEY (owner_id) REFERENCES users(id);
ALTER TABLE estimate_tickets ADD CONSTRAINT fk_estimate_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers(id);

ALTER TABLE supplier_tickets ADD CONSTRAINT fk_supplier_owner FOREIGN KEY (owner_id) REFERENCES users(id);
ALTER TABLE supplier_tickets ADD CONSTRAINT fk_supplier_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers(id);

ALTER TABLE general_tickets ADD CONSTRAINT fk_general_owner FOREIGN KEY (owner_id) REFERENCES users(id);
