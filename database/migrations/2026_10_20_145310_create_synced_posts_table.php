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
        Schema::create('synced_posts', function (Blueprint $table) {
            $table->id();
            $table->string('substack_guid')->unique();
            $table->string('substack_url');
            $table->string('title');
            $table->unsignedBigInteger('wp_post_id')->nullable();
            $table->string('content_hash', 64)->nullable();
            $table->string('status')->default('pending');
            $table->string('error')->nullable();
            $table->timestamps('synced_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('synced_posts');
    }
};
