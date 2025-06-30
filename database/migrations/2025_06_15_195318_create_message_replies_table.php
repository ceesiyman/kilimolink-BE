<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('message_replies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('community_message_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->text('content');
            $table->foreignId('parent_reply_id')->nullable()->constrained('message_replies')->onDelete('cascade');
            $table->integer('likes_count')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        // Create a table for reply likes
        Schema::create('reply_likes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_reply_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->timestamps();

            $table->unique(['message_reply_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reply_likes');
        Schema::dropIfExists('message_replies');
    }
};
