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

require_once 'pve2_api.class.php';

/**
 * Proxmox module for FOSSBilling
 */
class Service implements \FOSSBilling\InjectionAwareInterface
{
	protected $di;
	public function setDi(\Pimple\Container|null $di): void
	{
		$this->di = $di;
	}

	public function getDi(): ?\Pimple\Container
	{
		return $this->di;
	}

	public function validateCustomForm(array &$data, array $product)
	{
		if ($product['form_id']) {
			$formbuilderService = $this->di['mod_service']('formbuilder');
			$form = $formbuilderService->getForm($product['form_id']);

			foreach ($form['fields'] as $field) {
				if ($field['required'] == 1) {
					$field_name = $field['name'];
					if ((!isset($data[$field_name]) || empty($data[$field_name]))) {
						throw new \Box_Exception("You must fill in all required fields. " . $field['label'] . " is missing", null, 9684);
					}
				}

				if ($field['readonly'] == 1) {
					$field_name = $field['name'];
					if ($data[$field_name] != $field['default_value']) {
						throw new \Box_Exception("Field " . $field['label'] . " is read only. You can not change its value", null, 5468);
					}
				}
			}
		}
	}

	/**
	 * Method to install module. In most cases you will provide your own
	 * database table or tables to store extension related data.
	 *
	 * If your extension is not very complicated then extension_meta
	 * database table might be enough.
	 *
	 * @return bool
	 */
	public function install()
	{
		// execute sql script if needed
		// TODO: Eventually remove root_password
		$sql = "
        CREATE TABLE IF NOT EXISTS `service_proxmox_server` (
            `id` bigint(20) NOT NULL AUTO_INCREMENT,
            `name` varchar(255) DEFAULT NULL,
            `group` varchar(255) DEFAULT NULL,
            `ipv4` varchar(255) DEFAULT NULL,
            `ipv6` varchar(255) DEFAULT NULL,
            `hostname` varchar(255) DEFAULT NULL,
			`mac` varchar(255) DEFAULT NULL,
			`realm` varchar(255) DEFAULT NULL,
			`cpu_cores` bigint(20) DEFAULT NULL,
			`ram` bigint(20) DEFAULT NULL,
			`ram_used` bigint(20) DEFAULT NULL,
			`cpu_cores_allocated` bigint(20) DEFAULT NULL,
			`slots` bigint(20) DEFAULT NULL,
			`root_user` varchar(255) DEFAULT NULL,
            `root_password` varchar(255) DEFAULT NULL,
			`tokenname` varchar(255) DEFAULT NULL,
			`tokenvalue` varchar(255) DEFAULT NULL,			
            `config` text,
			`active` bigint(20) DEFAULT NULL,
            `created_at` varchar(35) DEFAULT NULL,
            `updated_at` varchar(35) DEFAULT NULL,
            PRIMARY KEY (`id`)
            ) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;";
		$this->di['db']->exec($sql);

		$sql = "
        CREATE TABLE IF NOT EXISTS `service_proxmox` (
            `id` bigint(20) NOT NULL AUTO_INCREMENT,
			`client_id` bigint(20) DEFAULT NULL,
			`order_id` bigint(20) DEFAULT NULL,
			`server_id` bigint(20) DEFAULT NULL,
			`vmid` bigint(20) DEFAULT NULL,
			`ipv4` varchar(255) DEFAULT NULL,
            `ipv6` varchar(255) DEFAULT NULL,
			`hostname` varchar(255) DEFAULT NULL,
			`password` varchar(255) DEFAULT NULL,
            `config` text,
			`status` varchar(255) DEFAULT NULL,	
			`storage` varchar(255) DEFAULT NULL,
			`cpu_cores` varchar(255) DEFAULT NULL,
            `created_at` varchar(35) DEFAULT NULL,
            `updated_at` varchar(35) DEFAULT NULL,
            PRIMARY KEY (`id`),
			KEY `client_id_idx` (`client_id`)
            ) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;";
		$this->di['db']->exec($sql);

		$sql = "
        CREATE TABLE IF NOT EXISTS `service_proxmox_users` (
            `id` bigint(20) NOT NULL AUTO_INCREMENT,
			`client_id` bigint(20) DEFAULT NULL,
			`admin_tokenname` varchar(255) DEFAULT NULL,
			`admin_tokenvalue` varchar(255) DEFAULT NULL,
			`view_tokenname` varchar(255) DEFAULT NULL,
			`view_tokenvalue` varchar(255) DEFAULT NULL,
            `created_at` varchar(35) DEFAULT NULL,
            `updated_at` varchar(35) DEFAULT NULL,
            PRIMARY KEY (`id`),
			KEY `client_id_idx` (`client_id`)
            ) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;";
		$this->di['db']->exec($sql);

		// create table for storage class information
		$sql = "
			`id` bigint(20) NOT NULL AUTO_INCREMENT,
			`storageclass` varchar(35) DEFAULT NULL,
			PRIMARY KEY (`id`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;";
		$this->di['db']->exec($sql);


		// create table for server storage
		// server_id and storage form a unique key
		$sql = "
		CREATE TABLE IF NOT EXISTS `service_proxmox_storage` (
			`id` bigint(20) NOT NULL AUTO_INCREMENT,
			`server_id` bigint(20) DEFAULT NULL,
			`storage` varchar(255) DEFAULT NULL,
			`type` varchar(255) DEFAULT NULL,
			`content` varchar(255) DEFAULT NULL,
			`active` bigint(20) DEFAULT NULL,
			`storageclass` varchar(35) DEFAULT NULL,
			`size` bigint(20) DEFAULT NULL,
			`used` bigint(20) DEFAULT NULL,
			`free` bigint(20) DEFAULT NULL,
			PRIMARY KEY (`id`),
			UNIQUE KEY `server_storage_unique` (`server_id`, `storage`)
		) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;";
		$this->di['db']->exec($sql);
		return true;
	}

	/**
	 * Method to uninstall module.
	 *
	 * @return bool
	 */
	public function uninstall()
	{
		$this->di['db']->exec("DROP TABLE IF EXISTS `service_proxmox`");
		$this->di['db']->exec("DROP TABLE IF EXISTS `service_proxmox_server`");
		$this->di['db']->exec("DROP TABLE IF EXISTS `service_proxmox_users`");
		$this->di['db']->exec("DROP TABLE IF EXISTS `service_proxmox_storage`");
		return true;
	}

	/* ################################################################################################### */
	/* ###########################################  Orders  ############################################## */
	/* ################################################################################################### */

	/**
	 * @param \Model_ClientOrder $order
	 * @return void
	 */
	public function create($order)
	{
		$config = json_decode($order->config, 1);
		//$this->validateOrderData($config);
		/*
		    public function validateOrderData(array &$data)
			{
				if(!isset($data['server_id'])) {
					throw new \Box_Exception('Hosting product is not configured completely. Configure server for hosting product.', null, 701);
				}
				if(!isset($data['hosting_plan_id'])) {
					throw new \Box_Exception('Hosting product is not configured completely. Configure hosting plan for hosting product.', null, 702);
				}
				if(!isset($data['sld']) || empty($data['sld'])) {
					throw new \Box_Exception('Domain name is not valid.', null, 703);
				}
				if(!isset($data['tld']) || empty($data['tld'])) {
					throw new \Box_Exception('Domain extension is not valid.', null, 704);
				}
			}
		*/

		$product = $this->di['db']->getExistingModelById('Product', $order->product_id, 'Product not found');

		$model                	= $this->di['db']->dispense('service_proxmox');
		$model->client_id     	= $order->client_id;
		$model->order_id     	= $order->id;
		$model->created_at    	= date('Y-m-d H:i:s');
		$model->updated_at    	= date('Y-m-d H:i:s');

		$this->di['db']->store($model);

		return $model;
	}

	/**
	 * @param \Model_ClientOrder $order
	 * @return boolean
	 */
	public function activate($order, $model)
	{
		$pmxauth = new ProxmoxAuthentication();
		if (!is_object($model)) {
			throw new \Box_Exception('Could not activate order. Service was not created');
		}
		$config = json_decode($order->config, 1);
		//$this->validateOrderData($c);//
		$client  = $this->di['db']->load('client', $order->client_id);
		$product = $this->di['db']->load('product', $order->product_id);
		$service = $this->di['db']->dispense('service_proxmox');
		if (!$product) {
			throw new \Box_Exception('Could not activate order because ordered product does not exists');
		}
		$product_config = json_decode($product->config, 1);

		// Allocate to an appropriate server id
		$serverid = $this->find_empty($product);

		// Retrieve server info
		$server = $this->di['db']->load('service_proxmox_server', $serverid);

		// Retrieve or create client unser account in service_proxmox_users
		$clientuser = $this->di['db']->findOne('service_proxmox_users', 'server_id = ? and client_id = ?', array($server->id, $client->id));
		if (!$clientuser) {
			$pmxauth->create_client_user($server, $client);
		}

		// Connect to Proxmox API
		$serveraccess = $this->find_access($server);
		$proxmox = new PVE2_API($serveraccess, $server->root_user, $server->realm, $server->root_password, tokenid: $server->tokenname, tokensecret: $server->tokenvalue);

		// Create Proxmox VM
		if ($proxmox->login()) {

			//$proxmox->delete("/nodes/".$model->node."/qemu/".$model->vmid);
			//return var_export($promox, true);

			// Retrieve next VMID available
			$vmid = $proxmox->get("/cluster/nextid");
			$nodes = $proxmox->get_node_list();
			$first_node = $nodes[0];
			$proxmoxuser_password = $this->di['tools']->generatePassword(10, 4); // Generate password

			// Create VM
			$clone = '';
			$container_settings = array();
			$description = 'Service package ' . $service->id . ' belonging to ' . $client->first_name . ' ' . $client->last_name . ' id: ' . $client->id;

			if ($product_config['clone'] == true) {
				$clone = '/' . $product_config['cloneid'] . '/clone'; // Define the route for cloning
				$container_settings = array(
					'newid' => $vmid,
					'name' => $model->username,
					'description' => $description,
					'full' => true
				);
			} else { // TODO: Fix Container templates 
				if ($product_config['virt'] == 'qemu') {
					$container_settings = array(
						'vmid' => $vmid,
						'name' => $model->username,                 // Hostname to define
						'description' => $description,
						'storage' => $product_config['storage'],
						'memory' => $product_config['memory'],
						'ostype' => 'other',
						'ide2' => $product_config['cdrom'] . ',media=cdrom',
						'sockets' => $product_config['cpu'],
						'cores' => $product_config['cpu'],
						'ide0' => $product_config['ide0']
					);
				} else {
					$container_settings = array(
						'vmid' => $vmid,
						'hostname' => $model->username,             // Hostname to define
						'description' => $description,
						'storage' => $product_config['storage'],
						'memory' => $product_config['memory'],
						'ostemplate' => $product_config['ostemplate'],
						'password' => $proxmoxuser_password,
						'net0' => $product_config['network']
					);
					// Storage to do for LXC
				}
			}

			// If the VM is properly created
			if ($proxmox->post("/nodes/" . $first_node . "/" . $product_config['virt'] . $clone, $container_settings)) {

				// Start the server
				sleep(20);
				$proxmox->post("/nodes/" . $first_node . "/" . $product_config['virt'] . "/" . $vmid . "/status/start", array());
				$status = $proxmox->get("/nodes/" . $first_node . "/" . $product_config['virt'] . "/" . $vmid . "/status/current");

				if (!empty($status)) {
					sleep(10);
					// Wait until it has been started
					while ($status['status'] != 'running') {
						$proxmox->post("/nodes/" . $first_node . "/" . $product_config['virt'] . "/" . $vmid . "/status/start", array());
						sleep(10);
						$status = $proxmox->get("/nodes/" . $first_node . "/" . $product_config['virt'] . "/" . $vmid . "/status/current");
						// Starting twice => error...
					}
					// Debug 

					// Retrieve IP


					// USER TO BE DONE: not working yet

					/*$user_settings = array(
						'email' => $client->email,
						'firstname' => $client->first_name,
						'lastname' => $client->last_name,
						//'password' => $proxmoxuser_password		// Not working
					);
					
					// If proxmox user exists, delete it first TODO: NEVER DO THAT.
					if($proxmox->get("/access/users/".$client->id.'@'.$server->realm)) {
						//Update user information
						if(!$proxmox->delete("/access/users/".$client->id.'@'.$server->realm)) {
							throw new \Box_Exception("Proxmox user exists but could not be deleted");
						}
					}
					
					// Create a Proxmox user for SSH access and server commands
					$user_settings['userid'] = $client->id.'@'.$server->realm;
					if(!$proxmox->post("/access/users", $user_settings)) {
						throw new \Box_Exception("Proxmox user not created");
					}

					// Give correct permissions
					$permission_settings = array(
						'path' => '/vms/'.$vmid,
						'roles' => 'Dedicated',
						'users' => $client->id.'@'.$server->realm
					);
					if(!$proxmox->put("/access/acl", $permission_settings)) {
						throw new Exception("Proxmox permissions not added");
					}
					
					// Update password: not working yet...
					/*$user_settings = array(
						'password' => $proxmoxuser_password,
						'userid' => $client->id.'@'.$server->realm
					);
					if(!$proxmox->put("/access/password", $user_settings)) {
						throw new \Box_Exception("Proxmox user password not updated");
					}*/
				} else {
					throw new \Box_Exception("VMID cannot be found");
				}
			} else {
				throw new \Box_Exception("VPS has not been created");
			}
		} else {
			throw new \Box_Exception('Login to Proxmox Host failed', null, 7457);
		}

		// Retrieve VM IP

		$model->server_id 		= $serverid;
		$model->updated_at    	= date('Y-m-d H:i:s');
		$model->vmid			= $vmid;
		$model->node			= $first_node;
		$model->password		= $proxmoxuser_password;
		//$model->ipv4			= $ipv4;      	//How to do that?
		//$model->ipv6			= $ipv6;		//How to do that?
		//$model->hostname		= $hostname;	//How to do that?
		$this->di['db']->store($model);

		return array(
			'ip'	=> 	'to be sent by us shortly',		// Return IP address of the VM?
			'username'  =>  'root',
			'password'  =>  $proxmoxuser_password,
		);
	}

	public function toApiArray($model)
	{
		// Retrieve associated server
		$server = $this->di['db']->findOne('service_proxmox_server', 'id=:id', array(':id' => $model->server_id));

		return array(
			'id'              => $model->id,
			'client_id'       => $model->client_id,
			'server_id'       => $model->server_id,
			'username'        => $model->username,
			'mailbox_quota'   => $model->mailbox_quota,
			'server' 		  => $server,
		);
	}
	/* ################################################################################################### */
	/* #######################################  VM Management  ########################################### */
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

		// Retrieve associated server
		$server = $this->di['db']->findOne('service_proxmox_server', 'id=:id', array(':id' => $service->server_id));

		// Test if login
		$serveraccess = $this->find_access($server);
		$proxmox = new PVE2_API($serveraccess, $server->root_user, $server->realm, $server->root_password, tokenid: $server->tokenname, tokensecret: $server->tokenvalue);
		if ($proxmox->get_version()) {
			$status = $proxmox->get("/nodes/" . $service->node . "/" . $product_config['virt'] . "/" . $service->vmid . "/status/current");
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

		// Retrieve associated server
		$server = $this->di['db']->findOne('service_proxmox_server', 'id=:id', array(':id' => $service->server_id));

		// Test if login
		$serveraccess = $this->find_access($server);
		$proxmox = new PVE2_API($serveraccess, $server->root_user, $server->realm, $server->root_password, tokenid: $server->tokenname, tokensecret: $server->tokenvalue);
		if ($proxmox->login()) {
			$proxmox->post("/nodes/" . $service->node . "/" . $product_config['virt'] . "/" . $service->vmid . "/status/shutdown", array());
			$status = $proxmox->get("/nodes/" . $service->node . "/" . $product_config['virt'] . "/" . $service->vmid . "/status/current");

			// Wait until the VM has been shut down if the VM exists
			if (!empty($status)) {
				while ($status['status'] != 'stopped') {
					sleep(10);
					$proxmox->post("/nodes/" . $service->node . "/" . $product_config['virt'] . "/" . $service->vmid . "/status/shutdown", array());
					$status = $proxmox->get("/nodes/" . $service->node . "/" . $product_config['virt'] . "/" . $service->vmid . "/status/current");
				}
			}
			// Restart
			if ($proxmox->post("/nodes/" . $service->node . "/" . $product_config['virt'] . "/" . $service->vmid . "/status/start", array())) {
				sleep(10);
				$status = $proxmox->get("/nodes/" . $service->node . "/" . $product_config['virt'] . "/" . $service->vmid . "/status/current");
				while ($status['status'] != 'running') {
					$proxmox->post("/nodes/" . $service->node . "/" . $product_config['virt'] . "/" . $service->vmid . "/status/start", array());
					sleep(10);
					$status = $proxmox->get("/nodes/" . $service->node . "/" . $product_config['virt'] . "/" . $service->vmid . "/status/current");
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

		// Retrieve associated server
		$server = $this->di['db']->findOne('service_proxmox_server', 'id=:id', array(':id' => $service->server_id));

		// Test if login
		$serveraccess = $this->find_access($server);
		$proxmox = new PVE2_API($serveraccess, $server->root_user, $server->realm, $server->root_password, tokenid: $server->tokenname, tokensecret: $server->tokenvalue);
		if ($proxmox->login()) {
			$proxmox->post("/nodes/" . $service->node . "/" . $product_config['virt'] . "/" . $service->vmid . "/status/start", array());
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

		// Retrieve associated server
		$server = $this->di['db']->findOne('service_proxmox_server', 'id=:id', array(':id' => $service->server_id));

		// Test if login
		$serveraccess = $this->find_access($server);
		$proxmox = new PVE2_API($serveraccess, $server->root_user, $server->realm, $server->root_password, tokenid: $server->tokenname, tokensecret: $server->tokenvalue);
		if ($proxmox->login()) {
			$settings = array(
				'forceStop' 	=> true
			);

			$proxmox->post("/nodes/" . $service->node . "/" . $product_config['virt'] . "/" . $service->vmid . "/status/shutdown", $settings);
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
		//$proxmox = new PVE2_API($serveraccess, $client->id, $service->node, $password);

		$proxmox = new PVE2_API($serveraccess, $server->root_user, $server->realm, $server->root_password, tokenid: $server->tokenname, tokensecret: $server->tokenvalue);
		if ($proxmox->login()) {
			$proxmox->setCookie();
			$cli = $console = '<iframe  src="https://' . $serveraccess . ':8006/?console=' . $console . '&novnc=1&vmid=' . $service->vmid . '&node=' . $service->node . '" frameborder="0" scrolling="no" width="100%" height="600px"></iframe>';
			return $cli;
		} else {
			throw new \Box_Exception("Login to Proxmox VM failed.");
		}
	}


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




	/* ################################################################################################### */
	/* ########################################  Permissions  ############################################ */
	/* ################################################################################################### */

	/**
	 * Function to set up proxmox server for FOSSBilling
	 * @param $server - server object
	 * @return array - array of permission information
	 */
	public function prepare_pve_setup($server)
	{
		$serveraccess = $this->find_access($server);
		$proxmox = new PVE2_API($serveraccess, $server->root_user, $server->realm, $server->root_password, tokenid: $server->tokenname, tokensecret: $server->tokenvalue);
		if (!$proxmox->login()) {
			throw new \Box_Exception("Failed to connect to the server. ");
		}
		// Create api token for Admin user if not logged in via api token
		if (empty($server->tokenname) || empty($server->tokenvalue)) {

			// check if the user that connects has Realm.AllocateUser permission
			$permissions = $proxmox->get("/access/permissions");
			$found_permission = 0;
			//echo "<script>console.log('".json_encode($permissions)."');</script>";
			foreach ($permissions as $permission) {
				if ($permission['Realm.AllocateUser'] == 1) {
					$found_permission += 1;
				}
			}
			if (!$found_permission) {
				throw new \Box_Exception("User does not have Realm.AllocateUser permission");
			}

			// Validate if there already is a group for FOSSBilling
			$groups = $proxmox->get("/access/groups");
			$foundgroups = 0;
			foreach ($groups as $group) {
				//check if group beginning with FOSSBilling exists
				if (strpos($group['groupid'], 'FOSSBilling') === 0) {
					$foundgroups += 1;
					$groupid = $group['groupid'];
				}
			}
			// switch case there is no group, create one, if there is one, use it, if there are more than one, throw error
			switch ($foundgroups) {
				case 0:
					// Create Group
					$groupid = 'FOSSBilling_' . rand(1000, 9999);
					$newgroup = $proxmox->post("/access/groups", array('groupid' => $groupid, 'comment' => 'FOSSBilling group',));
					break;
				case 1:
					// Use Group
					break;
				default:
					throw new \Box_Exception("More than one group found");
					break;
			}


			// Validate if there already is a user & token for FOSSBilling
			$users = $proxmox->get("/access/users");
			$found = 0;
			foreach ($users as $user) {
				//check if user beginning with FOSSBilling exists
				if (strpos($user['userid'], 'fb') === 0) {
					$found += 1;
					$userid = $user['userid'];
				}
				// switch case there is no user, create one, if there is one, use it, if there are more than one, throw error
				switch ($found) {
					case 0:
						// Create user
						$userid = 'fb_' . rand(1000, 9999) . '@pve'; // TODO: Make realm configurable in the module settings
						$newuser = $proxmox->post("/access/users", array('userid' => $userid, 'password' => $this->generateRandomPassword(16), 'enable' => 1, 'comment' => 'FOSSBilling user', 'groups' => $groupid,));

						// Create token

						$token = $proxmox->post("/access/users/" . $userid . "/token/fb_access", array());
						// check if token was created
						if ($token) {
							$server->tokenname = $token['full-tokenid'];
							$server->tokenvalue = $token['value'];
						} else {
							throw new \Box_Exception("Failed to create token for FOSSBilling user");
							break;
						}
						break;
					case 1:
						// Create token 
						$token = $proxmox->post("/access/users/" . $userid . "/token/fb_access", array());
						if ($token) {
							$server->tokenname = $token['full-tokenid'];
							$server->tokenvalue = $token['value'];
						} else {
							throw new \Box_Exception("Failed to create token for FOSSBilling user");
							break;
						}
						break;
					default:
						throw new \Box_Exception("There are more than one FOSSBilling users on the server. Please delete all but one.");
						break;
				}
				// Create permissions for the token we just created
				// Setup permissions for that token (Admin user) so that it can create users and groups and manage them
				$permissions = $proxmox->put("/access/acl/", array('path' => '/', 'roles' => 'PVEUserAdmin', 'propagate' => 1, 'users' => $userid));
				$permissions = $proxmox->put("/access/acl/", array('path' => '/', 'roles' => 'PVEAuditor', 'propagate' => 1, 'users' => $userid));
				$permissions = $proxmox->put("/access/acl/", array('path' => '/', 'roles' => 'PVESysAdmin', 'propagate' => 1, 'users' => $userid));
				$permissions = $proxmox->put("/access/acl/", array('path' => '/', 'roles' => 'PVEPoolAdmin', 'propagate' => 1, 'users' => $userid));
				$permissions = $proxmox->put("/access/acl/", array('path' => '/', 'roles' => 'PVEDatastoreAdmin', 'propagate' => 1, 'users' => $userid));
				$permissions = $proxmox->put("/access/acl/", array('path' => '/', 'roles' => 'PVEUserAdmin', 'propagate' => 1, 'tokens' => $server->tokenname));
				$permissions = $proxmox->put("/access/acl/", array('path' => '/', 'roles' => 'PVEAuditor', 'propagate' => 1, 'tokens' => $server->tokenname));
				$permissions = $proxmox->put("/access/acl/", array('path' => '/', 'roles' => 'PVESysAdmin', 'propagate' => 1, 'tokens' => $server->tokenname));
				$permissions = $proxmox->put("/access/acl/", array('path' => '/', 'roles' => 'PVEPoolAdmin', 'propagate' => 1, 'tokens' => $server->tokenname));
				$permissions = $proxmox->put("/access/acl/", array('path' => '/', 'roles' => 'PVEDatastoreAdmin', 'propagate' => 1, 'tokens' => $server->tokenname));
				sleep(5);
				// Check if permissions were created correctly by logging in and creating another user
				/*echo "<script>console.log('".json_encode($serveraccess)."');</script>";
				echo "<script>console.log('".json_encode($userid)."');</script>";
				echo "<script>console.log('".json_encode($server->realm)."');</script>";
				echo "<script>console.log('".json_encode($server->tokenname)."');</script>";
				echo "<script>console.log('".json_encode($server->tokenvalue)."');</script><br /><br />";*/
				$server->root_password = null;
				unset($proxmox);

				//echo "<script>console.log('testpmx: ".json_encode($testpmx)."');</script>";

				return $this->test_access($server);
			}
		}
	}


	public function test_access($server)
	{
		$serveraccess = $this->find_access($server);
		$proxmox = new PVE2_API($serveraccess, $server->root_user, $server->realm, $server->root_password, tokenid: $server->tokenname, tokensecret: $server->tokenvalue);
		if (!$proxmox->login()) {
			throw new \Box_Exception("Failed to connect to the server. testpmx");
		}

		$userid = 'tfb_' . rand(1000, 9999) . '@pve'; // TODO: Make realm configurable in the module settings
		$newuser = $proxmox->post("/access/users", array('userid' => $userid, 'password' => $this->generateRandomPassword(16), 'enable' => '1', 'comment' => 'FOSSBilling user 2'));

		$newuser = $proxmox->get("/access/users/" . $userid);
		if (!$newuser) {
			throw new \Box_Exception("Failed to create test user for FOSSBilling");
		} else {
			// Delete user
			$deleteuser = $proxmox->delete("/access/users/" . $userid);
			$deleteuser = $proxmox->get("/access/users/" . $userid);
			if ($deleteuser) {
				throw new \Box_Exception("Failed to delete test user for FOSSBilling. Check Permissions");
			} else {
				//delete root password from server
				$server->root_password = null;
				return $server;
			}
		}
	}

	// Function to create a new proxmox User on the server and save the token in the database
	public function create_client_user($server, $client)
	{
		$clientuser = $this->di['db']->dispense('service_proxmox_users');
		$clientuser->client_id = $client->id;
		$this->di['db']->store($clientuser);
		$serveraccess = $this->find_access($server);
		$proxmox = new PVE2_API($serveraccess, $server->root_user, $server->realm, $server->root_password, tokenid: $server->tokenname, tokensecret: $server->tokenvalue);
		if (!$proxmox->login()) {
			throw new \Box_Exception("Failed to connect to the server. create_client_user");
		}
		$userid = 'fb_customer_' . $client->id . '@pve'; // TODO: Make realm configurable in the module settings
		$newuser = $proxmox->post("/access/users", array('userid' => $userid, 'password' => $this->generateRandomPassword(16), 'enable' => '1', 'comment' => 'FOSSBilling user ' . $client->id));
		$newuser = $proxmox->get("/access/users/" . $userid);

		// Create Token for Client
		$clientuser->admin_tokenname = 'fb_admin_' . $client->id;
		$clientuser->admin_tokenvalue = $proxmox->post("/access/users/" . $userid . "/token" . $clientuser->admin_tokenname, array());
		$clientuser->view_tokenname = 'fb_view_' . $client->id;
		$clientuser->view_tokenvalue = $proxmox->post("/access/users/" . $userid . "/token" . $clientuser->view_tokenname, array());
		$this->di['db']->store($clientuser);

		// Check if the client already has a pool and if not create it.
		$pool = $proxmox->get("/pools/" . $client->id);
		if (!$pool) {
			$pool = $proxmox->post("/pools", array('poolid' => $client->id, 'comment' => 'FOSSBilling pool ' . $client->id));
		}
		// Add permissions for client
		$permissions = $proxmox->put("/access/acl/", array('path' => '/pool/' . $client->id, 'roles' => 'PVEVMUser', 'propagate' => 1, 'users' => $userid));
		$permissions = $proxmox->put("/access/acl/", array('path' => '/pool/' . $client->id, 'roles' => 'PVEVMAdmin', 'propagate' => 1, 'users' => $userid));
		$permissions = $proxmox->put("/access/acl/", array('path' => '/pool/' . $client->id, 'roles' => 'PVEDatastoreAdmin', 'propagate' => 1, 'users' => $userid));
		$permissions = $proxmox->put("/access/acl/", array('path' => '/pool/' . $client->id, 'roles' => 'PVEVMUser', 'propagate' => 1, 'tokens' => $clientuser->view_tokenname));
		$permissions = $proxmox->put("/access/acl/", array('path' => '/pool/' . $client->id, 'roles' => 'PVEVMAdmin', 'propagate' => 1, 'tokens' => $clientuser->admin_tokenname));
		$permissions = $proxmox->put("/access/acl/", array('path' => '/pool/' . $client->id, 'roles' => 'PVEDatastoreUser', 'propagate' => 1, 'tokens', $clientuser->view_tokenname));
		$permissions = $proxmox->put("/access/acl/", array('path' => '/pool/' . $client->id, 'roles' => 'PVEDatastoreAdmin', 'propagate' => 1, 'tokens', $clientuser->admin_tokenname));
	}

	/* ################################################################################################### */
	/* ######################################  Helper functions  ######################################### */
	/* ################################################################################################### */

	public function generateRandomPassword($length = 8)
	{
		$characters = 'abcdefghijklmnopqrstuvwxy#@€zABCDEFGHI.J$KLM!NOPQRSTUVWXYZ0123456789';
		$password = '';

		$max = strlen($characters) - 1;
		for ($i = 0; $i < $length; $i++) {
			$index = random_int(0, $max);
			$password .= $characters[$index];
		}

		return $password;
	}
}
