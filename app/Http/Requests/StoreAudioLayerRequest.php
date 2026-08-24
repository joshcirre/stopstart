<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

class StoreAudioLayerRequest extends FormRequest
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
            'audio' => [
                'required',
                // 'weba'/'oga' are the extensions Symfony maps audio/webm
                // and audio/ogg to — MediaRecorder blobs arrive as those.
                File::types(['webm', 'weba', 'ogg', 'oga', 'mp4', 'm4a'])->max(25 * 1024),
            ],
            'name' => ['required', 'string', 'max:100'],
            'offset' => ['required', 'numeric', 'min:0', 'max:3600'],
            'duration' => ['required', 'numeric', 'gt:0', 'max:3600'],
        ];
    }
}
