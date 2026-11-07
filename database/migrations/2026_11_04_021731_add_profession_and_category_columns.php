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
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'profession')) {
                $table->string('profession')->nullable()->after('type');
            }
        });

        Schema::table('services', function (Blueprint $table) {
            if (! Schema::hasColumn('services', 'category')) {
                $table->string('category')->default('soins')->after('name');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'profession')) {
                $table->dropColumn('profession');
            }
        });

        Schema::table('services', function (Blueprint $table) {
            if (Schema::hasColumn('services', 'category')) {
                $table->dropColumn('category');
            }
        });
    }
};
