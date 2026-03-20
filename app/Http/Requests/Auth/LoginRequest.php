<?php

namespace App\Http\Requests\Auth;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use App\Models\User;

class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'login' => ['required', 'string'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Attempt to authenticate the request's credentials.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        $login = (string) $this->input('login');
        $password = (string) $this->input('password');
        $remember = $this->boolean('remember');

        $user = null;
        if ($login !== '') {
            $user = User::query()
                ->where('email', $login)
                ->orWhere('username', $login)
                ->orWhere('name', $login)
                ->first();
        }

        if ($user && $user->approval_status !== 'approved') {
            if ($user->approval_status === 'rejected') {
                throw ValidationException::withMessages([
                    'login' => 'Your account request was rejected. Please contact an administrator.',
                ]);
            }

            throw ValidationException::withMessages([
                'login' => 'Your request is still pending approval by the Regional Director.',
            ]);
        }

        $attempted = false;
        if ($login !== '') {
            $attempted = Auth::attempt(['email' => $login, 'password' => $password], $remember);
            if (! $attempted) {
                $attempted = Auth::attempt(['username' => $login, 'password' => $password], $remember);
            }
            if (! $attempted) {
                $attempted = Auth::attempt(['name' => $login, 'password' => $password], $remember);
            }
        }

        if (! $attempted) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'login' => trans('auth.failed'),
            ]);
        }

        RateLimiter::clear($this->throttleKey());
    }

    /**
     * Ensure the login request is not rate limited.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'login' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the rate limiting throttle key for the request.
     */
    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('login')).'|'.$this->ip());
    }
}
