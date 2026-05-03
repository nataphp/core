<?php
/**
 * NataPHP Framework
 *
 * Copyright (c) Sérgio Dinis Lopes. (http://nataphp.com)
 * Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Sérgio Dinis Lopes. (http://nataphp.com)
 * @link          http://nataphp.com NataPHP Project
 * @since         NataPHP 2.0.0
 * @license       http://www.opensource.org/licenses/mit-license.php MIT License
 */

namespace Nata\View\Smarty;

use Nata\Cache\Cache;
use Smarty\Smarty;
use Smarty\Template;
use Smarty\Template\Cached;
use Smarty\Cacheresource\Base;

/**
 * Nata Cache resource for Smarty 5.
 *
 * Stores compiled Smarty output in the Nata cache engine.
 * All cache keys are sha1-hashed to avoid collisions.
 */
class CacheResource extends Base {

    protected array $_config;

/**
 * Constructor.
 *
 *
 */
    public function __construct(array $config) {
        $this->_config = $config;
    }

    public function populate(Cached $cached, Template $template): void {
        $cached->filepath = $template->source->uid
            . '#' . $this->sanitize($cached->source->resource)
            . '#' . $this->sanitize($cached->cache_id)
            . '#' . $this->sanitize($cached->compile_id);

        $this->populateTimestamp($cached);
    }

    public function populateTimestamp(Cached $cached): void {
        if (!$this->fetch($cached->filepath, $cached->source->name, $cached->cache_id, $cached->compile_id, $content, $timestamp, $cached->source->uid)) {
            return;
        }

        $cached->content = $content;
        $cached->timestamp = (int) $timestamp;
        $cached->exists = $cached->timestamp;
    }

    public function process(Template $template, ?Cached $cached = null, bool $update = false): bool {
        if (!$cached) {
            $cached = $template->cached;
        }

        $content = $cached->content ?: null;
        $timestamp = $cached->timestamp ?: null;

        if ($content === null || !$timestamp) {
            if (!$this->fetch($template->cached->filepath, $template->source->name, $template->cache_id, $template->compile_id, $content, $timestamp, $template->source->uid)) {
                return false;
            }
        }

        if (isset($content)) {
            $_smarty_tpl = $template;
            eval('?>' . $content);

            return true;
        }

        return false;
    }

    public function writeCachedContent(Template $template, string $content): bool {
        $this->addMetaTimestamp($content);

        return $this->write([$template->cached->filepath => $content]);
    }

    public function readCachedContent(Template $template): ?string {
        $content = null;
        $timestamp = null;

        if ($this->fetch($template->cached->filepath, $template->source->name, $template->cache_id, $template->compile_id, $content, $timestamp, $template->source->uid)) {
            return $content;
        }

        return null;
    }

    public function clearAll(Smarty $smarty, ?int $exp_time = null): int {
        if (!$this->purge()) {
            $this->invalidate(null);
        }

        return -1;
    }

    public function clear(Smarty $smarty, string $resource_name, ?string $cache_id, ?string $compile_id, ?int $exp_time): int {
        $uid = $this->getTemplateUid($smarty, $resource_name);
        $cid = $uid . '#' . $this->sanitize($resource_name) . '#' . $this->sanitize($cache_id) . '#' . $this->sanitize($compile_id);

        $this->delete([$cid]);
        $this->invalidate($cid, $resource_name, $cache_id, $compile_id, $uid);

        return -1;
    }

    public function hasLock(Smarty $smarty, Cached $cached): bool {
        $key = 'LOCK#' . $cached->filepath;
        $data = $this->read([$key]);

        return (bool) ($data && time() - $data[$key] < $smarty->locking_timeout);
    }

    public function acquireLock(Smarty $smarty, Cached $cached): bool {
        $cached->is_locked = true;

        return $this->write(['LOCK#' . $cached->filepath => time()]);
    }

    public function releaseLock(Smarty $smarty, Cached $cached): bool {
        $cached->is_locked = false;

        return $this->delete(['LOCK#' . $cached->filepath]);
    }

    protected function getTemplateUid(Smarty $smarty, string $resource_name): string {
        try {
            $tpl = $smarty->createTemplate($resource_name);

            return $tpl->source->exists ? $tpl->source->uid : '';
        } catch (\Throwable $e) {
            return '';
        }
    }

    protected function sanitize(?string $string): ?string {
        $string = trim((string) $string, '|');
        if ($string === '') {
            return null;
        }

        return preg_replace('#[^\w\|]+#S', '_', $string);
    }

    protected function fetch(string $cid, ?string $resource_name, ?string $cache_id, ?string $compile_id, &$content, &$timestamp, ?string $resource_uid): bool {
        $t = $this->read([$cid]);

        $content = !empty($t[$cid]) ? $t[$cid] : null;
        $timestamp = null;

        if ($content && ($timestamp = $this->getMetaTimestamp($content))) {
            $invalidated = $this->getLatestInvalidationTimestamp($cid, $resource_name, $cache_id, $compile_id, $resource_uid);
            if ($invalidated > $timestamp) {
                $timestamp = null;
                $content = null;
            }
        }

        return (bool) $content;
    }

    protected function addMetaTimestamp(string &$content): void {
        $mt = explode(' ', microtime());
        $ts = pack('NN', $mt[1], (int) ($mt[0] * 100000000));
        $content = $ts . $content;
    }

    protected function getMetaTimestamp(string &$content): float {
        $s = unpack('N', substr($content, 0, 4));
        $m = unpack('N', substr($content, 4, 4));
        $content = substr($content, 8);

        return $s[1] + ($m[1] / 100000000);
    }

    protected function invalidate(?string $cid, ?string $resource_name = null, ?string $cache_id = null, ?string $compile_id = null, ?string $resource_uid = null): void {
        $now = microtime(true);

        if (!$resource_name && !$cache_id && !$compile_id) {
            $key = 'IVK#ALL';
        } elseif ($resource_name && !$cache_id && !$compile_id) {
            $key = 'IVK#TEMPLATE#' . $resource_uid . '#' . $this->sanitize($resource_name);
        } elseif (!$resource_name && $cache_id && !$compile_id) {
            $key = 'IVK#CACHE#' . $this->sanitize($cache_id);
        } elseif (!$resource_name && !$cache_id && $compile_id) {
            $key = 'IVK#COMPILE#' . $this->sanitize($compile_id);
        } else {
            $key = 'IVK#CID#' . $cid;
        }

        $this->write([$key => $now]);
    }

    protected function getLatestInvalidationTimestamp(string $cid, ?string $resource_name, ?string $cache_id, ?string $compile_id, ?string $resource_uid): float {
        $keys = $this->listInvalidationKeys($cid, $resource_name, $cache_id, $compile_id, $resource_uid);
        if (!$keys) {
            return 0;
        }

        $values = $this->read($keys);
        if (!$values) {
            return 0;
        }

        return max(array_map('floatval', $values));
    }

    protected function listInvalidationKeys(string $cid, ?string $resource_name, ?string $cache_id, ?string $compile_id, ?string $resource_uid): array {
        $t = ['IVK#ALL'];
        $_name = $_compile = '#';

        if ($resource_name) {
            $_name .= $resource_uid . '#' . $this->sanitize($resource_name);
            $t[] = 'IVK#TEMPLATE' . $_name;
        }

        if ($compile_id) {
            $_compile .= $this->sanitize($compile_id);
            $t[] = 'IVK#COMPILE' . $_compile;
        }

        $_name .= '#';
        $cid = trim((string) $cache_id, '|');
        if (!$cid) {
            return $t;
        }

        $i = 0;
        while (true) {
            $i = strpos($cid, '|', $i);
            if ($i === false) {
                $t[] = 'IVK#CACHE#' . $cid;
                $t[] = 'IVK#CID' . $_name . $cid . $_compile;
                $t[] = 'IVK#CID' . $_name . $_compile;
                break;
            }
            $part = substr($cid, 0, $i);
            $t[] = 'IVK#CACHE#' . $part;
            $t[] = 'IVK#CID' . $_name . $part . $_compile;
            $i++;
        }

        return $t;
    }

    protected function read(array $keys): array {
        $data = [];
        foreach ($keys as $key) {
            $data[$key] = Cache::read($this->cacheKey($key), $this->_config['config']);
        }

        return $data;
    }

    protected function write(array $keys): bool {
        foreach ($keys as $key => $value) {
            Cache::write($this->cacheKey($key), $value, $this->_config['config']);
        }

        return true;
    }

    protected function delete(array $keys): bool {
        foreach ($keys as $key) {
            Cache::delete($this->cacheKey($key), $this->_config['config']);
        }

        return true;
    }

    protected function purge(): bool {
        return Cache::clearAll();
    }

    protected function cacheKey(string $key): string {
        $parts = explode('#', $key);
        $tpl = array_pop($parts);

        return 'view_' . sha1($key) . '_' . $tpl;
    }
}
