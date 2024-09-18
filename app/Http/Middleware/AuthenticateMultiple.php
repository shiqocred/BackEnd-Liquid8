<?php 
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Http\Resources\ResponseResource;
use Illuminate\Support\Facades\Log;
use App\Models\User; // Pastikan ini ada


class AuthenticateMultiple
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  mixed ...$roles
     * @return mixed
     */
    public function handle(Request $request, Closure $next, ...$roles)
    {
        $authorizationHeader = $request->header('Authorization');

        // 1. Cek otentikasi menggunakan Sanctum
        if (Auth::guard('sanctum')->check()) {
            $user = Auth::guard('sanctum')->user(); // Ambil pengguna dari Auth guard

            if (!$user) {
                // Jika pengguna masih null, kembalikan respon Unauthorized
                return response()->json(['message' => 'Unauthorized'], 401);
            }

            // Eager load relasi 'role'
            $user->load('role');

            // Debug: Coba ambil peran pengguna
            if ($user->role) {
                Log::info('User role:', ['role_name' => $user->role->role_name]);
            } else {
                Log::info('User has no role.');
            }

            // Cek jika pengguna memiliki peran yang benar seperti pada middleware CheckRole
            if (!in_array($user->role->role_name, $roles)) {
                $resource = new ResponseResource(false, "anda tidak berhak mengakses halaman ini", null);
                return $resource->response()->setStatusCode(403);
            }

            return $next($request);
        }

        // 2. Cek otentikasi menggunakan API key (jika header Authorization tidak mengandung 'Bearer')
        if ($authorizationHeader && strpos($authorizationHeader, 'Bearer ') !== 0) {
            $apiKey = $authorizationHeader;

            if (User::where('api_key', $apiKey)->exists()) {
                return $next($request);
            }
        }

        // 3. Jika kedua metode otentikasi gagal
        return response()->json(['message' => 'Unauthorized'], 401);
    }
}
