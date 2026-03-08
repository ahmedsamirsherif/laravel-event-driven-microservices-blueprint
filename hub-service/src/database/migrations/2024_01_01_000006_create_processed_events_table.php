<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('processed_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_id', 36)->unique(); // unique() creates the index automatically
            $table->string('event_type', 50);
            $table->timestamp('processed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('processed_events');
    }
};
