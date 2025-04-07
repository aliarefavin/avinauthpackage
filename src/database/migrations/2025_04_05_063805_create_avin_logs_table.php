<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('avin_logs', function (Blueprint $table) {
            $table->id();
            $table->string('receiver')->index();
            $table->string('ip');
            $table->text('agent')->nullable();
            $table->string('code');
            $table->unsignedInteger('count')->default(0);
            $table->enum('status', ['active', 'inactive'])->default('inactive');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('avin_logs');
    }
};
