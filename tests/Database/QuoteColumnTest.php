<?php
/**
 * NATAPHP – column names are always quoted in conditions, ORDER BY, SET and VALUES.
 */

namespace Nata\Test\Database;

use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Nata\Database\Connection;
use Nata\Database\Query;
use Nata\Database\Query\QueryExpression;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Conditions used to quote a column only when Doctrine's reserved word list
 * knew it. MariaDB reserves PORTION and no Doctrine list does, so
 * `['portion' => 'dose']` produced SQL that MariaDB refused (MySQL accepts it).
 */
class QuoteColumnTest extends TestCase {

/**
 * A query over an in-memory SQLite connection (quote character: ").
 *
 * @return Query Query selecting from "menu_item_portions" (alias MenuItemPortions)
 */
    private function _query(): Query {
        $connection = new Connection(['driver' => 'pdo_sqlite', 'memory' => true, '_configName' => 'test']);
        $query = new Query($connection);

        return $query->select('MenuItemPortions.id')->from('menu_item_portions', 'MenuItemPortions');
    }

/**
 * The SQL of the query's DBAL builder, on one line.
 *
 * @param Query $query Query
 * @return string SQL
 */
    private function _sql(Query $query): string {
        $builder = (new ReflectionMethod(Query::class, '_loadBuilder'))->invoke($query);

        return trim((string) preg_replace('/\s+/', ' ', $builder->getSQL()));
    }

/**
 * Plain identifiers are quoted part by part; anything else is left alone.
 *
 * @return void
 */
    public function testQuoteColumn(): void {
        $this->assertSame('`portion`', Query::quoteColumn('portion'));
        $this->assertSame('`MenuItemPortions`.`portion`', Query::quoteColumn('MenuItemPortions.portion'));
        $this->assertSame('`portion`', Query::quoteColumn('portion', new MariaDBPlatform()));
        $this->assertSame('DATE(created)', Query::quoteColumn('DATE(created)'));
        $this->assertSame('`portion`', Query::quoteColumn('`portion`'));
        $this->assertSame('*', Query::quoteColumn('*'));
        $this->assertSame('1', Query::quoteColumn('1'));
        $this->assertSame('price * 2', Query::quoteColumn('price * 2'));
    }

/**
 * WHERE, OR WHERE, closures and ORDER BY quote the column, whatever its name.
 *
 * @return void
 */
    public function testConditionsAndOrderQuoteColumns(): void {
        $query = $this->_query()
            ->where(['menu_item_id' => 12, 'portion' => 'dose'])
            ->orWhere(['AND' => [['MenuItemPortions.people >=' => 2], ['portion IS' => null]]])
            ->andWhere(['AND' => function (QueryExpression $exp) {
                return $exp->in('portion', ['dose', 'meia_dose'])->isNotNull('people');
            }])
            ->order(['portion' => 'ASC', 'MenuItemPortions.sort_order' => 'DESC']);

        $sql = $this->_sql($query);

        $this->assertStringContainsString('"menu_item_id" = ? AND "portion" = ?', $sql);
        $this->assertStringContainsString('"MenuItemPortions"."people" >= ?', $sql);
        $this->assertStringContainsString('"portion" IS NULL', $sql);
        $this->assertStringContainsString('"portion" IN (?,?)', $sql);
        $this->assertStringContainsString('"people" IS NOT NULL', $sql);
        $this->assertStringContainsString('ORDER BY "portion" ASC, "MenuItemPortions"."sort_order" DESC', $sql);
    }

/**
 * An expression built without a query falls back to backticks.
 *
 * @return void
 */
    public function testStandaloneExpressionUsesBackticks(): void {
        $expression = new QueryExpression(['portion' => 'dose', 'DATE(created) >=' => '2026-01-01']);

        $this->assertSame('`portion` = ? AND DATE(created) >= ?', $expression->getSql());
    }

}
