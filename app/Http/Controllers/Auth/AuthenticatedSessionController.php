<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class AuthenticatedSessionController extends Controller
{
    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): JsonResponse
    {
        $request->authenticate();
        $request->session()->regenerate();

        $user = Auth::user();
        $existingToken  = $user->tokens()->where('name', 'auth_token')->latest()->first();
        $expiration = now()->addMinutes(120);
        if (!$existingToken ) {
            $token = $user->createToken('auth_token', ['*'], $expiration)->plainTextToken;
        } else {
            $user->tokens()->delete();
            $token = $user->createToken('auth_token', ['*'], $expiration)->plainTextToken;
        }

        $userData = [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ];

        $response =  response()->json([
            'message' => 'Authentication successful',
            'user' => $userData,
            'token' => $token,
        ]);
        $response->header('Authorization', 'Bearer' . $token);

        return $response;
    }

    public function checkAuth(Request $request): JsonResponse
    {
        if (!Auth::check()) {
            // Si l'utilisateur n'est pas authentifié, renvoyez la réponse avec l'état non authentifié
            return response()->json(['authenticated' => false]);
        }

        $user = Auth::user();
        Log::info('Utilisateur authentifié', ['user' => $user->toArray()]);

        $token = $request->bearerToken();
        Log::info('Token reçu', ['token' => $token]);

        // Vérifiez si un token est fourni
        if ($token) {
            // Trouver le token par ID (avant le hachage)
            $parts = explode('|', $token, 2);

            if (count($parts) === 2) {
                list($id, $tokenValue) = $parts;
                $validToken = $user->tokens()->find($id);

                if ($validToken) {
                    if ($validToken->expires_at && $validToken->expires_at->isPast()) {
                        Log::info('Token expiré', ['expires_at' => $validToken->expires_at]);
                        $user->tokens()->delete();
                        Auth::guard('web')->logout();
                        return response()->json(['authenticated' => false, 'message' => 'Token expiré'], 401);
                    }
                    if (!$validToken->can('*')) {
                        Log::warning('Token invalide', ['token' => $tokenValue]);
                        $user->tokens()->delete();
                        Auth::guard('web')->logout();
                        return response()->json(['authenticated' => false, 'message' => 'Token invalide'], 401);
                    }
                } else {
                    Log::error('Format de token invalide', ['token' => $token]);
                    $user->tokens()->delete();
                    Auth::guard('web')->logout();
                    return response()->json(['authenticated' => false, 'message' => 'Token non trouvé'], 401);
                }
            } else {
                Log::error('Format de token invalide', ['token' => $token]);
                $user->tokens()->delete();
                Auth::guard('web')->logout();
                return response()->json(['authenticated' => false, 'message' => 'Format de token invalide'], 401);
            }
        }

        return response()->json([
            'authenticated' => true,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ]
        ]);
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): JsonResponse
    {
        // Retreive the user
        $user = $request->user();
        // delete all authentification tokens from the user
        $user->tokens()->delete();

        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return response()->json(['message' => 'Logged out successfully.']);
    }
}
