-- ONLY for an EXISTING database whose `transactions` table has no receipt_image column
-- (the old payment_db.sql / system_track dumps never defined it, but the code writes it).
-- Fresh installs from schema.sql already have it. If you get "Duplicate column name", you are fine - skip this file.
ALTER TABLE `transactions` ADD COLUMN `receipt_image` VARCHAR(255) NULL DEFAULT NULL AFTER `date`;
