<?php
/**
 * NATAPHP – date and time column types on DBAL 4.6.
 */

namespace Nata\Test\ORM;

use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use Nata\Database\Schema;
use Nata\I18n\Time;
use Nata\ORM\Table;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

/**
 * DBAL 4.6 introspects the MySQL/MariaDB date and time columns as immutable types,
 * while the ORM writes Nata\I18n\Time, a mutable DateTime: every save() with a date
 * failed ("Could not convert PHP value of type Nata\I18n\Time to type
 * Doctrine\DBAL\Types\DateTimeImmutableType").
 */
class DateTimeTypesTest extends TestCase {

/**
 * Each immutable date or time type maps to its mutable type; the others stay.
 *
 * @return void
 */
    public function testMutableType(): void {
        $expected = [
            Types::DATE_IMMUTABLE => Types::DATE_MUTABLE,
            Types::DATETIME_IMMUTABLE => Types::DATETIME_MUTABLE,
            Types::DATETIMETZ_IMMUTABLE => Types::DATETIMETZ_MUTABLE,
            Types::TIME_IMMUTABLE => Types::TIME_MUTABLE,
            Types::DATETIME_MUTABLE => Types::DATETIME_MUTABLE,
            Types::STRING => Types::STRING
        ];
        foreach ($expected as $name => $mutableName) {
            $this->assertSame($mutableName, Type::lookupName(Schema::mutableType(Type::getType($name))), $name);
        }
    }

/**
 * A Time is written to an immutable column type as to the mutable one.
 *
 * @return void
 */
    public function testTimeIsWrittenToImmutableTypes(): void {
        $table = (new ReflectionClass(Table::class))->newInstanceWithoutConstructor();
        $format = new ReflectionMethod(Table::class, '_formatDatabaseDateTime');
        $platform = new MySQLPlatform();
        $pairs = [
            Types::DATE_IMMUTABLE => Types::DATE_MUTABLE,
            Types::DATETIME_IMMUTABLE => Types::DATETIME_MUTABLE,
            Types::TIME_IMMUTABLE => Types::TIME_MUTABLE
        ];
        foreach ($pairs as $immutableName => $mutableName) {
            $value = new Time('2026-11-14 21:37:50', 'UTC');
            $mutable = $format->invoke($table, Type::getType($mutableName), $value, $platform);
            $immutable = $format->invoke($table, Type::getType($immutableName), $value, $platform);
            $this->assertNotNull($immutable, $immutableName);
            $this->assertSame($mutable, $immutable, $immutableName);
        }
        $this->assertSame('2026-11-14 21:37:50', $format->invoke($table, Type::getType(Types::DATETIME_IMMUTABLE), new Time('2026-11-14 21:37:50', 'UTC'), $platform));
    }
}
