<?php

use App\Models\Endpoint;
use App\Models\EndpointCollection;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('endpoints', function (Blueprint $table) {
            $table->foreignUuid('collection_id')
                ->nullable()
                ->after('user_id')
                ->constrained('endpoint_collections')
                ->cascadeOnDelete();
        });

        // Create a default "General" collection for each user that has endpoints
        $userIds = Endpoint::query()->distinct()->pluck('user_id');
        foreach ($userIds as $userId) {
            $collection = EndpointCollection::create([
                'user_id' => $userId,
                'name' => 'General',
                'slug' => 'general-'.substr($userId, 0, 8),
            ]);

            Endpoint::where('user_id', $userId)
                ->whereNull('collection_id')
                ->update(['collection_id' => $collection->id]);
        }

        // Now make collection_id non-nullable and update unique constraint
        Schema::table('endpoints', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->unique(['collection_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::table('endpoints', function (Blueprint $table) {
            $table->dropUnique(['collection_id', 'slug']);
            $table->unique('slug');
            $table->dropConstrainedForeignId('collection_id');
        });
    }
};
