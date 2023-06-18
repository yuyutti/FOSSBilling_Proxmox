# Proxmox module for FOSSBilling
Initial Proxmox support for FOSSBilling. 

**This module is still in development and not ready for production use.**

Based on [previous work](https://github.com/scith/BoxBilling_Proxmox) by [Scith](https://github.com/scith).


## Server List
![Serveroverview](https://github.com/Anuril/Proxmox/assets/1939311/96629395-e9a5-4029-a86f-fbd86b34b42c)

## Features
- Manage pools of Proxmox servers (orders can be allocated to servers automatically based on their capacity)
- Complete Privilege Separation (each client can only see their own VMs),
- Admin can't see inside client's VMs (Only the VM ID, Name, Status, IP, RAM, CPU, Disk, Bandwidth, etc.)
- Provision LXC containers
- Provision QEMU KVM machines
- Clients can start, shutdown and reboot their VMs (online console not working right now)
- Proxmox Servers do not have to be reachable from the Internet (only the FOSSBilling server needs to be able to reach the Proxmox server)

## TODOs:
- Prevent disaster by "just" deleting x,y,z
- Better Error Handling when creating unexpected things happen & get returned from pve host.
- Better VM Allocation procedure
- Consistent Naming: Server => Proxmox Virtual Environment Server | VM => Virtual Machine
- VM & LXC Template setup need to be fixed and simplified
- Provisioning of VMs with Cloudinit (https://pve.proxmox.com/wiki/Cloud-Init_Support)
- Work on Usability to configure products and manage customer's products

## Requirements
- Tested on Proxmox VE 7 or higher, PVE 6 should work too


## Installation
- Copy the "Serviceproxmox" folder in *modules*
- Configure the Proxmox module in the FOSSBilling admin area
- Add new Proxmox servers
- "Prepare Server" on each Proxmox server (this will create the necessary API user and role)
- Add new Proxmox products with the correct VM settings setup


## (New) Storage List 
![Storageoverview](https://github.com/Anuril/Proxmox/assets/1939311/139d7d32-3fe3-45b3-b0cd-e2e7e6f5af0e)

## (New) Settings
![Settings](https://github.com/Anuril/Proxmox/assets/1939311/f6f4bfe6-071b-4a91-9027-6f6dbf2dfb06)


## Licensing
This module is licensed under the GNU General Public License v3.0. See the LICENSE file for more information.
