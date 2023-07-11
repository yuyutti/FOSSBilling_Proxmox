-- Migration: 0.0.6
-- Create vm configuration template table
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `service_proxmox_vm_config_template` (
            `id` bigint(20) NOT NULL AUTO_INCREMENT,
            `name` varchar(255) DEFAULT NULL,
            `description` varchar(255) DEFAULT NULL,
            `cores` bigint(20) DEFAULT NULL,
            `memory` bigint(20) DEFAULT NULL,
            -- boolean field for balloon
            `balloon` TINYINT(1) DEFAULT 0,
            `balloon_size` bigint(20) DEFAULT NULL,
            `os` varchar(255) DEFAULT NULL,
            `bios` varchar(255) DEFAULT NULL,
            `iso` varchar(255) DEFAULT NULL,
            `onboot` TINYINT(1) DEFAULT 1,
            `agent` TINYINT(1) DEFAULT 1,
            `created_at` varchar(35) DEFAULT NULL,
            `updated_at` varchar(35) DEFAULT NULL,
            PRIMARY KEY (`id`)
            ) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------
-- Create vm storage template table
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `service_proxmox_vm_storage_template` (
            `id` bigint(20) NOT NULL AUTO_INCREMENT,
            `template_id` bigint(20) DEFAULT NULL,
            `storage_type` varchar(255) DEFAULT NULL,
            `size` bigint(20) DEFAULT NULL,
            `format` varchar(255) DEFAULT NULL,
            `created_at` varchar(35) DEFAULT NULL,
            `updated_at` varchar(35) DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `template_id_idx` (`template_id`)
            ) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;


-- --------------------------------------------------------
-- Create vm network template table
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `service_proxmox_vm_network_template` (
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
            ) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------
-- Rename "service_proxmox_templates" to "service_proxmox_lxc_template"
-- --------------------------------------------------------
RENAME TABLE `service_proxmox_templates` TO `service_proxmox_lxc_template`;