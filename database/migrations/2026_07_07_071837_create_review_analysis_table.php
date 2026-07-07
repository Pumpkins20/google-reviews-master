<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateReviewAnalysisTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('review_analysis', function (Blueprint $table) {
            $table->id();
            $table->string('review_id', 255);
            $table->string('place_id', 255);
            $table->string('provider', 50);
            $table->string('model', 50);
            $table->string('prompt_version', 20);
            $table->string('spam_label', 50);
            $table->double('confidence');
            $table->string('category', 100)->nullable();
            $table->text('reason')->nullable();
            $table->longText('raw_response')->nullable();
            $table->timestamp('analyzed_at')->nullable();
            $table->timestamps();

            $table->foreign(['review_id', 'place_id'])->references(['review_id', 'place_id'])->on('reviews')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('review_analysis');
    }
}
