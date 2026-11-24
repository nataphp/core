<?php
/**
 * NATAPHP – Cache\RateLimiter.
 */

namespace Nata\Test\Cache;

use Nata\Cache\Cache;
use Nata\Cache\RateLimiter;
use PHPUnit\Framework\TestCase;

/**
 * Sliding window over the cache, with a fixed clock.
 */
class RateLimiterTest extends TestCase {

/**
 * Current time of the fake clock.
 *
 * @var int
 */
    protected int $_now = 1_800_000_000;

/**
 * Limiter under test.
 *
 * @var \Nata\Cache\RateLimiter
 */
    protected RateLimiter $_limiter;

/**
 * A file cache of its own, emptied before each test.
 *
 * @return void
 */
    protected function setUp(): void {
        // The File engine does not create its folder.
        $path = TMP . 'cache' . DS . 'rate_limit_test' . DS;
        if (!is_dir($path)) {
            mkdir($path, 0777, true);
        }
        Cache::config('rate_limit_test', [
            'engine' => 'File',
            'path' => $path,
            'duration' => '+1 day',
            'prefix' => 'rlt_',
        ]);
        Cache::clear(false, 'rate_limit_test');
        $this->_limiter = new RateLimiter('rate_limit_test', fn () => $this->_now);
    }

/**
 * Empty the cache.
 *
 * @return void
 */
    protected function tearDown(): void {
        Cache::clear(false, 'rate_limit_test');
    }

/**
 * Up to the limit the hits pass; the next one does not.
 *
 * @return void
 */
    public function testAllowsUpToTheLimit(): void {
        for ($i = 0; $i < 3; $i++) {
            $this->assertTrue($this->_limiter->hit('ip_1', 3, 60));
        }
        $this->assertFalse($this->_limiter->hit('ip_1', 3, 60));
        $this->assertSame(0, $this->_limiter->remaining('ip_1', 3, 60));
    }

/**
 * Buckets are independent.
 *
 * @return void
 */
    public function testBucketsAreIndependent(): void {
        $this->assertTrue($this->_limiter->hit('ip_1', 1, 60));
        $this->assertFalse($this->_limiter->hit('ip_1', 1, 60));
        $this->assertTrue($this->_limiter->hit('ip_2', 1, 60));
    }

/**
 * The window slides: old hits stop counting one by one.
 *
 * @return void
 */
    public function testWindowSlides(): void {
        $this->assertTrue($this->_limiter->hit('ip_1', 2, 60));
        $this->_now += 30;
        $this->assertTrue($this->_limiter->hit('ip_1', 2, 60));
        $this->assertFalse($this->_limiter->hit('ip_1', 2, 60));

        // The first hit leaves the window; the second still counts.
        $this->_now += 31;
        $this->assertSame(1, $this->_limiter->remaining('ip_1', 2, 60));
        $this->assertTrue($this->_limiter->hit('ip_1', 2, 60));
        $this->assertFalse($this->_limiter->hit('ip_1', 2, 60));
    }

/**
 * Refused hits are not stored: they do not push the reopening further.
 *
 * @return void
 */
    public function testRefusedHitsAreNotStored(): void {
        $this->assertTrue($this->_limiter->hit('ip_1', 1, 60));
        for ($i = 0; $i < 5; $i++) {
            $this->_now += 10;
            $this->assertFalse($this->_limiter->hit('ip_1', 1, 60));
        }
        $this->_now += 11;
        $this->assertTrue($this->_limiter->hit('ip_1', 1, 60));
    }

/**
 * remaining() does not register a hit; reset() forgets the bucket.
 *
 * @return void
 */
    public function testRemainingAndReset(): void {
        $this->assertSame(2, $this->_limiter->remaining('email_x', 2, 60));
        $this->assertSame(2, $this->_limiter->remaining('email_x', 2, 60));

        $this->_limiter->hit('email_x', 2, 60);
        $this->_limiter->hit('email_x', 2, 60);
        $this->assertFalse($this->_limiter->hit('email_x', 2, 60));

        $this->_limiter->reset('email_x');
        $this->assertTrue($this->_limiter->hit('email_x', 2, 60));
    }

/**
 * Any string is a valid bucket (emails, IPv6, spaces).
 *
 * @return void
 */
    public function testAnyStringIsABucket(): void {
        $this->assertTrue($this->_limiter->hit('login maria@exemplo.pt 2001:db8::1/ç', 1, 60));
        $this->assertFalse($this->_limiter->hit('login maria@exemplo.pt 2001:db8::1/ç', 1, 60));
    }

}
