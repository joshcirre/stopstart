<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('frames', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sequence');
            $table->string('path');
            $table->timestamps();

            $table->unique(['project_id', 'sequence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('frames');
    }
};
