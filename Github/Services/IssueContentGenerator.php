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
            CURLOPT_POSTFIELDS => \Helper::jsonEncodeSafe([
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
                    // Post-process to inject conversation JSON
                    return $this->injectConversationContext($content, $conversation);
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
            CURLOPT_POSTFIELDS => \Helper::jsonEncodeSafe([
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
                    // Post-process to inject conversation JSON
                    return $this->injectConversationContext($content, $conversation);
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
        
        // Get all messages including support team analysis for better context
        $threads = $conversation->threads()
            ->whereIn('type', [\App\Thread::TYPE_CUSTOMER, \App\Thread::TYPE_MESSAGE, \App\Thread::TYPE_NOTE])
            ->orderBy('created_at')
            ->limit(8) // Increased to capture support analysis
            ->get();
        
        // Extract conversation summary and diagnostic info
        $conversationSummary = $this->extractConversationSummary($threads);
        $conversationText = $this->extractConversationText($conversation);
        $diagnosticInfo = $this->extractDiagnosticInfo($conversationText);
        $technicalDetails = $this->extractTechnicalDetails($threads);
        $customerMessage = '';
        
        $firstCustomerThread = $threads->where('type', \App\Thread::TYPE_CUSTOMER)->first();
        if ($firstCustomerThread) {
            $customerMessage = \Helper::utf8Encode(strip_tags($firstCustomerThread->body));
            $customerMessage = strlen($customerMessage) > 800 ? 
                substr($customerMessage, 0, 800) . '...' : 
                $customerMessage;
        }

        // Get customer info safely
        $customerName = 'Unknown Customer';
        $customerEmail = 'No email';
        if ($conversation->customer) {
            $customerName = $conversation->customer->getFullName() ?: 'Unknown Customer';
            $customerEmail = $conversation->customer->getMainEmail() ?: 'No email';
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
                '{conversation_json}',
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
                $conversationText, // Full conversation JSON
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
            
            // Add AI-extracted diagnostic information if available
            if ($diagnosticInfo) {
                if (isset($diagnosticInfo['reproduction_confirmed']) && $diagnosticInfo['reproduction_confirmed']) {
                    $body .= "## Reproduction Status\n\n";
                    $body .= "✅ **Confirmed** - Support team successfully reproduced this issue\n\n";
                }
                
                if (!empty($diagnosticInfo['root_cause'])) {
                    $body .= "## Root Cause Analysis\n\n";
                    $body .= $diagnosticInfo['root_cause'] . "\n\n";
                }
                
                if (!empty($diagnosticInfo['symptoms'])) {
                    $body .= "## Symptoms\n\n";
                    foreach ($diagnosticInfo['symptoms'] as $symptom) {
                        $body .= "- " . $symptom . "\n";
                    }
                    $body .= "\n";
                }
                
                if (!empty($diagnosticInfo['conflicting_plugins'])) {
                    $body .= "## Plugin Conflicts\n\n";
                    foreach ($diagnosticInfo['conflicting_plugins'] as $plugin) {
                        $body .= "- " . $plugin . "\n";
                    }
                    $body .= "\n";
                }
                
                if (!empty($diagnosticInfo['support_analysis'])) {
                    $body .= "## Support Team Analysis\n\n";
                    foreach ($diagnosticInfo['support_analysis'] as $analysis) {
                        $body .= "- " . $analysis . "\n";
                    }
                    $body .= "\n";
                }
                
                if (!empty($diagnosticInfo['customer_environment'])) {
                    $body .= "## Customer Environment\n\n";
                    foreach ($diagnosticInfo['customer_environment'] as $key => $value) {
                        $body .= "- **" . ucfirst(str_replace('_', ' ', $key)) . ":** " . $value . "\n";
                    }
                    $body .= "\n";
                }
            }
            
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
                    $preview = \Helper::utf8Encode(strip_tags($thread->body));
                    $preview = strlen($preview) > 200 ? substr($preview, 0, 200) . '...' : $preview;
                    $body .= "**{$sender}** (" . $thread->created_at->format('M d, H:i') . "):\n";
                    $body .= "> " . str_replace("\n", "\n> ", $preview) . "\n\n";
                }
            }

            // Add conversation context for AI
            $body .= "## Conversation Context (Last 7 Days)\n\n";
            $body .= "The following JSON contains the full conversation history for AI analysis:\n\n";
            $body .= $conversationText . "\n\n";
            
            $body .= "## Metadata\n\n";
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
     * Post-process AI response to inject conversation context
     */
    private function injectConversationContext($content, Conversation $conversation)
    {
        // Extract conversation JSON
        $conversationText = $this->extractConversationText($conversation);
        
        $body = $content['body'];
        
        // Look for FreeScout link section and replace it with conversation JSON
        $patterns = [
            '/##\s*FreeScout\s*Link\s*\n+.*?(?=\n##|\z)/si',
            '/##\s*Related\s*Conversation\s*\n+.*?(?=\n##|\z)/si',
            '/\*\*FreeScout\s*Link\*\*:\s*.*?\n/i',
            '/\[View in FreeScout\]\(.*?\)\n?/i'
        ];
        
        $foundLink = false;
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $body)) {
                $foundLink = true;
                $body = preg_replace($pattern, '', $body);
                break;
            }
        }
        
        // Add conversation context section
        $conversationSection = "\n## Conversation Context (Last 7 Days)\n\n";
        $conversationSection .= "The following JSON contains the full conversation history for AI analysis:\n\n";
        $conversationSection .= $conversationText . "\n";
        
        // If we found and removed a FreeScout link, insert the conversation JSON in its place
        if ($foundLink) {
            // Find a good place to insert (before the last section or at the end)
            if (preg_match('/\n(##[^\n]+)$/', $body, $matches, PREG_OFFSET_CAPTURE)) {
                // Insert before the last section
                $insertPos = $matches[0][1];
                $body = substr($body, 0, $insertPos) . $conversationSection . substr($body, $insertPos);
            } else {
                // Just append at the end
                $body .= $conversationSection;
            }
        } else {
            // No FreeScout link found, append at the end
            $body .= $conversationSection;
        }
        
        $content['body'] = $body;
        return $content;
    }

    /**
     * Extract conversation text for AI processing
     */
    private function extractConversationText(Conversation $conversation)
    {
        // Get threads from the past 7 days only
        $sevenDaysAgo = \Carbon\Carbon::now()->subDays(7);
        
        $threads = $conversation->threads()
            ->whereIn('type', [\App\Thread::TYPE_CUSTOMER, \App\Thread::TYPE_MESSAGE, \App\Thread::TYPE_NOTE])
            ->where('created_at', '>=', $sevenDaysAgo)
            ->orderBy('created_at')
            ->limit(20) // Increased limit since we're filtering by date
            ->get();

        // Build structured conversation data
        $conversationData = [
            'subject' => $conversation->subject,
            'created_at' => $conversation->created_at->toIso8601String(),
            'messages' => []
        ];
        
        foreach ($threads as $thread) {
            // Determine sender type more accurately
            $sender = 'Support';
            $senderName = 'Support Team';
            
            if ($thread->type === \App\Thread::TYPE_CUSTOMER) {
                $sender = 'Customer';
                $senderName = $thread->created_by ? $thread->created_by->getFullName() : 'Customer';
            } elseif ($thread->type === \App\Thread::TYPE_NOTE) {
                $sender = 'Support Team (Internal Note)';
                $senderName = $thread->created_by ? $thread->created_by->getFullName() : 'Support Team';
            } elseif ($thread->created_by && $thread->created_by->isCustomer()) {
                $sender = 'Customer';
                $senderName = $thread->created_by->getFullName();
            } elseif ($thread->created_by) {
                $senderName = $thread->created_by->getFullName();
            }
            
            $body = $this->extractStructuredContent($thread->body);
            $body = $this->filterExternalLinks($body);
            
            $conversationData['messages'][] = [
                'timestamp' => $thread->created_at->toIso8601String(),
                'sender_type' => $sender,
                'sender_name' => $senderName,
                'message' => $body
            ];
        }

        // Format as JSON in markdown block for better AI parsing
        $jsonData = json_encode($conversationData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        
        return "```json\n" . $jsonData . "\n```";
    }

    /**
     * Filter out external and private links that AI can't access
     */
    private function filterExternalLinks($text)
    {
        // Remove common external/private link patterns
        $patterns = [
            '/https?:\/\/www\.loom\.com\/[^\s]+/i',           // Loom videos
            '/https?:\/\/[^\/\s]*\.loom\.com\/[^\s]+/i',     // Any loom subdomain
            '/https?:\/\/drive\.google\.com\/[^\s]+/i',      // Google Drive
            '/https?:\/\/dropbox\.com\/[^\s]+/i',            // Dropbox
            '/https?:\/\/[^\/\s]*\.sharepoint\.com\/[^\s]+/i', // SharePoint
            '/https?:\/\/[^\/\s]*support\.[^\/\s]+\/[^\s]+/i', // Support portals
            '/https?:\/\/support\.[^\/\s]+\/[^\s]+/i',       // Support subdomains
        ];
        
        foreach ($patterns as $pattern) {
            $text = preg_replace($pattern, '[External link removed]', $text);
        }
        
        return $text;
    }

    /**
     * Extract structured content from HTML, preserving form field structure
     */
    private function extractStructuredContent($html)
    {
        // Clean UTF-8 encoding before processing
        $html = \Helper::utf8Encode($html);
        
        // Check if this looks like a structured HTML table form
        if (strpos($html, '<table') !== false && strpos($html, '<strong>') !== false) {
            return $this->parseHTMLTable($html);
        }
        
        // Fall back to regular strip_tags for simple content
        return \Helper::utf8Encode(strip_tags($html));
    }

    /**
     * Parse HTML table structure to extract form fields
     */
    private function parseHTMLTable($html)
    {
        try {
            $structured = [];
            
            // Create DOMDocument to parse HTML properly
            $dom = new \DOMDocument();
            
            // Suppress HTML parsing warnings for malformed HTML
            libxml_use_internal_errors(true);
            $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
            libxml_clear_errors();
            
            // Find all table rows
            $rows = $dom->getElementsByTagName('tr');
            $currentField = null;
            
            foreach ($rows as $row) {
                $cells = $row->getElementsByTagName('td');
                
                if ($cells->length >= 2) {
                    $firstCell = \Helper::utf8Encode(trim($cells->item(0)->textContent));
                    $secondCell = \Helper::utf8Encode(trim($cells->item(1)->textContent));
                    
                    // Check if first cell contains a field label (has <strong> tag)
                    $strongTags = $cells->item(0)->getElementsByTagName('strong');
                    if ($strongTags->length > 0) {
                        $currentField = \Helper::utf8Encode(trim($strongTags->item(0)->textContent));
                    } else if (!empty($secondCell) && !empty($currentField) && $secondCell !== '&nbsp;') {
                        // This is a value row for the current field
                        $structured[$currentField] = $secondCell;
                        $currentField = null;
                    }
                }
            }
            
            // Format the structured data
            $formatted = [];
            foreach ($structured as $field => $value) {
                if (!empty($value) && $value !== '&nbsp;') {
                    $formatted[] = "{$field}: {$value}";
                }
            }
            
            $result = implode("\n", $formatted);
            
            // If we got structured data, return it, otherwise fall back to strip_tags
            return !empty($result) ? $result : \Helper::utf8Encode(strip_tags($html));
            
        } catch (\Exception $e) {
            // If HTML parsing fails, fall back to strip_tags
            \Helper::log('github_html_parsing', 'HTML parsing failed: ' . $e->getMessage());
            return \Helper::utf8Encode(strip_tags($html));
        }
    }

    /**
     * Build AI prompt for content generation
     */
    private function buildPrompt($conversationText, Conversation $conversation)
    {
        $customerName = 'Unknown Customer';
        $customerEmail = 'No email';
        
        if ($conversation->customer) {
            $customerName = $conversation->customer->getFullName() ?: 'Unknown Customer';
            $customerEmail = $conversation->customer->getMainEmail() ?: 'No email';
        } else {
            // Try to load customer manually if the relationship didn't work
            if (!empty($conversation->customer_id)) {
                $customer = \App\Customer::find($conversation->customer_id);
                if ($customer) {
                    $customerName = $customer->getFullName() ?: 'Unknown Customer';
                    $customerEmail = $customer->getMainEmail() ?: 'No email';
                }
            }
        }
        
        $conversationUrl = url("/conversation/" . $conversation->id);
        $status = ucfirst($conversation->getStatusName());
        
        // Check for custom AI prompt template
        $customPrompt = \Option::get('github.ai_prompt_template');
        
        if (!empty($customPrompt)) {
            // Use custom template with variable replacement
            $prompt = str_replace([
                '{customer_name}',
                '{customer_email}',
                '{conversation_url}',
                '{conversation_json}',
                '{status}',
                '{conversation_text}'
            ], [
                $customerName,
                $customerEmail,
                $conversationUrl,
                $conversationText, // Same as conversation_json
                $status,
                $conversationText
            ], $customPrompt);
            
            return $prompt;
        }
        
        // Extract diagnostic information first
        $diagnosticInfo = $this->extractDiagnosticInfo($conversationText);
        
        // Build diagnostic context for the prompt
        $diagnosticContext = "";
        if ($diagnosticInfo) {
            $diagnosticContext = "\n\nDIAGNOSTIC INFORMATION EXTRACTED:\n";
            $diagnosticContext .= json_encode($diagnosticInfo, JSON_PRETTY_PRINT);
            $diagnosticContext .= "\n\nUse this diagnostic information to create a comprehensive issue.";
        }

        // Default prompt template
        $prompt = "Create a GitHub issue from this customer support conversation.

Customer: $customerName
Customer Email: $customerEmail
FreeScout URL: $conversationUrl
Status: $status

Conversation:
$conversationText$diagnosticContext

Requirements:
1. Create a clear, professional issue title (max 80 characters)
2. Create a detailed issue body with these sections:
   - **Problem Summary**: Brief description of the issue
   - **Customer Details**: name: $customerName, email: $customerEmail
   - **Reproduction Status**: Include if support team confirmed reproduction
   - **Root Cause**: Include any identified root cause or technical analysis
   - **Symptoms**: List specific symptoms or behaviors
   - **Plugin Conflicts**: Any conflicting plugins identified
   - **Customer Environment**: Customer's setup details (WordPress version, troubleshooting methods, etc.)
   - **Steps to Reproduce**: Any reproduction steps mentioned
   - **Support Analysis**: Key findings from support team investigation
   - **Conversation Context**: Include the full conversation JSON from the last 7 days

3. Use proper GitHub markdown formatting with clear sections
4. Be professional and technical in tone
5. Make the issue actionable for developers
6. The conversation data is provided as structured JSON - parse it carefully
7. If diagnostic information is provided above, prioritize it over manual conversation parsing
8. Do NOT include external links (Loom, Google Drive, etc.) - they've been filtered out

Respond with valid JSON in this format:
{
  \"title\": \"Issue title here\",
  \"body\": \"Issue body with markdown formatting\"
}";

        return $prompt;
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
     * Extract diagnostic information using AI analysis
     */
    private function extractDiagnosticInfo($conversationText)
    {
        $aiService = \Option::get('github.ai_service');
        $aiApiKey = \Option::get('github.ai_api_key');
        
        if (empty($aiService) || empty($aiApiKey)) {
            return null;
        }
        
        $prompt = "Analyze this support conversation (provided as structured JSON) and extract diagnostic information.

Conversation Data:
$conversationText

Extract the following diagnostic information if present:
1. reproduction_confirmed: true/false - did support team confirm they reproduced the issue?
2. root_cause: string - any identified root cause or technical analysis
3. issue_type: string - type of issue (CSS, JavaScript, plugin conflict, etc.)
4. symptoms: array - specific symptoms or behaviors described
5. conflicting_plugins: array - any plugins mentioned as causing conflicts
6. technical_details: array - versions, error messages, browser info, etc.
7. reproduction_steps: array - any steps mentioned to reproduce the issue
8. support_analysis: array - key findings or analysis from support team
9. customer_environment: object - customer's setup details (WordPress version, plugins, etc.)

Pay special attention to:
- Internal notes from support team (sender_type: \"Support Team (Internal Note)\")
- Support team messages confirming reproduction
- Technical analysis and root cause identification
- Plugin conflicts and compatibility issues

Only include fields that have actual information. Return valid JSON only.

Example response:
{
  \"reproduction_confirmed\": true,
  \"root_cause\": \"CSS issue with checkbox styling caused by plugin conflict\",
  \"issue_type\": \"CSS conflict\",
  \"symptoms\": [\"checkboxes become unclickable when WP Fusion is activated\"],
  \"conflicting_plugins\": [\"User Menus plugin\", \"WP Fusion\"],
  \"support_analysis\": [\"Issue appears after User Menu update\", \"Working fine few months ago\", \"Inspected elements and confirmed CSS issue\"],
  \"customer_environment\": {\"troubleshooting_method\": \"Health Check plugin used to isolate conflict\"}
}";

        try {
            $response = $this->callAiService($aiService, $aiApiKey, $prompt);
            $diagnosticData = json_decode($response, true);
            
            if (json_last_error() === JSON_ERROR_NONE && is_array($diagnosticData)) {
                return $diagnosticData;
            }
        } catch (\Exception $e) {
            \Log::error('AI diagnostic extraction failed: ' . $e->getMessage());
        }
        
        return null;
    }

    /**
     * Extract technical details from conversation (fallback method)
     */
    private function extractTechnicalDetails($threads)
    {
        // Simple fallback extraction without regex complexity
        $details = [];
        
        foreach ($threads as $thread) {
            $body = strip_tags($thread->body);
            
            // Look for URLs (excluding FreeScout)
            if (preg_match_all('/(https?:\/\/[^\s]+)/i', $body, $matches)) {
                foreach ($matches[1] as $url) {
                    if (!strpos($url, 'freescout') && !strpos($url, 'support.')) {
                        $details[] = "URL mentioned: " . $url;
                    }
                }
            }
        }
        
        return $details ? implode("\n", array_unique($details)) : null;
    }
}