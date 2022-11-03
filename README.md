# Proxmox module for FOSSBilling
![Screenshot](https://user-images.githubusercontent.com/35808275/199811426-9d4708d2-e83c-47b3-b586-74e797b3fbf4.png)
Initial Proxmox support for FOSSBilling. This module is still in development and not ready for production use.

## Features
- Manage pools of Proxmox servers (orders can be allocated to servers automatically based on their capacity)
- Provision LXC containers
- Provision QEMU KVM machines
- Clients can use an online console, start, shutdown and reboot their VMs (not working right now)

## Installation
- Copy the "Serviceproxmox" folder in *bb-modules*
- Add new Proxmox servers
- Add new Proxmox products with the correct VM settings setup

## Licensing
This module is licensed under the GNU General Public License v3.0. See the LICENSE file for more information.
