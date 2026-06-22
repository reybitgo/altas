-- Migration 028: Change package_pv_rate from a percentage of entry_fee
-- to a direct absolute PV value.
--
-- Before: package_pv_rate = 100 (meaning 100% of entry_fee)
-- After:  package_pv_rate = 10000 (meaning 10,000 PV directly)
--
-- Formula conversion: new_value = old_value × entry_fee / 100

ALTER TABLE packages
  MODIFY COLUMN package_pv_rate DECIMAL(14,2) NOT NULL DEFAULT 10000.00
    COMMENT 'Absolute PV amount (not a percentage) — basis for direct/indirect/DFI/binary PV';

UPDATE packages SET package_pv_rate = package_pv_rate * entry_fee / 100;
