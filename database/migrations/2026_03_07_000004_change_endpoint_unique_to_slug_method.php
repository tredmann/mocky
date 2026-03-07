<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('endpoints', function (Blueprint $table) {
            $table->dropUnique(['collection_id', 'slug']);
            $table->unique(['collection_id', 'slug', 'method']);
        });
    }

    public function down(): void
    {
        Schema::table('endpoints', function (Blueprint $table) {
            $table->dropUnique(['collection_id', 'slug', 'method']);
            $table->unique(['collection_id', 'slug']);
        });
    }
};
