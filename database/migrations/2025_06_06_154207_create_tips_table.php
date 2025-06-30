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
        Schema::create('tips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('category_id')->constrained('tip_categories')->onDelete('cascade');
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('content');
            $table->boolean('is_featured')->default(false);
            $table->integer('views_count')->default(0);
            $table->integer('likes_count')->default(0);
            $table->json('tags')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // Create a table for users who have saved tips
        Schema::create('saved_tips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('tip_id')->constrained()->onDelete('cascade');
            $table->timestamps();

            $table->unique(['user_id', 'tip_id']);
        });

        // Create a table for tip likes
        Schema::create('tip_likes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('tip_id')->constrained()->onDelete('cascade');
            $table->timestamps();

            $table->unique(['user_id', 'tip_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tip_likes');
        Schema::dropIfExists('saved_tips');
        Schema::dropIfExists('tips');
    }
};
