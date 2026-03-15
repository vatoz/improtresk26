-- Add 'approved' status to registrations.payment_status enum

ALTER TABLE registrations
    MODIFY COLUMN `payment_status`
        ENUM('pending','approved','paid','cancelled','refunded')
        NOT NULL DEFAULT 'pending';
