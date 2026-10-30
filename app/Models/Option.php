<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Option extends Model
{
    protected $fillable = [
        'instancia_id',
        'chatbot_node_id',
        'key',
        'text',
        'respuesta',
        'next_node_id',
    ];

    protected $casts = [
        'respuesta' => 'array',
    ];

    public function instancia(): BelongsTo
    {
        return $this->belongsTo(Instancia::class);
    }

    public function nodo(): BelongsTo
    {
        $relation = $this->belongsTo(ChatbotNode::class, 'chatbot_node_id', 'id');

        if ($this->getAttribute('instancia_id') !== null) {
            $relation->where('chatbot_nodes.instancia_id', $this->getAttribute('instancia_id'));
        }

        return $relation;
    }
}
