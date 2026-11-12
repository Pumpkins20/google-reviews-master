<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePlacePhotosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('place_photos', function (Blueprint $table) {
            $table->id();
            $table->string('place_id', 255);
            $table->char('url_hash', 40);
            $table->text('url');
            $table->text('maps_url')->nullable();
            // Copy of the latest analysis, so lists can filter without joins.
            $table->string('label', 30)->nullable();
            $table->timestamp('analyzed_at')->nullable();
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('removed_at')->nullable();
            $table->timestamps();

            $table->unique(['place_id', 'url_hash']);
            $table->index('label');
            $table->foreign('place_id')->references('place_id')->on('places')->onDelete('cascade');
        });

        Schema::create('photo_analyses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('photo_id');
            $table->string('provider', 50);
            $table->string('model', 50);
            $table->string('prompt_version', 20);
            $table->string('label', 30);
            $table->string('category', 40)->nullable();
            $table->double('confidence')->nullable();
            $table->text('extracted_phones')->nullable();
            $table->text('official_phones')->nullable();
            $table->boolean('phone_mismatch')->default(false);
            $table->text('flags')->nullable();
            $table->text('reason')->nullable();
            $table->longText('raw_response')->nullable();
            $table->integer('execution_time_ms')->nullable();
            $table->integer('input_tokens')->nullable();
            $table->integer('output_tokens')->nullable();
            $table->decimal('cost', 10, 6)->nullable();
            $table->timestamp('analyzed_at')->nullable();
            $table->timestamps();

            $table->index('photo_id');
            $table->foreign('photo_id')->references('id')->on('place_photos')->onDelete('cascade');
        });

        Schema::create('photo_reports', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('photo_id')->unique();
            $table->string('status', 20)->default('pending');
            $table->text('note')->nullable();
            $table->timestamp('reported_at')->nullable();
            $table->timestamps();

            $table->foreign('photo_id')->references('id')->on('place_photos')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('photo_reports');
        Schema::dropIfExists('photo_analyses');
        Schema::dropIfExists('place_photos');
    }
}
