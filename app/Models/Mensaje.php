<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Mensaje extends Model
{
    use HasFactory;

    protected $table = 'mensajes';

    protected $fillable = [
        'instancia_id',
        'whatsapp_id',
        'from',
        'to',
        'body',
        'type',
        'media_url',
        'direction',
        'status',
        'error_message',
    ];

    public function instancia()
    {
        return $this->belongsTo(Instancia::class);
    }
}
