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
        Schema::create('archive_logs', function (Blueprint $table) {
            $table->id();
            $table->string('table_name');
            $table->string('status')->default('pending');
            $table->bigInteger('total_records')->default(0);
            $table->bigInteger('archived_count')->default(0);
            $table->decimal('duration', 10, 3)->default(0);
            $table->json('metadata')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['table_name', 'status']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('archive_logs');
    }
};