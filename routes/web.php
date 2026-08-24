<?php

use App\Http\Controllers\AudioLayerController;
use App\Http\Controllers\CaptureController;
use App\Http\Controllers\DubController;
use App\Http\Controllers\FrameController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\RemoteController;
use App\Http\Controllers\VideoController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/projects')->name('home');

Route::get('/projects', [ProjectController::class, 'index'])->name('projects.index');
Route::post('/projects', [ProjectController::class, 'store'])->name('projects.store');
Route::get('/projects/{project}', [ProjectController::class, 'show'])->name('projects.show');
Route::patch('/projects/{project}', [ProjectController::class, 'update'])->name('projects.update');
Route::delete('/projects/{project}', [ProjectController::class, 'destroy'])->name('projects.destroy');
Route::get('/projects/{project}/capture', [CaptureController::class, 'show'])->name('projects.capture');

Route::post('/projects/{project}/frames', [FrameController::class, 'store'])->name('projects.frames.store');
Route::delete('/projects/{project}/frames/{frame}', [FrameController::class, 'destroy'])->name('projects.frames.destroy');
Route::get('/projects/{project}/frames/{frame}/image', [FrameController::class, 'image'])->name('projects.frames.image');

Route::post('/projects/{project}/videos', [VideoController::class, 'store'])->name('projects.videos.store');
Route::post('/projects/{project}/videos/upload', [VideoController::class, 'upload'])->name('projects.videos.upload');
Route::get('/videos/{video}/download', [VideoController::class, 'download'])->name('videos.download');

Route::get('/remote/{project:remote_token}', [RemoteController::class, 'show'])->name('remote.show');
Route::post('/remote/{project:remote_token}/command', [RemoteController::class, 'command'])
    ->middleware('throttle:remote-commands')
    ->name('remote.command');

Route::get('/remote/{project:remote_token}/dub', [DubController::class, 'show'])->name('remote.dub');
Route::get('/remote/{project:remote_token}/videos/{video}/stream', [DubController::class, 'streamVideo'])->name('remote.videos.stream');
Route::post('/remote/{project:remote_token}/export', [DubController::class, 'export'])
    ->middleware('throttle:dub-exports')
    ->name('remote.export');

Route::post('/remote/{project:remote_token}/layers', [AudioLayerController::class, 'store'])
    ->middleware('throttle:dub-layers')
    ->name('remote.layers.store');
Route::patch('/remote/{project:remote_token}/layers/{audioLayer}', [AudioLayerController::class, 'update'])->name('remote.layers.update');
Route::delete('/remote/{project:remote_token}/layers/{audioLayer}', [AudioLayerController::class, 'destroy'])->name('remote.layers.destroy');
Route::get('/remote/{project:remote_token}/layers/{audioLayer}/audio', [AudioLayerController::class, 'audio'])->name('remote.layers.audio');
