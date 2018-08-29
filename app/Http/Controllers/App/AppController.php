<?php

namespace Adshares\Adserver\Http\Controllers\App;

use Adshares\Adserver\Exceptions\JsonResponseException;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Validator;

class AppController extends BaseController
{
    public static function jsonWithoutSessionCookie($data = [], $code = 200, $errors = FALSE)
    {
        return self::json($data, $code, $errors);
    }
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
     * @param string                   $name
     * @param array                    $rules
     *
     * @return array
     *
     * @throws JsonResponseException
     * @throws \Illuminate\Validation\ValidationException
     */
    protected function validateRequestObject(Request $request, String $name, array $rules)
    {
        if (!$request->has($name)) {
            throw new JsonResponseException(self::json([], 422, ['message' => "Missing request object '$name'"]));
        }
        /* @var $validator \Illuminate\Validation\Validator */
        $validator = Validator::make($request->input($name), $rules);

        return $validator->validate();
    }
}
