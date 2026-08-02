<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Seshac\Otp\Otp;
use App\Models\OtpLog;

class OneWaySmsService
{
	public $endpoint;
	public $username;
	public $password;

	/**
	 * [__construct description]
	 * @param [type] $endpoint [description]
	 * @param [type] $username [description]
	 * @param [type] $password [description]
	 */
	public function __construct($endpoint = null, $username = null, $password = null)
	{
		$this->endpoint = $endpoint ?? config('services.onewaysms.api_endpoint');
		$this->username = $username ?? config('services.onewaysms.api_username');
		$this->password = $password ?? config('services.onewaysms.api_password');
	}

	/**
	 * [make description]
	 * @param  [type] $endpoint [description]
	 * @param  [type] $username [description]
	 * @param  [type] $password [description]
	 * @return [type]           [description]
	 */
	public static function make($endpoint = null, $username = null, $password = null)
	{
	    return new self($endpoint, $username, $password);
	}

	/**
	 * [processOtp description]
	 * @param  [type] $mobile_no [description]
	 * @return [type]            [description]
	 */
	public function processOtp($mobile_no)
	{
	    $otp = Otp::generate($mobile_no);
	    if (!$otp->status) {
	        return response()->json([
	            'status' => false,
	            'message' => $otp->message,
	        ]);
	    }
	    $otp->mobile = $mobile_no;
	    return $this->sendOtp($otp);
	}

	/**
	 * [sendOtp description]
	 * @param  [type] $otp [description]
	 * @return [type]      [description]
	 */
	public function sendOtp($otp)
	{
	    $data = [
	        'type' => 'mt',
	        'sender_id' => 'INFO',
	        'mobile_no' => $otp->mobile,
	        'message' => 'AUTOMAID: Your OTP is ' . $otp->token . '. Please verify the OTP within 10 minutes.',
	    ];
	    $send = $this->sendSms($data);
	    $log = OtpLog::create([
	        'data' => json_encode($send)
	    ]);
	    return $send;
	}

	/**
	 * [sendSms description]
	 * @param  [type] $data [description]
	 * @return [type]       [description]
	 */
	public function sendSms($data)
	{
		try {
			switch ($data['type']):
				case 'mt':
					$type = 'api';
					break;
				case 'cb':
					$type = 'bulkcredit';
					break;
				case 'ct':
					$type = 'bulktrx';
					break;					
			endswitch;

			$path  = $type . '.aspx?apiusername=' . $this->username . '&apipassword=' . $this->password;
			$path .= '&senderid=' . rawurlencode($data['sender_id']) . '&mobileno=' . rawurlencode($data['mobile_no']);
			$path .= '&message=' . rawurlencode(stripslashes($data['message'])) . '&languagetype=1';
			$url = $this->endpoint . '/' . $path; 
			$fd = @implode ('', file ($url)); 
			
			if ($fd) {
				if ($fd > 0) {
					$status = 'success';					
					$msg = "MT ID : " . $fd;
				}
				else {
					$status = 'error';
					$msg = "Please refer to API on Error : " . $fd;
				}
			}
			else {
				$status = 'error';
				$msg = "Failed";
			}	
			return ['message' => $msg, 'status' => $status];
		} catch (\Exception $e) {
		    return ['data' => [], 'message' => $e->getMessage(), 'status' => false, 'code' => $e->getCode()];
		}	
	}


}




