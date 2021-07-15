# Nextcloud filesystem API

High level guide to using the Nextcloud filesystem API

## Node API

The "Node API" is the primary api for apps to access the Nextcloud filesystem, each item in the filesystem is
represented as either a File or Folder node with each node providing access to the relevant filesystem information
and actions for the node.

### Getting access

Access to the filesystem is provided by the `IRootFolder` which can be injected into your class.
From the root folder you can either access a user's home folder or access a file or folder by its absolute path.

```php
use OCP\Files\IRootFolder;
use OCP\IUserSession;

class FileCreator {
	/** @var IUserSession */
	private $userSession;
	/** @var IRootFolder */
	private $rootFolder;
	
	public function __constructor(IUserSession $userSession, IRootFolder $rootFolder) {
		$this->userSession = $userSession;
		$this->rootFolder = $rootFolder;
	}
	
	/**
	 * Create a new file with specified content in the home folder of the current user
	 * returning the size of the resulting file. 
	 */
	public function createUserFile(string $path, string $content): int {
		$user = $this->userSession->getUser();
		if ($user !== null) {
			// the "user folder" corresponds to the root of the user visible files
			$userFolder = $this->rootFolder->getUserFolder($user->getUID());
			// paths passed to a folder method are relative to that folder
			$file = $userFolder->newFile($path, $content);
			return $file->getSize();
		} else {
			return 0;
		}
	}
}
```

For details on the specific methods provided by file and folder nodes see the method documentation from the `OCP\Files\File` and `OCP\Files\Folder` interfaces.

## Direct storage access

While it should be generally avoided in favor of the higher level apis,
sometimes an app needs to talk directly to the storage implementation of it's metadata cache.

You can get access to the underlying storage of a file or folder by calling `getStorage` on the node or first getting
the mountpoint by calling `getMountPoint` and getting the storage from there.

Once you have the storage instance you can use the storage api from `OCP\Files\Storage\IStorage`, note however that
all paths used in the storage api are internal to the storage, the `IMountPoint` returned from `getMountPoint` provides
methods for translating between absolute filesystem paths and internal storage paths.

If you need to query the cached metadata directory you can get the `OCP\Files\Cache\ICache` from the storage by calling `getCache`.

## Implementing a storage

The recommended way for implementing a storage backend is by sub-classing `OC\Files\Storage\Common` which provides
fallback implementations for various methods, reducing the amount of work required to implement the full storage api.
Note however that various of these fallback implementations are likely to be significantly less efficient than an
implementation of the method optimized for the abilities of the storage backend.

## Adding mounts to the filesystem

The recommended way of adding your own mounts to the filesystem from an app is implementing `OCP\Files\Config\IMountProvider`
and registering the provider using `OCP\Files\Config\IMountProviderCollection::registerProvider`.

Once registered, your provider will be called every time the filesystem is being setup for a user and your mount provider
can return a list of mounts to add for that user.
