<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateJiraIssueConversationTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('jira_issue_conversation', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('jira_issue_id');
            $table->unsignedInteger('conversation_id');

            // Indexes
            $table->unique(['jira_issue_id', 'conversation_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('jira_issue_conversation');
    }
}
