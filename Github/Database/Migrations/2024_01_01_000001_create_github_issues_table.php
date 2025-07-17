<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGithubIssuesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('github_issues', function (Blueprint $table) {
            $table->id();
            $table->integer('number')->index(); // GitHub issue number
            $table->string('repository')->index(); // owner/repo format
            $table->string('title');
            $table->text('body')->nullable();
            $table->string('state', 20)->default('open'); // open, closed
            $table->json('labels')->nullable(); // GitHub labels
            $table->json('assignees')->nullable(); // GitHub assignees
            $table->string('author')->nullable();
            $table->timestamp('github_created_at')->nullable();
            $table->timestamp('github_updated_at')->nullable();
            $table->string('html_url');
            $table->timestamps();
            
            // Unique constraint on number + repository
            $table->unique(['number', 'repository']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('github_issues');
    }
}