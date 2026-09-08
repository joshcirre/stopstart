<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\AuthorizesProjectOwner;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MoveFrameRequest extends FormRequest
{
    use AuthorizesProjectOwner;

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'direction' => ['required', Rule::in(['earlier', 'later'])],
        ];
    }
}
