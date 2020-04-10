SMB
===

[![Code Coverage](https://scrutinizer-ci.com/g/icewind1991/SMB/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/icewind1991/SMB/?branch=master)
[![Build Status](https://travis-ci.org/icewind1991/SMB.svg?branch=master)](https://travis-ci.org/icewind1991/SMB)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/icewind1991/SMB/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/icewind1991/SMB/?branch=master)

PHP wrapper for `smbclient` and [`libsmbclient-php`](https://github.com/eduardok/libsmbclient-php)

- Reuses a single `smbclient` instance for multiple requests
- Doesn't leak the password to the process list
- Simple 1-on-1 mapping of SMB commands
- A stream-based api to remove the need for temporary files
- Support for using libsmbclient directly trough [`libsmbclient-php`](https://github.com/eduardok/libsmbclient-php)

Examples
----

### Connect to a share ###

```php
<?php
use Icewind\SMB\ServerFactory;
use Icewind\SMB\BasicAuth;

require('vendor/autoload.php');

$serverFactory = new ServerFactory();
$auth = new BasicAuth('user', 'workgroup', 'password');
$server = $serverFactory->createServer('localhost', $auth);

$share = $server->getShare('test');
```

The server factory will automatically pick between the `smbclient` and `libsmbclient-php`
based backend depending on what is available.

### Using anonymous authentication ### 

```php
$serverFactory = new ServerFactory();
$auth = new AnonymousAuth();
$server = $serverFactory->createServer('localhost', $auth);
```

### Using kerberos authentication ###

```php
$serverFactory = new ServerFactory();
$auth = new KerberosAuth();
$server = $serverFactory->createServer('localhost', $auth);
```

Note that this requires a valid kerberos ticket to already be available for php

### Upload a file ###

```php
$share->put($fileToUpload, 'example.txt');
```

### Download a file ###

```php
$share->get('example.txt', $target);
```

### List shares on the remote server ###

```php
$shares = $server->listShares();

foreach ($shares as $share) {
	echo $share->getName() . "\n";
}
```

### List the content of a folder ###

```php
$content = $share->dir('test');

foreach ($content as $info) {
	echo $info->getName() . "\n";
	echo "\tsize :" . $info->getSize() . "\n";
}
```

### Using read streams

```php
$fh = $share->read('test.txt');
echo fread($fh, 4086);
fclose($fh);
```

### Using write streams

```php
$fh = $share->write('test.txt');
fwrite($fh, 'bar');
fclose($fh);
```

**Note**: write() will truncate your file to 0bytes. You may open a writeable stream with append() which will point
the cursor to the end of the file or create it if it does not exists yet. (append() is only compatible with libsmbclient-php)
```php
$fh = $share->append('test.txt');
fwrite($fh, 'bar');
fclose($fh);
```


### Using notify

```php
$share->notify('')->listen(function (\Icewind\SMB\Change $change) {
	echo $change->getCode() . ': ' . $change->getPath() . "\n";
});
```

### Changing network timeouts

```php
$options = new Options();
$options->setTimeout(5);
$serverFactory = new ServerFactory($options);
```

### Customizing system integration

The `smbclient` backend needs to get various information about the system it's running on to function
such as the paths of various binaries or the system timezone.
While the default logic for getting this information should work on most systems, it possible to customize this behaviour.

In order to customize the integration you provide a custom implementation of `ITimezoneProvider` and/or `ISystem` and pass them as arguments to the `ServerFactory`. 

## Testing SMB

Use the following steps to check if the library can connect to your SMB share.

1. Clone this repository or download the source as [zip](https://github.com/icewind1991/SMB/archive/master.zip)
2. Make sure [composer](https://getcomposer.org/) is installed
3. Run `composer install` in the root of the repository
4. Edit `example.php` with the relevant settings for your share.
5. Run `php example.php`

If everything works correctly then the contents of the share should be outputted.
