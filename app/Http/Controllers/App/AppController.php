<?php

namespace Adshares\Adserver\Http\Controllers\App;

use Adshares\Adserver\Exceptions\JsonResponseException;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Validator;

class AppController extends BaseController
{
    /**
     * @param array $data
     * @param int   $code
     * @param mixed $errors
     *
     * @return \Illuminate\Http\JsonResponse
     */
    protected static function json($data = [], $code = 200, $errors = false)
    {
        if (empty($errors)) {
            return Response::json($data, $code);
        }
        $data['errors'] = $errors;

        return Response::json($data, $code);
    }

    /**
     * @param \Illuminate\Http\Request $request
     * @param string                   $index
     * @param array                    $rules
     *
     * @return array
     *
     * @throws JsonResponseException
     * @throws \Illuminate\Validation\ValidationException
     */
    protected function validateRequest(Request $request, String $index, array $rules)
    {
        if (!$request->has($index)) {
            throw new JsonResponseException(self::json([], 422, ['message' => "Missing data '$index'"]));
        }
        /* @var $validator \Illuminate\Validation\Validator */
        $validator = Validator::make($request->input($index), $rules);

        return $validator->validate();
    }
}
