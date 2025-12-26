<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Support\Facades\Http;
use Throwable;

class Recaptcha implements Rule
{
    /**
     * Determine if the validation rule passes.
     */
    public function passes($attribute, $value)
    {
        $secret = config('services.recaptcha.secret_key');
        $enabled = config('services.recaptcha.enabled', false);

        if (!$enabled || empty($secret)) {
            // Skip validation when reCAPTCHA is not configured
            return true;
        }

        if (empty($value)) {
            return false;
        }

        try {
            $response = Http::asForm()->post('https://www.google.com/recaptcha/api/siteverify', [
                'secret' => $secret,
                'response' => $value,
                'remoteip' => request()->ip(),
            ]);

            if (!$response->ok()) {
                return false;
            }

            return (bool) $response->json('success', false);
        } catch (Throwable $exception) {
            report($exception);
            return false;
        }
    }

    /**
     * Get the validation error message.
     */
    public function message()
    {
        return 'reCAPTCHA verification failed. Please try again.';
    }
}
