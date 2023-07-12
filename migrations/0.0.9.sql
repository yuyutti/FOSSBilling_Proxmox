-- Migration: 0.0.9
-- --------------------------------------------------------
--  add lxc config template table with a foreign key to service_proxmox_lxc_template
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `service_proxmox_lxc_config_template` (
            `id` bigint(20) NOT NULL AUTO_INCREMENT,
            `template_id` bigint(20) DEFAULT NULL,
            `cores` bigint(20) DEFAULT NULL,
            `description` varchar(255) DEFAULT NULL,
            `memory` bigint(20) DEFAULT NULL,
            `swap` bigint(20) DEFAULT NULL,
            `ostemplate` varchar(255) DEFAULT NULL,
            `onboot` TINYINT(1) DEFAULT 1,
            `created_at` varchar(35) DEFAULT NULL,
            `updated_at` varchar(35) DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `template_id_idx` (`template_id`)
            ) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 COMMENT='0.0.9' ;

-- --------------------------------------------------------
-- add name to service_proxmox_lxc_config_template
-- --------------------------------------------------------
ALTER TABLE `service_proxmox_lxc_config_template` ADD COLUMN `name` varchar(255) DEFAULT NULL AFTER `template_id`;

-- --------------------------------------------------------
--  add lxc storage template table with a foreign key to service_proxmox_lxc_template
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `service_proxmox_lxc_storage_template` (
            `id` bigint(20) NOT NULL AUTO_INCREMENT,
            `template_id` bigint(20) DEFAULT NULL,
            `storage_type` varchar(255) DEFAULT NULL,
            `size` bigint(20) DEFAULT NULL,
            `format` varchar(255) DEFAULT NULL,
            `created_at` varchar(35) DEFAULT NULL,
            `updated_at` varchar(35) DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `template_id_idx` (`template_id`)
            ) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 COMMENT='0.0.9' ;

-- --------------------------------------------------------
--  add lxc network template table with a foreign key to service_proxmox_lxc_template
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `service_proxmox_lxc_network_template` (
            `id` bigint(20) NOT NULL AUTO_INCREMENT,
            `template_id` bigint(20) DEFAULT NULL,
            `network_type` varchar(255) DEFAULT NULL,
            `model` varchar(255) DEFAULT NULL,
            `macaddr` varchar(255) DEFAULT NULL,
            `bridge` varchar(255) DEFAULT NULL,
            `tag` bigint(20) DEFAULT NULL,
            `firewall` TINYINT(1) DEFAULT 0,
            `queues` bigint(20) DEFAULT NULL,
            `created_at` varchar(35) DEFAULT NULL,
            `updated_at` varchar(35) DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `template_id_idx` (`template_id`)
            ) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 COMMENT='0.0.9' ;
-- --------------------------------------------------------

-- Table: service_proxmox_lxc_template
-- --------------------------------------------------------
-- remove maintainer field
-- --------------------------------------------------------
ALTER TABLE `service_proxmox_lxc_template` DROP COLUMN `maintainer`;
-- --------------------------------------------------------
RENAME TABLE `service_proxmox_lxc_template` TO `service_proxmox_lxc_appliance`;

-- --------------------------------------------------------
-- add template vm table for storing information about qemu_template VMs
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `service_proxmox_qemu_template` (
            `id` bigint(20) NOT NULL,
            `server_id` bigint(20) DEFAULT NULL,
            `name` varchar(255) DEFAULT NULL,
            `created_at` varchar(35) DEFAULT NULL,
            `updated_at` varchar(35) DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `id_idx` (`id`),
            UNIQUE KEY `vmid_server_id_idx` (`id`, `server_id`)
            ) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 COMMENT='0.0.9' ;

-- --------------------------------------------------------

-- Table: service_proxmox_vm_config_template
-- --------------------------------------------------------
-- remove iso field from service_proxmox_vm_config_template
-- --------------------------------------------------------
 ALTER TABLE `service_proxmox_vm_config_template` DROP COLUMN `iso`;


-- Table: service_proxmox
-- --------------------------------------------------------
-- add template_vmid and vm config template to service_proxmox
-- --------------------------------------------------------
ALTER TABLE `service_proxmox` ADD COLUMN `template_vmid` bigint(20) DEFAULT NULL AFTER `vmid`;
ALTER TABLE `service_proxmox` ADD COLUMN `vm_config_template_id` bigint(20) DEFAULT NULL AFTER `template_vmid`;


--- New Table: service_proxmox_client_vlan
-- --------------------------------------------------------
-- Table to store client vlans
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `service_proxmox_client_vlan` (
            `id` bigint(20) NOT NULL AUTO_INCREMENT,
            `client_id` bigint(20) DEFAULT NULL,
            `vlan` bigint(20) DEFAULT NULL,
            `ip_range` varchar(255) DEFAULT NULL,
            `created_at` varchar(35) DEFAULT NULL,
            `updated_at` varchar(35) DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `client_id_idx` (`client_id`)
            ) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 COMMENT='0.0.9' ;

-- --------------------------------------------------------

--- New Table: service_proxmox_ip_range
-- --------------------------------------------------------
-- Table to store ip networks
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `service_proxmox_ip_range` (
            `id` bigint(20) NOT NULL AUTO_INCREMENT,
            `cidr` varchar(255) DEFAULT NULL,
            `gateway` varchar(255) DEFAULT NULL,
            `broadcast` varchar(255) DEFAULT NULL,
            `type` varchar(255) DEFAULT NULL,
            `created_at` varchar(35) DEFAULT NULL,
            `updated_at` varchar(35) DEFAULT NULL,
            PRIMARY KEY (`id`)
            ) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 COMMENT='0.0.9' ;



-- --------------------------------------------------------
-- increment all tables to 0.0.9
-- --------------------------------------------------------
ALTER TABLE `service_proxmox_server` COMMENT = '0.0.9';
ALTER TABLE `service_proxmox` COMMENT = '0.0.9';
ALTER TABLE `service_proxmox_users` COMMENT = '0.0.9';
ALTER TABLE `service_proxmox_storageclass` COMMENT = '0.0.9';
ALTER TABLE `service_proxmox_storage` COMMENT = '0.0.9';
ALTER TABLE `service_proxmox_lxc_appliance` COMMENT = '0.0.9';
ALTER TABLE `service_proxmox_vm_config_template` COMMENT = '0.0.9';
ALTER TABLE `service_proxmox_vm_storage_template` COMMENT = '0.0.9';
ALTER TABLE `service_proxmox_vm_network_template` COMMENT = '0.0.9';
ALTER TABLE `service_proxmox_lxc_config_template` COMMENT = '0.0.9';
ALTER TABLE `service_proxmox_lxc_storage_template` COMMENT = '0.0.9';
ALTER TABLE `service_proxmox_lxc_network_template` COMMENT = '0.0.9';
ALTER TABLE `service_proxmox_qemu_template` COMMENT = '0.0.9';
ALTER TABLE `service_proxmox_client_vlan` COMMENT = '0.0.9';
ALTER TABLE `service_proxmox_ip_range` COMMENT = '0.0.9';
-- --------------------------------------------------------

