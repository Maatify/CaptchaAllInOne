[![Current version](https://img.shields.io/packagist/v/maatify/captcha-all-in-one)][pkg]
[![Packagist PHP Version Support](https://img.shields.io/packagist/php-v/maatify/captcha-all-in-one)][pkg]
[![Monthly Downloads](https://img.shields.io/packagist/dm/maatify/captcha-all-in-one)][pkg-stats]
[![Total Downloads](https://img.shields.io/packagist/dt/maatify/captcha-all-in-one)][pkg-stats]
[![Stars](https://img.shields.io/packagist/stars/maatify/captcha-all-in-one)](https://github.com/maatify/CaptchaAllInOne/stargazers)

[pkg]: <https://packagist.org/packages/maatify/captcha-all-in-one>
[pkg-stats]: <https://packagist.org/packages/maatify/captcha-all-in-one/stats>

# Installation

```shell
composer require maatify/captcha-all-in-one
```

# Env File Should Contain

```dotenv

TURNSTILE_STATUS=1
TURNSTILE_SITE_KEY='turnstile_site_key'
TURNSTILE_SECRET_KEY='turnstile_secret_key'
TURNSTILE_TRIES=4

GOOGLE_RECAPTCHA_V3_STATUS=1
GOOGLE_RECAPTCHA_V3_SITE_KEY='google_recpatcha_v3_site_key'
GOOGLE_RECAPTCHA_V3_SECRET_KEY='google_recaptcha_v3_secret_key'
GOOGLE_RECAPTCHA_V3_TRIES=6

GOOGLE_RECAPTCHA_V2_STATUS=1
GOOGLE_RECAPTCHA_V2_SITE_KEY='google_recpatcha_v2_site_key'
GOOGLE_RECAPTCHA_V2_SECRET_KEY='google_recaptcha_v2_secret_key'
GOOGLE_RECAPTCHA_V2_TRIES=8

HCAPTCHA_STATUS=1
HCAPTCHA_SITE_KEY='hcaptcha_site_key'
HCAPTCHA_SECRET_KEY='hcaptcha_secret_key'
HCAPTCHA_TRIES=10

SESSION_TIMEOUT_CAPTCHA='60' ## IN MINUTES
```

# Usage

```PHP
<?php
/**
 * Created by Maatify.dev
 * User: Maatify.dev
 * Date: 2024-08-10
 * Time: 12:17 AM
 * https://www.Maatify.dev
 */
 
use Maatify\CaptchaV1\CaptchaManager;

require 'vendor/autoload.php';

try {
    $captcha = CaptchaManager::getInstance();
    
    // ===== get config in array format to use in HTML code
    $config = $captcha->getCaptchaConfig();
    
    // ===== get result in array format
    $result = $captcha->getResponse();
    
    // ====== get bool of validation 
    $result = $captcha->isSuccess();
    
    // ====== using maatify json on error response with json code with die and if success there is no error
    $captcha->jsonErrors();

} catch (Exception $e) {

    echo $e->getMessage();
    
}
```

#### jsonErrors();
>##### Error Example
>
>   Header 400
>
>   Body:
>
> - on validation error
>
>```json
>   {
>        "success": false,
>        "response": 40002,
>        "var": "captcha",
>        "description": {
>            "success": false,
>            "error-codes": [
>                "missing-input-response"
>            ],
>            "messages": [],
>            "next_captcha": "turnstile"
>        },
>        "more_info": "Invalid Validation",
>        "error_details": "test:159"
>    }
>```


### Create From in HTML Code

```php
if(!empty($config['type'])){
    switch ($config['type']) {
        case 'turnstile':
            ?>
            <form action="test.php" method="POST">
                <input name="test" value="test">
                <!-- Your other form fields -->
                <div class="cf-turnstile" data-sitekey="<?= $config['site_key'] ?>" data-theme="dark" data-language="ar"></div>
                <input type="submit" value="Submit">
            </form>
    
            <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
            <?php
            break;
    
        case 'google_v2':
            ?>
                <form method="POST">
             Your other form fields
                    <div class="g-recaptcha" data-sitekey="<?= $config['site_key'] ?>" data-theme="dark" ></div>
                    <input type="submit" value="Submit">
                </form>
    
                <script src="https://www.google.com/recaptcha/api.js?hl=ar" async defer ></script>
            <?php
            break;
    
        case 'google_v3':
            ?>
            <script src="https://www.google.com/recaptcha/api.js?hl=ar"></script>
    
            <script>
                function onSubmit(token) {
                    document.getElementById("demo-form").submit();
                }
            </script>
    
    
            <form method="POST" id="demo-form">
                <input name="test" value="test">
                <button class="g-recaptcha"
                        data-sitekey="<?= $config['site_key'] ?>"
                        data-callback='onSubmit'
                        data-action='submit'>Submit</button>
    
            </form>
            <?php
            break;
    
        case 'hcaptcha':
            ?>
            <form method="POST">
                <!-- Your other form fields -->
                <div class="h-captcha" data-sitekey="<?= $config['site_key'] ?>" data-theme="dark" data-hl="ar"></div>
                <input type="submit" value="Submit">
            </form>
    
            <script src="https://js.hcaptcha.com/1/api.js" async defer></script>
            <?php
            break;
    }
}
```