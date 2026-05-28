<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use App\Models\User;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

/**
 * Temporary endpoint for bcrypt→scrypt password migration.
 * Called by the SvelteKit app on Cloudflare Workers when it encounters
 * a bcrypt hash (which can't be verified on Workers).
 *
 * Remove this once all users have migrated to scrypt hashes.
 */
Route::post('/verify-password', function (Request $request) {
    $secret = $request->header('X-Migration-Secret');
    if ($secret !== config('app.migration_secret')) {
        return response()->json(['error' => 'unauthorized'], 401);
    }

    $request->validate([
        'email' => 'required|email',
        'password' => 'required|string',
    ]);

    $user = User::where('email', $request->email)->first();
    if (!$user) {
        return response()->json(['valid' => false]);
    }

    $valid = Hash::check($request->password, $user->password);

    return response()->json(['valid' => $valid]);
});
