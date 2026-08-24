<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audio_layers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('path');
            $table->string('mime_type');
            $table->double('offset')->default(0);
            $table->double('volume')->default(1);
            $table->double('duration');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audio_layers');
    }
};
