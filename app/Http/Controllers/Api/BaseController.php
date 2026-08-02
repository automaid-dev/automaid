<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class BaseController extends Controller
{
    /**
     * [sendResponse description]
     * @param  [type] $result  [description]
     * @param  [type] $message [description]
     * @return [type]          [description]
     */
    public function sendResponse($result, $message)
    {
        $response = [
            'success' => true,
            'data'    => $result,
            'message' => $message,
        ];
    
        return response()->json($response, 200);
    }
    
    /**
     * [sendError description]
     * @param  [type]  $error         [description]
     * @param  array   $errorMessages [description]
     * @param  integer $code          [description]
     * @return [type]                 [description]
     */
    public function sendError($error, $errorMessages = [], $code = 404)
    {
        $response = [
            'success' => false,
            'message' => $error,
        ];
    
        if(!empty($errorMessages)){
            $response['data'] = $errorMessages;
        }
    
        return response()->json($response, $code);
    }
}
