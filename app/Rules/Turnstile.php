<?php

namespace App\Rules;

use App\Services\TurnstileService;
use Illuminate\Contracts\Validation\Rule;

class Turnstile implements Rule
{
    protected ?string $ip;
    protected string $message = 'Security verification failed. Please verify you are human and try again.';

    /**
     * Create a new rule instance.
     *
     * @param string|null $ip
     */
    public function __construct(?string $ip = null)
    {
        $this->ip = $ip;
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        return app(TurnstileService::class)->verify($value, $this->ip);
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return $this->message;
    }
}
