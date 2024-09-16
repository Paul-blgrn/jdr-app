<?php

use Illuminate\Support\Facades\Route;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

Route::get('/', function () {
    return ['Laravel' => app()->version()];
});

Route::options('/{any}', function () {
    return response()->json(['message' => 'Preflight Request']);
})->where('any', '.*')->middleware(\Illuminate\Http\Middleware\HandleCors::class);

// Route::post('/sanctum/token', function (Request $request) {
//     $request->validate([
//         'email' => 'required|email',
//         'password' => 'required',
//         'device_name' => 'required',
//     ]);

//     $user = User::where('email', $request->email)->first();

//     if (! $user || ! Hash::check($request->password, $user->password)) {
//         throw ValidationException::withMessages([
//             'email' => ['The provided credentials are incorrect.'],
//         ]);
//     }

//     return $user->createToken($request->device_name)->plainTextToken;
// });

require __DIR__.'/auth.php';
