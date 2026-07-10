<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddMetricsToReviewAnalysisTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('review_analysis', function (Blueprint $table) {
            $table->integer('execution_time_ms')->nullable()->after('raw_response');
            $table->integer('input_tokens')->nullable()->after('execution_time_ms');
            $table->integer('output_tokens')->nullable()->after('input_tokens');
            $table->decimal('cost', 10, 6)->nullable()->after('output_tokens');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('review_analysis', function (Blueprint $table) {
            $table->dropColumn(['execution_time_ms', 'input_tokens', 'output_tokens', 'cost']);
        });
    }
}
