<?php

namespace Adshares\Adserver\Exceptions;

use Exception;

use Illuminate\Http\JsonResponse;

class JsonResponseException extends Exception
{
    protected $response;

    public function __construct(JsonResponse $response)
    {
        $this->response = $response;
    }

    /**
     * @return JsonResponse
     */
    public function get()
    {
        return $this->response;
    }
}
