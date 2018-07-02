<?php

namespace Adshares\Adserver\Models\Traits;

/**
 * automate some custom model columns accessors and mutators.
 */
trait AutomateMutators
{
    /**
     * Get an attribute from the model.
     *
     * @param string $key
     *
     * @return mixed
     */
    public function getAttribute($key)
    {
        if (empty($this->traitAutomate[$key])) {
            return parent::getAttribute($key);
        }
        $func = lcfirst($this->traitAutomate[$key]).'Accessor';

        return $this->$func(parent::getAttribute($key));
    }

    /**
     * Set a given attribute on the model.
     *
     * @param string $key
     * @param mixed  $value
     *
     * @return $this
     */
    public function setAttribute($key, $value)
    {
        if (empty($this->traitAutomate[$key])) {
            return parent::setAttribute($key, $value);
        }
        $func = lcfirst($this->traitAutomate[$key]).'Mutator';

        return $this->$func($key, $value);
    }

    public function toArray()
    {
        return array_merge($this->toArrayProcessTraitAttributes(), $this->relationsToArray());
    }

    protected function toArrayProcessTraitAttributes()
    {
        if (empty($this->traitAutomate)) {
            return $this->toArrayExtrasCheck(parent::attributesToArray());
        }
        $array = parent::attributesToArray();
        foreach (array_keys($this->traitAutomate) as $k) {
            $array[$k] = $this->$k;
        }

        return $this->toArrayExtrasCheck($array);
    }

    public function toArrayExtrasCheck($array)
    {
        if (!method_exists($this, 'toArrayExtras')) {
            return $array;
        }

        return $this->toArrayExtras($array);
    }
}
