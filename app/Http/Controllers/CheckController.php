<?php

namespace App\Http\Controllers;

use App\Email;
use App\Code;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Annotations as OA;
use Illuminate\Support\Facades\Mail;

/**
 * Class CheckController
 *
 * @OA\Info(
 *      title="Test",
 *      version="1.0"
 * )
 *
 * @package App\Http\Controllers
 */
class CheckController extends Controller
{
    const FIVE_MINUTES_IN_SECONDS = 5 * 60;
    const ONE_HOUR_IN_SECONDS     = 60 * 60;
    const COUNT                   = 5;
    const AVAILABLE_ATTEMPT       = 3;

    /**
     * @OA\Post(
     *      path="/send-code",
     *      description="Send email code",
     *      @OA\Parameter(
     *          name="email",
     *          in="query",
     *          required=true,
     *          @OA\Schema(type="string")
     *      ),
     *      @OA\Response(
     *          response="200",
     *          description="Success",
     *          @OA\JsonContent(example={"code": 1234, "message": "Email send"}),
     *      ),
     *      @OA\Response(
     *          response="400",
     *          description="Bad request",
     *          @OA\JsonContent(example={"message": "Error"}),
     *      ),
     *      @OA\Response(
     *          response="404",
     *          description="Not found",
     *          @OA\JsonContent(example={"message": "Error"}),
     *      )
     * )
     *
     * @param Request $request
     *
     * @return JsonResponse
     * @throws \Exception
     */
    public function sendCode(Request $request)
    {
        $email = $request->input('email');

        if (empty($email)) {
            return response()->json(['message' => 'Email обязателен'], 400);
        }

        $entityEmail = Email::where('email', $email)->first();

        if (empty($entityEmail)) {
            return response()->json(['message' => 'Email не найден'], 404);
        }

        $emailAttributes = $entityEmail->getAttributes();

        if ($emailAttributes['isConfirmed']) {
            return response()->json(['message' => 'Email подтвержден']);
        }

        // Выбрать последние 5 записей
        $collectionCodes =
            Code::where('emailId', $emailAttributes['id'])->orderBy('created_at', 'desc')->take(5)->get();

        if ($collectionCodes->isEmpty()) {
            // Генерируем новый токен, сохраняем и отправляем на email
            $code = getUniqCode();

            addNewCode($code, $emailAttributes['id']);
            sendEmail($emailAttributes['email'], "Код для подтверждения email {$code}");

            $data = ['message' => 'Email с кодом отправлен'];

            if (env('APP_ENV') === 'local') {
                $data['code'] = $code;
            }

            return response()->json($data);
        }

        $count               = $collectionCodes->count();
        $firstItemAttributes = $collectionCodes[$count - 1]->getAttributes();

        // Время в часах, прошедшее с первого запуска
        $time = round(
            (time() - strtotime($firstItemAttributes['created_at']))
            / self::ONE_HOUR_IN_SECONDS
        );

        // Ошибка #2
        if ($count >= self::COUNT && $time <= 1) {
            return response()->json(
                ['message' => 'Нельзя создать запрос для подтверждения email чаще 5 раз за 1 час'], 400
            );
        }

        $lastItemAttributes = $collectionCodes[0]->getAttributes();

        // Время в секундах, прошеднее с последней отправки кода
        $time = time() - strtotime($lastItemAttributes['created_at']);

        // Ошибка #1
        if ($time <= self::FIVE_MINUTES_IN_SECONDS) {
            return response()->json(
                ['message' => 'Нельзя создать запрос для подтверждения email чаще 1 раза в 5 минут'], 400
            );
        }

        // Деактивируем последний код
        $entityCode = Code::where('id', $lastItemAttributes['id'])->first();

        $entityCode->isValid = false;

        $entityCode->save();

        // Генерируем новый токен, сохраняем и отправляем на email
        $code = getUniqCode();

        addNewCode($code, $emailAttributes['id']);
        sendEmail($emailAttributes['email'], "Код для подтверждения email {$code}");

        $data = ['message' => 'Email с кодом отправлен'];

        if (env('APP_ENV') === 'local') {
            $data['code'] = $code;
        }

        return response()->json($data);
    }

    /**
     * @OA\Post(
     *      path="/check-code",
     *      description="Send email code",
     *      @OA\Parameter(
     *          name="email",
     *          in="query",
     *          required=true,
     *          @OA\Schema(type="string")
     *      ),
     *      @OA\Parameter(
     *          name="code",
     *          in="query",
     *          required=true,
     *          @OA\Schema(type="integer")
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Success",
     *          @OA\JsonContent(),
     *      ),
     *      @OA\Response(
     *          response=400,
     *          description="Bad request",
     *          @OA\JsonContent(example={"message": "Error"}),
     *      ),
     *      @OA\Response(
     *          response=404,
     *          description="Not found",
     *          @OA\JsonContent(example={"message": "Error"}),
     *      ),
     * )
     *
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function checkCode(Request $request)
    {
        $email = $request->input('email');
        $code  = $request->input('code');

        if (empty($email) || empty($code)) {
            return response()->json(['message' => 'Email и code обязательны'], 400);
        }

        $entityEmail = Email::where('email', $email)->first();

        if (empty($entityEmail)) {
            return response()->json(['message' => 'Email не найден'], 404);
        }

        $emailAttributes = $entityEmail->getAttributes();

        $entityCode = Code::where('emailId', $emailAttributes['id'])->first();

        if (empty($entityCode)) {
            return response()->json(['message' => 'Код не найден, попробуй отправить запрос на получение кода'], 400);
        }

        if ($entityCode->countAttempt >= self::AVAILABLE_ATTEMPT) {
            if ($entityCode->isValid) {
                $entityCode->isValid = false;

                $entityCode->save();
            }

            return response()->json(['message' => "Код деактивирован, запросите его заново"], 400);
        }

        if ($entityCode->code != $code) {
            $entityCode->countAttempt++;

            if ($entityCode->countAttempt >= self::AVAILABLE_ATTEMPT) {
                $entityCode->isValid = false;

                $entityCode->save();

                return response()->json(['message' => "Код деактивирован, запросите его заново"], 400);
            }

            $entityCode->save();

            $attempt = self::AVAILABLE_ATTEMPT - $entityCode->countAttempt;

            return response()->json(['message' => "Не верный код, у вас осталось {$attempt} попыток"], 400);
        }

        if ($entityCode->code == $code && $entityCode->isValid) {
            $entityEmail->isConfirmed = true;

            $entityEmail->save();

            $entityCode->isValid = false;

            $entityCode->save();

            Code::where('emailId', $emailAttributes['id'])->delete();

            return response()->json(['message' => 'Email подтвержден']);
        }

        return response()->json(['message' => 'Код не валидный или деактивирон, запросите другой код']);
    }
}
