<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Notifications\TestNotification;

class TestController extends Controller
{
    public function sendSms()
    {
        $user = auth()->user();
        TestNotification::send($user);
    }
}
