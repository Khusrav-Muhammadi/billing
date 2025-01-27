<?php

namespace App\Traits;

use App\Enums\ModelHistoryStatuses;
use App\Models\ChangeHistory;
use App\Models\ModelHistory;
use Illuminate\Database\Eloquent\Model;

trait TrackHistoryTrait
{
    public function create(Model $model, ?int $user_id): void
    {
        $history = ModelHistory::create([
            'status' => ModelHistoryStatuses::CREATED,
            'user_id' => $user_id,
            'model_id' => $model->id,
            'model_type' => get_class($model),
        ]);

        ChangeHistory::create([
            'model_history_id' => $history->id,
            'body' => json_encode([]),
        ]);
    }

    public function update(Model $model, $user_id): void
    {
        $documentHistory = ModelHistory::create([
            'status' => ModelHistoryStatuses::UPDATED,
            'user_id' => $user_id,
            'model_id' => $model->id,
            'model_type' => get_class($model),
        ]);

       $this->track($model, $documentHistory);
    }

    public function delete(Model $model, int $user_id = null): void
    {
        ModelHistory::create([
            'status' => ModelHistoryStatuses::DELETED,
            'user_id' => $user_id,
            'model_id' => $model->id,
            'model_type' => get_class($model),
        ]);
    }


    public function restore(Model $model, int $user_id = null): void
    {
        ModelHistory::create([
            'status' => ModelHistoryStatuses::RESTORED,
            'user_id' => $user_id,
            'model_id' => $model->id,
            'model_type' => get_class($model),
        ]);
    }

    public function forceDelete(Model $model, int $user_id = null): void
    {
        ModelHistory::create([
            'status' => ModelHistoryStatuses::FORCE_DELETED,
            'user_id' => $user_id,
            'model_id' => $model->id,
            'model_type' => get_class($model),
        ]);
    }

    private function getHistoryDetails(Model $document, $value, $field): array
    {
        $modelMap = config('models.model_map');

        $fieldKey = isset($modelMap[$field]) ? $field . '_id' : $field;

        $previousValue = $document->getOriginal($fieldKey);

        if (isset($modelMap[$field])) {
            $model = $modelMap[$field];
            $previousModel = optional($model::find($previousValue))->name;
            $newModel = optional($model::find($value))->name;

            return [
                'previous_value' => $previousModel,
                'new_value' => $newModel,
            ];
        }

        return [
            'previous_value' => $previousValue,
            'new_value' => $value,
        ];
    }

    private function track(Model $document, ModelHistory $history)
    {
        $value = collect($this->getUpdated($document))
            ->filter(function ($value, $field) {
                return $field != 'file' && $field != 'position' && $field != 'dialog_state' && $field != 'is_finished' && $field != 'status_update_date';
            })
            ->mapWithKeys(function ($value, $field) use ($document) {
                return [$field => $this->getHistoryDetails($document, $value, $field)];
            });

        if ($value->isNotEmpty()) {
            ChangeHistory::create([
                'model_history_id' => $history->id,
                'body' => json_encode($value),
            ]);
        } else {
            $history->delete();
        }

    }

    private function getUpdated($model)
    {
        return collect($model->getDirty())->filter(function ($value, $key) {
            return !in_array($key, ['created_at', 'updated_at']);
        })->mapWithKeys(function ($value, $key) {
            return [str_replace('_id', '', $key) => $value];
        });
    }

}
