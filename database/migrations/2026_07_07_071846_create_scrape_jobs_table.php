<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateScrapeJobsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('scrape_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('place_id', 255);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->integer('duration')->nullable();
            $table->string('status', 50);
            $table->integer('new_reviews')->default(0);
            $table->integer('updated_reviews')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->foreign('place_id')->references('place_id')->on('places')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('scrape_jobs');
    }
}
