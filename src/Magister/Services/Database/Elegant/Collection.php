<?php

namespace Magister\Services\Database\Elegant;

use Magister\Services\Support\Collection as BaseCollection;

class Collection extends BaseCollection
{
    /**
     * Find a model in the collection by key.
     *
     * @param mixed $key
     * @param mixed $default
     * @return \Magister\Services\Database\Elegant\Model
     */
    public function find($key, $default = null)
    {
        if ($key instanceof Model) {
            $key = $key->getKey();
        }

        return array_first($this->items, function ($itemKey, $model) use ($key) {
            return $model->getKey() == $key;

        }, $default);
    }

    /**
     * Add an item to the collection.
     *
     * @param mixed $item
     * @return $this
     */
    public function add($item)
    {
        $this->items[] = $item;

        return $this;
    }

    /**
     * Determine if a key exists in the collection.
     *
     * @param mixed $key
     * @param mixed $value
     * @return bool
     */
    public function contains($key, $value = null)
    {
        if (func_num_args() == 2) {
            return parent::contains($key, $value);
        }

        if ($this->useAsCallable($key)) {
            return parent::contains($key);
        }

        $key = $key instanceof Model ? $key->getKey() : $key;

        return parent::contains(function ($k, $m) use ($key) {
            return $m->getKey() == $key;
        });
    }

    /**
     * Get a base Support collection instance from this collection.
     *
     * @return \Magister\Services\Support\Collection
     */
    public function toBase()
    {
        return new BaseCollection($this->items);
    }
}
