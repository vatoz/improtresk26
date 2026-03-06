-- Add 'upgradable' status to registrations.payment_status enum
-- 'upgradable' means a spot opened in a higher-priority workshop that conflicts with an
-- already-approved registration; the user must decide whether to accept the upgrade
-- (cancelling the conflicting approved registration) or decline (cancel this row).

ALTER TABLE registrations
    MODIFY COLUMN `payment_status`
        ENUM('pending','approved','paid','notpaid','upgradable','cancelled','refunded')
        NOT NULL DEFAULT 'pending';
