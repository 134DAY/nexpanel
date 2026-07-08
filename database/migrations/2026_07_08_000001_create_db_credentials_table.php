<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('db_credentials')) {
            Schema::create('db_credentials', function (Blueprint $table) {
                $table->id();
                $table->string('db_name')->unique();
                $table->string('username');
                $table->text('password'); // encrypted at rest
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('db_credentials');
    }
};
