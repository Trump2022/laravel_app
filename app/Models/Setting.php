<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @method static create(array $data)
 * @method static firstOrCreate(string[] $array, array $array1)
 */
class Setting extends Model
{
    protected $fillable = [
        'setting_key',
        'name',
        'type',
        'setting_value',
        'json_value',
    ];

    protected function casts(): array
    {
        return [
            'type' => 'integer',
            'json_value' => 'array',
        ];
    }

    /**
     * 获取设置项的实际值
     */
    public function getValueAttribute(): mixed
    {
        return match ($this->type) {
            1 => $this->setting_value,
            2 => (int) $this->setting_value,
            3 => (bool) $this->setting_value,
            4 => (float) $this->setting_value,
            5 => $this->json_value,
            default => null,
        };
    }
}
