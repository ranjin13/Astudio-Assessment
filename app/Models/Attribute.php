<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Attribute extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'type',
        'options', // For select type attributes
    ];

    protected $casts = [
        'options' => 'array',
    ];

    public const TYPES = [
        'text',
        'date',
        'number',
        'select',
    ];

    public function values(): HasMany
    {
        return $this->hasMany(AttributeValue::class);
    }
}
