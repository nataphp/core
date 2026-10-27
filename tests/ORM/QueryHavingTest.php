<?php
/**
 * NATAPHP – HAVING clause composition on ORM queries.
 */

namespace Nata\Test\ORM;

use Nata\Database\Connection;
use Nata\Database\Query as DatabaseQuery;
use Nata\ORM\Query;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

/**
 * ORM\Query::andHaving() must join with AND, like Database\Query::andHaving():
 * it called orHaving(), so having(a)->andHaving(b) became "HAVING (a) OR (b)".
 */
class QueryHavingTest extends TestCase {

/**
 * An ORM query over an in-memory SQLite connection, built without a Table:
 * only the SQL it renders is under test.
 *
 * @return Query Query selecting from "sessions" (alias Sessions)
 */
    private function _query(): Query {
        $connection = new Connection(['driver' => 'pdo_sqlite', 'memory' => true, '_configName' => 'test']);
        $query = (new ReflectionClass(Query::class))->newInstanceWithoutConstructor();
        (new ReflectionClass(DatabaseQuery::class))->getProperty('_connection')->setValue($query, $connection);
        $query->select('Sessions.id')->from('sessions', 'Sessions');

        return $query;
    }

/**
 * The SQL of the query's DBAL builder, on one line. ORM\Query::sql() would need a
 * Table for its eager loader, which the HAVING clause does not involve.
 *
 * @param Query $query Query
 * @return string SQL
 */
    private function _sql(Query $query): string {
        $builder = (new ReflectionMethod(DatabaseQuery::class, '_loadBuilder'))->invoke($query);

        return trim((string) preg_replace('/\s+/', ' ', $builder->getSQL()));
    }

/**
 * andHaving() narrows the existing HAVING.
 *
 * @return void
 */
    public function testAndHavingJoinsWithAnd(): void {
        $sql = $this->_sql($this->_query()->having(['distance <' => 50])->andHaving(['distance >=' => 25]));

        $this->assertStringContainsString('HAVING (distance < ?) AND (distance >= ?)', $sql);
        $this->assertStringNotContainsString(' OR ', $sql);
    }

/**
 * orHaving() still widens it.
 *
 * @return void
 */
    public function testOrHavingJoinsWithOr(): void {
        $sql = $this->_sql($this->_query()->having(['distance <' => 5])->orHaving(['distance >' => 100]));

        $this->assertStringContainsString('HAVING (distance < ?) OR (distance > ?)', $sql);
    }

/**
 * andHaving() on a query without HAVING starts one.
 *
 * @return void
 */
    public function testAndHavingAloneStartsTheClause(): void {
        $sql = $this->_sql($this->_query()->andHaving(['total >' => 1]));

        $this->assertStringContainsString('HAVING total > ?', $sql);
    }

}
