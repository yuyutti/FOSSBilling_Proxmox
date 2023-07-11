-- Migration: 0.0.7
-- --------------------------------------------------------
--  alter table service_proxmox_server to add field port
-- --------------------------------------------------------
ALTER TABLE `service_proxmox_server` ADD COLUMN `port` varchar(255) DEFAULT NULL AFTER `hostname`;

-- --------------------------------------------------------