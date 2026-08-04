<?php

namespace App\Models\Ai;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiModel extends Model
{
    protected $table = 'ai_models';

    protected $fillable = [
        'name',
        'provider',
        'cost_per_1m_input',
        'cost_per_1m_output',
        'is_active',
    ];

    protected $casts = [
        'cost_per_1m_input'  => 'decimal:6',
        'cost_per_1m_output' => 'decimal:6',
        'is_active'          => 'boolean',
    ];

    public static array $providers = [
        'openai'   => 'OpenAI',
        'deepseek' => 'DeepSeek',
        'gemini'   => 'Gemini',
        'claude'   => 'Claude',
    ];

    public function getProviderLabelAttribute(): string
    {
        return self::$providers[$this->provider] ?? $this->provider;
    }

    public function tariffPlans(): HasMany
    {
        return $this->hasMany(AiTariffPlan::class, 'ai_model_id');
    }
}
