<?php

namespace Modules\Github\Services;

use App\Conversation;
use App\Thread;

class IssueContentGenerator
{
    /**
     * Generate issue title and body from conversation
     */
    public function generateContent(Conversation $conversation)
    {
        $aiService = \Option::get('github.ai_service', 'openai');
        $aiApiKey = \Option::get('github.ai_api_key');

        if (!$aiApiKey) {
            // Fallback to manual content generation
            return $this->generateManualContent($conversation);
        }

        try {
            switch ($aiService) {
                case 'openai':
                    return $this->generateWithOpenAI($conversation, $aiApiKey);
                case 'claude':
                    return $this->generateWithClaude($conversation, $aiApiKey);
                default:
                    return $this->generateManualContent($conversation);
            }
        } catch (\Exception $e) {
            \Helper::logException($e, '[GitHub] AI Content Generation Error');
            return $this->generateManualContent($conversation);
        }
    }

    /**
     * Generate content using OpenAI API
     */
    private function generateWithOpenAI(Conversation $conversation, $apiKey)
    {
        $conversationText = $this->extractConversationText($conversation);
        
        $prompt = $this->buildPrompt($conversationText, $conversation);

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => 'https://api.openai.com/v1/chat/completions',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json'
            ],
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode([
                'model' => 'gpt-3.5-turbo',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You are a helpful assistant that creates GitHub issues from customer support conversations. Always respond with valid JSON containing "title" and "body" fields.'
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ]
                ],
                'max_tokens' => 1000,
                'temperature' => 0.7
            ])
        ]);

        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if ($httpCode === 200) {
            $data = json_decode($response, true);
            if (isset($data['choices'][0]['message']['content'])) {
                $content = json_decode($data['choices'][0]['message']['content'], true);
                if ($content && isset($content['title'], $content['body'])) {
                    return $content;
                }
            }
        }

        // Fallback if API call fails
        return $this->generateManualContent($conversation);
    }

    /**
     * Generate content using Claude API
     */
    private function generateWithClaude(Conversation $conversation, $apiKey)
    {
        $conversationText = $this->extractConversationText($conversation);
        
        $prompt = $this->buildPrompt($conversationText, $conversation);

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => 'https://api.anthropic.com/v1/messages',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => [
                'x-api-key: ' . $apiKey,
                'Content-Type: application/json',
                'anthropic-version: 2023-06-01'
            ],
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode([
                'model' => 'claude-3-haiku-20240307',
                'max_tokens' => 1000,
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ]
                ]
            ])
        ]);

        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if ($httpCode === 200) {
            $data = json_decode($response, true);
            if (isset($data['content'][0]['text'])) {
                $content = json_decode($data['content'][0]['text'], true);
                if ($content && isset($content['title'], $content['body'])) {
                    return $content;
                }
            }
        }

        // Fallback if API call fails
        return $this->generateManualContent($conversation);
    }

    /**
     * Generate content manually without AI
     */
    private function generateManualContent(Conversation $conversation)
    {
        $subject = $conversation->subject;
        $firstThread = $conversation->threads()->orderBy('created_at')->first();
        
        // Extract first customer message
        $customerMessage = '';
        if ($firstThread && $firstThread->type === Thread::TYPE_CUSTOMER) {
            $customerMessage = strip_tags($firstThread->body);
            $customerMessage = strlen($customerMessage) > 500 ? 
                substr($customerMessage, 0, 500) . '...' : 
                $customerMessage;
        }

        // Generate title
        $title = $subject;
        if (empty($title)) {
            $title = 'Support Request from ' . $conversation->customer->getFullName();
        }

        // Generate body
        $body = "## Support Request\n\n";
        $body .= "**Customer:** " . $conversation->customer->getFullName() . "\n";
        $body .= "**Email:** " . $conversation->customer->email . "\n";
        $body .= "**Subject:** " . $subject . "\n\n";
        
        if ($customerMessage) {
            $body .= "**Original Message:**\n";
            $body .= $customerMessage . "\n\n";
        }

        $body .= "**FreeScout Conversation:** " . \Helper::getAppUrl() . "/conversation/" . $conversation->id . "\n";
        $body .= "**Status:** " . ucfirst($conversation->getStatusName()) . "\n";
        $body .= "**Created:** " . $conversation->created_at->format('Y-m-d H:i:s') . "\n";

        return [
            'title' => $title,
            'body' => $body
        ];
    }

    /**
     * Extract conversation text for AI processing
     */
    private function extractConversationText(Conversation $conversation)
    {
        $threads = $conversation->threads()
            ->whereIn('type', [Thread::TYPE_CUSTOMER, Thread::TYPE_MESSAGE])
            ->orderBy('created_at')
            ->limit(10) // Limit to first 10 messages to avoid token limits
            ->get();

        $text = "Subject: " . $conversation->subject . "\n\n";
        
        foreach ($threads as $thread) {
            $sender = $thread->type === Thread::TYPE_CUSTOMER ? 'Customer' : 'Support';
            $body = strip_tags($thread->body);
            $body = strlen($body) > 300 ? substr($body, 0, 300) . '...' : $body;
            
            $text .= "[$sender]: $body\n\n";
        }

        return $text;
    }

    /**
     * Build AI prompt for content generation
     */
    private function buildPrompt($conversationText, Conversation $conversation)
    {
        $customerName = $conversation->customer->getFullName();
        $conversationUrl = \Helper::getAppUrl() . "/conversation/" . $conversation->id;

        return "Create a GitHub issue from this customer support conversation.

Customer: $customerName
FreeScout URL: $conversationUrl
Status: " . ucfirst($conversation->getStatusName()) . "

Conversation:
$conversationText

Requirements:
1. Create a clear, professional issue title (max 80 characters)
2. Create a detailed issue body with:
   - Brief problem summary
   - Customer details (name, email)
   - Original message context
   - Link back to FreeScout conversation
   - Any technical details mentioned
3. Use proper GitHub markdown formatting
4. Be professional and technical in tone

Respond with valid JSON in this format:
{
  \"title\": \"Issue title here\",
  \"body\": \"Issue body with markdown formatting\"
}";
    }
}