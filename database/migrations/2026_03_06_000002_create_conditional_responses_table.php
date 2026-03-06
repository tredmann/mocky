<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conditional_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('endpoint_id')->constrained()->cascadeOnDelete();
            $table->enum('condition_source', ['body', 'query', 'header']);
            $table->string('condition_field');
            $table->enum('condition_operator', ['equals', 'not_equals', 'contains']);
            $table->string('condition_value');
            $table->unsignedSmallInteger('status_code')->default(200);
            $table->string('content_type')->default('application/json');
            $table->longText('response_body')->nullable();
            $table->unsignedSmallInteger('priority')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conditional_responses');
    }
};
