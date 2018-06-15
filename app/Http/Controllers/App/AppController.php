<?php

namespace Adshares\Adserver\Http\Controllers\App;

use Adshares\Adserver\Exceptions\JsonResponseException;

use Exception;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Response;

use Validator;

class AppController extends BaseController
{
    protected $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    protected static function json($data = [], $code=200, $errors=false)
    {
        $return=['data'=>$data];
        if (empty($errors)) {
            return Response::json($return, $code);
        }
        $return['errors'] = $errors;
        return Response::json($return, $code);
    }

    protected function validateRequest(String $index, $rules)
    {
        if (!$this->request->has($index)) {
            throw new JsonResponseException(self::json([], 422, ['message'=>"Missing data '$index'"]));
        }
        $validator = Validator::make($this->request->input($index), $rules);
        return $validator->validate();
    }
}
