<?php

/**
 * Zend Framework
 *
 * LICENSE
 *
 * This source file is subject to the new BSD license that is bundled
 * with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://framework.zend.com/license/new-bsd
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@zend.com so we can send you a copy immediately.
 *
 * @category   Zend
 * @package    Zend_Db
 * @subpackage Select
 * @copyright  Copyright (c) 2005-2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id$
 */


/**
 * @see Zend_Db_Adapter_Abstract
 */
require_once 'Zend/Db/Adapter/Abstract.php';

/**
 * @see Zend_Db_Expr
 */
require_once 'Zend/Db/Expr.php';


/**
 * Class for SQL SELECT generation and results.
 *
 * @category   Zend
 * @package    Zend_Db
 * @subpackage Select
 * @copyright  Copyright (c) 2005-2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 */
class Zend_Db_Select
{

    const DISTINCT = 'distinct';
    const COLUMNS = 'columns';
    const FROM = 'from';
    const UNION = 'union';
    const WHERE = 'where';
    const GROUP = 'group';
    const HAVING = 'having';
    const ORDER = 'order';
    const LIMIT_COUNT = 'limitcount';
    const LIMIT_OFFSET = 'limitoffset';
    const FOR_UPDATE = 'forupdate';

    const INNER_JOIN = 'inner join';
    const LEFT_JOIN = 'left join';
    const RIGHT_JOIN = 'right join';
    const FULL_JOIN = 'full join';
    const CROSS_JOIN = 'cross join';
    const NATURAL_JOIN = 'natural join';

    const SQL_WILDCARD = '*';
    const SQL_SELECT = 'SELECT';
    const SQL_UNION = 'UNION';
    const SQL_UNION_ALL = 'UNION ALL';
    const SQL_FROM = 'FROM';
    const SQL_WHERE = 'WHERE';
    const SQL_DISTINCT = 'DISTINCT';
    const SQL_GROUP_BY = 'GROUP BY';
    const SQL_ORDER_BY = 'ORDER BY';
    const SQL_HAVING = 'HAVING';
    const SQL_FOR_UPDATE = 'FOR UPDATE';
    const SQL_AND = 'AND';
    const SQL_AS = 'AS';
    const SQL_OR = 'OR';
    const SQL_ON = 'ON';
    const SQL_ASC = 'ASC';
    const SQL_DESC = 'DESC';

    const REGEX_COLUMN_EXPR = '/^([\w]*\s*\(([^\(\)]|(?1))*\))$/';
    const REGEX_COLUMN_EXPR_ORDER = '/^([\w]+\s*\(([^\(\)]|(?1))*\))$/';
    const REGEX_COLUMN_EXPR_GROUP = '/^([\w]+\s*\(([^\(\)]|(?1))*\))$/';

    // @see http://stackoverflow.com/a/13823184/2028814
    const REGEX_SQL_COMMENTS = '@
    (([\'"]).*?[^\\\]\2) # $1 : Skip single & double quoted expressions
    |(                   # $3 : Match comments
        (?:\#|--).*?$    # - Single line comments
        |                # - Multi line (nested) comments
         /\*             #   . comment open marker
            (?: [^/*]    #   . non comment-marker characters
                |/(?!\*) #   . ! not a comment open
                |\*(?!/) #   . ! not a comment close
                |(?R)    #   . recursive case
            )*           #   . repeat eventually
        \*\/             #   . comment close marker
    )\s*                 # Trim after comments
    |(?<=;)\s+           # Trim after semi-colon
    @msx';

    /**
     * Bind variables for query
     *
     * @var array
     */
    protected $_bind = array();

    /**
     * Zend_Db_Adapter_Abstract object.
     *
     * @var Zend_Db_Adapter_Abstract
     */
    protected $_adapter;

    /**
     * The initial values for the $_parts array.
     * NOTE: It is important for the 'FOR_UPDATE' part to be last to ensure
     * meximum compatibility with database adapters.
     *
     * @var array
     */
    protected static $_partsInit = array(
        self::DISTINCT => false,
        self::COLUMNS => array(),
        self::UNION => array(),
        self::FROM => array(),
        self::WHERE => array(),
        self::GROUP => array(),
        self::HAVING => array(),
        self::ORDER => array(),
        self::LIMIT_COUNT => null,
        self::LIMIT_OFFSET => null,
        self::FOR_UPDATE => false
    );

    /**
     * Specify legal join types.
     *
     * @var array
     */
    protected static $_joinTypes = array(
        self::INNER_JOIN,
        self::LEFT_JOIN,
        self::RIGHT_JOIN,
        self::FULL_JOIN,
        self::CROSS_JOIN,
        self::NATURAL_JOIN,
    );

    /**
     * Specify legal union types.
     *
     * @var array
     */
    protected static $_unionTypes = array(
        self::SQL_UNION,
        self::SQL_UNION_ALL
    );

    /**
     * The component parts of a SELECT statement.
     * Initialized to the $_partsInit array in the constructor.
     *
     * @var array
     */
    protected $_parts = array();

    /**
     * Tracks which columns are being select from each table and join.
     *
     * @var array
     */
    protected $_tableCols = array();

    /**
     * Class constructor
     *
     * @param Zend_Db_Adapter_Abstract $adapter
     */
    public function __construct(Zend_Db_Adapter_Abstract $adapter)
    {
        $this->_adapter = $adapter;
        $this->_parts = self::$_partsInit;
    }

    /**
     * Get bind variables
     *
     * @return array
     */
    public function getBind()
    {
        return $this->_bind;
    }

    /**
     * Set bind variables
     *
     * @param mixed $bind
     * @return Zend_Db_Select
     */
    public function bind($bind)
    {
        $this->_bind = $bind;

        return $this;
    }

    /**
     * Makes the query SELECT DISTINCT.
     *
     * @param bool $flag Whether or not the SELECT is DISTINCT (default true).
     * @return Zend_Db_Select This Zend_Db_Select object.
     */
    public function distinct($flag = true)
    {
        $this->_parts[self::DISTINCT] = (bool) $flag;
        return $this;
    }

    /**
     * Adds a FROM table and optional columns to the query.
     *
     * The first parameter $name can be a simple string, in which case the
     * correlation name is generated automatically.  If you want to specify
     * the correlation name, the first parameter must be an associative
     * array in which the key is the correlation name, and the value is
     * the physical table name.  For example, array('alias' => 'table').
     * The correlation name is prepended to all columns fetched for this
     * table.
     *
     * The second parameter can be a single string or Zend_Db_Expr object,
     * or else an array of strings or Zend_Db_Expr objects.
     *
     * The first parameter can be null or an empty string, in which case
     * no correlation name is generated or prepended to the columns named
     * in the second parameter.
     *
     * @param  array|string|Zend_Db_Expr $name The table name or an associative array
     *                                         relating correlation name to table name.
     * @param  array|string|Zend_Db_Expr $cols The columns to select from this table.
     * @param  string $schema The schema name to specify, if any.
     * @return Zend_Db_Select This Zend_Db_Select object.
     */
    public function from($name, $cols = '*', $schema = null)
    {
        return $this->_join(self::FROM, $name, null, $cols, $schema);
    }

    /**
     * Specifies the columns used in the FROM clause.
     *
     * The parameter can be a single string or Zend_Db_Expr object,
     * or else an array of strings or Zend_Db_Expr objects.
     *
     * @param  array|string|Zend_Db_Expr $cols The columns to select from this table.
     * @param  string $correlationName Correlation name of target table. OPTIONAL
     * @return Zend_Db_Select This Zend_Db_Select object.
     */
    public function columns($cols = '*', $correlationName = null)
    {
        if ($correlationName === null && count($this->_parts[self::FROM])) {
            $correlationNameKeys = array_keys($this->_parts[self::FROM]);
            $correlationName = current($correlationNameKeys);
        }

        if (! array_key_exists($correlationName, $this->_parts[self::FROM])) {
            /**
             * @see Zend_Db_Select_Exception
             */
            require_once 'Zend/Db/Select/Exception.php';
            throw new Zend_Db_Select_Exception("No table has been specified for the FROM clause");
        }

        $this->_tableCols($correlationName, $cols);

        return $this;
    }

    /**
     * Adds a UNION clause to the query.
     *
     * The first parameter has to be an array of Zend_Db_Select or
     * sql query strings.
     *
     * <code>
     * $sql1 = $db->select();
     * $sql2 = "SELECT ...";
     * $select = $db->select()
     *      ->union(array($sql1, $sql2))
     *      ->order("id");
     * </code>
     *
     * @param  array $select Array of select clauses for the union.
     * @return Zend_Db_Select This Zend_Db_Select object.
     */
    public function union($select = array(), $type = self::SQL_UNION)
    {
        if (! is_array($select)) {
            require_once 'Zend/Db/Select/Exception.php';
            throw new Zend_Db_Select_Exception(
                "union() only accepts an array of Zend_Db_Select instances of sql query strings."
            );
        }

        if (! in_array($type, self::$_unionTypes)) {
            require_once 'Zend/Db/Select/Exception.php';
            throw new Zend_Db_Select_Exception("Invalid union type '{$type}'");
        }

        foreach ($select as $target) {
            $this->_parts[self::UNION][] = array($target, $type);
        }

        return $this;
    }

    /**
     * Adds a JOIN table and columns to the query.
     *
     * The $name and $cols parameters follow the same logic
     * as described in the from() method.
     *
     * @param  array|string|Zend_Db_Expr $name The table name.
     * @param  string $cond Join on this condition.
     * @param  array|string $cols The columns to select from the joined table.
     * @param  string $schema The database name to specify, if any.
     * @return Zend_Db_Select This Zend_Db_Select object.
     */
    public function join($name, $cond, $cols = self::SQL_WILDCARD, $schema = null)
    {
        return $this->joinInner($name, $cond, $cols, $schema);
    }

    /**
     * Add an INNER JOIN table and colums to the query
     * Rows in both tables are matched according to the expression
     * in the $cond argument.  The result set is comprised
     * of all cases where rows from the left table match
     * rows from the right table.
     *
     * The $name and $cols parameters follow the same logic
     * as described in the from() method.
     *
     * @param  array|string|Zend_Db_Expr $name The table name.
     * @param  string $cond Join on this condition.
     * @param  array|string $cols The columns to select from the joined table.
     * @param  string $schema The database name to specify, if any.
     * @return Zend_Db_Select This Zend_Db_Select object.
     */
    public function joinInner($name, $cond, $cols = self::SQL_WILDCARD, $schema = null)
    {
        return $this->_join(self::INNER_JOIN, $name, $cond, $cols, $schema);
    }

    /**
     * Add a LEFT OUTER JOIN table and colums to the query
     * All rows from the left operand table are included,
     * matching rows from the right operand table included,
     * and the columns from the right operand table are filled
     * with NULLs if no row exists matching the left table.
     *
     * The $name and $cols parameters follow the same logic
     * as described in the from() method.
     *
     * @param  array|string|Zend_Db_Expr $name The table name.
     * @param  string $cond Join on this condition.
     * @param  array|string $cols The columns to select from the joined table.
     * @param  string $schema The database name to specify, if any.
     * @return Zend_Db_Select This Zend_Db_Select object.
     */
    public function joinLeft($name, $cond, $cols = self::SQL_WILDCARD, $schema = null)
    {
        return $this->_join(self::LEFT_JOIN, $name, $cond, $cols, $schema);
    }

    /**
     * Add a RIGHT OUTER JOIN table and colums to the query.
     * Right outer join is the complement of left outer join.
     * All rows from the right operand table are included,
     * matching rows from the left operand table included,
     * and the columns from the left operand table are filled
     * with NULLs if no row exists matching the right table.
     *
     * The $name and $cols parameters follow the same logic
     * as described in the from() method.
     *
     * @param  array|string|Zend_Db_Expr $name The table name.
     * @param  string $cond Join on this condition.
     * @param  array|string $cols The columns to select from the joined table.
     * @param  string $schema The database name to specify, if any.
     * @return Zend_Db_Select This Zend_Db_Select object.
     */
    public function joinRight($name, $cond, $cols = self::SQL_WILDCARD, $schema = null)
    {
        return $this->_join(self::RIGHT_JOIN, $name, $cond, $cols, $schema);
    }

    /**
     * Add a FULL OUTER JOIN table and colums to the query.
     * A full outer join is like combining a left outer join
     * and a right outer join.  All rows from both tables are
     * included, paired with each other on the same row of the
     * result set if they satisfy the join condition, and otherwise
     * paired with NULLs in place of columns from the other table.
     *
     * The $name and $cols parameters follow the same logic
     * as described in the from() method.
     *
     * @param  array|string|Zend_Db_Expr $name The table name.
     * @param  string $cond Join on this condition.
     * @param  array|string $cols The columns to select from the joined table.
     * @param  string $schema The database name to specify, if any.
     * @return Zend_Db_Select This Zend_Db_Select object.
     */
    public function joinFull($name, $cond, $cols = self::SQL_WILDCARD, $schema = null)
    {
        return $this->_join(self::FULL_JOIN, $name, $cond, $cols, $schema);
    }

    /**
     * Add a CROSS JOIN table and colums to the query.
     * A cross join is a cartesian product; there is no join condition.
     *
     * The $name and $cols parameters follow the same logic
     * as described in the from() method.
     *
     * @param  array|string|Zend_Db_Expr $name The table name.
     * @param  array|string $cols The columns to select from the joined table.
     * @param  string $schema The database name to specify, if any.
     * @return Zend_Db_Select This Zend_Db_Select object.
     */
    public function joinCross($name, $cols = self::SQL_WILDCARD, $schema = null)
    {
        return $this->_join(self::CROSS_JOIN, $name, null, $cols, $schema);
    }

    /**
     * Add a NATURAL JOIN table and colums to the query.
     * A natural join assumes an equi-join across any column(s)
     * that appear with the same name in both tables.
     * Only natural inner joins are supported by this API,
     * even though SQL permits natural outer joins as well.
     *
     * The $name and $cols parameters follow the same logic
     * as described in the from() method.
     *
     * @param  array|string|Zend_Db_Expr $name The table name.
     * @param  array|string $cols The columns to select from the joined table.
     * @param  string $schema The database name to specify, if any.
     * @return Zend_Db_Select This Zend_Db_Select object.
     */
    public function joinNatural($name, $cols = self::SQL_WILDCARD, $schema = null)
    {
        return $this->_join(self::NATURAL_JOIN, $name, null, $cols, $schema);
    }

    /**
     * Adds a WHERE condition to the query by AND.
     *
     * If a value is passed as the second param, it will be quoted
     * and replaced into the condition wherever a question-mark
     * appears. Array values are quoted and comma-separated.
     *
     * <code>
     * // simplest but non-secure
     * $select->where("id = $id");
     *
     * // secure (ID is quoted but matched anyway)
     * $select->where('id = ?', $id);
     *
     * // alternatively, with named binding
     * $select->where('id = :id');
     * </code>
     *
     * Note that it is more correct to use named bindings in your
     * queries for values other than strings. When you use named
     * bindings, don't forget to pass the values when actually
     * making a query:
     *
     * <code>
     * $db->fetchAll($select, array('id' => 5));
     * </code>
     *
     * @param string   $cond  The WHERE condition.
     * @param mixed    $value OPTIONAL The value to quote into the condition.
     * @param int      $type  OPTIONAL The type of the given value
     * @return Zend_Db_Select This Zend_Db_Select object.
     */
    public function where($cond, $value = null, $type = null)
    {
        $this->_parts[self::WHERE][] = $this->_where($cond, $value, $type, true);

        return $this;
    }

    /**
     * Adds a WHERE condition to the query by OR.
     *
     * Otherwise identical to where().
     *
     * @param string   $cond  The WHERE condition.
     * @param mixed    $value OPTIONAL The value to quote into the condition.
     * @param int      $type  OPTIONAL The type of the given value
     * @return Zend_Db_Select This Zend_Db_Select object.
     *
     * @see where()
     */
    public function orWhere($cond, $value = null, $type = null)
    {
        $this->_parts[self::WHERE][] = $this->_where($cond, $value, $type, false);

        return $this;
    }

    /**
     * Add an IN condition to the WHERE clause.
     *
     * @param  string  $cond
     * @param  array   $values
     * @return self
     */
    public function whereIn($cond, array $values)
    {
        if (empty($values)) {
            return $this->where('1 = 0');
        }

        if (is_string($cond) && ! str_contains($cond, '?') && ! str_contains($cond, ':')) {
            $cond .= ' IN (?)';
        }

        return $this->where($cond, $values);
    }

    /**
     * Add a NOT IN condition to the WHERE clause.
     *
     * @param  string  $cond
     * @param  array   $values
     * @return self
     */
    public function whereNotIn($cond, array $values)
    {
        if (empty($values)) {
            return $this;
        }

        if (is_string($cond) && ! str_contains($cond, '?') && ! str_contains($cond, ':')) {
            $cond .= ' NOT IN (?)';
        }

        return $this->where($cond, $values);
    }

    /**
     * Add an OR IN condition to the WHERE clause.
     *
     * @param  string  $cond
     * @param  array   $values
     * @return self
     */
    public function orWhereIn($cond, array $values)
    {
        if (empty($values)) {
            return $this->orWhere('1 = 0');
        }

        if (is_string($cond) && ! str_contains($cond, '?') && ! str_contains($cond, ':')) {
            $cond .= ' IN (?)';
        }

        return $this->orWhere($cond, $values);
    }

    /**
     * Add an OR NOT IN condition to the WHERE clause.
     *
     * @param  string  $cond
     * @param  array   $values
     * @return self
     */
    public function orWhereNotIn($cond, array $values)
    {
        if (empty($values)) {
            return $this;
        }

        if (is_string($cond) && ! str_contains($cond, '?') && ! str_contains($cond, ':')) {
            $cond .= ' NOT IN (?)';
        }

        return $this->orWhere($cond, $values);
    }

    /**
     * Add an IS NULL condition to the WHERE clause.
     *
     * @param  string  $column
     * @return self
     */
    public function whereNull($column)
    {
        return $this->where("{$column} IS NULL");
    }

    /**
     * Add an IS NOT NULL condition to the WHERE clause.
     *
     * @param  string  $column
     * @return self
     */
    public function whereNotNull($column)
    {
        return $this->where("{$column} IS NOT NULL");
    }

    /**
     * Add an OR IS NULL condition to the WHERE clause.
     *
     * @param  string  $column
     * @return self
     */
    public function orWhereNull($column)
    {
        return $this->orWhere("{$column} IS NULL");
    }

    /**
     * Add an OR IS NOT NULL condition to the WHERE clause.
     *
     * @param  string  $column
     * @return self
     */
    public function orWhereNotNull($column)
    {
        return $this->orWhere("{$column} IS NOT NULL");
    }

    /**
     * Add a DATE comparison condition to the WHERE clause.
     * E.g. $select->whereDate('created_at', '2026-11-18')
     *      $select->whereDate('created_at', '>=', '2026-11-18')
     *
     * @param  string  $column
     * @param  string  $operator
     * @param  mixed   $value
     * @return self
     */
    public function whereDate($column, $operator, $value = null)
    {
        list($op, $val) = $this->_normalizeOperatorAndValue($operator, $value);
        $expr = $this->_dateExpression('date', $column);

        return $this->where("{$expr} {$op} ?", $val);
    }

    /**
     * Add an OR DATE comparison condition to the WHERE clause.
     *
     * @param  string  $column
     * @param  string  $operator
     * @param  mixed   $value
     * @return self
     */
    public function orWhereDate($column, $operator, $value = null)
    {
        list($op, $val) = $this->_normalizeOperatorAndValue($operator, $value);
        $expr = $this->_dateExpression('date', $column);

        return $this->orWhere("{$expr} {$op} ?", $val);
    }

    /**
     * Add a DATE BETWEEN condition to the WHERE clause for reporting (inclusive).
     * Automatically extracts the DATE portion so datetime columns include entire end date.
     * Accepts either 2 parameters ($start, $end) or an array [$start, $end].
     *
     * E.g. $select->whereDateBetween('created_at', '2026-11-01', '2026-11-30')
     *      $select->whereDateBetween('created_at', [$from, $to])
     *
     * @param  string        $column
     * @param  array|string  $start
     * @param  string|null   $end
     * @return self
     */
    public function whereDateBetween($column, $start, $end = null)
    {
        if (is_array($start)) {
            $end = isset($start[1]) ? $start[1] : null;
            $start = isset($start[0]) ? $start[0] : null;
        }

        if ($start === null || $end === null || $start === '' || $end === '') {
            return $this;
        }

        $expr = $this->_dateExpression('date', $column);
        $min = $this->_adapter->quote((string) $start);
        $max = $this->_adapter->quote((string) $end);

        return $this->where("{$expr} BETWEEN {$min} AND {$max}");
    }

    /**
     * Alias for whereDateBetween().
     *
     * @param  string        $column
     * @param  array|string  $start
     * @param  string|null   $end
     * @return self
     */
    public function whereBetweenDates($column, $start, $end = null)
    {
        return $this->whereDateBetween($column, $start, $end);
    }

    /**
     * Add an OR DATE BETWEEN condition to the WHERE clause.
     *
     * @param  string        $column
     * @param  array|string  $start
     * @param  string|null   $end
     * @return self
     */
    public function orWhereDateBetween($column, $start, $end = null)
    {
        if (is_array($start)) {
            $end = isset($start[1]) ? $start[1] : null;
            $start = isset($start[0]) ? $start[0] : null;
        }

        if ($start === null || $end === null || $start === '' || $end === '') {
            return $this;
        }

        $expr = $this->_dateExpression('date', $column);
        $min = $this->_adapter->quote((string) $start);
        $max = $this->_adapter->quote((string) $end);

        return $this->orWhere("{$expr} BETWEEN {$min} AND {$max}");
    }

    /**
     * Add a DATE NOT BETWEEN condition to the WHERE clause.
     *
     * @param  string        $column
     * @param  array|string  $start
     * @param  string|null   $end
     * @return self
     */
    public function whereNotDateBetween($column, $start, $end = null)
    {
        if (is_array($start)) {
            $end = isset($start[1]) ? $start[1] : null;
            $start = isset($start[0]) ? $start[0] : null;
        }

        if ($start === null || $end === null || $start === '' || $end === '') {
            return $this;
        }

        $expr = $this->_dateExpression('date', $column);
        $min = $this->_adapter->quote((string) $start);
        $max = $this->_adapter->quote((string) $end);

        return $this->where("{$expr} NOT BETWEEN {$min} AND {$max}");
    }

    /**
     * Add an OR DATE NOT BETWEEN condition to the WHERE clause.
     *
     * @param  string        $column
     * @param  array|string  $start
     * @param  string|null   $end
     * @return self
     */
    public function orWhereNotDateBetween($column, $start, $end = null)
    {
        if (is_array($start)) {
            $end = isset($start[1]) ? $start[1] : null;
            $start = isset($start[0]) ? $start[0] : null;
        }

        if ($start === null || $end === null || $start === '' || $end === '') {
            return $this;
        }

        $expr = $this->_dateExpression('date', $column);
        $min = $this->_adapter->quote((string) $start);
        $max = $this->_adapter->quote((string) $end);

        return $this->orWhere("{$expr} NOT BETWEEN {$min} AND {$max}");
    }

    /**
     * Fluent date filter for reporting dashboards.
     * Automatically handles:
     * - Both dates provided: WHERE DATE(col) BETWEEN start AND end
     * - Only start date provided: WHERE DATE(col) >= start
     * - Only end date provided: WHERE DATE(col) <= end
     * - Neither provided: skips condition without breaking query chain
     *
     * @param  string             $column
     * @param  array|string|null  $start
     * @param  string|null        $end
     * @return self
     */
    public function whereDateRange($column, $start = null, $end = null)
    {
        if (is_array($start)) {
            $end = isset($start[1]) ? $start[1] : null;
            $start = isset($start[0]) ? $start[0] : null;
        }

        $hasStart = $start !== null && $start !== '';
        $hasEnd = $end !== null && $end !== '';

        if ($hasStart && $hasEnd) {
            return $this->whereDateBetween($column, $start, $end);
        }

        if ($hasStart) {
            return $this->whereDate($column, '>=', $start);
        }

        if ($hasEnd) {
            return $this->whereDate($column, '<=', $end);
        }

        return $this;
    }

    /**
     * Add a YEAR comparison condition to the WHERE clause.
     *
     * @param  string  $column
     * @param  string  $operator
     * @param  mixed   $value
     * @return self
     */
    public function whereYear($column, $operator, $value = null)
    {
        list($op, $val) = $this->_normalizeOperatorAndValue($operator, $value);
        $expr = $this->_dateExpression('year', $column);

        return $this->where("{$expr} {$op} ?", (int) $val);
    }

    /**
     * Add an OR YEAR comparison condition to the WHERE clause.
     *
     * @param  string  $column
     * @param  string  $operator
     * @param  mixed   $value
     * @return self
     */
    public function orWhereYear($column, $operator, $value = null)
    {
        list($op, $val) = $this->_normalizeOperatorAndValue($operator, $value);
        $expr = $this->_dateExpression('year', $column);

        return $this->orWhere("{$expr} {$op} ?", (int) $val);
    }

    /**
     * Add a MONTH comparison condition to the WHERE clause.
     *
     * @param  string  $column
     * @param  string  $operator
     * @param  mixed   $value
     * @return self
     */
    public function whereMonth($column, $operator, $value = null)
    {
        list($op, $val) = $this->_normalizeOperatorAndValue($operator, $value);
        $expr = $this->_dateExpression('month', $column);

        return $this->where("{$expr} {$op} ?", (int) $val);
    }

    /**
     * Add an OR MONTH comparison condition to the WHERE clause.
     *
     * @param  string  $column
     * @param  string  $operator
     * @param  mixed   $value
     * @return self
     */
    public function orWhereMonth($column, $operator, $value = null)
    {
        list($op, $val) = $this->_normalizeOperatorAndValue($operator, $value);
        $expr = $this->_dateExpression('month', $column);

        return $this->orWhere("{$expr} {$op} ?", (int) $val);
    }

    /**
     * Add a DAY comparison condition to the WHERE clause.
     *
     * @param  string  $column
     * @param  string  $operator
     * @param  mixed   $value
     * @return self
     */
    public function whereDay($column, $operator, $value = null)
    {
        list($op, $val) = $this->_normalizeOperatorAndValue($operator, $value);
        $expr = $this->_dateExpression('day', $column);

        return $this->where("{$expr} {$op} ?", (int) $val);
    }

    /**
     * Add an OR DAY comparison condition to the WHERE clause.
     *
     * @param  string  $column
     * @param  string  $operator
     * @param  mixed   $value
     * @return self
     */
    public function orWhereDay($column, $operator, $value = null)
    {
        list($op, $val) = $this->_normalizeOperatorAndValue($operator, $value);
        $expr = $this->_dateExpression('day', $column);

        return $this->orWhere("{$expr} {$op} ?", (int) $val);
    }

    /**
     * Add a TIME comparison condition to the WHERE clause.
     *
     * @param  string  $column
     * @param  string  $operator
     * @param  mixed   $value
     * @return self
     */
    public function whereTime($column, $operator, $value = null)
    {
        list($op, $val) = $this->_normalizeOperatorAndValue($operator, $value);
        $expr = $this->_dateExpression('time', $column);

        return $this->where("{$expr} {$op} ?", $val);
    }

    /**
     * Add an OR TIME comparison condition to the WHERE clause.
     *
     * @param  string  $column
     * @param  string  $operator
     * @param  mixed   $value
     * @return self
     */
    public function orWhereTime($column, $operator, $value = null)
    {
        list($op, $val) = $this->_normalizeOperatorAndValue($operator, $value);
        $expr = $this->_dateExpression('time', $column);

        return $this->orWhere("{$expr} {$op} ?", $val);
    }

    /**
     * Add a BETWEEN condition to the WHERE clause.
     *
     * @param  string  $column
     * @param  array   $values
     * @return self
     */
    public function whereBetween($column, array $values)
    {
        $values = array_values($values);
        if (count($values) < 2) {
            return $this;
        }

        $min = $this->_adapter->quote($values[0]);
        $max = $this->_adapter->quote($values[1]);

        return $this->where("{$column} BETWEEN {$min} AND {$max}");
    }

    /**
     * Add a NOT BETWEEN condition to the WHERE clause.
     *
     * @param  string  $column
     * @param  array   $values
     * @return self
     */
    public function whereNotBetween($column, array $values)
    {
        $values = array_values($values);
        if (count($values) < 2) {
            return $this;
        }

        $min = $this->_adapter->quote($values[0]);
        $max = $this->_adapter->quote($values[1]);

        return $this->where("{$column} NOT BETWEEN {$min} AND {$max}");
    }

    /**
     * Add an OR BETWEEN condition to the WHERE clause.
     *
     * @param  string  $column
     * @param  array   $values
     * @return self
     */
    public function orWhereBetween($column, array $values)
    {
        $values = array_values($values);
        if (count($values) < 2) {
            return $this;
        }

        $min = $this->_adapter->quote($values[0]);
        $max = $this->_adapter->quote($values[1]);

        return $this->orWhere("{$column} BETWEEN {$min} AND {$max}");
    }

    /**
     * Add an OR NOT BETWEEN condition to the WHERE clause.
     *
     * @param  string  $column
     * @param  array   $values
     * @return self
     */
    public function orWhereNotBetween($column, array $values)
    {
        $values = array_values($values);
        if (count($values) < 2) {
            return $this;
        }

        $min = $this->_adapter->quote($values[0]);
        $max = $this->_adapter->quote($values[1]);

        return $this->orWhere("{$column} NOT BETWEEN {$min} AND {$max}");
    }

    /**
     * Add a LIKE condition to the WHERE clause.
     *
     * @param  string  $column
     * @param  string  $value
     * @return self
     */
    public function whereLike($column, $value)
    {
        return $this->where("{$column} LIKE ?", $value);
    }

    /**
     * Add a NOT LIKE condition to the WHERE clause.
     *
     * @param  string  $column
     * @param  string  $value
     * @return self
     */
    public function whereNotLike($column, $value)
    {
        return $this->where("{$column} NOT LIKE ?", $value);
    }

    /**
     * Add an OR LIKE condition to the WHERE clause.
     *
     * @param  string  $column
     * @param  string  $value
     * @return self
     */
    public function orWhereLike($column, $value)
    {
        return $this->orWhere("{$column} LIKE ?", $value);
    }

    /**
     * Add an OR NOT LIKE condition to the WHERE clause.
     *
     * @param  string  $column
     * @param  string  $value
     * @return self
     */
    public function orWhereNotLike($column, $value)
    {
        return $this->orWhere("{$column} NOT LIKE ?", $value);
    }

    /**
     * Add a raw condition to the WHERE clause with bindings.
     *
     * @param  string  $sql
     * @param  array   $bind
     * @return self
     */
    public function whereRaw($sql, array $bind = array())
    {
        if (empty($bind)) {
            return $this->where($sql);
        }

        foreach ($bind as $val) {
            $quoted = $this->_adapter->quote($val);
            $sql = preg_replace('/\?/', $quoted, $sql, 1);
        }

        return $this->where($sql);
    }

    /**
     * Add a condition comparing two columns to the WHERE clause.
     * E.g. $select->whereColumn('updated_at', '>', 'created_at')
     *      $select->whereColumn('first_name', 'last_name')
     *
     * @param  string       $first
     * @param  string       $operator
     * @param  string|null  $second
     * @return self
     */
    public function whereColumn($first, $operator, $second = null)
    {
        list($op, $sec) = $this->_normalizeOperatorAndValue($operator, $second);
        $quotedFirst = $this->_adapter->quoteIdentifier($first);
        $quotedSecond = $this->_adapter->quoteIdentifier($sec);

        return $this->where("{$quotedFirst} {$op} {$quotedSecond}");
    }

    /**
     * Add an OR condition comparing two columns to the WHERE clause.
     *
     * @param  string       $first
     * @param  string       $operator
     * @param  string|null  $second
     * @return self
     */
    public function orWhereColumn($first, $operator, $second = null)
    {
        list($op, $sec) = $this->_normalizeOperatorAndValue($operator, $second);
        $quotedFirst = $this->_adapter->quoteIdentifier($first);
        $quotedSecond = $this->_adapter->quoteIdentifier($sec);

        return $this->orWhere("{$quotedFirst} {$op} {$quotedSecond}");
    }

    /**
     * Add an OR raw condition to the WHERE clause with bindings.
     *
     * @param  string  $sql
     * @param  array   $bind
     * @return self
     */
    public function orWhereRaw($sql, array $bind = array())
    {
        if (empty($bind)) {
            return $this->orWhere($sql);
        }

        foreach ($bind as $val) {
            $quoted = $this->_adapter->quote($val);
            $sql = preg_replace('/\?/', $quoted, $sql, 1);
        }

        return $this->orWhere($sql);
    }

    /**
     * Add an "ORDER BY column DESC" clause to the query.
     *
     * @param  string  $column
     * @return self
     */
    public function latest($column = 'created_at')
    {
        return $this->order("{$column} ".self::SQL_DESC);
    }

    /**
     * Add an "ORDER BY column ASC" clause to the query.
     *
     * @param  string  $column
     * @return self
     */
    public function oldest($column = 'created_at')
    {
        return $this->order("{$column} ".self::SQL_ASC);
    }

    /**
     * Order the query results randomly.
     *
     * @return self
     */
    public function inRandomOrder()
    {
        $adapterClass = get_class($this->_adapter);
        $randomFunc = str_contains($adapterClass, 'Sqlite') || str_contains($adapterClass, 'Pgsql') ? 'RANDOM()' : 'RAND()';

        return $this->order(new Zend_Db_Expr($randomFunc));
    }

    /**
     * Alias for limit($count). Limit the number of returned records.
     *
     * @param  int  $limit
     * @return self
     */
    public function take($limit)
    {
        $this->_parts[self::LIMIT_COUNT] = (int) $limit;

        return $this;
    }

    /**
     * Alias for limit($count, $offset). Set the record offset.
     *
     * @param  int  $offset
     * @return self
     */
    public function skip($offset)
    {
        $this->_parts[self::LIMIT_OFFSET] = (int) $offset;

        return $this;
    }

    /**
     * Normalize operator and value when 2 or 3 arguments are passed.
     *
     * @param  string  $operator
     * @param  mixed   $value
     * @return array
     */
    protected function _normalizeOperatorAndValue($operator, $value = null)
    {
        if ($value === null) {
            return array('=', $operator);
        }

        $cleanOp = strtoupper(trim((string) $operator));
        $validOperators = array(
            '=', '<', '>', '<=', '>=', '<>', '!=', '<=>',
            'LIKE', 'LIKE BINARY', 'NOT LIKE', 'ILIKE',
            '&', '|', '^', '<<', '>>', 'RLIKE', 'REGEXP', 'NOT REGEXP',
        );

        if (! in_array($cleanOp, $validOperators, true)) {
            require_once 'Zend/Db/Select/Exception.php';
            throw new Zend_Db_Select_Exception("Invalid SQL comparison operator '{$operator}'");
        }

        return array($cleanOp, $value);
    }

    /**
     * Format a date extraction expression for the active database adapter.
     *
     * @param  string  $type
     * @param  string  $column
     * @return string
     */
    protected function _dateExpression($type, $column)
    {
        $adapterClass = get_class($this->_adapter);

        if (str_contains($adapterClass, 'Sqlite')) {
            return match ($type) {
                'year' => "CAST(strftime('%Y', {$column}) AS INTEGER)",
                'month' => "CAST(strftime('%m', {$column}) AS INTEGER)",
                'day' => "CAST(strftime('%d', {$column}) AS INTEGER)",
                'time' => "time({$column})",
                default => "date({$column})",
            };
        }

        if (str_contains($adapterClass, 'Pgsql')) {
            return match ($type) {
                'year' => "EXTRACT(YEAR FROM {$column})",
                'month' => "EXTRACT(MONTH FROM {$column})",
                'day' => "EXTRACT(DAY FROM {$column})",
                'time' => "({$column})::time",
                default => "({$column})::date",
            };
        }

        return match ($type) {
            'year' => "YEAR({$column})",
            'month' => "MONTH({$column})",
            'day' => "DAY({$column})",
            'time' => "TIME({$column})",
            default => "DATE({$column})",
        };
    }

    /**
     * Adds grouping to the query.
     *
     * @param  array|string $spec The column(s) to group by.
     * @return Zend_Db_Select This Zend_Db_Select object.
     */
    public function group($spec)
    {
        if (! is_array($spec)) {
            $spec = array($spec);
        }

        foreach ($spec as $val) {
            // Remove comments from SQL statement
            $noComments = preg_replace(self::REGEX_SQL_COMMENTS, '$1', (string) $val);
            if (preg_match(self::REGEX_COLUMN_EXPR_GROUP, $noComments)) {
                $val = new Zend_Db_Expr($val);
            }
            $this->_parts[self::GROUP][] = $val;
        }

        return $this;
    }

    /**
     * Adds a HAVING condition to the query by AND.
     *
     * If a value is passed as the second param, it will be quoted
     * and replaced into the condition wherever a question-mark
     * appears. See {@link where()} for an example
     *
     * @param string $cond The HAVING condition.
     * @param mixed    $value OPTIONAL The value to quote into the condition.
     * @param int      $type  OPTIONAL The type of the given value
     * @return Zend_Db_Select This Zend_Db_Select object.
     */
    public function having($cond, $value = null, $type = null)
    {
        if ($value !== null) {
            $cond = $this->_adapter->quoteInto($cond, $value, $type);
        }

        if ($this->_parts[self::HAVING]) {
            $this->_parts[self::HAVING][] = self::SQL_AND." ($cond)";
        } else {
            $this->_parts[self::HAVING][] = "($cond)";
        }

        return $this;
    }

    /**
     * Adds a HAVING condition to the query by OR.
     *
     * Otherwise identical to orHaving().
     *
     * @param string $cond The HAVING condition.
     * @param mixed    $value OPTIONAL The value to quote into the condition.
     * @param int      $type  OPTIONAL The type of the given value
     * @return Zend_Db_Select This Zend_Db_Select object.
     *
     * @see having()
     */
    public function orHaving($cond, $value = null, $type = null)
    {
        if ($value !== null) {
            $cond = $this->_adapter->quoteInto($cond, $value, $type);
        }

        if ($this->_parts[self::HAVING]) {
            $this->_parts[self::HAVING][] = self::SQL_OR." ($cond)";
        } else {
            $this->_parts[self::HAVING][] = "($cond)";
        }

        return $this;
    }

    /**
     * Adds a row order to the query.
     *
     * @param mixed $spec The column(s) and direction to order by.
     * @return Zend_Db_Select This Zend_Db_Select object.
     */
    public function order($spec)
    {
        if (! is_array($spec)) {
            $spec = array($spec);
        }

        // force 'ASC' or 'DESC' on each order spec, default is ASC.
        foreach ($spec as $val) {
            if ($val instanceof Zend_Db_Expr) {
                $expr = $val->__toString();
                if (empty($expr)) {
                    continue;
                }
                $this->_parts[self::ORDER][] = $val;
            } else {
                if (empty($val)) {
                    continue;
                }
                $direction = self::SQL_ASC;
                if (preg_match('/(.*\W)('.self::SQL_ASC.'|'.self::SQL_DESC.')\b/si', $val, $matches)) {
                    $val = trim($matches[1]);
                    $direction = $matches[2];
                }
                // Remove comments from SQL statement
                $noComments = preg_replace(self::REGEX_SQL_COMMENTS, '$1', (string) $val);
                if (preg_match(self::REGEX_COLUMN_EXPR_ORDER, $noComments)) {
                    $val = new Zend_Db_Expr($val);
                }
                $this->_parts[self::ORDER][] = array($val, $direction);
            }
        }

        return $this;
    }

    /**
     * Sets a limit count and offset to the query.
     *
     * @param int $count OPTIONAL The number of rows to return.
     * @param int $offset OPTIONAL Start returning after this many rows.
     * @return Zend_Db_Select This Zend_Db_Select object.
     */
    public function limit($count = null, $offset = null)
    {
        $this->_parts[self::LIMIT_COUNT] = (int) $count;
        $this->_parts[self::LIMIT_OFFSET] = (int) $offset;
        return $this;
    }

    /**
     * Sets the limit and count by page number.
     *
     * @param int $page Limit results to this page number.
     * @param int $rowCount Use this many rows per page.
     * @return Zend_Db_Select This Zend_Db_Select object.
     */
    public function limitPage($page, $rowCount)
    {
        $page = ($page > 0) ? $page : 1;
        $rowCount = ($rowCount > 0) ? $rowCount : 1;
        $this->_parts[self::LIMIT_COUNT] = (int) $rowCount;
        $this->_parts[self::LIMIT_OFFSET] = (int) $rowCount * ($page - 1);
        return $this;
    }

    /**
     * Makes the query SELECT FOR UPDATE.
     *
     * @param bool $flag Whether or not the SELECT is FOR UPDATE (default true).
     * @return Zend_Db_Select This Zend_Db_Select object.
     */
    public function forUpdate($flag = true)
    {
        $this->_parts[self::FOR_UPDATE] = (bool) $flag;
        return $this;
    }

    /**
     * Get part of the structured information for the current query.
     *
     * @param string $part
     * @return mixed
     * @throws Zend_Db_Select_Exception
     */
    public function getPart($part)
    {
        $part = strtolower($part);
        if (! array_key_exists($part, $this->_parts)) {
            require_once 'Zend/Db/Select/Exception.php';
            throw new Zend_Db_Select_Exception("Invalid Select part '$part'");
        }
        return $this->_parts[$part];
    }

    /**
     * Executes the current select object and returns the result
     *
     * @param integer $fetchMode OPTIONAL
     * @param  mixed  $bind An array of data to bind to the placeholders.
     * @return PDO_Statement|Zend_Db_Statement
     */
    public function query($fetchMode = null, $bind = array())
    {
        if (! empty($bind)) {
            $this->bind($bind);
        }

        $stmt = $this->_adapter->query($this);
        if ($fetchMode == null) {
            $fetchMode = $this->_adapter->getFetchMode();
        }
        $stmt->setFetchMode($fetchMode);
        return $stmt;
    }

    /**
     * Converts this object to an SQL SELECT string.
     *
     * @return string|null This object as a SELECT string. (or null if a string cannot be produced.)
     */
    public function assemble()
    {
        $sql = self::SQL_SELECT;
        foreach (array_keys(self::$_partsInit) as $part) {
            $method = '_render'.ucfirst($part);
            if (method_exists($this, $method)) {
                $sql = $this->$method($sql);
            }
        }
        return $sql;
    }

    /**
     * Clear parts of the Select object, or an individual part.
     *
     * @param string $part OPTIONAL
     * @return Zend_Db_Select
     */
    public function reset($part = null)
    {
        if ($part == null) {
            $this->_parts = self::$_partsInit;
        } elseif (array_key_exists($part, self::$_partsInit)) {
            $this->_parts[$part] = self::$_partsInit[$part];
        }
        return $this;
    }

    /**
     * Gets the Zend_Db_Adapter_Abstract for this
     * particular Zend_Db_Select object.
     *
     * @return Zend_Db_Adapter_Abstract
     */
    public function getAdapter()
    {
        return $this->_adapter;
    }

    /**
     * Populate the {@link $_parts} 'join' key
     *
     * Does the dirty work of populating the join key.
     *
     * The $name and $cols parameters follow the same logic
     * as described in the from() method.
     *
     * @param  null|string $type Type of join; inner, left, and null are currently supported
     * @param  array|string|Zend_Db_Expr $name Table name
     * @param  string $cond Join on this condition
     * @param  array|string $cols The columns to select from the joined table
     * @param  string $schema The database name to specify, if any.
     * @return Zend_Db_Select This Zend_Db_Select object
     * @throws Zend_Db_Select_Exception
     */
    protected function _join($type, $name, $cond, $cols, $schema = null)
    {
        if (! in_array($type, self::$_joinTypes) && $type != self::FROM) {
            /**
             * @see Zend_Db_Select_Exception
             */
            require_once 'Zend/Db/Select/Exception.php';
            throw new Zend_Db_Select_Exception("Invalid join type '$type'");
        }

        if (count($this->_parts[self::UNION])) {
            require_once 'Zend/Db/Select/Exception.php';
            throw new Zend_Db_Select_Exception("Invalid use of table with ".self::SQL_UNION);
        }

        if (empty($name)) {
            $correlationName = $tableName = '';
        } elseif (is_array($name)) {
            // Must be array($correlationName => $tableName) or array($ident, ...)
            foreach ($name as $_correlationName => $_tableName) {
                if (is_string($_correlationName)) {
                    // We assume the key is the correlation name and value is the table name
                    $tableName = $_tableName;
                    $correlationName = $_correlationName;
                } else {
                    // We assume just an array of identifiers, with no correlation name
                    $tableName = $_tableName;
                    $correlationName = $this->_uniqueCorrelation($tableName);
                }
                break;
            }
        } elseif ($name instanceof Zend_Db_Expr || $name instanceof Zend_Db_Select) {
            $tableName = $name;
            $correlationName = $this->_uniqueCorrelation('t');
        } elseif (preg_match('/^(.+)\s+AS\s+(.+)$/i', $name, $m)) {
            $tableName = $m[1];
            $correlationName = $m[2];
        } else {
            $tableName = $name;
            $correlationName = $this->_uniqueCorrelation($tableName);
        }

        // Schema from table name overrides schema argument
        if (! is_object($tableName) && false !== strpos($tableName, '.')) {
            list($schema, $tableName) = explode('.', $tableName);
        }

        $lastFromCorrelationName = null;
        if (! empty($correlationName)) {
            if (array_key_exists($correlationName, $this->_parts[self::FROM])) {
                /**
                 * @see Zend_Db_Select_Exception
                 */
                require_once 'Zend/Db/Select/Exception.php';
                throw new Zend_Db_Select_Exception("You cannot define a correlation name '$correlationName' more than once");
            }

            if ($type == self::FROM) {
                // append this from after the last from joinType
                $tmpFromParts = $this->_parts[self::FROM];
                $this->_parts[self::FROM] = array();
                // move all the froms onto the stack
                while ($tmpFromParts) {
                    $currentCorrelationName = key($tmpFromParts);
                    if ($tmpFromParts[$currentCorrelationName]['joinType'] != self::FROM) {
                        break;
                    }
                    $lastFromCorrelationName = $currentCorrelationName;
                    $this->_parts[self::FROM][$currentCorrelationName] = array_shift($tmpFromParts);
                }
            } else {
                $tmpFromParts = array();
            }
            $this->_parts[self::FROM][$correlationName] = array(
                'joinType' => $type,
                'schema' => $schema,
                'tableName' => $tableName,
                'joinCondition' => $cond
            );
            while ($tmpFromParts) {
                $currentCorrelationName = key($tmpFromParts);
                $this->_parts[self::FROM][$currentCorrelationName] = array_shift($tmpFromParts);
            }
        }

        // add to the columns from this joined table
        if ($type == self::FROM && $lastFromCorrelationName == null) {
            $lastFromCorrelationName = true;
        }
        $this->_tableCols($correlationName, $cols, $lastFromCorrelationName);

        return $this;
    }

    /**
     * Handle JOIN... USING... syntax
     *
     * This is functionality identical to the existing JOIN methods, however
     * the join condition can be passed as a single column name. This method
     * then completes the ON condition by using the same field for the FROM
     * table and the JOIN table.
     *
     * <code>
     * $select = $db->select()->from('table1')
     *                        ->joinUsing('table2', 'column1');
     *
     * // SELECT * FROM table1 JOIN table2 ON table1.column1 = table2.column2
     * </code>
     *
     * These joins are called by the developer simply by adding 'Using' to the
     * method name. E.g.
     * * joinUsing
     * * joinInnerUsing
     * * joinFullUsing
     * * joinRightUsing
     * * joinLeftUsing
     *
     * @return Zend_Db_Select This Zend_Db_Select object.
     */
    public function _joinUsing($type, $name, $cond, $cols = '*', $schema = null)
    {
        if (empty($this->_parts[self::FROM])) {
            require_once 'Zend/Db/Select/Exception.php';
            throw new Zend_Db_Select_Exception("You can only perform a joinUsing after specifying a FROM table");
        }

        $join = $this->_adapter->quoteIdentifier(key($this->_parts[self::FROM]), true);
        $from = $this->_adapter->quoteIdentifier($this->_uniqueCorrelation($name), true);

        $joinCond = array();
        foreach ((array) $cond as $fieldName) {
            $cond1 = $from.'.'.$fieldName;
            $cond2 = $join.'.'.$fieldName;
            $joinCond[] = $cond1.' = '.$cond2;
        }
        $cond = implode(' '.self::SQL_AND.' ', $joinCond);

        return $this->_join($type, $name, $cond, $cols, $schema);
    }

    /**
     * Generate a unique correlation name
     *
     * @param string|array $name A qualified identifier.
     * @return string A unique correlation name.
     */
    private function _uniqueCorrelation($name)
    {
        if (is_array($name)) {
            $k = key($name);
            $c = is_string($k) ? $k : end($name);
        } else {
            // Extract just the last name of a qualified table name
            $dot = strrpos($name, '.');
            $c = ($dot === false) ? $name : substr($name, $dot + 1);
        }
        for ($i = 2; array_key_exists($c, $this->_parts[self::FROM]); ++$i) {
            $c = $name.'_'.(string) $i;
        }
        return $c;
    }

    /**
     * Adds to the internal table-to-column mapping array.
     *
     * @param  string $tbl The table/join the columns come from.
     * @param  array|string $cols The list of columns; preferably as
     * an array, but possibly as a string containing one column.
     * @param  bool|string True if it should be prepended, a correlation name if it should be inserted
     * @return void
     */
    protected function _tableCols($correlationName, $cols, $afterCorrelationName = null)
    {
        if (! is_array($cols)) {
            $cols = array($cols);
        }

        if ($correlationName == null) {
            $correlationName = '';
        }

        $columnValues = array();

        foreach (array_filter($cols) as $alias => $col) {
            $currentCorrelationName = $correlationName;
            if (is_string($col)) {
                // Check for a column matching "<column> AS <alias>" and extract the alias name
                $col = trim(str_replace("\n", ' ', $col));
                if (preg_match('/^(.+)\s+'.self::SQL_AS.'\s+(.+)$/i', $col, $m)) {
                    $col = $m[1];
                    $alias = $m[2];
                }
                // Check for columns that look like functions and convert to Zend_Db_Expr
                if (preg_match(self::REGEX_COLUMN_EXPR, (string) $col)) {
                    $col = new Zend_Db_Expr($col);
                } elseif (preg_match('/(.+)\.(.+)/', $col, $m)) {
                    $currentCorrelationName = $m[1];
                    $col = $m[2];
                }
            }
            $columnValues[] = array($currentCorrelationName, $col, is_string($alias) ? $alias : null);
        }

        if ($columnValues) {

            // should we attempt to prepend or insert these values?
            if ($afterCorrelationName === true || is_string($afterCorrelationName)) {
                $tmpColumns = $this->_parts[self::COLUMNS];
                $this->_parts[self::COLUMNS] = array();
            } else {
                $tmpColumns = array();
            }

            // find the correlation name to insert after
            if (is_string($afterCorrelationName)) {
                while ($tmpColumns) {
                    $this->_parts[self::COLUMNS][] = $currentColumn = array_shift($tmpColumns);
                    if ($currentColumn[0] == $afterCorrelationName) {
                        break;
                    }
                }
            }

            // apply current values to current stack
            foreach ($columnValues as $columnValue) {
                array_push($this->_parts[self::COLUMNS], $columnValue);
            }

            // finish ensuring that all previous values are applied (if they exist)
            while ($tmpColumns) {
                array_push($this->_parts[self::COLUMNS], array_shift($tmpColumns));
            }
        }
    }

    /**
     * Internal function for creating the where clause
     *
     * @param string   $condition
     * @param mixed    $value  optional
     * @param string   $type   optional
     * @param boolean  $bool  true = AND, false = OR
     * @return string  clause
     */
    protected function _where($condition, $value = null, $type = null, $bool = true)
    {
        if (count($this->_parts[self::UNION])) {
            require_once 'Zend/Db/Select/Exception.php';
            throw new Zend_Db_Select_Exception("Invalid use of where clause with ".self::SQL_UNION);
        }

        if ($value !== null) {
            if (is_string($condition) && ! str_contains($condition, '?') && ! str_contains($condition, ':') && ! preg_match('/\s*(=|<|>|!|LIKE|IN|IS)\s*/i', $condition)) {
                $condition .= is_array($value) ? ' IN (?)' : ' = ?';
            }
            $condition = $this->_adapter->quoteInto($condition, $value, $type);
        }

        $cond = "";
        if ($this->_parts[self::WHERE]) {
            if ($bool === true) {
                $cond = self::SQL_AND.' ';
            } else {
                $cond = self::SQL_OR.' ';
            }
        }

        return $cond."($condition)";
    }

    /**
     * @return array
     */
    protected function _getDummyTable()
    {
        return array();
    }

    /**
     * Return a quoted schema name
     *
     * @param string   $schema  The schema name OPTIONAL
     * @return string|null
     */
    protected function _getQuotedSchema($schema = null)
    {
        if ($schema === null) {
            return null;
        }
        return $this->_adapter->quoteIdentifier($schema, true).'.';
    }

    /**
     * Return a quoted table name
     *
     * @param string   $tableName        The table name
     * @param string   $correlationName  The correlation name OPTIONAL
     * @return string
     */
    protected function _getQuotedTable($tableName, $correlationName = null)
    {
        return $this->_adapter->quoteTableAs($tableName, $correlationName, true);
    }

    /**
     * Render DISTINCT clause
     *
     * @param string   $sql SQL query
     * @return string
     */
    protected function _renderDistinct($sql)
    {
        if ($this->_parts[self::DISTINCT]) {
            $sql .= ' '.self::SQL_DISTINCT;
        }

        return $sql;
    }

    /**
     * Render DISTINCT clause
     *
     * @param string   $sql SQL query
     * @return string|null
     */
    protected function _renderColumns($sql)
    {
        if (! count($this->_parts[self::COLUMNS])) {
            return null;
        }

        $columns = array();
        foreach ($this->_parts[self::COLUMNS] as $columnEntry) {
            list($correlationName, $column, $alias) = $columnEntry;
            if ($column instanceof Zend_Db_Expr) {
                $columns[] = $this->_adapter->quoteColumnAs($column, $alias, true);
            } else {
                if ($column == self::SQL_WILDCARD) {
                    $column = new Zend_Db_Expr(self::SQL_WILDCARD);
                    $alias = null;
                }
                if (empty($correlationName)) {
                    $columns[] = $this->_adapter->quoteColumnAs($column, $alias, true);
                } else {
                    $columns[] = $this->_adapter->quoteColumnAs(array($correlationName, $column), $alias, true);
                }
            }
        }

        return $sql.' '.implode(', ', $columns);
    }

    /**
     * Render FROM clause
     *
     * @param string   $sql SQL query
     * @return string
     */
    protected function _renderFrom($sql)
    {
        /*
         * If no table specified, use RDBMS-dependent solution
         * for table-less query.  e.g. DUAL in Oracle.
         */
        if (empty($this->_parts[self::FROM])) {
            $this->_parts[self::FROM] = $this->_getDummyTable();
        }

        $from = array();

        foreach ($this->_parts[self::FROM] as $correlationName => $table) {
            $tmp = '';

            $joinType = ($table['joinType'] == self::FROM) ? self::INNER_JOIN : $table['joinType'];

            // Add join clause (if applicable)
            if (! empty($from)) {
                $tmp .= ' '.strtoupper($joinType).' ';
            }

            $tmp .= $this->_getQuotedSchema($table['schema']);
            $tmp .= $this->_getQuotedTable($table['tableName'], $correlationName);

            // Add join conditions (if applicable)
            if (! empty($from) && ! empty($table['joinCondition'])) {
                $tmp .= ' '.self::SQL_ON.' '.$table['joinCondition'];
            }

            // Add the table name and condition add to the list
            $from[] = $tmp;
        }

        // Add the list of all joins
        if (! empty($from)) {
            $sql .= ' '.self::SQL_FROM.' '.implode("\n", $from);
        }

        return $sql;
    }

    /**
     * Render UNION query
     *
     * @param string   $sql SQL query
     * @return string
     */
    protected function _renderUnion($sql)
    {
        if ($this->_parts[self::UNION]) {
            $parts = count($this->_parts[self::UNION]);
            foreach ($this->_parts[self::UNION] as $cnt => $union) {
                list($target, $type) = $union;
                if ($target instanceof Zend_Db_Select) {
                    $target = $target->assemble();
                }
                $sql .= $target;
                if ($cnt < $parts - 1) {
                    $sql .= ' '.$type.' ';
                }
            }
        }

        return $sql;
    }

    /**
     * Render WHERE clause
     *
     * @param string   $sql SQL query
     * @return string
     */
    protected function _renderWhere($sql)
    {
        if ($this->_parts[self::FROM] && $this->_parts[self::WHERE]) {
            $sql .= ' '.self::SQL_WHERE.' '.implode(' ', $this->_parts[self::WHERE]);
        }

        return $sql;
    }

    /**
     * Render GROUP clause
     *
     * @param string   $sql SQL query
     * @return string
     */
    protected function _renderGroup($sql)
    {
        if ($this->_parts[self::FROM] && $this->_parts[self::GROUP]) {
            $group = array();
            foreach ($this->_parts[self::GROUP] as $term) {
                $group[] = $this->_adapter->quoteIdentifier($term, true);
            }
            $sql .= ' '.self::SQL_GROUP_BY.' '.implode(",\n\t", $group);
        }

        return $sql;
    }

    /**
     * Render HAVING clause
     *
     * @param string   $sql SQL query
     * @return string
     */
    protected function _renderHaving($sql)
    {
        if ($this->_parts[self::FROM] && $this->_parts[self::HAVING]) {
            $sql .= ' '.self::SQL_HAVING.' '.implode(' ', $this->_parts[self::HAVING]);
        }

        return $sql;
    }

    /**
     * Render ORDER clause
     *
     * @param string   $sql SQL query
     * @return string
     */
    protected function _renderOrder($sql)
    {
        if ($this->_parts[self::ORDER]) {
            $order = array();
            foreach ($this->_parts[self::ORDER] as $term) {
                if (is_array($term)) {
                    if (is_numeric($term[0]) && strval(intval($term[0])) == $term[0]) {
                        $order[] = (int) trim($term[0]).' '.$term[1];
                    } else {
                        $order[] = $this->_adapter->quoteIdentifier($term[0], true).' '.$term[1];
                    }
                } elseif (is_numeric($term) && strval(intval($term)) == $term) {
                    $order[] = (int) trim($term);
                } else {
                    $order[] = $this->_adapter->quoteIdentifier($term, true);
                }
            }
            $sql .= ' '.self::SQL_ORDER_BY.' '.implode(', ', $order);
        }

        return $sql;
    }

    /**
     * Render LIMIT OFFSET clause
     *
     * @param string   $sql SQL query
     * @return string
     */
    protected function _renderLimitoffset($sql)
    {
        $count = 0;
        $offset = 0;

        if (! empty($this->_parts[self::LIMIT_OFFSET])) {
            $offset = (int) $this->_parts[self::LIMIT_OFFSET];
            $count = PHP_INT_MAX;
        }

        if (! empty($this->_parts[self::LIMIT_COUNT])) {
            $count = (int) $this->_parts[self::LIMIT_COUNT];
        }

        /*
         * Add limits clause
         */
        if ($count > 0) {
            $sql = trim($this->_adapter->limit($sql, $count, $offset));
        }

        return $sql;
    }

    /**
     * Render FOR UPDATE clause
     *
     * @param string   $sql SQL query
     * @return string
     */
    protected function _renderForupdate($sql)
    {
        if ($this->_parts[self::FOR_UPDATE]) {
            $sql .= ' '.self::SQL_FOR_UPDATE;
        }

        return $sql;
    }

    /**
     * Turn magic function calls into non-magic function calls
     * for joinUsing syntax
     *
     * @param string $method
     * @param array $args OPTIONAL Zend_Db_Table_Select query modifier
     * @return Zend_Db_Select
     * @throws Zend_Db_Select_Exception If an invalid method is called.
     */
    public function __call($method, array $args)
    {
        $matches = array();

        /**
         * Recognize methods for Has-Many cases:
         * findParent<Class>()
         * findParent<Class>By<Rule>()
         * Use the non-greedy pattern repeat modifier e.g. \w+?
         */
        if (preg_match('/^join([a-zA-Z]*?)Using$/', $method, $matches)) {
            $type = strtolower($matches[1]);
            if ($type) {
                $type .= ' join';
                if (! in_array($type, self::$_joinTypes)) {
                    require_once 'Zend/Db/Select/Exception.php';
                    throw new Zend_Db_Select_Exception("Unrecognized method '$method()'");
                }
                if (in_array($type, array(self::CROSS_JOIN, self::NATURAL_JOIN))) {
                    require_once 'Zend/Db/Select/Exception.php';
                    throw new Zend_Db_Select_Exception("Cannot perform a joinUsing with method '$method()'");
                }
            } else {
                $type = self::INNER_JOIN;
            }
            array_unshift($args, $type);
            return call_user_func_array(array($this, '_joinUsing'), $args);
        }

        require_once 'Zend/Db/Select/Exception.php';
        throw new Zend_Db_Select_Exception("Unrecognized method '$method()'");
    }

    /**
     * Implements magic method.
     *
     * @return string This object as a SELECT string.
     */
    public function __toString()
    {
        try {
            $sql = $this->assemble();
        } catch (Exception $e) {
            trigger_error($e->getMessage(), E_USER_WARNING);
            $sql = '';
        }
        return (string) $sql;
    }

    public function when($check, $cond, $value = null, $type = null)
    {
        if ($check) {
            $this->_parts[self::WHERE][] = $this->_where($cond, $value, $type, true);
        }

        return $this;
    }

    /**
     * Get the raw SQL string with all bindings and parameters interpolated.
     *
     * @return string
     */
    public function toRawSql()
    {
        if (class_exists('ZF1\Database\DB')) {
            return ZF1\Database\DB::toRawSql($this);
        }

        return (string) $this->assemble();
    }

    /**
     * Dump the raw SQL query with bindings and continue chaining.
     *
     * @return self
     */
    public function dumpSql()
    {
        if (function_exists('dump_sql')) {
            dump_sql($this);
        } else {
            echo $this->toRawSql().PHP_EOL;
        }

        return $this;
    }

    /**
     * Dump the raw SQL query with bindings and terminate execution ("dump and die").
     *
     * @return never
     */
    public function ddSql()
    {
        if (function_exists('dd_sql')) {
            dd_sql($this);
        }

        echo $this->toRawSql().PHP_EOL;
        exit(1);
    }

    /**
     * Execute the query and fetch the first matching row.
     *
     * @param  array|string  $columns
     * @param  int           $fetchMode
     * @return \ZF1\Database\FluentRow|array<string, mixed>|null
     */
    public function first($columns = '*', $fetchMode = Zend_Db::FETCH_OBJ)
    {
        $select = clone $this;
        if ($columns !== '*' && $columns !== array('*')) {
            $select->reset(self::COLUMNS);
            $select->columns($columns);
        }
        $select->limit(1);

        $row = $select->_adapter->fetchRow($select, $select->getBind(), $fetchMode);

        if ($row === false) {
            return null;
        }

        if ($fetchMode === Zend_Db::FETCH_OBJ && class_exists('ZF1\Database\FluentRow')) {
            return new \ZF1\Database\FluentRow($row);
        }

        return $row;
    }

    /**
     * Find a single record by primary key or given column.
     *
     * @param  mixed         $id
     * @param  string        $column
     * @param  array|string  $columns
     * @param  int           $fetchMode
     * @return \ZF1\Database\FluentRow|array<string, mixed>|null
     */
    public function find($id, $column = 'id', $columns = '*', $fetchMode = Zend_Db::FETCH_OBJ)
    {
        if ($id === null) {
            return null;
        }

        $select = clone $this;
        $select->where($column, $id);

        return $select->first($columns, $fetchMode);
    }

    /**
     * Execute the query and fetch all matching rows.
     *
     * @param  array|string  $columns
     * @param  int           $fetchMode
     * @return \ZF1\Database\FluentCollection<int, \ZF1\Database\FluentRow>|array<int, array<string, mixed>>
     */
    public function get($columns = '*', $fetchMode = Zend_Db::FETCH_OBJ)
    {
        $select = clone $this;
        if ($columns !== '*' && $columns !== array('*')) {
            $select->reset(self::COLUMNS);
            $select->columns($columns);
        }

        $rows = $select->_adapter->fetchAll($select, $select->getBind(), $fetchMode);

        if ($fetchMode === Zend_Db::FETCH_OBJ && class_exists('ZF1\Database\FluentCollection')) {
            return new \ZF1\Database\FluentCollection($rows);
        }

        return $rows;
    }

    /**
     * Alias for get(). Fetch all matching rows.
     *
     * @param  array|string  $columns
     * @param  int           $fetchMode
     * @return \ZF1\Database\FluentCollection<int, \ZF1\Database\FluentRow>|array<int, array<string, mixed>>
     */
    public function all($columns = '*', $fetchMode = Zend_Db::FETCH_OBJ)
    {
        return $this->get($columns, $fetchMode);
    }

    /**
     * Paginate the query results into a modern fluent Paginator instance.
     *
     * @param  int           $perPage
     * @param  array|string  $columns
     * @param  string        $pageName
     * @param  int|null      $page
     * @return \ZF1\Database\Paginator
     */
    public function paginate($perPage = 15, $columns = '*', $pageName = 'page', $page = null)
    {
        $perPage = max(1, (int) $perPage);
        $pageName = (string) $pageName;

        if ($page === null) {
            $page = isset($_GET[$pageName]) ? max(1, (int) $_GET[$pageName]) : 1;
        } else {
            $page = max(1, (int) $page);
        }

        $total = $this->count();
        $offset = ($page - 1) * $perPage;

        $select = clone $this;
        $items = $select->limit($perPage, $offset)->get($columns);

        return new \ZF1\Database\Paginator($items, $total, $perPage, $page, [
            'pageName' => $pageName,
        ]);
    }

    /**
     * Paginate the query using simple Next / Previous pagination without running a count query.
     *
     * @param  int           $perPage
     * @param  array|string  $columns
     * @param  string        $pageName
     * @param  int|null      $page
     * @return \ZF1\Database\Paginator
     */
    public function simplePaginate($perPage = 15, $columns = '*', $pageName = 'page', $page = null)
    {
        $perPage = max(1, (int) $perPage);
        $pageName = (string) $pageName;

        if ($page === null) {
            $page = isset($_GET[$pageName]) ? max(1, (int) $_GET[$pageName]) : 1;
        } else {
            $page = max(1, (int) $page);
        }

        $offset = ($page - 1) * $perPage;

        $select = clone $this;
        $results = $select->limit($perPage + 1, $offset)->get($columns);

        $hasMore = false;
        $count = count($results);

        if ($count > $perPage) {
            $hasMore = true;
            $rawItems = $results instanceof \ZF1\Database\FluentCollection ? $results->all() : (array) $results;
            $items = array_slice($rawItems, 0, $perPage);
        } else {
            $items = $results;
        }

        $estimatedTotal = $offset + count($items) + ($hasMore ? 1 : 0);

        return new \ZF1\Database\Paginator($items, $estimatedTotal, $perPage, $page, [
            'pageName' => $pageName,
        ]);
    }

    /**
     * Chunk the results of the query into batches of a given size.
     *
     * @param  int       $count
     * @param  callable  $callback
     * @return bool
     */
    public function chunk($count, callable $callback)
    {
        $count = max(1, (int) $count);
        $page = 1;

        do {
            $offset = ($page - 1) * $count;
            $select = clone $this;
            $items = $select->limit($count, $offset)->get();
            $totalItems = count($items);

            if ($totalItems === 0) {
                break;
            }

            if ($callback($items, $page) === false) {
                return false;
            }

            unset($items);
            $page++;
        } while ($totalItems === $count);

        return true;
    }

    /**
     * Retrieve a single column's value from the first matching row.
     *
     * @param  string  $column
     * @return mixed|null
     */
    public function value($column)
    {
        $select = clone $this;
        $select->reset(self::COLUMNS);
        $select->columns($column);
        $select->limit(1);

        $val = $select->_adapter->fetchOne($select, $select->getBind());

        return $val !== false ? $val : null;
    }

    /**
     * Retrieve a list of column values.
     *
     * @param  string       $column
     * @param  string|null  $key
     * @return array
     */
    public function pluck($column, $key = null)
    {
        $select = clone $this;
        $select->reset(self::COLUMNS);

        if ($key !== null) {
            $select->columns(array($key, $column));
            return $select->_adapter->fetchPairs($select, $select->getBind());
        }

        $select->columns($column);
        return $select->_adapter->fetchCol($select, $select->getBind());
    }

    /**
     * Retrieve the count of records for the query.
     *
     * @param  string  $column
     * @return int
     */
    public function count($column = '*')
    {
        $select = clone $this;
        $select->reset(self::ORDER);
        $select->reset(self::LIMIT_COUNT);
        $select->reset(self::LIMIT_OFFSET);

        $hasGroups = ! empty($select->getPart(self::GROUP));
        $hasDistinct = (bool) $select->getPart(self::DISTINCT);

        if ($hasGroups || $hasDistinct) {
            $subSql = $select->assemble();
            $sql = "SELECT COUNT(*) FROM ({$subSql}) AS _count_subquery";

            return (int) $this->_adapter->fetchOne($sql, $select->getBind());
        }

        $select->reset(self::COLUMNS);
        $select->columns(new Zend_Db_Expr("COUNT({$column})"));

        return (int) $select->_adapter->fetchOne($select, $select->getBind());
    }

    /**
     * Determine if any matching records exist.
     *
     * @return bool
     */
    public function exists()
    {
        $select = clone $this;
        $select->reset(self::COLUMNS);
        $select->reset(self::ORDER);
        $select->columns(new Zend_Db_Expr('1'));
        $select->limit(1);

        return $select->_adapter->fetchOne($select, $select->getBind()) !== false;
    }

    /**
     * Determine if no matching records exist.
     *
     * @return bool
     */
    public function notExist()
    {
        return ! $this->exists();
    }

    /**
     * Retrieve the minimum value of a given column.
     *
     * @param  string  $column
     * @return mixed
     */
    public function min($column)
    {
        return $this->_aggregate("MIN({$column})");
    }

    /**
     * Retrieve the maximum value of a given column.
     *
     * @param  string  $column
     * @return mixed
     */
    public function max($column)
    {
        return $this->_aggregate("MAX({$column})");
    }

    /**
     * Retrieve the sum of values for a given column.
     *
     * @param  string  $column
     * @return mixed
     */
    public function sum($column)
    {
        return $this->_aggregate("SUM({$column})");
    }

    /**
     * Retrieve the average value of a given column.
     *
     * @param  string  $column
     * @return mixed
     */
    public function avg($column)
    {
        return $this->_aggregate("AVG({$column})");
    }

    /**
     * Execute an aggregate expression on the query.
     *
     * @param  string  $expression
     * @return mixed
     */
    protected function _aggregate($expression)
    {
        $select = clone $this;
        $select->reset(self::COLUMNS);
        $select->reset(self::ORDER);
        $select->reset(self::LIMIT_COUNT);
        $select->reset(self::LIMIT_OFFSET);
        $select->columns(new Zend_Db_Expr($expression));

        $val = $select->_adapter->fetchOne($select, $select->getBind());

        return $val !== false ? $val : null;
    }

    /**
     * Get the primary table name configured in the FROM clause.
     *
     * @return string|null
     */
    public function getTableName()
    {
        if (empty($this->_parts[self::FROM])) {
            return null;
        }

        foreach ($this->_parts[self::FROM] as $from) {
            if ($from['joinType'] === self::FROM && ! empty($from['tableName'])) {
                return (string) $from['tableName'];
            }
        }

        $first = reset($this->_parts[self::FROM]);

        return ! empty($first['tableName']) ? (string) $first['tableName'] : null;
    }

    /**
     * Insert a new record into the table associated with this query.
     *
     * @param  array<string, mixed>  $values
     * @return int Number of affected rows
     */
    public function insert(array $values)
    {
        $table = $this->getTableName();
        if ($table === null) {
            require_once 'Zend/Db/Select/Exception.php';
            throw new Zend_Db_Select_Exception('Cannot execute insert on query without a FROM table');
        }

        return $this->_adapter->insert($table, $values);
    }

    /**
     * Insert a new record and retrieve the last insert ID.
     *
     * @param  array<string, mixed>  $values
     * @param  string|null           $idSequence
     * @return string|int
     */
    public function insertGetId(array $values, $idSequence = null)
    {
        $this->insert($values);

        return $this->_adapter->lastInsertId($this->getTableName(), $idSequence);
    }

    /**
     * Update records matching the query builder conditions.
     *
     * @param  array<string, mixed>  $values
     * @return int Number of affected rows
     */
    public function update(array $values)
    {
        $table = $this->getTableName();
        if ($table === null) {
            require_once 'Zend/Db/Select/Exception.php';
            throw new Zend_Db_Select_Exception('Cannot execute update on query without a FROM table');
        }

        if (empty($values)) {
            return 0;
        }

        $set = array();
        $bind = array();
        foreach ($values as $col => $val) {
            if ($val instanceof Zend_Db_Expr) {
                $set[] = $this->_adapter->quoteIdentifier($col, true).' = '.(string) $val;
            } else {
                $set[] = $this->_adapter->quoteIdentifier($col, true).' = ?';
                $bind[] = $val;
            }
        }

        $sql = 'UPDATE '.$this->_adapter->quoteIdentifier($table, true).' SET '.implode(', ', $set);

        $whereClause = $this->_renderWhere('');
        if (! empty($whereClause)) {
            $sql .= ' '.$whereClause;
        }

        $whereBind = $this->getBind();
        $allBind = array_merge($bind, is_array($whereBind) ? array_values($whereBind) : array());

        $stmt = $this->_adapter->query($sql, $allBind);

        return $stmt->rowCount();
    }

    /**
     * Delete records matching the query builder conditions.
     * 
     * @return int Number of affected rows
     */
    public function delete()
    {
        $table = $this->getTableName();
        if ($table === null) {
            require_once 'Zend/Db/Select/Exception.php';
            throw new Zend_Db_Select_Exception('Cannot execute delete on query without a FROM table');
        }

        $sql = 'DELETE FROM '.$this->_adapter->quoteIdentifier($table, true);

        $whereClause = $this->_renderWhere('');
        if (! empty($whereClause)) {
            $sql .= ' '.$whereClause;
        }

        $stmt = $this->_adapter->query($sql, $this->getBind());

        return $stmt->rowCount();
    }
}
