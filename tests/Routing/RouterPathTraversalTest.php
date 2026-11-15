<?php
/**
 * NATAPHP – Router::parse() and path traversal URLs.
 */

namespace Nata\Test\Routing;

use Nata\Routing\Router;
use PHPUnit\Framework\TestCase;

/**
 * A path traversal URL is answered with an empty route (so a 404). Without a
 * routing cache, parse() wrote that answer to the cache with an undefined key,
 * a TypeError: scanners got a 500 and every probe filled the error log.
 */
class RouterPathTraversalTest extends TestCase {

/**
 * Without a cache, a traversal URL gives an empty route, no error.
 *
 * @return void
 */
    public function testTraversalWithoutCache(): void {
        $this->assertSame(
            ['controller' => null, 'action' => null, 'ext' => null],
            Router::parse('/x/..%2f..%2fetc%2fpasswd')
        );
    }

}
