<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Project extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'status',
    ];

    protected $with = ['attributeValues.attribute'];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    public function timesheets(): HasMany
    {
        return $this->hasMany(Timesheet::class);
    }

    public function attributeValues(): MorphMany
    {
        return $this->morphMany(AttributeValue::class, 'entity');
    }

    public function getAttribute($key)
    {
        $attribute = parent::getAttribute($key);
        
        if ($attribute === null) {
            $attributeValue = $this->attributeValues
                ->first(function ($value) use ($key) {
                    return $value->attribute->name === $key;
                });
            
            return $attributeValue ? $attributeValue->value : null;
        }
        
        return $attribute;
    }
}
