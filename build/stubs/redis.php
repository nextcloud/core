<?php

/**
 * Helper autocomplete for php redis extension
 *
 * @author Max Kamashev <max.kamashev@gmail.com>
 * @link   https://github.com/ukko/phpredis-phpdoc
 */
class Redis
{
    const AFTER                 = 'after';
    const BEFORE                = 'before';

    /**
     * Options
     */
    const OPT_SERIALIZER        = 1;
    const OPT_PREFIX            = 2;
    const OPT_READ_TIMEOUT      = 3;
    const OPT_SCAN              = 4;
    const OPT_FAILOVER          = 5;
    const OPT_TCP_KEEPALIVE     = 6;
    const OPT_COMPRESSION       = 7;
    const OPT_REPLY_LITERAL     = 8;
    const OPT_COMPRESSION_LEVEL = 9;

    /**
     * Cluster options
     */
    const FAILOVER_NONE         = 0;
    const FAILOVER_ERROR        = 1;
    const FAILOVER_DISTRIBUTE   = 2;
    const FAILOVER_DISTRIBUTE_SLAVES = 3;

    /**
     * SCAN options
     */
    const SCAN_NORETRY          = 0;
    const SCAN_RETRY            = 1;

    /**
     * Serializers
     */
    const SERIALIZER_NONE       = 0;
    const SERIALIZER_PHP        = 1;
    const SERIALIZER_IGBINARY   = 2;
    const SERIALIZER_MSGPACK    = 3;
    const SERIALIZER_JSON       = 4;

    /**
     * Compressions
     */
    const COMPRESSION_NONE      = 0;
    const COMPRESSION_LZF       = 1;
    const COMPRESSION_ZSTD      = 2;

    /**
     * Compression ZSTD levels
     */
    const COMPRESSION_ZSTD_MIN = 1;
    const COMPRESSION_ZSTD_DEFAULT = 3;
    const COMPRESSION_ZSTD_MAX = 22;

    /**
     * Multi
     */
    const ATOMIC                = 0;
    const MULTI                 = 1;
    const PIPELINE              = 2;

    /**
     * Type
     */
    const REDIS_NOT_FOUND       = 0;
    const REDIS_STRING          = 1;
    const REDIS_SET             = 2;
    const REDIS_LIST            = 3;
    const REDIS_ZSET            = 4;
    const REDIS_HASH            = 5;
    const REDIS_STREAM          = 6;

    /**
     * Creates a Redis client
     *
     * @example $redis = new Redis();
     */
    public function __construct()
    {
    }

    /**
     * Connects to a Redis instance.
     *
     * @param string $host          can be a host, or the path to a unix domain socket
     * @param int    $port          optional
     * @param float  $timeout       value in seconds (optional, default is 0.0 meaning unlimited)
     * @param null   $reserved      should be null if $retryInterval is specified
     * @param int    $retryInterval retry interval in milliseconds.
     * @param float  $readTimeout   value in seconds (optional, default is 0 meaning unlimited)
     *
     * @return bool TRUE on success, FALSE on error
     *
     * @example
     * <pre>
     * $redis->connect('127.0.0.1', 6379);
     * $redis->connect('127.0.0.1');            // port 6379 by default
     * $redis->connect('127.0.0.1', 6379, 2.5); // 2.5 sec timeout.
     * $redis->connect('/tmp/redis.sock');      // unix domain socket.
     * </pre>
     */
    public function connect(
        $host,
        $port = 6379,
        $timeout = 0.0,
        $reserved = null,
        $retryInterval = 0,
        $readTimeout = 0.0
    ) {
    }

    /**
     * Connects to a Redis instance.
     *
     * @param string $host          can be a host, or the path to a unix domain socket
     * @param int    $port          optional
     * @param float  $timeout       value in seconds (optional, default is 0.0 meaning unlimited)
     * @param null   $reserved      should be null if $retry_interval is specified
     * @param int    $retryInterval retry interval in milliseconds.
     * @param float  $readTimeout   value in seconds (optional, default is 0 meaning unlimited)
     *
     * @return bool TRUE on success, FALSE on error
     *
     * @see        connect()
     * @deprecated use Redis::connect()
     */
    public function open(
        $host,
        $port = 6379,
        $timeout = 0.0,
        $reserved = null,
        $retryInterval = 0,
        $readTimeout = 0.0
    ) {
    }

    /**
     * A method to determine if a phpredis object thinks it's connected to a server
     *
     * @return bool Returns TRUE if phpredis thinks it's connected and FALSE if not
     */
    public function isConnected()
    {
    }

    /**
     * Retrieve our host or unix socket that we're connected to
     *
     * @return string|bool The host or unix socket we're connected to or FALSE if we're not connected
     */
    public function getHost()
    {
    }

    /**
     * Get the port we're connected to
     *
     * @return int|bool Returns the port we're connected to or FALSE if we're not connected
     */
    public function getPort()
    {
    }

    /**
     * Get the database number phpredis is pointed to
     *
     * @return int|bool Returns the database number (int) phpredis thinks it's pointing to
     * or FALSE if we're not connected
     */
    public function getDbNum()
    {
    }

    /**
     * Get the (write) timeout in use for phpredis
     *
     * @return float|bool The timeout (DOUBLE) specified in our connect call or FALSE if we're not connected
     */
    public function getTimeout()
    {
    }

    /**
     * Get the read timeout specified to phpredis or FALSE if we're not connected
     *
     * @return float|bool Returns the read timeout (which can be set using setOption and Redis::OPT_READ_TIMEOUT)
     * or FALSE if we're not connected
     */
    public function getReadTimeout()
    {
    }

    /**
     * Gets the persistent ID that phpredis is using
     *
     * @return string|null|bool Returns the persistent id phpredis is using
     * (which will only be set if connected with pconnect),
     * NULL if we're not using a persistent ID,
     * and FALSE if we're not connected
     */
    public function getPersistentID()
    {
    }

    /**
     * Get the password used to authenticate the phpredis connection
     *
     * @return string|null|bool Returns the password used to authenticate a phpredis session or NULL if none was used,
     * and FALSE if we're not connected
     */
    public function getAuth()
    {
    }

    /**
     * Connects to a Redis instance or reuse a connection already established with pconnect/popen.
     *
     * The connection will not be closed on close or end of request until the php process ends.
     * So be patient on to many open FD's (specially on redis server side) when using persistent connections on
     * many servers connecting to one redis server.
     *
     * Also more than one persistent connection can be made identified by either host + port + timeout
     * or host + persistentId or unix socket + timeout.
     *
     * This feature is not available in threaded versions. pconnect and popen then working like their non persistent
     * equivalents.
     *
     * @param string $host          can be a host, or the path to a unix domain socket
     * @param int    $port          optional
     * @param float  $timeout       value in seconds (optional, default is 0 meaning unlimited)
     * @param string $persistentId  identity for the requested persistent connection
     * @param int    $retryInterval retry interval in milliseconds.
     * @param float  $readTimeout   value in seconds (optional, default is 0 meaning unlimited)
     *
     * @return bool TRUE on success, FALSE on ertcnror.
     *
     * @example
     * <pre>
     * $redis->pconnect('127.0.0.1', 6379);
     *
     * // port 6379 by default - same connection like before
     * $redis->pconnect('127.0.0.1');
     *
     * // 2.5 sec timeout and would be another connection than the two before.
     * $redis->pconnect('127.0.0.1', 6379, 2.5);
     *
     * // x is sent as persistent_id and would be another connection than the three before.
     * $redis->pconnect('127.0.0.1', 6379, 2.5, 'x');
     *
     * // unix domain socket - would be another connection than the four before.
     * $redis->pconnect('/tmp/redis.sock');
     * </pre>
     */
    public function pconnect(
        $host,
        $port = 6379,
        $timeout = 0.0,
        $persistentId = null,
        $retryInterval = 0,
        $readTimeout = 0.0
    ) {
    }

    /**
     * @param string $host
     * @param int    $port
     * @param float  $timeout
     * @param string $persistentId
     * @param int    $retryInterval
     * @param float  $readTimeout
     *
     * @return bool
     *
     * @deprecated use Redis::pconnect()
     * @see pconnect()
     */
    public function popen(
        $host,
        $port = 6379,
        $timeout = 0.0,
        $persistentId = '',
        $retryInterval = 0,
        $readTimeout = 0.0
    ) {
    }

    /**
     * Disconnects from the Redis instance.
     *
     * Note: Closing a persistent connection requires PhpRedis >= 4.2.0
     *
     * @since >= 4.2 Closing a persistent connection requires PhpRedis
     *
     * @return bool TRUE on success, FALSE on error
     */
    public function close()
    {
    }

    /**
     * Swap one Redis database with another atomically
     *
     * Note: Requires Redis >= 4.0.0
     *
     * @param int $db1
     * @param int $db2
     *
     * @return bool TRUE on success and FALSE on failure
     *
     * @link https://redis.io/commands/swapdb
     * @since >= 4.0
     * @example
     * <pre>
     * // Swaps DB 0 with DB 1 atomically
     * $redis->swapdb(0, 1);
     * </pre>
     */
    public function swapdb(int $db1, int $db2)
    {
    }

    /**
     * Set client option
     *
     * @param int   $option option name
     * @param mixed $value  option value
     *
     * @return bool TRUE on success, FALSE on error
     *
     * @example
     * <pre>
     * $redis->setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_NONE);        // don't serialize data
     * $redis->setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_PHP);         // use built-in serialize/unserialize
     * $redis->setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_IGBINARY);    // use igBinary serialize/unserialize
     * $redis->setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_MSGPACK);     // Use msgpack serialize/unserialize
     * $redis->setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_JSON);        // Use json serialize/unserialize
     *
     * $redis->setOption(Redis::OPT_PREFIX, 'myAppName:');                      // use custom prefix on all keys
     *
     * // Options for the SCAN family of commands, indicating whether to abstract
     * // empty results from the user.  If set to SCAN_NORETRY (the default), phpredis
     * // will just issue one SCAN command at a time, sometimes returning an empty
     * // array of results.  If set to SCAN_RETRY, phpredis will retry the scan command
     * // until keys come back OR Redis returns an iterator of zero
     * $redis->setOption(Redis::OPT_SCAN, Redis::SCAN_NORETRY);
     * $redis->setOption(Redis::OPT_SCAN, Redis::SCAN_RETRY);
     * </pre>
     */
    public function setOption($option, $value)
    {
    }

    /**
     * Get client option
     *
     * @param int $option parameter name
     *
     * @return mixed|null Parameter value
     *
     * @see setOption()
     * @example
     * // return option value
     * $redis->getOption(Redis::OPT_SERIALIZER);
     */
    public function getOption($option)
    {
    }

    /**
     * Check the current connection status
     *
     * @param string $message
     *
     * @return bool|string TRUE if the command is successful or returns message
     * Throws a RedisException object on connectivity error, as described above.
     * @throws RedisException
     * @link    https://redis.io/commands/ping
     */
    public function ping($message)
    {
    }

    /**
     * Echo the given string
     *
     * @param string $message
     *
     * @return string Returns message
     *
     * @link    https://redis.io/commands/echo
     */
    public function echo($message)
    {
    }

    /**
     * Get the value related to the specified key
     *
     * @param string $key
     *
     * @return string|mixed|bool If key didn't exist, FALSE is returned.
     * Otherwise, the value related to this key is returned
     *
     * @link    https://redis.io/commands/get
     * @example
     * <pre>
     * $redis->set('key', 'hello');
     * $redis->get('key');
     *
     * // set and get with serializer
     * $redis->setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_JSON);
     *
     * $redis->set('key', ['asd' => 'as', 'dd' => 123, 'b' => true]);
     * var_dump($redis->get('key'));
     * // Output:
     * array(3) {
     *  'asd' => string(2) "as"
     *  'dd' => int(123)
     *  'b' => bool(true)
     * }
     * </pre>
     */
    public function get($key)
    {
    }

    /**
     * Set the string value in argument as value of the key.
     *
     * @since If you're using Redis >= 2.6.12, you can pass extended options as explained in example
     *
     * @param string       $key
     * @param string|mixed $value string if not used serializer
     * @param int|array    $timeout [optional] Calling setex() is preferred if you want a timeout.<br>
     * Since 2.6.12 it also supports different flags inside an array. Example ['NX', 'EX' => 60]<br>
     *  - EX seconds -- Set the specified expire time, in seconds.<br>
     *  - PX milliseconds -- Set the specified expire time, in milliseconds.<br>
     *  - NX -- Only set the key if it does not already exist.<br>
     *  - XX -- Only set the key if it already exist.<br>
     * <pre>
     * // Simple key -> value set
     * $redis->set('key', 'value');
     *
     * // Will redirect, and actually make an SETEX call
     * $redis->set('key','value', 10);
     *
     * // Will set the key, if it doesn't exist, with a ttl of 10 seconds
     * $redis->set('key', 'value', ['nx', 'ex' => 10]);
     *
     * // Will set a key, if it does exist, with a ttl of 1000 milliseconds
     * $redis->set('key', 'value', ['xx', 'px' => 1000]);
     * </pre>
     *
     * @return bool TRUE if the command is successful
     *
     * @link     https://redis.io/commands/set
     */
    public function set($key, $value, $timeout = null)
    {
    }

    /**
     * Set the string value in argument as value of the key, with a time to live.
     *
     * @param string       $key
     * @param int          $ttl
     * @param string|mixed $value
     *
     * @return bool TRUE if the command is successful
     *
     * @link    https://redis.io/commands/setex
     * @example $redis->setex('key', 3600, 'value'); // sets key → value, with 1h TTL.
     */
    public function setex($key, $ttl, $value)
    {
    }

    /**
     * Set the value and expiration in milliseconds of a key.
     *
     * @see     setex()
     * @param   string       $key
     * @param   int          $ttl, in milliseconds.
     * @param   string|mixed $value
     *
     * @return bool TRUE if the command is successful
     *
     * @link    https://redis.io/commands/psetex
     * @example $redis->psetex('key', 1000, 'value'); // sets key → value, with 1sec TTL.
     */
    public function psetex($key, $ttl, $value)
    {
    }

    /**
     * Set the string value in argument as value of the key if the key doesn't already exist in the database.
     *
     * @param string       $key
     * @param string|mixed $value
     *
     * @return bool TRUE in case of success, FALSE in case of failure
     *
     * @link    https://redis.io/commands/setnx
     * @example
     * <pre>
     * $redis->setnx('key', 'value');   // return TRUE
     * $redis->setnx('key', 'value');   // return FALSE
     * </pre>
     */
    public function setnx($key, $value)
    {
    }

    /**
     * Remove specified keys.
     *
     * @param   int|string|array $key1 An array of keys, or an undefined number of parameters, each a key: key1 key2 key3 ... keyN
     * @param   int|string       ...$otherKeys
     *
     * @return int Number of keys deleted
     *
     * @link https://redis.io/commands/del
     * @example
     * <pre>
     * $redis->set('key1', 'val1');
     * $redis->set('key2', 'val2');
     * $redis->set('key3', 'val3');
     * $redis->set('key4', 'val4');
     *
     * $redis->del('key1', 'key2');     // return 2
     * $redis->del(['key3', 'key4']);   // return 2
     * </pre>
     */
    public function del($key1, ...$otherKeys)
    {
    }

    /**
     * @see del()
     * @deprecated use Redis::del()
     *
     * @param   string|string[] $key1
     * @param   string          $key2
     * @param   string          $key3
     *
     * @return int Number of keys deleted
     */
    public function delete($key1, $key2 = null, $key3 = null)
    {
    }

    /**
     * Delete a key asynchronously in another thread. Otherwise it is just as DEL, but non blocking.
     *
     * @see del()
     * @param string|string[] $key1
     * @param string          $key2
     * @param string          $key3
     *
     * @return int Number of keys unlinked.
     *
     * @link    https://redis.io/commands/unlink
     * @example
     * <pre>
     * $redis->set('key1', 'val1');
     * $redis->set('key2', 'val2');
     * $redis->set('key3', 'val3');
     * $redis->set('key4', 'val4');
     * $redis->unlink('key1', 'key2');          // return 2
     * $redis->unlink(array('key3', 'key4'));   // return 2
     * </pre>
     */
    public function unlink($key1, $key2 = null, $key3 = null)
    {
    }

    /**
     * Enter and exit transactional mode.
     *
     * @param int $mode Redis::MULTI|Redis::PIPELINE
     * Defaults to Redis::MULTI.
     * A Redis::MULTI block of commands runs as a single transaction;
     * a Redis::PIPELINE block is simply transmitted faster to the server, but without any guarantee of atomicity.
     * discard cancels a transaction.
     *
     * @return Redis returns the Redis instance and enters multi-mode.
     * Once in multi-mode, all subsequent method calls return the same object until exec() is called.
     *
     * @link    https://redis.io/commands/multi
     * @example
     * <pre>
     * $ret = $redis->multi()
     *      ->set('key1', 'val1')
     *      ->get('key1')
     *      ->set('key2', 'val2')
     *      ->get('key2')
     *      ->exec();
     *
     * //$ret == array (
     * //    0 => TRUE,
     * //    1 => 'val1',
     * //    2 => TRUE,
     * //    3 => 'val2');
     * </pre>
     */
    public function multi($mode = Redis::MULTI)
    {
    }

    /**
     * Returns a Redis instance which can simply transmitted faster to the server.
     *
     * @return Redis returns the Redis instance.
     * Once in pipeline-mode, all subsequent method calls return the same object until exec() is called.
     * Pay attention, that Pipeline is not a transaction, so you can get unexpected
     * results in case of big pipelines and small read/write timeouts.
     *
     * @link   https://redis.io/topics/pipelining
     * @example
     * <pre>
     * $ret = $this->redis->pipeline()
     *      ->ping()
     *      ->multi()->set('x', 42)->incr('x')->exec()
     *      ->ping()
     *      ->multi()->get('x')->del('x')->exec()
     *      ->ping()
     *      ->exec();
     *
     * //$ret == array (
     * //    0 => '+PONG',
     * //    1 => [TRUE, 43],
     * //    2 => '+PONG',
     * //    3 => [43, 1],
     * //    4 => '+PONG');
     * </pre>
     */
    public function pipeline()
    {
    }


    /**
     * @return void|array
     *
     * @see multi()
     * @link https://redis.io/commands/exec
     */
    public function exec()
    {
    }

    /**
     * @see multi()
     * @link https://redis.io/commands/discard
     */
    public function discard()
    {
    }

    /**
     * Watches a key for modifications by another client. If the key is modified between WATCH and EXEC,
     * the MULTI/EXEC transaction will fail (return FALSE). unwatch cancels all the watching of all keys by this client.
     * @param string|string[] $key a list of keys
     *
     * @return void
     *
     * @link    https://redis.io/commands/watch
     * @example
     * <pre>
     * $redis->watch('x');
     * // long code here during the execution of which other clients could well modify `x`
     * $ret = $redis->multi()
     *          ->incr('x')
     *          ->exec();
     * // $ret = FALSE if x has been modified between the call to WATCH and the call to EXEC.
     * </pre>
     */
    public function watch($key)
    {
    }

    /**
     * @see watch()
     * @link    https://redis.io/commands/unwatch
     */
    public function unwatch()
    {
    }

    /**
     * Subscribe to channels.
     *
     * Warning: this function will probably change in the future.
     *
     * @param string[]     $channels an array of channels to subscribe
     * @param string|array $callback either a string or an array($instance, 'method_name').
     * The callback function receives 3 parameters: the redis instance, the channel name, and the message.
     *
     * @return mixed|null Any non-null return value in the callback will be returned to the caller.
     *
     * @link    https://redis.io/commands/subscribe
     * @example
     * <pre>
     * function f($redis, $chan, $msg) {
     *  switch($chan) {
     *      case 'chan-1':
     *          ...
     *          break;
     *
     *      case 'chan-2':
     *                     ...
     *          break;
     *
     *      case 'chan-2':
     *          ...
     *          break;
     *      }
     * }
     *
     * $redis->subscribe(array('chan-1', 'chan-2', 'chan-3'), 'f'); // subscribe to 3 chans
     * </pre>
     */
    public function subscribe($channels, $callback)
    {
    }

    /**
     * Subscribe to channels by pattern
     *
     * @param array        $patterns   an array of glob-style patterns to subscribe
     * @param string|array $callback   Either a string or an array with an object and method.
     *                     The callback will get four arguments ($redis, $pattern, $channel, $message)
     * @param mixed        Any non-null return value in the callback will be returned to the caller
     *
     * @link    https://redis.io/commands/psubscribe
     * @example
     * <pre>
     * function psubscribe($redis, $pattern, $chan, $msg) {
     *  echo "Pattern: $pattern\n";
     *  echo "Channel: $chan\n";
     *  echo "Payload: $msg\n";
     * }
     * </pre>
     */
    public function psubscribe($patterns, $callback)
    {
    }

    /**
     * Publish messages to channels.
     *
     * Warning: this function will probably change in the future.
     *
     * @param string $channel a channel to publish to
     * @param string $message string
     *
     * @return int Number of clients that received the message
     *
     * @link    https://redis.io/commands/publish
     * @example $redis->publish('chan-1', 'hello, world!'); // send message.
     */
    public function publish($channel, $message)
    {
    }

    /**
     * A command allowing you to get information on the Redis pub/sub system
     *
     * @param string       $keyword    String, which can be: "channels", "numsub", or "numpat"
     * @param string|array $argument   Optional, variant.
     *                                 For the "channels" subcommand, you can pass a string pattern.
     *                                 For "numsub" an array of channel names
     *
     * @return array|int Either an integer or an array.
     *   - channels  Returns an array where the members are the matching channels.
     *   - numsub    Returns a key/value array where the keys are channel names and
     *               values are their counts.
     *   - numpat    Integer return containing the number active pattern subscriptions
     *
     * @link    https://redis.io/commands/pubsub
     * @example
     * <pre>
     * $redis->pubsub('channels'); // All channels
     * $redis->pubsub('channels', '*pattern*'); // Just channels matching your pattern
     * $redis->pubsub('numsub', array('chan1', 'chan2')); // Get subscriber counts for 'chan1' and 'chan2'
     * $redis->pubsub('numpat'); // Get the number of pattern subscribers
     * </pre>
     */
    public function pubsub($keyword, $argument)
    {
    }

    /**
     * Stop listening for messages posted to the given channels.
     *
     * @param array $channels an array of channels to usubscribe
     *
     * @link    https://redis.io/commands/unsubscribe
     */
    public function unsubscribe($channels = null)
    {
    }

    /**
     * Stop listening for messages posted to the given channels.
     *
     * @param array $patterns   an array of glob-style patterns to unsubscribe
     *
     * @link https://redis.io/commands/punsubscribe
     */
    public function punsubscribe($patterns = null)
    {
    }

    /**
     * Verify if the specified key/keys exists
     *
     * This function took a single argument and returned TRUE or FALSE in phpredis versions < 4.0.0.
     *
     * @since >= 4.0 Returned int, if < 4.0 returned bool
     *
     * @param string|string[] $key
     *
     * @return int|bool The number of keys tested that do exist
     *
     * @link https://redis.io/commands/exists
     * @link https://github.com/phpredis/phpredis#exists
     * @example
     * <pre>
     * $redis->exists('key'); // 1
     * $redis->exists('NonExistingKey'); // 0
     *
     * $redis->mset(['foo' => 'foo', 'bar' => 'bar', 'baz' => 'baz']);
     * $redis->exists(['foo', 'bar', 'baz]); // 3
     * $redis->exists('foo', 'bar', 'baz'); // 3
     * </pre>
     */
    public function exists($key)
    {
    }

    /**
     * Increment the number stored at key by one.
     *
     * @param   string $key
     *
     * @return int the new value
     *
     * @link    https://redis.io/commands/incr
     * @example
     * <pre>
     * $redis->incr('key1'); // key1 didn't exists, set to 0 before the increment and now has the value 1
     * $redis->incr('key1'); // 2
     * $redis->incr('key1'); // 3
     * $redis->incr('key1'); // 4
     * </pre>
     */
    public function incr($key)
    {
    }

    /**
     * Increment the float value of a key by the given amount
     *
     * @param string $key
     * @param float  $increment
     *
     * @return float
     *
     * @link    https://redis.io/commands/incrbyfloat
     * @example
     * <pre>
     * $redis->set('x', 3);
     * $redis->incrByFloat('x', 1.5);   // float(4.5)
     * $redis->get('x');                // float(4.5)
     * </pre>
     */
    public function incrByFloat($key, $increment)
    {
    }

    /**
     * Increment the number stored at key by one.
     * If the second argument is filled, it will be used as the integer value of the increment.
     *
     * @param string $key   key
     * @param int    $value value that will be added to key (only for incrBy)
     *
     * @return int the new value
     *
     * @link    https://redis.io/commands/incrby
     * @example
     * <pre>
     * $redis->incr('key1');        // key1 didn't exists, set to 0 before the increment and now has the value 1
     * $redis->incr('key1');        // 2
     * $redis->incr('key1');        // 3
     * $redis->incr('key1');        // 4
     * $redis->incrBy('key1', 10);  // 14
     * </pre>
     */
    public function incrBy($key, $value)
    {
    }

    /**
     * Decrement the number stored at key by one.
     *
     * @param string $key
     *
     * @return int the new value
     *
     * @link    https://redis.io/commands/decr
     * @example
     * <pre>
     * $redis->decr('key1'); // key1 didn't exists, set to 0 before the increment and now has the value -1
     * $redis->decr('key1'); // -2
     * $redis->decr('key1'); // -3
     * </pre>
     */
    public function decr($key)
    {
    }

    /**
     * Decrement the number stored at key by one.
     * If the second argument is filled, it will be used as the integer value of the decrement.
     *
     * @param string $key
     * @param int    $value  that will be subtracted to key (only for decrBy)
     *
     * @return int the new value
     *
     * @link    https://redis.io/commands/decrby
     * @example
     * <pre>
     * $redis->decr('key1');        // key1 didn't exists, set to 0 before the increment and now has the value -1
     * $redis->decr('key1');        // -2
     * $redis->decr('key1');        // -3
     * $redis->decrBy('key1', 10);  // -13
     * </pre>
     */
    public function decrBy($key, $value)
    {
    }

    /**
     * Adds the string values to the head (left) of the list.
     * Creates the list if the key didn't exist.
     * If the key exists and is not a list, FALSE is returned.
     *
     * @param string $key
     * @param string|mixed $value1... Variadic list of values to push in key, if dont used serialized, used string
     *
     * @return int|bool The new length of the list in case of success, FALSE in case of Failure
     *
     * @link https://redis.io/commands/lpush
     * @example
     * <pre>
     * $redis->lPush('l', 'v1', 'v2', 'v3', 'v4')   // int(4)
     * var_dump( $redis->lRange('l', 0, -1) );
     * // Output:
     * // array(4) {
     * //   [0]=> string(2) "v4"
     * //   [1]=> string(2) "v3"
     * //   [2]=> string(2) "v2"
     * //   [3]=> string(2) "v1"
     * // }
     * </pre>
     */
    public function lPush($key, ...$value1)
    {
    }

    /**
     * Adds the string values to the tail (right) of the list.
     * Creates the list if the key didn't exist.
     * If the key exists and is not a list, FALSE is returned.
     *
     * @param string $key
     * @param string|mixed $value1... Variadic list of values to push in key, if dont used serialized, used string
     *
     * @return int|bool The new length of the list in case of success, FALSE in case of Failure
     *
     * @link    https://redis.io/commands/rpush
     * @example
     * <pre>
     * $redis->rPush('l', 'v1', 'v2', 'v3', 'v4');    // int(4)
     * var_dump( $redis->lRange('l', 0, -1) );
     * // Output:
     * // array(4) {
     * //   [0]=> string(2) "v1"
     * //   [1]=> string(2) "v2"
     * //   [2]=> string(2) "v3"
     * //   [3]=> string(2) "v4"
     * // }
     * </pre>
     */
    public function rPush($key, ...$value1)
    {
    }

    /**
     * Adds the string value to the head (left) of the list if the list exists.
     *
     * @param string $key
     * @param string|mixed $value String, value to push in key
     *
     * @return int|bool The new length of the list in case of success, FALSE in case of Failure.
     *
     * @link    https://redis.io/commands/lpushx
     * @example
     * <pre>
     * $redis->del('key1');
     * $redis->lPushx('key1', 'A');     // returns 0
     * $redis->lPush('key1', 'A');      // returns 1
     * $redis->lPushx('key1', 'B');     // returns 2
     * $redis->lPushx('key1', 'C');     // returns 3
     * // key1 now points to the following list: [ 'A', 'B', 'C' ]
     * </pre>
     */
    public function lPushx($key, $value)
    {
    }

    /**
     * Adds the string value to the tail (right) of the list if the ist exists. FALSE in case of Failure.
     *
     * @param string $key
     * @param string|mixed $value String, value to push in key
     *
     * @return int|bool The new length of the list in case of success, FALSE in case of Failure.
     *
     * @link    https://redis.io/commands/rpushx
     * @example
     * <pre>
     * $redis->del('key1');
     * $redis->rPushx('key1', 'A'); // returns 0
     * $redis->rPush('key1', 'A'); // returns 1
     * $redis->rPushx('key1', 'B'); // returns 2
     * $redis->rPushx('key1', 'C'); // returns 3
     * // key1 now points to the following list: [ 'A', 'B', 'C' ]
     * </pre>
     */
    public function rPushx($key, $value)
    {
    }

    /**
     * Returns and removes the first element of the list.
     *
     * @param   string $key
     *
     * @return  mixed|bool if command executed successfully BOOL FALSE in case of failure (empty list)
     *
     * @link    https://redis.io/commands/lpop
     * @example
     * <pre>
     * $redis->rPush('key1', 'A');
     * $redis->rPush('key1', 'B');
     * $redis->rPush('key1', 'C');  // key1 => [ 'A', 'B', 'C' ]
     * $redis->lPop('key1');        // key1 => [ 'B', 'C' ]
     * </pre>
     */
    public function lPop($key)
    {
    }

    /**
     * Returns and removes the last element of the list.
     *
     * @param   string $key
     *
     * @return  mixed|bool if command executed successfully BOOL FALSE in case of failure (empty list)
     *
     * @link    https://redis.io/commands/rpop
     * @example
     * <pre>
     * $redis->rPush('key1', 'A');
     * $redis->rPush('key1', 'B');
     * $redis->rPush('key1', 'C');  // key1 => [ 'A', 'B', 'C' ]
     * $redis->rPop('key1');        // key1 => [ 'A', 'B' ]
     * </pre>
     */
    public function rPop($key)
    {
    }

    /**
     * Is a blocking lPop primitive. If at least one of the lists contains at least one element,
     * the element will be popped from the head of the list and returned to the caller.
     * Il all the list identified by the keys passed in arguments are empty, blPop will block
     * during the specified timeout until an element is pushed to one of those lists. This element will be popped.
     *
     * @param string|string[] $keys    String array containing the keys of the lists OR variadic list of strings
     * @param int             $timeout Timeout is always the required final parameter
     *
     * @return array ['listName', 'element']
     *
     * @link    https://redis.io/commands/blpop
     * @example
     * <pre>
     * // Non blocking feature
     * $redis->lPush('key1', 'A');
     * $redis->del('key2');
     *
     * $redis->blPop('key1', 'key2', 10);        // array('key1', 'A')
     * // OR
     * $redis->blPop(['key1', 'key2'], 10);      // array('key1', 'A')
     *
     * $redis->brPop('key1', 'key2', 10);        // array('key1', 'A')
     * // OR
     * $redis->brPop(['key1', 'key2'], 10); // array('key1', 'A')
     *
     * // Blocking feature
     *
     * // process 1
     * $redis->del('key1');
     * $redis->blPop('key1', 10);
     * // blocking for 10 seconds
     *
     * // process 2
     * $redis->lPush('key1', 'A');
     *
     * // process 1
     * // array('key1', 'A') is returned
     * </pre>
     */
    public function blPop($keys, $timeout)
    {
    }

    /**
     * Is a blocking rPop primitive. If at least one of the lists contains at least one element,
     * the element will be popped from the head of the list and returned to the caller.
     * Il all the list identified by the keys passed in arguments are empty, brPop will
     * block during the specified timeout until an element is pushed to one of those lists. T
     * his element will be popped.
     *
     * @param string|string[] $keys    String array containing the keys of the lists OR variadic list of strings
     * @param int             $timeout Timeout is always the required final parameter
     *
     * @return array ['listName', 'element']
     *
     * @link    https://redis.io/commands/brpop
     * @example
     * <pre>
     * // Non blocking feature
     * $redis->lPush('key1', 'A');
     * $redis->del('key2');
     *
     * $redis->blPop('key1', 'key2', 10); // array('key1', 'A')
     * // OR
     * $redis->blPop(array('key1', 'key2'), 10); // array('key1', 'A')
     *
     * $redis->brPop('key1', 'key2', 10); // array('key1', 'A')
     * // OR
     * $redis->brPop(array('key1', 'key2'), 10); // array('key1', 'A')
     *
     * // Blocking feature
     *
     * // process 1
     * $redis->del('key1');
     * $redis->blPop('key1', 10);
     * // blocking for 10 seconds
     *
     * // process 2
     * $redis->lPush('key1', 'A');
     *
     * // process 1
     * // array('key1', 'A') is returned
     * </pre>
     */
    public function brPop(array $keys, $timeout)
    {
    }

    /**
     * Returns the size of a list identified by Key. If the list didn't exist or is empty,
     * the command returns 0. If the data type identified by Key is not a list, the command return FALSE.
     *
     * @param string $key
     *
     * @return int|bool The size of the list identified by Key exists.
     * bool FALSE if the data type identified by Key is not list
     *
     * @link    https://redis.io/commands/llen
     * @example
     * <pre>
     * $redis->rPush('key1', 'A');
     * $redis->rPush('key1', 'B');
     * $redis->rPush('key1', 'C'); // key1 => [ 'A', 'B', 'C' ]
     * $redis->lLen('key1');       // 3
     * $redis->rPop('key1');
     * $redis->lLen('key1');       // 2
     * </pre>
     */
    public function lLen($key)
    {
    }

    /**
     * @see lLen()
     * @link https://redis.io/commands/llen
     * @deprecated use Redis::lLen()
     *
     * @param string $key
     *
     * @return int The size of the list identified by Key exists
     */
    public function lSize($key)
    {
    }

    /**
     * Return the specified element of the list stored at the specified key.
     * 0 the first element, 1 the second ... -1 the last element, -2 the penultimate ...
     * Return FALSE in case of a bad index or a key that doesn't point to a list.
     *
     * @param string $key
     * @param int    $index
     *
     * @return mixed|bool the element at this index
     *
     * Bool FALSE if the key identifies a non-string data type, or no value corresponds to this index in the list Key.
     *
     * @link    https://redis.io/commands/lindex
     * @example
     * <pre>
     * $redis->rPush('key1', 'A');
     * $redis->rPush('key1', 'B');
     * $redis->rPush('key1', 'C');  // key1 => [ 'A', 'B', 'C' ]
     * $redis->lIndex('key1', 0);     // 'A'
     * $redis->lIndex('key1', -1);    // 'C'
     * $redis->lIndex('key1', 10);    // `FALSE`
     * </pre>
     */
    public function lIndex($key, $index)
    {
    }

    /**
     * @see lIndex()
     * @link https://redis.io/commands/lindex
     * @deprecated use Redis::lIndex()
     *
     * @param string $key
     * @param int $index
     * @return mixed|bool the element at this index
     */
    public function lGet($key, $index)
    {
    }

    /**
     * Set the list at index with the new value.
     *
     * @param string $key
     * @param int    $index
     * @param string $value
     *
     * @return bool TRUE if the new value is setted.
     * FALSE if the index is out of range, or data type identified by key is not a list.
     *
     * @link    https://redis.io/commands/lset
     * @example
     * <pre>
     * $redis->rPush('key1', 'A');
     * $redis->rPush('key1', 'B');
     * $redis->rPush('key1', 'C');    // key1 => [ 'A', 'B', 'C' ]
     * $redis->lIndex('key1', 0);     // 'A'
     * $redis->lSet('key1', 0, 'X');
     * $redis->lIndex('key1', 0);     // 'X'
     * </pre>
     */
    public function lSet($key, $index, $value)
    {
    }

    /**
     * Returns the specified elements of the list stored at the specified key in
     * the range [start, end]. start and stop are interpretated as indices: 0 the first element,
     * 1 the second ... -1 the last element, -2 the penultimate ...
     *
     * @param string $key
     * @param int    $start
     * @param int    $end
     *
     * @return array containing the values in specified range.
     *
     * @link    https://redis.io/commands/lrange
     * @example
     * <pre>
     * $redis->rPush('key1', 'A');
     * $redis->rPush('key1', 'B');
     * $redis->rPush('key1', 'C');
     * $redis->lRange('key1', 0, -1); // array('A', 'B', 'C')
     * </pre>
     */
    public function lRange($key, $start, $end)
    {
    }

    /**
     * @see lRange()
     * @link https://redis.io/commands/lrange
     * @deprecated use Redis::lRange()
     *
     * @param string    $key
     * @param int       $start
     * @param int       $end
     * @return array
     */
    public function lGetRange($key, $start, $end)
    {
    }

    /**
     * Trims an existing list so that it will contain only a specified range of elements.
     *
     * @param string $key
     * @param int    $start
     * @param int    $stop
     *
     * @return array|bool Bool return FALSE if the key identify a non-list value
     *
     * @link        https://redis.io/commands/ltrim
     * @example
     * <pre>
     * $redis->rPush('key1', 'A');
     * $redis->rPush('key1', 'B');
     * $redis->rPush('key1', 'C');
     * $redis->lRange('key1', 0, -1); // array('A', 'B', 'C')
     * $redis->lTrim('key1', 0, 1);
     * $redis->lRange('key1', 0, -1); // array('A', 'B')
     * </pre>
     */
    public function lTrim($key, $start, $stop)
    {
    }

    /**
     * @see lTrim()
     * @link  https://redis.io/commands/ltrim
     * @deprecated use Redis::lTrim()
     *
     * @param string    $key
     * @param int       $start
     * @param int       $stop
     */
    public function listTrim($key, $start, $stop)
    {
    }

    /**
     * Removes the first count occurrences of the value element from the list.
     * If count is zero, all the matching elements are removed. If count is negative,
     * elements are removed from tail to head.
     *
     * @param string $key
     * @param string $value
     * @param int    $count
     *
     * @return int|bool the number of elements to remove
     * bool FALSE if the value identified by key is not a list.
     *
     * @link    https://redis.io/commands/lrem
     * @example
     * <pre>
     * $redis->lPush('key1', 'A');
     * $redis->lPush('key1', 'B');
     * $redis->lPush('key1', 'C');
     * $redis->lPush('key1', 'A');
     * $redis->lPush('key1', 'A');
     *
     * $redis->lRange('key1', 0, -1);   // array('A', 'A', 'C', 'B', 'A')
     * $redis->lRem('key1', 'A', 2);    // 2
     * $redis->lRange('key1', 0, -1);   // array('C', 'B', 'A')
     * </pre>
     */
    public function lRem($key, $value, $count)
    {
    }

    /**
     * @see lRem
     * @link https://redis.io/commands/lremove
     * @deprecated use Redis::lRem()
     *
     * @param string $key
     * @param string $value
     * @param int $count
     */
    public function lRemove($key, $value, $count)
    {
    }

    /**
     * Insert value in the list before or after the pivot value. the parameter options
     * specify the position of the insert (before or after). If the list didn't exists,
     * or the pivot didn't exists, the value is not inserted.
     *
     * @param string       $key
     * @param int          $position Redis::BEFORE | Redis::AFTER
     * @param string       $pivot
     * @param string|mixed $value
     *
     * @return int The number of the elements in the list, -1 if the pivot didn't exists.
     *
     * @link    https://redis.io/commands/linsert
     * @example
     * <pre>
     * $redis->del('key1');
     * $redis->lInsert('key1', Redis::AFTER, 'A', 'X');     // 0
     *
     * $redis->lPush('key1', 'A');
     * $redis->lPush('key1', 'B');
     * $redis->lPush('key1', 'C');
     *
     * $redis->lInsert('key1', Redis::BEFORE, 'C', 'X');    // 4
     * $redis->lRange('key1', 0, -1);                       // array('A', 'B', 'X', 'C')
     *
     * $redis->lInsert('key1', Redis::AFTER, 'C', 'Y');     // 5
     * $redis->lRange('key1', 0, -1);                       // array('A', 'B', 'X', 'C', 'Y')
     *
     * $redis->lInsert('key1', Redis::AFTER, 'W', 'value'); // -1
     * </pre>
     */
    public function lInsert($key, $position, $pivot, $value)
    {
    }

    /**
     * Adds a values to the set value stored at key.
     *
     * @param string       $key       Required key
     * @param string|mixed ...$value1 Variadic list of values
     *
     * @return int|bool The number of elements added to the set.
     * If this value is already in the set, FALSE is returned
     *
     * @link    https://redis.io/commands/sadd
     * @example
     * <pre>
     * $redis->sAdd('k', 'v1');                // int(1)
     * $redis->sAdd('k', 'v1', 'v2', 'v3');    // int(2)
     * </pre>
     */
    public function sAdd($key, ...$value1)
    {
    }

    /**
     * Removes the specified members from the set value stored at key.
     *
     * @param   string       $key
     * @param   string|mixed ...$member1 Variadic list of members
     *
     * @return int The number of elements removed from the set
     *
     * @link    https://redis.io/commands/srem
     * @example
     * <pre>
     * var_dump( $redis->sAdd('k', 'v1', 'v2', 'v3') );    // int(3)
     * var_dump( $redis->sRem('k', 'v2', 'v3') );          // int(2)
     * var_dump( $redis->sMembers('k') );
     * //// Output:
     * // array(1) {
     * //   [0]=> string(2) "v1"
     * // }
     * </pre>
     */
    public function sRem($key, ...$member1)
    {
    }

    /**
     * @see sRem()
     * @link    https://redis.io/commands/srem
     * @deprecated use Redis::sRem()
     *
     * @param   string  $key
     * @param   string|mixed  ...$member1
     */
    public function sRemove($key, ...$member1)
    {
    }

    /**
     * Moves the specified member from the set at srcKey to the set at dstKey.
     *
     * @param string       $srcKey
     * @param string       $dstKey
     * @param string|mixed $member
     *
     * @return bool If the operation is successful, return TRUE.
     * If the srcKey and/or dstKey didn't exist, and/or the member didn't exist in srcKey, FALSE is returned.
     *
     * @link    https://redis.io/commands/smove
     * @example
     * <pre>
     * $redis->sAdd('key1' , 'set11');
     * $redis->sAdd('key1' , 'set12');
     * $redis->sAdd('key1' , 'set13');          // 'key1' => {'set11', 'set12', 'set13'}
     * $redis->sAdd('key2' , 'set21');
     * $redis->sAdd('key2' , 'set22');          // 'key2' => {'set21', 'set22'}
     * $redis->sMove('key1', 'key2', 'set13');  // 'key1' =>  {'set11', 'set12'}
     *                                          // 'key2' =>  {'set21', 'set22', 'set13'}
     * </pre>
     */
    public function sMove($srcKey, $dstKey, $member)
    {
    }

    /**
     * Checks if value is a member of the set stored at the key key.
     *
     * @param string       $key
     * @param string|mixed $value
     *
     * @return bool TRUE if value is a member of the set at key key, FALSE otherwise
     *
     * @link    https://redis.io/commands/sismember
     * @example
     * <pre>
     * $redis->sAdd('key1' , 'set1');
     * $redis->sAdd('key1' , 'set2');
     * $redis->sAdd('key1' , 'set3'); // 'key1' => {'set1', 'set2', 'set3'}
     *
     * $redis->sIsMember('key1', 'set1'); // TRUE
     * $redis->sIsMember('key1', 'setX'); // FALSE
     * </pre>
     */
    public function sIsMember($key, $value)
    {
    }

    /**
     * @see sIsMember()
     * @link    https://redis.io/commands/sismember
     * @deprecated use Redis::sIsMember()
     *
     * @param string       $key
     * @param string|mixed $value
     */
    public function sContains($key, $value)
    {
    }

    /**
     * Returns the cardinality of the set identified by key.
     *
     * @param string $key
     *
     * @return int the cardinality of the set identified by key, 0 if the set doesn't exist.
     *
     * @link    https://redis.io/commands/scard
     * @example
     * <pre>
     * $redis->sAdd('key1' , 'set1');
     * $redis->sAdd('key1' , 'set2');
     * $redis->sAdd('key1' , 'set3');   // 'key1' => {'set1', 'set2', 'set3'}
     * $redis->sCard('key1');           // 3
     * $redis->sCard('keyX');           // 0
     * </pre>
     */
    public function sCard($key)
    {
    }

    /**
     * Removes and returns a random element from the set value at Key.
     *
     * @param string $key
     * @param int    $count [optional]
     *
     * @return string|mixed|array|bool "popped" values
     * bool FALSE if set identified by key is empty or doesn't exist.
     *
     * @link    https://redis.io/commands/spop
     * @example
     * <pre>
     * $redis->sAdd('key1' , 'set1');
     * $redis->sAdd('key1' , 'set2');
     * $redis->sAdd('key1' , 'set3');   // 'key1' => {'set3', 'set1', 'set2'}
     * $redis->sPop('key1');            // 'set1', 'key1' => {'set3', 'set2'}
     * $redis->sPop('key1');            // 'set3', 'key1' => {'set2'}
     *
     * // With count
     * $redis->sAdd('key2', 'set1', 'set2', 'set3');
     * var_dump( $redis->sPop('key2', 3) ); // Will return all members but in no particular order
     *
     * // array(3) {
     * //   [0]=> string(4) "set2"
     * //   [1]=> string(4) "set3"
     * //   [2]=> string(4) "set1"
     * // }
     * </pre>
     */
    public function sPop($key, $count = 1)
    {
    }

    /**
     * Returns a random element(s) from the set value at Key, without removing it.
     *
     * @param string $key
     * @param int    $count [optional]
     *
     * @return string|mixed|array|bool value(s) from the set
     * bool FALSE if set identified by key is empty or doesn't exist and count argument isn't passed.
     *
     * @link    https://redis.io/commands/srandmember
     * @example
     * <pre>
     * $redis->sAdd('key1' , 'one');
     * $redis->sAdd('key1' , 'two');
     * $redis->sAdd('key1' , 'three');              // 'key1' => {'one', 'two', 'three'}
     *
     * var_dump( $redis->sRandMember('key1') );     // 'key1' => {'one', 'two', 'three'}
     *
     * // string(5) "three"
     *
     * var_dump( $redis->sRandMember('key1', 2) );  // 'key1' => {'one', 'two', 'three'}
     *
     * // array(2) {
     * //   [0]=> string(2) "one"
     * //   [1]=> string(5) "three"
     * // }
     * </pre>
     */
    public function sRandMember($key, $count = 1)
    {
    }

    /**
     * Returns the members of a set resulting from the intersection of all the sets
     * held at the specified keys. If just a single key is specified, then this command
     * produces the members of this set. If one of the keys is missing, FALSE is returned.
     *
     * @param string $key1         keys identifying the different sets on which we will apply the intersection.
     * @param string ...$otherKeys variadic list of keys
     *
     * @return array contain the result of the intersection between those keys
     * If the intersection between the different sets is empty, the return value will be empty array.
     *
     * @link    https://redis.io/commands/sinter
     * @example
     * <pre>
     * $redis->sAdd('key1', 'val1');
     * $redis->sAdd('key1', 'val2');
     * $redis->sAdd('key1', 'val3');
     * $redis->sAdd('key1', 'val4');
     *
     * $redis->sAdd('key2', 'val3');
     * $redis->sAdd('key2', 'val4');
     *
     * $redis->sAdd('key3', 'val3');
     * $redis->sAdd('key3', 'val4');
     *
     * var_dump($redis->sInter('key1', 'key2', 'key3'));
     *
     * //array(2) {
     * //  [0]=>
     * //  string(4) "val4"
     * //  [1]=>
     * //  string(4) "val3"
     * //}
     * </pre>
     */
    public function sInter($key1, ...$otherKeys)
    {
    }

    /**
     * Performs a sInter command and stores the result in a new set.
     *
     * @param string $dstKey       the key to store the diff into.
     * @param string $key1         keys identifying the different sets on which we will apply the intersection.
     * @param string ...$otherKeys variadic list of keys
     *
     * @return int|bool The cardinality of the resulting set, or FALSE in case of a missing key
     *
     * @link    https://redis.io/commands/sinterstore
     * @example
     * <pre>
     * $redis->sAdd('key1', 'val1');
     * $redis->sAdd('key1', 'val2');
     * $redis->sAdd('key1', 'val3');
     * $redis->sAdd('key1', 'val4');
     *
     * $redis->sAdd('key2', 'val3');
     * $redis->sAdd('key2', 'val4');
     *
     * $redis->sAdd('key3', 'val3');
     * $redis->sAdd('key3', 'val4');
     *
     * var_dump($redis->sInterStore('output', 'key1', 'key2', 'key3'));
     * var_dump($redis->sMembers('output'));
     *
     * //int(2)
     * //
     * //array(2) {
     * //  [0]=>
     * //  string(4) "val4"
     * //  [1]=>
     * //  string(4) "val3"
     * //}
     * </pre>
     */
    public function sInterStore($dstKey, $key1, ...$otherKeys)
    {
    }

    /**
     * Performs the union between N sets and returns it.
     *
     * @param string $key1         first key for union
     * @param string ...$otherKeys variadic list of keys corresponding to sets in redis
     *
     * @return array string[] The union of all these sets
     *
     * @link    https://redis.io/commands/sunionstore
     * @example
     * <pre>
     * $redis->sAdd('s0', '1');
     * $redis->sAdd('s0', '2');
     * $redis->sAdd('s1', '3');
     * $redis->sAdd('s1', '1');
     * $redis->sAdd('s2', '3');
     * $redis->sAdd('s2', '4');
     *
     * var_dump($redis->sUnion('s0', 's1', 's2'));
     *
     * array(4) {
     * //  [0]=>
     * //  string(1) "3"
     * //  [1]=>
     * //  string(1) "4"
     * //  [2]=>
     * //  string(1) "1"
     * //  [3]=>
     * //  string(1) "2"
     * //}
     * </pre>
     */
    public function sUnion($key1, ...$otherKeys)
    {
    }

    /**
     * Performs the same action as sUnion, but stores the result in the first key
     *
     * @param   string  $dstKey  the key to store the diff into.
     * @param string $key1         first key for union
     * @param string ...$otherKeys variadic list of keys corresponding to sets in redis
     *
     * @return int Any number of keys corresponding to sets in redis
     *
     * @link    https://redis.io/commands/sunionstore
     * @example
     * <pre>
     * $redis->del('s0', 's1', 's2');
     *
     * $redis->sAdd('s0', '1');
     * $redis->sAdd('s0', '2');
     * $redis->sAdd('s1', '3');
     * $redis->sAdd('s1', '1');
     * $redis->sAdd('s2', '3');
     * $redis->sAdd('s2', '4');
     *
     * var_dump($redis->sUnionStore('dst', 's0', 's1', 's2'));
     * var_dump($redis->sMembers('dst'));
     *
     * //int(4)
     * //array(4) {
     * //  [0]=>
     * //  string(1) "3"
     * //  [1]=>
     * //  string(1) "4"
     * //  [2]=>
     * //  string(1) "1"
     * //  [3]=>
     * //  string(1) "2"
     * //}
     * </pre>
     */
    public function sUnionStore($dstKey, $key1, ...$otherKeys)
    {
    }

    /**
     * Performs the difference between N sets and returns it.
     *
     * @param string $key1         first key for diff
     * @param string ...$otherKeys variadic list of keys corresponding to sets in redis
     *
     * @return array string[] The difference of the first set will all the others
     *
     * @link    https://redis.io/commands/sdiff
     * @example
     * <pre>
     * $redis->del('s0', 's1', 's2');
     *
     * $redis->sAdd('s0', '1');
     * $redis->sAdd('s0', '2');
     * $redis->sAdd('s0', '3');
     * $redis->sAdd('s0', '4');
     *
     * $redis->sAdd('s1', '1');
     * $redis->sAdd('s2', '3');
     *
     * var_dump($redis->sDiff('s0', 's1', 's2'));
     *
     * //array(2) {
     * //  [0]=>
     * //  string(1) "4"
     * //  [1]=>
     * //  string(1) "2"
     * //}
     * </pre>
     */
    public function sDiff($key1, ...$otherKeys)
    {
    }

    /**
     * Performs the same action as sDiff, but stores the result in the first key
     *
     * @param string $dstKey       the key to store the diff into.
     * @param string $key1         first key for diff
     * @param string ...$otherKeys variadic list of keys corresponding to sets in redis
     *
     * @return int|bool The cardinality of the resulting set, or FALSE in case of a missing key
     *
     * @link    https://redis.io/commands/sdiffstore
     * @example
     * <pre>
     * $redis->del('s0', 's1', 's2');
     *
     * $redis->sAdd('s0', '1');
     * $redis->sAdd('s0', '2');
     * $redis->sAdd('s0', '3');
     * $redis->sAdd('s0', '4');
     *
     * $redis->sAdd('s1', '1');
     * $redis->sAdd('s2', '3');
     *
     * var_dump($redis->sDiffStore('dst', 's0', 's1', 's2'));
     * var_dump($redis->sMembers('dst'));
     *
     * //int(2)
     * //array(2) {
     * //  [0]=>
     * //  string(1) "4"
     * //  [1]=>
     * //  string(1) "2"
     * //}
     * </pre>
     */
    public function sDiffStore($dstKey, $key1, ...$otherKeys)
    {
    }

    /**
     * Returns the contents of a set.
     *
     * @param string $key
     *
     * @return array An array of elements, the contents of the set
     *
     * @link    https://redis.io/commands/smembers
     * @example
     * <pre>
     * $redis->del('s');
     * $redis->sAdd('s', 'a');
     * $redis->sAdd('s', 'b');
     * $redis->sAdd('s', 'a');
     * $redis->sAdd('s', 'c');
     * var_dump($redis->sMembers('s'));
     *
     * //array(3) {
     * //  [0]=>
     * //  string(1) "c"
     * //  [1]=>
     * //  string(1) "a"
     * //  [2]=>
     * //  string(1) "b"
     * //}
     * // The order is random and corresponds to redis' own internal representation of the set structure.
     * </pre>
     */
    public function sMembers($key)
    {
    }

    /**
     * @see sMembers()
     * @link    https://redis.io/commands/smembers
     * @deprecated use Redis::sMembers()
     *
     * @param  string  $key
     * @return array   An array of elements, the contents of the set
     */
    public function sGetMembers($key)
    {
    }

    /**
     * Scan a set for members
     *
     * @param string $key      The set to search.
     * @param int    $iterator LONG (reference) to the iterator as we go.
     * @param string   $pattern  String, optional pattern to match against.
     * @param int    $count    How many members to return at a time (Redis might return a different amount)
     *
     * @return array|bool PHPRedis will return an array of keys or FALSE when we're done iterating
     *
     * @link    https://redis.io/commands/sscan
     * @example
     * <pre>
     * $iterator = null;
     * while ($members = $redis->sScan('set', $iterator)) {
     *     foreach ($members as $member) {
     *         echo $member . PHP_EOL;
     *     }
     * }
     * </pre>
     */
    public function sScan($key, &$iterator, $pattern = null, $count = 0)
    {
    }

    /**
     * Sets a value and returns the previous entry at that key.
     *
     * @param string       $key
     * @param string|mixed $value
     *
     * @return string|mixed A string (mixed, if used serializer), the previous value located at this key
     *
     * @link    https://redis.io/commands/getset
     * @example
     * <pre>
     * $redis->set('x', '42');
     * $exValue = $redis->getSet('x', 'lol');   // return '42', replaces x by 'lol'
     * $newValue = $redis->get('x')'            // return 'lol'
     * </pre>
     */
    public function getSet($key, $value)
    {
    }

    /**
     * Returns a random key
     *
     * @return string an existing key in redis
     *
     * @link    https://redis.io/commands/randomkey
     * @example
     * <pre>
     * $key = $redis->randomKey();
     * $surprise = $redis->get($key);  // who knows what's in there.
     * </pre>
     */
    public function randomKey()
    {
    }

    /**
     * Switches to a given database
     *
     * @param int $dbIndex
     *
     * @return bool TRUE in case of success, FALSE in case of failure
     *
     * @link    https://redis.io/commands/select
     * @example
     * <pre>
     * $redis->select(0);       // switch to DB 0
     * $redis->set('x', '42');  // write 42 to x
     * $redis->move('x', 1);    // move to DB 1
     * $redis->select(1);       // switch to DB 1
     * $redis->get('x');        // will return 42
     * </pre>
     */
    public function select($dbIndex)
    {
    }

    /**
     * Moves a key to a different database.
     *
     * @param string $key
     * @param int    $dbIndex
     *
     * @return bool TRUE in case of success, FALSE in case of failure
     *
     * @link    https://redis.io/commands/move
     * @example
     * <pre>
     * $redis->select(0);       // switch to DB 0
     * $redis->set('x', '42');  // write 42 to x
     * $redis->move('x', 1);    // move to DB 1
     * $redis->select(1);       // switch to DB 1
     * $redis->get('x');        // will return 42
     * </pre>
     */
    public function move($key, $dbIndex)
    {
    }

    /**
     * Renames a key
     *
     * @param string $srcKey
     * @param string $dstKey
     *
     * @return bool TRUE in case of success, FALSE in case of failure
     *
     * @link    https://redis.io/commands/rename
     * @example
     * <pre>
     * $redis->set('x', '42');
     * $redis->rename('x', 'y');
     * $redis->get('y');   // → 42
     * $redis->get('x');   // → `FALSE`
     * </pre>
     */
    public function rename($srcKey, $dstKey)
    {
    }

    /**
     * @see rename()
     * @link    https://redis.io/commands/rename
     * @deprecated use Redis::rename()
     *
     * @param   string  $srcKey
     * @param   string  $dstKey
     */
    public function renameKey($srcKey, $dstKey)
    {
    }

    /**
     * Renames a key
     *
     * Same as rename, but will not replace a key if the destination already exists.
     * This is the same behaviour as setNx.
     *
     * @param string $srcKey
     * @param string $dstKey
     *
     * @return bool TRUE in case of success, FALSE in case of failure
     *
     * @link    https://redis.io/commands/renamenx
     * @example
     * <pre>
     * $redis->set('x', '42');
     * $redis->rename('x', 'y');
     * $redis->get('y');   // → 42
     * $redis->get('x');   // → `FALSE`
     * </pre>
     */
    public function renameNx($srcKey, $dstKey)
    {
    }

    /**
     * Sets an expiration date (a timeout) on an item
     *
     * @param string $key The key that will disappear
     * @param int    $ttl The key's remaining Time To Live, in seconds
     *
     * @return bool TRUE in case of success, FALSE in case of failure
     *
     * @link    https://redis.io/commands/expire
     * @example
     * <pre>
     * $redis->set('x', '42');
     * $redis->expire('x', 3);  // x will disappear in 3 seconds.
     * sleep(5);                    // wait 5 seconds
     * $redis->get('x');            // will return `FALSE`, as 'x' has expired.
     * </pre>
     */
    public function expire($key, $ttl)
    {
    }

    /**
     * Sets an expiration date (a timeout in milliseconds) on an item
     *
     * @param string $key The key that will disappear.
     * @param int    $ttl The key's remaining Time To Live, in milliseconds
     *
     * @return bool TRUE in case of success, FALSE in case of failure
     *
     * @link    https://redis.io/commands/pexpire
     * @example
     * <pre>
     * $redis->set('x', '42');
     * $redis->pExpire('x', 11500); // x will disappear in 11500 milliseconds.
     * $redis->ttl('x');            // 12
     * $redis->pttl('x');           // 11500
     * </pre>
     */
    public function pExpire($key, $ttl)
    {
    }

    /**
     * @see expire()
     * @link    https://redis.io/commands/expire
     * @deprecated use Redis::expire()
     *
     * @param   string  $key
     * @param   int     $ttl
     * @return  bool
     */
    public function setTimeout($key, $ttl)
    {
    }

    /**
     * Sets an expiration date (a timestamp) on an item.
     *
     * @param string $key       The key that will disappear.
     * @param int    $timestamp Unix timestamp. The key's date of death, in seconds from Epoch time.
     *
     * @return bool TRUE in case of success, FALSE in case of failure
     *
     * @link    https://redis.io/commands/expireat
     * @example
     * <pre>
     * $redis->set('x', '42');
     * $now = time(NULL);               // current timestamp
     * $redis->expireAt('x', $now + 3); // x will disappear in 3 seconds.
     * sleep(5);                        // wait 5 seconds
     * $redis->get('x');                // will return `FALSE`, as 'x' has expired.
     * </pre>
     */
    public function expireAt($key, $timestamp)
    {
    }

    /**
     * Sets an expiration date (a timestamp) on an item. Requires a timestamp in milliseconds
     *
     * @param string $key       The key that will disappear
     * @param int    $timestamp Unix timestamp. The key's date of death, in seconds from Epoch time
     *
     * @return bool TRUE in case of success, FALSE in case of failure
     *
     * @link    https://redis.io/commands/pexpireat
     * @example
     * <pre>
     * $redis->set('x', '42');
     * $redis->pExpireAt('x', 1555555555005);
     * echo $redis->ttl('x');                       // 218270121
     * echo $redis->pttl('x');                      // 218270120575
     * </pre>
     */
    public function pExpireAt($key, $timestamp)
    {
    }

    /**
     * Returns the keys that match a certain pattern.
     *
     * @param string $pattern pattern, using '*' as a wildcard
     *
     * @return array string[] The keys that match a certain pattern.
     *
     * @link    https://redis.io/commands/keys
     * @example
     * <pre>
     * $allKeys = $redis->keys('*');   // all keys will match this.
     * $keyWithUserPrefix = $redis->keys('user*');
     * </pre>
     */
    public function keys($pattern)
    {
    }

    /**
     * @see keys()
     * @deprecated use Redis::keys()
     *
     * @param string $pattern
     * @link    https://redis.io/commands/keys
     */
    public function getKeys($pattern)
    {
    }

    /**
     * Returns the current database's size
     *
     * @return int DB size, in number of keys
     *
     * @link    https://redis.io/commands/dbsize
     * @example
     * <pre>
     * $count = $redis->dbSize();
     * echo "Redis has $count keys\n";
     * </pre>
     */
    public function dbSize()
    {
    }

    /**
     * Authenticate the connection using a password.
     * Warning: The password is sent in plain-text over the network.
     *
     * @param string $password
     *
     * @return bool TRUE if the connection is authenticated, FALSE otherwise
     *
     * @link    https://redis.io/commands/auth
     * @example $redis->auth('foobared');
     */
    public function auth($password)
    {
    }

    /**
     * Starts the background rewrite of AOF (Append-Only File)
     *
     * @return bool TRUE in case of success, FALSE in case of failure
     *
     * @link    https://redis.io/commands/bgrewriteaof
     * @example $redis->bgrewriteaof();
     */
    public function bgrewriteaof()
    {
    }

    /**
     * Changes the slave status
     * Either host and port, or no parameter to stop being a slave.
     *
     * @param string $host [optional]
     * @param int    $port [optional]
     *
     * @return bool TRUE in case of success, FALSE in case of failure
     *
     * @link    https://redis.io/commands/slaveof
     * @example
     * <pre>
     * $redis->slaveof('10.0.1.7', 6379);
     * // ...
     * $redis->slaveof();
     * </pre>
     */
    public function slaveof($host = '127.0.0.1', $port = 6379)
    {
    }

    /**
     * Access the Redis slowLog
     *
     * @param string   $operation This can be either GET, LEN, or RESET
     * @param int|null $length    If executing a SLOWLOG GET command, you can pass an optional length.
     *
     * @return mixed The return value of SLOWLOG will depend on which operation was performed.
     * - SLOWLOG GET: Array of slowLog entries, as provided by Redis
     * - SLOGLOG LEN: Integer, the length of the slowLog
     * - SLOWLOG RESET: Boolean, depending on success
     *
     * @example
     * <pre>
     * // Get ten slowLog entries
     * $redis->slowLog('get', 10);
     * // Get the default number of slowLog entries
     *
     * $redis->slowLog('get');
     * // Reset our slowLog
     * $redis->slowLog('reset');
     *
     * // Retrieve slowLog length
     * $redis->slowLog('len');
     * </pre>
     *
     * @link https://redis.io/commands/slowlog
     */
    public function slowLog(string $operation, int $length = null)
    {
    }


    /**
     * Describes the object pointed to by a key.
     * The information to retrieve (string) and the key (string).
     * Info can be one of the following:
     * - "encoding"
     * - "refcount"
     * - "idletime"
     *
     * @param string $string
     * @param string $key
     *
     * @return string|int|bool for "encoding", int for "refcount" and "idletime", FALSE if the key doesn't exist.
     *
     * @link    https://redis.io/commands/object
     * @example
     * <pre>
     * $redis->lPush('l', 'Hello, world!');
     * $redis->object("encoding", "l"); // → ziplist
     * $redis->object("refcount", "l"); // → 1
     * $redis->object("idletime", "l"); // → 400 (in seconds, with a precision of 10 seconds).
     * </pre>
     */
    public function object($string = '', $key = '')
    {
    }

    /**
     * Performs a synchronous save.
     *
     * @return bool TRUE in case of success, FALSE in case of failure
     * If a save is already running, this command will fail and return FALSE.
     *
     * @link    https://redis.io/commands/save
     * @example $redis->save();
     */
    public function save()
    {
    }

    /**
     * Performs a background save.
     *
     * @return bool TRUE in case of success, FALSE in case of failure
     * If a save is already running, this command will fail and return FALSE
     *
     * @link    https://redis.io/commands/bgsave
     * @example $redis->bgSave();
     */
    public function bgsave()
    {
    }

    /**
     * Returns the timestamp of the last disk save.
     *
     * @return int timestamp
     *
     * @link    https://redis.io/commands/lastsave
     * @example $redis->lastSave();
     */
    public function lastSave()
    {
    }

    /**
     * Blocks the current client until all the previous write commands are successfully transferred and
     * acknowledged by at least the specified number of slaves.
     *
     * @param int $numSlaves Number of slaves that need to acknowledge previous write commands.
     * @param int $timeout   Timeout in milliseconds.
     *
     * @return  int The command returns the number of slaves reached by all the writes performed in the
     *              context of the current connection
     *
     * @link    https://redis.io/commands/wait
     * @example $redis->wait(2, 1000);
     */
    public function wait($numSlaves, $timeout)
    {
    }

    /**
     * Returns the type of data pointed by a given key.
     *
     * @param string $key
     *
     * @return int
     * Depending on the type of the data pointed by the key,
     * this method will return the following value:
     * - string: Redis::REDIS_STRING
     * - set:   Redis::REDIS_SET
     * - list:  Redis::REDIS_LIST
     * - zset:  Redis::REDIS_ZSET
     * - hash:  Redis::REDIS_HASH
     * - other: Redis::REDIS_NOT_FOUND
     *
     * @link    https://redis.io/commands/type
     * @example $redis->type('key');
     */
    public function type($key)
    {
    }

    /**
     * Append specified string to the string stored in specified key.
     *
     * @param string       $key
     * @param string|mixed $value
     *
     * @return int Size of the value after the append
     *
     * @link    https://redis.io/commands/append
     * @example
     * <pre>
     * $redis->set('key', 'value1');
     * $redis->append('key', 'value2'); // 12
     * $redis->get('key');              // 'value1value2'
     * </pre>
     */
    public function append($key, $value)
    {
    }

    /**
     * Return a substring of a larger string
     *
     * @param string $key
     * @param int    $start
     * @param int    $end
     *
     * @return string the substring
     *
     * @link    https://redis.io/commands/getrange
     * @example
     * <pre>
     * $redis->set('key', 'string value');
     * $redis->getRange('key', 0, 5);   // 'string'
     * $redis->getRange('key', -5, -1); // 'value'
     * </pre>
     */
    public function getRange($key, $start, $end)
    {
    }

    /**
     * Return a substring of a larger string
     *
     * @deprecated
     * @param   string  $key
     * @param   int     $start
     * @param   int     $end
     */
    public function substr($key, $start, $end)
    {
    }

    /**
     * Changes a substring of a larger string.
     *
     * @param string $key
     * @param int    $offset
     * @param string $value
     *
     * @return int the length of the string after it was modified
     *
     * @link    https://redis.io/commands/setrange
     * @example
     * <pre>
     * $redis->set('key', 'Hello world');
     * $redis->setRange('key', 6, "redis"); // returns 11
     * $redis->get('key');                  // "Hello redis"
     * </pre>
     */
    public function setRange($key, $offset, $value)
    {
    }

    /**
     * Get the length of a string value.
     *
     * @param string $key
     * @return int
     *
     * @link    https://redis.io/commands/strlen
     * @example
     * <pre>
     * $redis->set('key', 'value');
     * $redis->strlen('key'); // 5
     * </pre>
     */
    public function strlen($key)
    {
    }

    /**
     * Return the position of the first bit set to 1 or 0 in a string. The position is returned, thinking of the
     * string as an array of bits from left to right, where the first byte's most significant bit is at position 0,
     * the second byte's most significant bit is at position 8, and so forth.
     *
     * @param string $key
     * @param int    $bit
     * @param int    $start
     * @param int    $end
     *
     * @return int The command returns the position of the first bit set to 1 or 0 according to the request.
     * If we look for set bits (the bit argument is 1) and the string is empty or composed of just
     * zero bytes, -1 is returned. If we look for clear bits (the bit argument is 0) and the string
     * only contains bit set to 1, the function returns the first bit not part of the string on the
     * right. So if the string is three bytes set to the value 0xff the command BITPOS key 0 will
     * return 24, since up to bit 23 all the bits are 1. Basically, the function considers the right
     * of the string as padded with zeros if you look for clear bits and specify no range or the
     * start argument only. However, this behavior changes if you are looking for clear bits and
     * specify a range with both start and end. If no clear bit is found in the specified range, the
     * function returns -1 as the user specified a clear range and there are no 0 bits in that range.
     *
     * @link    https://redis.io/commands/bitpos
     * @example
     * <pre>
     * $redis->set('key', '\xff\xff');
     * $redis->bitpos('key', 1); // int(0)
     * $redis->bitpos('key', 1, 1); // int(8)
     * $redis->bitpos('key', 1, 3); // int(-1)
     * $redis->bitpos('key', 0); // int(16)
     * $redis->bitpos('key', 0, 1); // int(16)
     * $redis->bitpos('key', 0, 1, 5); // int(-1)
     * </pre>
     */
    public function bitpos($key, $bit, $start = 0, $end = null)
    {
    }

    /**
     * Return a single bit out of a larger string
     *
     * @param string $key
     * @param int    $offset
     *
     * @return int the bit value (0 or 1)
     *
     * @link    https://redis.io/commands/getbit
     * @example
     * <pre>
     * $redis->set('key', "\x7f");  // this is 0111 1111
     * $redis->getBit('key', 0);    // 0
     * $redis->getBit('key', 1);    // 1
     * </pre>
     */
    public function getBit($key, $offset)
    {
    }

    /**
     * Changes a single bit of a string.
     *
     * @param string   $key
     * @param int      $offset
     * @param bool|int $value  bool or int (1 or 0)
     *
     * @return int 0 or 1, the value of the bit before it was set
     *
     * @link    https://redis.io/commands/setbit
     * @example
     * <pre>
     * $redis->set('key', "*");     // ord("*") = 42 = 0x2f = "0010 1010"
     * $redis->setBit('key', 5, 1); // returns 0
     * $redis->setBit('key', 7, 1); // returns 0
     * $redis->get('key');          // chr(0x2f) = "/" = b("0010 1111")
     * </pre>
     */
    public function setBit($key, $offset, $value)
    {
    }

    /**
     * Count bits in a string
     *
     * @param string $key
     *
     * @return int The number of bits set to 1 in the value behind the input key
     *
     * @link    https://redis.io/commands/bitcount
     * @example
     * <pre>
     * $redis->set('bit', '345'); // // 11 0011  0011 0100  0011 0101
     * var_dump( $redis->bitCount('bit', 0, 0) ); // int(4)
     * var_dump( $redis->bitCount('bit', 1, 1) ); // int(3)
     * var_dump( $redis->bitCount('bit', 2, 2) ); // int(4)
     * var_dump( $redis->bitCount('bit', 0, 2) ); // int(11)
     * </pre>
     */
    public function bitCount($key)
    {
    }

    /**
     * Bitwise operation on multiple keys.
     *
     * @param string $operation    either "AND", "OR", "NOT", "XOR"
     * @param string $retKey       return key
     * @param string $key1         first key
     * @param string ...$otherKeys variadic list of keys
     *
     * @return int The size of the string stored in the destination key
     *
     * @link    https://redis.io/commands/bitop
     * @example
     * <pre>
     * $redis->set('bit1', '1'); // 11 0001
     * $redis->set('bit2', '2'); // 11 0010
     *
     * $redis->bitOp('AND', 'bit', 'bit1', 'bit2'); // bit = 110000
     * $redis->bitOp('OR',  'bit', 'bit1', 'bit2'); // bit = 110011
     * $redis->bitOp('NOT', 'bit', 'bit1', 'bit2'); // bit = 110011
     * $redis->bitOp('XOR', 'bit', 'bit1', 'bit2'); // bit = 11
     * </pre>
     */
    public function bitOp($operation, $retKey, $key1, ...$otherKeys)
    {
    }

    /**
     * Removes all entries from the current database.
     *
     * @return bool Always TRUE
     * @link    https://redis.io/commands/flushdb
     * @example $redis->flushDB();
     */
    public function flushDB()
    {
    }

    /**
     * Removes all entries from all databases.
     *
     * @return bool Always TRUE
     *
     * @link    https://redis.io/commands/flushall
     * @example $redis->flushAll();
     */
    public function flushAll()
    {
    }

    /**
     * Sort
     *
     * @param string $key
     * @param array  $option array(key => value, ...) - optional, with the following keys and values:
     * - 'by' => 'some_pattern_*',
     * - 'limit' => array(0, 1),
     * - 'get' => 'some_other_pattern_*' or an array of patterns,
     * - 'sort' => 'asc' or 'desc',
     * - 'alpha' => TRUE,
     * - 'store' => 'external-key'
     *
     * @return array
     * An array of values, or a number corresponding to the number of elements stored if that was used
     *
     * @link    https://redis.io/commands/sort
     * @example
     * <pre>
     * $redis->del('s');
     * $redis->sadd('s', 5);
     * $redis->sadd('s', 4);
     * $redis->sadd('s', 2);
     * $redis->sadd('s', 1);
     * $redis->sadd('s', 3);
     *
     * var_dump($redis->sort('s')); // 1,2,3,4,5
     * var_dump($redis->sort('s', array('sort' => 'desc'))); // 5,4,3,2,1
     * var_dump($redis->sort('s', array('sort' => 'desc', 'store' => 'out'))); // (int)5
     * </pre>
     */
    public function sort($key, $option = null)
    {
    }

    /**
     * Returns an associative array of strings and integers
     *
     * @param string $option Optional. The option to provide redis.
     * SERVER | CLIENTS | MEMORY | PERSISTENCE | STATS | REPLICATION | CPU | CLASTER | KEYSPACE | COMANDSTATS
     *
     * Returns an associative array of strings and integers, with the following keys:
     * - redis_version
     * - redis_git_sha1
     * - redis_git_dirty
     * - arch_bits
     * - multiplexing_api
     * - process_id
     * - uptime_in_seconds
     * - uptime_in_days
     * - lru_clock
     * - used_cpu_sys
     * - used_cpu_user
     * - used_cpu_sys_children
     * - used_cpu_user_children
     * - connected_clients
     * - connected_slaves
     * - client_longest_output_list
     * - client_biggest_input_buf
     * - blocked_clients
     * - used_memory
     * - used_memory_human
     * - used_memory_peak
     * - used_memory_peak_human
     * - mem_fragmentation_ratio
     * - mem_allocator
     * - loading
     * - aof_enabled
     * - changes_since_last_save
     * - bgsave_in_progress
     * - last_save_time
     * - total_connections_received
     * - total_commands_processed
     * - expired_keys
     * - evicted_keys
     * - keyspace_hits
     * - keyspace_misses
     * - hash_max_zipmap_entries
     * - hash_max_zipmap_value
     * - pubsub_channels
     * - pubsub_patterns
     * - latest_fork_usec
     * - vm_enabled
     * - role
     *
     * @return string
     *
     * @link    https://redis.io/commands/info
     * @example
     * <pre>
     * $redis->info();
     *
     * or
     *
     * $redis->info("COMMANDSTATS"); //Information on the commands that have been run (>=2.6 only)
     * $redis->info("CPU"); // just CPU information from Redis INFO
     * </pre>
     */
    public function info($option = null)
    {
    }

    /**
     * Resets the statistics reported by Redis using the INFO command (`info()` function).
     * These are the counters that are reset:
     *      - Keyspace hits
     *      - Keyspace misses
     *      - Number of commands processed
     *      - Number of connections received
     *      - Number of expired keys
     *
     * @return bool `TRUE` in case of success, `FALSE` in case of failure.
     *
     * @example $redis->resetStat();
     * @link https://redis.io/commands/config-resetstat
     */
    public function resetStat()
    {
    }

    /**
     * Returns the time to live left for a given key, in seconds. If the key doesn't exist, FALSE is returned.
     *
     * @param string $key
     *
     * @return int|bool the time left to live in seconds
     *
     * @link    https://redis.io/commands/ttl
     * @example
     * <pre>
     * $redis->setex('key', 123, 'test');
     * $redis->ttl('key'); // int(123)
     * </pre>
     */
    public function ttl($key)
    {
    }

    /**
     * Returns a time to live left for a given key, in milliseconds.
     *
     * If the key doesn't exist, FALSE is returned.
     *
     * @param string $key
     *
     * @return int|bool the time left to live in milliseconds
     *
     * @link    https://redis.io/commands/pttl
     * @example
     * <pre>
     * $redis->setex('key', 123, 'test');
     * $redis->pttl('key'); // int(122999)
     * </pre>
     */
    public function pttl($key)
    {
    }

    /**
     * Remove the expiration timer from a key.
     *
     * @param string $key
     *
     * @return bool TRUE if a timeout was removed, FALSE if the key didn’t exist or didn’t have an expiration timer.
     *
     * @link    https://redis.io/commands/persist
     * @example $redis->persist('key');
     */
    public function persist($key)
    {
    }

    /**
     * Sets multiple key-value pairs in one atomic command.
     * MSETNX only returns TRUE if all the keys were set (see SETNX).
     *
     * @param array $array Pairs: array(key => value, ...)
     *
     * @return bool TRUE in case of success, FALSE in case of failure
     *
     * @link    https://redis.io/commands/mset
     * @example
     * <pre>
     * $redis->mset(array('key0' => 'value0', 'key1' => 'value1'));
     * var_dump($redis->get('key0'));
     * var_dump($redis->get('key1'));
     * // Output:
     * // string(6) "value0"
     * // string(6) "value1"
     * </pre>
     */
    public function mset(array $array)
    {
    }

    /**
     * Get the values of all the specified keys.
     * If one or more keys dont exist, the array will contain FALSE at the position of the key.
     *
     * @param array $keys Array containing the list of the keys
     *
     * @return array Array containing the values related to keys in argument
     *
     * @deprecated use Redis::mGet()
     * @example
     * <pre>
     * $redis->set('key1', 'value1');
     * $redis->set('key2', 'value2');
     * $redis->set('key3', 'value3');
     * $redis->getMultiple(array('key1', 'key2', 'key3')); // array('value1', 'value2', 'value3');
     * $redis->getMultiple(array('key0', 'key1', 'key5')); // array(`FALSE`, 'value2', `FALSE`);
     * </pre>
     */
    public function getMultiple(array $keys)
    {
    }

    /**
     * Returns the values of all specified keys.
     *
     * For every key that does not hold a string value or does not exist,
     * the special value false is returned. Because of this, the operation never fails.
     *
     * @param array $array
     *
     * @return array
     *
     * @link https://redis.io/commands/mget
     * @example
     * <pre>
     * $redis->del('x', 'y', 'z', 'h');  // remove x y z
     * $redis->mset(array('x' => 'a', 'y' => 'b', 'z' => 'c'));
     * $redis->hset('h', 'field', 'value');
     * var_dump($redis->mget(array('x', 'y', 'z', 'h')));
     * // Output:
     * // array(3) {
     * //   [0]=> string(1) "a"
     * //   [1]=> string(1) "b"
     * //   [2]=> string(1) "c"
     * //   [3]=> bool(false)
     * // }
     * </pre>
     */
    public function mget(array $array)
    {
    }

    /**
     * @see mset()
     * @param array $array
     * @return int 1 (if the keys were set) or 0 (no key was set)
     *
     * @link    https://redis.io/commands/msetnx
     */
    public function msetnx(array $array)
    {
    }

    /**
     * Pops a value from the tail of a list, and pushes it to the front of another list.
     * Also return this value.
     *
     * @since   redis >= 1.1
     *
     * @param string $srcKey
     * @param string $dstKey
     *
     * @return string|mixed|bool The element that was moved in case of success, FALSE in case of failure.
     *
     * @link    https://redis.io/commands/rpoplpush
     * @example
     * <pre>
     * $redis->del('x', 'y');
     *
     * $redis->lPush('x', 'abc');
     * $redis->lPush('x', 'def');
     * $redis->lPush('y', '123');
     * $redis->lPush('y', '456');
     *
     * // move the last of x to the front of y.
     * var_dump($redis->rpoplpush('x', 'y'));
     * var_dump($redis->lRange('x', 0, -1));
     * var_dump($redis->lRange('y', 0, -1));
     *
     * //Output:
     * //
     * //string(3) "abc"
     * //array(1) {
     * //  [0]=>
     * //  string(3) "def"
     * //}
     * //array(3) {
     * //  [0]=>
     * //  string(3) "abc"
     * //  [1]=>
     * //  string(3) "456"
     * //  [2]=>
     * //  string(3) "123"
     * //}
     * </pre>
     */
    public function rpoplpush($srcKey, $dstKey)
    {
    }

    /**
     * A blocking version of rpoplpush, with an integral timeout in the third parameter.
     *
     * @param string $srcKey
     * @param string $dstKey
     * @param int    $timeout
     *
     * @return  string|mixed|bool  The element that was moved in case of success, FALSE in case of timeout
     *
     * @link    https://redis.io/commands/brpoplpush
     */
    public function brpoplpush($srcKey, $dstKey, $timeout)
    {
    }

    /**
     * Adds the specified member with a given score to the sorted set stored at key
     *
     * @param string                $key     Required key
     * @param array|float           $options Options if needed or score if omitted
     * @param float|string|mixed    $score1  Required score or value if options omitted
     * @param string|float|mixed    $value1  Required value or optional score if options omitted
     * @param float|string|mixed    $score2  Optional score or value if options omitted
     * @param string|float|mixed    $value2  Optional value or score if options omitted
     * @param float|string|mixed    $scoreN  Optional score or value if options omitted
     * @param string|float|mixed    $valueN  Optional value or score if options omitted
     *
     * @return int Number of values added
     *
     * @link    https://redis.io/commands/zadd
     * @example
     * <pre>
     * <pre>
     * $redis->zAdd('z', 1, 'v1', 2, 'v2', 3, 'v3', 4, 'v4' );  // int(2)
     * $redis->zRem('z', 'v2', 'v3');                           // int(2)
     * $redis->zAdd('z', ['NX'], 5, 'v5');                      // int(1)
     * $redis->zAdd('z', ['NX'], 6, 'v5');                      // int(0)
     * $redis->zAdd('z', 7, 'v6');                              // int(1)
     * $redis->zAdd('z', 8, 'v6');                              // int(0)
     *
     * var_dump( $redis->zRange('z', 0, -1) );
     * // Output:
     * // array(4) {
     * //   [0]=> string(2) "v1"
     * //   [1]=> string(2) "v4"
     * //   [2]=> string(2) "v5"
     * //   [3]=> string(2) "v8"
     * // }
     *
     * var_dump( $redis->zRange('z', 0, -1, true) );
     * // Output:
     * // array(4) {
     * //   ["v1"]=> float(1)
     * //   ["v4"]=> float(4)
     * //   ["v5"]=> float(5)
     * //   ["v6"]=> float(8)
     * </pre>
     * </pre>
     */
    public function zAdd($key, $options, $score1, $value1 = null, $score2 = null, $value2 = null, $scoreN = null, $valueN = null)
    {
    }

    /**
     * Returns a range of elements from the ordered set stored at the specified key,
     * with values in the range [start, end]. start and stop are interpreted as zero-based indices:
     * 0 the first element,
     * 1 the second ...
     * -1 the last element,
     * -2 the penultimate ...
     *
     * @param string $key
     * @param int    $start
     * @param int    $end
     * @param bool   $withscores
     *
     * @return array Array containing the values in specified range.
     *
     * @link    https://redis.io/commands/zrange
     * @example
     * <pre>
     * $redis->zAdd('key1', 0, 'val0');
     * $redis->zAdd('key1', 2, 'val2');
     * $redis->zAdd('key1', 10, 'val10');
     * $redis->zRange('key1', 0, -1); // array('val0', 'val2', 'val10')
     * // with scores
     * $redis->zRange('key1', 0, -1, true); // array('val0' => 0, 'val2' => 2, 'val10' => 10)
     * </pre>
     */
    public function zRange($key, $start, $end, $withscores = null)
    {
    }

    /**
     * Deletes a specified member from the ordered set.
     *
     * @param string       $key
     * @param string|mixed $member1
     * @param string|mixed ...$otherMembers
     *
     * @return int Number of deleted values
     *
     * @link    https://redis.io/commands/zrem
     * @example
     * <pre>
     * $redis->zAdd('z', 1, 'v1', 2, 'v2', 3, 'v3', 4, 'v4' );  // int(2)
     * $redis->zRem('z', 'v2', 'v3');                           // int(2)
     * var_dump( $redis->zRange('z', 0, -1) );
     * //// Output:
     * // array(2) {
     * //   [0]=> string(2) "v1"
     * //   [1]=> string(2) "v4"
     * // }
     * </pre>
     */
    public function zRem($key, $member1, ...$otherMembers)
    {
    }

    /**
     * @see zRem()
     * @link https://redis.io/commands/zrem
     * @deprecated use Redis::zRem()
     *
     * @param string       $key
     * @param string|mixed $member1
     * @param string|mixed ...$otherMembers
     *
     * @return int Number of deleted values
     */
    public function zDelete($key, $member1, ...$otherMembers)
    {
    }

    /**
     * Returns the elements of the sorted set stored at the specified key in the range [start, end]
     * in reverse order. start and stop are interpretated as zero-based indices:
     * 0 the first element,
     * 1 the second ...
     * -1 the last element,
     * -2 the penultimate ...
     *
     * @param string $key
     * @param int    $start
     * @param int    $end
     * @param bool   $withscore
     *
     * @return array Array containing the values in specified range.
     *
     * @link    https://redis.io/commands/zrevrange
     * @example
     * <pre>
     * $redis->zAdd('key', 0, 'val0');
     * $redis->zAdd('key', 2, 'val2');
     * $redis->zAdd('key', 10, 'val10');
     * $redis->zRevRange('key', 0, -1); // array('val10', 'val2', 'val0')
     *
     * // with scores
     * $redis->zRevRange('key', 0, -1, true); // array('val10' => 10, 'val2' => 2, 'val0' => 0)
     * </pre>
     */
    public function zRevRange($key, $start, $end, $withscore = null)
    {
    }

    /**
     * Returns the elements of the sorted set stored at the specified key which have scores in the
     * range [start,end]. Adding a parenthesis before start or end excludes it from the range.
     * +inf and -inf are also valid limits.
     *
     * zRevRangeByScore returns the same items in reverse order, when the start and end parameters are swapped.
     *
     * @param string $key
     * @param int    $start
     * @param int    $end
     * @param array  $options Two options are available:
     *  - withscores => TRUE,
     *  - and limit => array($offset, $count)
     *
     * @return array Array containing the values in specified range.
     *
     * @link    https://redis.io/commands/zrangebyscore
     * @example
     * <pre>
     * $redis->zAdd('key', 0, 'val0');
     * $redis->zAdd('key', 2, 'val2');
     * $redis->zAdd('key', 10, 'val10');
     * $redis->zRangeByScore('key', 0, 3);                                          // array('val0', 'val2')
     * $redis->zRangeByScore('key', 0, 3, array('withscores' => TRUE);              // array('val0' => 0, 'val2' => 2)
     * $redis->zRangeByScore('key', 0, 3, array('limit' => array(1, 1));                        // array('val2')
     * $redis->zRangeByScore('key', 0, 3, array('withscores' => TRUE, 'limit' => array(1, 1));  // array('val2' => 2)
     * </pre>
     */
    public function zRangeByScore($key, $start, $end, array $options = array())
    {
    }

    /**
     * @see zRangeByScore()
     * @param string $key
     * @param int    $start
     * @param int    $end
     * @param array  $options
     *
     * @return array
     */
    public function zRevRangeByScore($key, $start, $end, array $options = array())
    {
    }

    /**
     * Returns a lexigraphical range of members in a sorted set, assuming the members have the same score. The
     * min and max values are required to start with '(' (exclusive), '[' (inclusive), or be exactly the values
     * '-' (negative inf) or '+' (positive inf).  The command must be called with either three *or* five
     * arguments or will return FALSE.
     *
     * @param string $key    The ZSET you wish to run against.
     * @param int    $min    The minimum alphanumeric value you wish to get.
     * @param int    $max    The maximum alphanumeric value you wish to get.
     * @param int    $offset Optional argument if you wish to start somewhere other than the first element.
     * @param int    $limit  Optional argument if you wish to limit the number of elements returned.
     *
     * @return array|bool Array containing the values in the specified range.
     *
     * @link    https://redis.io/commands/zrangebylex
     * @example
     * <pre>
     * foreach (array('a', 'b', 'c', 'd', 'e', 'f', 'g') as $char) {
     *     $redis->zAdd('key', $char);
     * }
     *
     * $redis->zRangeByLex('key', '-', '[c'); // array('a', 'b', 'c')
     * $redis->zRangeByLex('key', '-', '(c'); // array('a', 'b')
     * $redis->zRangeByLex('key', '-', '[c'); // array('b', 'c')
     * </pre>
     */
    public function zRangeByLex($key, $min, $max, $offset = null, $limit = null)
    {
    }

    /**
     * @see zRangeByLex()
     * @param string $key
     * @param int    $min
     * @param int    $max
     * @param int    $offset
     * @param int    $limit
     *
     * @return array
     *
     * @link    https://redis.io/commands/zrevrangebylex
     */
    public function zRevRangeByLex($key, $min, $max, $offset = null, $limit = null)
    {
    }

    /**
     * Returns the number of elements of the sorted set stored at the specified key which have
     * scores in the range [start,end]. Adding a parenthesis before start or end excludes it
     * from the range. +inf and -inf are also valid limits.
     *
     * @param string $key
     * @param string $start
     * @param string $end
     *
     * @return int the size of a corresponding zRangeByScore
     *
     * @link    https://redis.io/commands/zcount
     * @example
     * <pre>
     * $redis->zAdd('key', 0, 'val0');
     * $redis->zAdd('key', 2, 'val2');
     * $redis->zAdd('key', 10, 'val10');
     * $redis->zCount('key', 0, 3); // 2, corresponding to array('val0', 'val2')
     * </pre>
     */
    public function zCount($key, $start, $end)
    {
    }

    /**
     * Deletes the elements of the sorted set stored at the specified key which have scores in the range [start,end].
     *
     * @param string       $key
     * @param float|string $start double or "+inf" or "-inf" string
     * @param float|string $end double or "+inf" or "-inf" string
     *
     * @return int The number of values deleted from the sorted set
     *
     * @link    https://redis.io/commands/zremrangebyscore
     * @example
     * <pre>
     * $redis->zAdd('key', 0, 'val0');
     * $redis->zAdd('key', 2, 'val2');
     * $redis->zAdd('key', 10, 'val10');
     * $redis->zRemRangeByScore('key', 0, 3); // 2
     * </pre>
     */
    public function zRemRangeByScore($key, $start, $end)
    {
    }

    /**
     * @see zRemRangeByScore()
     * @deprecated use Redis::zRemRangeByScore()
     *
     * @param string $key
     * @param float  $start
     * @param float  $end
     */
    public function zDeleteRangeByScore($key, $start, $end)
    {
    }

    /**
     * Deletes the elements of the sorted set stored at the specified key which have rank in the range [start,end].
     *
     * @param string $key
     * @param int    $start
     * @param int    $end
     *
     * @return int The number of values deleted from the sorted set
     *
     * @link    https://redis.io/commands/zremrangebyrank
     * @example
     * <pre>
     * $redis->zAdd('key', 1, 'one');
     * $redis->zAdd('key', 2, 'two');
     * $redis->zAdd('key', 3, 'three');
     * $redis->zRemRangeByRank('key', 0, 1); // 2
     * $redis->zRange('key', 0, -1, array('withscores' => TRUE)); // array('three' => 3)
     * </pre>
     */
    public function zRemRangeByRank($key, $start, $end)
    {
    }

    /**
     * @see zRemRangeByRank()
     * @link    https://redis.io/commands/zremrangebyscore
     * @deprecated use Redis::zRemRangeByRank()
     *
     * @param string $key
     * @param int    $start
     * @param int    $end
     */
    public function zDeleteRangeByRank($key, $start, $end)
    {
    }

    /**
     * Returns the cardinality of an ordered set.
     *
     * @param string $key
     *
     * @return int the set's cardinality
     *
     * @link    https://redis.io/commands/zsize
     * @example
     * <pre>
     * $redis->zAdd('key', 0, 'val0');
     * $redis->zAdd('key', 2, 'val2');
     * $redis->zAdd('key', 10, 'val10');
     * $redis->zCard('key');            // 3
     * </pre>
     */
    public function zCard($key)
    {
    }

    /**
     * @see zCard()
     * @deprecated use Redis::zCard()
     *
     * @param string $key
     * @return int
     */
    public function zSize($key)
    {
    }

    /**
     * Returns the score of a given member in the specified sorted set.
     *
     * @param string       $key
     * @param string|mixed $member
     *
     * @return float|bool false if member or key not exists
     *
     * @link    https://redis.io/commands/zscore
     * @example
     * <pre>
     * $redis->zAdd('key', 2.5, 'val2');
     * $redis->zScore('key', 'val2'); // 2.5
     * </pre>
     */
    public function zScore($key, $member)
    {
    }

    /**
     * Returns the rank of a given member in the specified sorted set, starting at 0 for the item
     * with the smallest score. zRevRank starts at 0 for the item with the largest score.
     *
     * @param string       $key
     * @param string|mixed $member
     *
     * @return int|bool the item's score, or false if key or member is not exists
     *
     * @link    https://redis.io/commands/zrank
     * @example
     * <pre>
     * $redis->del('z');
     * $redis->zAdd('key', 1, 'one');
     * $redis->zAdd('key', 2, 'two');
     * $redis->zRank('key', 'one');     // 0
     * $redis->zRank('key', 'two');     // 1
     * $redis->zRevRank('key', 'one');  // 1
     * $redis->zRevRank('key', 'two');  // 0
     * </pre>
     */
    public function zRank($key, $member)
    {
    }

    /**
     * @see zRank()
     * @param string       $key
     * @param string|mixed $member
     *
     * @return int|bool the item's score, false - if key or member is not exists
     *
     * @link   https://redis.io/commands/zrevrank
     */
    public function zRevRank($key, $member)
    {
    }

    /**
     * Increments the score of a member from a sorted set by a given amount.
     *
     * @param string $key
     * @param float  $value (double) value that will be added to the member's score
     * @param string $member
     *
     * @return float the new value
     *
     * @link    https://redis.io/commands/zincrby
     * @example
     * <pre>
     * $redis->del('key');
     * $redis->zIncrBy('key', 2.5, 'member1');  // key or member1 didn't exist, so member1's score is to 0
     *                                          // before the increment and now has the value 2.5
     * $redis->zIncrBy('key', 1, 'member1');    // 3.5
     * </pre>
     */
    public function zIncrBy($key, $value, $member)
    {
    }

    /**
     * Creates an union of sorted sets given in second argument.
     * The result of the union will be stored in the sorted set defined by the first argument.
     * The third optionnel argument defines weights to apply to the sorted sets in input.
     * In this case, the weights will be multiplied by the score of each element in the sorted set
     * before applying the aggregation. The forth argument defines the AGGREGATE option which
     * specify how the results of the union are aggregated.
     *
     * @param string $output
     * @param array  $zSetKeys
     * @param array  $weights
     * @param string $aggregateFunction  Either "SUM", "MIN", or "MAX": defines the behaviour to use on
     * duplicate entries during the zUnionStore
     *
     * @return int The number of values in the new sorted set
     *
     * @link    https://redis.io/commands/zunionstore
     * @example
     * <pre>
     * $redis->del('k1');
     * $redis->del('k2');
     * $redis->del('k3');
     * $redis->del('ko1');
     * $redis->del('ko2');
     * $redis->del('ko3');
     *
     * $redis->zAdd('k1', 0, 'val0');
     * $redis->zAdd('k1', 1, 'val1');
     *
     * $redis->zAdd('k2', 2, 'val2');
     * $redis->zAdd('k2', 3, 'val3');
     *
     * $redis->zUnionStore('ko1', array('k1', 'k2')); // 4, 'ko1' => array('val0', 'val1', 'val2', 'val3')
     *
     * // Weighted zUnionStore
     * $redis->zUnionStore('ko2', array('k1', 'k2'), array(1, 1)); // 4, 'ko2' => array('val0', 'val1', 'val2', 'val3')
     * $redis->zUnionStore('ko3', array('k1', 'k2'), array(5, 1)); // 4, 'ko3' => array('val0', 'val2', 'val3', 'val1')
     * </pre>
     */
    public function zUnionStore($output, $zSetKeys, array $weights = null, $aggregateFunction = 'SUM')
    {
    }

    /**
     * @see zUnionStore
     * @deprecated use Redis::zUnionStore()
     *
     * @param string     $Output
     * @param array      $ZSetKeys
     * @param array|null $Weights
     * @param string     $aggregateFunction
     */
    public function zUnion($Output, $ZSetKeys, array $Weights = null, $aggregateFunction = 'SUM')
    {
    }

    /**
     * Creates an intersection of sorted sets given in second argument.
     * The result of the union will be stored in the sorted set defined by the first argument.
     * The third optional argument defines weights to apply to the sorted sets in input.
     * In this case, the weights will be multiplied by the score of each element in the sorted set
     * before applying the aggregation. The forth argument defines the AGGREGATE option which
     * specify how the results of the union are aggregated.
     *
     * @param string $output
     * @param array  $zSetKeys
     * @param array  $weights
     * @param string $aggregateFunction Either "SUM", "MIN", or "MAX":
     * defines the behaviour to use on duplicate entries during the zInterStore.
     *
     * @return int The number of values in the new sorted set.
     *
     * @link    https://redis.io/commands/zinterstore
     * @example
     * <pre>
     * $redis->del('k1');
     * $redis->del('k2');
     * $redis->del('k3');
     *
     * $redis->del('ko1');
     * $redis->del('ko2');
     * $redis->del('ko3');
     * $redis->del('ko4');
     *
     * $redis->zAdd('k1', 0, 'val0');
     * $redis->zAdd('k1', 1, 'val1');
     * $redis->zAdd('k1', 3, 'val3');
     *
     * $redis->zAdd('k2', 2, 'val1');
     * $redis->zAdd('k2', 3, 'val3');
     *
     * $redis->zInterStore('ko1', array('k1', 'k2'));               // 2, 'ko1' => array('val1', 'val3')
     * $redis->zInterStore('ko2', array('k1', 'k2'), array(1, 1));  // 2, 'ko2' => array('val1', 'val3')
     *
     * // Weighted zInterStore
     * $redis->zInterStore('ko3', array('k1', 'k2'), array(1, 5), 'min'); // 2, 'ko3' => array('val1', 'val3')
     * $redis->zInterStore('ko4', array('k1', 'k2'), array(1, 5), 'max'); // 2, 'ko4' => array('val3', 'val1')
     * </pre>
     */
    public function zInterStore($output, $zSetKeys, array $weights = null, $aggregateFunction = 'SUM')
    {
    }

    /**
     * @see zInterStore
     * @deprecated use Redis::zInterStore()
     *
     * @param $Output
     * @param $ZSetKeys
     * @param array|null $Weights
     * @param string $aggregateFunction
     */
    public function zInter($Output, $ZSetKeys, array $Weights = null, $aggregateFunction = 'SUM')
    {
    }

    /**
     * Scan a sorted set for members, with optional pattern and count
     *
     * @param string $key      String, the set to scan.
     * @param int    $iterator Long (reference), initialized to NULL.
     * @param string $pattern  String (optional), the pattern to match.
     * @param int    $count    How many keys to return per iteration (Redis might return a different number).
     *
     * @return array|bool PHPRedis will return matching keys from Redis, or FALSE when iteration is complete
     *
     * @link    https://redis.io/commands/zscan
     * @example
     * <pre>
     * $iterator = null;
     * while ($members = $redis-zscan('zset', $iterator)) {
     *     foreach ($members as $member => $score) {
     *         echo $member . ' => ' . $score . PHP_EOL;
     *     }
     * }
     * </pre>
     */
    public function zScan($key, &$iterator, $pattern = null, $count = 0)
    {
    }

    /**
     * Block until Redis can pop the highest or lowest scoring members from one or more ZSETs.
     * There are two commands (BZPOPMIN and BZPOPMAX for popping the lowest and highest scoring elements respectively.)
     *
     * @param string|array $key1
     * @param string|array $key2 ...
     * @param int $timeout
     *
     * @return array Either an array with the key member and score of the highest or lowest element or an empty array
     * if the timeout was reached without an element to pop.
     *
     * @since >= 5.0
     * @link https://redis.io/commands/bzpopmax
     * @example
     * <pre>
     * // Wait up to 5 seconds to pop the *lowest* scoring member from sets `zs1` and `zs2`.
     * $redis->bzPopMin(['zs1', 'zs2'], 5);
     * $redis->bzPopMin('zs1', 'zs2', 5);
     *
     * // Wait up to 5 seconds to pop the *highest* scoring member from sets `zs1` and `zs2`
     * $redis->bzPopMax(['zs1', 'zs2'], 5);
     * $redis->bzPopMax('zs1', 'zs2', 5);
     * </pre>
     */
    public function bzPopMax($key1, $key2, $timeout)
    {
    }

    /**
     * @param string|array $key1
     * @param string|array $key2 ...
     * @param int $timeout
     *
     * @return array Either an array with the key member and score of the highest or lowest element or an empty array
     * if the timeout was reached without an element to pop.
     *
     * @see bzPopMax
     * @since >= 5.0
     * @link https://redis.io/commands/bzpopmin
     */
    public function bzPopMin($key1, $key2, $timeout)
    {
    }

    /**
     * Can pop the highest scoring members from one ZSET.
     *
     * @param string $key
     * @param int $count
     *
     * @return array Either an array with the key member and score of the highest element or an empty array
     * if there is no element to pop.
     *
     * @since >= 5.0
     * @link https://redis.io/commands/zpopmax
     * @example
     * <pre>
     * // Pop the *lowest* scoring member from set `zs1`.
     * $redis->zPopMax('zs1');
     * // Pop the *lowest* 3 scoring member from set `zs1`.
     * $redis->zPopMax('zs1', 3);
     * </pre>
     */
    public function zPopMax($key, $count = 1)
    {
    }

    /**
     * Can pop the lowest scoring members from one ZSET.
     *
     * @param string $key
     * @param int $count
     *
     * @return array Either an array with the key member and score of the lowest element or an empty array
     * if there is no element to pop.
     *
     * @since >= 5.0
     * @link https://redis.io/commands/zpopmin
     * @example
     * <pre>
     * // Pop the *lowest* scoring member from set `zs1`.
     * $redis->zPopMin('zs1');
     * // Pop the *lowest* 3 scoring member from set `zs1`.
     * $redis->zPopMin('zs1', 3);
     * </pre>
     */
    public function zPopMin($key, $count = 1)
    {
    }

    /**
     * Adds a value to the hash stored at key. If this value is already in the hash, FALSE is returned.
     *
     * @param string $key
     * @param string $hashKey
     * @param string $value
     *
     * @return int|bool
     * - 1 if value didn't exist and was added successfully,
     * - 0 if the value was already present and was replaced, FALSE if there was an error.
     *
     * @link    https://redis.io/commands/hset
     * @example
     * <pre>
     * $redis->del('h')
     * $redis->hSet('h', 'key1', 'hello');  // 1, 'key1' => 'hello' in the hash at "h"
     * $redis->hGet('h', 'key1');           // returns "hello"
     *
     * $redis->hSet('h', 'key1', 'plop');   // 0, value was replaced.
     * $redis->hGet('h', 'key1');           // returns "plop"
     * </pre>
     */
    public function hSet($key, $hashKey, $value)
    {
    }

    /**
     * Adds a value to the hash stored at key only if this field isn't already in the hash.
     *
     * @param string $key
     * @param string $hashKey
     * @param string $value
     *
     * @return  bool TRUE if the field was set, FALSE if it was already present.
     *
     * @link    https://redis.io/commands/hsetnx
     * @example
     * <pre>
     * $redis->del('h')
     * $redis->hSetNx('h', 'key1', 'hello'); // TRUE, 'key1' => 'hello' in the hash at "h"
     * $redis->hSetNx('h', 'key1', 'world'); // FALSE, 'key1' => 'hello' in the hash at "h". No change since the field
     * wasn't replaced.
     * </pre>
     */
    public function hSetNx($key, $hashKey, $value)
    {
    }

    /**
     * Gets a value from the hash stored at key.
     * If the hash table doesn't exist, or the key doesn't exist, FALSE is returned.
     *
     * @param string $key
     * @param string $hashKey
     *
     * @return string The value, if the command executed successfully BOOL FALSE in case of failure
     *
     * @link    https://redis.io/commands/hget
     */
    public function hGet($key, $hashKey)
    {
    }

    /**
     * Returns the length of a hash, in number of items
     *
     * @param string $key
     *
     * @return int|bool the number of items in a hash, FALSE if the key doesn't exist or isn't a hash
     *
     * @link    https://redis.io/commands/hlen
     * @example
     * <pre>
     * $redis->del('h')
     * $redis->hSet('h', 'key1', 'hello');
     * $redis->hSet('h', 'key2', 'plop');
     * $redis->hLen('h'); // returns 2
     * </pre>
     */
    public function hLen($key)
    {
    }

    /**
     * Removes a values from the hash stored at key.
     * If the hash table doesn't exist, or the key doesn't exist, FALSE is returned.
     *
     * @param string $key
     * @param string $hashKey1
     * @param string ...$otherHashKeys
     *
     * @return int|bool Number of deleted fields
     *
     * @link    https://redis.io/commands/hdel
     * @example
     * <pre>
     * $redis->hMSet('h',
     *               array(
     *                    'f1' => 'v1',
     *                    'f2' => 'v2',
     *                    'f3' => 'v3',
     *                    'f4' => 'v4',
     *               ));
     *
     * var_dump( $redis->hDel('h', 'f1') );        // int(1)
     * var_dump( $redis->hDel('h', 'f2', 'f3') );  // int(2)
     * s
     * var_dump( $redis->hGetAll('h') );
     * //// Output:
     * //  array(1) {
     * //    ["f4"]=> string(2) "v4"
     * //  }
     * </pre>
     */
    public function hDel($key, $hashKey1, ...$otherHashKeys)
    {
    }

    /**
     * Returns the keys in a hash, as an array of strings.
     *
     * @param string $key
     *
     * @return array An array of elements, the keys of the hash. This works like PHP's array_keys().
     *
     * @link    https://redis.io/commands/hkeys
     * @example
     * <pre>
     * $redis->del('h');
     * $redis->hSet('h', 'a', 'x');
     * $redis->hSet('h', 'b', 'y');
     * $redis->hSet('h', 'c', 'z');
     * $redis->hSet('h', 'd', 't');
     * var_dump($redis->hKeys('h'));
     *
     * // Output:
     * // array(4) {
     * // [0]=>
     * // string(1) "a"
     * // [1]=>
     * // string(1) "b"
     * // [2]=>
     * // string(1) "c"
     * // [3]=>
     * // string(1) "d"
     * // }
     * // The order is random and corresponds to redis' own internal representation of the set structure.
     * </pre>
     */
    public function hKeys($key)
    {
    }

    /**
     * Returns the values in a hash, as an array of strings.
     *
     * @param string $key
     *
     * @return array An array of elements, the values of the hash. This works like PHP's array_values().
     *
     * @link    https://redis.io/commands/hvals
     * @example
     * <pre>
     * $redis->del('h');
     * $redis->hSet('h', 'a', 'x');
     * $redis->hSet('h', 'b', 'y');
     * $redis->hSet('h', 'c', 'z');
     * $redis->hSet('h', 'd', 't');
     * var_dump($redis->hVals('h'));
     *
     * // Output
     * // array(4) {
     * //   [0]=>
     * //   string(1) "x"
     * //   [1]=>
     * //   string(1) "y"
     * //   [2]=>
     * //   string(1) "z"
     * //   [3]=>
     * //   string(1) "t"
     * // }
     * // The order is random and corresponds to redis' own internal representation of the set structure.
     * </pre>
     */
    public function hVals($key)
    {
    }

    /**
     * Returns the whole hash, as an array of strings indexed by strings.
     *
     * @param string $key
     *
     * @return array An array of elements, the contents of the hash.
     *
     * @link    https://redis.io/commands/hgetall
     * @example
     * <pre>
     * $redis->del('h');
     * $redis->hSet('h', 'a', 'x');
     * $redis->hSet('h', 'b', 'y');
     * $redis->hSet('h', 'c', 'z');
     * $redis->hSet('h', 'd', 't');
     * var_dump($redis->hGetAll('h'));
     *
     * // Output:
     * // array(4) {
     * //   ["a"]=>
     * //   string(1) "x"
     * //   ["b"]=>
     * //   string(1) "y"
     * //   ["c"]=>
     * //   string(1) "z"
     * //   ["d"]=>
     * //   string(1) "t"
     * // }
     * // The order is random and corresponds to redis' own internal representation of the set structure.
     * </pre>
     */
    public function hGetAll($key)
    {
    }

    /**
     * Verify if the specified member exists in a key.
     *
     * @param string $key
     * @param string $hashKey
     *
     * @return bool If the member exists in the hash table, return TRUE, otherwise return FALSE.
     *
     * @link    https://redis.io/commands/hexists
     * @example
     * <pre>
     * $redis->hSet('h', 'a', 'x');
     * $redis->hExists('h', 'a');               //  TRUE
     * $redis->hExists('h', 'NonExistingKey');  // FALSE
     * </pre>
     */
    public function hExists($key, $hashKey)
    {
    }

    /**
     * Increments the value of a member from a hash by a given amount.
     *
     * @param string $key
     * @param string $hashKey
     * @param int    $value (integer) value that will be added to the member's value
     *
     * @return int the new value
     *
     * @link    https://redis.io/commands/hincrby
     * @example
     * <pre>
     * $redis->del('h');
     * $redis->hIncrBy('h', 'x', 2); // returns 2: h[x] = 2 now.
     * $redis->hIncrBy('h', 'x', 1); // h[x] ← 2 + 1. Returns 3
     * </pre>
     */
    public function hIncrBy($key, $hashKey, $value)
    {
    }

    /**
     * Increment the float value of a hash field by the given amount
     *
     * @param string $key
     * @param string $field
     * @param float  $increment
     *
     * @return float
     *
     * @link    https://redis.io/commands/hincrbyfloat
     * @example
     * <pre>
     * $redis = new Redis();
     * $redis->connect('127.0.0.1');
     * $redis->hset('h', 'float', 3);
     * $redis->hset('h', 'int',   3);
     * var_dump( $redis->hIncrByFloat('h', 'float', 1.5) ); // float(4.5)
     *
     * var_dump( $redis->hGetAll('h') );
     *
     * // Output
     *  array(2) {
     *    ["float"]=>
     *    string(3) "4.5"
     *    ["int"]=>
     *    string(1) "3"
     *  }
     * </pre>
     */
    public function hIncrByFloat($key, $field, $increment)
    {
    }

    /**
     * Fills in a whole hash. Non-string values are converted to string, using the standard (string) cast.
     * NULL values are stored as empty strings
     *
     * @param string $key
     * @param array  $hashKeys key → value array
     *
     * @return bool
     *
     * @link    https://redis.io/commands/hmset
     * @example
     * <pre>
     * $redis->del('user:1');
     * $redis->hMSet('user:1', array('name' => 'Joe', 'salary' => 2000));
     * $redis->hIncrBy('user:1', 'salary', 100); // Joe earns 100 more now.
     * </pre>
     */
    public function hMSet($key, $hashKeys)
    {
    }

    /**
     * Retirieve the values associated to the specified fields in the hash.
     *
     * @param string $key
     * @param array  $hashKeys
     *
     * @return array Array An array of elements, the values of the specified fields in the hash,
     * with the hash keys as array keys.
     *
     * @link    https://redis.io/commands/hmget
     * @example
     * <pre>
     * $redis->del('h');
     * $redis->hSet('h', 'field1', 'value1');
     * $redis->hSet('h', 'field2', 'value2');
     * $redis->hmGet('h', array('field1', 'field2')); // returns array('field1' => 'value1', 'field2' => 'value2')
     * </pre>
     */
    public function hMGet($key, $hashKeys)
    {
    }

    /**
     * Scan a HASH value for members, with an optional pattern and count.
     *
     * @param string $key
     * @param int    $iterator
     * @param string $pattern    Optional pattern to match against.
     * @param int    $count      How many keys to return in a go (only a sugestion to Redis).
     *
     * @return array An array of members that match our pattern.
     *
     * @link    https://redis.io/commands/hscan
     * @example
     * <pre>
     * // $iterator = null;
     * // while($elements = $redis->hscan('hash', $iterator)) {
     * //     foreach($elements as $key => $value) {
     * //         echo $key . ' => ' . $value . PHP_EOL;
     * //     }
     * // }
     * </pre>
     */
    public function hScan($key, &$iterator, $pattern = null, $count = 0)
    {
    }

    /**
     * Get the string length of the value associated with field in the hash stored at key
     *
     * @param string $key
     * @param string $field
     *
     * @return int the string length of the value associated with field, or zero when field is not present in the hash
     * or key does not exist at all.
     *
     * @link https://redis.io/commands/hstrlen
     * @since >= 3.2
     */
    public function hStrLen(string $key, string $field)
    {
    }

    /**
     * Add one or more geospatial items to the specified key.
     * This function must be called with at least one longitude, latitude, member triplet.
     *
     * @param string $key
     * @param float  $longitude
     * @param float  $latitude
     * @param string $member
     *
     * @return int The number of elements added to the geospatial key
     *
     * @link https://redis.io/commands/geoadd
     * @since >=3.2
     *
     * @example
     * <pre>
     * $redis->del("myplaces");
     *
     * // Since the key will be new, $result will be 2
     * $result = $redis->geoAdd(
     *   "myplaces",
     *   -122.431, 37.773, "San Francisco",
     *   -157.858, 21.315, "Honolulu"
     * ); // 2
     * </pre>
     */
    public function geoadd($key, $longitude, $latitude, $member)
    {
    }

    /**
     * Retrieve Geohash strings for one or more elements of a geospatial index.

     * @param string $key
     * @param string ...$member variadic list of members
     *
     * @return array One or more Redis Geohash encoded strings
     *
     * @link https://redis.io/commands/geohash
     * @since >=3.2
     *
     * @example
     * <pre>
     * $redis->geoAdd("hawaii", -157.858, 21.306, "Honolulu", -156.331, 20.798, "Maui");
     * $hashes = $redis->geoHash("hawaii", "Honolulu", "Maui");
     * var_dump($hashes);
     * // Output: array(2) {
     * //   [0]=>
     * //   string(11) "87z9pyek3y0"
     * //   [1]=>
     * //   string(11) "8e8y6d5jps0"
     * // }
     * </pre>
     */
    public function geohash($key, ...$member)
    {
    }

    /**
     * Return longitude, latitude positions for each requested member.
     *
     * @param string $key
     * @param string $member
     * @return array One or more longitude/latitude positions
     *
     * @link https://redis.io/commands/geopos
     * @since >=3.2
     *
     * @example
     * <pre>
     * $redis->geoAdd("hawaii", -157.858, 21.306, "Honolulu", -156.331, 20.798, "Maui");
     * $positions = $redis->geoPos("hawaii", "Honolulu", "Maui");
     * var_dump($positions);
     *
     * // Output:
     * array(2) {
     *  [0]=> array(2) {
     *      [0]=> string(22) "-157.85800248384475708"
     *      [1]=> string(19) "21.3060004581273077"
     *  }
     *  [1]=> array(2) {
     *      [0]=> string(22) "-156.33099943399429321"
     *      [1]=> string(20) "20.79799924753607598"
     *  }
     * }
     * </pre>
     */
    public function geopos(string $key, string $member)
    {
    }

    /**
     * Return the distance between two members in a geospatial set.
     *
     * If units are passed it must be one of the following values:
     * - 'm' => Meters
     * - 'km' => Kilometers
     * - 'mi' => Miles
     * - 'ft' => Feet
     *
     * @param string $key
     * @param string $member1
     * @param string $member2
     * @param string|null $unit
     *
     * @return float The distance between the two passed members in the units requested (meters by default)
     *
     * @link https://redis.io/commands/geodist
     * @since >=3.2
     *
     * @example
     * <pre>
     * $redis->geoAdd("hawaii", -157.858, 21.306, "Honolulu", -156.331, 20.798, "Maui");
     *
     * $meters = $redis->geoDist("hawaii", "Honolulu", "Maui");
     * $kilometers = $redis->geoDist("hawaii", "Honolulu", "Maui", 'km');
     * $miles = $redis->geoDist("hawaii", "Honolulu", "Maui", 'mi');
     * $feet = $redis->geoDist("hawaii", "Honolulu", "Maui", 'ft');
     *
     * echo "Distance between Honolulu and Maui:\n";
     * echo "  meters    : $meters\n";
     * echo "  kilometers: $kilometers\n";
     * echo "  miles     : $miles\n";
     * echo "  feet      : $feet\n";
     *
     * // Bad unit
     * $inches = $redis->geoDist("hawaii", "Honolulu", "Maui", 'in');
     * echo "Invalid unit returned:\n";
     * var_dump($inches);
     *
     * // Output
     * Distance between Honolulu and Maui:
     * meters    : 168275.204
     * kilometers: 168.2752
     * miles     : 104.5616
     * feet      : 552084.0028
     * Invalid unit returned:
     * bool(false)
     * </pre>
     */
    public function geodist($key, $member1, $member2, $unit = null)
    {
    }

    /**
     * Return members of a set with geospatial information that are within the radius specified by the caller.
     *
     * @param $key
     * @param $longitude
     * @param $latitude
     * @param $radius
     * @param $unit
     * @param array|null $options
     * <pre>
     * |Key         |Value          |Description                                        |
     * |------------|---------------|---------------------------------------------------|
     * |COUNT       |integer > 0    |Limit how many results are returned                |
     * |            |WITHCOORD      |Return longitude and latitude of matching members  |
     * |            |WITHDIST       |Return the distance from the center                |
     * |            |WITHHASH       |Return the raw geohash-encoded score               |
     * |            |ASC            |Sort results in ascending order                    |
     * |            |DESC           |Sort results in descending order                   |
     * |STORE       |key            |Store results in key                               |
     * |STOREDIST   |key            |Store the results as distances in key              |
     * </pre>
     * Note: It doesn't make sense to pass both ASC and DESC options but if both are passed
     * the last one passed will be used.
     * Note: When using STORE[DIST] in Redis Cluster, the store key must has to the same slot as
     * the query key or you will get a CROSSLOT error.
     * @return mixed When no STORE option is passed, this function returns an array of results.
     * If it is passed this function returns the number of stored entries.
     *
     * @link https://redis.io/commands/georadius
     * @since >= 3.2
     * @example
     * <pre>
     * // Add some cities
     * $redis->geoAdd("hawaii", -157.858, 21.306, "Honolulu", -156.331, 20.798, "Maui");
     *
     * echo "Within 300 miles of Honolulu:\n";
     * var_dump($redis->geoRadius("hawaii", -157.858, 21.306, 300, 'mi'));
     *
     * echo "\nWithin 300 miles of Honolulu with distances:\n";
     * $options = ['WITHDIST'];
     * var_dump($redis->geoRadius("hawaii", -157.858, 21.306, 300, 'mi', $options));
     *
     * echo "\nFirst result within 300 miles of Honolulu with distances:\n";
     * $options['count'] = 1;
     * var_dump($redis->geoRadius("hawaii", -157.858, 21.306, 300, 'mi', $options));
     *
     * echo "\nFirst result within 300 miles of Honolulu with distances in descending sort order:\n";
     * $options[] = 'DESC';
     * var_dump($redis->geoRadius("hawaii", -157.858, 21.306, 300, 'mi', $options));
     *
     * // Output
     * Within 300 miles of Honolulu:
     * array(2) {
     *  [0]=> string(8) "Honolulu"
     *  [1]=> string(4) "Maui"
     * }
     *
     * Within 300 miles of Honolulu with distances:
     * array(2) {
     *     [0]=>
     *   array(2) {
     *         [0]=>
     *     string(8) "Honolulu"
     *         [1]=>
     *     string(6) "0.0002"
     *   }
     *   [1]=>
     *   array(2) {
     *         [0]=>
     *     string(4) "Maui"
     *         [1]=>
     *     string(8) "104.5615"
     *   }
     * }
     *
     * First result within 300 miles of Honolulu with distances:
     * array(1) {
     *     [0]=>
     *   array(2) {
     *         [0]=>
     *     string(8) "Honolulu"
     *         [1]=>
     *     string(6) "0.0002"
     *   }
     * }
     *
     * First result within 300 miles of Honolulu with distances in descending sort order:
     * array(1) {
     *     [0]=>
     *   array(2) {
     *         [0]=>
     *     string(4) "Maui"
     *         [1]=>
     *     string(8) "104.5615"
     *   }
     * }
     * </pre>
     */
    public function georadius($key, $longitude, $latitude, $radius, $unit, array $options = null)
    {
    }

    /**
     * This method is identical to geoRadius except that instead of passing a longitude and latitude as the "source"
     * you pass an existing member in the geospatial set
     *
     * @param string $key
     * @param string $member
     * @param $radius
     * @param $units
     * @param array|null $options see georadius
     *
     * @return array The zero or more entries that are close enough to the member given the distance and radius specified
     *
     * @link https://redis.io/commands/georadiusbymember
     * @since >= 3.2
     * @see georadius
     * @example
     * <pre>
     * $redis->geoAdd("hawaii", -157.858, 21.306, "Honolulu", -156.331, 20.798, "Maui");
     *
     * echo "Within 300 miles of Honolulu:\n";
     * var_dump($redis->geoRadiusByMember("hawaii", "Honolulu", 300, 'mi'));
     *
     * echo "\nFirst match within 300 miles of Honolulu:\n";
     * var_dump($redis->geoRadiusByMember("hawaii", "Honolulu", 300, 'mi', ['count' => 1]));
     *
     * // Output
     * Within 300 miles of Honolulu:
     * array(2) {
     *  [0]=> string(8) "Honolulu"
     *  [1]=> string(4) "Maui"
     * }
     *
     * First match within 300 miles of Honolulu:
     * array(1) {
     *  [0]=> string(8) "Honolulu"
     * }
     * </pre>
     */
    public function georadiusbymember($key, $member, $radius, $units, array $options = null)
    {
    }

    /**
     * Get or Set the redis config keys.
     *
     * @param string       $operation either `GET` or `SET`
     * @param string       $key       for `SET`, glob-pattern for `GET`
     * @param string|mixed $value     optional string (only for `SET`)
     *
     * @return array Associative array for `GET`, key -> value
     *
     * @link    https://redis.io/commands/config-get
     * @example
     * <pre>
     * $redis->config("GET", "*max-*-entries*");
     * $redis->config("SET", "dir", "/var/run/redis/dumps/");
     * </pre>
     */
    public function config($operation, $key, $value)
    {
    }

    /**
     * Evaluate a LUA script serverside
     *
     * @param string $script
     * @param array  $args
     * @param int    $numKeys
     *
     * @return mixed What is returned depends on what the LUA script itself returns, which could be a scalar value
     * (int/string), or an array. Arrays that are returned can also contain other arrays, if that's how it was set up in
     * your LUA script.  If there is an error executing the LUA script, the getLastError() function can tell you the
     * message that came back from Redis (e.g. compile error).
     *
     * @link   https://redis.io/commands/eval
     * @example
     * <pre>
     * $redis->eval("return 1"); // Returns an integer: 1
     * $redis->eval("return {1,2,3}"); // Returns Array(1,2,3)
     * $redis->del('mylist');
     * $redis->rpush('mylist','a');
     * $redis->rpush('mylist','b');
     * $redis->rpush('mylist','c');
     * // Nested response:  Array(1,2,3,Array('a','b','c'));
     * $redis->eval("return {1,2,3,redis.call('lrange','mylist',0,-1)}}");
     * </pre>
     */
    public function eval($script, $args = array(), $numKeys = 0)
    {
    }

    /**
     * @see eval()
     * @deprecated use Redis::eval()
     *
     * @param   string  $script
     * @param   array   $args
     * @param   int     $numKeys
     * @return  mixed   @see eval()
     */
    public function evaluate($script, $args = array(), $numKeys = 0)
    {
    }

    /**
     * Evaluate a LUA script serverside, from the SHA1 hash of the script instead of the script itself.
     * In order to run this command Redis will have to have already loaded the script, either by running it or via
     * the SCRIPT LOAD command.
     *
     * @param string $scriptSha
     * @param array  $args
     * @param int    $numKeys
     *
     * @return mixed @see eval()
     *
     * @see     eval()
     * @link    https://redis.io/commands/evalsha
     * @example
     * <pre>
     * $script = 'return 1';
     * $sha = $redis->script('load', $script);
     * $redis->evalSha($sha); // Returns 1
     * </pre>
     */
    public function evalSha($scriptSha, $args = array(), $numKeys = 0)
    {
    }

    /**
     * @see evalSha()
     * @deprecated use Redis::evalSha()
     *
     * @param string $scriptSha
     * @param array  $args
     * @param int    $numKeys
     */
    public function evaluateSha($scriptSha, $args = array(), $numKeys = 0)
    {
    }

    /**
     * Execute the Redis SCRIPT command to perform various operations on the scripting subsystem.
     * @param string $command load | flush | kill | exists
     * @param string $script
     *
     * @return  mixed
     *
     * @link    https://redis.io/commands/script-load
     * @link    https://redis.io/commands/script-kill
     * @link    https://redis.io/commands/script-flush
     * @link    https://redis.io/commands/script-exists
     * @example
     * <pre>
     * $redis->script('load', $script);
     * $redis->script('flush');
     * $redis->script('kill');
     * $redis->script('exists', $script1, [$script2, $script3, ...]);
     * </pre>
     *
     * SCRIPT LOAD will return the SHA1 hash of the passed script on success, and FALSE on failure.
     * SCRIPT FLUSH should always return TRUE
     * SCRIPT KILL will return true if a script was able to be killed and false if not
     * SCRIPT EXISTS will return an array with TRUE or FALSE for each passed script
     */
    public function script($command, $script)
    {
    }

    /**
     * The last error message (if any)
     *
     * @return string|null A string with the last returned script based error message, or NULL if there is no error
     *
     * @example
     * <pre>
     * $redis->eval('this-is-not-lua');
     * $err = $redis->getLastError();
     * // "ERR Error compiling script (new function): user_script:1: '=' expected near '-'"
     * </pre>
     */
    public function getLastError()
    {
    }

    /**
     * Clear the last error message
     *
     * @return bool true
     *
     * @example
     * <pre>
     * $redis->set('x', 'a');
     * $redis->incr('x');
     * $err = $redis->getLastError();
     * // "ERR value is not an integer or out of range"
     * $redis->clearLastError();
     * $err = $redis->getLastError();
     * // NULL
     * </pre>
     */
    public function clearLastError()
    {
    }

    /**
     * Issue the CLIENT command with various arguments.
     * The Redis CLIENT command can be used in four ways:
     * - CLIENT LIST
     * - CLIENT GETNAME
     * - CLIENT SETNAME [name]
     * - CLIENT KILL [ip:port]
     *
     * @param string $command
     * @param string $value
     * @return mixed This will vary depending on which client command was executed:
     * - CLIENT LIST will return an array of arrays with client information.
     * - CLIENT GETNAME will return the client name or false if none has been set
     * - CLIENT SETNAME will return true if it can be set and false if not
     * - CLIENT KILL will return true if the client can be killed, and false if not
     *
     * Note: phpredis will attempt to reconnect so you can actually kill your own connection but may not notice losing it!
     *
     * @link https://redis.io/commands/client-list
     * @link https://redis.io/commands/client-getname
     * @link https://redis.io/commands/client-setname
     * @link https://redis.io/commands/client-kill
     *
     * @example
     * <pre>
     * $redis->client('list'); // Get a list of clients
     * $redis->client('getname'); // Get the name of the current connection
     * $redis->client('setname', 'somename'); // Set the name of the current connection
     * $redis->client('kill', <ip:port>); // Kill the process at ip:port
     * </pre>
     */
    public function client($command, $value = '')
    {
    }

    /**
     * A utility method to prefix the value with the prefix setting for phpredis.
     *
     * @param mixed $value The value you wish to prefix
     *
     * @return string If a prefix is set up, the value now prefixed.
     * If there is no prefix, the value will be returned unchanged.
     *
     * @example
     * <pre>
     * $redis->setOption(Redis::OPT_PREFIX, 'my-prefix:');
     * $redis->_prefix('my-value'); // Will return 'my-prefix:my-value'
     * </pre>
     */
    public function _prefix($value)
    {
    }

    /**
     * A utility method to unserialize data with whatever serializer is set up.  If there is no serializer set, the
     * value will be returned unchanged.  If there is a serializer set up, and the data passed in is malformed, an
     * exception will be thrown. This can be useful if phpredis is serializing values, and you return something from
     * redis in a LUA script that is serialized.
     *
     * @param string $value The value to be unserialized
     *
     * @return mixed
     * @example
     * <pre>
     * $redis->setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_PHP);
     * $redis->_unserialize('a:3:{i:0;i:1;i:1;i:2;i:2;i:3;}'); // Will return Array(1,2,3)
     * </pre>
     */
    public function _unserialize($value)
    {
    }

    /**
     * A utility method to serialize values manually. This method allows you to serialize a value with whatever
     * serializer is configured, manually. This can be useful for serialization/unserialization of data going in
     * and out of EVAL commands as phpredis can't automatically do this itself.  Note that if no serializer is
     * set, phpredis will change Array values to 'Array', and Objects to 'Object'.
     *
     * @param mixed $value The value to be serialized.
     *
     * @return  mixed
     * @example
     * <pre>
     * $redis->setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_NONE);
     * $redis->_serialize("foo"); // returns "foo"
     * $redis->_serialize(Array()); // Returns "Array"
     * $redis->_serialize(new stdClass()); // Returns "Object"
     *
     * $redis->setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_PHP);
     * $redis->_serialize("foo"); // Returns 's:3:"foo";'
     * </pre>
     */
    public function _serialize($value)
    {
    }

    /**
     * Dump a key out of a redis database, the value of which can later be passed into redis using the RESTORE command.
     * The data that comes out of DUMP is a binary representation of the key as Redis stores it.
     * @param string $key
     *
     * @return string|bool The Redis encoded value of the key, or FALSE if the key doesn't exist
     *
     * @link    https://redis.io/commands/dump
     * @example
     * <pre>
     * $redis->set('foo', 'bar');
     * $val = $redis->dump('foo'); // $val will be the Redis encoded key value
     * </pre>
     */
    public function dump($key)
    {
    }

    /**
     * Restore a key from the result of a DUMP operation.
     *
     * @param string $key   The key name
     * @param int    $ttl   How long the key should live (if zero, no expire will be set on the key)
     * @param string $value (binary).  The Redis encoded key value (from DUMP)
     *
     * @return bool
     *
     * @link    https://redis.io/commands/restore
     * @example
     * <pre>
     * $redis->set('foo', 'bar');
     * $val = $redis->dump('foo');
     * $redis->restore('bar', 0, $val); // The key 'bar', will now be equal to the key 'foo'
     * </pre>
     */
    public function restore($key, $ttl, $value)
    {
    }

    /**
     * Migrates a key to a different Redis instance.
     *
     * @param string $host    The destination host
     * @param int    $port    The TCP port to connect to.
     * @param string $key     The key to migrate.
     * @param int    $db      The target DB.
     * @param int    $timeout The maximum amount of time given to this transfer.
     * @param bool   $copy    Should we send the COPY flag to redis.
     * @param bool   $replace Should we send the REPLACE flag to redis.
     *
     * @return bool
     *
     * @link    https://redis.io/commands/migrate
     * @example
     * <pre>
     * $redis->migrate('backup', 6379, 'foo', 0, 3600);
     * </pre>
     */
    public function migrate($host, $port, $key, $db, $timeout, $copy = false, $replace = false)
    {
    }

    /**
     * Return the current Redis server time.
     *
     * @return array If successful, the time will come back as an associative array with element zero being the
     * unix timestamp, and element one being microseconds.
     *
     * @link    https://redis.io/commands/time
     * @example
     * <pre>
     * var_dump( $redis->time() );
     * // array(2) {
     * //   [0] => string(10) "1342364352"
     * //   [1] => string(6) "253002"
     * // }
     * </pre>
     */
    public function time()
    {
    }

    /**
     * Scan the keyspace for keys
     *
     * @param int    $iterator Iterator, initialized to NULL.
     * @param string $pattern  Pattern to match.
     * @param int    $count    Count of keys per iteration (only a suggestion to Redis).
     *
     * @return array|bool This function will return an array of keys or FALSE if there are no more keys.
     *
     * @link   https://redis.io/commands/scan
     * @example
     * <pre>
     * $iterator = null;
     * while(false !== ($keys = $redis->scan($iterator))) {
     *     foreach($keys as $key) {
     *         echo $key . PHP_EOL;
     *     }
     * }
     * </pre>
     */
    public function scan(&$iterator, $pattern = null, $count = 0)
    {
    }

    /**
     * Adds all the element arguments to the HyperLogLog data structure stored at the key.
     *
     * @param string $key
     * @param array  $elements
     *
     * @return bool
     *
     * @link    https://redis.io/commands/pfadd
     * @example $redis->pfAdd('key', array('elem1', 'elem2'))
     */
    public function pfAdd($key, array $elements)
    {
    }

    /**
     * When called with a single key, returns the approximated cardinality computed by the HyperLogLog data
     * structure stored at the specified variable, which is 0 if the variable does not exist.
     *
     * @param string|array $key
     *
     * @return int
     *
     * @link    https://redis.io/commands/pfcount
     * @example
     * <pre>
     * $redis->pfAdd('key1', array('elem1', 'elem2'));
     * $redis->pfAdd('key2', array('elem3', 'elem2'));
     * $redis->pfCount('key1'); // int(2)
     * $redis->pfCount(array('key1', 'key2')); // int(3)
     */
    public function pfCount($key)
    {
    }

    /**
     * Merge multiple HyperLogLog values into an unique value that will approximate the cardinality
     * of the union of the observed Sets of the source HyperLogLog structures.
     *
     * @param string $destKey
     * @param array  $sourceKeys
     *
     * @return bool
     *
     * @link    https://redis.io/commands/pfmerge
     * @example
     * <pre>
     * $redis->pfAdd('key1', array('elem1', 'elem2'));
     * $redis->pfAdd('key2', array('elem3', 'elem2'));
     * $redis->pfMerge('key3', array('key1', 'key2'));
     * $redis->pfCount('key3'); // int(3)
     */
    public function pfMerge($destKey, array $sourceKeys)
    {
    }

    /**
     * Send arbitrary things to the redis server.
     *
     * @param string $command   Required command to send to the server.
     * @param mixed  $arguments Optional variable amount of arguments to send to the server.
     *
     * @return mixed
     *
     * @example
     * <pre>
     * $redis->rawCommand('SET', 'key', 'value'); // bool(true)
     * $redis->rawCommand('GET", 'key'); // string(5) "value"
     * </pre>
     */
    public function rawCommand($command, $arguments)
    {
    }

    /**
     * Detect whether we're in ATOMIC/MULTI/PIPELINE mode.
     *
     * @return int Either Redis::ATOMIC, Redis::MULTI or Redis::PIPELINE
     *
     * @example $redis->getMode();
     */
    public function getMode()
    {
    }

    /**
     * Acknowledge one or more messages on behalf of a consumer group.
     *
     * @param string $stream
     * @param string $group
     * @param array  $messages
     *
     * @return int The number of messages Redis reports as acknowledged.
     *
     * @link    https://redis.io/commands/xack
     * @example
     * <pre>
     * $redis->xAck('stream', 'group1', ['1530063064286-0', '1530063064286-1']);
     * </pre>
     */
    public function xAck($stream, $group, $messages)
    {
    }

    /**
     * Add a message to a stream
     *
     * @param string $key
     * @param string $id
     * @param array  $messages
     * @param int    $maxLen
     * @param bool   $isApproximate
     *
     * @return string The added message ID.
     *
     * @link    https://redis.io/commands/xadd
     * @example
     * <pre>
     * $redis->xAdd('mystream', "*", ['field' => 'value']);
     * $redis->xAdd('mystream', "*", ['field' => 'value'], 10);
     * $redis->xAdd('mystream', "*", ['field' => 'value'], 10, true);
     * </pre>
     */
    public function xAdd($key, $id, $messages, $maxLen = 0, $isApproximate = false)
    {
    }

    /**
     * Claim ownership of one or more pending messages
     *
     * @param string $key
     * @param string $group
     * @param string $consumer
     * @param int    $minIdleTime
     * @param array  $ids
     * @param array  $options ['IDLE' => $value, 'TIME' => $value, 'RETRYCOUNT' => $value, 'FORCE', 'JUSTID']
     *
     * @return array Either an array of message IDs along with corresponding data, or just an array of IDs
     * (if the 'JUSTID' option was passed).
     *
     * @link    https://redis.io/commands/xclaim
     * @example
     * <pre>
     * $ids = ['1530113681011-0', '1530113681011-1', '1530113681011-2'];
     *
     * // Without any options
     * $redis->xClaim('mystream', 'group1', 'myconsumer1', 0, $ids);
     *
     * // With options
     * $redis->xClaim(
     *     'mystream', 'group1', 'myconsumer2', 0, $ids,
     *     [
     *         'IDLE' => time() * 1000,
     *         'RETRYCOUNT' => 5,
     *         'FORCE',
     *         'JUSTID'
     *     ]
     * );
     * </pre>
     */
    public function xClaim($key, $group, $consumer, $minIdleTime, $ids, $options = [])
    {
    }

    /**
     * Delete one or more messages from a stream
     *
     * @param string $key
     * @param array  $ids
     *
     * @return int The number of messages removed
     *
     * @link    https://redis.io/commands/xdel
     * @example
     * <pre>
     * $redis->xDel('mystream', ['1530115304877-0', '1530115305731-0']);
     * </pre>
     */
    public function xDel($key, $ids)
    {
    }

    /**
     * @param string $operation  e.g.: 'HELP', 'SETID', 'DELGROUP', 'CREATE', 'DELCONSUMER'
     * @param string $key
     * @param string $group
     * @param string $msgId
     * @param bool   $mkStream
     *
     * @return mixed This command returns different types depending on the specific XGROUP command executed.
     *
     * @link    https://redis.io/commands/xgroup
     * @example
     * <pre>
     * $redis->xGroup('CREATE', 'mystream', 'mygroup', 0);
     * $redis->xGroup('CREATE', 'mystream', 'mygroup', 0, true); // create stream
     * $redis->xGroup('DESTROY', 'mystream', 'mygroup');
     * </pre>
     */
    public function xGroup($operation, $key, $group, $msgId = '', $mkStream = false)
    {
    }

    /**
     * Get information about a stream or consumer groups
     *
     * @param string $operation  e.g.: 'CONSUMERS', 'GROUPS', 'STREAM', 'HELP'
     * @param string $stream
     * @param string $group
     *
     * @return mixed This command returns different types depending on which subcommand is used.
     *
     * @link    https://redis.io/commands/xinfo
     * @example
     * <pre>
     * $redis->xInfo('STREAM', 'mystream');
     * </pre>
     */
    public function xInfo($operation, $stream, $group)
    {
    }

    /**
     * Get the length of a given stream.
     *
     * @param string $stream
     *
     * @return int The number of messages in the stream.
     *
     * @link    https://redis.io/commands/xlen
     * @example
     * <pre>
     * $redis->xLen('mystream');
     * </pre>
     */
    public function xLen($stream)
    {
    }

    /**
     * Get information about pending messages in a given stream
     *
     * @param string $stream
     * @param string $group
     * @param string $start
     * @param string $end
     * @param int    $count
     * @param string $consumer
     *
     * @return array Information about the pending messages, in various forms depending on
     * the specific invocation of XPENDING.
     *
     * @link https://redis.io/commands/xpending
     * @example
     * <pre>
     * $redis->xPending('mystream', 'mygroup');
     * $redis->xPending('mystream', 'mygroup', '-', '+', 1, 'consumer-1');
     * </pre>
     */
    public function xPending($stream, $group, $start = null, $end = null, $count = null, $consumer = null)
    {
    }

    /**
     * Get a range of messages from a given stream
     *
     * @param string $stream
     * @param string $start
     * @param string $end
     * @param int    $count
     *
     * @return array The messages in the stream within the requested range.
     *
     * @link    https://redis.io/commands/xrange
     * @example
     * <pre>
     * // Get everything in this stream
     * $redis->xRange('mystream', '-', '+');
     * // Only the first two messages
     * $redis->xRange('mystream', '-', '+', 2);
     * </pre>
     */
    public function xRange($stream, $start, $end, $count = null)
    {
    }

    /**
     * Read data from one or more streams and only return IDs greater than sent in the command.
     *
     * @param array      $streams
     * @param int|string $count
     * @param int|string $block
     *
     * @return array The messages in the stream newer than the IDs passed to Redis (if any)
     *
     * @link    https://redis.io/commands/xread
     * @example
     * <pre>
     * $redis->xRead(['stream1' => '1535222584555-0', 'stream2' => '1535222584555-0']);
     * </pre>
     */
    public function xRead($streams, $count = null, $block = null)
    {
    }

    /**
     * This method is similar to xRead except that it supports reading messages for a specific consumer group.
     *
     * @param string   $group
     * @param string   $consumer
     * @param array    $streams
     * @param int|null $count
     * @param int|null $block
     *
     * @return array The messages delivered to this consumer group (if any).
     *
     * @link    https://redis.io/commands/xreadgroup
     * @example
     * <pre>
     * // Consume messages for 'mygroup', 'consumer1'
     * $redis->xReadGroup('mygroup', 'consumer1', ['s1' => 0, 's2' => 0]);
     * // Read a single message as 'consumer2' for up to a second until a message arrives.
     * $redis->xReadGroup('mygroup', 'consumer2', ['s1' => 0, 's2' => 0], 1, 1000);
     * </pre>
     */
    public function xReadGroup($group, $consumer, $streams, $count = null, $block = null)
    {
    }

    /**
     * This is identical to xRange except the results come back in reverse order.
     * Also note that Redis reverses the order of "start" and "end".
     *
     * @param string $stream
     * @param string $end
     * @param string $start
     * @param int    $count
     *
     * @return array The messages in the range specified
     *
     * @link    https://redis.io/commands/xrevrange
     * @example
     * <pre>
     * $redis->xRevRange('mystream', '+', '-');
     * </pre>
     */
    public function xRevRange($stream, $end, $start, $count = null)
    {
    }

    /**
     * Trim the stream length to a given maximum.
     * If the "approximate" flag is pasesed, Redis will use your size as a hint but only trim trees in whole nodes
     * (this is more efficient)
     *
     * @param string $stream
     * @param int    $maxLen
     * @param bool   $isApproximate
     *
     * @return int The number of messages trimed from the stream.
     *
     * @link    https://redis.io/commands/xtrim
     * @example
     * <pre>
     * // Trim to exactly 100 messages
     * $redis->xTrim('mystream', 100);
     * // Let Redis approximate the trimming
     * $redis->xTrim('mystream', 100, true);
     * </pre>
     */
    public function xTrim($stream, $maxLen, $isApproximate)
    {
    }

    /**
     * Adds a values to the set value stored at key.
     *
     * @param string $key Required key
     * @param array  $values Required values
     *
     * @return  int|bool The number of elements added to the set.
     * If this value is already in the set, FALSE is returned
     *
     * @link    https://redis.io/commands/sadd
     * @link    https://github.com/phpredis/phpredis/commit/3491b188e0022f75b938738f7542603c7aae9077
     * @since   phpredis 2.2.8
     * @example
     * <pre>
     * $redis->sAddArray('k', array('v1'));                // boolean
     * $redis->sAddArray('k', array('v1', 'v2', 'v3'));    // boolean
     * </pre>
     */
    public function sAddArray($key, array $values)
    {
    }
}

class RedisException extends Exception
{
}

/**
 * @mixin \Redis
 */
class RedisArray
{
    /**
     * Constructor
     *
     * @param string|array $hosts Name of the redis array from redis.ini or array of hosts to construct the array with
     * @param array        $opts  Array of options
     *
     * @link    https://github.com/nicolasff/phpredis/blob/master/arrays.markdown
     */
    public function __construct($hosts, array $opts = null)
    {
    }

    /**
     * @return array list of hosts for the selected array
     */
    public function _hosts()
    {
    }

    /**
     * @return string the name of the function used to extract key parts during consistent hashing
     */
    public function _function()
    {
    }

    /**
     * @param string $key The key for which you want to lookup the host
     *
     * @return  string  the host to be used for a certain key
     */
    public function _target($key)
    {
    }

    /**
     * Use this function when a new node is added and keys need to be rehashed.
     */
    public function _rehash()
    {
    }

    /**
     * Returns an associative array of strings and integers, with the following keys:
     * - redis_version
     * - redis_git_sha1
     * - redis_git_dirty
     * - redis_build_id
     * - redis_mode
     * - os
     * - arch_bits
     * - multiplexing_api
     * - atomicvar_api
     * - gcc_version
     * - process_id
     * - run_id
     * - tcp_port
     * - uptime_in_seconds
     * - uptime_in_days
     * - hz
     * - lru_clock
     * - executable
     * - config_file
     * - connected_clients
     * - client_longest_output_list
     * - client_biggest_input_buf
     * - blocked_clients
     * - used_memory
     * - used_memory_human
     * - used_memory_rss
     * - used_memory_rss_human
     * - used_memory_peak
     * - used_memory_peak_human
     * - used_memory_peak_perc
     * - used_memory_peak
     * - used_memory_overhead
     * - used_memory_startup
     * - used_memory_dataset
     * - used_memory_dataset_perc
     * - total_system_memory
     * - total_system_memory_human
     * - used_memory_lua
     * - used_memory_lua_human
     * - maxmemory
     * - maxmemory_human
     * - maxmemory_policy
     * - mem_fragmentation_ratio
     * - mem_allocator
     * - active_defrag_running
     * - lazyfree_pending_objects
     * - mem_fragmentation_ratio
     * - loading
     * - rdb_changes_since_last_save
     * - rdb_bgsave_in_progress
     * - rdb_last_save_time
     * - rdb_last_bgsave_status
     * - rdb_last_bgsave_time_sec
     * - rdb_current_bgsave_time_sec
     * - rdb_last_cow_size
     * - aof_enabled
     * - aof_rewrite_in_progress
     * - aof_rewrite_scheduled
     * - aof_last_rewrite_time_sec
     * - aof_current_rewrite_time_sec
     * - aof_last_bgrewrite_status
     * - aof_last_write_status
     * - aof_last_cow_size
     * - changes_since_last_save
     * - aof_current_size
     * - aof_base_size
     * - aof_pending_rewrite
     * - aof_buffer_length
     * - aof_rewrite_buffer_length
     * - aof_pending_bio_fsync
     * - aof_delayed_fsync
     * - loading_start_time
     * - loading_total_bytes
     * - loading_loaded_bytes
     * - loading_loaded_perc
     * - loading_eta_seconds
     * - total_connections_received
     * - total_commands_processed
     * - instantaneous_ops_per_sec
     * - total_net_input_bytes
     * - total_net_output_bytes
     * - instantaneous_input_kbps
     * - instantaneous_output_kbps
     * - rejected_connections
     * - maxclients
     * - sync_full
     * - sync_partial_ok
     * - sync_partial_err
     * - expired_keys
     * - evicted_keys
     * - keyspace_hits
     * - keyspace_misses
     * - pubsub_channels
     * - pubsub_patterns
     * - latest_fork_usec
     * - migrate_cached_sockets
     * - slave_expires_tracked_keys
     * - active_defrag_hits
     * - active_defrag_misses
     * - active_defrag_key_hits
     * - active_defrag_key_misses
     * - role
     * - master_replid
     * - master_replid2
     * - master_repl_offset
     * - second_repl_offset
     * - repl_backlog_active
     * - repl_backlog_size
     * - repl_backlog_first_byte_offset
     * - repl_backlog_histlen
     * - master_host
     * - master_port
     * - master_link_status
     * - master_last_io_seconds_ago
     * - master_sync_in_progress
     * - slave_repl_offset
     * - slave_priority
     * - slave_read_only
     * - master_sync_left_bytes
     * - master_sync_last_io_seconds_ago
     * - master_link_down_since_seconds
     * - connected_slaves
     * - min-slaves-to-write
     * - min-replicas-to-write
     * - min_slaves_good_slaves
     * - used_cpu_sys
     * - used_cpu_user
     * - used_cpu_sys_children
     * - used_cpu_user_children
     * - cluster_enabled
     *
     * @link    https://redis.io/commands/info
     * @return  array
     * @example
     * <pre>
     * $redis->info();
     * </pre>
     */
    public function info() {
    }
}
