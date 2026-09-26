<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class LoginProtection
{
    private const CAPTCHA_AFTER_FAILURES = 1;

    private const MAX_FAILURES = 8;

    private const FAILURE_WINDOW_SECONDS = 900;

    private const BLOCK_SECONDS = 1800;

    private const CAPTCHA_SECONDS = 300;

    public function blockedResponse(Request $request): ?JsonResponse
    {
        $until = (int) Cache::get($this->blockedKey($request), 0);
        $retryAfter = $until - now()->timestamp;

        if ($retryAfter <= 0) {
            return null;
        }

        $minutes = max(1, (int) ceil($retryAfter / 60));
        $message = "Se bloquearon temporalmente los intentos desde esta IP. Vuelve a probar en {$minutes} min.";

        return response()->json([
            'message' => $message,
            'errors' => ['ip' => [$message]],
            'blocked' => true,
            'retry_after' => $retryAfter,
        ], 429)->header('Retry-After', (string) $retryAfter)
            ->header('Cache-Control', 'no-store, private');
    }

    public function verifyCaptchaIfRequired(Request $request): bool
    {
        if (! $this->captchaRequired($request)) {
            return true;
        }

        $id = $request->input('captcha_id');
        $answer = $request->input('captcha_answer');

        if (! is_string($id) || ! preg_match('/^[A-Za-z0-9]{48}$/', $id) || ! is_string($answer)) {
            return false;
        }

        $expected = Cache::pull($this->captchaKey($request, $id));

        return is_string($expected) && hash_equals($expected, trim($answer));
    }

    public function challengeForRetry(Request $request): array
    {
        return $this->captchaRequired($request) ? $this->newCaptcha($request) : [];
    }

    public function failed(Request $request, string $field, string $message): JsonResponse
    {
        RateLimiter::hit($this->failuresKey($request), self::FAILURE_WINDOW_SECONDS);
        $failures = RateLimiter::attempts($this->failuresKey($request));

        if ($failures >= self::MAX_FAILURES) {
            $until = now()->addSeconds(self::BLOCK_SECONDS);
            Cache::put($this->blockedKey($request), $until->timestamp, $until);

            return $this->blockedResponse($request) ?? response()->json([
                'message' => 'Demasiados intentos fallidos. Vuelve a probar más tarde.',
                'blocked' => true,
                'retry_after' => self::BLOCK_SECONDS,
            ], 429)->header('Cache-Control', 'no-store, private');
        }

        $response = [
            'message' => $message,
            'errors' => [$field => [$message]],
        ];

        if ($field === 'two_factor_code') {
            $response['two_factor_required'] = true;
        }

        if ($failures >= self::CAPTCHA_AFTER_FAILURES) {
            $response += $this->newCaptcha($request);
        }

        return response()->json($response, 422)->header('Cache-Control', 'no-store, private');
    }

    public function succeeded(Request $request): void
    {
        RateLimiter::clear($this->failuresKey($request));
        Cache::forget($this->blockedKey($request));
    }

    private function captchaRequired(Request $request): bool
    {
        return RateLimiter::attempts($this->failuresKey($request)) >= self::CAPTCHA_AFTER_FAILURES;
    }

    private function newCaptcha(Request $request): array
    {
        $left = random_int(1, 9);
        $right = random_int(1, 9);
        $id = Str::random(48);

        Cache::put(
            $this->captchaKey($request, $id),
            (string) ($left + $right),
            now()->addSeconds(self::CAPTCHA_SECONDS)
        );

        return [
            'captcha_required' => true,
            'captcha_id' => $id,
            'captcha_question' => "Verificación: ¿cuánto es {$left} + {$right}?",
        ];
    }

    private function fingerprint(Request $request): string
    {
        $ip = (string) ($request->ip() ?: 'unknown');

        return hash_hmac('sha256', $ip, (string) config('app.key'));
    }

    private function failuresKey(Request $request): string
    {
        return 'nexo:login:failures:'.$this->fingerprint($request);
    }

    private function blockedKey(Request $request): string
    {
        return 'nexo:login:blocked:'.$this->fingerprint($request);
    }

    private function captchaKey(Request $request, string $id): string
    {
        return 'nexo:login:captcha:'.$this->fingerprint($request).':'.hash('sha256', $id);
    }
}
