<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::table('comments', function (Blueprint $table) {
            $table->unsignedBigInteger('experience_id')->nullable()->after('alert_id');
            $table->foreign('experience_id')->references('id')->on('experiences')->onDelete('cascade');
        });
    }
    public function down()
    {
        Schema::table('comments', function (Blueprint $table) {
            $table->dropForeign(['experience_id']);
            $table->dropColumn('experience_id');
        });
    }
}; 