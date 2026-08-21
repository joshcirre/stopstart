<?php

namespace App\Http\Requests;

use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

class StoreFrameRequest extends FormRequest
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
        $project = $this->route('project');
        assert($project instanceof Project);

        return [
            'image' => [
                'required',
                File::types(['jpg', 'jpeg'])->max(10 * 1024),
                Rule::dimensions()
                    ->width($project->orientation->width())
                    ->height($project->orientation->height()),
            ],
        ];
    }
}
