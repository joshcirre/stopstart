<?php

use App\Http\Controllers\CaptureController;
use App\Http\Controllers\FrameController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\RemoteController;
use App\Http\Controllers\VideoController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/projects')->name('home');

Route::get('/projects', [ProjectController::class, 'index'])->name('projects.index');
Route::post('/projects', [ProjectController::class, 'store'])->name('projects.store');
Route::get('/projects/{project}', [ProjectController::class, 'show'])->name('projects.show');
Route::delete('/projects/{project}', [ProjectController::class, 'destroy'])->name('projects.destroy');
Route::get('/projects/{project}/capture', [CaptureController::class, 'show'])->name('projects.capture');

Route::post('/projects/{project}/frames', [FrameController::class, 'store'])->name('projects.frames.store');
Route::delete('/projects/{project}/frames/{frame}', [FrameController::class, 'destroy'])->name('projects.frames.destroy');

Route::post('/projects/{project}/videos', [VideoController::class, 'store'])->name('projects.videos.store');
Route::post('/projects/{project}/videos/upload', [VideoController::class, 'upload'])->name('projects.videos.upload');
Route::get('/videos/{video}/download', [VideoController::class, 'download'])->name('videos.download');

Route::get('/remote/{project:remote_token}', [RemoteController::class, 'show'])->name('remote.show');
Route::post('/remote/{project:remote_token}/command', [RemoteController::class, 'command'])
    ->middleware('throttle:remote-commands')
    ->name('remote.command');
