<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGithubIssueConversationTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('github_issue_conversation', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('github_issue_id');
            $table->unsignedBigInteger('conversation_id');
            $table->timestamps();
            
            // Foreign key constraints
            $table->foreign('github_issue_id')->references('id')->on('github_issues')->onDelete('cascade');
            $table->foreign('conversation_id')->references('id')->on('conversations')->onDelete('cascade');
            
            // Unique constraint to prevent duplicate links
            $table->unique(['github_issue_id', 'conversation_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('github_issue_conversation');
    }
}