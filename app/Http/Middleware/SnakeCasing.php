<?php

namespace Adshares\Adserver\Http\Middleware;

use Closure;
use Symfony\Component\HttpFoundation\ParameterBag;

class SnakeCasing
{
    /**
     * The additional attributes passed to the middleware.
     *
     * @var array
     */
    protected $attributes = [];

    /**
     * Handle an incoming request.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Closure                 $next
     * @param array                    ...$attributes
     *
     * @return mixed
     */
    public function handle($request, Closure $next, ...$attributes)
    {
        $this->attributes = $attributes;

        $this->clean($request);

        return $next($request);
    }

    /**
     * Clean the request's data.
     *
     * @param \Illuminate\Http\Request $request
     */
    protected function clean($request)
    {
        $this->cleanParameterBag($request->query);

        if ($request->isJson()) {
            $this->cleanParameterBag($request->json());
        } else {
            $this->cleanParameterBag($request->request);
        }
    }

    /**
     * Clean the data in the parameter bag.
     *
     * @param \Symfony\Component\HttpFoundation\ParameterBag $bag
     */
    protected function cleanParameterBag(ParameterBag $bag)
    {
        $bag->replace($this->snakeArray($bag->all()));
    }

    /**
     * Clean the data in the given array.
     *
     * @param array $data
     *
     * @return array
     */
    protected function snakeArray(array $data)
    {
        if (empty($data)) {
            return $data;
        }

        $result = [];
        foreach ($data as $k => $v) {
            if (is_array($v)) {
                $result[$k] = $this->snakeArray($v);
                continue;
            }
            $result[snake_case($k)] = $v;
        }

        return $result;
    }
}
