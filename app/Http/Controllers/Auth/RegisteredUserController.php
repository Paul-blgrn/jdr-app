<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Validation\Rules\Password;

class RegisteredUserController extends Controller
{
    /**
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => [
                'required',
                'confirmed',
                Password::min(8)
                    ->letters()
                    ->mixedCase()
                    ->numbers()
                    ->symbols(),
                    // disable temporary "uncompromised" because we are not yet in prod (no ssl)
                    //->uncompromised(),
            ]
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->string('password')),
        ]);

        event(new Registered($user));

        Auth::login($user);

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

        // Retreive the role by name "user"
        $userRole = Role::where('name', 'user')->first();
        // Check if the user role exists
        if (!$userRole) {
            return response()->json([
                'response' => [
                    'status_code' => 404,
                    'status_title' => 'Not Found',
                    'status_message' => 'The user role does not exist.',
                ],
            ], 404);
        }

        // Attach role "user" to the user
        $user->roles()->attach($userRole->id);

        return response()->json([
            'message' => 'Registration successful',
            'user' => $userData,
            'token' => $token,
        ], 200);
    }
}
