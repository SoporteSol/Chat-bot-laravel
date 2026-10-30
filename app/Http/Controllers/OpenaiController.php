<?php

namespace App\Http\Controllers;

use App\Services\AiService;
use Illuminate\Http\Request;

class OpenaiController extends Controller
{
    public function __construct(
        protected AiService $aiService
    ) {}

    public function funRespuestaIA(Request $request)
    {
        $user = $request->user('api') ?? $request->user('web') ?? auth()->user();
        if (!$user || !$user->isAdmin()) {
            return response()->json([
                'status' => 403,
                'error'  => 'No autorizado para consultar el motor de IA',
            ], 403);
        }

        $mensaje = trim((string) $request->input('mensaje', ''));

        if ($mensaje === '') {
            return response()->json([
                'status' => 400,
                'error'  => 'El campo mensaje es obligatorio',
            ], 400);
        }

        $respuesta = $this->aiService->obtenerRespuesta($mensaje);

        return response()->json([
            'status'   => 200,
            'response' => $respuesta,
        ]);
    }
}
