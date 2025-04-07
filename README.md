<h1 align="center">AvinAuth OTP Verification (Redis / Database)</h1> <p align="center"> A lightweight and customizable Laravel package for verifying mobile/email using one-time passwords (OTP) on Redis. </p> <div align="center">
🔐 Secure & Simple    •    📦 Easily Integrates with Laravel    •    ✉️ SMS/Email Ready

</div>

</div>

Verify your user mobile/email with a one-time password using both `Redis` and `Mysql Database`.

## Installation

You can install the package via composer:

```bash
composer require aliarefavin/avinauthpackage
```

Publish config file using:

```bash
php artisan vendor:publish --tag=avinauth-config
```

## Configuration
Configuration is located at config/avinauthconfig.php. Here's what you can adjust:

`connection`: you can set your connection to redis or database as you wish

> Note: The default connection is `redis` and we recommend to keep it that way specially for large scale projects.
> If you are not using redis on your project and want to use `database` connection make sure to run `php artisan migrate` after installing the project. 


`code`: OTP code generation rules.

`resend_delay` :  Time delay (in seconds) before allowing another resend.

`max_attemps`: Maximum verification attempts allowed.

`max_resends`: Total resends allowed in one hour.


## Usage

### Send code to the user

```php
use AliArefAvin\AvinAuthPackage\Services\AvinAuthService;


$result = (new AvinAuthService())->sendOTP($mobile, $request, $class);
        // Check if the request was successful
        if ($result['success'] && !array_key_exists('seconds', $result)) {
            // If the request was successful and the 'seconds' key is not present in the result,
            // set the 'seconds' key to the configured resend delay time
            $result['seconds'] = config('avinauthconfig.resend_delay');
        }
        // Return the result array, which contains information about the request status
        return $result;
```

`$class` is your class that implements `AliArefAvin\AvinAuthPackage\Contracts\AvinAuthInterface` 

this `$class` contains your custom function named `send` that you use to send the code for users `(email|sms)` for exmaple your `$class` will be `new AuthenticateService()`

```php
namespace App\Services\Auth;

use AliArefAvin\AvinAuthPackage\Contracts\AvinAuthInterface;

class AuthenticateService implements AvinAuthInterface
{
    public function send(string $receiver, string $code)
    {
        try {
            $Message = 'کد ورود شما:' . PHP_EOL .
                "code: $code" . PHP_EOL ;
                // If the receiver is not in the list of test numbers, send the SMS notification
                Notification::route(NotifyType::TSMS, []) // Set the notification route for SMS
                ->notify(
                    new SendSmsNotification( // Create a new SMS notification
                        $receiver, // The receiver's mobile number
                        $Message // The message content
                    )
                );
            return true; // Return true if the message was sent successfully
        } catch (Exception $e) {
            // If there is an exception (e.g., sending fails), return false
            return false; // Indicate that sending the code failed
        }
    }
}

```

### Verify
You can verify code with request validation.

```php
$request->validate([
    'mobile' => ['required'],
    'code' => 'required|avin_verify:mobile',
]);
```
> `mobile` is your receiver which in this case is mobile.

> Note: You can verify a code just once. so if you need to check code in two different requests then you should use something like the session to handle that.

## Credits & Inspiration 🙌
* This package is heavily inspired by the excellent work of the [Sanjab Verify package](https://github.com/sanjabteam/verify). Special thanks to the Sanjab team for their elegant design and contribution to the Laravel ecosystem.

## Contributing 🛠️

Contributions are welcome!

* Fork the Project
* Create a new branch
* Add your changes
* Submit a pull request 🙌


## License 📝

This project is open-sourced under the MIT license. 
