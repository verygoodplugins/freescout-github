<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGithubLabelMappingsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('github_label_mappings', function (Blueprint $table) {
            $table->id();
            $table->string('freescout_tag');
            $table->string('github_label');
            $table->string('repository'); // owner/repo format
            $table->decimal('confidence_threshold', 3, 2)->default(0.80); // 0.00 to 1.00
            $table->timestamps();
            
            // Unique constraint to prevent duplicate mappings
            $table->unique(['freescout_tag', 'repository']);
            
            // Index for faster lookups
            $table->index(['repository', 'freescout_tag']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('github_label_mappings');
    }
}