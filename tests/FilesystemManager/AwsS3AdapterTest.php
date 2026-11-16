<?php
/**
 * NATAPHP – AwsS3 file storage adapter (S3, R2, Hetzner, DigitalOcean).
 */

namespace Nata\Test\FilesystemManager;

use Aws\CommandInterface;
use Aws\MockHandler;
use Aws\Result;
use Aws\S3\Exception\S3Exception;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Utils;
use Nata\FilesystemManager\Exception\FileStorageException;
use Nata\FilesystemManager\FileStorage;
use Nata\FilesystemManager\FileStorage\Adapter\AwsS3;
use Nata\FilesystemManager\FileStorage\Adapter\CloudflareR2;
use PHPUnit\Framework\TestCase;

/**
 * The adapter is asked through the SDK with a mock handler: each test sees
 * the commands sent and answers them, without network.
 */
class AwsS3AdapterTest extends TestCase {

/**
 * Commands received by the mock handler.
 *
 * @var list<\Aws\CommandInterface>
 */
    private array $_commands = [];

/**
 * Adapter over a mock handler that answers with the given results, in order.
 * A result is an \Aws\Result, or an HTTP status code to fail with.
 *
 * @param list<\Aws\Result|int> $results Results
 * @param array $config Store configuration
 * @param string $class Adapter class
 * @return \Nata\FilesystemManager\FileStorage\Adapter\AwsS3 Adapter
 */
    private function _adapter(array $results, array $config = [], string $class = CloudflareR2::class): AwsS3 {
        $mock = new MockHandler();
        foreach ($results as $result) {
            $mock->append(function (CommandInterface $command) use ($result) {
                $this->_commands[] = $command;
                if (is_int($result)) {
                    return new S3Exception('HTTP ' . $result, $command, ['response' => new Response($result)]);
                }
                return $result;
            });
        }

        return new $class('test', $config + [
            'bucket' => 'bucket',
            'endpoint' => 'https://account.r2.cloudflarestorage.com',
            'accountId' => 'account',
            'publicKey' => 'key',
            'secretKey' => 'secret',
            'acl' => 'private',
            'handler' => $mock,
        ]);
    }

/**
 * A put without metadata works (it used to add null to an array), takes
 * the ACL of the store, and sends only scalar metadata, as strings.
 *
 * @return void
 */
    public function testPutWithoutMetadataUsesTheAclOfTheStore(): void {
        $adapter = $this->_adapter([new Result(['ETag' => '"e"'])]);

        $this->assertNotNull($adapter->put('a/b.txt', 'hello'));
        $this->assertSame('private', $this->_commands[0]['ACL']);
        $this->assertSame(['original-filename' => 'b.txt'], $this->_commands[0]['Metadata']);

        $adapter = $this->_adapter([new Result(['ETag' => '"e"'])]);
        $adapter->put('a/b.txt', 'hello', ['metadata' => ['flag' => true, 'count' => 3, 'options' => ['x' => 1], 'none' => null]]);
        $this->assertSame(['original-filename' => 'b.txt', 'flag' => '1', 'count' => '3'], $this->_commands[1]['Metadata']);
    }

/**
 * The ACL given wins; without it, the visibility; "private-read" and other
 * private values are "private", "public" is "public-read".
 *
 * @return void
 */
    public function testAclResolution(): void {
        $adapter = $this->_adapter(array_fill(0, 4, new Result(['ETag' => '"e"'])), ['acl' => 'private-read']);

        $adapter->put('a.txt', 'x');
        $adapter->put('a.txt', 'x', ['visibility' => 'public']);
        $adapter->put('a.txt', 'x', ['acl' => 'private', 'visibility' => 'public']);
        $adapter->put('a.txt', 'x', ['acl' => 'public']);

        $this->assertSame(['private', 'public-read', 'private', 'public-read'], array_map(fn($command) => $command['ACL'], $this->_commands));
    }

/**
 * With `checksum`, the SHA-256 of the contents goes with the put, so the
 * storage refuses a file that arrives different.
 *
 * @return void
 */
    public function testPutWithChecksum(): void {
        $adapter = $this->_adapter([new Result(['ETag' => '"e"']), new Result(['ETag' => '"e"'])]);

        $adapter->put('a.txt', 'hello', ['checksum' => true]);
        $adapter->put('a.txt', 'hello');

        $this->assertSame(base64_encode(hash('sha256', 'hello', true)), $this->_commands[0]['ChecksumSHA256']);
        $this->assertNull($this->_commands[1]['ChecksumSHA256']);
    }

/**
 * A put refused by the storage returns null with the error.
 *
 * @return void
 */
    public function testPutRefused(): void {
        $adapter = $this->_adapter([400]);

        $this->assertNull($adapter->put('a.txt', 'hello', ['checksum' => true]));
        $this->assertStringContainsString('HTTP 400', (string)$adapter->getLastError());
    }

/**
 * info() reads one HEAD: size, ETag without quotes, metadata and the
 * SHA-256 in hex; null for a missing file.
 *
 * @return void
 */
    public function testInfo(): void {
        $adapter = $this->_adapter([
            new Result([
                'ContentLength' => 5,
                'ETag' => '"5d41402abc4b2a76b9719d911017c592"',
                'ContentType' => 'text/plain',
                'Metadata' => ['sha256' => 'abc'],
                'ChecksumSHA256' => base64_encode(hash('sha256', 'hello', true)),
            ]),
            404,
        ]);

        $info = $adapter->info('a.txt');
        $this->assertSame(5, $info['size']);
        $this->assertSame('5d41402abc4b2a76b9719d911017c592', $info['etag']);
        $this->assertSame('text/plain', $info['mime']);
        $this->assertSame(['sha256' => 'abc'], $info['metadata']);
        $this->assertSame(hash('sha256', 'hello'), $info['checksumSha256']);
        $this->assertSame('ENABLED', $this->_commands[0]['ChecksumMode']);

        $this->assertNull($adapter->info('missing.txt'));
    }

/**
 * Only a 404 means a missing file: any other error throws, so a storage
 * that cannot be asked is never taken for an empty one.
 *
 * @return void
 */
    public function testExistsThrowsWhenTheStorageCannotBeAsked(): void {
        $adapter = $this->_adapter([new Result(['ContentLength' => 1]), 404, 403]);

        $this->assertTrue($adapter->exists('a.txt'));
        $this->assertFalse($adapter->exists('b.txt'));

        $this->expectException(FileStorageException::class);
        $adapter->exists('c.txt');
    }

/**
 * read() returns the contents; null for a missing file.
 *
 * @return void
 */
    public function testRead(): void {
        $adapter = $this->_adapter([new Result(['Body' => Utils::streamFor('hello')]), 404]);

        $this->assertSame('hello', $adapter->read('a.txt'));
        $this->assertNull($adapter->read('missing.txt'));
    }

/**
 * The client is created once per adapter.
 *
 * @return void
 */
    public function testClientIsReused(): void {
        $adapter = new class('test', ['region' => 'auto', 'endpoint' => 'https://example.test', 'publicKey' => 'k', 'secretKey' => 's']) extends AwsS3 {

            /**
             * The client.
             *
             * @return \Aws\S3\S3Client
             */
            public function client() {
                return $this->_loadS3Client();
            }

        };

        $this->assertSame($adapter->client(), $adapter->client());
    }

/**
 * Through FileStorage, a put without visibility nor ACL takes the ACL of
 * the store (it used to be public-read: FileStorage sent visibility
 * "public", which the adapter ignored, and the adapter defaulted to
 * public-read).
 *
 * @return void
 */
    public function testFacadePutTakesTheAclOfTheStore(): void {
        $mock = new MockHandler();
        $mock->append(function (CommandInterface $command) {
            $this->_commands[] = $command;
            return new Result(['ETag' => '"e"']);
        });
        FileStorage::config('stores');
        FileStorage::config('stores.aws_s3_adapter_test', [
            'adapter' => 'CloudflareR2',
            'bucket' => 'bucket',
            'endpoint' => 'https://account.r2.cloudflarestorage.com',
            'accountId' => 'account',
            'publicKey' => 'key',
            'secretKey' => 'secret',
            'acl' => 'private',
            'handler' => $mock,
        ]);

        $this->assertNotFalse(FileStorage::put('hello', 'aws_s3_adapter_test', 'a/b.txt', ['asObject' => false, 'checksum' => true]));
        $this->assertSame('private', $this->_commands[0]['ACL']);
        $this->assertNotNull($this->_commands[0]['ChecksumSHA256']);
    }

}
