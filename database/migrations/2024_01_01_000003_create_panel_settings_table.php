<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('panel_settings')) {
            Schema::create('panel_settings', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->onDelete('cascade');
                $table->string('panel_name')->default('NexPanel');
                $table->string('timezone')->default('Asia/Bangkok');
                $table->integer('session_timeout')->default(120); // minutes
                $table->string('language')->default('en');
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('panel_settings');
    }
};
