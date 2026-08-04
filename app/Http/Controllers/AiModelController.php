<?php

namespace App\Http\Controllers;

use App\Models\Ai\AiModel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AiModelController extends Controller
{
    public function index()
    {
        $models    = AiModel::query()->orderBy('provider')->orderBy('name')->get();
        $providers = AiModel::$providers;

        return view('admin.ai-models.index', compact('models', 'providers'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'               => 'required|string|max:100',
            'provider'           => ['required', Rule::in(array_keys(AiModel::$providers))],
            'cost_per_1m_input'  => 'required|numeric|min:0',
            'cost_per_1m_output' => 'required|numeric|min:0',
            'is_active'          => 'nullable|boolean',
        ]);

        AiModel::query()->create([
            'name'               => $data['name'],
            'provider'           => $data['provider'],
            'cost_per_1m_input'  => $data['cost_per_1m_input'],
            'cost_per_1m_output' => $data['cost_per_1m_output'],
            'is_active'          => (bool) ($data['is_active'] ?? true),
        ]);

        return redirect()->route('ai-model.index')->with('success', 'Модель добавлена.');
    }

    public function update(Request $request, AiModel $aiModel): RedirectResponse
    {
        $data = $request->validate([
            'name'               => 'required|string|max:100',
            'provider'           => ['required', Rule::in(array_keys(AiModel::$providers))],
            'cost_per_1m_input'  => 'required|numeric|min:0',
            'cost_per_1m_output' => 'required|numeric|min:0',
            'is_active'          => 'nullable|boolean',
        ]);

        $aiModel->update([
            'name'               => $data['name'],
            'provider'           => $data['provider'],
            'cost_per_1m_input'  => $data['cost_per_1m_input'],
            'cost_per_1m_output' => $data['cost_per_1m_output'],
            'is_active'          => (bool) ($data['is_active'] ?? false),
        ]);

        return redirect()->route('ai-model.index')->with('success', 'Модель обновлена.');
    }

    public function destroy(AiModel $aiModel): RedirectResponse
    {
        $aiModel->delete();

        return redirect()->route('ai-model.index')->with('success', 'Модель удалена.');
    }
}
