<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DbCredential extends Model
{
    protected $fillable = ['db_name', 'username', 'password'];

    protected function casts(): array
    {
        return ['password' => 'encrypted'];
    }
}
