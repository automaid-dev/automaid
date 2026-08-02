<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\ResetCodePassword;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class VerificationController extends Controller
{
    /**
     * [showResetPassword description]
     * @param  [type] $token [description]
     * @return [type]        [description]
     */
    public function showResetPassword($token)
    {
        $passwordReset = ResetCodePassword::firstWhere('token', $token);
        if (!$passwordReset) {
            return 'Invalid token!';            
        }
        if ($passwordReset->created_at > now()->addHour()) {
            return 'Password token is expired.';
        }
        return view('auth.forgetPasswordLink', ['token' => $token, 'email' => $passwordReset->email]);
    }

    /**
     * [resetPassword description]
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function resetPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users',
            'password' => 'required|string|min:6|confirmed',
            'password_confirmation' => 'required'
        ]);
        
        $updatePassword = DB::table('password_reset_tokens')
        ->where([
            'email' => $request->email, 
            'token' => $request->token
        ])
        ->first();
        
        if(!$updatePassword){
            return 'Invalid token!';
        }

        $user = User::where('email', $request->email)->update(['password' => Hash::make($request->password)]);
        
        DB::table('password_reset_tokens')->where(['email'=> $request->email])->delete();
        
        return redirect()->route('auth.password.success');
    }

    /**
     * [redirectSuccess description]
     * @param  [type] $message [description]
     * @return [type]          [description]
     */
    public function redirectSuccess()
    {
        return view('auth.successReset');
    }

}
