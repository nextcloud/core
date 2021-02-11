<?php

$cloudEnvironmentId = getenv('CLOUDENV_ENVIRONMENT_ID');

$CONFIG = [
    'mail_from_address' => 'no-reply',
    'mail_smtpmode' => 'smtp',
    'mail_sendmailmode' => 'smtp',
    'mail_domain' => 'example.com',
    'mail_smtphost' => 'localhost',
    'mail_smtpport' => '1025',
    'memcache.local' => '\OC\Memcache\APCu',
];

if($cloudEnvironmentId !== true) {
    $CONFIG['overwritehost'] = $cloudEnvironmentId . '-80.apps.codespaces.githubusercontent.com';
}
