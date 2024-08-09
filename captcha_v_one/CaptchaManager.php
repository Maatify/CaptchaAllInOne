<?php
/**
 * @PHP         Version >= 8.0
 * @Liberary    CaptchaAllInOne
 * @Project     CaptchaAllInOne
 * @copyright   ©2024 Maatify.dev
 * @see         https://www.maatify.dev Visit Maatify.dev
 * @link        https://github.com/Maatify/CaptchaAllInOne View project on GitHub
 * @since       2024-08-06 8:04 AM
 * @author      Mohamed Abdulalim (megyptm) <mohamed@maatify.dev>
 * @Maatify     CaptchaAllInOne :: CaptchaManager
 * @note        This Project using for Call CaptchaAllInOne Turnstile, HCaptcha and Google Recaptcha Validation
 *
 * This program is distributed in the hope that it will be useful - WITHOUT
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or
 * FITNESS FOR A PARTICULAR PURPOSE.
 *
 */

namespace Maatify\CaptchaV1;

use Exception;
use Maatify\Functions\GeneralFunctions;
use Maatify\GoogleRecaptchaV2\GoogleReCaptchaV2Validation;
use Maatify\HCaptcha\HCaptchaPublisherProValidation;
use Maatify\Json\Json;
use Maatify\Logger\Logger;
use Maatify\Turnstile\TurnstileValidation;

class CaptchaManager
{
    public TurnstileValidation $turnstile;
    public GoogleReCaptchaV2Validation $googleV3;
    public GoogleReCaptchaV2Validation $googleV2;
    public HCaptchaPublisherProValidation $hCaptcha;
    private string $captcha_type;
    private static ?self $instance = null;
    private int $timeout;
    private string $current_captcha_type;

    /**
     * @throws Exception
     */
    public static function getInstance(): self
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * @throws Exception
     */
    public function __construct()
    {
        if (! empty($_ENV['TURNSTILE_STATUS']) && ! empty($_ENV['TURNSTILE_SECRET_KEY'])) {
            $this->turnstile = new TurnstileValidation($_ENV['TURNSTILE_SECRET_KEY']);
        }

        if (! empty($_ENV['GOOGLE_RECAPTCHA_V2_STATUS']) && ! empty($_ENV['GOOGLE_RECAPTCHA_V2_SECRET_KEY'])) {
            $this->googleV2 = new GoogleReCaptchaV2Validation($_ENV['GOOGLE_RECAPTCHA_V2_SECRET_KEY']);
        }

        if (! empty($_ENV['GOOGLE_RECAPTCHA_V3_STATUS']) && ! empty($_ENV['GOOGLE_RECAPTCHA_V3_SECRET_KEY'])) {
            $this->googleV3 = new GoogleReCaptchaV2Validation($_ENV['GOOGLE_RECAPTCHA_V3_SECRET_KEY']);
        }

        if (! empty($_ENV['HCAPTCHA_STATUS']) && ! empty($_ENV['HCAPTCHA_SECRET_KEY'])) {
            $this->hCaptcha = new HCaptchaPublisherProValidation($_ENV['HCAPTCHA_SECRET_KEY']);
        }

        $this->timeout = $_ENV['SESSION_TIMEOUT_CAPTCHA'] * 60;

        if (! isset($_SESSION['captcha_fails']) || ! isset($_SESSION['captcha_fails_last_time'])) {
            $_SESSION['captcha_fails'] = 0;
            $_SESSION['captcha_fails_last_time'] = time();
        } else {
            $this->checkTimeout();
        }

        $this->setCaptchaType();
    }

    private function checkTimeout(): void
    {
        $currentTime = time();
        if (($currentTime - $_SESSION['captcha_fails_last_time']) > $this->timeout) {
            // Reset if timeout has passed
            $_SESSION['captcha_fails'] = 0;
            $_SESSION['captcha_fails_last_time'] = $currentTime;
        }
    }

    private function setCaptchaType(): void
    {
        $this->checkTimeout();
        $fails = $_SESSION['captcha_fails'];

        if (! empty($_ENV['TURNSTILE_TRIES']) && $fails <= $_ENV['TURNSTILE_TRIES'] && ! empty($this->turnstile)) {
            $this->captcha_type = 'turnstile';
        } elseif (! empty($_ENV['GOOGLE_RECAPTCHA_V3_TRIES']) && $fails <= $_ENV['GOOGLE_RECAPTCHA_V3_TRIES'] && ! empty($this->googleV3)) {
            $this->captcha_type = 'google_v3';
        } elseif (! empty($_ENV['GOOGLE_RECAPTCHA_V2_TRIES']) && $fails <= $_ENV['GOOGLE_RECAPTCHA_V2_TRIES'] && ! empty($this->googleV2)) {
            $this->captcha_type = 'google_v2';
        } elseif (! empty($_ENV['HCAPTCHA_TRIES']) && $fails <= $_ENV['HCAPTCHA_TRIES'] && ! empty($this->hCaptcha)) {
            $this->captcha_type = 'hcaptcha';
        } else {
            $this->captcha_type = '';
            //            throw new Exception("Unknown CAPTCHA type, there is no active captcha or you have so many attempts", 1);
        }

        if (empty($this->current_captcha_type)) {
            $this->current_captcha_type = $this->captcha_type;
        }
    }

    public function getCaptchaType(): string
    {
        $this->setCaptchaType();

        return $this->captcha_type;
    }


    /**
     * @throws Exception
     */
    public function isSuccess(): bool
    {
        $this->current_captcha_type = $this->captcha_type;
        $is_success = match ($this->captcha_type) {
            'turnstile' => $this->turnstile->isSuccess(),
            'google_v3' => $this->googleV3->isSuccess(),
            'google_v2' => $this->googleV2->isSuccess(),
            'hcaptcha' => $this->hCaptcha->isSuccess(),
            default => throw new Exception("Unknown CAPTCHA type"),
        };

        if (! $is_success) {
            $_SESSION['captcha_fails']++;
            $_SESSION['captcha_fails_last_time'] = time();
            $this->setCaptchaType();
        } else {
            // Reset fails on success
            $_SESSION['captcha_fails'] = 0;
            $_SESSION['captcha_fails_last_time'] = time();
        }

        return $is_success;
    }

    /**
     * @throws Exception
     */
    public function jsonErrors(): void
    {
        $is_success = $this->isSuccess();
        if (! $is_success) {
            $response = $this->getResponse();
            $this->captchaInvalid($response, $response['error-codes'][0] ?? '', __LINE__);
        }
    }

    /**
     * @throws Exception
     */
    public function getResponse(): array
    {
        $is_success = $this->isSuccess();

        $result = match ($this->current_captcha_type) {
            'turnstile' => $this->turnstile->getResponse(),
            'google_v3' => $this->googleV3->getResponse(),
            'google_v2' => $this->googleV2->getResponse(),
            'hcaptcha' => $this->hCaptcha->getResponse(),
            default => throw new Exception("Unknown CAPTCHA type"),
        };

        if (! $is_success) {
            $result['next_captcha'] = $this->getCaptchaType();
        } else {
            // Reset fails on success
            $result['next_captcha'] = null;
        }


        return $result;
    }

    /**
     * @throws Exception
     */
    public function getCaptchaConfig(): array
    {
        $type = $this->getCaptchaType();

        return [
            'type'     => $type,
            'site_key' => $this->getSiteKey(),
        ];
    }

    public function getSiteKey(): string
    {
        // You should store these securely, this is just for demonstration
        $type = $this->getCaptchaType();
        $siteKeys = [
            'turnstile' => $_ENV['TURNSTILE_SITE_KEY'],
            'google_v3' => $_ENV['GOOGLE_RECAPTCHA_V3_STATUS'],
            'google_v2' => $_ENV['GOOGLE_RECAPTCHA_V3_STATUS'],
            'hcaptcha'  => $_ENV['HCAPTCHA_SITE_KEY'],
        ];

        return $siteKeys[$type] ?? '';
    }

    public function captchaInvalid(array|string $description = '', string $more_info = '', int|string $line = 0): void
    {
        if (empty($more_info)) {
            $more_info = 'Invalid Validation';
        }
        $this->ErrorWithHeader400($description, $more_info, line: $line ? : debug_backtrace()[0]['line']);
    }

    private function ErrorWithHeader400(array|string $description = '',
        string $moreInfo = '',
        int|string $line = ''
    ): void
    {
        http_response_code(400);
        $this->HeaderResponseJson([
            'success'       => false,
            'response'      => 40002,
            'var'           => 'captcha',
            'description'   => $description,
            'more_info'     => $moreInfo,
            'next_captcha'  => $this->getCaptchaType(),
            'error_details' => GeneralFunctions::CurrentPageError($line),
        ]);
    }


    private function HeaderResponseJson($json_array): void
    {
        if (! empty($_ENV['JSON_POST_LOG'])) {
            $this->LoggerResponseAndPost($json_array);
        }
        header('Content-type: application/json; charset=utf-8');
        echo(json_encode($json_array,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES));
        exit();
    }


    private function LoggerResponseAndPost($json_array): void
    {
        $arr = $_POST;
        if (isset($_POST['password']) & ! empty($_POST['password'])) {
            $arr['password'] = '*******';
        }

        if (! empty($_POST['base64_file'])) {
            if (base64_encode(base64_decode($_POST['base64_file'], true)) === $_POST['base64_file']) {
                $arr['base64_file'] = 'Valid Base64';
            } else {
                $arr['base64_file'] = 'Not Valid Base64';
            }
        }
        if (isset($json_array['result']['base64'])) {
            unset($json_array['result']['base64']);
        }

        // Handle Logger APP Folder
        $url = $_SERVER['REQUEST_URI'];
        $url = ltrim($url, '/');
        $urlParts = explode('/', $url);
        $app_type = $urlParts[0] ?? '';
        if (! empty($app_type) && $app_type == 'apps') {
            $app_folder_logger = ($urlParts[0] ?? '') . '-' . ($urlParts[1] ?? '');
        } else {
            $app_folder_logger = $urlParts[0] ?? '';
        }

        Logger::RecordLog(['Response'    => $json_array,
                           'posted_data' => ($arr ?? ''),
                           'agent'       => $_SERVER['HTTP_USER_AGENT'],
                           'ip'          => $_SERVER['REMOTE_ADDR'] ?? '',
                           'real_ip'     => $_SERVER['REMOTE_ADDR'],
                           'forward_ip'  => ($_SERVER['HTTP_X_FORWARDED_FOR'] ??
                                             ''),
                           'server_ip'   => ($_SERVER['SERVER_ADDR'] ?? ''),
                           'referer'     => ($_SERVER['HTTP_REFERER'] ?? ''),
                           'uri'         => ($_SERVER['REQUEST_URI'] ?? ''),
                           'page'        => (basename($_SERVER['PHP_SELF']) ??
                                             ''),
        ],
            'post/' .
            ($app_folder_logger ? $app_folder_logger . '/' : '') .
            (basename($_SERVER["PHP_SELF"], '.php') ?? 'posted') .
            '_response');
    }
}
