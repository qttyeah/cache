<?php
// +----------------------------------------------------------------------
// | copy-source ThinkPHP
// +----------------------------------------------------------------------
// | Redis缓存
// +----------------------------------------------------------------------

namespace Qttyeah\Cache\driver;

use Qttyeah\Cache\Driver;

class Redis extends Driver
{
    protected $options = [
        'host'       => '127.0.0.1',
        'port'       => 6379,
        'password'   => '',
        'select'     => 0,
        'timeout'    => 0,
        'expire'     => 0,
        'persistent' => false,
        'prefix'     => '',
    ];

    /**
     * 架构函数
     * @access public
     * @param  array $options 缓存参数
     */
    public function __construct($options = [])
    {
        if (!extension_loaded('redis')) {
            throw new \BadFunctionCallException('not support: redis');
        }

        if (!empty($options)) {
            $this->options = array_merge($this->options, $options);
        }

        $func = $this->options['persistent'] ? 'pconnect' : 'connect';

        $this->handler = new \Redis;
        $this->handler->$func($this->options['host'], $this->options['port'], $this->options['timeout']);

        if ('' != $this->options['password']) {
            $this->handler->auth($this->options['password']);
        }

        if (0 != $this->options['select']) {
            $this->handler->select($this->options['select']);
        }
    }

    /**
     * 判断缓存
     * @access public
     * @param  string $name 缓存变量名
     * @return bool
     */
    public function has($name)
    {
        return $this->handler->get($this->getCacheKey($name)) ? true : false;
    }

    /**
     * 读取缓存
     * @access public
     * @param  string $name 缓存变量名
     * @param  mixed  $default 默认值
     * @return mixed
     */
    public function get($name, $default = false)
    {
        $this->readTimes++;

        $value = $this->handler->get($this->getCacheKey($name));

        if (is_null($value) || false === $value) {
            return $default;
        }

        return $this->unserialize($value);
    }

    /**
     * 写入缓存
     * @access public
     * @param  string            $name 缓存变量名
     * @param  mixed             $value  存储数据
     * @param  integer|\DateTime $expire  有效时间（秒）
     * @return boolean
     */
    public function set($name, $value, $expire = null)
    {
        $this->writeTimes++;

        if (is_null($expire)) {
            $expire = $this->options['expire'];
        }

        if ($this->tag && !$this->has($name)) {
            $first = true;
        }

        $key    = $this->getCacheKey($name);
        $expire = $this->getExpireTime($expire);

        $value = $this->serialize($value);

        if ($expire) {
            $result = $this->handler->setex($key, $expire, $value);
        } else {
            $result = $this->handler->set($key, $value);
        }

        isset($first) && $this->setTagItem($key);

        return $result;
    }

    /**
     * 自增缓存（针对数值缓存）
     * @access public
     * @param  string    $name 缓存变量名
     * @param  int       $step 步长
     * @return false|int
     */
    public function inc($name, $step = 1)
    {
        $this->writeTimes++;

        $key = $this->getCacheKey($name);

        return $this->handler->incrby($key, $step);
    }

    /**
     * 自减缓存（针对数值缓存）
     * @access public
     * @param  string    $name 缓存变量名
     * @param  int       $step 步长
     * @return false|int
     */
    public function dec($name, $step = 1)
    {
        $this->writeTimes++;

        $key = $this->getCacheKey($name);

        return $this->handler->decrby($key, $step);
    }

    /**
     * 删除缓存
     * @access public
     * @param  string $name 缓存变量名
     * @return boolean
     */
    public function rm($name)
    {
        $this->writeTimes++;

        return $this->handler->delete($this->getCacheKey($name));
    }

    /**
     * 清除缓存
     * @access public
     * @param  string $tag 标签名
     * @return boolean
     */
    public function clear($tag = null)
    {
        if ($tag) {
            // 指定标签清除
            $keys = $this->getTagItem($tag);

            foreach ($keys as $key) {
                $this->handler->delete($key);
            }

            $this->rm('tag_' . md5($tag));
            return true;
        }

        $this->writeTimes++;

        return $this->handler->flushDB();
    }

    /**
     * lpush
     * @param $key
     * @param $value
     * @return int
     */
    public function lpush($key, $value)
    {
        return $this->handler->lpush($key, $value);
    }

    /**
     * rpush
     * @param $key
     * @param $value
     * @return int
     */
    public function rpush($key, $value)
    {
        return $this->handler->rpush($key, $value);
    }

    /**
     * add lpop
     * @param $key
     * @return string
     */
    public function lpop($key)
    {
        return $this->handler->lpop($key);
    }

    /**
     * lrange
     * @param $key
     * @param $start
     * @param $end
     * @return array
     */
    public function lrange($key, $start, $end)
    {
        return $this->handler->lrange($key, $start, $end);
    }

    /**
     * set hash opeation
     * @param $name
     * @param $key
     * @param $value
     * @return int
     */
    public function hset($name, $key, $value)
    {
        if (is_array($value))
        {
            $value = json_encode($value);
        }
        return $this->handler->hset($name, $key, $value);

    }

    /**
     * get hash opeation
     * @param $name
     * @param null $key
     * @return array|mixed|string
     */
    public function hget($name, $key = null)
    {

        if ($key)
        {
            $data = $this->handler->hget($name, $key);
            $value = json_decode($data, true);
            if (is_null($value))
            {
                $value = $data;
            }
            return $value;
        }
        return $this->handler->hgetAll($name);
    }

    /**
     * delete hash opeation
     * @param $name
     * @param null $key
     * @return int
     */
    public function hdel($name, $key = null)
    {
        if ($key)
        {
            return $this->handler->hdel($name, $key);
        }
        return $this->handler->del($name);
    }




}
