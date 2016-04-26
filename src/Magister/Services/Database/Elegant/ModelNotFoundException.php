<?php

namespace Magister\Services\Database\Elegant;

use RuntimeException;

class ModelNotFoundException extends RuntimeException
{
    /**
     * Name of the affected Elegant model.
     *
     * @var string
     */
    protected $model;

    /**
     * Set the affected Elegant model.
     *
     * @param string $model
     * @return $this
     */
    public function setModel($model)
    {
        $this->model = $model;

        $this->message = "No query results for model [{$model}].";

        return $this;
    }

    /**
     * Get the affected Elegant model.
     *
     * @return string
     */
    public function getModel()
    {
        return $this->model;
    }
}
