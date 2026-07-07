<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChatHistory extends Model
{
    protected $fillable = ['user_id', 'session_id', 'role', 'content'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
