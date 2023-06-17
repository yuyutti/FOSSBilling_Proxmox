<?php

/**
 * Proxmox module for FOSSBilling
 *
 * @author   FOSSBilling (https://www.fossbilling.org) & Scitch (https://github.com/scitch)
 * @license  GNU General Public License version 3 (GPLv3)
 *
 * This software may contain code previously used in the BoxBilling project.
 * Copyright BoxBilling, Inc 2011-2021
 *
 * This source file is subject to the GNU General Public License version 3 (GPLv3) that is bundled
 * with this source code in the file LICENSE.
 */

namespace Box\Mod\Serviceproxmox;

/**
 * Proxmox module for FOSSBilling
 */
trait ProxmoxServer
{
	/* ################################################################################################### */
	/* #####################################  Server Management  ######################################### */
	/* ################################################################################################### */


	/*
		Test connection
	*/
	public function test_connection($server)
	{
		// Test if login
		$serveraccess = $this->find_access($server);
		$proxmox = new PVE2_API($serveraccess, $server->root_user, $server->realm, $server->root_password, tokenid: $server->tokenname, tokensecret: $server->tokenvalue);
		// check if tokenname and tokenvalue contain values by checking their content
		if (empty($server->tokenname) || empty($server->tokenvalue)) {
			if (!empty($server->root_user) && !empty($server->root_password)) {

				if ($proxmox->login()) {
					return true;
				} else {
					throw new \Box_Exception("Login to Proxmox Host failed");
				}
			} else {
				throw new \Box_Exception("No login information provided");
			}
		} else if ($proxmox->get_version()) {
			return true;
		} else {
			throw new \Box_Exception("Failed to connect to the server.");
		}
	}


	/*
		Find empty slots
	*/
	public function find_empty($product)
	{
		$config = json_decode($product->config, 1);
		$group = $config['group'];
		$filling = $config['filling'];

		// Retrieve list of active server from this group
		// Retrieve the number of slots used per server
		if ($filling == 'least') {
			$condition = "ORDER BY ratio ASC";
		} else if ($filling == 'full') {
			$condition = "ORDER BY ratio DESC";
		} else {
			$condition = "";
		}

		// Retrieve only non-full active servers sorted by filling ratio (DESC for filling the least filled, ASC for filling servers up) - COALESC transforms null cells into zeros for calculations.
		$sql = "SELECT `server`.id, `server`.group, `server`.active, `server`.slots, COALESCE(`service`.used,0) as used, `server`.slots - COALESCE(`service`.used,0) as free, COALESCE(`service`.used,0) / `server`.slots as ratio
				FROM `service_proxmox_server` as `server`
				LEFT JOIN (
					SELECT COUNT(*) AS used, `service`.server_id
					FROM `service_proxmox` as `service`
					LEFT JOIN `client_order` ON `client_order`.service_id=`service`.id AND `client_order`.status = 'active'
				) as `service` ON `service`.server_id=`server`.id
				WHERE `server`.slots <> COALESCE(`service`.used,0) AND `server`.active=1 AND `server`.group='" . $group . "'
				" . $condition . " LIMIT 1";

		$appropriate_server = $this->di['db']->getAll($sql);
		if (!empty($appropriate_server[0]['id'])) {
			return $appropriate_server[0]['id'];
		} else {
			throw new \Box_Exception('No server found');
			return false;
		}
	}

	/*
		Find access to server (hostname, ipv4, ipv6)
	*/
	public function find_access($server)
	{
		if (!empty($server->ipv6)) {
			return $server->ipv6;
		} else if (!empty($server->ipv4)) {
			return $server->ipv4;
		} else if (!empty($server->hostname)) {
			return $server->hostname;
		} else {
			throw new \Box_Exception('No IPv6, IPv4 or Hostname found for server ' . $server->id);
		}
	}

	/*
		Find server hardware usage information "getHardwareData"
	*/
	public function getHardwareData($server)
	{
		// Retrieve associated server
		$serveraccess = $this->find_access($server);
		$proxmox = new PVE2_API($serveraccess, $server->root_user, $server->realm, $server->root_password, tokenid: $server->tokenname, tokensecret: $server->tokenvalue);

		if ($proxmox->login()) {
			$hardware = $proxmox->get("/nodes/" . $server->name . "/status");
			return $hardware;
		} else {
			throw new \Box_Exception("Failed to connect to the server. hw Token Access Failed");
		}
	}

	public function getStorageData($server)
	{
		// Retrieve associated server
		$serveraccess = $this->find_access($server);
		$proxmox = new PVE2_API($serveraccess, $server->root_user, $server->realm, $server->root_password, tokenid: $server->tokenname, tokensecret: $server->tokenvalue);
		if ($proxmox->login()) {
			$storage = $proxmox->get("/nodes/" . $server->name . "/storage");
			return $storage;
		} else {
			throw new \Box_Exception("Failed to connect to the server. st");
		}
	}
}
