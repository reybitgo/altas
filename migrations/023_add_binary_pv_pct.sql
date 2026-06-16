-- Migration 023: Separate binary PV allocation from package PV.
--
-- package_pv_rate continues to drive direct/indirect/DFI PV basis.
-- binary_pv_pct determines how much of the entry fee flows into the binary tree.
-- Default 20% means a ₱10,000 package allocates 2,000 PV to binary legs.

ALTER TABLE packages
  ADD COLUMN binary_pv_pct DECIMAL(5,2) NOT NULL DEFAULT 20.00
    COMMENT 'Percentage of entry fee that becomes binary PV'
  AFTER package_pv_rate;
