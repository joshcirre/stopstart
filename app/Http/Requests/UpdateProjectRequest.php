<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\AuthorizesProjectOwner;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProjectRequest extends FormRequest
{
    use AuthorizesProjectOwner;

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'fps' => ['required', 'integer', 'between:1,60'],
        ];
    }
}
