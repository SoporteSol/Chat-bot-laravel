<?php

namespace App\Http\Controllers;

use App\Models\User;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use PHPOpenSourceSaver\JWTAuth\JWTGuard;

class AuthController extends Controller
{
    protected function guard(): JWTGuard
    {
        /** @var JWTGuard $guard */
        $guard = auth('api');
        return $guard;
    }

    // Login vía API con JWT
    public function login(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'email'    => 'required|email|max:255',
                'password' => 'required|string|min:6|max:255',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors'  => $validator->errors(),
                ], 422);
            }

            $credentials = $request->only('email', 'password');

            if (!$token = $this->guard()->attempt($credentials)) {
                return response()->json([
                    'success' => false,
                    'error'   => 'Credenciales incorrectas o no autorizadas',
                ], 401);
            }

            return $this->respondWithToken((string) $token);
        } catch (Exception $e) {
            Log::error('Error en AuthController@login: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'error'   => 'Error interno al procesar la autenticación',
            ], 500);
        }
    }

    // Datos del usuario actual con su rol y permisos
    public function me(): JsonResponse
    {
        /** @var User|null $user */
        $user = $this->guard()->user();

        if (!$user) {
            return response()->json(['error' => 'No autenticado'], 401);
        }

        return response()->json([
            'success' => true,
            'user'    => $user->load('role.permissions'),
        ]);
    }

    // Cierra sesión e invalida el token JWT
    public function logout(): JsonResponse
    {
        $this->guard()->logout();

        return response()->json([
            'success' => true,
            'message' => 'Sesión cerrada exitosamente. Token invalidado.',
        ]);
    }

    // Renovación del token
    public function refresh(): JsonResponse
    {
        return $this->respondWithToken((string) $this->guard()->refresh());
    }

    protected function respondWithToken(string $token): JsonResponse
    {
        $ttlMinutes = (int) $this->guard()->getTTL();

        /** @var User|null $user */
        $user = $this->guard()->user();

        return response()->json([
            'success'      => true,
            'access_token' => $token,
            'token_type'   => 'bearer',
            'expires_in'   => $ttlMinutes * 60,
            'expires_at'   => now()->addMinutes($ttlMinutes)->toIso8601String(),
            'user'         => $user ? $user->load('role') : null,
        ]);
    }
}
