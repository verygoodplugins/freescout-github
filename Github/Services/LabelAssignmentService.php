<?php

namespace Modules\Github\Services;

use App\Conversation;
use Modules\Github\Entities\GithubLabelMapping;

class LabelAssignmentService
{
    /**
     * Assign labels to an issue based on conversation data
     */
    public function assignLabels(Conversation $conversation, array $availableLabels)
    {
        $repository = \Option::get('github.default_repository');
        $assignedLabels = [];

        // Method 1: Try FreeScout tag mapping first
        $tagLabels = $this->mapFreeScoutTags($conversation, $availableLabels, $repository);
        if (is_array($tagLabels)) {
            $assignedLabels = array_merge($assignedLabels, $tagLabels);
        }

        // Method 2: If no tags or insufficient mapping, use AI analysis
        if (empty($assignedLabels) && \Option::get('github.ai_enabled', true)) {
            $aiLabels = $this->analyzeConversationContent($conversation, $availableLabels);
            if (is_array($aiLabels)) {
                $assignedLabels = array_merge($assignedLabels, $aiLabels);
            }
        }

        // Method 3: Apply default labels based on conversation properties
        $defaultLabels = $this->applyDefaultLabels($conversation, $availableLabels);
        if (is_array($defaultLabels)) {
            $assignedLabels = array_merge($assignedLabels, $defaultLabels);
        }

        // Remove duplicates and validate
        $assignedLabels = array_unique($assignedLabels);
        $validatedLabels = $this->validateAndFilterLabels($assignedLabels, $availableLabels);
        
        // Filter based on allowed labels setting
        return $this->filterByAllowedLabels($validatedLabels);
    }

    /**
     * Map FreeScout conversation tags to GitHub labels
     */
    private function mapFreeScoutTags(Conversation $conversation, array $availableLabels, $repository)
    {
        $mappedLabels = [];
        
        // Get conversation tags (if FreeScout supports tagging)
        // Note: This depends on FreeScout's tagging implementation
        $tags = $this->getConversationTags($conversation);
        
        if (empty($tags)) {
            return [];
        }

        // Get available label names for easier matching
        $labelNames = array_column($availableLabels, 'name');

        foreach ($tags as $tag) {
            // Try direct mapping first
            $mapping = GithubLabelMapping::getGithubLabel($tag, $repository);
            if ($mapping && in_array($mapping, $labelNames)) {
                $mappedLabels[] = $mapping;
                continue;
            }

            // Try fuzzy matching
            $fuzzyMatches = $this->findFuzzyMatches($tag, $labelNames);
            if (!empty($fuzzyMatches)) {
                $mappedLabels[] = $fuzzyMatches[0]['label'];
                continue;
            }

            // Try direct name matching (case-insensitive)
            $directMatch = $this->findDirectMatch($tag, $labelNames);
            if ($directMatch) {
                $mappedLabels[] = $directMatch;
            }
        }

        return array_unique($mappedLabels);
    }

    /**
     * Analyze conversation content using AI to suggest labels
     */
    private function analyzeConversationContent(Conversation $conversation, array $availableLabels)
    {
        $aiService = \Option::get('github.ai_service', 'openai');
        $aiApiKey = \Option::get('github.ai_api_key');

        if (!$aiApiKey) {
            return $this->analyzeContentManually($conversation, $availableLabels);
        }

        try {
            switch ($aiService) {
                case 'openai':
                    return $this->analyzeWithOpenAI($conversation, $availableLabels, $aiApiKey);
                case 'claude':
                    return $this->analyzeWithClaude($conversation, $availableLabels, $aiApiKey);
                default:
                    return $this->analyzeContentManually($conversation, $availableLabels);
            }
        } catch (\Exception $e) {
            \Helper::logException($e, '[GitHub] AI Label Analysis Error');
            return $this->analyzeContentManually($conversation, $availableLabels);
        }
    }

    /**
     * Analyze content with OpenAI
     */
    private function analyzeWithOpenAI(Conversation $conversation, array $availableLabels, $apiKey)
    {
        $conversationText = $this->extractConversationText($conversation);
        $labelNames = array_column($availableLabels, 'name');
        
        $prompt = $this->buildLabelPrompt($conversationText, $labelNames);

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
                        'content' => 'You are a helpful assistant that assigns GitHub labels to issues based on conversation content. Always respond with valid JSON containing an array of label names.'
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ]
                ],
                'max_tokens' => 200,
                'temperature' => 0.3
            ])
        ]);

        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if ($httpCode === 200) {
            $data = json_decode($response, true);
            if (isset($data['choices'][0]['message']['content'])) {
                $content = json_decode($data['choices'][0]['message']['content'], true);
                if ($content && isset($content['labels']) && is_array($content['labels'])) {
                    return $content['labels'];
                }
            }
        }

        return $this->analyzeContentManually($conversation, $availableLabels);
    }

    /**
     * Analyze content with Claude
     */
    private function analyzeWithClaude(Conversation $conversation, array $availableLabels, $apiKey)
    {
        $conversationText = $this->extractConversationText($conversation);
        $labelNames = array_column($availableLabels, 'name');
        
        $prompt = $this->buildLabelPrompt($conversationText, $labelNames);

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
                'max_tokens' => 200,
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
                if ($content && isset($content['labels']) && is_array($content['labels'])) {
                    return $content['labels'];
                }
            }
        }

        return $this->analyzeContentManually($conversation, $availableLabels);
    }

    /**
     * Analyze content manually without AI
     */
    private function analyzeContentManually(Conversation $conversation, array $availableLabels)
    {
        $labels = [];
        $labelNames = array_column($availableLabels, 'name');
        
        // Get conversation text
        $text = strtolower($this->extractConversationText($conversation));
        
        // Define keyword patterns for common labels
        $patterns = [
            'bug' => ['bug', 'error', 'issue', 'problem', 'broken', 'not working', 'fail'],
            'enhancement' => ['feature', 'request', 'improve', 'enhancement', 'suggestion', 'better'],
            'question' => ['question', 'how to', 'help', 'support', 'confused', 'understand'],
            'documentation' => ['docs', 'documentation', 'guide', 'tutorial', 'manual'],
            'urgent' => ['urgent', 'asap', 'immediately', 'critical', 'emergency'],
            'high priority' => ['priority', 'important', 'urgent', 'critical'],
            'low priority' => ['minor', 'sometime', 'future', 'nice to have'],
        ];

        // Match patterns to available labels
        foreach ($patterns as $category => $keywords) {
            foreach ($keywords as $keyword) {
                if (strpos($text, $keyword) !== false) {
                    // Find matching label
                    $matchingLabel = $this->findDirectMatch($category, $labelNames);
                    if ($matchingLabel) {
                        $labels[] = $matchingLabel;
                        break;
                    }
                }
            }
        }

        return array_unique($labels);
    }

    /**
     * Apply default labels based on conversation properties
     */
    private function applyDefaultLabels(Conversation $conversation, array $availableLabels)
    {
        $labels = [];
        $labelNames = array_column($availableLabels, 'name');

        // Add status-based labels
        if ($conversation->status === Conversation::STATUS_ACTIVE) {
            $label = $this->findDirectMatch('open', $labelNames);
            if ($label) $labels[] = $label;
        }

        // Add priority-based labels based on conversation age
        $daysSinceCreated = $conversation->created_at->diffInDays(now());
        if ($daysSinceCreated > 7) {
            $label = $this->findDirectMatch('stale', $labelNames);
            if ($label) $labels[] = $label;
        }

        // Add customer-based labels
        if ($conversation->customer) {
            $label = $this->findDirectMatch('customer-request', $labelNames);
            if ($label) $labels[] = $label;
        }

        return array_unique($labels);
    }

    /**
     * Get conversation tags (placeholder for FreeScout tagging system)
     */
    private function getConversationTags(Conversation $conversation)
    {
        // This depends on FreeScout's tagging implementation
        // For now, return empty array - this can be extended when tagging is available
        return [];
    }

    /**
     * Find fuzzy matches for a tag
     */
    private function findFuzzyMatches($tag, array $labelNames, $threshold = 0.6)
    {
        $matches = [];
        
        foreach ($labelNames as $label) {
            $similarity = $this->calculateSimilarity($tag, $label);
            if ($similarity >= $threshold) {
                $matches[] = [
                    'label' => $label,
                    'similarity' => $similarity
                ];
            }
        }

        // Sort by similarity (highest first)
        usort($matches, function ($a, $b) {
            return $b['similarity'] <=> $a['similarity'];
        });

        return $matches;
    }

    /**
     * Find direct match for a tag
     */
    private function findDirectMatch($tag, array $labelNames)
    {
        $tag = strtolower($tag);
        
        foreach ($labelNames as $label) {
            if (strtolower($label) === $tag) {
                return $label;
            }
        }

        return null;
    }

    /**
     * Calculate string similarity
     */
    private function calculateSimilarity($str1, $str2)
    {
        $str1 = strtolower($str1);
        $str2 = strtolower($str2);
        
        return similar_text($str1, $str2) / max(strlen($str1), strlen($str2));
    }

    /**
     * Extract conversation text for analysis
     */
    private function extractConversationText(Conversation $conversation)
    {
        $threads = $conversation->threads()
            ->whereIn('type', [\App\Thread::TYPE_CUSTOMER, \App\Thread::TYPE_MESSAGE])
            ->orderBy('created_at')
            ->limit(5) // Limit to first 5 messages
            ->get();

        $text = $conversation->subject . " ";
        
        foreach ($threads as $thread) {
            $body = strip_tags($thread->body);
            $body = strlen($body) > 200 ? substr($body, 0, 200) . '...' : $body;
            $text .= $body . " ";
        }

        return $text;
    }

    /**
     * Build AI prompt for label assignment
     */
    private function buildLabelPrompt($conversationText, array $labelNames)
    {
        $labelsJson = json_encode($labelNames);
        
        return "Analyze this customer support conversation and assign appropriate GitHub labels.

Available labels: $labelsJson

Conversation:
$conversationText

Instructions:
1. Assign 1-3 most relevant labels from the available list
2. Consider the problem type (bug, feature request, question, etc.)
3. Consider priority/urgency indicators
4. Consider technical context

Respond with valid JSON in this format:
{
  \"labels\": [\"label1\", \"label2\"]
}";
    }

    /**
     * Validate and filter labels
     */
    private function validateAndFilterLabels(array $labels, array $availableLabels)
    {
        $validLabels = [];
        $labelNames = array_column($availableLabels, 'name');

        foreach ($labels as $label) {
            if (in_array($label, $labelNames)) {
                $validLabels[] = $label;
            }
        }

        // Limit to maximum 5 labels
        return array_slice(array_unique($validLabels), 0, 5);
    }

    /**
     * Filter labels based on allowed labels setting
     */
    private function filterByAllowedLabels(array $labels)
    {
        // Get allowed labels setting
        $allowedLabelsJson = \Option::get('github.allowed_labels', '[]');
        
        // Handle case where the setting might already be an array or a JSON string
        if (is_array($allowedLabelsJson)) {
            $allowedLabels = $allowedLabelsJson;
        } else {
            $allowedLabels = json_decode($allowedLabelsJson, true);
        }
        
        // Ensure we have a valid array
        if (!is_array($allowedLabels)) {
            $allowedLabels = [];
        }
        
        // If no allowed labels are configured, allow all (backward compatibility)
        if (empty($allowedLabels)) {
            return $labels;
        }

        // Filter labels to only include allowed ones
        $originalCount = count($labels);
        $filteredLabels = array_values(array_intersect($labels, $allowedLabels));
        $filteredCount = count($filteredLabels);
        
        if ($originalCount !== $filteredCount) {
            \Helper::log('github_labels', 'Filtered assigned labels: ' . $originalCount . ' -> ' . $filteredCount . ' (removed ' . ($originalCount - $filteredCount) . ' disallowed labels)');
        }

        return $filteredLabels;
    }
}