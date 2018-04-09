<?php

namespace App\ModelTraits;

/**
automate some custom model columns accessors and mutators
*/
trait AutomateMutators
{
    /**
     * Get an attribute from the model.
     *
     * @param  string  $key
     * @return mixed
     */
    public function getAttribute($key)
    {
        if (empty($this->traitAutomate[$key])) {
            return parent::getAttribute($key);
        }
        $func = lcfirst($this->traitAutomate[$key]) . 'Accessor';
        return $this->$func(parent::getAttribute($key));
    }

    /**
     * Set a given attribute on the model.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return $this
     */
    public function setAttribute($key, $value)
    {
        if (empty($this->traitAutomate[$key])) {
            return parent::setAttribute($key, $value);
        }
        $func = lcfirst($this->traitAutomate[$key]) . 'Mutator';
        return $this->$func($key,$value);
    }
}
