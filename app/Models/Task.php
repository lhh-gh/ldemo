<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Task extends Model
{
    /**
     * @var array<string, bool>
     */
    protected $attributes = [
        'is_completed' => false,
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'title',
        'is_completed',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_completed' => 'boolean',
        ];
    }
}
