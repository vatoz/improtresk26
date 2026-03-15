-- Add 'notpaid' status to registrations.payment_status enum


ALTER TABLE registrations
    MODIFY COLUMN `payment_status`
        ENUM('pending','approved','paid','notpaid','cancelled','refunded')
        NOT NULL DEFAULT 'pending';
