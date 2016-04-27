<?php

namespace Magister\Services\Database;

use Closure;
use Exception;
use GuzzleHttp\Client;
use Magister\Services\Database\Query\Builder;
use Magister\Services\Contracts\Events\Dispatcher;
use Magister\Services\Database\Query\Processors\Processor;
use Magister\Services\Database\Query\Builder as QueryBuilder;

/**
 * Class Connection
 * @package Magister
 */
class Connection implements ConnectionInterface
{
    /**
     * The active connection.
     *
     * @var \GuzzleHttp\Client
     */
    protected $client;

    /**
     * The query processor implementation.
     *
     * @var \Magister\Services\Database\Query\Processors\Processor
     */
    protected $postProcessor;

    /**
     * The event dispatcher instance.
     *
     * @var \Magister\Services\Contracts\Events\Dispatcher
     */
    protected $events;

    /**
     * All of the queries run against the connection.
     *
     * @var array
     */
    protected $queryLog = [];

    /**
     * Indicates whether queries are being logged.
     *
     * @var bool
     */
    protected $loggingQueries = false;

    /**
     * Indicates if the connection is in a "dry run".
     *
     * @var bool
     */
    protected $pretending = false;

    /**
     * Create a new connection instance.
     *
     * @param \GuzzleHttp\Client $client
     */
    public function __construct(Client $client)
    {
        $this->client = $client;

        $this->useDefaultPostProcessor();
    }

    /**
     * Use the default processor.
     *
     * @return void
     */
    public function useDefaultPostProcessor()
    {
        $this->postProcessor = $this->setDefaultPostProcessor();
    }

    /**
     * Set the default processor.
     *
     * @return \Magister\Services\Database\Query\Processors\Processor
     */
    public function setDefaultPostProcessor()
    {
        return new Processor;
    }

    /**
     * Start a query against the database.
     *
     * @param string $query
     * @return \Magister\Services\Database\Query\Builder
     */
    public function table($query)
    {
        return $this->query()->from($table);
    }

    /**
     * Get a new query builder instance.
     *
     * @return \Magister\Services\Database\Query\Builder
     */
    public function query()
    {
        return new QueryBuilder(
            $this, $this->getPostProcessor()
        );
    }

    /**
     * Run a select statement against the server.
     *
     * @param string $query
     * @param array $bindings
     * @return mixed
     */
    public function select($query, $bindings = [])
    {
        return $this->run($query, $bindings, function ($me, $query, $bindings) {
            if ($me->pretending()) {
                return [];
            }

            // For select statements, we'll simply execute the query and return an array
            // of the result set. Each element in the array will be a single
            // row from the response, and will either be an array or objects.
            list($query, $bindings) = $me->prepareBindings($query, $bindings);
             
            $statement = $me->getClient()->get($query, ['query' => $bindings]);

            return $statement->json();
        });
    }

    /**
     * Run an insert statement against the database.
     *
     * @param string $query
     * @param array $bindings
     * @return bool
     */
    public function insert($query, $bindings = [])
    {
        return $this->statement($query, $bindings);
    }

    /**
     * Run an update statement against the database.
     *
     * @param string $query
     * @param array $bindings
     * @return int
     */
    public function update($query, $bindings = [])
    {
        return $this->affectingStatement($query, $bindings);
    }

    /**
     * Run a delete statement against the database.
     *
     * @param string $query
     * @param array $bindings
     * @return int
     */
    public function delete($query, $bindings = [])
    {
        return $this->affectingStatement($query, $bindings);
    }

    /**
     * Execute an statement and return the boolean result.
     *
     * @param string $query
     * @param array $bindings
     * @return bool
     */
    public function statement($query, $bindings = [])
    {
        return $this->run($query, $bindings, function ($me, $query, $bindings) {
            if ($me->pretending()) {
                return true;
            }

            // TODO
        });
    }

    /**
     * Prepare the query bindings for execution.
     *
     * @param string $query
     * @param array $bindings
     * @return array
     */
    public function prepareBindings($query, array $bindings)
    {
        foreach ($bindings as $key => $value) {
            $search = ':' . $key;

            if (stripos($query, $search) !== false) {
                $query = str_ireplace($search, $value, $query);

                unset($bindings[$key]);
            }
        }

        return [$query, $bindings];
    }

    /**
     * Execute the given callback in "dry run" mode.
     *
     * @param \Closure $callback
     * @return array
     */
    public function pretend(Closure $callback)
    {
        $loggingQueries = $this->loggingQueries;

        $this->enableQueryLog();

        $this->pretending = true;

        $this->queryLog = [];

        // Basically to make the database connection "pretend", we will just return
        // the default values for all the query methods, then we will return an
        // array of queries that were "executed" within the Closure callback.
        $callback($this);

        $this->pretending = false;

        $this->loggingQueries = $loggingQueries;
        
        return $this->queryLog;
    }

    /**
     * Run a statement and log its execution context.
     *
     * @param string $query
     * @param array $bindings
     * @param \Closure $callback
     * @return mixed
     */
    public function run($query, $bindings, Closure $callback)
    {
        $start = microtime(true);

        $result = $this->runQueryCallback($query, $bindings, $callback);

        // Once we have run the query we will calculate the time that it took to run and
        // then log the query, bindings, and execution time so we will report them on
        // the event that the developer needs them. We'll log time in milliseconds.
        $time = $this->getElapsedTime($start);

        $this->logQuery($query, $bindings, $time);

        return $result;
    }

    /**
     * Run a SQL statement.
     *
     * @param string $query
     * @param array $bindings
     * @param \Closure $callback
     * @return mixed
     * @throws \Magister\Services\Database\QueryException
     */
    protected function runQueryCallback($query, $bindings, Closure $callback)
    {
        try {
            $result = $callback($this, $query, $bindings);
        } catch (Exception $e) {
            // If an exception occurs when attempting to run a request, we'll format the error
            // message to include the bindings, which will make this exception a
            // lot more helpful to the developer instead of just the client's errors.
            list($query, $bindings) = $this->prepareBindings($query, $bindings);

            throw new QueryException($query, $bindings, $e);
        }

        return $result;
    }

    /**
     * Log a query in the connection's query log.
     *
     * @param string $query
     * @param array $bindings
     * @param float|null $time
     * @return void
     */
    public function logQuery($query, $bindings, $time = null)
    {
        if (isset($this->events)) {
            $this->events->fire('database.query', [$query, $bindings, $time, $this->getName()]);
        }

        if (! $this->loggingQueries) {
            return;
        }

        $this->queryLog[] = compact('query', 'bindings', 'time');
    }

    /**
     * Register a database query listener with the connection.
     *
     * @param \Closure $callback
     * @return void
     */
    public function listen(Closure $callback)
    {
        if (isset($this->events)) {
            $this->events->listen('database.query', $callback);
        }
    }

    /**
     * Get the elapsed time since a given starting point.
     *
     * @param int $start
     * @return float
     */
    protected function getElapsedTime($start)
    {
        return round((microtime(true) - $start) * 1000, 2);
    }

    /**
     * Get the processor used by the connection.
     *
     * @return \Magister\Services\Database\Query\Processors\Processor
     */
    public function getPostProcessor()
    {
        return $this->postProcessor;
    }

    /**
     * Set the processor used by the connection.
     *
     * @param \Magister\Services\Database\Query\Processors\Processor $processor
     * @return void
     */
    public function setProcessor(Processor $processor)
    {
        $this->postProcessor = $processor;
    }

    /**
     * Get the event dispatcher used by the connection.
     *
     * @return \Magister\Services\Contracts\Events\Dispatcher
     */
    public function getEventDispatcher()
    {
        return $this->events;
    }

    /**
     * Set the event dispatcher instance on the connection.
     *
     * @param \Magister\Services\Contracts\Events\Dispatcher $events
     * @return void
     */
    public function setEventDispatcher(Dispatcher $events)
    {
        $this->events = $events;
    }

    /**
     * Determine if the connection in a "dry run".
     *
     * @return bool
     */
    public function pretending()
    {
        return $this->pretending === true;
    }

    /**
     * Get the current client.
     *
     * @return \GuzzleHttp\Client
     */
    public function getClient()
    {
        return $this->client;
    }

    /**
     * Get the connection query log.
     *
     * @return array
     */
    public function getQueryLog()
    {
        return $this->queryLog;
    }

    /**
     * Clear the query log.
     *
     * @return void
     */
    public function flushQueryLog()
    {
        $this->queryLog = [];
    }

    /**
     * Enable the query log on the connection.
     *
     * @return void
     */
    public function enableQueryLog()
    {
        $this->loggingQueries = true;
    }

    /**
     * Disable the query log on the connection.
     *
     * @return void
     */
    public function disableQueryLog()
    {
        $this->loggingQueries = false;
    }

    /**
     * Determine whether we're logging queries.
     *
     * @return bool
     */
    public function logging()
    {
        return $this->loggingQueries;
    }
}
