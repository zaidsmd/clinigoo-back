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
        Schema::table('professional_profiles', function (Blueprint $table) {
            if (! Schema::hasColumn('professional_profiles', 'birthday')) {
                $table->date('birthday')->nullable()->after('sexe');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('professional_profiles', function (Blueprint $table) {
            if (Schema::hasColumn('professional_profiles', 'birthday')) {
                $table->dropColumn('birthday');
            }
        });
    }
};

