<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChatbotNode extends Model
{
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'instancia_id',
        'mensaje',
        'media_url',
        'target_etapa',
        'auto_tags',
    ];

    // Asegura que las operaciones de guardado y borrado incluyan instancia_id en la clave
    protected function setKeysForSaveQuery($query)
    {
        $query->where('id', $this->getAttribute('id'));

        if ($this->getAttribute('instancia_id') !== null) {
            $query->where('instancia_id', $this->getAttribute('instancia_id'));
        }

        return $query;
    }

    // Scope para traer los nodos y opciones de una instancia específica
    public function scopeForInstancia(Builder $query, int $instanciaId): Builder
    {
        return $query->where('instancia_id', $instanciaId)
            ->with(['opciones' => function ($q) use ($instanciaId) {
                $q->where('options.instancia_id', $instanciaId);
            }]);
    }

    public function instancia(): BelongsTo
    {
        return $this->belongsTo(Instancia::class);
    }

    public function opciones(): HasMany
    {
        $relation = $this->hasMany(Option::class, 'chatbot_node_id', 'id')
            ->orderByRaw("CASE WHEN key ~ '^[0-9]+$' THEN CAST(key AS INTEGER) ELSE 999999 END ASC, key ASC");

        if ($this->getAttribute('instancia_id') !== null) {
            $relation->where('options.instancia_id', $this->getAttribute('instancia_id'));
        }

        return $relation;
    }
}
