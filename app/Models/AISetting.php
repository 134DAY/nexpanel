<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class AISetting extends Model
{
    // IMPORTANT: Laravel converts AISetting to a_i_settings by default
    // We must explicitly set the correct table name
    protected $table = 'ai_settings';

    protected $fillable = [
        'user_id',
        'provider',
        'api_key',
        'model',
    ];

    protected $hidden = ['api_key'];

    /**
     * Decrypt API key when accessing
     */
    public function getApiKeyAttribute($value)
    {
        if (!$value) return null;
        try {
            return Crypt::decryptString($value);
        } catch (\Exception $e) {
            return $value;
        }
    }

    /**
     * Encrypt API key when storing
     */
    public function setApiKeyAttribute($value)
    {
        if ($value && $value !== str_repeat('•', 32)) {
            $this->attributes['api_key'] = Crypt::encryptString($value);
        }
    }

    /**
     * Get the model name for display
     */
    public function getModelName(): ?string
    {
        return $this->attributes['model'] ?? null;
    }
}
