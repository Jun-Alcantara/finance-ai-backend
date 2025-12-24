<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Gemini\Enums\Role;

class Message extends Model
{
    use HasFactory;

    protected $fillable = ['session_id', 'content', 'role', 'metadata'];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function getRoleAttribute($value)
    {
        return $value == "user"
            ? Role::USER
            : Role::MODEL;
    }
}
