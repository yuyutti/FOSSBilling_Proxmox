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

namespace Box\Mod\Serviceproxmox\Controller;

class Admin implements \FOSSBilling\InjectionAwareInterface
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

    public function fetchNavigation()
    {
        return array(
            'subpages'  =>  array(
                array(
                    'location'  => 'system',
                    'index'     => 140,
                    'label' => 'Proxmox servers',
                    'uri'   => $this->di['url']->adminLink('serviceproxmox'),
                    'class' => '',
                ),
            ),
        );
    }

    public function register(\Box_App &$app)
    {
        $app->get('/serviceproxmox', 'get_index', null, get_class($this));
        $app->get('/serviceproxmox/maintenance/backup', 'start_backup', null, get_class($this));
        $app->get('/serviceproxmox/server/:id', 'get_server', array('id' => '[0-9]+'), get_class($this));
        $app->get('/serviceproxmox/storage', 'get_storage', null, get_class($this));
        $app->get('/serviceproxmox/storage/:id', 'get_storage', array('id' => '[0-9]+'), get_class($this));
        $app->get('/serviceproxmox/storageclass', 'get_storage', null, get_class($this));
        $app->get('/serviceproxmox/storageclass/:id', 'get_storageclass ', array('id' => '[0-9]+'), get_class($this));
        // fake route for app.js from proxmox

        // TODOS:
        // add vm management
        // add vm templates management
        // add vm backups management
        // add vm snapshots management
    }

    public function get_index(\Box_App $app)
    {
        $this->di['is_admin_logged'];
        return $app->render('mod_serviceproxmox_index');
    }

    public function get_server(\Box_App $app, $id)
    {
        $this->di['is_admin_logged'];
        $api = $this->di['api_admin'];
        $server = $api->Serviceproxmox_server_get(array('server_id' => $id));
        return $app->render('mod_serviceproxmox_server', array('server' => $server));
    }

    public function get_storage(\Box_App $app, $id)
    {
        $this->di['is_admin_logged'];
        $api = $this->di['api_admin'];
        $storage = $api->Serviceproxmox_storage_get(array('storage_id' => $id));
        return $app->render('mod_serviceproxmox_storage', array('storage' => $storage));
    }

    // Function to start backup via API
    public function start_backup(\Box_App $app)
    {
        $this->di['is_admin_logged'];
        $api = $this->di['api_admin'];
        $backup = $api->Serviceproxmox_proxmox_backup_config('backup');
        return $app->redirect('extension/settings/serviceproxmox');
    }

}
