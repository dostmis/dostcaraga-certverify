<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    private function isSpaRequest(Request $request): bool
    {
        return $request->expectsJson()
            || $request->ajax()
            || $request->wantsJson()
            || $request->header('X-Requested-With') === 'XMLHttpRequest';
    }

    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse|JsonResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        if ($this->isSpaRequest($request)) {
            $user = $request->user();
            $role = $user?->isRegionalDirector()
                ? User::ROLE_REGIONAL_DIRECTOR
                : ($user?->role ?? User::ROLE_ORGANIZER);

            return response()->json([
                'message' => 'Authenticated.',
                'redirect' => route('dashboard'),
                'user' => [
                    'id' => $user?->id ?? 0,
                    'name' => $user?->name ?? '',
                    'username' => $user?->username ?? '',
                    'email' => $user?->email ?? '',
                    'role' => $role,
                    'status' => $user?->approval_status ?? 'approved',
                ],
            ]);
        }

        return redirect()->route('dashboard');
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse|JsonResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($this->isSpaRequest($request)) {
            return response()->json([
                'message' => 'Logged out.',
                'redirect' => route('login'),
            ]);
        }

        return redirect()->route('login');
    }
}
