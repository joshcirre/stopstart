<?php

return [

    /*
    |--------------------------------------------------------------------------
    | FFmpeg Binary
    |--------------------------------------------------------------------------
    |
    | The ffmpeg executable used to assemble captured frames into a video.
    | A bare command name resolves through PATH; set an absolute path when
    | the binary lives somewhere the queue worker's PATH does not cover.
    |
    */

    'ffmpeg_path' => env('FFMPEG_PATH', 'ffmpeg'),

];
