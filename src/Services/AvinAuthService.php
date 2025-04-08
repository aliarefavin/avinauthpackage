<?php

namespace AliArefAvin\AvinAuthPackage\Services;

use AliArefAvin\AvinAuthPackage\Contracts\AvinAuthInterface;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Exception;
use Illuminate\Support\Facades\Session;



/**
 * Class AvinAuthService.
 */
class AvinAuthService
{


    public function sendOTP($receiver, $request, $method = null)
    {
        $connection = config('avinauthconfig.connection');
        $ip = request()->ip();
        $userAgent = request()->userAgent();
        $function = strtolower($connection) . 'Retrieve';

        if (method_exists($this, $function)) {
            return $this->$function($receiver, $ip, $userAgent, $method);
        } else {
            throw new Exception('کانکشن موردنظر یافت نشد.', 400);
        }

    }

    public function validate(string $receiver, string $code)
    {
        $connection = config('avinauthconfig.connection');
        $ip = request()->ip();
        $userAgent = request()->userAgent();
        $function = strtolower($connection) . 'Verify';
        if (method_exists($this, $function)) {
            return $this->$function($receiver, $code, $ip, $userAgent);
        } else {
            throw new Exception('کانکشن موردنظر یافت نشد.', 400);
        }

    }

    public function databaseVerify($receiver, $code, $ip, $userAgent)
    {

        if (config('avinauthconfig.sms_mode') == 'Local' && $code == 0000) {
            return ['success' => true, 'message' => 'کد شما با موفقیت تایید شد.'];
        }
        $log = DB::table('avin_logs')->where('receiver', $receiver)->latest()->first();
        if ($log == null || Carbon::parse($log->created_at)->diffInSeconds() > config('avinauthconfig.resend_delay')) {
            return [
                'success' => false,
                'message' => 'کد شما منقضی شده است, لطفا مجدادا درخواست بدهید.'
            ];
        }
        if ($log->count > config('avinauthconfig.max_attempts')) {
            $maxAttempts = config('avinauthconfig.max_attempts');
            return [
                'success' => false,
                'message' => "شما بیش از $maxAttempts بار درخواست بررسی کد داده اید. لطفا مجددا درخواست کد بدهید."
            ];
        }
       /* if ($log->ip != request()->ip() || $log->agent != request()->userAgent()) {
            return [
                'success' => false,
                'message' => 'این کد تایید به شما تعلق ندارد. لطفا مجددا درخواست دهید.',
            ];
        }*/

        DB::table('avin_logs')
            ->where('receiver', $receiver)
            ->orderByDesc('created_at') // latest()
            ->limit(1)
            ->increment('count');

        if ($log->code != $code && (config('avinauthconfig.code.case_sensitive') == false && strtolower($log->code) != strtolower($code))) {
            return [
                'success' => false,
                'message' => 'کد وارد شده اشتباه است.',
            ];
        }

        DB::table('avin_logs')
            ->where('receiver', $receiver)
            ->orderByDesc('created_at')
            ->limit(1)
            ->update(['count' => 2147483647,'status'=>'active']);

        return ['success' => true, 'message' => 'کد شما با موفقیت تایید شد.'];
    }

    public function redisVerify($receiver, $code, $ip, $userAgent)
    {

        if (config('avinauthconfig.sms_mode') == 'Local' && $code == 0000) {
            return ['success' => true, 'message' => 'کد شما با موفقیت تایید شد.'];
        }

        $key = config('avinauthconfig.prefix') . '_' . $receiver;
        $maxAttempts = config('avinauthconfig.max_attempts');
        $logs = json_decode(Redis::get($key), true);

        if (!!$logs && is_array($logs)) {
            if (array_key_exists('count', $logs)) {
                if ($logs['count'] >= $maxAttempts) {
                    return [
                        'success' => false,
                        'message' => "شما بیش از $maxAttempts بار درخواست بررسی کد داده اید. لطفا مجددا درخواست کد بدهید."
                    ];
                }
                if ($logs['status'] == 'active') {
                    return [
                        'success' => false,
                        'message' => 'کد شما منقضی شده است, لطفا مجدادا درخواست بدهید.'
                    ];
                }
            }

            if (is_array($logs) && array_key_exists('code', $logs)) {
                if ($logs['code'] == $code) {
                    $ttl = Redis::connection()->client()->ttl($key);
                    Redis::setex($key, $ttl, json_encode([
                        'receiver' => $logs['receiver'],
                        'ip' => $logs['ip'],
                        'code' => $logs['code'],
                        'userAgent' => $logs['userAgent'],
                        'count' => $logs['count'],
                        'status' => 'active',
                        'created_at' => $logs['created_at']
                    ]));
                    return ['success' => true, 'message' => 'کد شما با موفقیت ساخته شد.'];
                } else {
                    $ttl = Redis::connection()->client()->ttl($key);

                    Redis::setex($key, $ttl, json_encode([
                        'receiver' => $logs['receiver'],
                        'ip' => $logs['ip'],
                        'code' => $logs['code'],
                        'userAgent' => $logs['userAgent'],
                        'count' => intval($logs['count']) + 1,
                        'status' => $logs['status'],
                        'created_at' => $logs['created_at']
                    ]));
                    return [
                        'success' => false,
                        'message' => 'کد وارد شده اشتباه است.'
                    ];
                }
            }
            return [
                'success' => false,
                'message' => 'کد شما منقضی شده است, لطفا مجدادا درخواست بدهید.'
            ];
        }
        return [
            'success' => false,
            'message' => 'کد شما منقضی شده است, لطفا مجدادا درخواست بدهید.'
        ];
    }

    public function databaseRetrieve($receiver, $ip, $userAgent, $method = null)
    {

        $ip = request()->ip();
        DB::table('avin_logs')->where('created_at', '<', now()->subDay())->delete();
        $lastestLog = DB::table('avin_logs')->where('ip', $ip)->orWhere('receiver', $receiver)->latest()->first();
        $diffInSeconds = Carbon::parse($lastestLog?->created_at)->diffInSeconds();
        if ($lastestLog) {
            if (Carbon::parse($lastestLog->created_at)->gt(now()->subSeconds(config('avinauthconfig.resend_delay')))) {
                $second = intval(config('avinauthconfig.resend_delay') - $diffInSeconds);
                return [
                    'success' => false,
                    'message' => "شما اخیرا درخواست داده اید, لطف $second ثانیه صبر کنید",
                    'seconds' => intval(config('avinauthconfig.resend_delay') - $diffInSeconds)
                ];
            }
            if (DB::table('avin_logs')->where('created_at', '>', now()->subHour())
                    ->where(function ($query) use ($receiver, $ip) {
                        $query->where('ip', $ip)->orWhere('receiver', $receiver);
                    })->count() > config('avinauthconfig.max_resends.per_ip') ||
                (is_array(session('avin_verify')) && count(array_filter(session('avin_verify'), function ($time) {
                        return $time > time() - 3600;
                    })) > config('avinauthconfig.max_resends.per_session'))
            ) {
                return ['success' => false, 'message' => 'تعداد درخواست ها بیش از حد مجاز است.'];
            }
        }
        $verifyMethod = new $method;
        if (!($verifyMethod instanceof AvinAuthInterface)) {
            throw new Exception('Verify method is not instance of AvinAuthInterface.');
        }
        $code = $this->generateCode();
        if ($verifyMethod->send($receiver, $code)) {
            DB::table('avin_logs')->insert([
                'ip' => request()->ip(),
                'agent' => request()->userAgent(),
                'receiver' => $receiver,
                'code' => $code,
                'created_at' => now(), // Don't forget timestamps if your table uses them
                'updated_at' => now(),
            ]);
            Session::push('avin_verify', time());
            return ['success' => true, 'message' => 'کد تایید با موفقیت برای شما ارسال گردید.'];
        }
        return ['success' => false, 'message' => 'مشکلی پیش آمده است.'];
    }

    public function redisRetrieve($mobile, $ip, $userAgent, $method = null)
    {
        $key = config('avinauthconfig.prefix') . '_' . $mobile;
//            Redis::del($key);

        if (Redis::exists($key)) {
            $logs = json_decode(Redis::get($key), true);
            $message = $this->checkAttempts($logs, $key, $mobile, $ip, $userAgent, $method);
        } else {
            $message = $this->redisRecordCreate($key, $mobile, $ip, $userAgent, $method);
        }

        return $message;
    }

    public function checkAttempts($logs, $key, $mobile, $ip, $userAgent, $method = null)
    {
        $created_at = Carbon::parse($logs['created_at']);
        if ($created_at->gt(now()->subSeconds(config('avinauthconfig.resend_delay')))) {
            $seconds = intval(config('avinauthconfig.resend_delay') - $created_at->diffInSeconds());
            return [
                'success' => false,
                'message' => "شما اخیرا درخواست داده اید, لطف $seconds ثانیه صبر کنید",
                'seconds' => $seconds,
            ];
        } else {
            return $this->redisRecordCreate($key, $mobile, $ip, $userAgent, $method);
        }
    }

    public function redisRecordCreate($key, $mobile, $ip, $userAgent, $method = null, $count = 0)
    {
        $created_at = now();
        $expirationTime = config('avinauthconfig.resend_delay');
        $code = $this->generateCode();

        $AuthService = new $method;
        if ($AuthService instanceof AvinAuthInterface) {
            if ($AuthService->send($mobile, $code)) {
                Redis::setex($key, $expirationTime, json_encode([
                    'receiver' => $mobile,
                    'ip' => $ip,
                    'code' => $code,
                    'userAgent' => $userAgent,
                    'count' => $count,
                    'status' => 'inactive',
                    'created_at' => $created_at
                ]));

                return [
                    'success' => true,
                    'message' => 'کد تایید با موفقیت برای شما ارسال گردید.',
                    'seconds' => $expirationTime
                ];
            } else
                throw new Exception('مشکلی پیش آمده است.', 400);
        } else
            throw new Exception('مشکلی پیش آمده است.', 400);

    }

    public function generateCode()
    {
        $length = config('avinauthconfig.code.length');
        $numbers = config('avinauthconfig.code.numbers');
        $symbols = config('avinauthconfig.code.symbols');
        $lowercase = config('avinauthconfig.code.lower_case');
        $uppercase = config('avinauthconfig.code.upper_case');

        $characters = '';

        if ($numbers) {
            $characters .= '0123456789';
        }

        if ($lowercase) {
            $characters .= 'abcdefghijklmnopqrstuvwxyz';
        }

        if ($uppercase) {
            $characters .= 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        }

        if ($symbols) {
            $characters .= '!@#$%^&*()-_=+[]{}|;:<>?';
        }

        if (empty($characters)) {
            throw new Exception('تنظیمات تولید کد را اصلاح کنید.', 400);
        }
        $code = '';
        for ($i = 0; $i < $length; $i++) {
            $code .= $characters[rand(0, strlen($characters) - 1)];
        }

        return $code;
    }

}
