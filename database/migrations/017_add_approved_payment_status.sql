-- Add 'approved' status to registrations.payment_status enum
-- 'approved' means the lottery selected this registration; user still needs to pay

ALTER TABLE registrations
    MODIFY COLUMN `payment_status`
        ENUM('pending','approved','paid','cancelled','refunded')
        NOT NULL DEFAULT 'pending';
