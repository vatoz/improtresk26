-- Replace the unique_user_workshop constraint to allow a user to re-register
-- for the same workshop after cancellation.
-- The new key enforces that a user cannot hold two registrations with the same
-- payment_status for the same workshop (e.g. two 'pending' rows), while still
-- permitting a new 'pending' row alongside an old 'cancelled' one.

ALTER TABLE registrations
    DROP INDEX `unique_user_workshop`,
    ADD UNIQUE KEY `unique_user_workshop_status` (`user_id`, `workshop_id`, `payment_status`);
