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
trait ProxmoxVM
{
	/* ################################################################################################### */
	/* #####################################  VM Management  ############################################ */
	/* ################################################################################################### */
	/**
	 * Suspend Proxmox VM
	 * @param $order
	 * @return boolean
	 */
	public function suspend($order, $model)
	{
		// Shutdown VM
		$this->vm_shutdown($order, $model);
		// need to check that the VM was shutdown

		$model->updated_at = date('Y-m-d H:i:s');
		$this->di['db']->store($model);

		return true;
	}

	/**
	 * Unsuspend Proxmox VM
	 * @param $order
	 * @return boolean
	 */
	public function unsuspend($order, $model)
	{
		// Power on VM?
		$this->vm_start($order, $model);
		$model->updated_at = date('Y-m-d H:i:s');
		$this->di['db']->store($model);

		return true;
	}

	/**
	 * Cancel Proxmox VM
	 * @param $order
	 * @return boolean
	 */
	public function cancel($order, $model)
	{
		return $this->suspend($order, $model);
	}

	/**
	 * Uncancel Proxmox VM
	 * @param $order
	 * @return boolean
	 */
	public function uncancel($order, $model)
	{
		return $this->unsuspend($order, $model);
	}

	/**
	 * Delete Proxmox VM
	 * @param $order
	 * @return boolean
	 */
	public function delete($order, $model)
	{
		if (is_object($model)) {

			$product = $this->di['db']->load('product', $order->product_id);
			$product_config = json_decode($product->config, 1);

			// Retrieve associated server
			$server = $this->di['db']->findOne('service_proxmox_server', 'id=:id', array(':id' => $model->server_id));

			// Connect to YNH API
			$serveraccess = $this->find_access($server);
			$proxmox = new PVE2_API($serveraccess, $server->root_user, $server->realm, $server->root_password, tokenid: $server->tokenname, tokensecret: $server->tokenvalue);

			if ($proxmox->login()) {
				$proxmox->post("/nodes/" . $model->node . "/" . $product_config['virt'] . "/" . $model->vmid . "/status/shutdown", array());
				$status = $proxmox->get("/nodes/" . $model->node . "/" . $product_config['virt'] . "/" . $model->vmid . "/status/current");

				// Wait until the server has been shut down if the server exists
				if (!empty($status)) {
					while ($status['status'] != 'stopped') {
						sleep(10);
						$proxmox->post("/nodes/" . $model->node . "/" . $product_config['virt'] . "/" . $model->vmid . "/status/shutdown", array());
						$status = $proxmox->get("/nodes/" . $model->node . "/" . $product_config['virt'] . "/" . $model->vmid . "/status/current");
					}
				} else {
					throw new \Box_Exception("VMID cannot be found");
				}
				if ($proxmox->delete("/nodes/" . $model->node . "/" . $product_config['virt'] . "/" . $model->vmid)) {
					// Delete Proxmox user if it exists : TO BE DONE
					/*if($proxmox->get("/access/users/".$client->id.'@'.$server->realm)) {
						//Update user information
						if(!$proxmox->delete("/access/users/".$client->id.'@'.$server->realm)) {
							throw new \Box_Exception("Proxmox user exists but could not be deleted");
						}
					}*/
					return true;
				} else {
					throw new \Box_Exception("VM not deleted");
				}
			} else {
				throw new \Box_Exception("Login to Proxmox Host failed");
			}
		}
	}

	/*
		VM status
		TO DO : add more infos
	*/
	public function vm_info($order, $service)
	{
		$product = $this->di['db']->load('product', $order->product_id);
		$product_config = json_decode($product->config, 1);
		$client = $this->di['db']->load('client', $order->client_id);

		// Retrieve associated server
		$server = $this->di['db']->findOne('service_proxmox_server', 'id=:id', array(':id' => $service->server_id));
		$clientuser = $this->di['db']->findOne('service_proxmox_users', 'server_id = ? and client_id = ?', array($server->id, $client->id));

		// Test if login
		$serveraccess = $this->find_access($server);
		$proxmox = new PVE2_API($serveraccess, $server->root_user, $server->realm, $server->root_password, tokenid: $clientuser->admin_tokenname, tokensecret: $clientuser->admin_tokenvalue);
		if ($proxmox->get_version()) {
			$status = $proxmox->get("/nodes/" . $server->name . "/" . $product_config['virt'] . "/" . $service->vmid . "/status/current");
			// VM monitoring?

			$output = array(
				'status'	=> $status['status']
			);
			return $output;
		} else {
			throw new \Box_Exception("Login to Proxmox Host failed.");
		}
	}

	/*
		Reboot VM
	*/
	public function vm_reboot($order, $service)
	{
		$product = $this->di['db']->load('product', $order->product_id);
		$product_config = json_decode($product->config, 1);
		$client = $this->di['db']->load('client', $order->client_id);

		// Retrieve associated server
		$server = $this->di['db']->findOne('service_proxmox_server', 'id=:id', array(':id' => $service->server_id));
		$clientuser = $this->di['db']->findOne('service_proxmox_users', 'server_id = ? and client_id = ?', array($server->id, $client->id));

		// Test if login
		$serveraccess = $this->find_access($server);
		$proxmox = new PVE2_API($serveraccess, $server->root_user, $server->realm, $server->root_password, tokenid: $clientuser->admin_tokenname, tokensecret: $clientuser->admin_tokenvalue);
		if ($proxmox->login()) {
			$proxmox->post("/nodes/" . $server->name . "/" . $product_config['virt'] . "/" . $service->vmid . "/status/shutdown", array());
			$status = $proxmox->get("/nodes/" . $server->name . "/" . $product_config['virt'] . "/" . $service->vmid . "/status/current");

			// Wait until the VM has been shut down if the VM exists
			if (!empty($status)) {
				while ($status['status'] != 'stopped') {
					sleep(10);
					$proxmox->post("/nodes/" . $server->name . "/" . $product_config['virt'] . "/" . $service->vmid . "/status/shutdown", array());
					$status = $proxmox->get("/nodes/" . $server->name . "/" . $product_config['virt'] . "/" . $service->vmid . "/status/current");
				}
			}
			// Restart
			if ($proxmox->post("/nodes/" . $server->name . "/" . $product_config['virt'] . "/" . $service->vmid . "/status/start", array())) {
				sleep(10);
				$status = $proxmox->get("/nodes/" . $server->name . "/" . $product_config['virt'] . "/" . $service->vmid . "/status/current");
				while ($status['status'] != 'running') {
					$proxmox->post("/nodes/" . $server->name . "/" . $product_config['virt'] . "/" . $service->vmid . "/status/start", array());
					sleep(10);
					$status = $proxmox->get("/nodes/" . $server->name . "/" . $product_config['virt'] . "/" . $service->vmid . "/status/current");
					// Starting twice => error...
				}
				return true;
			} else {
				throw new \Box_Exception("Reboot failed");
			}
		} else {
			throw new \Box_Exception("Login to Proxmox Host failed.");
		}
	}

	/*
		Start VM
	*/
	public function vm_start($order, $service)
	{
		$product = $this->di['db']->load('product', $order->product_id);
		$product_config = json_decode($product->config, 1);
		$client = $this->di['db']->load('client', $order->client_id);
		// Retrieve associated server
		// Retrieve associated server
		$server = $this->di['db']->findOne('service_proxmox_server', 'id=:id', array(':id' => $service->server_id));
		$clientuser = $this->di['db']->findOne('service_proxmox_users', 'server_id = ? and client_id = ?', array($server->id, $client->id));

		// Test if login
		$serveraccess = $this->find_access($server);
		$proxmox = new PVE2_API($serveraccess, $server->root_user, $server->realm, $server->root_password, tokenid: $clientuser->admin_tokenname, tokensecret: $clientuser->admin_tokenvalue);
		if ($proxmox->login()) {
			$proxmox->post("/nodes/" . $server->name . "/" . $product_config['virt'] . "/" . $service->vmid . "/status/start", array());
			return true;
		} else {
			throw new \Box_Exception("Login to Proxmox Host failed.");
		}
	}

	/*
		Shutdown VM
	*/
	public function vm_shutdown($order, $service)
	{
		$product = $this->di['db']->load('product', $order->product_id);
		$product_config = json_decode($product->config, 1);
		$client = $this->di['db']->load('client', $order->client_id);
		// Retrieve associated server
		$server = $this->di['db']->findOne('service_proxmox_server', 'id=:id', array(':id' => $service->server_id));

		// Test if login
		// find service access for server
		$clientuser = $this->di['db']->findOne('service_proxmox_users', 'server_id = ? and client_id = ?', array($server->id, $client->id));
		//echo "D: ".var_dump($order);
		$serveraccess = $this->find_access($server);
		$proxmox = new PVE2_API($serveraccess, $server->root_user, $server->realm, $server->root_password, tokenid: $clientuser->admin_tokenname, tokensecret: $clientuser->admin_tokenvalue);
		if ($proxmox->login()) {
			$settings = array(
				'forceStop' 	=> true
			);

			$proxmox->post("/nodes/" . $server->name . "/" . $product_config['virt'] . "/" . $service->vmid . "/status/shutdown", $settings);
			return true;
		} else {
			throw new \Box_Exception("Login to Proxmox Host failed.");
		}
	}


	/*
		VM iframe for Web CLI
	*/
	public function vm_cli($order, $service)
	{

		$product = $this->di['db']->load('product', $order->product_id);
		$product_config = json_decode($product->config, 1);
		$client  = $this->di['db']->load('client', $order->client_id);

		// Retrieve associated server
		$server = $this->di['db']->findOne('service_proxmox_server', 'id=:id', array(':id' => $service->server_id));
		$clientuser = $this->di['db']->findOne('service_proxmox_users', 'server_id = ? and client_id = ?', array($server->id, $client->id));

		// Find access route
		$serveraccess = $this->find_access($server);

		// Setup console type
		if ($product_config['virt'] == 'qemu') {
			$console = 'kvm';
		} else {
			$console = 'shell';
		}

		// The user enters the password to see the iframe: TBD
		//$password = 'test';
		//$proxmox = new PVE2_API($serveraccess, $client->id, $server->name, $password);

		$proxmox = new PVE2_API($serveraccess, $server->root_user, $server->realm, $server->root_password, tokenid: $clientuser->view_tokenname, tokensecret: $clientuser->view_tokenvalue);

		if ($proxmox->login()) {
			// Get VNC Web proxy ticket by calling /nodes/{node}/{type}/{vmid}/vncproxy
			$vncproxy_response = $proxmox->post("/nodes/" . $server->name . "/" . $product_config['virt'] . "/" . $service->vmid . "/vncproxy",	array('node' => $server->name, 'vmid' => $service->vmid));
			$vncproxy_port = $vncproxy_response['data']['port'];
			$vncproxy_ticket = $vncproxy_response['data']['ticket'];
			// open a vnc web socket
			// we'll do it ourselves until the upstream API supports it
			$put_post_http_headers[] = "Authorization: PVEAPIToken={$clientuser->view_tokenname}={$clientuser->view_tokenvalue}";
			// add the vncticket to the post_http_headers
			$put_post_http_headers[] = "vncticket: {$vncproxy_ticket}";
			// add the port to the post_http_headers
			$put_post_http_headers[] = "port: {$vncproxy_port}";
			// add the host to the post_http_headers
			$put_post_http_headers[] = "host: {$serveraccess}";
			// add the vmid to the post_http_headers
			$put_post_http_headers[] = "vmid: {$service->vmid}";
			// open websocket connection and display the console
			/* 			curl_setopt($prox_ch, CURLOPT_URL, "https://{$serveraccess}:8006/api2/json/nodes/{$server->name}/{$product_config['virt']}/{$service->vmid}/vncwebsocket");
			curl_setopt($prox_ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($prox_ch, CURLOPT_POSTFIELDS, $login_postfields_string);
			curl_setopt($prox_ch, CURLOPT_SSL_VERIFYPEER, $this->verify_ssl);
			curl_setopt($prox_ch, CURLOPT_SSL_VERIFYHOST, $this->verify_ssl);
			*/
			// return array of vncport & ticket
			return array('port' => $vncproxy_port, 'ticket' => $vncproxy_ticket);
		} else {
			throw new \Box_Exception("Login to Proxmox VM failed.");
		}
	}
}
