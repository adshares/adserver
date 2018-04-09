<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;

class TmpTestModelController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('guest');
    }

    public function runTests() {

      die('finished');
    }
}
