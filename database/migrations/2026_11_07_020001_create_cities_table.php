<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('cities')) {
            Schema::create('cities', function (Blueprint $table) {
                $table->id();
                $table->string('name')->unique();
                $table->string('slug')->nullable()->unique();
                $table->string('region')->nullable();
                $table->boolean('is_active')->default(true);
                $table->integer('display_order')->default(0);
                $table->timestamps();
            });

            // Seed default Moroccan cities
            $moroccanCities = [
                ['name' => 'Casablanca', 'slug' => 'casablanca', 'region' => 'Casablanca-Settat', 'display_order' => 1],
                ['name' => 'Rabat', 'slug' => 'rabat', 'region' => 'Rabat-Salé-Kénitra', 'display_order' => 2],
                ['name' => 'Marrakech', 'slug' => 'marrakech', 'region' => 'Marrakech-Safi', 'display_order' => 3],
                ['name' => 'Tanger', 'slug' => 'tanger', 'region' => 'Tanger-Tétouan-Al Hoceïma', 'display_order' => 4],
                ['name' => 'Fès', 'slug' => 'fes', 'region' => 'Fès-Meknès', 'display_order' => 5],
                ['name' => 'Agadir', 'slug' => 'agadir', 'region' => 'Souss-Massa', 'display_order' => 6],
                ['name' => 'Meknès', 'slug' => 'meknes', 'region' => 'Fès-Meknès', 'display_order' => 7],
                ['name' => 'Oujda', 'slug' => 'oujda', 'region' => 'Oriental', 'display_order' => 8],
                ['name' => 'Kénitra', 'slug' => 'kenitra', 'region' => 'Rabat-Salé-Kénitra', 'display_order' => 9],
                ['name' => 'Tétouan', 'slug' => 'tetouan', 'region' => 'Tanger-Tétouan-Al Hoceïma', 'display_order' => 10],
                ['name' => 'Safi', 'slug' => 'safi', 'region' => 'Marrakech-Safi', 'display_order' => 11],
                ['name' => 'Mohammédia', 'slug' => 'mohammedia', 'region' => 'Casablanca-Settat', 'display_order' => 12],
                ['name' => 'El Jadida', 'slug' => 'el-jadida', 'region' => 'Casablanca-Settat', 'display_order' => 13],
                ['name' => 'Salé', 'slug' => 'sale', 'region' => 'Rabat-Salé-Kénitra', 'display_order' => 14],
                ['name' => 'Nador', 'slug' => 'nador', 'region' => 'Oriental', 'display_order' => 15],
                ['name' => 'Beni Mellal', 'slug' => 'beni-mellal', 'region' => 'Béni Mellal-Khénifra', 'display_order' => 16],
                ['name' => 'Taza', 'slug' => 'taza', 'region' => 'Fès-Meknès', 'display_order' => 17],
                ['name' => 'Settat', 'slug' => 'settat', 'region' => 'Casablanca-Settat', 'display_order' => 18],
                ['name' => 'Larache', 'slug' => 'larache', 'region' => 'Tanger-Tétouan-Al Hoceïma', 'display_order' => 19],
                ['name' => 'Khémisset', 'slug' => 'khemisset', 'region' => 'Rabat-Salé-Kénitra', 'display_order' => 20],
                ['name' => 'Guelmim', 'slug' => 'guelmim', 'region' => 'Guelmim-Oued Noun', 'display_order' => 21],
                ['name' => 'Berrechid', 'slug' => 'berrechid', 'region' => 'Casablanca-Settat', 'display_order' => 22],
                ['name' => 'Errachidia', 'slug' => 'errachidia', 'region' => 'Drâa-Tafilalet', 'display_order' => 23],
                ['name' => 'Taroudant', 'slug' => 'taroudant', 'region' => 'Souss-Massa', 'display_order' => 24],
                ['name' => 'Essaouira', 'slug' => 'essaouira', 'region' => 'Marrakech-Safi', 'display_order' => 25],
                ['name' => 'Tiznit', 'slug' => 'tiznit', 'region' => 'Souss-Massa', 'display_order' => 26],
                ['name' => 'Ouarzazate', 'slug' => 'ouarzazate', 'region' => 'Drâa-Tafilalet', 'display_order' => 27],
                ['name' => 'Dakhla', 'slug' => 'dakhla', 'region' => 'Dakhla-Oued Ed-Dahab', 'display_order' => 28],
                ['name' => 'Laâyoune', 'slug' => 'laayoune', 'region' => 'Laâyoune-Sakia El Hamra', 'display_order' => 29],
            ];

            $now = now();
            foreach ($moroccanCities as $city) {
                DB::table('cities')->insert(array_merge($city, [
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]));
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cities');
    }
};

