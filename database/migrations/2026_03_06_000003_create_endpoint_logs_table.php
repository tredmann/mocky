<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('endpoint_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('endpoint_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('matched_conditional_response_id')->nullable()->constrained('conditional_responses')->nullOnDelete();
            $table->string('request_method', 10);
            $table->string('request_ip', 45)->nullable();
            $table->text('request_user_agent')->nullable();
            $table->json('request_headers');
            $table->json('request_query');
            $table->longText('request_body')->nullable();
            $table->unsignedSmallInteger('response_status_code');
            $table->longText('response_body')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('endpoint_logs');
    }
};
