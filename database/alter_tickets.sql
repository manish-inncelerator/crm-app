-- Add estimated_time column to ticket tables
ALTER TABLE estimate_tickets
ADD COLUMN IF NOT EXISTS estimated_time VARCHAR(50);

ALTER TABLE supplier_tickets
ADD COLUMN IF NOT EXISTS estimated_time VARCHAR(50);

ALTER TABLE general_tickets
ADD COLUMN IF NOT EXISTS estimated_time VARCHAR(50);

-- Add index for better performance when filtering by estimated_time
ALTER TABLE estimate_tickets ADD INDEX idx_estimated_time (estimated_time);

ALTER TABLE supplier_tickets ADD INDEX idx_estimated_time (estimated_time);

ALTER TABLE general_tickets ADD INDEX idx_estimated_time (estimated_time);