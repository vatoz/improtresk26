-- Add 'skipped' status to registrations.payment_status enum
-- 'skipped' means the user's registration for this workshop was superseded by
-- a higher-priority registration that was accepted; the spot is freed and the
-- registration is treated as inactive (like 'cancelled') for capacity counting.

ALTER TABLE registrations
    MODIFY COLUMN `payment_status`
        ENUM('pending','approved','paid','notpaid','upgradable','cancelled','refunded','skipped')
        NOT NULL DEFAULT 'pending';
