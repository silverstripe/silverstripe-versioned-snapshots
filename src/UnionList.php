<?php


namespace SilverStripe\Snapshots;


use SilverStripe\Core\Injector\Injectable;
use SilverStripe\ORM\Connect\Query;
use SilverStripe\ORM\Limitable;
use SilverStripe\ORM\Queries\SQLSelect;
use SilverStripe\ORM\Map;
use ArrayIterator;
use Exception;
use SilverStripe\ORM\Sortable;
use InvalidArgumentException;

/**
 * There isn't great support for UNION queries in the ORM, so this is a patchwork fix.
 * The main thing it needs to provide is pagination for the UNION query in the snapshot
 * admin. Unfortunately, that means it has to implement everything in SS_List, as well.
 * Many of the methods are just stubs that error out, while others are actually implemented
 * in a good-enough way for an internal API.
 *
 * This API should be considered unstable, and exists only to serve the snapshot-admin needs.
 *
 * @internal
 */
class UnionList implements Limitable, Sortable
{
    use Injectable;

    /**
     * @var SQLSelect[]
     */
    protected $queries = [];

    /**
     * @var Query
     */
    protected $result;

    /**
     * @var array
     */
    protected $limit = [];

    /**
     * @var UnionSelect
     */
    protected $select;

    /**
     * UnionList constructor.
     * @param SQLSelect[] ...$queries
     */
    public function __construct(...$queries)
    {
        $this->select = new UnionSelect($queries);
    }

    /**
     * @param int $limit
     * @param int $offset
     * @return Limitable|void
     */
    public function limit($limit, $offset = 0)
    {
        $this->select->setLimit($limit, $offset);
    }

    public function sort()
    {
        $count = func_num_args();
        if ($count == 0) {
            return $this;
        }

        if ($count > 2) {
            throw new InvalidArgumentException('This method takes zero, one or two arguments');
        }

        if ($count == 2) {
            $col = null;
            $dir = null;
            list($col, $dir) = func_get_args();
            if (!in_array(strtolower($dir), ['desc', 'asc'])) {
                user_error('Second argument to sort must be either ASC or DESC');
            }

            $sort = [$col => $dir];
        } else {
            $sort = func_get_arg(0);
        }

        $this->select->setOrderBy($sort);
    }

    public function canSortBy($by)
    {
        return $this->select->canSortBy($by);
    }

    public function reverse()
    {
        $this->select->reverseOrderBy();
    }

    /**
     * @return ArrayIterator
     */
    public function getIterator()
    {
        return new ArrayIterator($this->toArray());
    }

    /**
     * @return mixed|Query
     */
    public function last()
    {
        return $this->select->lastRow()->execute();
    }

    /**
     * @param string $colName
     * @return array
     */
    public function column($colName = "ID")
    {
        $fieldExpression = $this->select->expressionForField($colName);
        $this->select->setSelect(array());
        $this->select->selectField($fieldExpression, $colName);

        return $this->select->execute()->column($colName);
    }

    /**
     * @return int
     */
    public function count()
    {
        return $this->select->count();
    }

    /**
     * @return mixed|SQLSelect
     */
    public function first()
    {
        return $this->select->firstRow()->execute();
    }

    /**
     * @param string $keyfield
     * @param string $titlefield
     * @return Map
     */
    public function map($keyfield = 'ID', $titlefield = 'Title')
    {
        return new Map($this, $keyfield, $titlefield);

    }

    /**
     * @param callable $callback
     * @return Limitable|void
     */
    public function each($callback)
    {
        foreach ($this as $row) {
            $callback($row);
        }
    }

    /**
     * @param string $key
     * @param mixed $value
     * @return array|null
     */
    public function find($key, $value)
    {
        foreach ($this as $row) {
            if (isset($row[$key]) && $row[$key] == $value) {
                return $row;
            }
        }

        return null;
    }

    /**
     * @return array
     */
    public function toNestedArray()
    {
        return $this->toArray();
    }

    /**
     * @return array
     */
    public function toArray()
    {
        $result = $this->select->execute();
        $rows = [];
        foreach ($result as $row) {
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * @param mixed $offset
     * @throws Exception
     */
    public function offsetExists($offset)
    {
        throw new Exception(sprintf('The %s method is not supported for %s', __FUNCTION__, __CLASS__));
    }

    /**
     * @param mixed $offset
     * @return mixed|void
     * @throws Exception
     */
    public function offsetGet($offset)
    {
        throw new Exception(sprintf('The %s method is not supported for %s', __FUNCTION__, __CLASS__));
    }

    /**
     * @param mixed $offset
     * @throws Exception
     */
    public function offsetUnset($offset)
    {
        throw new Exception(sprintf('The %s method is not supported for %s', __FUNCTION__, __CLASS__));
    }

    /**
     * @param mixed $offset
     * @param mixed $value
     * @throws Exception
     */
    public function offsetSet($offset, $value)
    {
        throw new Exception(sprintf('The %s method is not supported for %s', __FUNCTION__, __CLASS__));
    }

    /**
     * @param mixed $item
     * @throws Exception
     */
    public function remove($item)
    {
        throw new Exception(sprintf('The %s method is not supported for %s', __FUNCTION__, __CLASS__));
    }

    /**
     * @param mixed $item
     * @throws Exception
     */
    public function add($item)
    {
        throw new Exception(sprintf('The %s method is not supported for %s', __FUNCTION__, __CLASS__));
    }


}