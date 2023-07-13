-- Migration: 0.0.7
-- --------------------------------------------------------
--  alter table service_proxmox_server to add field port
-- --------------------------------------------------------
ALTER TABLE `service_proxmox_server` ADD COLUMN `port` varchar(255) DEFAULT NULL AFTER `hostname`;

-- --------------------------------------------------------
-- alter service_proxmox_qemu_template to add auto increment to id, and add vmid field after id
-- --------------------------------------------------------
ALTER TABLE `service_proxmox_qemu_template` CHANGE `id` `id` INT(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `service_proxmox_qemu_template` ADD `vmid` INT(11) NOT NULL AFTER `id`;
