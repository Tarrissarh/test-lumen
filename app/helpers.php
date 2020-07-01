<?php

use App\Code;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

if (!function_exists('getUniqCode')) {
    /**
     * Уникальный код для подтверждения email
     *
     * @return string
     * @throws Exception
     */
    function getUniqCode()
    {
        // Генерируем новый токен, сохраняем и отправляем на email
        $code = random_int(0, 9999);

        if (strlen($code) == 3) {
            $code = '000' . $code;
        } elseif (strlen($code) == 2) {
            $code = '00' . $code;
        } elseif (strlen($code) == 1) {
            $code = '0' . $code;
        }

        $entityCode = Code::where('code', $code)->first();

        if (empty($entityCode)) {
            return $code;
        }

        return getUniqCode();
    }
}

if (!function_exists('addNewCode')) {
    /**
     * Add new code
     *
     * @param string $code
     * @param int    $emailId
     */
    function addNewCode(string $code, int $emailId)
    {
        $token = Code::create(
            [
                'emailId' => $emailId,
                'code'    => $code,
                'isValid' => true,
            ]
        );

        $token->save();
    }
}

if (!function_exists('sendEmail')) {
    /**
     * Send email
     *
     * @param string $email
     * @param string $txt
     */
    function sendEmail(string $email, string $txt)
    {
        Mail::raw(
            $txt,
            function ($message) use ($email) {
                $message->to($email)->subject('Подтверждение email');
            }
        );
    }
}
