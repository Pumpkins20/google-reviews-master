<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateReviewsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->string('review_id', 255);
            $table->string('place_id', 255);
            $table->text('author')->nullable();
            $table->double('rating')->nullable();
            $table->longText('review_text')->nullable();
            $table->string('review_date', 100)->nullable();
            $table->string('raw_date', 100)->nullable();
            $table->integer('likes')->default(0);
            $table->text('user_images')->nullable();
            $table->text('s3_images')->nullable();
            $table->text('profile_url')->nullable();
            $table->text('profile_picture')->nullable();
            $table->text('s3_profile_picture')->nullable();
            $table->text('owner_responses')->nullable();
            $table->string('created_date', 50);
            $table->string('last_modified', 50);
            $table->integer('last_seen_session')->nullable();
            $table->integer('last_changed_session')->nullable();
            $table->tinyInteger('is_deleted')->default(0);
            $table->string('content_hash', 64)->nullable();
            $table->string('engagement_hash', 64)->nullable();
            $table->integer('row_version')->default(1);
            $table->text('sub_ratings')->nullable();
            $table->timestamps();

            $table->primary(['review_id', 'place_id']);
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
        Schema::dropIfExists('reviews');
    }
}
