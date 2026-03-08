<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('last_name');
            $table->decimal('salary', 12, 2)->default(0);
            $table->string('country', 10);
            $table->string('ssn', 11)->nullable();
            $table->text('address')->nullable();
            $table->text('goal')->nullable();
            $table->string('tax_id', 20)->nullable();
            $table->timestamps();
            $table->index('country');
            $table->index(['country', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
