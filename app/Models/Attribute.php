<?php

namespace App\Models;

use App\Filters\AttributeFilter;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

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

    public function attributeValues(): HasMany
    {
        return $this->hasMany(AttributeValue::class);
    }

    public function scopeFilter(Builder $query, AttributeFilter $filter): Builder
    {
        return $filter->apply($query);
    }
}
