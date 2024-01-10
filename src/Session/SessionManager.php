<?php
/*
 * Copyright (c) 2023 cclilshy
 * Contact Information:
 * Email: jingnigg@gmail.com
 * Website: https://cc.cloudtay.com/
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 *
 * 版权所有 (c) 2023 cclilshy
 *
 * 特此免费授予任何获得本软件及相关文档文件（“软件”）副本的人，不受限制地处理
 * 本软件，包括但不限于使用、复制、修改、合并、出版、发行、再许可和/或销售
 * 软件副本的权利，并允许向其提供本软件的人做出上述行为，但须符合以下条件：
 *
 * 上述版权声明和本许可声明应包含在本软件的所有副本或主要部分中。
 *
 * 本软件按“原样”提供，不提供任何形式的保证，无论是明示或暗示的，
 * 包括但不限于适销性、特定目的的适用性和非侵权性的保证。在任何情况下，
 * 无论是合同诉讼、侵权行为还是其他方面，作者或版权持有人均不对
 * 由于软件或软件的使用或其他交易而引起的任何索赔、损害或其他责任承担责任。
 */

namespace Cclilshy\PRippleWeb\Session;

use Core\Map\WorkerMap;
use RedisException;
use RuntimeException;
use Throwable;
use Worker\Built\RedisWorker;

/**
 * Class Session
 */
class SessionManager
{
    public const int TYPE_FILE  = 1;
    public const int TYPE_REDIS = 2;

    /**
     * Session directory
     * @var string $filePath
     */

    public string $filePath;

    /**
     * @var string $redisName
     */
    public string $redisName = 'default';

    /**
     * @var string|mixed $prefix
     */
    public string $prefix = '';

    /**
     * SessionManager constructor.
     * @param array    $config
     * @param int|null $type
     */
    public function __construct(array $config, public readonly int|null $type = SessionManager::TYPE_FILE)
    {
        if ($this->type === SessionManager::TYPE_FILE) {
            if (!$filePath = $config['FILE_PATH'] ?? null) {
                throw new RuntimeException('Session directory does not exist: ' . $filePath);
            } elseif (!is_dir($filePath) && !mkdir($filePath, 0755, true)) {
                throw new RuntimeException('Session directory does not exist: ' . $filePath);
            }
            $this->filePath = $filePath;
        } elseif ($this->type === SessionManager::TYPE_REDIS) {
            if (!$redisName = $config['REDIS_NAME'] ?? null) {
                throw new RuntimeException('Redis name does not exist: ' . $redisName);
            } else {
                /**
                 * @var RedisWorker $redisWorker
                 */
                $redisWorker = WorkerMap::get(RedisWorker::class);
                if (!isset($redisWorker->redisConfigs[$redisName])) {
                    throw new RuntimeException('Redis name does not exist: ' . $redisName);
                }
            }
            $this->redisName = $redisName;
        } else {
            throw new RuntimeException('Session type does not exist: ' . $this->type);
        }
        if ($config['PREFIX'] ?? null) {
            $this->prefix = $config['PREFIX'];
        }
    }

    /**
     * 通过Key构建Session
     * @param string $key
     * @return Session|false
     * @throws RedisException
     */
    public function buildSession(string $key): Session|false
    {
        if ($this->type === SessionManager::TYPE_FILE) {
            $sessionFile = "{$this->filePath}/session_{$this->prefix}_{$key}";
            if (file_exists($sessionFile)) {
                try {
                    $session = unserialize(file_get_contents($sessionFile));
                } catch (Throwable $exception) {
                    unlink($sessionFile);
                    return new Session($key, $this);
                }
                if (!$session instanceof Session) {
                    unlink($sessionFile);
                    return new Session($key, $this);
                } elseif ($session->expire > 0 && $session->startTime + $session->expire < time()) {
                    unlink($sessionFile);
                    return new Session($key, $this);
                }
                return $session;
            }
            return new Session($key, $this);
        } elseif ($this->type === SessionManager::TYPE_REDIS) {
            $sessionKey = "p:session_{$this->prefix}_{$key}";
            if ($origin = \Facade\RedisWorker::getClient($this->redisName)->get($sessionKey)) {
                try {
                    $session = unserialize($origin);
                } catch (Throwable $exception) {
                    \Facade\RedisWorker::getClient($this->redisName)->del($sessionKey);
                    return new Session($key, $this);
                }
                if (!$session instanceof Session) {
                    \Facade\RedisWorker::getClient($this->redisName)->del($sessionKey);
                    return new Session($key, $this);
                } elseif ($session->expire > 0 && $session->startTime + $session->expire > time()) {
                    \Facade\RedisWorker::getClient($this->redisName)->del($sessionKey);
                    return new Session($key, $this);
                }
                return $session;
            }
            return new Session($key, $this);
        } else {
            return false;
        }
    }
}
