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
trait ProxmoxAuthentication
{
	/* ################################################################################################### */
	/* ########################################  Permissions  ############################################ */
	/* ################################################################################################### */

	/**
	 * Function to set up proxmox server for fossbilling
	 * @param $server - server object
	 * @return array - array of permission information
	 */
	public function prepare_pve_setup($server)
	{

		$serveraccess = $this->find_access($server);
		$proxmox = new PVE2_API($serveraccess, $server->root_user, $server->realm, $server->root_password, port: $server->port, tokenid: $server->tokenname, tokensecret: $server->tokenvalue);
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

			// Validate if there already is a group for fossbilling
			$groups = $proxmox->get("/access/groups");
			$foundgroups = 0;
			foreach ($groups as $group) {
				//check if group beginning with fossbilling exists
				if (strpos($group['groupid'], 'fossbilling') === 0) {
					$foundgroups += 1;
					$groupid = $group['groupid'];
				}
			}
			// switch case there is no group, create one, if there is one, use it, if there are more than one, throw error
			switch ($foundgroups) {
				case 0:
					// Create Group
					$groupid = 'fossbilling_' . rand(1000, 9999);
					$newgroup = $proxmox->post("/access/groups", array('groupid' => $groupid, 'comment' => 'fossbilling group',));
					break;
				case 1:
					// Use Group
					break;
				default:
					throw new \Box_Exception("More than one group found");
					break;
			}


			// Validate if there already is a user & token for fossbilling
			$users = $proxmox->get("/access/users");
			$found = 0;
			foreach ($users as $user) {
				//check if user beginning with fossbilling exists
				if (strpos($user['userid'], 'fb') === 0) {
					$found += 1;
					$userid = $user['userid'];
				}
				// switch case there is no user, create one, if there is one, use it, if there are more than one, throw error
				switch ($found) {
					case 0:
						// Create user
						$userid = 'fb_' . rand(1000, 9999) . '@pve'; // TODO: Make realm configurable in the module settings
						$newuser = $proxmox->post("/access/users", array('userid' => $userid, 'password' => $this->di['tools'], 'enable' => 1, 'comment' => 'fossbilling user', 'groups' => $groupid,));

						// Create token

						$token = $proxmox->post("/access/users/" . $userid . "/token/fb_access", array());
						// check if token was created
						if ($token) {
							$server->tokenname = $token['full-tokenid'];
							$server->tokenvalue = $token['value'];
						} else {
							throw new \Box_Exception("Failed to create token for fossbilling user");
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
							throw new \Box_Exception("Failed to create token for fossbilling user");
							break;
						}
						break;
					default:
						throw new \Box_Exception("There are more than one fossbilling users on the server. Please delete all but one.");
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
		$proxmox = new PVE2_API($serveraccess, $server->root_user, $server->realm, $server->root_password, port: $server->port, tokenid: $server->tokenname, tokensecret: $server->tokenvalue);
		if (!$proxmox->login()) {
			throw new \Box_Exception("Failed to connect to the server. testpmx");
		}

		$userid = 'tfb_' . rand(1000, 9999) . '@pve'; // TODO: Make realm configurable in the module settings
		$newuser = $proxmox->post("/access/users", array('userid' => $userid, 'password' => $this->di['tools']->generatePassword(16, 4), 'enable' => '1', 'comment' => 'fossbilling user 2'));

		$newuser = $proxmox->get("/access/users/" . $userid);
		if (!$newuser) {
			throw new \Box_Exception("Failed to create test user for fossbilling");
		} else {
			// Delete user
			$deleteuser = $proxmox->delete("/access/users/" . $userid);
			$deleteuser = $proxmox->get("/access/users/" . $userid);
			if ($deleteuser) {
				throw new \Box_Exception("Failed to delete test user for fossbilling. Check Permissions");
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
		$proxmox = new PVE2_API($serveraccess, $server->root_user, $server->realm, $server->root_password, port: $server->port, tokenid: $server->tokenname, tokensecret: $server->tokenvalue);
		if (!$proxmox->login()) {
			throw new \Box_Exception("Failed to connect to the server. create_client_user");
		}
		$userid = 'fb_customer_' . $client->id . '@pve'; // TODO: Make realm configurable in the module settings
		$newuser = $proxmox->post("/access/users", array('userid' => $userid, 'password' => $this->di['tools']->generatePassword(16, 4), 'enable' => '1', 'comment' => 'fossbilling user ' . $client->id));
		$newuser = $proxmox->get("/access/users/" . $userid);

		// Create Token for Client
		$clientuser->admin_tokenname = 'fb_admin_' . $client->id;
		$clientuser->server_id = $server->id;
		$admintoken_response = $proxmox->post("/access/users/" . $userid . "/token/" . $clientuser->admin_tokenname, array('comment' => 'fossbilling admin token for client id: ' . $client->id));
		$clientuser->admin_tokenname = $admintoken_response['full-tokenid'];
		$clientuser->admin_tokenvalue = $admintoken_response['value'];
		$clientuser->view_tokenname = 'fb_view_' . $client->id;
		$viewtoken_response = $proxmox->post("/access/users/" . $userid . "/token/" . $clientuser->view_tokenname, array('comment' => 'fossbilling view token for client id: ' . $client->id));
		$clientuser->view_tokenname = $viewtoken_response['full-tokenid'];
		$clientuser->view_tokenvalue = $viewtoken_response['value'];


		$this->di['db']->store($clientuser);

		// Check if the client already has a pool and if not create it.
		$pool = $proxmox->get("/pools/" . $client->id);
		if (!$pool) {
			$pool = $proxmox->post("/pools", array('poolid' => 'fb_client_' . $client->id, 'comment' => 'fossbilling pool for client id: ' . $client->id));
		}
		// Add permissions for client

		$permissions = $proxmox->put("/access/acl/", array('path' => '/pool/' . 'fb_client_' . $client->id, 'roles' => 'PVEVMUser,PVEVMAdmin,PVEDatastoreAdmin,PVEDatastoreUser', 'propagate' => 1, 'users' => $userid));
		/* 
		$permissions = $proxmox->put("/access/acl/", array('path' => '/pool/' . 'fb_client_' . $client->id, 'roles' => 'PVEVMAdmin', 'propagate' => 1, 'users' => $userid));
		$permissions = $proxmox->put("/access/acl/", array('path' => '/pool/' . 'fb_client_' . $client->id, 'roles' => 'PVEDatastoreAdmin', 'propagate' => 1, 'users' => $userid));
		$permissions = $proxmox->put("/access/acl/", array('path' => '/pool/' . 'fb_client_' . $client->id, 'roles' => 'PVEDatastoreUser', 'propagate' => 1, 'users' => $userid));
		 */

		$permissions = $proxmox->put("/access/acl/", array('path' => '/pool/' . 'fb_client_' . $client->id, 'roles' => 'PVEVMUser,PVEDatastoreUser', 'propagate' => 1, 'tokens' => $clientuser->view_tokenname));
		//$permissions = $proxmox->put("/access/acl/", array('path' => '/pool/' . 'fb_client_' . $client->id, 'roles' => 'PVEDatastoreUser', 'propagate' => 1, 'tokens', $clientuser->view_tokenname));

		$permissions = $proxmox->put("/access/acl/", array('path' => '/pool/' . 'fb_client_' . $client->id, 'roles' => 'PVEVMAdmin,PVEDatastoreAdmin', 'propagate' => 1, 'tokens' => $clientuser->admin_tokenname));
		//$permissions = $proxmox->put("/access/acl/", array('path' => '/pool/' . 'fb_client_' . $client->id, 'roles' => 'PVEDatastoreAdmin', 'propagate' => 1, 'tokens', $clientuser->admin_tokenname));


	}
}
