<?php
/**
 * NATAPHP – Local file storage adapter: info() and read().
 */

namespace Nata\Test\FilesystemManager;

use Nata\FilesystemManager\FileStorage\Adapter\Local;
use PHPUnit\Framework\TestCase;

/**
 * The Local adapter answers info() and read() like the object storage
 * adapters, so code that uses them runs against a local store in tests.
 */
class LocalAdapterTest extends TestCase {

/**
 * Root of the store.
 *
 * @var string
 */
    private string $_root;

/**
 * Temporary root.
 *
 * @return void
 */
    protected function setUp(): void {
        $this->_root = str_replace('\\', '/', TMP . 'local-adapter-' . bin2hex(random_bytes(4)));
    }

/**
 * Remove the temporary root.
 *
 * @return void
 */
    protected function tearDown(): void {
        if (is_dir($this->_root)) {
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->_root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($files as $file) {
                $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
            }
            rmdir($this->_root);
        }
    }

/**
 * info() gives the size and the SHA-256 of a stored file, read() its
 * contents; both null for a missing file.
 *
 * @return void
 */
    public function testInfoAndRead(): void {
        $adapter = new Local('test', ['root' => $this->_root]);
        $adapter->put('a/b.txt', 'hello');

        $info = $adapter->info('a/b.txt');
        $this->assertSame(5, $info['size']);
        $this->assertSame(hash('sha256', 'hello'), $info['checksumSha256']);
        $this->assertNull($info['etag']);
        $this->assertSame('hello', $adapter->read('a/b.txt'));

        $this->assertNull($adapter->info('a/missing.txt'));
        $this->assertNull($adapter->read('a/missing.txt'));
    }

}
