<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

class TwoFactorAuth
{
    private const BASE32 = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public function newSecret(): string
    {
        return $this->encodeBase32(random_bytes(20));
    }

    public function provisioningUri(User $user, string $secret): string
    {
        $label = rawurlencode('Nexo:'.$user->email);

        return 'otpauth://totp/'.$label.'?'.http_build_query([
            'secret' => $secret,
            'issuer' => 'Nexo',
            'algorithm' => 'SHA1',
            'digits' => 6,
            'period' => 30,
        ], '', '&', PHP_QUERY_RFC3986);
    }

    public function matchingStep(string $secret, string $code, ?int $timestamp = null): ?int
    {
        $code = trim($code);
        if (! preg_match('/^\d{6}$/D', $code)) {
            return null;
        }

        $key = $this->decodeBase32($secret);
        if ($key === '') {
            return null;
        }

        $currentStep = intdiv($timestamp ?? now()->timestamp, 30);
        foreach ([$currentStep - 1, $currentStep, $currentStep + 1] as $step) {
            $expected = $this->hotp($key, $step);
            if (hash_equals($expected, $code)) {
                return $step;
            }
        }

        return null;
    }

    public function verifyAndConsume(User $user, string $code): bool
    {
        return DB::transaction(function () use ($user, $code): bool {
            $locked = User::query()->lockForUpdate()->find($user->id);
            if (! $locked || ! $locked->two_factor_confirmed_at || ! $locked->two_factor_secret) {
                return false;
            }

            try {
                $secret = Crypt::decryptString($locked->two_factor_secret);
            } catch (\Throwable) {
                return false;
            }

            $step = $this->matchingStep($secret, $code);
            if ($step !== null) {
                if ($locked->two_factor_last_used_step !== null && $step <= $locked->two_factor_last_used_step) {
                    return false;
                }

                $locked->two_factor_last_used_step = $step;
                $locked->save();

                return true;
            }

            $candidate = $this->recoveryHash($code);
            $codes = $locked->two_factor_recovery_codes ?? [];
            foreach ($codes as $index => $stored) {
                if (is_string($stored) && hash_equals($stored, $candidate)) {
                    unset($codes[$index]);
                    $locked->two_factor_recovery_codes = array_values($codes);
                    $locked->save();

                    return true;
                }
            }

            return false;
        });
    }

    public function makeRecoveryCodes(): array
    {
        return array_map(
            fn () => strtoupper(bin2hex(random_bytes(5))),
            range(1, 10)
        );
    }

    public function hashRecoveryCodes(array $codes): array
    {
        return array_map(fn (string $code) => $this->recoveryHash($code), $codes);
    }

    public function encryptedSecret(string $secret): string
    {
        return Crypt::encryptString($secret);
    }

    public function decryptSecret(string $secret): string
    {
        return Crypt::decryptString($secret);
    }

    private function recoveryHash(string $code): string
    {
        return hash_hmac('sha256', strtoupper(trim($code)), (string) config('app.key'));
    }

    private function hotp(string $key, int $step): string
    {
        $counter = pack('N2', ($step >> 32) & 0xffffffff, $step & 0xffffffff);
        $hash = hash_hmac('sha1', $counter, $key, true);
        $offset = ord($hash[19]) & 0x0f;
        $binary = ((ord($hash[$offset]) & 0x7f) << 24)
            | ((ord($hash[$offset + 1]) & 0xff) << 16)
            | ((ord($hash[$offset + 2]) & 0xff) << 8)
            | (ord($hash[$offset + 3]) & 0xff);

        return str_pad((string) ($binary % 1_000_000), 6, '0', STR_PAD_LEFT);
    }

    private function encodeBase32(string $bytes): string
    {
        $bits = '';
        foreach (unpack('C*', $bytes) as $byte) {
            $bits .= str_pad(decbin($byte), 8, '0', STR_PAD_LEFT);
        }

        $encoded = '';
        foreach (str_split($bits, 5) as $chunk) {
            if (strlen($chunk) < 5) {
                $chunk = str_pad($chunk, 5, '0', STR_PAD_RIGHT);
            }
            $encoded .= self::BASE32[bindec($chunk)];
        }

        return $encoded;
    }

    private function decodeBase32(string $encoded): string
    {
        $bits = '';
        foreach (str_split(strtoupper(rtrim($encoded, '='))) as $character) {
            $value = strpos(self::BASE32, $character);
            if ($value === false) {
                return '';
            }
            $bits .= str_pad(decbin($value), 5, '0', STR_PAD_LEFT);
        }

        $bytes = '';
        foreach (str_split($bits, 8) as $chunk) {
            if (strlen($chunk) === 8) {
                $bytes .= chr(bindec($chunk));
            }
        }

        return $bytes;
    }
}
