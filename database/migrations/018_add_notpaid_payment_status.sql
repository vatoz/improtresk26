-- Add 'notpaid' status to registrations.payment_status enum
-- 'notpaid' marks registrations where payment was not received and the spot was not released via lottery

ALTER TABLE registrations
    MODIFY COLUMN `payment_status`
        ENUM('pending','approved','paid','notpaid','cancelled','refunded')
        NOT NULL DEFAULT 'pending';
