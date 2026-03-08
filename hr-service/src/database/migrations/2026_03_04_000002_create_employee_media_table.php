<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('media_id')->constrained()->cascadeOnDelete();
            $table->string('document_type');
            $table->boolean('is_current')->default(true);
            $table->timestamp('created_at')->useCurrent();

            $table->index(['employee_id', 'document_type', 'is_current']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_media');
    }
};
