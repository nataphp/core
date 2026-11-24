<?php
/**
 * NataPHP Framework
 *
 * @copyright     Copyright (c) Sérgio Dinis Lopes (http://nataphp.com)
 * @link          http://nataphp.com NataPHP Project
 * @license       http://www.opensource.org/licenses/mit-license.php MIT License
 */

namespace Nata\Cache;

/**
 * Sliding-window rate limiter backed by the cache.
 *
 * A bucket is any string that names what is limited ("login_email_<sha1>",
 * "proposal_ip_<ip>"). Each hit stores its timestamp; a hit is allowed while
 * the bucket has fewer than `$maxHits` timestamps within the last
 * `$windowSeconds`. It answers yes or no and leaves the response to the caller,
 * so a login form can still reply with the same neutral message and a public
 * form can show its own text instead of a bare 429.
 *
 * The cache configuration must keep entries at least as long as the longest
 * window used with it (a File cache with `'duration' => '+1 hour'`, say).
 * Hits are read and written without a lock: under heavy concurrency a few
 * extra hits may pass, which is acceptable for abuse limits.
 *
 * ```php
 * Cache::config('rate_limit', ['engine' => 'File', 'duration' => '+1 hour', 'prefix' => 'rl_']);
 *
 * $limiter = new RateLimiter('rate_limit');
 * if (!$limiter->hit('proposal_ip_' . $ip, 5, 3600)) {
 *     // over the limit
 * }
 * ```
 */
class RateLimiter {

/**
 * Cache configuration used to store the hit timestamps.
 *
 * @var string
 */
    protected string $_cacheConfig;

/**
 * Current Unix time (replaceable in tests).
 *
 * @var callable(): int
 */
    protected $_clock;

/**
 * Constructor.
 *
 * @param string $cacheConfig Cache configuration name (must outlive the window).
 * @param callable|null $clock Returns the current Unix time; defaults to time().
 */
    public function __construct(string $cacheConfig = 'rate_limit', ?callable $clock = null) {
        $this->_cacheConfig = $cacheConfig;
        $this->_clock = $clock ?? 'time';
    }

/**
 * Register a hit for a bucket and tell whether it is still within the limit.
 * A refused hit is not stored, so a client that keeps trying is let in again
 * as soon as its oldest allowed hit leaves the window.
 *
 * @param string $bucket Identifier, e.g. "magic_link_email_<sha1>".
 * @param int $maxHits Maximum hits allowed in the window.
 * @param int $windowSeconds Window length in seconds.
 * @return bool True when allowed, false when over the limit.
 */
    public function hit(string $bucket, int $maxHits, int $windowSeconds): bool {
        $now = (int)($this->_clock)();
        $hits = $this->_hits($bucket, $now, $windowSeconds);

        if (count($hits) >= $maxHits) {
            return false;
        }

        $hits[] = $now;
        Cache::write($this->_key($bucket), $hits, $this->_cacheConfig);

        return true;
    }

/**
 * Hits still left in the window for a bucket, without registering one.
 *
 * @param string $bucket Identifier.
 * @param int $maxHits Maximum hits allowed in the window.
 * @param int $windowSeconds Window length in seconds.
 * @return int Hits left (0 when over the limit).
 */
    public function remaining(string $bucket, int $maxHits, int $windowSeconds): int {
        $hits = $this->_hits($bucket, (int)($this->_clock)(), $windowSeconds);
        return max(0, $maxHits - count($hits));
    }

/**
 * Forget a bucket (e.g. after a successful login).
 *
 * @param string $bucket Identifier.
 * @return void
 */
    public function reset(string $bucket): void {
        Cache::delete($this->_key($bucket), $this->_cacheConfig);
    }

/**
 * Timestamps of a bucket still inside the window.
 *
 * @param string $bucket Identifier.
 * @param int $now Current Unix time.
 * @param int $windowSeconds Window length in seconds.
 * @return int[] Timestamps.
 */
    protected function _hits(string $bucket, int $now, int $windowSeconds): array {
        $hits = Cache::read($this->_key($bucket), $this->_cacheConfig);
        if (!is_array($hits)) {
            return [];
        }
        return array_values(array_filter($hits, function ($timestamp) use ($now, $windowSeconds) {
            return (int)$timestamp > $now - $windowSeconds;
        }));
    }

/**
 * Cache key of a bucket (any string becomes a safe file name).
 *
 * @param string $bucket Identifier.
 * @return string Cache key.
 */
    protected function _key(string $bucket): string {
        return 'rate_limit_' . sha1($bucket);
    }

}
