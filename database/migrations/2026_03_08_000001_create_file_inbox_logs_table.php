<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('file_inbox_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('filename');
            $table->string('file_md5', 32)->index();
            $table->string('disk');
            $table->string('status');
            $table->text('error_message')->nullable();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('file_inbox_logs');
    }
};
