<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conditional_responses', function (Blueprint $table) {
            $table->string('condition_source')->change();
        });
    }

    public function down(): void
    {
        Schema::table('conditional_responses', function (Blueprint $table) {
            $table->enum('condition_source', ['body', 'query', 'header'])->change();
        });
    }
};
