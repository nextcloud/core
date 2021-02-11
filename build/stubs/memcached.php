<?php

// Start of memcached v.3.1.5

/**
 * Represents a connection to a set of memcached servers.
 * @link https://php.net/manual/en/class.memcached.php
 */
class Memcached  {

	/**
	 * <p>Enables or disables payload compression. When enabled,
	 * item values longer than a certain threshold (currently 100 bytes) will be
	 * compressed during storage and decompressed during retrieval
	 * transparently.</p>
	 * <p>Type: boolean, default: <b>TRUE</b>.</p>
	 * @link https://php.net/manual/en/memcached.constants.php
	 */
	const OPT_COMPRESSION = -1001;
	const OPT_COMPRESSION_TYPE = -1004;

	/**
	 * <p>This can be used to create a "domain" for your item keys. The value
	 * specified here will be prefixed to each of the keys. It cannot be
	 * longer than 128 characters and will reduce the
	 * maximum available key size. The prefix is applied only to the item keys,
	 * not to the server keys.</p>
	 * <p>Type: string, default: "".</p>
	 * @link https://php.net/manual/en/memcached.constants.php
	 */
	const OPT_PREFIX_KEY = -1002;

	/**
	 * <p>
	 * Specifies the serializer to use for serializing non-scalar values.
	 * The valid serializers are <b>Memcached::SERIALIZER_PHP</b>
	 * or <b>Memcached::SERIALIZER_IGBINARY</b>. The latter is
	 * supported only when memcached is configured with
	 * --enable-memcached-igbinary option and the
	 * igbinary extension is loaded.
	 * </p>
	 * <p>Type: integer, default: <b>Memcached::SERIALIZER_PHP</b>.</p>
	 * @link https://php.net/manual/en/memcached.constants.php
	 */
	const OPT_SERIALIZER = -1003;

	/**
	 * <p>Indicates whether igbinary serializer support is available.</p>
	 * <p>Type: boolean.</p>
	 * @link https://php.net/manual/en/memcached.constants.php
	 */
	const HAVE_IGBINARY = 0;

	/**
	 * <p>Indicates whether JSON serializer support is available.</p>
	 * <p>Type: boolean.</p>
	 * @link https://php.net/manual/en/memcached.constants.php
	 */
	const HAVE_JSON = 0;

	/**
	 * <p>Indicates whether msgpack serializer support is available.</p>
	 * <p>Type: boolean.</p>
	 * Available as of Memcached 3.0.0.
	 * @since 3.0.0
	 * @link https://php.net/manual/en/memcached.constants.php
	 */
	const HAVE_MSGPACK = 0;

	/**
	 * <p>Indicate whether set_encoding_key is available</p>
	 * <p>Type: boolean.</p>
	 * @link https://github.com/php-memcached-dev/php-memcached/blob/v3.1.5/memcached-api.php, https://github.com/php-memcached-dev/php-memcached/blob/v3.1.5/php_memcached.c#L4387
	 */
	const HAVE_ENCODING = 0;

	/**
	 * Feature support
	 */
	const HAVE_SESSION = 1;
	const HAVE_SASL = 0;

	/**
	 * <p>Specifies the hashing algorithm used for the item keys. The valid
	 * values are supplied via <b>Memcached::HASH_*</b> constants.
	 * Each hash algorithm has its advantages and its disadvantages. Go with the
	 * default if you don't know or don't care.</p>
	 * <p>Type: integer, default: <b>Memcached::HASH_DEFAULT</b></p>
	 * @link https://php.net/manual/en/memcached.constants.php
	 */
	const OPT_HASH = 2;

	/**
	 * <p>The default (Jenkins one-at-a-time) item key hashing algorithm.</p>
	 * @link https://php.net/manual/en/memcached.constants.php
	 */
	const HASH_DEFAULT = 0;

	/**
	 * <p>MD5 item key hashing algorithm.</p>
	 * @link https://php.net/manual/en/memcached.constants.php
	 */
	const HASH_MD5 = 1;

	/**
	 * <p>CRC item key hashing algorithm.</p>
	 * @link https://php.net/manual/en/memcached.constants.php
	 */
	const HASH_CRC = 2;

	/**
	 * <p>FNV1_64 item key hashing algorithm.</p>
	 * @link https://php.net/manual/en/memcached.constants.php
	 */
	const HASH_FNV1_64 = 3;

	/**
	 * <p>FNV1_64A item key hashing algorithm.</p>
	 * @link https://php.net/manual/en/memcached.constants.php
	 */
	const HASH_FNV1A_64 = 4;

	/**
	 * <p>FNV1_32 item key hashing algorithm.</p>
	 * @link https://php.net/manual/en/memcached.constants.php
	 */
	const HASH_FNV1_32 = 5;

	/**
	 * <p>FNV1_32A item key hashing algorithm.</p>
	 * @link https://php.net/manual/en/memcached.constants.php
	 */
	const HASH_FNV1A_32 = 6;

	/**
	 * <p>Hsieh item key hashing algorithm.</p>
	 * @link https://php.net/manual/en/memcached.constants.php
	 */
	const HASH_HSIEH = 7;

	/**
	 * <p>Murmur item key hashing algorithm.</p>
	 * @link https://php.net/manual/en/memcached.constants.php
	 */
	const HASH_MURMUR = 8;

	/**
	 * <p>Specifies the method of distributing item keys to the servers.
	 * Currently supported methods are modulo and consistent hashing. Consistent
	 * hashing delivers better distribution and allows servers to be added to
	 * the cluster with minimal cache losses.</p>
	 * <p>Type: integer, default: <b>Memcached::DISTRIBUTION_MODULA.</b></p>
	 * @link https://php.net/manual/en/memcached.constants.php
	 */
	const OPT_DISTRIBUTION = 9;

	/**
	 * <p>Modulo-based key distribution algorithm.</p>
	 * @link https://php.net/manual/en/memcached.constants.php
	 */
	const DISTRIBUTION_MODULA = 0;

	/**
	 * <p>Consistent hashing key distribution algorithm (based on libketama).</p>
	 * @link https://php.net/manual/en/memcached.constants.php
	 */
	const DISTRIBUTION_CONSISTENT = 1;
	const DISTRIBUTION_VIRTUAL_BUCKET = 6;

	/**
	 * <p>Enables or disables compatibility with libketama-like behavior. When
	 * enabled, the item key hashing algorithm is set to MD5 and distribution is
	 * set to be weighted consistent hashing distribution. This is useful
	 * because other libketama-based clients (Python, Ruby, etc.) with the same
	 * server configuration will be able to access the keys transparently.
	 * </p>
	 * <p>
	 * It is highly recommended to enable this option if you want to use
	 * consistent hashing, and it may be enabled by default in future
	 * releases.
	 * </p>
	 * <p>Type: boolean, default: <b>FALSE</b>.</p>
	 * @link https://php.net/manual/en/memcached.constants.php
	 */
	const OPT_LIBKETAMA_COMPATIBLE = 16;
	const OPT_LIBKETAMA_HASH = 17;
	const OPT_TCP_KEEPALIVE = 32;

	/**
	 * <p>Enables or disables buffered I/O. Enabling buffered I/O causes
	 * storage commands to "buffer" instead of being sent. Any action that
	 * retrieves data causes this buffer to be sent to the remote connection.
	 * Quitting the connection or closing down the connection will also cause
	 * the buffered data to be pushed to the remote connection.</p>
	 * <p>Type: boolean, default: <b>FALSE</b>.</p>
	 * @link https://php.net/manual/en/memcached.constants.php
	 */
	const OPT_BUFFER_WRITES = 10;

	/**
	 * <p>Enable the use of the binary protocol. Please note that you cannot
	 * toggle this option on an open connection.</p>
	 * <p>Type: boolean, default: <b>FALSE</b>.</p>
	 * @link https://php.net/manual/en/memcached.constants.php
	 */
	const OPT_BINARY_PROTOCOL = 18;

	/**
	 * <p>Enables or disables asynchronous I/O. This is the fastest transport
	 * available for storage functions.</p>
	 * <p>Type: boolean, default: <b>FALSE</b>.</p>
	 * @link https://php.net/manual/en/memcached.constants.php
	 */
	const OPT_NO_BLOCK = 0;

	/**
	 * <p>Enables or disables the no-delay feature for connecting sockets (may
	 * be faster in some environments).</p>
	 * <p>Type: boolean, default: <b>FALSE</b>.</p>
	 * @link https://php.net/manual/en/memcached.constants.php
	 */
	const OPT_TCP_NODELAY = 1;

	/**
	 * <p>The maximum socket send buffer in bytes.</p>
	 * <p>Type: integer, default: varies by platform/kernel
	 * configuration.</p>
	 * @link https://php.net/manual/en/memcached.constants.php
	 */
	const OPT_SOCKET_SEND_SIZE = 4;

	/**
	 * <p>The maximum socket receive buffer in bytes.</p>
	 * <p>Type: integer, default: varies by platform/kernel
	 * configuration.</p>
	 * @link https://php.net/manual/en/memcached.constants.php
	 */
	const OPT_SOCKET_RECV_SIZE = 5;

	/**
	 * <p>In non-blocking mode this set the value of the timeout during socket
	 * connection, in milliseconds.</p>
	 * <p>Type: integer, default: 1000.</p>
	 * @link https://php.net/manual/en/memcached.constants.php
	 */
	const OPT_CONNECT_TIMEOUT = 14;

	/**
	 * <p>The amount of time, in seconds, to wait until retrying a failed
	 * connection attempt.</p>
	 * <p>Type: integer, default: 0.</p>
	 * @link https://php.net/manual/en/memcached.constants.php
	 */
	const OPT_RETRY_TIMEOUT = 15;

	/**
	 * <p>Socket sending timeout, in microseconds. In cases where you cannot
	 * use non-blocking I/O this will allow you to still have timeouts on the
	 * sending of data.</p>
	 * <p>Type: integer, default: 0.</p>
	 * @link https://php.net/manual/en/memcached.constants.php
	 */
	const OPT_SEND_TIMEOUT = 19;

	/**
	 * <p>Socket reading timeout, in microseconds. In cases where you cannot
	 * use non-blocking I/O this will allow you to still have timeouts on the
	 * reading of data.</p>
	 * <p>Type: integer, default: 0.</p>
	 * @link https://php.net/manual/en/memcached.constants.php
	 */
	const OPT_RECV_TIMEOUT = 20;

	/**
	 * <p>Timeout for connection polling, in milliseconds.</p>
	 * <p>Type: integer, default: 1000.</p>
	 * @link https://php.net/manual/en/memcached.constants.php
	 */
	const OPT_POLL_TIMEOUT = 8;

	/**
	 * <p>Enables or disables caching of DNS lookups.</p>
	 * <p>Type: boolean, default: <b>FALSE</b>.</p>
	 * @link https://php.net/manual/en/memcached.constants.php
	 */
	const OPT_CACHE_LOOKUPS = 6;

	/**
	 * <p>Specifies the failure limit for server connection attempts. The
	 * server will be removed after this many continuous connection
	 * failures.</p>
	 * <p>Type: integer, default: 0.</p>
	 * @link https://php.net/manual/en/memcached.constants.php
	 */
	const OPT_SERVER_FAILURE_LIMIT = 21;
	const OPT_AUTO_EJECT_HOSTS = 28;
	const OPT_HASH_WITH_PREFIX_KEY = 25;
	const OPT_NOREPLY = 26;
	const OPT_SORT_HOSTS = 12;
	const OPT_VERIFY_KEY = 13;
	const OPT_USE_UDP = 27;
	const OPT_NUMBER_OF_REPLICAS = 29;
	const OPT_RANDOMIZE_REPLICA_READ = 30;
	const OPT_CORK = 31;
	const OPT_REMOVE_FAILED_SERVERS = 35;
	const OPT_DEAD_TIMEOUT = 36;
	const OPT_SERVER_TIMEOUT_LIMIT = 37;
	const OPT_MAX = 38;
	const OPT_IO_BYTES_WATERMARK = 23;
	const OPT_IO_KEY_PREFETCH = 24;
	const OPT_IO_MSG_WATERMARK = 22;
	const OPT_LOAD_FROM_FILE = 34;
	const OPT_SUPPORT_CAS = 7;
	const OPT_TCP_KEEPIDLE = 33;
	const OPT_USER_DATA = 11;


	/**
	 * libmemcached result codes
	 */
	/**
	 * <p>The operation was successful.</p>
	 * @link https://php.net/manual/en/memcached.constants.php
	 */
	const RES_SUCCESS = 0;

	/**
	 * <p>The operation failed in some fashion.</p>
	 * @link https://php.net/manual/en/memcached.constants.php
	 */
	const RES_FAILURE = 1;

	/**
	 * <p>DNS lookup failed.</p>
	 * @link https://php.net/manual/en/memcached.constants.php
	 */
	const RES_HOST_LOOKUP_FAILURE = 2;

	/**
	 * <p>Failed to read network data.</p>
	 * @link https://php.net/manual/en/memcached.constants.php
	 */
	const RES_UNKNOWN_READ_FAILURE = 7;

	/**
	 * <p>Bad command in memcached protocol.</p>
	 * @link https://php.net/manual/en/memcached.constants.php
	 */
	const RES_PROTOCOL_ERROR = 8;

	/**
	 * <p>Error on the client side.</p>
	 * @link https://php.net/manual/en/memcached.constants.php
	 */
	const RES_CLIENT_ERROR = 9;

	/**
	 * <p>Error on the server side.</p>
	 * @link https://php.net/manual/en/memcached.constants.php
	 */
	const RES_SERVER_ERROR = 10;

	/**
	 * <p>Failed to write network data.</p>
	 * @link https://php.net/manual/en/memcached.constants.php
	 */
	const RES_WRITE_FAILURE = 5;

	/**
	 * <p>Failed to do compare-and-swap: item you are trying to store has been
	 * modified since you last fetched it.</p>
	 * @link https://php.net/manual/en/memcached.constants.php
	 */
	const RES_DATA_EXISTS = 12;

	/**
	 * <p>Item was not stored: but not because of an error. This normally
	 * means that either the condition for an "add" or a "replace" command
	 * wasn't met, or that the item is in a delete queue.</p>
	 * @link https://php.net/manual/en/memcached.constants.php
	 */
	const RES_NOTSTORED = 14;

	/**
	 * <p>Item with this key was not found (with "get" operation or "cas"
	 * operations).</p>
	 * @link https://php.net/manual/en/memcached.constants.php
	 */
	const RES_NOTFOUND = 16;

	/**
	 * <p>Partial network data read error.</p>
	 * @link https://php.net/manual/en/memcached.constants.php
	 */
	const RES_PARTIAL_READ = 18;

	/**
	 * <p>Some errors occurred during multi-get.</p>
	 * @link https://php.net/manual/en/memcached.constants.php
	 */
	const RES_SOME_ERRORS = 19;

	/**
	 * <p>Server list is empty.</p>
	 * @link https://php.net/manual/en/memcached.constants.php
	 */
	const RES_NO_SERVERS = 20;

	/**
	 * <p>End of result set.</p>
	 * @link https://php.net/manual/en/memcached.constants.php
	 */
	const RES_END = 21;

	/**
	 * <p>System error.</p>
	 * @link https://php.net/manual/en/memcached.constants.php
	 */
	const RES_ERRNO = 26;

	/**
	 * <p>The operation was buffered.</p>
	 * @link https://php.net/manual/en/memcached.constants.php
	 */
	const RES_BUFFERED = 32;

	/**
	 * <p>The operation timed out.</p>
	 * @link https://php.net/manual/en/memcached.constants.php
	 */
	const RES_TIMEOUT = 31;

	/**
	 * <p>Bad key.</p>
	 * @link https://php.net/manual/en/memcached.constants.php, http://docs.libmemcached.org/index.html
	 */
	/**
	 * <p>MEMCACHED_BAD_KEY_PROVIDED: The key provided is not a valid key.</p>
	 */
	const RES_BAD_KEY_PROVIDED = 33;
	/**
	 * <p>MEMCACHED_STORED: The requested object has been successfully stored on the server.</p>
	 */
	const RES_STORED = 15;
	/**
	 * <p>MEMCACHED_DELETED: The object requested by the key has been deleted.</p>
	 */
	const RES_DELETED = 22;
	/**
	 * <p>MEMCACHED_STAT: A “stat” command has been returned in the protocol.</p>
	 */
	const RES_STAT = 24;
	/**
	 * <p>MEMCACHED_ITEM: An item has been fetched (this is an internal error only).</p>
	 */
	const RES_ITEM = 25;
	/**
	 * <p>MEMCACHED_NOT_SUPPORTED: The given method is not supported in the server.</p>
	 */
	const RES_NOT_SUPPORTED = 28;
	/**
	 * <p>MEMCACHED_FETCH_NOTFINISHED: A request has been made, but the server has not finished the fetch of the last request.</p>
	 */
	const RES_FETCH_NOTFINISHED = 30;
	/**
	 * <p>MEMCACHED_SERVER_MARKED_DEAD: The requested server has been marked dead.</p>
	 */
	const RES_SERVER_MARKED_DEAD = 35;
	/**
	 * <p>MEMCACHED_UNKNOWN_STAT_KEY: The server you are communicating with has a stat key which has not be defined in the protocol.</p>
	 */
	const RES_UNKNOWN_STAT_KEY = 36;
	/**
	 * <p>MEMCACHED_INVALID_HOST_PROTOCOL: The server you are connecting too has an invalid protocol. Most likely you are connecting to an older server that does not speak the binary protocol.</p>
	 */
	const RES_INVALID_HOST_PROTOCOL = 34;
	/**
	 * <p>MEMCACHED_MEMORY_ALLOCATION_FAILURE: An error has occurred while trying to allocate memory.</p>
	 */
	const RES_MEMORY_ALLOCATION_FAILURE = 17;
	/**
	 * <p>MEMCACHED_E2BIG: Item is too large for the server to store.</p>
	 */
	const RES_E2BIG = 37;
	/**
	 * <p>MEMCACHED_KEY_TOO_BIG: The key that has been provided is too large for the given server.</p>
	 */
	const RES_KEY_TOO_BIG = 39;
	/**
	 * <p>MEMCACHED_SERVER_TEMPORARILY_DISABLED</p>
	 */
	const RES_SERVER_TEMPORARILY_DISABLED = 47;
	/**
	 * <p>MEMORY_ALLOCATION_FAILURE: An error has occurred while trying to allocate memory.
	 *
	 * #if defined(LIBMEMCACHED_VERSION_HEX) && LIBMEMCACHED_VERSION_HEX >= 0x01000008</p>
	 */
	const RES_SERVER_MEMORY_ALLOCATION_FAILURE = 48;
	/**
	 * <p>MEMCACHED_AUTH_PROBLEM: An unknown issue has occured during authentication.</p>
	 */
	const RES_AUTH_PROBLEM = 40;
	/**
	 * <p>MEMCACHED_AUTH_FAILURE: The credentials provided are not valid for this server.</p>
	 */
	const RES_AUTH_FAILURE = 41;
	/**
	 * <p>MEMCACHED_AUTH_CONTINUE: Authentication has been paused.</p>
	 */
	const RES_AUTH_CONTINUE = 42;
	/**
	 * <p>MEMCACHED_CONNECTION_FAILURE: A unknown error has occured while trying to connect to a server.</p>
	 */
	const RES_CONNECTION_FAILURE = 3;
	/**
	 * <p>MEMCACHED_CONNECTION_BIND_FAILURE: Deprecated since version <0.30(libmemcached).
	 * We were not able to bind() to the socket.</p>
	 */
	const RES_CONNECTION_BIND_FAILURE = 4;
	/**
	 * <p>MEMCACHED_READ_FAILURE: A read failure has occurred.</p>
	 */
	const RES_READ_FAILURE = 6;
	/**
	 * <p>MEMCACHED_DATA_DOES_NOT_EXIST: The data requested with the key given was not found.</p>
	 */
	const RES_DATA_DOES_NOT_EXIST = 13;
	/**
	 * <p>MEMCACHED_VALUE: A value has been returned from the server (this is an internal condition only).</p>
	 */
	const RES_VALUE = 23;
	/**
	 * <p>MEMCACHED_FAIL_UNIX_SOCKET: A connection was not established with the server via a unix domain socket.</p>
	 */
	const RES_FAIL_UNIX_SOCKET = 27;
	/**
	 * <p>MEMCACHED_NO_KEY_PROVIDED: Deprecated since version <0.30(libmemcached): Use MEMCACHED_BAD_KEY_PROVIDED instead.
	 * No key was provided.</p>
	 */
	const RES_NO_KEY_PROVIDED = 29;
	/**
	 * <p>MEMCACHED_INVALID_ARGUMENTS: The arguments supplied to the given function were not valid.</p>
	 */
	const RES_INVALID_ARGUMENTS = 38;
	/**
	 * <p>MEMCACHED_PARSE_ERROR: An error has occurred while trying to parse the configuration string. You should use memparse to determine what the error was.</p>
	 */
	const RES_PARSE_ERROR = 43;
	/**
	 * <p>MEMCACHED_PARSE_USER_ERROR: An error has occurred in parsing the configuration string.</p>
	 */
	const RES_PARSE_USER_ERROR = 44;
	/**
	 * <p>MEMCACHED_DEPRECATED: The method that was requested has been deprecated.</p>
	 */
	const RES_DEPRECATED = 45;
	//unknow
	const RES_IN_PROGRESS = 46;
	/**
	 * <p>MEMCACHED_MAXIMUM_RETURN: This in an internal only state.</p>
	 */
	const RES_MAXIMUM_RETURN = 49;

	/**
	 * Server callbacks, if compiled with --memcached-protocol
	 * @link https://github.com/php-memcached-dev/php-memcached/blob/v3.1.5/memcached-api.php
	 */
	const ON_CONNECT = 0;
	const ON_ADD = 1;
	const ON_APPEND = 2;
	const ON_DECREMENT = 3;
	const ON_DELETE = 4;
	const ON_FLUSH = 5;
	const ON_GET = 6;
	const ON_INCREMENT = 7;
	const ON_NOOP = 8;
	const ON_PREPEND = 9;
	const ON_QUIT = 10;
	const ON_REPLACE = 11;
	const ON_SET = 12;
	const ON_STAT = 13;
	const ON_VERSION = 14;
	/**
	 * Constants used when compiled with --memcached-protocol
	 * @link https://github.com/php-memcached-dev/php-memcached/blob/v3.1.5/memcached-api.php
	 */
	const RESPONSE_SUCCESS = 0;
	const RESPONSE_KEY_ENOENT = 1;
	const RESPONSE_KEY_EEXISTS = 2;
	const RESPONSE_E2BIG = 3;
	const RESPONSE_EINVAL = 4;
	const RESPONSE_NOT_STORED = 5;
	const RESPONSE_DELTA_BADVAL = 6;
	const RESPONSE_NOT_MY_VBUCKET = 7;
	const RESPONSE_AUTH_ERROR = 32;
	const RESPONSE_AUTH_CONTINUE = 33;
	const RESPONSE_UNKNOWN_COMMAND = 129;
	const RESPONSE_ENOMEM = 130;
	const RESPONSE_NOT_SUPPORTED = 131;
	const RESPONSE_EINTERNAL = 132;
	const RESPONSE_EBUSY = 133;
	const RESPONSE_ETMPFAIL = 134;


	/**
	 * <p>Failed to create network socket.</p>
	 * @link https://php.net/manual/en/memcached.constants.php
	 */
	const RES_CONNECTION_SOCKET_CREATE_FAILURE = 11;

	/**
	 * <p>Payload failure: could not compress/decompress or serialize/unserialize the value.</p>
	 * @link https://php.net/manual/en/memcached.constants.php
	 */
	const RES_PAYLOAD_FAILURE = -1001;

	/**
	 * <p>The default PHP serializer.</p>
	 * @link https://php.net/manual/en/memcached.constants.php
	 */
	const SERIALIZER_PHP = 1;

	/**
	 * <p>The igbinary serializer.
	 * Instead of textual representation it stores PHP data structures in a
	 * compact binary form, resulting in space and time gains.</p>
	 * @link https://php.net/manual/en/memcached.constants.php
	 */
	const SERIALIZER_IGBINARY = 2;

	/**
	 * <p>The JSON serializer. Requires PHP 5.2.10+.</p>
	 * @link https://php.net/manual/en/memcached.constants.php
	 */
	const SERIALIZER_JSON = 3;
	const SERIALIZER_JSON_ARRAY = 4;
	/**
	 * <p>The msgpack serializer.</p>
	 * @link https://github.com/php-memcached-dev/php-memcached/blob/v3.1.5/memcached-api.php
	 */
	const SERIALIZER_MSGPACK = 5;

	const COMPRESSION_FASTLZ = 2;
	const COMPRESSION_ZLIB = 1;

	/**
	 * <p>A flag for <b>Memcached::getMulti</b> and
	 * <b>Memcached::getMultiByKey</b> to ensure that the keys are
	 * returned in the same order as they were requested in. Non-existing keys
	 * get a default value of NULL.</p>
	 * @link https://php.net/manual/en/memcached.constants.php
	 */
	const GET_PRESERVE_ORDER = 1;

	/**
	 * A flag for <b>Memcached::get()</b>, <b>Memcached::getMulti()</b> and
	 * <b>Memcached::getMultiByKey()</b> to ensure that the CAS token values are returned as well.
	 * @link https://php.net/manual/en/memcached.constants.php
	 */
	const GET_EXTENDED = 2;

	const GET_ERROR_RETURN_VALUE = false;

	/**
	 * (PECL memcached &gt;= 0.1.0)<br/>
	 * Create a Memcached instance
	 * @link https://php.net/manual/en/memcached.construct.php, https://github.com/php-memcached-dev/php-memcached/blob/v3.1.5/php_memcached.c
	 * @param string $persistent_id [optional]
	 * @param callable $on_new_object_cb [optional]
	 * @param string $connection_str [optional]
	 */
	public function __construct ($persistent_id = '', $on_new_object_cb = null, $connection_str = '') {}

	/**
	 * (PECL memcached &gt;= 0.1.0)<br/>
	 * Return the result code of the last operation
	 * @link https://php.net/manual/en/memcached.getresultcode.php
	 * @return int Result code of the last Memcached operation.
	 */
	public function getResultCode () {}

	/**
	 * (PECL memcached &gt;= 1.0.0)<br/>
	 * Return the message describing the result of the last operation
	 * @link https://php.net/manual/en/memcached.getresultmessage.php
	 * @return string Message describing the result of the last Memcached operation.
	 */
	public function getResultMessage () {}

	/**
	 * (PECL memcached &gt;= 0.1.0)<br/>
	 * Retrieve an item
	 * @link https://php.net/manual/en/memcached.get.php
	 * @param string $key <p>
	 * The key of the item to retrieve.
	 * </p>
	 * @param callable $cache_cb [optional] <p>
	 * Read-through caching callback or <b>NULL</b>.
	 * </p>
	 * @param int $flags [optional] <p>
	 * The flags for the get operation.
	 * </p>
	 * @return mixed the value stored in the cache or <b>FALSE</b> otherwise.
	 * The <b>Memcached::getResultCode</b> will return
	 * <b>Memcached::RES_NOTFOUND</b> if the key does not exist.
	 */
	public function get ($key, callable $cache_cb = null, $flags = 0) {}

	/**
	 * (PECL memcached &gt;= 0.1.0)<br/>
	 * Retrieve an item from a specific server
	 * @link https://php.net/manual/en/memcached.getbykey.php
	 * @param string $server_key <p>
	 * The key identifying the server to store the value on or retrieve it from. Instead of hashing on the actual key for the item, we hash on the server key when deciding which memcached server to talk to. This allows related items to be grouped together on a single server for efficiency with multi operations.
	 * </p>
	 * @param string $key <p>
	 * The key of the item to fetch.
	 * </p>
	 * @param callable $cache_cb [optional] <p>
	 * Read-through caching callback or <b>NULL</b>
	 * </p>
	 * @param int $flags [optional] <p>
	 * The flags for the get operation.
	 * </p>
	 * @return mixed the value stored in the cache or <b>FALSE</b> otherwise.
	 * The <b>Memcached::getResultCode</b> will return
	 * <b>Memcached::RES_NOTFOUND</b> if the key does not exist.
	 */
	public function getByKey ($server_key, $key, callable $cache_cb = null, $flags = 0) {}

	/**
	 * (PECL memcached &gt;= 0.1.0)<br/>
	 * Retrieve multiple items
	 * @link https://php.net/manual/en/memcached.getmulti.php
	 * @param array $keys <p>
	 * Array of keys to retrieve.
	 * </p>
	 * @param int $flags [optional] <p>
	 * The flags for the get operation.
	 * </p>
	 * @return mixed the array of found items or <b>FALSE</b> on failure.
	 * Use <b>Memcached::getResultCode</b> if necessary.
	 */
	public function getMulti (array $keys, $flags = 0) {}

	/**
	 * (PECL memcached &gt;= 0.1.0)<br/>
	 * Retrieve multiple items from a specific server
	 * @link https://php.net/manual/en/memcached.getmultibykey.php
	 * @param string $server_key <p>
	 * The key identifying the server to store the value on or retrieve it from. Instead of hashing on the actual key for the item, we hash on the server key when deciding which memcached server to talk to. This allows related items to be grouped together on a single server for efficiency with multi operations.
	 * </p>
	 * @param array $keys <p>
	 * Array of keys to retrieve.
	 * </p>
	 * @param int $flags [optional] <p>
	 * The flags for the get operation.
	 * </p>
	 * @return array|false the array of found items or <b>FALSE</b> on failure.
	 * Use <b>Memcached::getResultCode</b> if necessary.
	 */
	public function getMultiByKey ($server_key, array $keys, $flags = 0) {}

	/**
	 * (PECL memcached &gt;= 0.1.0)<br/>
	 * Request multiple items
	 * @link https://php.net/manual/en/memcached.getdelayed.php
	 * @param array $keys <p>
	 * Array of keys to request.
	 * </p>
	 * @param bool $with_cas [optional] <p>
	 * Whether to request CAS token values also.
	 * </p>
	 * @param callable $value_cb [optional] <p>
	 * The result callback or <b>NULL</b>.
	 * </p>
	 * @return bool <b>TRUE</b> on success or <b>FALSE</b> on failure.
	 * Use <b>Memcached::getResultCode</b> if necessary.
	 */
	public function getDelayed (array $keys, $with_cas = null, callable $value_cb = null) {}

	/**
	 * (PECL memcached &gt;= 0.1.0)<br/>
	 * Request multiple items from a specific server
	 * @link https://php.net/manual/en/memcached.getdelayedbykey.php
	 * @param string $server_key <p>
	 * The key identifying the server to store the value on or retrieve it from. Instead of hashing on the actual key for the item, we hash on the server key when deciding which memcached server to talk to. This allows related items to be grouped together on a single server for efficiency with multi operations.
	 * </p>
	 * @param array $keys <p>
	 * Array of keys to request.
	 * </p>
	 * @param bool $with_cas [optional] <p>
	 * Whether to request CAS token values also.
	 * </p>
	 * @param callable $value_cb [optional] <p>
	 * The result callback or <b>NULL</b>.
	 * </p>
	 * @return bool <b>TRUE</b> on success or <b>FALSE</b> on failure.
	 * Use <b>Memcached::getResultCode</b> if necessary.
	 */
	public function getDelayedByKey ($server_key, array $keys, $with_cas = null, callable $value_cb = null) {}

	/**
	 * (PECL memcached &gt;= 0.1.0)<br/>
	 * Fetch the next result
	 * @link https://php.net/manual/en/memcached.fetch.php
	 * @return array|false the next result or <b>FALSE</b> otherwise.
	 * The <b>Memcached::getResultCode</b> will return
	 * <b>Memcached::RES_END</b> if result set is exhausted.
	 */
	public function fetch () {}

	/**
	 * (PECL memcached &gt;= 0.1.0)<br/>
	 * Fetch all the remaining results
	 * @link https://php.net/manual/en/memcached.fetchall.php
	 * @return array|false the results or <b>FALSE</b> on failure.
	 * Use <b>Memcached::getResultCode</b> if necessary.
	 */
	public function fetchAll () {}

	/**
	 * (PECL memcached &gt;= 0.1.0)<br/>
	 * Store an item
	 * @link https://php.net/manual/en/memcached.set.php
	 * @param string $key <p>
	 * The key under which to store the value.
	 * </p>
	 * @param mixed $value <p>
	 * The value to store.
	 * </p>
	 * @param int $expiration [optional] <p>
	 * The expiration time, defaults to 0. See Expiration Times for more info.
	 * </p>
	 * @param int $udf_flags [optional]
	 * @return bool <b>TRUE</b> on success or <b>FALSE</b> on failure.
	 * Use <b>Memcached::getResultCode</b> if necessary.
	 */
	public function set ($key, $value, $expiration = 0, $udf_flags = 0) {}

	/**
	 * (PECL memcached &gt;= 0.1.0)<br/>
	 * Store an item on a specific server
	 * @link https://php.net/manual/en/memcached.setbykey.php
	 * @param string $server_key <p>
	 * The key identifying the server to store the value on or retrieve it from. Instead of hashing on the actual key for the item, we hash on the server key when deciding which memcached server to talk to. This allows related items to be grouped together on a single server for efficiency with multi operations.
	 * </p>
	 * @param string $key <p>
	 * The key under which to store the value.
	 * </p>
	 * @param mixed $value <p>
	 * The value to store.
	 * </p>
	 * @param int $expiration [optional] <p>
	 * The expiration time, defaults to 0. See Expiration Times for more info.
	 * </p>
	 * @param int $udf_flags [optional]
	 * @return bool <b>TRUE</b> on success or <b>FALSE</b> on failure.
	 * Use <b>Memcached::getResultCode</b> if necessary.
	 */
	public function setByKey ($server_key, $key, $value, $expiration = 0, $udf_flags = 0) {}

	/**
	 * (PECL memcached &gt;= 2.0.0)<br/>
	 * Set a new expiration on an item
	 * @link https://php.net/manual/en/memcached.touch.php
	 * @param string $key <p>
	 * The key under which to store the value.
	 * </p>
	 * @param int $expiration <p>
	 * The expiration time, defaults to 0. See Expiration Times for more info.
	 * </p>
	 * @return bool <b>TRUE</b> on success or <b>FALSE</b> on failure.
	 * Use <b>Memcached::getResultCode</b> if necessary.
	 */
	public function touch ($key, $expiration = 0) {}

	/**
	 * (PECL memcached &gt;= 2.0.0)<br/>
	 * Set a new expiration on an item on a specific server
	 * @link https://php.net/manual/en/memcached.touchbykey.php
	 * @param string $server_key <p>
	 * The key identifying the server to store the value on or retrieve it from. Instead of hashing on the actual key for the item, we hash on the server key when deciding which memcached server to talk to. This allows related items to be grouped together on a single server for efficiency with multi operations.
	 * </p>
	 * @param string $key <p>
	 * The key under which to store the value.
	 * </p>
	 * @param int $expiration <p>
	 * The expiration time, defaults to 0. See Expiration Times for more info.
	 * </p>
	 * @return bool <b>TRUE</b> on success or <b>FALSE</b> on failure.
	 * Use <b>Memcached::getResultCode</b> if necessary.
	 */
	public function touchByKey ($server_key, $key, $expiration) {}

	/**
	 * (PECL memcached &gt;= 0.1.0)<br/>
	 * Store multiple items
	 * @link https://php.net/manual/en/memcached.setmulti.php
	 * @param array $items <p>
	 * An array of key/value pairs to store on the server.
	 * </p>
	 * @param int $expiration [optional] <p>
	 * The expiration time, defaults to 0. See Expiration Times for more info.
	 * </p>
	 * @param int $udf_flags [optional]
	 * @return bool <b>TRUE</b> on success or <b>FALSE</b> on failure.
	 * Use <b>Memcached::getResultCode</b> if necessary.
	 */
	public function setMulti (array $items, $expiration = 0, $udf_flags = 0) {}

	/**
	 * (PECL memcached &gt;= 0.1.0)<br/>
	 * Store multiple items on a specific server
	 * @link https://php.net/manual/en/memcached.setmultibykey.php
	 * @param string $server_key <p>
	 * The key identifying the server to store the value on or retrieve it from. Instead of hashing on the actual key for the item, we hash on the server key when deciding which memcached server to talk to. This allows related items to be grouped together on a single server for efficiency with multi operations.
	 * </p>
	 * @param array $items <p>
	 * An array of key/value pairs to store on the server.
	 * </p>
	 * @param int $expiration [optional] <p>
	 * The expiration time, defaults to 0. See Expiration Times for more info.
	 * </p>
	 * @param int $udf_flags [optional]
	 * @return bool <b>TRUE</b> on success or <b>FALSE</b> on failure.
	 * Use <b>Memcached::getResultCode</b> if necessary.
	 */
	public function setMultiByKey ($server_key, array $items, $expiration = 0, $udf_flags = 0) {}

	/**
	 * (PECL memcached &gt;= 0.1.0)<br/>
	 * Compare and swap an item
	 * @link https://php.net/manual/en/memcached.cas.php
	 * @param float $cas_token <p>
	 * Unique value associated with the existing item. Generated by memcache.
	 * </p>
	 * @param string $key <p>
	 * The key under which to store the value.
	 * </p>
	 * @param mixed $value <p>
	 * The value to store.
	 * </p>
	 * @param int $expiration [optional] <p>
	 * The expiration time, defaults to 0. See Expiration Times for more info.
	 * </p>
	 * @param int $udf_flags [optional]
	 * @return bool <b>TRUE</b> on success or <b>FALSE</b> on failure.
	 * The <b>Memcached::getResultCode</b> will return
	 * <b>Memcached::RES_DATA_EXISTS</b> if the item you are trying
	 * to store has been modified since you last fetched it.
	 */
	public function cas ($cas_token, $key, $value, $expiration = 0, $udf_flags = 0) {}

	/**
	 * (PECL memcached &gt;= 0.1.0)<br/>
	 * Compare and swap an item on a specific server
	 * @link https://php.net/manual/en/memcached.casbykey.php
	 * @param float $cas_token <p>
	 * Unique value associated with the existing item. Generated by memcache.
	 * </p>
	 * @param string $server_key <p>
	 * The key identifying the server to store the value on or retrieve it from. Instead of hashing on the actual key for the item, we hash on the server key when deciding which memcached server to talk to. This allows related items to be grouped together on a single server for efficiency with multi operations.
	 * </p>
	 * @param string $key <p>
	 * The key under which to store the value.
	 * </p>
	 * @param mixed $value <p>
	 * The value to store.
	 * </p>
	 * @param int $expiration [optional] <p>
	 * The expiration time, defaults to 0. See Expiration Times for more info.
	 * </p>
	 * @param int $udf_flags [optional]
	 * @return bool <b>TRUE</b> on success or <b>FALSE</b> on failure.
	 * The <b>Memcached::getResultCode</b> will return
	 * <b>Memcached::RES_DATA_EXISTS</b> if the item you are trying
	 * to store has been modified since you last fetched it.
	 */
	public function casByKey ($cas_token, $server_key, $key, $value, $expiration = 0, $udf_flags = 0) {}

	/**
	 * (PECL memcached &gt;= 0.1.0)<br/>
	 * Add an item under a new key
	 * @link https://php.net/manual/en/memcached.add.php
	 * @param string $key <p>
	 * The key under which to store the value.
	 * </p>
	 * @param mixed $value <p>
	 * The value to store.
	 * </p>
	 * @param int $expiration [optional] <p>
	 * The expiration time, defaults to 0. See Expiration Times for more info.
	 * </p>
	 * @param int $udf_flags [optional]
	 * @return bool <b>TRUE</b> on success or <b>FALSE</b> on failure.
	 * The <b>Memcached::getResultCode</b> will return
	 * <b>Memcached::RES_NOTSTORED</b> if the key already exists.
	 */
	public function add ($key, $value, $expiration = 0, $udf_flags = 0) {}

	/**
	 * (PECL memcached &gt;= 0.1.0)<br/>
	 * Add an item under a new key on a specific server
	 * @link https://php.net/manual/en/memcached.addbykey.php
	 * @param string $server_key <p>
	 * The key identifying the server to store the value on or retrieve it from. Instead of hashing on the actual key for the item, we hash on the server key when deciding which memcached server to talk to. This allows related items to be grouped together on a single server for efficiency with multi operations.
	 * </p>
	 * @param string $key <p>
	 * The key under which to store the value.
	 * </p>
	 * @param mixed $value <p>
	 * The value to store.
	 * </p>
	 * @param int $expiration [optional] <p>
	 * The expiration time, defaults to 0. See Expiration Times for more info.
	 * </p>
	 * @param int $udf_flags [optional]
	 * @return bool <b>TRUE</b> on success or <b>FALSE</b> on failure.
	 * The <b>Memcached::getResultCode</b> will return
	 * <b>Memcached::RES_NOTSTORED</b> if the key already exists.
	 */
	public function addByKey ($server_key, $key, $value, $expiration = 0, $udf_flags = 0) {}

	/**
	 * (PECL memcached &gt;= 0.1.0)<br/>
	 * Append data to an existing item
	 * @link https://php.net/manual/en/memcached.append.php
	 * @param string $key <p>
	 * The key under which to store the value.
	 * </p>
	 * @param string $value <p>
	 * The string to append.
	 * </p>
	 * @return bool <b>TRUE</b> on success or <b>FALSE</b> on failure.
	 * The <b>Memcached::getResultCode</b> will return
	 * <b>Memcached::RES_NOTSTORED</b> if the key does not exist.
	 */
	public function append ($key, $value) {}

	/**
	 * (PECL memcached &gt;= 0.1.0)<br/>
	 * Append data to an existing item on a specific server
	 * @link https://php.net/manual/en/memcached.appendbykey.php
	 * @param string $server_key <p>
	 * The key identifying the server to store the value on or retrieve it from. Instead of hashing on the actual key for the item, we hash on the server key when deciding which memcached server to talk to. This allows related items to be grouped together on a single server for efficiency with multi operations.
	 * </p>
	 * @param string $key <p>
	 * The key under which to store the value.
	 * </p>
	 * @param string $value <p>
	 * The string to append.
	 * </p>
	 * @return bool <b>TRUE</b> on success or <b>FALSE</b> on failure.
	 * The <b>Memcached::getResultCode</b> will return
	 * <b>Memcached::RES_NOTSTORED</b> if the key does not exist.
	 */
	public function appendByKey ($server_key, $key, $value) {}

	/**
	 * (PECL memcached &gt;= 0.1.0)<br/>
	 * Prepend data to an existing item
	 * @link https://php.net/manual/en/memcached.prepend.php
	 * @param string $key <p>
	 * The key of the item to prepend the data to.
	 * </p>
	 * @param string $value <p>
	 * The string to prepend.
	 * </p>
	 * @return bool <b>TRUE</b> on success or <b>FALSE</b> on failure.
	 * The <b>Memcached::getResultCode</b> will return
	 * <b>Memcached::RES_NOTSTORED</b> if the key does not exist.
	 */
	public function prepend ($key, $value) {}

	/**
	 * (PECL memcached &gt;= 0.1.0)<br/>
	 * Prepend data to an existing item on a specific server
	 * @link https://php.net/manual/en/memcached.prependbykey.php
	 * @param string $server_key <p>
	 * The key identifying the server to store the value on or retrieve it from. Instead of hashing on the actual key for the item, we hash on the server key when deciding which memcached server to talk to. This allows related items to be grouped together on a single server for efficiency with multi operations.
	 * </p>
	 * @param string $key <p>
	 * The key of the item to prepend the data to.
	 * </p>
	 * @param string $value <p>
	 * The string to prepend.
	 * </p>
	 * @return bool <b>TRUE</b> on success or <b>FALSE</b> on failure.
	 * The <b>Memcached::getResultCode</b> will return
	 * <b>Memcached::RES_NOTSTORED</b> if the key does not exist.
	 */
	public function prependByKey ($server_key, $key, $value) {}

	/**
	 * (PECL memcached &gt;= 0.1.0)<br/>
	 * Replace the item under an existing key
	 * @link https://php.net/manual/en/memcached.replace.php
	 * @param string $key <p>
	 * The key under which to store the value.
	 * </p>
	 * @param mixed $value <p>
	 * The value to store.
	 * </p>
	 * @param int $expiration [optional] <p>
	 * The expiration time, defaults to 0. See Expiration Times for more info.
	 * </p>
	 * @param int $udf_flags [optional]
	 * @return bool <b>TRUE</b> on success or <b>FALSE</b> on failure.
	 * The <b>Memcached::getResultCode</b> will return
	 * <b>Memcached::RES_NOTSTORED</b> if the key does not exist.
	 */
	public function replace ($key, $value, $expiration = null, $udf_flags = 0) {}

	/**
	 * (PECL memcached &gt;= 0.1.0)<br/>
	 * Replace the item under an existing key on a specific server
	 * @link https://php.net/manual/en/memcached.replacebykey.php
	 * @param string $server_key <p>
	 * The key identifying the server to store the value on or retrieve it from. Instead of hashing on the actual key for the item, we hash on the server key when deciding which memcached server to talk to. This allows related items to be grouped together on a single server for efficiency with multi operations.
	 * </p>
	 * @param string $key <p>
	 * The key under which to store the value.
	 * </p>
	 * @param mixed $value <p>
	 * The value to store.
	 * </p>
	 * @param int $expiration [optional] <p>
	 * The expiration time, defaults to 0. See Expiration Times for more info.
	 * </p>
	 * @param int $udf_flags [optional]
	 * @return bool <b>TRUE</b> on success or <b>FALSE</b> on failure.
	 * The <b>Memcached::getResultCode</b> will return
	 * <b>Memcached::RES_NOTSTORED</b> if the key does not exist.
	 */
	public function replaceByKey ($server_key, $key, $value, $expiration = null, $udf_flags = 0) {}

	/**
	 * (PECL memcached &gt;= 0.1.0)<br/>
	 * Delete an item
	 * @link https://php.net/manual/en/memcached.delete.php
	 * @param string $key <p>
	 * The key to be deleted.
	 * </p>
	 * @param int $time [optional] <p>
	 * The amount of time the server will wait to delete the item.
	 * </p>
	 * @return bool <b>TRUE</b> on success or <b>FALSE</b> on failure.
	 * The <b>Memcached::getResultCode</b> will return
	 * <b>Memcached::RES_NOTFOUND</b> if the key does not exist.
	 */
	public function delete ($key, $time = 0) {}

	/**
	 * (PECL memcached &gt;= 2.0.0)<br/>
	 * Delete multiple items
	 * @link https://php.net/manual/en/memcached.deletemulti.php
	 * @param array $keys <p>
	 * The keys to be deleted.
	 * </p>
	 * @param int $time [optional] <p>
	 * The amount of time the server will wait to delete the items.
	 * </p>
	 * @return array Returns array indexed by keys and where values are indicating whether operation succeeded or not.
	 * The <b>Memcached::getResultCode</b> will return
	 * <b>Memcached::RES_NOTFOUND</b> if the key does not exist.
	 */
	public function deleteMulti (array $keys, $time = 0) {}

	/**
	 * (PECL memcached &gt;= 0.1.0)<br/>
	 * Delete an item from a specific server
	 * @link https://php.net/manual/en/memcached.deletebykey.php
	 * @param string $server_key <p>
	 * The key identifying the server to store the value on or retrieve it from. Instead of hashing on the actual key for the item, we hash on the server key when deciding which memcached server to talk to. This allows related items to be grouped together on a single server for efficiency with multi operations.
	 * </p>
	 * @param string $key <p>
	 * The key to be deleted.
	 * </p>
	 * @param int $time [optional] <p>
	 * The amount of time the server will wait to delete the item.
	 * </p>
	 * @return bool <b>TRUE</b> on success or <b>FALSE</b> on failure.
	 * The <b>Memcached::getResultCode</b> will return
	 * <b>Memcached::RES_NOTFOUND</b> if the key does not exist.
	 */
	public function deleteByKey ($server_key, $key, $time = 0) {}

	/**
	 * (PECL memcached &gt;= 2.0.0)<br/>
	 * Delete multiple items from a specific server
	 * @link https://php.net/manual/en/memcached.deletemultibykey.php
	 * @param string $server_key <p>
	 * The key identifying the server to store the value on or retrieve it from. Instead of hashing on the actual key for the item, we hash on the server key when deciding which memcached server to talk to. This allows related items to be grouped together on a single server for efficiency with multi operations.
	 * </p>
	 * @param array $keys <p>
	 * The keys to be deleted.
	 * </p>
	 * @param int $time [optional] <p>
	 * The amount of time the server will wait to delete the items.
	 * </p>
	 * @return bool <b>TRUE</b> on success or <b>FALSE</b> on failure.
	 * The <b>Memcached::getResultCode</b> will return
	 * <b>Memcached::RES_NOTFOUND</b> if the key does not exist.
	 */
	public function deleteMultiByKey ($server_key, array $keys, $time = 0) {}

	/**
	 * (PECL memcached &gt;= 0.1.0)<br/>
	 * Increment numeric item's value
	 * @link https://php.net/manual/en/memcached.increment.php
	 * @param string $key <p>
	 * The key of the item to increment.
	 * </p>
	 * @param int $offset [optional] <p>
	 * The amount by which to increment the item's value.
	 * </p>
	 * @param int $initial_value [optional] <p>
	 * The value to set the item to if it doesn't currently exist.
	 * </p>
	 * @param int $expiry [optional] <p>
	 * The expiry time to set on the item.
	 * </p>
	 * @return int|false new item's value on success or <b>FALSE</b> on failure.
	 */
	public function increment ($key, $offset = 1, $initial_value = 0, $expiry = 0) {}

	/**
	 * (PECL memcached &gt;= 0.1.0)<br/>
	 * Decrement numeric item's value
	 * @link https://php.net/manual/en/memcached.decrement.php
	 * @param string $key <p>
	 * The key of the item to decrement.
	 * </p>
	 * @param int $offset [optional] <p>
	 * The amount by which to decrement the item's value.
	 * </p>
	 * @param int $initial_value [optional] <p>
	 * The value to set the item to if it doesn't currently exist.
	 * </p>
	 * @param int $expiry [optional] <p>
	 * The expiry time to set on the item.
	 * </p>
	 * @return int|false item's new value on success or <b>FALSE</b> on failure.
	 */
	public function decrement ($key, $offset = 1, $initial_value = 0, $expiry = 0) {}

	/**
	 * (PECL memcached &gt;= 2.0.0)<br/>
	 * Increment numeric item's value, stored on a specific server
	 * @link https://php.net/manual/en/memcached.incrementbykey.php
	 * @param string $server_key <p>
	 * The key identifying the server to store the value on or retrieve it from. Instead of hashing on the actual key for the item, we hash on the server key when deciding which memcached server to talk to. This allows related items to be grouped together on a single server for efficiency with multi operations.
	 * </p>
	 * @param string $key <p>
	 * The key of the item to increment.
	 * </p>
	 * @param int $offset [optional] <p>
	 * The amount by which to increment the item's value.
	 * </p>
	 * @param int $initial_value [optional] <p>
	 * The value to set the item to if it doesn't currently exist.
	 * </p>
	 * @param int $expiry [optional] <p>
	 * The expiry time to set on the item.
	 * </p>
	 * @return int|false new item's value on success or <b>FALSE</b> on failure.
	 */
	public function incrementByKey ($server_key, $key, $offset = 1, $initial_value = 0, $expiry = 0) {}

	/**
	 * (PECL memcached &gt;= 2.0.0)<br/>
	 * Decrement numeric item's value, stored on a specific server
	 * @link https://php.net/manual/en/memcached.decrementbykey.php
	 * @param string $server_key <p>
	 * The key identifying the server to store the value on or retrieve it from. Instead of hashing on the actual key for the item, we hash on the server key when deciding which memcached server to talk to. This allows related items to be grouped together on a single server for efficiency with multi operations.
	 * </p>
	 * @param string $key <p>
	 * The key of the item to decrement.
	 * </p>
	 * @param int $offset [optional] <p>
	 * The amount by which to decrement the item's value.
	 * </p>
	 * @param int $initial_value [optional] <p>
	 * The value to set the item to if it doesn't currently exist.
	 * </p>
	 * @param int $expiry [optional] <p>
	 * The expiry time to set on the item.
	 * </p>
	 * @return int|false item's new value on success or <b>FALSE</b> on failure.
	 */
	public function decrementByKey ($server_key, $key, $offset = 1, $initial_value = 0, $expiry = 0) {}

	/**
	 * (PECL memcached &gt;= 0.1.0)<br/>
	 * Add a server to the server pool
	 * @link https://php.net/manual/en/memcached.addserver.php
	 * @param string $host <p>
	 * The hostname of the memcache server. If the hostname is invalid, data-related
	 * operations will set
	 * <b>Memcached::RES_HOST_LOOKUP_FAILURE</b> result code.
	 * </p>
	 * @param int $port <p>
	 * The port on which memcache is running. Usually, this is
	 * 11211.
	 * </p>
	 * @param int $weight [optional] <p>
	 * The weight of the server relative to the total weight of all the
	 * servers in the pool. This controls the probability of the server being
	 * selected for operations. This is used only with consistent distribution
	 * option and usually corresponds to the amount of memory available to
	 * memcache on that server.
	 * </p>
	 * @return bool <b>TRUE</b> on success or <b>FALSE</b> on failure.
	 */
	public function addServer ($host, $port, $weight = 0) {}

	/**
	 * (PECL memcached &gt;= 0.1.1)<br/>
	 * Add multiple servers to the server pool
	 * @link https://php.net/manual/en/memcached.addservers.php
	 * @param array $servers
	 * @return bool <b>TRUE</b> on success or <b>FALSE</b> on failure.
	 */
	public function addServers (array $servers) {}

	/**
	 * (PECL memcached &gt;= 0.1.0)<br/>
	 * Get the list of the servers in the pool
	 * @link https://php.net/manual/en/memcached.getserverlist.php
	 * @return array The list of all servers in the server pool.
	 */
	public function getServerList () {}

	/**
	 * (PECL memcached &gt;= 0.1.0)<br/>
	 * Map a key to a server
	 * @link https://php.net/manual/en/memcached.getserverbykey.php
	 * @param string $server_key <p>
	 * The key identifying the server to store the value on or retrieve it from. Instead of hashing on the actual key for the item, we hash on the server key when deciding which memcached server to talk to. This allows related items to be grouped together on a single server for efficiency with multi operations.
	 * </p>
	 * @return array an array containing three keys of host,
	 * port, and weight on success or <b>FALSE</b>
	 * on failure.
	 * Use <b>Memcached::getResultCode</b> if necessary.
	 */
	public function getServerByKey ($server_key) {}

	/**
	 * (PECL memcached &gt;= 2.0.0)<br/>
	 * Clears all servers from the server list
	 * @link https://php.net/manual/en/memcached.resetserverlist.php
	 * @return bool <b>TRUE</b> on success or <b>FALSE</b> on failure.
	 */
	public function resetServerList () {}

	/**
	 * (PECL memcached &gt;= 2.0.0)<br/>
	 * Close any open connections
	 * @link https://php.net/manual/en/memcached.quit.php
	 * @return bool <b>TRUE</b> on success or <b>FALSE</b> on failure.
	 */
	public function quit () {}

	/**
	 * (PECL memcached &gt;= 0.1.0)<br/>
	 * Get server pool statistics
	 * @link https://php.net/manual/en/memcached.getstats.php
	 * @param string $type <p>items, slabs, sizes ...</p>
	 * @return array Array of server statistics, one entry per server.
	 */
	public function getStats ($type = null) {}

	/**
	 * (PECL memcached &gt;= 0.1.5)<br/>
	 * Get server pool version info
	 * @link https://php.net/manual/en/memcached.getversion.php
	 * @return array Array of server versions, one entry per server.
	 */
	public function getVersion () {}

	/**
	 * (PECL memcached &gt;= 2.0.0)<br/>
	 * Gets the keys stored on all the servers
	 * @link https://php.net/manual/en/memcached.getallkeys.php
	 * @return array|false the keys stored on all the servers on success or <b>FALSE</b> on failure.
	 */
	public function getAllKeys () {}

	/**
	 * (PECL memcached &gt;= 0.1.0)<br/>
	 * Invalidate all items in the cache
	 * @link https://php.net/manual/en/memcached.flush.php
	 * @param int $delay [optional] <p>
	 * Numer of seconds to wait before invalidating the items.
	 * </p>
	 * @return bool <b>TRUE</b> on success or <b>FALSE</b> on failure.
	 * Use <b>Memcached::getResultCode</b> if necessary.
	 */
	public function flush ($delay = 0) {}

	/**
	 * (PECL memcached &gt;= 0.1.0)<br/>
	 * Retrieve a Memcached option value
	 * @link https://php.net/manual/en/memcached.getoption.php
	 * @param int $option <p>
	 * One of the Memcached::OPT_* constants.
	 * </p>
	 * @return mixed the value of the requested option, or <b>FALSE</b> on
	 * error.
	 */
	public function getOption ($option) {}

	/**
	 * (PECL memcached &gt;= 0.1.0)<br/>
	 * Set a Memcached option
	 * @link https://php.net/manual/en/memcached.setoption.php
	 * @param int $option
	 * @param mixed $value
	 * @return bool <b>TRUE</b> on success or <b>FALSE</b> on failure.
	 */
	public function setOption ($option, $value) {}

	/**
	 * (PECL memcached &gt;= 2.0.0)<br/>
	 * Set Memcached options
	 * @link https://php.net/manual/en/memcached.setoptions.php
	 * @param array $options <p>
	 * An associative array of options where the key is the option to set and
	 * the value is the new value for the option.
	 * </p>
	 * @return bool <b>TRUE</b> on success or <b>FALSE</b> on failure.
	 */
	public function setOptions (array $options) {}

	/**
	 * (PECL memcached &gt;= 2.0.0)<br/>
	 * Set the credentials to use for authentication
	 * @link https://www.php.net/manual/en/memcached.setsaslauthdata.php
	 * @param string $username <p>
	 * The username to use for authentication.
	 * </p>
	 * @param string $password <p>
	 * The password to use for authentication.
	 * </p>
	 * @return bool <b>TRUE</b> on success or <b>FALSE</b> on failure.
	 */
	public function setSaslAuthData (string $username , string $password) {}

	/**
	 * (PECL memcached &gt;= 2.0.0)<br/>
	 * Check if a persitent connection to memcache is being used
	 * @link https://php.net/manual/en/memcached.ispersistent.php
	 * @return bool true if Memcache instance uses a persistent connection, false otherwise.
	 */
	public function isPersistent () {}

	/**
	 * (PECL memcached &gt;= 2.0.0)<br/>
	 * Check if the instance was recently created
	 * @link https://php.net/manual/en/memcached.ispristine.php
	 * @return bool the true if instance is recently created, false otherwise.
	 */
	public function isPristine () {}

	/**
	 * Flush and send buffered commands
	 * @link https://github.com/php-memcached-dev/php-memcached/blob/v3.1.5/php_memcached.c
	 * @return bool
	 */
	public function flushBuffers () {}

	/**
	 * Sets AES encryption key (libmemcached 1.0.6 and higher)
	 * @link https://github.com/php-memcached-dev/php-memcached/blob/v3.1.5/php_memcached.c
	 * @param string $key
	 * @return bool
	 */
	public function setEncodingKey ( $key ) {}

	/**
	 * Returns the last disconnected server. Was added in 0.34 according to libmemcached's Changelog
	 * @link https://github.com/php-memcached-dev/php-memcached/blob/v3.1.5/php_memcached.c
	 * @return array|false
	 */
	public function getLastDisconnectedServer () {}

	/**
	 * Returns the last error errno that occurred
	 * @link https://github.com/php-memcached-dev/php-memcached/blob/v3.1.5/php_memcached.c
	 * @return int
	 */
	public function getLastErrorErrno () {}

	/**
	 * Returns the last error code that occurred
	 * @link https://github.com/php-memcached-dev/php-memcached/blob/v3.1.5/php_memcached.c
	 * @return int
	 */
	public function getLastErrorCode () {}

	/**
	 * Returns the last error message that occurred
	 * @link https://github.com/php-memcached-dev/php-memcached/blob/v3.1.5/php_memcached.c
	 * @return string
	 */
	public function getLastErrorMessage () {}

	/**
	 * Sets the memcached virtual buckets
	 * @link https://github.com/php-memcached-dev/php-memcached/blob/v3.1.5/php_memcached.c
	 * @param array $host_map
	 * @param array $forward_map
	 * @param int $replicas
	 * @return bool
	 */
	public function setBucket (array $host_map, array $forward_map, $replicas) {}

}

/**
 * @link https://php.net/manual/en/class.memcachedexception.php
 */
class MemcachedException extends RuntimeException  {
	function __construct( $errmsg = "", $errcode  = 0 ) {}
}
// End of memcached v.3.1.5
?>
