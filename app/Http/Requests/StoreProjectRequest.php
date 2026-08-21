<?php

namespace App\Http\Requests;

use App\Enums\Orientation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'orientation' => ['required', Rule::enum(Orientation::class)],
            'fps' => ['nullable', 'integer', 'between:1,60'],
        ];
    }
}
