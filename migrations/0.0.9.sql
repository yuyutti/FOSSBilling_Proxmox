-- Migration: 0.0.9
-- --------------------------------------------------------
--  add lxc config template table with a foreign key to service_proxmox_lxc_template
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `service_proxmox_lxc_config_template` (
            `id` bigint(20) NOT NULL AUTO_INCREMENT,
            `template_id` bigint(20) DEFAULT NULL,
            `cores` bigint(20) DEFAULT NULL,
            `memory` bigint(20) DEFAULT NULL,
            `swap` bigint(20) DEFAULT NULL,
            `ostemplate` varchar(255) DEFAULT NULL,
            `onboot` TINYINT(1) DEFAULT 1,
            `agent` TINYINT(1) DEFAULT 1,
            `created_at` varchar(35) DEFAULT NULL,
            `updated_at` varchar(35) DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `template_id_idx` (`template_id`)
            ) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 COMMENT='0.0.9' ;

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

-- --------------------------------------------------------
-- increment all tables to 0.0.9
-- --------------------------------------------------------
ALTER TABLE `service_proxmox_server` COMMENT = '0.0.9';
ALTER TABLE `service_proxmox` COMMENT = '0.0.9';
ALTER TABLE `service_proxmox_users` COMMENT = '0.0.9';
ALTER TABLE `service_proxmox_vm_config_template` COMMENT = '0.0.9';
ALTER TABLE `service_proxmox_storageclass` COMMENT = '0.0.9';
ALTER TABLE `service_proxmox_storage` COMMENT = '0.0.9';
ALTER TABLE `service_proxmox_vm_storage_template` COMMENT = '0.0.9';
ALTER TABLE `service_proxmox_vm_network_template` COMMENT = '0.0.9';
ALTER TABLE `service_proxmox_lxc_template` COMMENT = '0.0.9';
-- --------------------------------------------------------