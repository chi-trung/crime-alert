<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLikesTable extends Migration
{
    public function up(): void
    {
        Schema::create('likes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->morphs('likeable');
            $table->timestamps();
            $table->unique(['user_id', 'likeable_id', 'likeable_type']);
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('likes');
    }
} 