<?php

use App\Helpers\ExtensionHelper;
use App\Models\Extension;
use App\Models\OrderProduct;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

function Proxmox_getConfig()
{
    return [
        [
            'name' => 'host',
            'friendlyName' => 'Host',
            'type' => 'text',
            'required' => true,
            'description' => 'The IP address or domain name of the Proxmox server (with http:// or https://)',
        ],
        [
            'name' => 'port',
            'friendlyName' => 'Port',
            'type' => 'text',
            'required' => true,
            'description' => 'The port of the Proxmox server',
        ],
        [
            'name' => 'username',
            'friendlyName' => 'Username',
            'type' => 'text',
            'required' => true,
            'description' => 'The username of the Proxmox server',
        ],
        [
            'name' => 'password',
            'friendlyName' => 'Password',
            'type' => 'text',
            'required' => true,
            'description' => 'The password of the Proxmox server',
        ]
    ];
}

function Proxmox_getProductConfig($options)
{
    $nodes = Proxmox_getRequest('/nodes');
    if (!$nodes->json()) throw new Exception('Unable to get nodes');
    foreach ($nodes->json()['data'] as $node) {
        $nodeList[] = [
            'name' => $node['node'],
            'value' => $node['node']
        ];
    }

    $currentNode = isset($options['node']) ? $options['node'] : null;
    $storageName = isset($options['storage']) ? $options['storage'] : null;
    if ($currentNode == null) {
        $currentNode = $nodeList[0]['value'];
    }
    $storage = Proxmox_getRequest('/nodes/' . $currentNode . '/storage');
    $storageList = [];
    if (!$storage->json()) throw new Exception('Unable to get storage');
    foreach ($storage->json()['data'] as $storage) {
        $storageList[] = [
            'name' => $storage['storage'],
            'value' => $storage['storage']
        ];
    }

    $resourcePool = Proxmox_getRequest('/pools');
    $poolList = [
        [
            'name' => 'None',
            'value' => ''
        ]
    ];

    if (!$resourcePool->json()) throw new Exception('Unable to get resource pool');
    foreach ($resourcePool->json()['data'] as $pool) {
        $poolList[] = [
            'name' => $pool['poolid'],
            'value' => $pool['poolid']
        ];
    }

    // Only list contentVztmpl 
    $templateList = [];
    $isoList = [];
    foreach ($nodeList as $node) {
        // Get all storage
        $storage = Proxmox_getRequest('/nodes/' . $node['value'] . '/storage');
        if (!$storage->json()) throw new Exception('Unable to get storage');
        foreach ($storage->json()['data'] as $storage) {
            $storageName = $storage['storage'];
            $template = Proxmox_getRequest('/nodes/' . $node['value'] . '/storage/' . $storageName . '/content');
            if (!$template->json()) throw new Exception('Unable to get template');
            foreach ($template->json()['data'] as $template) {
                if ($template['content'] == 'vztmpl') {
                    $templateList[] = [
                        'name' => $template['volid'],
                        'value' => $template['volid']
                    ];
                } else if ($template['content'] == 'iso') {
                    $isoList[] = [
                        'name' => $template['volid'],
                        'value' => $template['volid']
                    ];
                }
            }
        }
    }



    $bridgeList = [];
    $bridge = Proxmox_getRequest('/nodes/' . $currentNode . '/network');
    if (!$bridge->json()) throw new Exception('Unable to get bridge');
    foreach ($bridge->json()['data'] as $bridge) {
        if (!isset($bridge['active'])) continue;
        if (!$bridge['active']) continue;
        $bridgeList[] = [
            'name' => $bridge['iface'],
            'value' => $bridge['iface']
        ];
    }



    return [
        [
            'type' => 'title',
            'friendlyName' => 'General',
            'description' => 'General options',
        ],
        [
            'name' => 'node',
            'type' => 'dropdown',
            'friendlyName' => 'Node',
            'required' => true,
            'description' => 'The node name of the wanted node (submit to update the storage list)',
            'options' =>  $nodeList
        ],
        [
            'name' => 'storage',
            'type' => 'dropdown',
            'friendlyName' => 'Storage',
            'description' => 'The storage name of the wanted storage',
            'options' =>  $storageList
        ],
        [
            'name' => 'pool',
            'type' => 'dropdown',
            'friendlyName' => 'Resource Pool',
            'description' => 'Resource Pool places VMs in a group',
            'options' =>  $poolList
        ],
        [
            'name' => 'type',
            'type' => 'dropdown',
            'friendlyName' => 'Type',
            'required' => true,
            'description' => 'The type of the wanted VM',
            'options' => [
                [
                    'name' => 'qemu',
                    'value' => 'qemu'
                ],
                [
                    'name' => 'lxc',
                    'value' => 'lxc'
                ]
            ]
        ],
        [
            'name' => 'cores',
            'type' => 'text',
            'friendlyName' => 'Cores',
            'required' => true,
            'description' => 'The number of cores of the wanted VM',
        ],
        [
            'name' => 'memory',
            'type' => 'text',
            'friendlyName' => 'Memory (MB)',
            'required' => true,
            'description' => 'The amount of memory of the wanted VM',
        ],
        [
            'name' => 'disk',
            'type' => 'text',
            'friendlyName' => 'Disk (GB)',
            'required' => true,
            'description' => 'The amount of disk of the wanted VM',
        ],


        [
            'name' => 'lxc',
            'type' => 'title',
            'friendlyName' => 'LXC',
            'description' => 'All LXC options',
        ],
        [
            'name' => 'template',
            'type' => 'dropdown',
            'friendlyName' => 'Template',
            'description' => 'The template name of the wanted VM',
            'options' => $templateList
        ],
        [
            'name' => 'unprivileged',
            'type' => 'boolean',
            'friendlyName' => 'Unprivileged Container',
            'description' => 'Enable/disable unprivileged container',
        ],
        [
            'name' => 'nesting',
            'type' => 'boolean',
            'friendlyName' => 'Nesting',
            'description' => 'Enable/disable nesting',
        ],
        [
            'name' => 'ostypelxc',
            'type' => 'dropdown',
            'friendlyName' => 'OS Type',
            'description' => 'The OS type of the wanted VM',
            'options' => [
                [
                    'name' => 'debian',
                    'value' => 'debian'
                ],
                [
                    'name' => 'devuan',
                    'value' => 'devuan'
                ],
                [
                    'name' => 'ubuntu',
                    'value' => 'ubuntu'
                ],
                [
                    'name' => 'centos',
                    'value' => 'centos'
                ],
                [
                    'name' => 'fedora',
                    'value' => 'fedora'
                ],
                [
                    'name' => 'opensuse',
                    'value' => 'opensuse'
                ],
                [
                    'name' => 'archlinux',
                    'value' => 'archlinux'
                ],
                [
                    'name' => 'alpine',
                    'value' => 'alpine'
                ],
                [
                    'name' => 'gentoo',
                    'value' => 'gentoo'
                ],
                [
                    'name' => 'nixos',
                    'value' => 'nixos'
                ],
                [
                    'name' => 'unmanaged',
                    'value' => 'unmanaged'
                ]
            ]
        ],

        [
            'type' => 'title',
            'friendlyName' => 'QEMU',
            'description' => 'All QEMU options',
        ],
        [
            'name' => 'nonetwork',
            'type' => 'boolean',
            'friendlyName' => 'No Network',
            'description' => 'Enable/disable network',
        ],
        [
            'name' => 'bridge',
            'type' => 'dropdown',
            'friendlyName' => 'Bridge',
            'options' => $bridgeList
        ],
        [
            'name' => 'model',
            'type' => 'dropdown',
            'friendlyName' => 'Model',
            'options' => [
                [
                    'name' => 'VirtIO',
                    'value' => 'virtio'
                ],
                [
                    'name' => 'Intel E1000',
                    'value' => 'e1000'
                ],
                [
                    'name' => 'Realtek RTL8139',
                    'value' => 'rtl8139'
                ],
                [
                    'name' => 'VMWare VMXNET3',
                    'value' => 'vmxnet3'
                ]
            ]
        ],
        [
            'name' => 'vlantag',
            'type' => 'text',
            'friendlyName' => 'VLAN Tag',
            'description' => 'Optional VLAN tag',
        ],
        [
            'name' => 'firewall',
            'type' => 'boolean',
            'friendlyName' => 'Firewall',
            'description' => 'Enable/disable firewall',
        ],
        [
            'name' => 'os',
            'type' => 'dropdown',
            'friendlyName' => 'OS',
            'required' => true,
            'options' => [
                [
                    'name' => 'ISO',
                    'value' => 'iso'
                ],
                [
                    'name' => 'Pysical CD/DVD drive',
                    'value' => 'cdrom'
                ],
                [
                    'name' => 'None',
                    'value' => 'none'
                ]
            ]
        ],
        [
            'name' => 'iso',
            'type' => 'dropdown',
            'friendlyName' => 'ISO',
            'description' => 'The ISO name of the wanted VM',
            'options' => $isoList
        ],
        [
            'name' => 'cloudinit',
            'type' => 'boolean',
            'friendlyName' => 'Cloudinit',
            'description' => 'Enable/disable cloudinit',
        ],
        [
            'name' => 'storageType',
            'type' => 'dropdown',
            'friendlyName' => 'Bus/Device',
            'description' => 'The bus/device of the VM',
            'options' =>
            [
                [
                    'name' => 'IDE',
                    'value' => 'ide'
                ],
                [
                    'name' => 'SATA',
                    'value' => 'sata'
                ],
                [
                    'name' => 'SCSI',
                    'value' => 'scsi'
                ],
                [
                    'name' => 'VirtIO block',
                    'value' => 'virtio'
                ]
            ]
        ],
        [
            'name' => 'storageFormat',
            'type' => 'dropdown',
            'friendlyName' => 'Storage Format',
            'description' => 'The storage format of the VM',
            'options' => [
                [
                    'name' => 'Raw',
                    'value' => 'raw'
                ],
                [
                    'name' => 'Qcow2',
                    'value' => 'qcow2'
                ],
                [
                    'name' => 'VMDK',
                    'value' => 'vmdk'
                ],
            ]
        ],
        [
            'name' => 'cache',
            'type' => 'dropdown',
            'friendlyName' => 'Cache',
            'description' => 'The cache of the VM',
            'options' => [
                [
                    'name' => 'Default (no cache)',
                    'value' => 'default'
                ],
                [
                    'name' => 'Direct Sync',
                    'value' => 'directsync'
                ],
                [
                    'name' => 'Write Through',
                    'value' => 'writethrough'
                ],
                [
                    'name' => 'Write Back',
                    'value' => 'write back'
                ],
                [
                    'name' => 'Write Back (unsafe)',
                    'value' => 'unsafe'
                ],
                [
                    'name' => 'No Cache',
                    'value' => 'none'
                ],
            ]
        ],
        [
            'name' => 'ostype',
            'type' => 'dropdown',
            'friendlyName' => 'Guest OS type',
            'description' => 'The OS type of the VM',
            'options' => [
                [
                    'name' => 'other',
                    'value' => 'other'
                ],
                [
                    'name' => 'Windows XP',
                    'value' => 'wxp'
                ],
                [
                    'name' => 'Windows 2000',
                    'value' => 'w2k'
                ],
                [
                    'name' => 'Windows 2003',
                    'value' => 'w2k3'
                ],
                [
                    'name' => 'Windows 2008',
                    'value' => 'w2k8'
                ],
                [
                    'name' => 'Windows Vista',
                    'value' => 'wvista'
                ],
                [
                    'name' => 'Windows 7',
                    'value' => 'win7'
                ],
                [
                    'name' => 'Windows 8',
                    'value' => 'win8'
                ],
                [
                    'name' => 'Windows 10',
                    'value' => 'win10'
                ],
                [
                    'name' => 'Windows 11',
                    'value' => 'win11'
                ],
                [
                    'name' => 'Linux 2.4 Kernel',
                    'value' => 'l24'
                ],
                [
                    'name' => 'Linux 6.x - 2.6 Kernel',
                    'value' => 'l26'
                ],
                [
                    'name' => 'solaris',
                    'value' => 'solaris'
                ]
            ]
        ],
    ];
}

function Proxmox_getRequest($url)
{
    $response = Http::withHeaders([
        'Authorization' => 'PVEAPIToken=' . ExtensionHelper::getConfig('Proxmox', 'username') . '=' . ExtensionHelper::getConfig('Proxmox', 'password'),
        'Accept' => 'application/json',
        'Content-Type' => 'application/json'
    ])->withoutVerifying()->get(ExtensionHelper::getConfig('Proxmox', 'host') . ':' . ExtensionHelper::getConfig('Proxmox', 'port') . '/api2/json' . $url);

    return $response;
}

function Proxmox_postRequest($url, $data = [])
{
    $response = Http::withHeaders([
        'Authorization' => 'PVEAPIToken=' . ExtensionHelper::getConfig('Proxmox', 'username') . '=' . ExtensionHelper::getConfig('Proxmox', 'password'),
        'Accept' => 'application/json',
        'Content-Type' => 'application/json'
    ])->withoutVerifying()->post(ExtensionHelper::getConfig('Proxmox', 'host') . ':' . ExtensionHelper::getConfig('Proxmox', 'port') . '/api2/json' . $url, $data);

    return $response;
}

function Proxmox_test()
{
    $response = Proxmox_getRequest('/nodes');
    if (!$response->json()) throw new Exception('Unable to get nodes');
    return true;
}

function Proxmox_getUserConfig(Product $product)
{
    $currentConfig = $product->settings;
    if ($currentConfig->where('name', 'type')->first()->value == 'lxc') {
        return [
            [
                'name' => 'hostname',
                'type' => 'text',
                'friendlyName' => 'Hostname',
                'description' => 'The hostname of the VM',
            ],
            [
                'name' => 'password',
                'type' => 'password',
                'friendlyName' => 'Password',
                'description' => 'The password of the VM',
                'required' => true,
            ],
        ];
    }

    return [
        [
            'name' => 'hostname',
            'type' => 'text',
            'friendlyName' => 'Hostname',
            'description' => 'The hostname of the VM',
        ],
        [
            'name' => 'password',
            'type' => 'password',
            'friendlyName' => 'Password',
            'description' => 'The password of the VM',
            'required' => true,
        ],
    ];
}

function Proxmox_createServer($user, $parmas, $order, $product, $configurableOptions)
{
    $node = isset($configurableOptions['node']) ? $configurableOptions['node'] : $parmas['node'];
    $storage = isset($configurableOptions['storage']) ? $configurableOptions['storage'] : $parmas['storage'];
    $pool = isset($configurableOptions['pool']) ? $configurableOptions['pool'] : $parmas['pool'];
    $cores = isset($configurableOptions['cores']) ? $configurableOptions['cores'] : $parmas['cores'];
    $memory = isset($configurableOptions['memory']) ? $configurableOptions['memory'] : $parmas['memory'];
    $disk = isset($configurableOptions['disk']) ? $configurableOptions['disk'] : $parmas['disk'];

    $vmid = Proxmox_getRequest('/cluster/nextid')->json()['data'];

    // Assign it to the orderProduct for further use
    ExtensionHelper::setOrderProductConfig('vmid', $vmid, $order->id);
    $postData = [];

    $currentConfig = $product->product->settings;
    $postData = [];
    if ($currentConfig->where('name', 'type')->first()->value == 'lxc') {
        $postData = [
            'vmid' => $vmid,
            'node' => $node,
            'storage' => $storage,
            'cores' => $cores,
            'memory' => $memory,
            'onboot' => 1,
            'ostemplate' => $parmas['template'],
            'ostype' => $parmas['ostypelxc'],
            'description' => $parmas['config']['hostname'],
            'hostname' => $parmas['config']['hostname'],
            'password' => $parmas['config']['password'],
            'net0' => 'name=test' . ',bridge=' . $parmas['bridge'] . ',' . (isset($parmas['firewall']) ? 'firewall=1' : 'firewall=0'),
        ];
        isset($pool) ? $postData['pool'] = $pool : null;
        $response = Proxmox_postRequest('/nodes/' . $node . '/lxc', $postData);
    } else {
        $postData = [
            'vmid' => $vmid,
            'node' => $node,
            'storage' => $storage,
            'cores' => $cores,
            'memory' => $memory,
            'onboot' => 1,
            'sockets' => 1,
            'ostype' => $parmas['ostype'],
            'name' => $parmas['config']['hostname'],
            'description' => $parmas['config']['hostname'],
            $parmas['storageType'] . '0' => $parmas['storage'] . ':' . $disk . ',format=' . $parmas['storageFormat'],
            'net0' => $parmas['model'] . ',bridge=' . $parmas['bridge'] . ',' . (isset($parmas['firewall']) ? 'firewall=1' : 'firewall=0'),
        ];
        isset($pool) ? $postData['pool'] = $pool : null;
        isset($parmas['cloudinit']) ? $postData[$parmas['storageType'] . '0'] = $parmas['storage'] . ':cloudinit' . ',format=' . $parmas['storageFormat'] : null;
        if (isset($parmas['os']) && $parmas['os'] == 'iso') {
            $postData['ide2'] = $parmas['iso'] . ',media=cdrom';
        }
        $response = Proxmox_postRequest('/nodes/' . $node . '/qemu', $postData);
    }
    if (!$response->json()) throw new Exception('Unable to create server' . $response->body());
    return true;
}

function Proxmox_getCustomPages($user, $parmas, $order, $product, $configurableOptions)
{
    $vmType = $parmas['type'];
    $vmid = $parmas['config']['vmid'];
    $status = Proxmox_getRequest('/nodes/' . $parmas['node'] . '/' . $vmType . '/' . $vmid . '/status/current');
    if (!$status->json()) throw new Exception('Unable to get server status');
    $status = $status->json()['data']['status'];

    $stats = Proxmox_getRequest('/nodes/' . $parmas['node'] . '/' . $vmType . '/' . $vmid . '/rrddata?timeframe=hour');
    if (!$stats->json()) throw new Exception('Unable to get server stats');
    $stats = $stats->json()['data'];

    $vnc;
    if ($vmType == 'lxc') $vnc = Proxmox_postRequest('/nodes/' . $parmas['node'] . '/' . $vmType . '/' . $vmid . '/vncproxy', ['websocket' => 1]);
    else  $vnc = Proxmox_postRequest('/nodes/' . $parmas['node'] . '/' . $vmType . '/' . $vmid . '/vncproxy', ['websocket' => 1, 'generate-password' => 1]);
    if (!$vnc->json()) throw new Exception('Unable to get server vnc');
    $vnc = $vnc->json()['data'];

    $users = Proxmox_getRequest('/nodes/' . $parmas['node'] . '/' . $vmType . '/' . $vmid . '/agent/info');
    if (!$users->json()) throw new Exception('Unable to get server users');
    $users = $users->json()['data'];


    // Make url for iframe
    $websocket = ExtensionHelper::getConfig('Proxmox', 'host') . ':' . ExtensionHelper::getConfig('Proxmox', 'port') . '/?console=kvm&novnc=1&node=' . $parmas['node'] . '&resize=1&vmid=' . $vmid . '&path=api2/json/nodes/' . $parmas['node'] . '/' . $vmType . '/' . $vmid . '/vncwebsocket/port/' . $vnc['port'] . '/"vncticket"/' . $vnc['ticket'];


    return [
        'name' => 'Proxmox',
        'template' => 'proxmox::control',
        'data' => [
            'status' => $status,
            'node' => $parmas['node'],
            'vmid' => $vmid,
            'stats' => $stats,
            'vnc' => $vnc,
            'websocket' => $websocket,
            'users' => $users,
        ],
        'pages' => [
            [
                'template' => 'proxmox::stats',
                'name' => 'Statistics',
                'url' => 'stats',
            ],
            // [
            //     'template' => 'proxmox::vnc',
            //     'name' => 'VNC',
            //     'url' => 'vnc',
            // ],
            // [
            //     'template' => 'proxmox::settings',
            //     'name' => 'Settings',
            //     'url' => 'settings',
            // ]
        ]
    ];
}

function Proxmox_status(Request $request, OrderProduct $product)
{
    if (!ExtensionHelper::hasAccess($product,  $request->user())) throw new Exception('You do not have access to this server');
    $request->validate([
        'status' => ['required', 'string', 'in:stop,start,reboot,shutdown'],
    ]);
    $data = ExtensionHelper::getParameters($product);
    $params = $data->config;
    $vmid = $params['config']['vmid'];
    $postData = [
        'node' => $params['node'],
        'vmid' => $vmid,
    ];
    // Change status
    $status = Proxmox_postRequest('/nodes/' . $params['node'] . '/qemu/' . $vmid . '/status/' . $request->status,  $postData);
    if (!$status->json()) throw new Exception('Unable to ' . $request->status . ' server');

    // Return json response
    return response()->json([
        'status' => 'success',
        'message' => 'Server has been ' . $request->status . 'ed successfully'
    ]);
}