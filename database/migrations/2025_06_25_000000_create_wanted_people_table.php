<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wanted_people', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('birth_year')->nullable();
            $table->string('address')->nullable();
            $table->string('parents')->nullable();
            $table->string('crime')->nullable();
            $table->string('decision')->nullable();
            $table->string('agency')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('wanted_people');
    }
}; 