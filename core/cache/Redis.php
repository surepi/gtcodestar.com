<?php
/**
 * Redis缓存类
 */
namespace core\cache;

use core\basic\Config;

class Redis implements Builder
{
    protected static $redis;
    protected $conn;

    private function __construct() {}

    private function __clone() {}

    public static function getInstance()
    {
        if (!self::$redis) {
            self::$redis = new self();
        }
        return self::$redis;
    }

    protected function conn()
    {
        if (!$this->conn) {
            $config = Config::get('cache.server');
            $socket = $config['socket'] ?? '/home/gtcodest75/.redis/redis.sock';
            $timeout = $config['timeout'] ?? 1;
            $database = $config['database'] ?? 0;

            $this->conn = new \Redis();
            $this->conn->connect($socket);
            if (isset($config['password']) && $config['password']) {
                $this->conn->auth($config['password']);
            }
            if ($database) {
                $this->conn->select($database);
            }
        }
        return $this->conn;
    }

    public function set($key, $value, $expire = 0)
    {
        $redis = $this->conn();
        $value = serialize($value);
        if ($expire > 0) {
            return $redis->setex($key, $expire, $value);
        } else {
            return $redis->set($key, $value);
        }
    }

    public function get($key)
    {
        $redis = $this->conn();
        $value = $redis->get($key);
        if ($value === false) {
            return null;
        }
        return unserialize($value);
    }

    public function delete($key)
    {
        $redis = $this->conn();
        return $redis->del($key);
    }

    public function flush()
    {
        $redis = $this->conn();
        return $redis->flushDB();
    }

    public function status()
    {
        $redis = $this->conn();
        return $redis->info();
    }
}
