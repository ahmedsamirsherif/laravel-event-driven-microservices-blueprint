<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->text('doc_work_permit')->nullable()->after('tax_id');
            $table->text('doc_tax_card')->nullable()->after('doc_work_permit');
            $table->text('doc_health_insurance')->nullable()->after('doc_tax_card');
            $table->text('doc_social_security')->nullable()->after('doc_health_insurance');
            $table->text('doc_employment_contract')->nullable()->after('doc_social_security');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn([
                'doc_work_permit',
                'doc_tax_card',
                'doc_health_insurance',
                'doc_social_security',
                'doc_employment_contract',
            ]);
        });
    }
};
