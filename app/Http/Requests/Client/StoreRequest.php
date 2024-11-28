<?php

namespace App\Http\Requests\Client;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRequest extends FormRequest
{

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required'],
            'phone' => ['required'],
            'project_uuid' => ['required'],
            'sub_domain' => ['required'],
            'business_type_id' => ['required', Rule::exists('business_types','id')],
        ];
    }
}
