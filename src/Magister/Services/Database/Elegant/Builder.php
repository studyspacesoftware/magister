<?php

namespace Magister\Services\Database\Elegant;

use Magister\Services\Database\Query\Builder as QueryBuilder;

/**
 * Class Builder
 * @package Magister
 */
class Builder
{
    /**
     * The base query builder implementation.
     *
     * @var \Magister\Services\Database\Query\Builder
     */
    protected $query;

    /**
     * The model being queried.
     *
     * @var \Magister\Services\Database\Elegant\Model
     */
    protected $model;

    /**
     * The methods that should be returned from query builder.
     *
     * @var array
     */
    protected $passthru = [
        'insert', 'getBindings',
    ];

    /**
     * Create a new elegant builder instance.
     *
     * @param \Magister\Services\Database\Query\Builder $query
     */
    public function __construct(QueryBuilder $query)
    {
        $this->query = $query;
    }

    /**
     * Find a model by its primary key.
     *
     * @param mixed $id
     * @return \Magister\Services\Database\Elegant\Model|\Magister\Services\Database\Elegant\Collection|null
     */
    public function find($id)
    {
        if (is_array($id)) {
            return $this->findMany($id);
        }

        return $this->get()->find($id);
    }

    /**
     * Find a model by its primary key.
     *
     * @param array $ids
     * @return \Magister\Services\Database\Elegant\Collection
     */
    public function findMany($ids)
    {
        if (empty($ids)) {
            return $this->model->newCollection();
        }

        return $this->get()->filter(function($model) use ($ids) {
            return in_array($model->getKey(), $ids);
        });
    }

    /**
     * Find a model by its primary key or throw an exception.
     *
     * @param mixed $id
     * @return \Magister\Services\Database\Elegant\Model|\Magister\Services\Database\Elegant\Collection
     * @throws \Magister\Services\Database\Elegant\ModelNotFoundException
     */
    public function findOrFail($id)
    {
        $result = $this->find($id);

        if (is_array($id)) {
            if (count($result) == count(array_unique($id))) {
                return $result;
            }
        } elseif (! is_null($result)) {
            return $result;
        }

        throw (new ModelNotFoundException)->setModel(get_class($this->model));
    }

    /**
     * Execute the query and get the first result.
     *
     * @return \Magister\Services\Database\Elegant\Model|static|null
     */
    public function first()
    {
        return $this->get()->first();
    }

    /**
     * Execute the query and get the first result or throw an exception.
     *
     * @return \Magister\Services\Database\Elegant\Model|static
     * @throws \Magister\Services\Database\Elegant\ModelNotFoundException
     */
    public function firstOrFail()
    {
        if (! is_null($model = $this->first())) {
            return $model;
        }

        throw (new ModelNotFoundException)->setModel(get_class($this->model));
    }

    /**
     * Execute the query as a select statement.
     *
     * @return \Magister\Services\Database\Elegant\Collection|static[]
     */
    public function get()
    {
        $models = $this->getModels();

        return $this->model->newCollection($models);
    }

    /**
     * Get a single column's value from the first result of a query.
     *
     * @param string $column
     * @return mixed
     */
    public function value($column)
    {
        $result = $this->first();

        if ($result) {
            return $result->$column;
        }
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
        return $this->query->lists($column, $key);
    }

    /**
     * Get the hydrated models.
     *
     * @return array
     */
    public function getModels()
    {
        $results = $this->query->get();

        $connection = $this->model->getConnectionName();

        return $this->model->hydrate($results, $connection)->all();
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
        call_user_func_array([$this->query, 'where'], func_get_args());

        return $this;
    }

    /**
     * Get the underlying query builder instance.
     *
     * @return \Magister\Services\Database\Query\Builder|static
     */
    public function getQuery()
    {
        return $this->query;
    }

    /**
     * Set the underlying query builder instance.
     *
     * @param \Magister\Services\Database\Query\Builder $query
     * @return $this
     */
    public function setQuery($query)
    {
        $this->query = $query;

        return $this;
    }

    /**
     * Set the model instance being queried.
     *
     * @param \Magister\Services\Database\Elegant\Model $model
     * @return $this
     */
    public function setModel(Model $model)
    {
        $this->model = $model;

        $this->query->from($model->getUrl());

        return $this;
    }

    /**
     * Get the model instance being queried.
     *
     * @return \Magister\Services\Database\Elegant\Model
     */
    public function getModel()
    {
        return $this->model;
    }

    /**
     * Dynamically handle calls into the query instance.
     *
     * @param string $method
     * @param array $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        $result = call_user_func_array([$this->query, $method], $parameters);

        return in_array($method, $this->passthru) ? $result : $this;
    }

    /**
     * Force a clone of the underlying query builder when cloning.
     *
     * @return void
     */
    public function __clone()
    {
        $this->query = clone $this->query;
    }
}
