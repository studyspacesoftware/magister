<?php

namespace Magister\Services\Database;

use Closure;
use Exception;
use GuzzleHttp\Client;
use Magister\Services\Database\Query\Builder;
use Magister\Services\Contracts\Events\Dispatcher;
use Magister\Services\Database\Query\Processors\Processor;

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
    protected $processor;

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
     * Create a new connection instance.
     *
     * @param \GuzzleHttp\Client $client
     */
    public function __construct(Client $client)
    {
        $this->client = $client;

        $this->useDefaultProcessor();
    }

    /**
     * Start a query against the server.
     *
     * @param string $query
     * @return \Magister\Services\Database\Query\Builder
     */
    public function query($query)
    {
        $processor = $this->getProcessor();

        $builder = new Builder($this, $processor);

        return $builder->from($query);
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
            list($query, $bindings) = $me->prepareBindings($query, $bindings);

            // For select statements, we'll simply execute the query and return an array
            // of the result set. Each element in the array will be a single
            // row from the response, and will either be an array or objects.
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
     * Execute an SQL statement and return the boolean result.
     *
     * @param string $query
     * @param array $bindings
     * @return bool
     */
    public function statement($query, $bindings = [])
    {
        return $this->run($query, $bindings, function ($me, $query, $bindings) {
            dump('Hell Yeah!');
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
     * Set the default processor.
     *
     * @return \Magister\Services\Database\Query\Processors\Processor
     */
    public function setDefaultProcessor()
    {
        return new Processor();
    }

    /**
     * Use the default processor.
     *
     * @return void
     */
    public function useDefaultProcessor()
    {
        $this->processor = $this->setDefaultProcessor();
    }

    /**
     * Set the processor used by the connection.
     *
     * @param \Magister\Services\Database\Query\Processors\Processor $processor
     * @return void
     */
    public function setProcessor(Processor $processor)
    {
        $this->processor = $processor;
    }

    /**
     * Get the processor used by the connection.
     *
     * @return \Magister\Services\Database\Query\Processors\Processor
     */
    public function getProcessor()
    {
        return $this->processor;
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
     * Get the event dispatcher used by the connection.
     *
     * @return \Magister\Services\Contracts\Events\Dispatcher
     */
    public function getEventDispatcher()
    {
        return $this->events;
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
