<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\AuthorizesProjectOwner;
use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

class StoreFrameRequest extends FormRequest
{
    use AuthorizesProjectOwner;

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
            // Insert directly after an existing frame instead of appending.
            // Anchored by id, not sequence: sequences shift as frames are
            // inserted, but the anchor's id stays valid.
            'after_frame_id' => [
                'nullable',
                'integer',
                Rule::exists('frames', 'id')->where('project_id', $project->id),
            ],
        ];
    }
}
