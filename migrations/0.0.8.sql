-- Migration: 0.0.8
-- --------------------------------------------------------
-- Add comment with version to every table
-- --------------------------------------------------------
ALTER TABLE `service_proxmox_server` COMMENT = '0.0.8';
ALTER TABLE `service_proxmox` COMMENT = '0.0.8';
ALTER TABLE `service_proxmox_users` COMMENT = '0.0.8';
ALTER TABLE `service_proxmox_vm_config_template` COMMENT = '0.0.8';
ALTER TABLE `service_proxmox_storageclass` COMMENT = '0.0.8';
ALTER TABLE `service_proxmox_storage` COMMENT = '0.0.8';
ALTER TABLE `service_proxmox_vm_storage_template` COMMENT = '0.0.8';
ALTER TABLE `service_proxmox_vm_network_template` COMMENT = '0.0.8';
ALTER TABLE `service_proxmox_lxc_template` COMMENT = '0.0.8';