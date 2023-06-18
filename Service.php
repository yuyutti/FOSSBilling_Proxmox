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
	use ProxmoxAuthentication;
	use ProxmoxServer;
	use ProxmoxVM;

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
			`cpu_cores_allocated` bigint(20) DEFAULT NULL,
			`ram` bigint(20) DEFAULT NULL,
			`ram_allocated` bigint(20) DEFAULT NULL,
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
			`server_id` bigint(20) DEFAULT NULL,
			`admin_tokenname` varchar(255) DEFAULT NULL,
			`admin_tokenvalue` varchar(255) DEFAULT NULL,
			`view_tokenname` varchar(255) DEFAULT NULL,
			`view_tokenvalue` varchar(255) DEFAULT NULL,
            `created_at` varchar(35) DEFAULT NULL,
            `updated_at` varchar(35) DEFAULT NULL,
            PRIMARY KEY (`id`),
			KEY `client_id_idx` (`client_id`),
			KEY `server_id_idx` (`server_id`)
            ) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;";
		$this->di['db']->exec($sql);

		// create table for storage class information
		$sql = "
		CREATE TABLE IF NOT EXISTS `service_proxmox_storageclass` (
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

	// Create function that runs with cron job hook
	/*
	public static function onBeforeAdminCronRun(\Box_Event $event)
	{
		 // getting Dependency Injector
		 $di = $event->getDi();

		 // @note almost in all cases you will need Admin API
		 $api = $di['api_admin'];
		 // get all servers from database
		 // like this $vms = $this->di['db']->findAll('service_proxmox', 'server_id = :server_id', array(':server_id' => $data['id']));
 		 $servers = $di['db']->findAll('service_proxmox_server');
		 // rum getHardwareData, getStorageData and getAssignedResources for each server 
		 foreach ($servers as $server) {
			$hardwareData = $api->getHardwareData($server['id']);
			$storageData = $api->getStorageData($server['id']);
			$assignedResources = $api->getAssignedResources($server['id']);
		  }
	} */


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

		// Find suitable server and save it to service_proxmox
		$$model->server_id = $this->find_empty($product);
		// Retrieve server info

		$this->di['db']->store($model);

		return $model;
	}

	/**
	 * @param \Model_ClientOrder $order
	 * @return boolean
	 */
	public function activate($order, $model)
	{
		if (!is_object($model)) {
			throw new \Box_Exception('Could not activate order. Service was not created');
		}
		$config = json_decode($order->config, 1);
		//$this->validateOrderData($c);//
		$client  = $this->di['db']->load('client', $order->client_id);
		$product = $this->di['db']->load('product', $order->product_id);
		if (!$product) {
			throw new \Box_Exception('Could not activate order because ordered product does not exists');
		}
		$product_config = json_decode($product->config, 1);

		// Allocate to an appropriate server id
		$server = $this->di['db']->load('service_proxmox_server', $model->server_id);

		// Retrieve or create client unser account in service_proxmox_users
		$clientuser = $this->di['db']->findOne('service_proxmox_users', 'server_id = ? and client_id = ?', array($server->id, $client->id));
		if (!$clientuser) {
			$this->create_client_user($server, $client);
		}

		// Connect to Proxmox API
		$serveraccess = $this->find_access($server);
		// find client permissions for server
		$clientuser = $this->di['db']->findOne('service_proxmox_users', 'server_id = ? and client_id = ?', array($server->id, $client->id));
		$proxmox = new PVE2_API($serveraccess, $server->root_user, $server->realm, $server->root_password, tokenid: $clientuser->admin_tokenname, tokensecret: $clientuser->admin_tokenvalue);

		// Create Proxmox VM
		if ($proxmox->login()) {

			//$proxmox->delete("/nodes/".$model->node."/qemu/".$model->vmid);
			//return var_export($promox, true);

			// compile VMID by combining the server id, the client id, and the order id separated by padded zeroes for thee numbers per variable
			$vmid = $server->id . str_pad($client->id, 3, '0', STR_PAD_LEFT) . str_pad($order->id, 3, '0', STR_PAD_LEFT);

			// check if vmid is already in use
			$vmid_in_use = $proxmox->get("/nodes/" . $server->node . "/qemu/" . $vmid);
			if ($vmid_in_use) {
				$vmid = $vmid . '1';
			}

			$proxmoxuser_password = $this->di['tools']->generatePassword(16, 4); // Generate password

			// Create VM
			$clone = '';
			$container_settings = array();
			$description = 'Service package ' . $model->id . ' belonging to client id: ' . $client->id;

			if ($product_config['clone'] == true) {
				$clone = '/' . $product_config['cloneid'] . '/clone'; // Define the route for cloning
				$container_settings = array(
					'newid' => $vmid,
					'name' => $model->username,
					'description' => $description,
					'full' => true
				);
			} else { // TODO: Fix Container templates 
				/* postdata= {
					"vmid":"2001002",
					"node":"dct-pub-vh01",
					"name":"vm2001002",
					"description":"Service package 1 belonging to client id: 1",
					"storage":"Storage01",
					"memory":"256",
					"ostype":"126",
					"ide2":"none,media=cdrom",
					"sockets":"1",
					"cores":"1",
					"numa":"0",
					"scsihw":"virtio-scsi-single",
					"scsi0":"Storage01:10GB,iothread=on",
					"pool":"fb_client_1",
					"net":"virtio,bridge=vmbr0,firewall=1"
					} */
				if ($product_config['virt'] == 'qemu') {
					$container_settings = array(
						'vmid' => $vmid,
						'name' => 'vm' . $vmid,                 // Hostname to define
						'node' => $server->name,                // Node to create the VM on
						'description' => $description,
						'storage' => $product_config['storage'],
						'memory' => $product_config['memory'],
						'scsihw' => 'virtio-scsi-single',
						'scsi0' => "Storage01:10",
						'ostype' => "other",
						'kvm' => "0",
						'ide2' => $product_config['cdrom'] . ',media=cdrom',
						'sockets' => $product_config['cpu'],
						'cores' => $product_config['cpu'],
						'numa' => "0",
						'pool' => 'fb_client_' . $client->id,
					);
				} else {
					$container_settings = array(
						'vmid' => $vmid,
						'hostname' => 'vm' . $vmid,                // Hostname to define
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
			//echo "Debug:\n " . json_encode($container_settings) . "\n \n";
			// If the1 VM is properly created
			$vmurl = "/nodes/" . $server->name . "/" . $product_config['virt'] . $clone;
			//echo "Debug:\n " . $vmurl . "\n \n";
			$vmcreate = $proxmox->post($vmurl, $container_settings);
			//echo "Debug:\n " . var_dump($vmcreate) . "\n \n";
			if ($vmcreate) {

				// Start the server
				sleep(20);
				$proxmox->post("/nodes/" . $server->name . "/" . $product_config['virt'] . "/" . $vmid . "/status/start", array());
				$status = $proxmox->get("/nodes/" . $server->name . "/" . $product_config['virt'] . "/" . $vmid . "/status/current");

				if (!empty($status)) {
					sleep(10);
					// Wait until it has been started
					while ($status['status'] != 'running') {
						$proxmox->post("/nodes/" . $server->name . "/" . $product_config['virt'] . "/" . $vmid . "/status/start", array());
						sleep(10);
						$status = $proxmox->get("/nodes/" . $server->name . "/" . $product_config['virt'] . "/" . $vmid . "/status/current");
						// Starting twice => error...
					}
				} else {
					throw new \Box_Exception("VMID cannot be found");
				}
			} else {
				throw new \Box_Exception("VPS has not been created");
			}
		} else {
			throw new \Box_Exception('Login to Proxmox Host failed with client credentials', null, 7457);
		}

		// Retrieve VM IP

		$model->server_id 		= $model->server_id;
		$model->updated_at    	= date('Y-m-d H:i:s');
		$model->vmid			= $vmid;
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

	// function to get novnc_appjs file
	public function get_novnc_appjs($data)
	{
		// get list of servers

		$servers = $this->di['db']->find('service_proxmox_server');
		// select first server
		$server = $servers['2'];

		$hostname = $server->hostname;
		// build url

		$url = "https://$hostname:8006/novnc/" . $data; //$data['ver'];
		// get file using symphony http client
		// set options
		$client = $this->getHttpClient()->withOptions([
			'verify_peer' => false,
			'verify_host' => false,
			'timeout' => 60,
		]);
		$result = $client->request('GET', $url);
		//echo "<script>console.log('Debug Objects: " . $result->getContent() . "' );</script>";
		// return file
		return $result;
	}

	public function getHttpClient()
	{
		return \Symfony\Component\HttpClient\HttpClient::create();
	}
}
