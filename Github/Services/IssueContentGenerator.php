<?php

namespace Modules\Github\Services;

use App\Conversation;

class IssueContentGenerator
{
    /**
     * Generate issue title and body from conversation
     */
    public function generateContent(Conversation $conversation)
    {
        $aiService = \Option::get('github.ai_service');
        $aiApiKey = \Option::get('github.ai_api_key');

        if (!$aiApiKey || empty($aiService)) {
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

        // Determine SSL settings based on environment
        $isLocalDev = in_array(config('app.env'), ['local', 'dev', 'development']) || 
                      strpos(config('app.url'), '.local') !== false ||
                      strpos(config('app.url'), 'localhost') !== false;

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => 'https://api.openai.com/v1/chat/completions',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => !$isLocalDev,
            CURLOPT_SSL_VERIFYHOST => $isLocalDev ? 0 : 2,
            CURLOPT_FOLLOWLOCATION => true,
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
        $error = curl_error($curl);
        $errno = curl_errno($curl);
        $info = curl_getinfo($curl);
        curl_close($curl);


        if ($httpCode === 200) {
            $data = json_decode($response, true);
            
            if (isset($data['choices'][0]['message']['content'])) {
                $contentString = $data['choices'][0]['message']['content'];
                
                $content = json_decode($contentString, true);
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

        // Determine SSL settings based on environment  
        $isLocalDev = in_array(config('app.env'), ['local', 'dev', 'development']) || 
                      strpos(config('app.url'), '.local') !== false ||
                      strpos(config('app.url'), 'localhost') !== false;

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => 'https://api.anthropic.com/v1/messages',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => !$isLocalDev,
            CURLOPT_SSL_VERIFYHOST => $isLocalDev ? 0 : 2,
            CURLOPT_FOLLOWLOCATION => true,
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
        
        // Get all customer messages for better context
        $threads = $conversation->threads()
            ->whereIn('type', [\App\Thread::TYPE_CUSTOMER, \App\Thread::TYPE_MESSAGE])
            ->orderBy('created_at')
            ->limit(5)
            ->get();
        
        // Extract conversation summary
        $conversationSummary = $this->extractConversationSummary($threads);
        $technicalDetails = $this->extractTechnicalDetails($threads);
        $customerMessage = '';
        
        $firstCustomerThread = $threads->where('type', \App\Thread::TYPE_CUSTOMER)->first();
        if ($firstCustomerThread) {
            $customerMessage = strip_tags($firstCustomerThread->body);
            $customerMessage = strlen($customerMessage) > 800 ? 
                substr($customerMessage, 0, 800) . '...' : 
                $customerMessage;
        }

        // Get customer info safely
        $customerName = 'Unknown Customer';
        $customerEmail = 'No email';
        if ($conversation->customer) {
            $customerName = $conversation->customer->getFullName() ?: 'Unknown Customer';
            $customerEmail = $conversation->customer->email ?: 'No email';
        }

        // Generate title
        $title = $subject;
        if (empty($title)) {
            $title = 'Support Request from ' . $customerName;
        }

        // Check for custom manual template
        $customTemplate = \Option::get('github.manual_template');
        
        if (!empty($customTemplate)) {
            // Prepare conversation summary with fallback text
            $summaryText = $conversationSummary;
            if (!$summaryText) {
                $aiService = \Option::get('github.ai_service');
                $aiApiKey = \Option::get('github.ai_api_key');
                
                if (empty($aiService) || empty($aiApiKey)) {
                    $summaryText = "_No AI service configured. To get intelligent summaries, configure OpenAI or Claude API in GitHub module settings._";
                } else {
                    $summaryText = "_AI summary generation failed. Using manual template._";
                }
            }
            
            // Use custom template with variable replacement
            $body = str_replace([
                '{customer_name}',
                '{customer_email}',
                '{subject}',
                '{conversation_url}',
                '{status}',
                '{created_at}',
                '{customer_message}',
                '{conversation_summary}',
                '{technical_details}',
                '{thread_count}'
            ], [
                $customerName,
                $customerEmail,
                $subject,
                url("/conversation/" . $conversation->id),
                ucfirst($conversation->getStatusName()),
                $conversation->created_at->format('Y-m-d H:i:s'),
                $customerMessage ?: 'No customer message available',
                $summaryText,
                $technicalDetails ?: 'No technical details found',
                $threads->count()
            ], $customTemplate);
        } else {
            // Default template generation
            $body = "## Summary\n\n";
            if ($conversationSummary) {
                $body .= $conversationSummary . "\n\n";
            } else {
                // Check if AI service is configured
                $aiService = \Option::get('github.ai_service');
                $aiApiKey = \Option::get('github.ai_api_key');
                
                if (empty($aiService) || empty($aiApiKey)) {
                    $body .= "_No AI service configured. To get intelligent summaries, configure OpenAI or Claude API in GitHub module settings._\n\n";
                } else {
                    $body .= "_AI summary generation failed. Using manual template._\n\n";
                }
            }
            
            $body .= "## Customer Information\n\n";
            $body .= "- **Name:** " . $customerName . "\n";
            $body .= "- **Email:** " . $customerEmail . "\n";
            $body .= "- **Subject:** " . $subject . "\n\n";
            
            if ($technicalDetails) {
                $body .= "## Technical Details\n\n";
                $body .= $technicalDetails . "\n\n";
            }
            
            if ($customerMessage) {
                $body .= "## Original Message\n\n";
                $body .= "```\n" . $customerMessage . "\n```\n\n";
            }
            
            // Add conversation thread summary
            if ($threads->count() > 1) {
                $body .= "## Conversation History\n\n";
                foreach ($threads->take(3) as $thread) {
                    $sender = $thread->type === \App\Thread::TYPE_CUSTOMER ? '👤 Customer' : '🏢 Support';
                    $preview = strip_tags($thread->body);
                    $preview = strlen($preview) > 200 ? substr($preview, 0, 200) . '...' : $preview;
                    $body .= "**{$sender}** (" . $thread->created_at->format('M d, H:i') . "):\n";
                    $body .= "> " . str_replace("\n", "\n> ", $preview) . "\n\n";
                }
            }

            $body .= "## Metadata\n\n";
            $body .= "- **FreeScout URL:** " . url("/conversation/" . $conversation->id) . "\n";
            $body .= "- **Status:** " . ucfirst($conversation->getStatusName()) . "\n";
            $body .= "- **Created:** " . $conversation->created_at->format('Y-m-d H:i:s') . "\n";
            $body .= "- **Messages:** " . $threads->count() . "\n";
        }

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
            ->whereIn('type', [\App\Thread::TYPE_CUSTOMER, \App\Thread::TYPE_MESSAGE])
            ->orderBy('created_at')
            ->limit(10) // Limit to first 10 messages to avoid token limits
            ->get();


        $text = "Subject: " . $conversation->subject . "\n\n";
        
        foreach ($threads as $thread) {
            $sender = $thread->type === \App\Thread::TYPE_CUSTOMER ? 'Customer' : 'Support';
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
        $customerName = $conversation->customer ? $conversation->customer->getFullName() : 'Unknown Customer';
        $customerEmail = $conversation->customer ? $conversation->customer->email : 'No email';
        $conversationUrl = url("/conversation/" . $conversation->id);
        $status = ucfirst($conversation->getStatusName());
        
        // Check for custom AI prompt template
        $customPrompt = \Option::get('github.ai_prompt_template');
        
        if (!empty($customPrompt)) {
            // Use custom template with variable replacement
            return str_replace([
                '{customer_name}',
                '{customer_email}',
                '{conversation_url}',
                '{status}',
                '{conversation_text}'
            ], [
                $customerName,
                $customerEmail,
                $conversationUrl,
                $status,
                $conversationText
            ], $customPrompt);
        }
        
        // Default prompt template
        return "Create a GitHub issue from this customer support conversation.

Customer: $customerName
Customer Email: $customerEmail
FreeScout URL: $conversationUrl
Status: $status

Conversation:
$conversationText

Requirements:
1. Create a clear, professional issue title (max 80 characters)
2. Create a detailed issue body with:
   - Brief problem summary
   - Customer details (name: $customerName, email: $customerEmail)
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

    /**
     * Extract a summary from conversation threads
     */
    private function extractConversationSummary($threads)
    {
        // Without AI, we can't generate a true summary
        // This would be replaced by AI analysis
        return null;
    }

    /**
     * Extract technical details from conversation
     */
    private function extractTechnicalDetails($threads)
    {
        $technicalKeywords = [
            'error', 'bug', 'issue', 'problem', 'crash', 'fail', 'exception',
            'version', 'PHP', 'WordPress', 'plugin', 'theme', 'database',
            'API', 'integration', 'webhook', 'timeout', '404', '500', 'status code'
        ];
        
        $details = [];
        
        foreach ($threads as $thread) {
            $body = strtolower(strip_tags($thread->body));
            
            // Look for version numbers
            if (preg_match_all('/(?:version|v)?\s*(\d+\.\d+(?:\.\d+)?)/i', $body, $matches)) {
                foreach ($matches[1] as $version) {
                    $details[] = "Version mentioned: " . $version;
                }
            }
            
            // Look for error messages
            if (preg_match_all('/(?:error|exception):\s*([^\n.]+)/i', $body, $matches)) {
                foreach ($matches[1] as $error) {
                    $details[] = "Error: " . trim($error);
                }
            }
            
            // Look for URLs
            if (preg_match_all('/(https?:\/\/[^\s]+)/i', $body, $matches)) {
                foreach ($matches[1] as $url) {
                    if (!strpos($url, 'freescout')) {
                        $details[] = "URL mentioned: " . $url;
                    }
                }
            }
        }
        
        return $details ? implode("\n", array_unique($details)) : null;
    }
}