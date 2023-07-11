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

use PhpZip\Exception\ZipException;
use PhpZip\ZipFile;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use \Model_Extension;

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
		// read manifest.json to get current version number
		$manifest = json_decode(file_get_contents(__DIR__ . '/manifest.json'), true);
		$version = $manifest['version'];

		// check if there is a sqldump backup with "uninstall" in it's name in the pmxconfig folder, if so, restore it
		$filesystem = new Filesystem();
		// list content of pmxconfig folder using symfony finder
		$finder = new Finder();

		// check if pmxconfig folder exists
		if (!$filesystem->exists(PATH_ROOT . '/pmxconfig')) {
			$filesystem->mkdir(PATH_ROOT . '/pmxconfig');
		}

		$pmxbackup_dir = $finder->in(PATH_ROOT . '/pmxconfig')->files()->name('proxmox_uninstall_*.sql');

		// find newest file in pmxbackup_dir according to timestamp
		$pmxbackup_file = array();
		foreach ($pmxbackup_dir as $file) {
			$pmxbackup_file[$file->getMTime()] = $file->getFilename();
		}
		ksort($pmxbackup_file);
		$pmxbackup_file = array_reverse($pmxbackup_file);
		$pmxbackup_file = reset($pmxbackup_file);
		
		// if pmxbackup_file is not empty, restore the sql dump to database
		if (!empty($pmxbackup_file)) {
			$dump = file_get_contents(PATH_ROOT.'/pmxconfig/'.$pmxbackup_file);
			// check if dump is not empty
			if (!empty($dump)) {
				// check version number in first line of dump
				$original_dump = $dump;
				$version_line = strtok($dump, "\n");
				// get version number from line
				$dump = str_replace('-- Proxmox module version: ', '', $version_line);	
				// if version number in dump is smaller than current version number, restore dump and run upgrade function
				if ($dump < $version) {
					$this->di['db']->exec($original_dump);
					$this->upgrade($version); // Runs all migrations between current and next version
				} elseif ($dump == $version) {
					$this->di['db']->exec($original_dump);
				} else {
					throw new \Box_Exception("The version number of the sql dump is bigger than the current version number of the module. Please check the installed Module version.", null, 9684);
				}
			}
		} else 
		{
		// Run migrations from smallest to biggest version number
		$migrations = glob(__DIR__ . '/migrations/*.sql');
		// Sort migrations by version number (Filenames: 0.0.1.sql, 0.0.2.sql etc.)
		usort($migrations, function ($a, $b) {
			return version_compare(basename($a, '.sql'), basename($b, '.sql'));
		});
		
		foreach ($migrations as $migration) {
			// get version from filename
			$filename = basename($migration, '.sql');
			$version = str_replace('_', '.', $filename);
			// run migration
			$this->di['db']->exec(file_get_contents($migration));
			}
		
		// Create table for vm 
		}
		return true;
	}


	/**
	 * Method to uninstall module.
	 *
	 * @return bool
	 */
	public function uninstall()
	{
		$this->pmxdbbackup('uninstall');
		$this->di['db']->exec("DROP TABLE IF EXISTS `service_proxmox`");
		$this->di['db']->exec("DROP TABLE IF EXISTS `service_proxmox_server`");
		$this->di['db']->exec("DROP TABLE IF EXISTS `service_proxmox_users`");
		$this->di['db']->exec("DROP TABLE IF EXISTS `service_proxmox_storage`");
		$this->di['db']->exec("DROP TABLE IF EXISTS `service_proxmox_templates`");
		return true;
	}


	// Function to upgrade module Database

	public function upgrade($previous_version)
	{
		// read current module version from manifest.json
		$manifest = json_decode(file_get_contents(__DIR__ . '/manifest.json'), true);
		$current_version = $manifest['version'];

		// read migrations directory and find all files between current version and previous version
		$migrations = glob(__DIR__ . '/migrations/*.sql');
		foreach ($migrations as $migration) {
			// get version from filename
			$filename = basename($migration, '.sql');
			$version = str_replace('_', '.', $filename);
			// check if version is between previous and current version
			if (version_compare($version, $previous_version, '>') && version_compare($version, $current_version, '<=')) {
				// run migration
				$this->di['db']->exec(file_get_contents($migration));
			}
		}
		return true;
	}


	// Function to create configuration Backups of Proxmox tables
	public function pmxdbbackup($data)
	{
		// create backup of all Proxmox tables
		try {
			$filesystem = new Filesystem();
			$filesystem->mkdir([PATH_ROOT . '/pmxconfig'], 0750);
		} catch (IOException $e) {
			error_log($e->getMessage());
			throw new \Box_Exception('Unable to create directory pmxconfig');
		}
		// create filename with timestamp
		// check if $data is 'uninstall' or 'backup'
		if ($data == 'uninstall') {
			$filename = '/pmxconfig/proxmox_uninstall_' . date('Y-m-d_H-i-s') . '.sql';
		} else {
			$filename = '/pmxconfig/proxmox_backup_' . date('Y-m-d_H-i-s') . '.sql';
		}

		$backup_command = 'mysqldump -u ' . $this->di['config']['db']['user'] . ' -p' . $this->di['config']['db']['password'] . ' ' . $this->di['config']['db']['name'] . ' service_proxmox service_proxmox_server service_proxmox_users service_proxmox_storage > ' . PATH_ROOT . $filename;
		try {
			exec($backup_command);
		} catch (Exception $e) {
			throw new \Box_Exception('Unable to run exec command, please enable exec in php.ini');
		}
		// read current module version from manifest.json
		$manifest = json_decode(file_get_contents(__DIR__ . '/manifest.json'), true);
		$current_version = $manifest['version'];
		// add version comment to backup file
		$version_comment = '-- Proxmox module version: ' . $current_version . "\n";
		$filename = PATH_ROOT . $filename;
		$handle = fopen($filename, 'r+');
		$len = strlen($version_comment);
		$final_len = filesize($filename) + $len;
		$cache_old = fread($handle, $len);
		rewind($handle);
		$i = 1;
		while (ftell($handle) < $final_len) {
			fwrite($handle, $version_comment);
			$version_comment = $cache_old;
			$cache_old = fread($handle, $len);
			fseek($handle, $i * $len);
			$i++;
		}
		fclose($handle);

		return true;
	}

	// Function to list all Proxmox backups
	public function pmxbackuplist()
	{
		$files = glob(PATH_ROOT . '/pmxconfig/*.sql');
		$backups = array();
		foreach ($files as $file) {
			$backups[] = basename($file);
		}
		return $backups;
	}

	// function to restore Proxmox tables from backup
	public function pmxbackuprestore($data)
	{
		// get filename from $data and see if it exists using finder
		$manifest = json_decode(file_get_contents(__DIR__ . '/manifest.json'), true);
		$version = $manifest['version'];
		//if the file exists, restore it
		$dump = file_get_contents(PATH_ROOT.'/pmxconfig/'.$data['backup']);
		// check if dump is not empty
		if (!empty($dump)) {
			// check version number in first line of dump format: 
			// -- Proxmox module version: 0.0.5
			// get first line of dump
			$version_line = strtok($dump, "\n");
			// get version number from line
			$dump = str_replace('-- Proxmox module version: ', '', $version_line);


			// if version number in dump is smaller than current version number, restore dump and run upgrade function
			if ($dump == $version) {
				$dump_command = 'mysql -u ' . $this->di['config']['db']['user'] . ' -p' . $this->di['config']['db']['password'] . ' ' . $this->di['config']['db']['name'] . ' < ' . PATH_ROOT . '/pmxconfig/' . $data['backup'];
				try {
					exec($dump_command);
				} catch (Exception $e) {
					throw new \Box_Exception('Unable to run exec command, please enable exec in php.ini');
				}
				return true;
			} else {
				throw new \Box_Exception("The sql dump file (V: $dump is not compatible with the current module version (V: $version). Please check the file.", null);
			}
		} else {
			throw new \Box_Exception("The sql dump file is empty. Please check the file.", null);
		}
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
