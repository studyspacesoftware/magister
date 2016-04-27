<?php

namespace Magister\Services\Database;

use Closure;

/**
 * Interface ConnectionInterface
 * @package Magister
 */
interface ConnectionInterface
{
    /**
     * Start a query against the database.
     *
     * @param string $query
     * @return \Magister\Services\Database\Query\Builder
     */
    public function table($query);

    /**
     * Run a select statement against the server.
     *
     * @param string $query
     * @param array $bindings
     * @return mixed
     */
    public function select($query, $bindings = []);

    /**
     * Run an insert statement against the database.
     *
     * @param string $query
     * @param array $bindings
     * @return bool
     */
    public function insert($query, $bindings = []);

    /**
     * Run an update statement against the database.
     *
     * @param string $query
     * @param array $bindings
     * @return int
     */
    public function update($query, $bindings = []);

    /**
     * Run a delete statement against the database.
     *
     * @param string $query
     * @param array $bindings
     * @return int
     */
    public function delete($query, $bindings = []);

    /**
     * Execute an SQL statement and return the boolean result.
     *
     * @param  string  $query
     * @param  array   $bindings
     * @return bool
     */
    public function statement($query, $bindings = []);

    /**
     * Execute the given callback in "dry run" mode.
     *
     * @param \Closure $callback
     * @return array
     */
    public function pretend(Closure $callback);
}
