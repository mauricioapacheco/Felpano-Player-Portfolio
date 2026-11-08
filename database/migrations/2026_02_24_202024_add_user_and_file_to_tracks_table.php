<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('tracks', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->string('file_name')->nullable();
            $table->unique(['user_id', 'file_name'], 'tracks_user_file_unique');
        });
    }

    public function down(): void {
        Schema::table('tracks', function (Blueprint $table) {
            $table->dropUnique('tracks_user_file_unique');
            $table->dropConstrainedForeignId('user_id');
            $table->dropColumn('file_name');
        });
    }
};
