-- Remove all unique constraints from the registrations table.
-- Drops:
--   unique_user_workshop_status  (user_id, workshop_id, payment_status) – added in migration 013
--   variable_symbol              (variable_symbol column inline UNIQUE)  – added in migration 003

ALTER TABLE `registrations`
	ADD INDEX `unique_user_workshop_status` (`user_id`, `workshop_id`, `payment_status`),
	DROP INDEX `unique_user_workshop_status`,    DROP INDEX `variable_symbol`;
