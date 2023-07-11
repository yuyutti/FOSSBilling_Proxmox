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
trait ProxmoxTemplates
{
	/* ################################################################################################### */
	/* ####################################  VM Template Mgmt  ########################################### */
	/* ################################################################################################### */
	
	// Function that gets all the VM templates and returns them as an array
	public function get_vmtemplates()
	{
		// get all the VM templates from the service_proxmox_vm_config_template table
		$templates = $this->di['db']->findAll('service_proxmox_vm_config_template');
		return $templates;
	}
}