<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RemoteCommandRequest extends FormRequest
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
            'command' => ['required', Rule::in(['capture', 'interval-start', 'interval-stop'])],
            'intervalSeconds' => ['required_if:command,interval-start', 'nullable', 'integer', 'between:1,60'],
        ];
    }
}
