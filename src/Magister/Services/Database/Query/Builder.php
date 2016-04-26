<?php

namespace Magister\Services\Database\Query;

use BadMethodCallException;
use Magister\Services\Support\Collection;
use Magister\Services\Database\ConnectionInterface;
use Magister\Services\Database\Query\Processors\Processor;

/**
 * Class Builder
 * @package Magister
 */
class Builder
{
    /**
     * The connection instance.
     *
     * @var \Magister\Services\Database\ConnectionInterface
     */
    protected $connection;

    /**
     * The processor instance.
     *
     * @var \Magister\Services\Database\Query\Processors\Processor
     */
    protected $processor;

    /**
     * The url which the query is targeting.
     *
     * @var string
     */
    protected $from;

    /**
     * The where constraints for the query.
     *
     * @var array
     */
    public $wheres;

    /**
     * The current query value bindings.
     *
     * @var array
     */
    protected $bindings = [
        'where'  => [],
    ];

    /**
     * Create a new query builder instance.
     *
     * @param \Magister\Services\Database\ConnectionInterface $connection
     * @param \Magister\Services\Database\Query\Processors\Processor $processor
     */
    public function __construct(ConnectionInterface $connection, Processor $processor)
    {
        $this->connection = $connection;
        $this->processor = $processor;
    }

    /**
     * Set the url which the query is targeting.
     *
     * @param string $query
     * @return $this
     */
    public function from($query)
    {
        $this->from = $query;

        return $this;
    }

    /**
     * Get an array with the values of a given column.
     *
     * @param string $column
     * @param string $key
     * @return array
     */
    public function lists($column, $key = null)
    {
        $columns = $this->getListSelect($column, $key);

        $results = new Collection($this->get());

        return $results->lists($columns[0], array_get($columns, 1));
    }

    /**
     * Get the columns that should be used in a lists array.
     *
     * @param string $column
     * @param string $key
     * @return array
     */
    protected function getListSelect($column, $key)
    {
        $select = is_null($key) ? [$column] : [$column, $key];

        return array_map(function ($column) {
            $dot = strpos($column, '.');

            return $dot === false ? $column : substr($column, $dot + 1);
        }, $select);
    }

    /**
     * Execute the query as a select statement.
     *
     * @return array
     */
    public function get()
    {
        return $this->processor->process($this, $this->runSelect());
    }

    /**
     * Run the query as a select statement against the connection.
     *
     * @return array
     */
    protected function runSelect()
    {
        return $this->connection->select($this->from, $this->getBindings());
    }

    /**
     * Add a basic where clause to the query.
     *
     * @param string $column
     * @param mixed $value
     * @return $this
     */
    public function where($column, $value = null)
    {
        $type = 'Basic';

        $this->wheres[] = compact('type', 'column', 'value');

        $this->addBinding($value, 'where');

        return $this;
    }

    /**
     * Handles dynamic "where" clauses to the query.
     *
     * @param string $method
     * @param string $parameters
     * @return $this
     */
    public function dynamicWhere($method, $parameters)
    {
        $finder = substr($method, 5);

        $segments = preg_split('/(And)(?=[A-Z])/', $finder, -1, PREG_SPLIT_DELIM_CAPTURE);

        $parameter = array_shift($parameters);

        foreach ($segments as $segment) {
            if ($segment != 'And') {
                $this->where($segment, $parameter);
            }
        }

        return $this;
    }

    /**
     * Insert a new record into the database.
     *
     * @param array $values
     * @return bool
     */
    public function insert(array $values)
    {
        if (empty($values)) {
            return true;
        }

        if (! is_array(reset($values))) {
            $values = [$values];
        } else {
            foreach ($values as $key => $value) {
                ksort($value);
                $values[$key] = $value;
            }
        }

        $bindings = [];

        foreach ($values as $record) {
            foreach ($record as $value) {
                $bindings[] = $value;
            }
        }

        return $this->connection->insert($this->from, $bindings);
    }

    /**
     * Get the current query value bindings in a flattened array.
     *
     * @return array
     */
    public function getBindings()
    {
        return array_flatten($this->bindings);
    }

    /**
     * Get the raw array of bindings.
     *
     * @return array
     */
    public function getRawBindings()
    {
        return $this->bindings;
    }

    /**
     * Set the bindings on the query builder.
     *
     * @param array $bindings
     * @param string $type
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function setBindings(array $bindings, $type = 'where')
    {
        if (! array_key_exists($type, $this->bindings)) {
            throw new InvalidArgumentException("Invalid binding type: {$type}.");
        }

        $this->bindings[$type] = $bindings;

        return $this;
    }

    /**
     * Add a binding to the query.
     *
     * @param mixed $value
     * @param string $type
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function addBinding($value, $type = 'where')
    {
        if (! array_key_exists($type, $this->bindings)) {
            throw new InvalidArgumentException("Invalid binding type: {$type}.");
        }

        if (is_array($value)) {
            $this->bindings[$type] = array_values(array_merge($this->bindings[$type], $value));
        } else {
            $this->bindings[$type][] = $value;
        }

        return $this;
    }

    /**
     * Merge an array of bindings into our bindings.
     *
     * @param \Magister\Services\Database\Query\Builder $query
     * @return $this
     */
    public function mergeBindings(Builder $query)
    {
        $this->bindings = array_merge_recursive($this->bindings, $query->bindings);

        return $this;
    }

    /**
     * Get the connection instance.
     *
     * @return \Magister\Services\Database\ConnectionInterface
     */
    public function getConnection()
    {
        return $this->connection;
    }

    /**
     * Get the query processor instance.
     *
     * @return \Magister\Services\Database\Query\Processors\Processor
     */
    public function getProcessor()
    {
        return $this->processor;
    }

    /**
     * Handle dynamic method calls into the method.
     *
     * @param string $method
     * @param array $parameters
     * @return mixed
     * @throws \BadMethodCallException
     */
    public function __call($method, $parameters)
    {
        if (starts_with($method, 'where')) {
            return $this->dynamicWhere($method, $parameters);
        }

        $className = get_class($this);

        throw new BadMethodCallException("Call to undefined method {$className}::{$method}()");
    }
}
