<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS postgis');

        Schema::create('change_points', function (Blueprint $table) {
            $table->id();
            $table->string('point_id', 30);
            $table->string('authority', 100);
            $table->string('county_city', 20);
            $table->string('verification_result', 50)->nullable();
            $table->string('change_type', 200)->nullable();
            $table->integer('year');
            $table->decimal('latitude', 15, 12);
            $table->decimal('longitude', 15, 12);
            $table->timestamps();

            $table->unique(['point_id', 'year']);
            $table->index('year');
            $table->index('county_city');
            $table->index('change_type');
            $table->index('verification_result');
        });

        DB::statement('ALTER TABLE change_points ADD COLUMN geom geometry(Point, 4326)');
        DB::statement('CREATE INDEX change_points_geom_idx ON change_points USING GIST(geom)');
    }

    public function down(): void
    {
        Schema::dropIfExists('change_points');
    }
};
