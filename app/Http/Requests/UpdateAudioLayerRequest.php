<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAudioLayerRequest extends FormRequest
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
            'name' => ['sometimes', 'string', 'max:100'],
            'offset' => ['sometimes', 'numeric', 'min:0', 'max:3600'],
            'volume' => ['sometimes', 'numeric', 'min:0', 'max:1.5'],
        ];
    }
}
