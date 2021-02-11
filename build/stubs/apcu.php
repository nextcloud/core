<?php

/**
 * Stubs for APC (apcu_bc nowadays) extension
 */

/**
 * @link https://php.net/manual/en/apc.constants.php
 */
define('APC_BIN_VERIFY_MD5', 1);
/**
 * @link https://php.net/manual/en/apc.constants.php
 */
define('APC_BIN_VERIFY_CRC32', 2);

/**
 * Retrieves cached information and meta-data from APC's data store
 * @link https://php.net/manual/en/function.apc-cache-info.php
 * @param string $type If cache_type is "user", information about the user cache will be returned.
 * If cache_type is "filehits", information about which files have been served from the bytecode
 * cache for the current request will be returned. This feature must be enabled at compile time
 * using --enable-filehits. If an invalid or no cache_type is specified, information about the
 * system cache (cached files) will be returned.
 * @param bool $limited If limited is TRUE, the return value will exclude the individual list
 * of cache entries. This is useful when trying to optimize calls for statistics gathering.
 * @return array|bool Array of cached data (and meta-data) or FALSE on failure.
 */
function apc_cache_info($type = '', $limited = false){}

/**
 * Clears the APC cache
 * @link https://php.net/manual/en/function.apc-clear-cache.php
 * @param string $cache_type If cache_type is "user", the user cache will be cleared;
 * otherwise, the system cache (cached files) will be cleared.
 * @return bool Returns TRUE on success or FALSE on failure.
 */
function apc_clear_cache($cache_type = ''){}

/**
 * Retrieves APC's Shared Memory Allocation information
 * @link https://php.net/manual/en/function.apc-sma-info.php
 * @param bool $limited When set to FALSE (default) apc_sma_info() will
 * return a detailed information about each segment.
 * @return array|bool Array of Shared Memory Allocation data; FALSE on failure.
 */
function apc_sma_info($limited = false){}

/**
 * Cache a variable in the data store
 * @link https://php.net/manual/en/function.apc-store.php
 * @param string|array $key String: Store the variable using this name. Keys are cache-unique,
 * so storing a second value with the same key will overwrite the original value.
 * Array: Names in key, variables in value.
 * @param mixed $var [optional] The variable to store
 * @param int $ttl [optional]  Time To Live; store var in the cache for ttl seconds. After the ttl has passed,
 * the stored variable will be expunged from the cache (on the next request). If no ttl is supplied
 * (or if the ttl is 0), the value will persist until it is removed from the cache manually,
 * or otherwise fails to exist in the cache (clear, restart, etc.).
 * @return bool|array Returns TRUE on success or FALSE on failure | array with error keys.
 */
function apc_store($key, $var, $ttl = 0){}

/**
 * Fetch a stored variable from the cache
 * @link https://php.net/manual/en/function.apc-fetch.php
 * @param string|string[] $key The key used to store the value (with apc_store()).
 * If an array is passed then each element is fetched and returned.
 * @param bool $success Set to TRUE in success and FALSE in failure.
 * @return mixed The stored variable or array of variables on success; FALSE on failure.
 */
function apc_fetch($key, &$success = null){}

/**
 * Removes a stored variable from the cache
 * @link https://php.net/manual/en/function.apc-delete.php
 * @param string|string[]|APCIterator $key The key used to store the value (with apc_store()).
 * @return bool|string[] Returns TRUE on success or FALSE on failure. For array of keys returns list of failed keys.
 */
function apc_delete($key){}

/**
 * Defines a set of constants for retrieval and mass-definition
 *
 * define() is notoriously slow. Since the main benefit of APC is to increase
 * the performance of scripts/applications, this mechanism is provided to streamline
 * the process of mass constant definition. However, this function does not perform
 * as well as anticipated. For a better-performing solution, try the hidef extension from PECL.
 *
 * @link https://php.net/manual/en/function.apc-define-constants.php
 * @param string $key The key serves as the name of the constant set being stored.
 * This key is used to retrieve the stored constants in apc_load_constants().
 * @param array $constants An associative array of constant_name => value pairs.
 * The constant_name must follow the normal constant naming rules. Value must evaluate to a scalar value.
 * @param bool $case_sensitive The default behaviour for constants is to be declared case-sensitive;
 * i.e. CONSTANT and Constant represent different values. If this parameter evaluates to FALSE
 * the constants will be declared as case-insensitive symbols.
 * @return bool Returns TRUE on success or FALSE on failure.
 */
function apc_define_constants($key, array $constants, $case_sensitive = true){}

/**
 * Caches a variable in the data store, only if it's not already stored
 * @link https://php.net/manual/en/function.apc-add.php
 * @param string $key Store the variable using this name. Keys are cache-unique,
 * so attempting to use apc_add() to store data with a key that already exists will not
 * overwrite the existing data, and will instead return FALSE. (This is the only difference
 * between apc_add() and apc_store().)
 * @param mixed $var The variable to store
 * @param int $ttl Time To Live; store var in the cache for ttl seconds. After the ttl has passed,
 * the stored variable will be expunged from the cache (on the next request). If no ttl is supplied
 * (or if the ttl is 0), the value will persist until it is removed from the cache manually,
 * or otherwise fails to exist in the cache (clear, restart, etc.).
 * @return bool
 */
function apc_add($key, $var, $ttl = 0){}

/**
 * Stores a file in the bytecode cache, bypassing all filters
 * @link https://php.net/manual/en/function.apc-compile-file.php
 * @param string|string[] $filename Full or relative path to a PHP file that will be
 * compiled and stored in the bytecode cache.
 * @param bool $atomic
 * @return bool Returns TRUE on success or FALSE on failure.
 */
function apc_compile_file($filename, $atomic = true){}

/**
 * Loads a set of constants from the cache
 * @link https://php.net/manual/en/function.apc-load-constants.php
 * @param string $key The name of the constant set (that was stored
 * with apc_define_constants()) to be retrieved.
 * @param bool $case_sensitive The default behaviour for constants is to be declared case-sensitive;
 * i.e. CONSTANT and Constant represent different values. If this parameter evaluates to FALSE
 * the constants will be declared as case-insensitive symbols.
 * @return bool Returns TRUE on success or FALSE on failure.
 */
function apc_load_constants($key, $case_sensitive = true){}

/**
 * Checks if APC key exists
 * @link https://php.net/manual/en/function.apc-exists.php
 * @param bool|string[] $keys A string, or an array of strings, that contain keys.
 * @return bool|string[] Returns TRUE if the key exists, otherwise FALSE
 * Or if an array was passed to keys, then an array is returned that
 * contains all existing keys, or an empty array if none exist.
 */
function apc_exists($keys){}

/**
 * Deletes the given files from the opcode cache
 *
 * Accepts a string, array of strings, or APCIterator object.
 * Returns True/False, or for an Array an Array of failed files.
 *
 * @link https://php.net/manual/en/function.apc-delete-file.php
 * @param string|string[]|APCIterator $keys
 * @return bool|string[]
 */
function apc_delete_file($keys){}

/**
 * Increase a stored number
 * @link https://php.net/manual/en/function.apc-inc.php
 * @param string $key The key of the value being increased.
 * @param int $step The step, or value to increase.
 * @param bool $success Optionally pass the success or fail boolean value to this referenced variable.
 * @return int|bool Returns the current value of key's value on success, or FALSE on failure.
 */
function apc_inc($key, $step = 1, &$success = null){}

/**
 * Decrease a stored number
 * @link https://php.net/manual/en/function.apc-dec.php
 * @param string $key The key of the value being decreased.
 * @param int $step The step, or value to decrease.
 * @param bool $success Optionally pass the success or fail boolean value to this referenced variable.
 * @return int|bool Returns the current value of key's value on success, or FALSE on failure.
 */
function apc_dec($key, $step = 1, &$success = null){}

/**
 * @link https://php.net/manual/en/function.apc-cas.php
 * @param string $key
 * @param int $old
 * @param int $new
 * @return bool
 */
function apc_cas($key, $old, $new){}

/**
 * Returns a binary dump of the given files and user variables from the APC cache
 *
 * A NULL for files or user_vars signals a dump of every entry, while array() will dump nothing.
 *
 * @link https://php.net/manual/en/function.apc-bin-dump.php
 * @param string[]|null $files The files. Passing in NULL signals a dump of every entry, while passing in array() will dump nothing.
 * @param string[]|null $user_vars The user vars. Passing in NULL signals a dump of every entry, while passing in array() will dump nothing.
 * @return string|bool|null Returns a binary dump of the given files and user variables from the APC cache, FALSE if APC is not enabled, or NULL if an unknown error is encountered.
 */
function apc_bin_dump($files = null, $user_vars = null){}

/**
 * Output a binary dump of the given files and user variables from the APC cache to the named file
 * @link https://php.net/manual/en/function.apc-bin-dumpfile.php
 * @param string[]|null $files The file names being dumped.
 * @param string[]|null $user_vars The user variables being dumped.
 * @param string $filename The filename where the dump is being saved.
 * @param int $flags Flags passed to the filename stream. See the file_put_contents() documentation for details.
 * @param resource $context The context passed to the filename stream. See the file_put_contents() documentation for details.
 * @return int|bool The number of bytes written to the file, otherwise FALSE if APC
 * is not enabled, filename is an invalid file name, filename can't be opened,
 * the file dump can't be completed (e.g., the hard drive is out of disk space),
 * or an unknown error was encountered.
 */
function apc_bin_dumpfile($files, $user_vars, $filename, $flags = 0, $context = null){}

/**
 * Load the given binary dump into the APC file/user cache
 * @link https://php.net/manual/en/function.apc-bin-load.php
 * @param string $data The binary dump being loaded, likely from apc_bin_dump().
 * @param int $flags Either APC_BIN_VERIFY_CRC32, APC_BIN_VERIFY_MD5, or both.
 * @return bool Returns TRUE if the binary dump data was loaded with success, otherwise FALSE is returned.
 * FALSE is returned if APC is not enabled, or if the data is not a valid APC binary dump (e.g., unexpected size).
 */
function apc_bin_load($data, $flags = 0){}

/**
 * Load the given binary dump from the named file into the APC file/user cache
 * @link https://php.net/manual/en/function.apc-bin-loadfile.php
 * @param string $filename The file name containing the dump, likely from apc_bin_dumpfile().
 * @param resource $context The files context.
 * @param int $flags Either APC_BIN_VERIFY_CRC32, APC_BIN_VERIFY_MD5, or both.
 * @return bool Returns TRUE on success, otherwise FALSE Reasons it may return FALSE include APC
 * is not enabled, filename is an invalid file name or empty, filename can't be opened,
 * the file dump can't be completed, or if the data is not a valid APC binary dump (e.g., unexpected size).
 */
function apc_bin_loadfile($filename, $context = null, $flags = 0){}

/**
 * The APCIterator class
 *
 * The APCIterator class makes it easier to iterate over large APC caches.
 * This is helpful as it allows iterating over large caches in steps, while grabbing a defined number
 * of entries per lock instance, so it frees the cache locks for other activities rather than hold up
 * the entire cache to grab 100 (the default) entries. Also, using regular expression matching is more
 * efficient as it's been moved to the C level.
 *
 * @link https://php.net/manual/en/class.apciterator.php
 */
class APCIterator implements Iterator
{
    /**
     * Constructs an APCIterator iterator object
     * @link https://php.net/manual/en/apciterator.construct.php
     * @param string $cache The cache type, which will be 'user' or 'file'.
     * @param string|string[]|null $search A PCRE regular expression that matches against APC key names,
     * either as a string for a single regular expression, or as an array of regular expressions.
     * Or, optionally pass in NULL to skip the search.
     * @param int $format The desired format, as configured with one ore more of the APC_ITER_* constants.
     * @param int $chunk_size The chunk size. Must be a value greater than 0. The default value is 100.
     * @param int $list The type to list. Either pass in APC_LIST_ACTIVE  or APC_LIST_INACTIVE.
     */
    public function __construct($cache, $search = null, $format = APC_ITER_ALL, $chunk_size = 100, $list = APC_LIST_ACTIVE){}

    /**
     * Rewinds back the iterator to the first element
     * @link https://php.net/manual/en/apciterator.rewind.php
     */
    public function rewind(){}

    /**
     * Checks if the current iterator position is valid
     * @link https://php.net/manual/en/apciterator.valid.php
     * @return bool Returns TRUE if the current iterator position is valid, otherwise FALSE.
     */
    public function valid(){}

    /**
     * Gets the current item from the APCIterator stack
     * @link https://php.net/manual/en/apciterator.current.php
     * @return mixed Returns the current item on success, or FALSE if no more items or exist, or on failure.
     */
    public function current(){}

    /**
     * Gets the current iterator key
     * @link https://php.net/manual/en/apciterator.key.php
     * @return string|int|bool Returns the key on success, or FALSE upon failure.
     */
    public function key(){}

    /**
     * Moves the iterator pointer to the next element
     * @link https://php.net/manual/en/apciterator.next.php
     * @return bool Returns TRUE on success or FALSE on failure.
     */
    public function next(){}

    /**
     * Gets the total number of cache hits
     * @link https://php.net/manual/en/apciterator.gettotalhits.php
     * @return int|bool The number of hits on success, or FALSE on failure.
     */
    public function getTotalHits(){}

    /**
     * Gets the total cache size
     * @link https://php.net/manual/en/apciterator.gettotalsize.php
     * @return int|bool The total cache size.
     */
    public function getTotalSize(){}

    /**
     * Get the total count
     * @link https://php.net/manual/en/apciterator.gettotalcount.php
     * @return int|bool The total count.
     */
    public function getTotalCount(){}
}

/**
 * Stubs for APCu 5.0.0
 */

/**
 * @link https://php.net/manual/en/apcu.constants.php
 */
define('APC_LIST_ACTIVE', 1);
/**
 * @link https://php.net/manual/en/apcu.constants.php
 */
define('APC_LIST_DELETED', 2);
/**
 * @link https://php.net/manual/en/apcu.constants.php
 */
define('APC_ITER_TYPE', 1);
/**
 * @link https://php.net/manual/en/apcu.constants.php
 */
define('APC_ITER_KEY', 2);
/**
 * @link https://php.net/manual/en/apcu.constants.php
 */
define('APC_ITER_FILENAME', 4);
/**
 * @link https://php.net/manual/en/apcu.constants.php
 */
define('APC_ITER_DEVICE', 8);
/**
 * @link https://php.net/manual/en/apcu.constants.php
 */
define('APC_ITER_INODE', 16);
/**
 * @link https://php.net/manual/en/apcu.constants.php
 */
define('APC_ITER_VALUE', 32);
/**
 * @link https://php.net/manual/en/apcu.constants.php
 */
define('APC_ITER_MD5', 64);
/**
 * @link https://php.net/manual/en/apcu.constants.php
 */
define('APC_ITER_NUM_HITS', 128);
/**
 * @link https://php.net/manual/en/apcu.constants.php
 */
define('APC_ITER_MTIME', 256);
/**
 * @link https://php.net/manual/en/apcu.constants.php
 */
define('APC_ITER_CTIME', 512);
/**
 * @link https://php.net/manual/en/apcu.constants.php
 */
define('APC_ITER_DTIME', 1024);
/**
 * @link https://php.net/manual/en/apcu.constants.php
 */
define('APC_ITER_ATIME', 2048);
/**
 * @link https://php.net/manual/en/apcu.constants.php
 */
define('APC_ITER_REFCOUNT', 4096);
/**
 * @link https://php.net/manual/en/apcu.constants.php
 */
define('APC_ITER_MEM_SIZE', 8192);
/**
 * @link https://php.net/manual/en/apcu.constants.php
 */
define('APC_ITER_TTL', 16384);
/**
 * @link https://php.net/manual/en/apcu.constants.php
 */
define('APC_ITER_NONE', 0);
/**
 * @link https://php.net/manual/en/apcu.constants.php
 */
define('APC_ITER_ALL', -1);


/**
 * Clears the APCu cache
 * @link https://php.net/manual/en/function.apcu-clear-cache.php
 *
 * @return bool Returns TRUE always.
 */
function apcu_clear_cache(){}

/**
 * Retrieves APCu Shared Memory Allocation information
 * @link https://php.net/manual/en/function.apcu-sma-info.php
 * @param bool $limited When set to FALSE (default) apcu_sma_info() will
 * return a detailed information about each segment.
 *
 * @return array|false Array of Shared Memory Allocation data; FALSE on failure.
 */
function apcu_sma_info($limited = false){}

/**
 * Cache a variable in the data store
 * @link https://php.net/manual/en/function.apcu-store.php
 * @param string|array $key String: Store the variable using this name. Keys are cache-unique,
 * so storing a second value with the same key will overwrite the original value.
 * Array: Names in key, variables in value.
 * @param mixed $var [optional] The variable to store
 * @param int $ttl [optional]  Time To Live; store var in the cache for ttl seconds. After the ttl has passed,
 * the stored variable will be expunged from the cache (on the next request). If no ttl is supplied
 * (or if the ttl is 0), the value will persist until it is removed from the cache manually,
 * or otherwise fails to exist in the cache (clear, restart, etc.).
 * @return bool|array Returns TRUE on success or FALSE on failure | array with error keys.
 */
function apcu_store($key, $var, $ttl = 0){}

/**
 * Fetch a stored variable from the cache
 * @link https://php.net/manual/en/function.apcu-fetch.php
 * @param string|string[] $key The key used to store the value (with apcu_store()).
 * If an array is passed then each element is fetched and returned.
 * @param bool $success Set to TRUE in success and FALSE in failure.
 * @return mixed The stored variable or array of variables on success; FALSE on failure.
 */
function apcu_fetch($key, &$success = null){}

/**
 * Removes a stored variable from the cache
 * @link https://php.net/manual/en/function.apcu-delete.php
 * @param string|string[]|APCuIterator $key The key used to store the value (with apcu_store()).
 * @return bool|string[] Returns TRUE on success or FALSE on failure. For array of keys returns list of failed keys.
 */
function apcu_delete($key){}

/**
 * Caches a variable in the data store, only if it's not already stored
 * @link https://php.net/manual/en/function.apcu-add.php
 * @param string|array $key Store the variable using this name. Keys are cache-unique,
 * so attempting to use apcu_add() to store data with a key that already exists will not
 * overwrite the existing data, and will instead return FALSE. (This is the only difference
 * between apcu_add() and apcu_store().)
 * Array: Names in key, variables in value.
 * @param mixed $var The variable to store
 * @param int $ttl Time To Live; store var in the cache for ttl seconds. After the ttl has passed,
 * the stored variable will be expunged from the cache (on the next request). If no ttl is supplied
 * (or if the ttl is 0), the value will persist until it is removed from the cache manually,
 * or otherwise fails to exist in the cache (clear, restart, etc.).
 * @return bool|array Returns TRUE if something has effectively been added into the cache, FALSE otherwise.
 * Second syntax returns array with error keys.
 */
function apcu_add($key, $var, $ttl = 0){}

/**
 * Checks if APCu key exists
 * @link https://php.net/manual/en/function.apcu-exists.php
 * @param string|string[] $keys A string, or an array of strings, that contain keys.
 * @return bool|string[] Returns TRUE if the key exists, otherwise FALSE
 * Or if an array was passed to keys, then an array is returned that
 * contains all existing keys, or an empty array if none exist.
 */
function apcu_exists($keys){}

/**
 * Increase a stored number
 * @link https://php.net/manual/en/function.apcu-inc.php
 * @param string $key The key of the value being increased.
 * @param int $step The step, or value to increase.
 * @param int $ttl Time To Live; store var in the cache for ttl seconds. After the ttl has passed,
 * the stored variable will be expunged from the cache (on the next request). If no ttl is supplied
 * (or if the ttl is 0), the value will persist until it is removed from the cache manually,
 * or otherwise fails to exist in the cache (clear, restart, etc.).
 * @param bool $success Optionally pass the success or fail boolean value to this referenced variable.
 * @return int|false Returns the current value of key's value on success, or FALSE on failure.
 */
function apcu_inc($key, $step = 1, &$success = null, $ttl = 0){}

/**
 * Decrease a stored number
 * @link https://php.net/manual/en/function.apcu-dec.php
 * @param string $key The key of the value being decreased.
 * @param int $step The step, or value to decrease.
 * @param int $ttl Time To Live; store var in the cache for ttl seconds. After the ttl has passed,
 * the stored variable will be expunged from the cache (on the next request). If no ttl is supplied
 * (or if the ttl is 0), the value will persist until it is removed from the cache manually,
 * or otherwise fails to exist in the cache (clear, restart, etc.).
 * @param bool $success Optionally pass the success or fail boolean value to this referenced variable.
 * @return int|false Returns the current value of key's value on success, or FALSE on failure.
 */
function apcu_dec($key, $step = 1, &$success = null, $ttl = 0){}

/**
 * Updates an old value with a new value
 *
 * apcu_cas() updates an already existing integer value if the old parameter matches the currently stored value
 * with the value of the new parameter.
 *
 * @link https://php.net/manual/en/function.apcu-cas.php
 * @param string $key The key of the value being updated.
 * @param int $old The old value (the value currently stored).
 * @param int $new The new value to update to.
 * @return bool Returns TRUE on success or FALSE on failure.
 */
function apcu_cas($key, $old, $new){}

/**
 * Atomically fetch or generate a cache entry
 *
 * <p>Atomically attempts to find key in the cache, if it cannot be found generator is called,
 * passing key as the only argument. The return value of the call is then cached with the optionally
 * specified ttl, and returned.
 * </p>
 *
 * <p>Note: When control enters <i>apcu_entry()</i> the lock for the cache is acquired exclusively, it is released when
 * control leaves apcu_entry(): In effect, this turns the body of generator into a critical section,
 * disallowing two processes from executing the same code paths concurrently.
 * In addition, it prohibits the concurrent execution of any other APCu functions,
 * since they will acquire the same lock.
 * </p>
 *
 * @link https://php.net/manual/en/function.apcu-entry.php
 *
 * @param string $key Identity of cache entry
 * @param callable $generator A callable that accepts key as the only argument and returns the value to cache.
 * <p>Warning
 * The only APCu function that can be called safely by generator is apcu_entry().</p>
 * @param int $ttl [optional] Time To Live; store var in the cache for ttl seconds.
 * After the ttl has passed, the stored variable will be expunged from the cache (on the next request).
 * If no ttl is supplied (or if the ttl is 0), the value will persist until it is removed from the cache manually,
 * or otherwise fails to exist in the cache (clear, restart, etc.).
 * @return mixed Returns the cached value
 * @since APCu 5.1.0
 */
function apcu_entry($key, callable $generator, $ttl = 0){}

/**
 * Retrieves cached information from APCu's data store
 *
 * @link https://php.net/manual/en/function.apcu-cache-info.php
 *
 * @param bool $limited If limited is TRUE, the return value will exclude the individual list of cache entries.
 * This is useful when trying to optimize calls for statistics gathering.
 * @return array|false Array of cached data (and meta-data) or FALSE on failure
 */
function apcu_cache_info($limited = false){}

/**
 * Whether APCu is usable in the current environment
 *
 * @link https://www.php.net/manual/en/function.apcu-enabled.php
 *
 * @return bool
 */
function apcu_enabled(){}

/**
 * @param string $key
 */
function apcu_key_info($key){}

/**
 * The APCuIterator class
 *
 * The APCuIterator class makes it easier to iterate over large APCu caches.
 * This is helpful as it allows iterating over large caches in steps, while grabbing a defined number
 * of entries per lock instance, so it frees the cache locks for other activities rather than hold up
 * the entire cache to grab 100 (the default) entries. Also, using regular expression matching is more
 * efficient as it's been moved to the C level.
 *
 * @link https://php.net/manual/en/class.apcuiterator.php
 * @since APCu 5.0.0
 */
class APCuIterator implements Iterator
{
	/**
	 * Constructs an APCuIterator iterator object
	 * @link https://php.net/manual/en/apcuiterator.construct.php
	 * @param string|string[]|null $search A PCRE regular expression that matches against APCu key names,
	 * either as a string for a single regular expression, or as an array of regular expressions.
	 * Or, optionally pass in NULL to skip the search.
	 * @param int $format The desired format, as configured with one ore more of the APC_ITER_* constants.
	 * @param int $chunk_size The chunk size. Must be a value greater than 0. The default value is 100.
	 * @param int $list The type to list. Either pass in APC_LIST_ACTIVE  or APC_LIST_DELETED.
	 */
	public function __construct($search = null, $format = APC_ITER_ALL, $chunk_size = 100, $list = APC_LIST_ACTIVE){}

	/**
	 * Rewinds back the iterator to the first element
	 * @link https://php.net/manual/en/apcuiterator.rewind.php
	 */
	public function rewind(){}

	/**
	 * Checks if the current iterator position is valid
	 * @link https://php.net/manual/en/apcuiterator.valid.php
	 * @return bool Returns TRUE if the current iterator position is valid, otherwise FALSE.
	 */
	public function valid(){}

	/**
	 * Gets the current item from the APCuIterator stack
	 * @link https://php.net/manual/en/apcuiterator.current.php
	 * @return mixed Returns the current item on success, or FALSE if no more items or exist, or on failure.
	 */
	public function current(){}

	/**
	 * Gets the current iterator key
	 * @link https://php.net/manual/en/apcuiterator.key.php
	 * @return string|int|false Returns the key on success, or FALSE upon failure.
	 */
	public function key(){}

	/**
	 * Moves the iterator pointer to the next element
	 * @link https://php.net/manual/en/apcuiterator.next.php
	 * @return bool Returns TRUE on success or FALSE on failure.
	 */
	public function next(){}

	/**
	 * Gets the total number of cache hits
	 * @link https://php.net/manual/en/apcuiterator.gettotalhits.php
	 * @return int|false The number of hits on success, or FALSE on failure.
	 */
	public function getTotalHits(){}

	/**
	 * Gets the total cache size
	 * @link https://php.net/manual/en/apcuiterator.gettotalsize.php
	 * @return int|false The total cache size.
	 */
	public function getTotalSize(){}

	/**
	 * Get the total count
	 * @link https://php.net/manual/en/apcuiterator.gettotalcount.php
	 * @return int|false The total count.
	 */
	public function getTotalCount(){}
}
