<?php
/**
 * NATAPHP – the database password never shows when printed.
 */

namespace Nata\Test\Database;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Connection as DriverConnection;
use LogicException;
use Nata\Database\Connection;
use Nata\Database\Driver\PasswordMiddleware;
use Nata\Database\Password;
use PHPUnit\Framework\TestCase;

/**
 * The password stays out of the Doctrine parameters and of every print of
 * the objects, and reaches the driver only at connect time.
 */
class ConnectionPasswordTest extends TestCase {

/**
 * Password of the tests.
 */
    const SECRET = 'not-to-be-printed-7c1f';

/**
 * Every way of printing a value.
 *
 * @param mixed $value Value
 * @return string Printed value
 */
    private function _printed(mixed $value): string {
        ob_start();
        var_dump($value);
        $printed = (string)ob_get_clean();

        return $printed . print_r($value, true) . var_export($value, true) . json_encode($value);
    }

/**
 * A Password prints as an empty object and refuses serialization.
 *
 * @return void
 */
    public function testPasswordNeverPrints(): void {
        $password = new Password(self::SECRET);

        $this->assertSame(self::SECRET, $password->reveal());
        $this->assertStringNotContainsString(self::SECRET, $this->_printed($password));
        $this->assertStringNotContainsString(self::SECRET, $this->_printed(['password' => $password]));
        $this->expectException(LogicException::class);
        serialize($password);
    }

/**
 * The Doctrine connection has no password in its parameters, and the
 * connection objects never print it.
 *
 * @return void
 */
    public function testConnectionNeverHoldsThePassword(): void {
        $connection = new Connection(['driver' => 'pdo_mysql', 'dbname' => 'nata', 'user' => 'nata', 'password' => self::SECRET, '_configName' => 'test']);

        $this->assertArrayNotHasKey('password', $connection->getDoctrineConnection()->getParams());
        $this->assertStringNotContainsString(self::SECRET, $this->_printed($connection));
        $this->assertStringNotContainsString(self::SECRET, $this->_printed($connection->getDoctrineConnection()));
    }

/**
 * The driver receives the password when it connects.
 *
 * @return void
 */
    public function testTheDriverReceivesThePassword(): void {
        $driver = $this->createMock(Driver::class);
        $driver->expects($this->once())->method('connect')
            ->with($this->callback(fn(array $params) => ($params['password'] ?? null) === self::SECRET && $params['user'] === 'nata'))
            ->willReturn($this->createMock(DriverConnection::class));

        (new PasswordMiddleware(new Password(self::SECRET)))->wrap($driver)->connect(['user' => 'nata']);
    }

}
