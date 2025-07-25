<?php

namespace Modules\Github\Entities;

use Illuminate\Database\Eloquent\Model;

class GithubLabelMapping extends Model
{
    protected $fillable = [
        'freescout_tag',
        'github_label',
        'repository',
        'confidence_threshold'
    ];

    protected $casts = [
        'confidence_threshold' => 'float'
    ];

    /**
     * Get mappings for a specific repository
     */
    public static function getRepositoryMappings($repository)
    {
        return self::where('repository', $repository)->get();
    }

    /**
     * Get GitHub label for FreeScout tag
     */
    public static function getGithubLabel($freescout_tag, $repository)
    {
        $mapping = self::where('freescout_tag', $freescout_tag)
            ->where('repository', $repository)
            ->first();

        return $mapping ? $mapping->github_label : null;
    }

    /**
     * Create or update mapping
     */
    public static function createOrUpdateMapping($freescout_tag, $github_label, $repository, $confidence_threshold = 0.80)
    {
        return self::updateOrCreate(
            [
                'freescout_tag' => $freescout_tag,
                'repository' => $repository
            ],
            [
                'github_label' => $github_label,
                'confidence_threshold' => $confidence_threshold
            ]
        );
    }

    /**
     * Get all mappings as array for quick lookup
     */
    public static function getMappingsArray($repository)
    {
        return self::where('repository', $repository)
            ->pluck('github_label', 'freescout_tag')
            ->toArray();
    }

    /**
     * Delete mapping
     */
    public static function deleteMapping($freescout_tag, $repository)
    {
        return self::where('freescout_tag', $freescout_tag)
            ->where('repository', $repository)
            ->delete();
    }

    /**
     * Get fuzzy matches for a tag
     */
    public static function getFuzzyMatches($freescout_tag, $repository, $similarity_threshold = 0.6)
    {
        $mappings = self::where('repository', $repository)->get();
        $matches = [];

        foreach ($mappings as $mapping) {
            $similarity = self::calculateSimilarity($freescout_tag, $mapping->freescout_tag);
            if ($similarity >= $similarity_threshold) {
                $matches[] = [
                    'mapping' => $mapping,
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
     * Calculate string similarity
     */
    private static function calculateSimilarity($str1, $str2)
    {
        return similar_text(strtolower($str1), strtolower($str2)) / max(strlen($str1), strlen($str2));
    }
}