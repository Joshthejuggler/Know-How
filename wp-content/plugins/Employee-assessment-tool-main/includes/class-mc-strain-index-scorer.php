<?php
if (!defined('ABSPATH'))
    exit;

class MC_Strain_Index_Scorer
{
    /**
     * Calculate Strain Index from user's existing quiz results.
     *
     * @param int $user_id The user ID to calculate for.
     * @return array|false Result object or false if data is missing.
     */
    public static function calculate_from_user_meta($user_id)
    {
        $mi_results = get_user_meta($user_id, 'miq_quiz_results', true);
        $cdt_results = get_user_meta($user_id, 'cdt_quiz_results', true);
        $bartle_results = get_user_meta($user_id, 'bartle_quiz_results', true);

        // Check if all quizzes are completed (or at least have data)
        if (!$mi_results || !$cdt_results || !$bartle_results) {
            return false;
        }

        // Helper to extract score for a category
        $get_score = function ($results, $cat) {
            $scores = [];
            if (isset($results['part1Scores'])) {
                $scores = $results['part1Scores'];
            } elseif (isset($results['scores'])) {
                $scores = $results['scores'];
            } else {
                $scores = $results;
            }

            if (isset($scores[$cat])) {
                return floatval($scores[$cat]);
            }
            return 0;
        };

        // Sum scores from all 3 quizzes
        // MI has 4 Rumination, 3 Avoidance, 3 Flood
        // CDT has 3 Rumination, 4 Avoidance, 3 Flood
        // Bartle has 4 Rumination, 3 Avoidance, 3 Flood
        // Total: 11 Rumination, 10 Avoidance, 9 Flood

        $raw_rumination = $get_score($mi_results, 'si-rumination') +
            $get_score($cdt_results, 'si-rumination') +
            $get_score($bartle_results, 'si-rumination');

        $raw_avoidance = $get_score($mi_results, 'si-avoidance') +
            $get_score($cdt_results, 'si-avoidance') +
            $get_score($bartle_results, 'si-avoidance');

        $raw_emotional_flood = $get_score($mi_results, 'si-emotional-flood') +
            $get_score($cdt_results, 'si-emotional-flood') +
            $get_score($bartle_results, 'si-emotional-flood');

        // Calculate Normalized Scores
        // Max score per question is 5.
        // Rumination: 11 questions * 5 = 55
        // Avoidance: 10 questions * 5 = 50
        // Flood: 9 questions * 5 = 45

        $max_rumination = 55;
        $max_avoidance = 50;
        $max_flood = 45;

        $norm_rumination = $max_rumination > 0 ? $raw_rumination / $max_rumination : 0;
        $norm_avoidance = $max_avoidance > 0 ? $raw_avoidance / $max_avoidance : 0;
        $norm_flood = $max_flood > 0 ? $raw_emotional_flood / $max_flood : 0;

        // Overall Strain
        $overall = ($norm_rumination + $norm_avoidance + $norm_flood) / 3;

        $result = [
            'strain_index' => [
                'raw_scores' => [
                    'rumination' => $raw_rumination,
                    'avoidance' => $raw_avoidance,
                    'emotional_flood' => $raw_emotional_flood
                ],
                'normalized' => [
                    'rumination' => round($norm_rumination, 4),
                    'avoidance' => round($norm_avoidance, 4),
                    'emotional_flood' => round($norm_flood, 4)
                ],
                'overall_strain' => round($overall, 4),
                'generated_at' => current_time('mysql')
            ]
        ];

        // Save to DB
        global $wpdb;
        $table_name = $wpdb->prefix . 'mc_strain_index_results';
        $wpdb->insert(
            $table_name,
            [
                'user_id' => $user_id,
                'raw_rumination' => $raw_rumination,
                'raw_avoidance' => $raw_avoidance,
                'raw_emotional_flood' => $raw_emotional_flood,
                'norm_rumination' => $norm_rumination,
                'norm_avoidance' => $norm_avoidance,
                'norm_emotional_flood' => $norm_flood,
                'overall_strain' => $overall,
                'payload_json' => json_encode($result),
                'created_at' => current_time('mysql')
            ]
        );

        // Also save to user meta for easy retrieval
        update_user_meta($user_id, 'strain_index_results', $result);

        return $result;
    }
}
