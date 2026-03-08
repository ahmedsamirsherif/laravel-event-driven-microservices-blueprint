<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_log', function (Blueprint $table) {
            $table->id();
            $table->string('event_id', 36)->unique();
            $table->string('event_type', 50);
            $table->string('country', 10);
            $table->unsignedBigInteger('employee_id')->nullable();
            $table->string('status', 20)->default('received'); // received, processed, failed
            $table->json('payload');
            $table->text('error_message')->nullable();
            $table->timestamp('received_at');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['event_type', 'country']);
            $table->index(['status', 'received_at']);
            $table->index('employee_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_log');
    }
};
