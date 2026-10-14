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
	        // Callers read this as an array ($send['status']), not a response.
	        return [
	            'status' => false,
	            'message' => $otp->message,
	        ];
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
					$msg = $this->failureMessage((string) $fd);
				}
			}
			else {
				$status = 'error';
				$msg = $this->failureMessage('no reply');
			}	
			return ['message' => $msg, 'status' => $status];
		} catch (\Exception $e) {
		    return ['data' => [], 'message' => $e->getMessage(), 'status' => false, 'code' => $e->getCode()];
		}	
	}


	/**
	 * OneWaySMS MT return codes (negative = not sent):
	 *   -100 wrong API username/password   -200 invalid sender ID
	 *   -300 invalid mobile number          -400 invalid language type
	 *   -500 invalid characters in message  -600 insufficient credit balance
	 * The customer/rider sees a plain message; the code goes to the log, and
	 * account-level problems (anything but a bad number) alert admin, at most
	 * once an hour, because every OTP (sign-up, login, phone change) fails
	 * until they are fixed.
	 */
	protected function failureMessage(string $code): string
	{
		$code = trim($code);
		$reasons = [
			'-100' => 'OneWaySMS API username/password rejected',
			'-200' => 'OneWaySMS sender ID rejected',
			'-300' => 'invalid mobile number',
			'-400' => 'OneWaySMS language type rejected',
			'-500' => 'invalid characters in SMS text',
			'-600' => 'OneWaySMS credit balance is empty — top up the account',
		];
		$reason = $reasons[$code] ?? "OneWaySMS error {$code}";
		\Illuminate\Support\Facades\Log::error('OTP SMS not sent', ['code' => $code, 'reason' => $reason]);

		if ($code === '-300') {
			return 'This mobile number could not receive SMS. Please check the number and try again.';
		}

		if (\Illuminate\Support\Facades\Cache::add('onewaysms_alert_' . $code, true, now()->addHour())) {
			try {
				(new \App\Services\TelegramService())->notifyAdmin("⚠️ <b>OTP SMS is failing</b>\nReason: " . e($reason) . " (code {$code}).\nNew sign-ups, logins by OTP and phone changes cannot complete until this is fixed.");
			} catch (\Throwable $e) {
				\Illuminate\Support\Facades\Log::error('Could not alert admin about SMS failure', ['error' => $e->getMessage()]);
			}
		}

		return 'We could not send the verification SMS right now. Please try again in a few minutes, or contact support at 1-300-82-0018.';
	}

}
