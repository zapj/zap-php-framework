<?php

namespace zap\http;

class Session
{
    const FLASH_MESSAGE_KEY = '__zap_flash__';

    /** @var self 单例 */
    protected static ?Session $instance = null;

    /** @var bool 是否已启动 */
    protected bool $started = false;

    public function __construct()
    {
    }

    /**
     * 获取单例
     */
    public static function getInstance(): self
    {
        if (static::$instance === null) {
            static::$instance = new self();
        }
        return static::$instance;
    }

    /**
     * 启动 Session
     *
     * @return $this
     */
    public function start(): self
    {
        if (!$this->started && session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $this->started = true;
        return $this;
    }

    // ───────────────────── 读写 ─────────────────────

    /**
     * 读取值
     *
     * @param string $key     键名（点分路径支持 'user.name'）
     * @param mixed  $default 默认值
     * @return mixed
     */
    public function get(string $key, $default = null)
    {
        $this->start();
        return $this->dotGet($_SESSION, $key, $default);
    }

    /**
     * 写入值
     *
     * @return $this
     */
    public function set(string $key, $value): self
    {
        $this->start();
        $this->dotSet($_SESSION, $key, $value);
        return $this;
    }

    /**
     * 检查键是否存在（不依赖值真假）
     *
     * @return bool
     */
    public function has(string $key): bool
    {
        $this->start();
        return $this->dotHas($_SESSION, $key);
    }

    /**
     * 删除一个或多个键
     *
     * @param string|array $keys
     * @return $this
     */
    public function forget($keys): self
    {
        $this->start();
        foreach ((array)$keys as $key) {
            $this->dotDelete($_SESSION, $key);
        }
        return $this;
    }

    /**
     * 读取并删除
     *
     * @return mixed
     */
    public function pull(string $key, $default = null)
    {
        $value = $this->get($key, $default);
        $this->forget($key);
        return $value;
    }

    /**
     * 写入数组类型值推入元素
     *
     * @return $this
     */
    public function push(string $key, $value): self
    {
        $this->start();
        $arr = $this->get($key, []);
        $arr[] = $value;
        $this->set($key, $arr);
        return $this;
    }

    /**
     * 递增
     *
     * @return $this
     */
    public function increment(string $key, int $amount = 1): self
    {
        $this->start();
        $value = $this->get($key, 0) + $amount;
        $this->set($key, $value);
        return $this;
    }

    /**
     * 递减
     *
     * @return $this
     */
    public function decrement(string $key, int $amount = 1): self
    {
        return $this->increment($key, -$amount);
    }

    /**
     * 获取所有 Session 数据
     */
    public function all(): array
    {
        $this->start();
        return $_SESSION;
    }

    /**
     * 仅获取指定键
     */
    public function only(array $keys): array
    {
        $this->start();
        $result = [];
        foreach ($keys as $key) {
            $result[$key] = $this->get($key);
        }
        return $result;
    }

    /**
     * 获取当前 Session ID
     */
    public function getId(): string
    {
        $this->start();
        return session_id();
    }

    /**
     * 重新生成 Session ID（防会话固定攻击）
     *
     * @param bool $deleteOld 是否删除旧 Session 文件
     * @return $this
     */
    public function regenerate(bool $deleteOld = false): self
    {
        $this->start();
        session_regenerate_id($deleteOld);
        return $this;
    }

    // ───────────────────── Flash 消息 ─────────────────────

    /**
     * 写入 Flash 消息
     *
     * @param string $type    消息类型
     * @param string $message 消息内容
     * @return $this
     */
    public function flash(string $type, string $message): self
    {
        $this->start();
        $_SESSION[self::FLASH_MESSAGE_KEY][$type][] = [
            'message'   => $message,
            'timestamp' => time(),
        ];
        return $this;
    }

    /**
     * 读取并清除 Flash 消息
     *
     * @param string|array|null $types 消息类型，null=所有类型
     * @return array
     */
    public function getFlash($types = null): array
    {
        $this->start();
        $flashMessages = [];

        if (empty($_SESSION[self::FLASH_MESSAGE_KEY])) {
            return $flashMessages;
        }

        if (is_null($types)) {
            $flashMessages = $_SESSION[self::FLASH_MESSAGE_KEY];
            unset($_SESSION[self::FLASH_MESSAGE_KEY]);
            return $flashMessages;
        }

        if (!is_array($types)) {
            $types = [$types];
        }

        foreach ($types as $type) {
            if (isset($_SESSION[self::FLASH_MESSAGE_KEY][$type])) {
                $flashMessages[$type] = $_SESSION[self::FLASH_MESSAGE_KEY][$type];
                unset($_SESSION[self::FLASH_MESSAGE_KEY][$type]);
            }
        }

        if (empty($_SESSION[self::FLASH_MESSAGE_KEY])) {
            unset($_SESSION[self::FLASH_MESSAGE_KEY]);
        }

        return $flashMessages;
    }

    /**
     * 检查是否有 Flash 消息
     *
     * @return bool
     */
    public function hasFlash(): bool
    {
        $this->start();
        return !empty($_SESSION[self::FLASH_MESSAGE_KEY]);
    }

    /**
     * 清除 Flash 消息
     *
     * @param string|array|null $types
     * @return $this
     */
    public function clearFlash($types = null): self
    {
        $this->start();

        if (is_null($types)) {
            unset($_SESSION[self::FLASH_MESSAGE_KEY]);
            return $this;
        }

        if (!is_array($types)) {
            $types = [$types];
        }

        foreach ($types as $type) {
            unset($_SESSION[self::FLASH_MESSAGE_KEY][$type]);
        }

        if (empty($_SESSION[self::FLASH_MESSAGE_KEY])) {
            unset($_SESSION[self::FLASH_MESSAGE_KEY]);
        }

        return $this;
    }

    // ───────────────────── 销毁 ─────────────────────

    /**
     * 销毁 Session
     */
    public function destroy(): void
    {
        if ($this->started || session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        $_SESSION = [];
        $this->started = false;
    }

    // ───────────────────── 点分路径辅助方法 ─────────────────────

    private function dotGet(array &$array, string $key, $default = null)
    {
        $keys = explode('.', $key);
        foreach ($keys as $segment) {
            if (!is_array($array) || !array_key_exists($segment, $array)) {
                return $default;
            }
            $array = &$array[$segment];
        }
        return $array;
    }

    private function dotSet(array &$array, string $key, $value): void
    {
        $keys = explode('.', $key);
        foreach ($keys as $i => $segment) {
            if ($i === count($keys) - 1) {
                $array[$segment] = $value;
                return;
            }
            if (!isset($array[$segment]) || !is_array($array[$segment])) {
                $array[$segment] = [];
            }
            $array = &$array[$segment];
        }
    }

    private function dotHas(array &$array, string $key): bool
    {
        $keys = explode('.', $key);
        foreach ($keys as $segment) {
            if (!is_array($array) || !array_key_exists($segment, $array)) {
                return false;
            }
            $array = &$array[$segment];
        }
        return true;
    }

    private function dotDelete(array &$array, string $key): void
    {
        $keys = explode('.', $key);
        $lastKey = array_pop($keys);
        foreach ($keys as $segment) {
            if (!is_array($array) || !array_key_exists($segment, $array)) {
                return;
            }
            $array = &$array[$segment];
        }
        unset($array[$lastKey]);
    }
}
