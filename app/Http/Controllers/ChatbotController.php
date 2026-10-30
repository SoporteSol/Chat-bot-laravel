<?php

namespace App\Http\Controllers;

use App\Models\ChatbotNode;
use App\Models\Option;
use Illuminate\Http\Request;

class ChatbotController extends Controller
{
    public function index()
    {
        return redirect()->route('instancias.index');
    }

    public function show(Request $request, $id)
    {
        $instanciaId = $request->input('instancia_id') ? (int) $request->input('instancia_id') : null;
        $query = ChatbotNode::where('id', $id);
        if ($instanciaId) {
            $query->forInstancia($instanciaId);
        }
        $node = $query->firstOrFail();

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'node'    => $node->load('opciones'),
            ]);
        }

        return redirect()->route('instancias.index');
    }

    public function storeNode(Request $request)
    {
        $request->validate([
            'id'           => 'required|string|max:50',
            'mensaje'      => 'required|string',
            'media_url'    => 'nullable|string|max:1000',
            'instancia_id' => 'required|integer|exists:instancias,id',
            'target_etapa' => 'nullable|string|max:40',
            'auto_tags'    => 'nullable|string|max:255',
        ]);

        $instanciaId = (int) $request->input('instancia_id');
        $cleanId = strtolower((string) preg_replace('/[^a-zA-Z0-9_-]/', '', (string) $request->id));

        if (ChatbotNode::where('instancia_id', $instanciaId)->where('id', $cleanId)->exists()) {
            return response()->json([
                'success' => false,
                'message' => "El nodo #{$cleanId} ya existe en esta instancia.",
            ], 422);
        }

        $node = ChatbotNode::create([
            'id'           => $cleanId,
            'instancia_id' => $instanciaId,
            'mensaje'      => $request->mensaje,
            'media_url'    => $request->input('media_url') ?: null,
            'target_etapa' => $request->input('target_etapa') ?: null,
            'auto_tags'    => $request->input('auto_tags') ? trim((string) $request->input('auto_tags')) : null,
        ]);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'node'    => $node->load('opciones'),
            ]);
        }

        return redirect()->back()->with('success', "Nodo #{$cleanId} creado correctamente.")->with('tab', 'nodos');
    }

    public function updateNode(Request $request, $id)
    {
        $request->validate([
            'mensaje'      => 'required|string',
            'media_url'    => 'nullable|string|max:1000',
            'instancia_id' => 'nullable|integer|exists:instancias,id',
            'target_etapa' => 'nullable|string|max:40',
            'auto_tags'    => 'nullable|string|max:255',
        ]);

        $instanciaId = $request->input('instancia_id') ? (int) $request->input('instancia_id') : null;
        $query = ChatbotNode::where('id', $id);
        if ($instanciaId) {
            $query->where('instancia_id', $instanciaId);
        }
        $node = $query->firstOrFail();

        $updateData = [
            'mensaje' => $request->mensaje,
        ];
        if ($request->has('media_url')) {
            $updateData['media_url'] = $request->input('media_url') ?: null;
        }
        if ($request->has('target_etapa')) {
            $updateData['target_etapa'] = $request->input('target_etapa') ?: null;
        }
        if ($request->has('auto_tags')) {
            $updateData['auto_tags'] = $request->input('auto_tags') ? trim((string) $request->input('auto_tags')) : null;
        }

        $node->update($updateData);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'node'    => $node->load('opciones'),
            ]);
        }

        return redirect()->back()->with('success', "Nodo #{$id} actualizado correctamente.")->with('tab', 'nodos');
    }

    // Subida de imágenes para los nodos del chatbot
    public function uploadNodeMedia(Request $request)
    {
        $request->validate([
            'archivo'      => 'required|file|mimes:jpeg,png,jpg,webp,gif|max:16384',
            'instancia_id' => 'required|integer|exists:instancias,id',
        ]);

        $file = $request->file('archivo');
        $ext = $file->getClientOriginalExtension() ?: 'jpg';
        $filename = 'bot_node_' . time() . '_' . \Illuminate\Support\Str::random(8) . '.' . $ext;

        $storageDir = storage_path('app/public/chatbot_media');
        if (!file_exists($storageDir)) {
            mkdir($storageDir, 0755, true);
        }
        $file->move($storageDir, $filename);
        $url = asset('storage/chatbot_media/' . $filename);

        return response()->json([
            'success'  => true,
            'url'      => $url,
            'filename' => $filename,
        ]);
    }

        return redirect()->back()->with('success', "Nodo #{$id} actualizado correctamente.")->with('tab', 'nodos');
    }

    public function destroyNode(Request $request, $id)
    {
        $instanciaId = $request->input('instancia_id') ? (int) $request->input('instancia_id') : null;
        $query = ChatbotNode::where('id', $id);
        if ($instanciaId) {
            $query->where('instancia_id', $instanciaId);
        }
        $node = $query->firstOrFail();

        // Si alguna opción saltaba a este nodo, la dejamos en null para no romper el flujo
        Option::where('instancia_id', $node->instancia_id)
            ->where('next_node_id', $node->id)
            ->update(['next_node_id' => null]);

        Option::where('instancia_id', $node->instancia_id)
            ->where('chatbot_node_id', $node->id)
            ->delete();

        $node->delete();

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'id'      => $id,
            ]);
        }

        return redirect()->back()->with('success', "Nodo #{$id} eliminado correctamente.")->with('tab', 'nodos');
    }

    public function storeOption(Request $request)
    {
        $request->validate([
            'node_id'      => 'required|string|max:50',
            'instancia_id' => 'nullable|integer|exists:instancias,id',
            'key'          => 'required|string|max:20',
            'text'         => 'required|string|max:150',
            'next_node_id' => 'nullable|string|max:50',
        ]);

        $instanciaId = $request->input('instancia_id') ? (int) $request->input('instancia_id') : null;
        $nodeQuery = ChatbotNode::where('id', $request->node_id);
        if ($instanciaId) {
            $nodeQuery->where('instancia_id', $instanciaId);
        }
        $parentNode = $nodeQuery->firstOrFail();

        $respuesta = null;
        if ($request->respuesta_tipo === 'text' && !empty($request->respuesta_texto)) {
            $respuesta = [
                'type' => 'text',
                'body' => $request->respuesta_texto,
            ];
        }

        $opcion = Option::create([
            'instancia_id'    => $parentNode->instancia_id,
            'chatbot_node_id' => $parentNode->id,
            'key'             => trim((string) $request->key),
            'text'            => trim((string) $request->text),
            'next_node_id'    => $request->next_node_id ?: null,
            'respuesta'       => $respuesta,
        ]);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'option'  => $opcion,
            ]);
        }

        return redirect()->back()->with('success', "Opción agregada al nodo #{$parentNode->id}.")->with('tab', 'nodos');
    }

    public function updateOption(Request $request, $id)
    {
        $instanciaId = $request->input('instancia_id') ? (int) $request->input('instancia_id') : null;
        $query = Option::where('id', $id);
        if ($instanciaId) {
            $query->where('instancia_id', $instanciaId);
        }
        $option = $query->firstOrFail();

        $request->validate([
            'key'          => 'sometimes|required|string|max:20',
            'text'         => 'sometimes|required|string|max:150',
            'next_node_id' => 'nullable|string|max:50',
        ]);

        $data = [];
        if ($request->has('key')) {
            $data['key'] = trim((string) $request->key);
        }
        if ($request->has('text')) {
            $data['text'] = trim((string) $request->text);
        }
        if ($request->has('next_node_id')) {
            $data['next_node_id'] = $request->next_node_id ?: null;
        }

        $option->update($data);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'option'  => $option,
            ]);
        }

        return redirect()->back()->with('success', 'Opción actualizada.')->with('tab', 'nodos');
    }

    public function deleteOption(Request $request, $id)
    {
        $instanciaId = $request->input('instancia_id') ? (int) $request->input('instancia_id') : null;
        $query = Option::where('id', $id);
        if ($instanciaId) {
            $query->where('instancia_id', $instanciaId);
        }
        $option = $query->firstOrFail();
        $nodeId = $option->chatbot_node_id;
        $option->delete();

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'id'      => $id,
                'node_id' => $nodeId,
            ]);
        }

        return redirect()->back()->with('success', 'Opción eliminada con éxito.')->with('tab', 'nodos');
    }
}