<?php

$CONFIG = [
	'appstoreenabled' => false,
	'apps_paths' => [
		[
			'path'		=> OC::$SERVERROOT . '/apps',
			'url'		=> '/apps',
			'writable'	=> true,
		],
	],
];

if (is_dir(OC::$SERVERROOT.'/apps2')) {
	$CONFIG['apps_paths'][] = [
		'path' => OC::$SERVERROOT . '/apps2',
		'url' => '/apps2',
		'writable' => false,
	];
}

if (getenv('OBJECT_STORE') === 's3') {
	$CONFIG['objectstore'] = [
		'class' => 'OC\\Files\\ObjectStore\\S3',
		'arguments' => [
			'bucket' => 'nextcloud',
			'autocreate' => true,
			'key' => 'dummy',
			'secret' => 'dummy',
			'hostname' => getenv('DRONE') === 'true' ? 'fake-s3' : 'localhost',
			'port' => 4569,
			'use_ssl' => false,
			// required for some non amazon s3 implementations
			'use_path_style' => true
		]
	];
}
if (getenv('OBJECT_STORE') === 'swift') {
	$swiftHost = getenv('DRONE') === 'true' ? 'dockswift' : 'localhost';

	if (getenv('SWIFT-AUTH') === 'v2.0') {
		$CONFIG['objectstore'] = [
			'class' => 'OC\\Files\\ObjectStore\\Swift',
			'arguments' => [
				'autocreate' => true,
				'username' => 'swift',
				'tenantName' => 'service',
				'password' => 'swift',
				'serviceName' => 'swift',
				'region' => 'regionOne',
				'url' => "http://$swiftHost:5000/v2.0",
				'bucket' => 'nextcloud'
			]
		];
	} else {
		$CONFIG['objectstore'] = [
			'class' => 'OC\\Files\\ObjectStore\\Swift',
			'arguments' => [
				'autocreate' => true,
				'user' => [
					'name' => 'swift',
					'password' => 'swift',
					'domain' => [
						'name' => 'default',
					]
				],
				'scope' => [
					'project' => [
						'name' => 'service',
						'domain' => [
							'name' => 'default',
						],
					],
				],
				'tenantName' => 'service',
				'serviceName' => 'swift',
				'region' => 'regionOne',
				'url' => "http://$swiftHost:5000/v3",
				'bucket' => 'nextcloud'
			]
		];
	}
}
if (getenv('OBJECT_STORE') === 'azure') {
	$CONFIG['objectstore'] = [
		'class' => 'OC\\Files\\ObjectStore\\Azure',
		'arguments' => [
			'container' => 'test',
			'account_name' => 'devstoreaccount1',
			'account_key' => 'Eby8vdM02xNOcqFlqUwJPLlmEtlCDXJ1OUzFT50uSRZ6IFsuFq2UVErCz4I6tq/K1SZFPTOtr/KBHBeksoGMGw==',
			'endpoint' => 'http://' . (getenv('DRONE') === 'true' ? 'azurite' : 'localhost') . ':10000/devstoreaccount1',
			'autocreate' => true
		]
	];
}
