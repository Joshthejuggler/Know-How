<?php
if (!defined('ABSPATH')) exit;

/**
 * Class MC_Culture_Scorecard
 *
 * Handles the Culture Average Scorecard feature:
 * - Persisting employee selections (array-based, multi-scorecard-ready)
 * - Dynamically computing aggregate CDT + Bartle profiles
 * - Calculating per-candidate culture fit (CDT alignment score + Bartle type fit)
 * - Providing AJAX endpoints consumed by the admin UI
 */
class MC_Culture_Scorecard {

    // ─── Dimension labels ──────────────────────────────────────────────────────

    const CDT_LABELS = [
        'ambiguity-tolerance'         => 'Ambiguity Tolerance',
        'value-conflict-navigation'   => 'Value Conflict Navigation',
        'self-confrontation-capacity' => 'Self-Confrontation Capacity',
        'discomfort-regulation'       => 'Discomfort Regulation',
        'growth-orientation'          => 'Growth Orientation',
    ];

    const BARTLE_LABELS = [
        'explorer'   => 'Explorer',
        'achiever'   => 'Achiever',
        'socializer' => 'Socializer',
        'strategist' => 'Strategist',
    ];

    const MI_LABELS = [
        'linguistic'           => 'Linguistic',
        'logical-mathematical' => 'Logical-Mathematical',
        'spatial'              => 'Spatial',
        'bodily-kinesthetic'   => 'Bodily-Kinesthetic',
        'musical'              => 'Musical',
        'interpersonal'        => 'Interpersonal',
        'intrapersonal'        => 'Intrapersonal',
        'naturalistic'         => 'Naturalistic',
    ];

    const MI_RECOMMENDATION_LIBRARY = [
        'linguistic' => [
            'why_it_matters' => 'Teams with strong linguistic intelligence tend to connect through conversation, storytelling, and shared language. Activities that invite dialogue and expression usually feel energizing rather than forced.',
            'activities' => [
                'Host a team storytelling dinner where people share career moments, wins, or lessons learned.',
                'Book a spoken-word event, author talk, or live theatre outing as a culture activity.',
                'Run a collaborative book, podcast, or article club tied to your team values or industry themes.',
            ],
        ],
        'logical-mathematical' => [
            'why_it_matters' => 'Teams high in logical-mathematical intelligence often enjoy structure, puzzles, systems, and strategic problem solving. The best activities give them something meaningful to crack together.',
            'activities' => [
                'Plan an escape room, strategy challenge, or team problem-solving event.',
                'Run a data-themed team challenge or innovation sprint with a clear scorecard and goals.',
                'Book a workshop built around design thinking, analytics, or systems mapping.',
            ],
        ],
        'spatial' => [
            'why_it_matters' => 'Teams with strong spatial intelligence usually respond well to visual environments, design-forward experiences, and activities that involve making, imagining, or arranging things together.',
            'activities' => [
                'Book a collaborative art, pottery, or design workshop for the team.',
                'Run a visual planning retreat using whiteboards, journey maps, and mood boards.',
                'Choose an immersive exhibit, gallery event, or architecture-focused outing.',
            ],
        ],
        'bodily-kinesthetic' => [
            'why_it_matters' => 'Teams high in bodily-kinesthetic intelligence often bond through movement, hands-on action, and experiences that feel active rather than passive.',
            'activities' => [
                'Organize an active team outing such as bowling, climbing, curling, or a sports-based event.',
                'Plan a volunteer build day or hands-on service activity where the team can make something tangible.',
                'Choose an interactive workshop like cooking, woodworking, or maker-space collaboration.',
            ],
        ],
        'musical' => [
            'why_it_matters' => 'Teams with strong musical intelligence often pick up on rhythm, tone, and atmosphere quickly. Shared experiences with sound, cadence, and live energy can create a strong culture memory.',
            'activities' => [
                'Book a concert, live music night, or local festival outing.',
                'Create a collaborative team playlist for milestones, wins, or events.',
                'Plan a rhythm, drumming, or music-based workshop as a team-building experience.',
            ],
        ],
        'interpersonal' => [
            'why_it_matters' => 'Teams high in interpersonal intelligence usually thrive on connection, collaboration, and relationship-building. The strongest recommendations help people mix, talk, and create social trust.',
            'activities' => [
                'Host a dinner, off-site social, or facilitated mixer designed around real conversation.',
                'Plan a team volunteering day with shared responsibilities and community interaction.',
                'Run a workshop focused on collaboration, communication, or peer appreciation.',
            ],
        ],
        'intrapersonal' => [
            'why_it_matters' => 'Teams with strong intrapersonal intelligence often value reflection, self-awareness, and meaningful experiences over loud or highly stimulating events. The best activities give space for insight as well as connection.',
            'activities' => [
                'Plan a retreat with guided reflection, journaling, and small-group discussion.',
                'Choose a quiet wellness activity such as a nature retreat, mindfulness session, or restorative off-site.',
                'Create a values and strengths workshop that helps the team reflect on how they work best together.',
            ],
        ],
        'naturalistic' => [
            'why_it_matters' => 'Teams high in naturalistic intelligence often respond well to outdoor settings, ecosystems thinking, and experiences that feel grounded in the real world. Fresh-air activities can feel especially natural for this mix.',
            'activities' => [
                'Organize an outdoor team outing such as a fishing trip, hike, or park-based off-site.',
                'Plan a conservation, gardening, or community clean-up activity with a shared purpose.',
                'Choose a nature-linked experience such as camping, canoeing, or a guided outdoor excursion.',
            ],
        ],
    ];

    // ─── CDT friction scenario map ─────────────────────────────────────────────
    // Each dimension has text for [below_team, above_team]
    const CDT_SCENARIOS = [
        'ambiguity-tolerance' => [
            'below' => 'May experience friction in roles with unclear direction, shifting scope, or open-ended briefs. Likely benefits from more explicit structure than the team typically provides.',
            'above' => 'Comfortable in ambiguity where the team may want more clarity. Well-positioned to lead on open-ended or exploratory initiatives.',
        ],
        'value-conflict-navigation' => [
            'below' => 'May find grey-area decisions or ethical tradeoffs more taxing than team norms suggest. Coaching on decision frameworks could support integration.',
            'above' => 'Strong ethical antenna — may surface tensions others overlook. This can be an asset in cultures that value integrity, or a source of friction if the team rarely names these tensions.',
        ],
        'self-confrontation-capacity' => [
            'below' => 'Feedback cycles may feel more intense if the team norm includes direct self-reflection. Coaching approach and psychological safety matter during onboarding.',
            'above' => 'Highly self-aware; may actively push for honest retrospectives the team isn\'t accustomed to. Consider how this surfaces in leadership meetings early on.',
        ],
        'discomfort-regulation' => [
            'below' => 'Likely to experience higher stress during high-pressure cycles (e.g., board prep, close periods) if the team typically stays regulated under pressure.',
            'above' => 'Strong under fire — may underestimate the stress signals of others. Worth surfacing team pressure norms explicitly during onboarding.',
        ],
        'growth-orientation' => [
            'below' => 'May need more explicit growth structures (defined goals, regular reviews) than the team typically requires. Consider making growth expectations visible early.',
            'above' => 'Highly motivated, self-directed learner — may outpace the available growth opportunities in the role. Worth discussing trajectory and development path during the offer conversation.',
        ],
    ];

    // ─── Bartle descriptions for PDF narrative ─────────────────────────────────
    const BARTLE_DESCRIPTIONS = [
        'explorer'   => 'motivated by discovering new systems, exploring ideas, and learning for its own sake',
        'achiever'   => 'motivated by setting measurable goals, tracking progress, and mastering skills',
        'socializer' => 'motivated by relationships, collaboration, and contributing to a team or community',
        'strategist' => 'motivated by competition, high-stakes challenges, and proving their skills against others',
    ];

    // ─── Init ──────────────────────────────────────────────────────────────────

    public static function init() {
        add_action('wp_ajax_mc_get_scorecard',   [__CLASS__, 'ajax_get_scorecard']);
        add_action('wp_ajax_mc_save_selection',  [__CLASS__, 'ajax_save_selection']);
        add_action('wp_ajax_mc_get_culture_fit', [__CLASS__, 'ajax_get_culture_fit']);
        add_action('wp_ajax_mc_generate_candidate_pdf', [__CLASS__, 'ajax_generate_candidate_pdf']);
        
        // Scorecard Management CRUD
        add_action('wp_ajax_mc_create_scorecard', [__CLASS__, 'ajax_create_scorecard']);
        add_action('wp_ajax_mc_rename_scorecard', [__CLASS__, 'ajax_rename_scorecard']);
        add_action('wp_ajax_mc_delete_scorecard', [__CLASS__, 'ajax_delete_scorecard']);
        add_action('wp_ajax_mc_get_scorecards',   [__CLASS__, 'ajax_get_scorecards_list']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // SECTION 1: DATA PERSISTENCE
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Get all scorecards for an employer.
     * Returns the array of scorecards, initialising with a default if none exist.
     */
    public static function get_scorecards($employer_id) {
        $scorecards = get_user_meta($employer_id, 'mc_culture_scorecards_' . $employer_id, true);

        if (empty($scorecards) || !is_array($scorecards)) {
            // Bootstrap the default single scorecard
            $scorecards = [
                [
                    'id'                => 'scorecard_1',
                    'label'             => 'Full Company Baseline',
                    'last_selected_ids' => [],
                    'report_config'     => [
                        'framing'      => 'executive',
                        'cdt_low_flag' => 40,
                    ],
                    'created_at'  => time(),
                    'updated_at'  => time(),
                ]
            ];
            update_user_meta($employer_id, 'mc_culture_scorecards_' . $employer_id, $scorecards);
        }

        return $scorecards;
    }

    /**
     * Save an updated selection for a specific scorecard.
     */
    public static function save_selection($employer_id, $scorecard_id, $selected_ids) {
        $scorecards = self::get_scorecards($employer_id);

        foreach ($scorecards as &$sc) {
            if ($sc['id'] === $scorecard_id) {
                $sc['last_selected_ids'] = array_map('intval', $selected_ids);
                $sc['updated_at']        = time();
                break;
            }
        }
        unset($sc);

        update_user_meta($employer_id, 'mc_culture_scorecards_' . $employer_id, $scorecards);
        return true;
    }

    /**
     * Find a single scorecard by ID from the employer's list.
     */
    public static function get_scorecard_by_id($employer_id, $scorecard_id) {
        $scorecards = self::get_scorecards($employer_id);
        foreach ($scorecards as $sc) {
            if ($sc['id'] === $scorecard_id) return $sc;
        }
        // Fallback: return first scorecard
        return $scorecards[0] ?? null;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // SECTION 2: SCORECARD COMPUTATION ENGINE
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Compute the full Culture Scorecard from an array of employee user IDs.
     * Returns MI, CDT, and Bartle averages plus composition metadata.
     */
    public static function compute_scorecard($employee_ids) {
        if (empty($employee_ids)) {
            return null;
        }

        $mi_totals     = array_fill_keys(array_keys(self::MI_LABELS),     0);
        $cdt_totals    = array_fill_keys(array_keys(self::CDT_LABELS),    0);
        $bartle_totals = array_fill_keys(array_keys(self::BARTLE_LABELS), 0);
        $bartle_dominant_counts = array_fill_keys(array_keys(self::BARTLE_LABELS), 0);
        $member_dominant_types  = [];
        $mi_valid_count = 0;
        $valid_count = 0;

        foreach ($employee_ids as $uid) {
            $uid   = intval($uid);
            $mi    = get_user_meta($uid, 'miq_quiz_results',     true);
            $cdt   = get_user_meta($uid, 'cdt_quiz_results',    true);
            $bartle = get_user_meta($uid, 'bartle_quiz_results', true);

            if (empty($cdt) || empty($bartle)) continue;
            $valid_count++;

            if (!empty($mi['part1Scores'])) {
                $mi_valid_count++;
                foreach ($mi['part1Scores'] as $slug => $raw) {
                    if (isset($mi_totals[$slug])) {
                        $mi_totals[$slug] += round($raw / 40 * 100);
                    }
                }
            }

            // --- CDT scores (max 50 per dimension → normalise to 0-100) ---
            if (!empty($cdt['sortedScores'])) {
                foreach ($cdt['sortedScores'] as $pair) {
                    $slug = $pair[0];
                    $raw  = $pair[1];
                    if (isset($cdt_totals[$slug])) {
                        $cdt_totals[$slug] += round($raw / 50 * 100);
                    }
                }
            }

            // --- Bartle scores (max 50 → normalise to 0-100) ---
            $bartle_scores = [];
            if (!empty($bartle['sortedScores'])) {
                foreach ($bartle['sortedScores'] as $pair) {
                    $slug = $pair[0];
                    $raw  = $pair[1];
                    if (isset($bartle_totals[$slug])) {
                        $bartle_totals[$slug] += round($raw / 50 * 100);
                        $bartle_scores[$slug]  = round($raw / 50 * 100);
                    }
                }
            }

            // Determine dominant Bartle type
            if (!empty($bartle_scores)) {
                arsort($bartle_scores);
                $dominant = array_key_first($bartle_scores);
                $bartle_dominant_counts[$dominant]++;
                $user_info = get_userdata($uid);
                $member_dominant_types[] = [
                    'id'   => $uid,
                    'name' => $user_info ? $user_info->display_name : 'Team Member',
                    'type' => $dominant,
                ];
            }
        }

        if ($valid_count === 0) {
            return ['error' => 'No employees with complete CDT and Bartle data.'];
        }

        // --- Compute averages ---
        $mi_averages = [];
        if ($mi_valid_count > 0) {
            foreach ($mi_totals as $slug => $total) {
                $mi_averages[$slug] = round($total / $mi_valid_count, 1);
            }
            $mi_averages = MC_Helpers::apply_ipsative($mi_averages);
        } else {
            foreach ($mi_totals as $slug => $total) {
                $mi_averages[$slug] = 0;
            }
        }

        $cdt_averages    = [];
        foreach ($cdt_totals as $slug => $total) {
            $cdt_averages[$slug] = round($total / $valid_count, 1);
        }
        $cdt_averages = MC_Helpers::apply_ipsative($cdt_averages);

        $bartle_averages = [];
        foreach ($bartle_totals as $slug => $total) {
            $bartle_averages[$slug] = round($total / $valid_count, 1);
        }
        $bartle_averages = MC_Helpers::apply_ipsative($bartle_averages);

        // --- Compute Player Type distribution ---
        $distribution = [];
        foreach ($bartle_dominant_counts as $slug => $count) {
            $pct    = round($count / $valid_count * 100);
            $status = self::get_blind_spot_status($count, $valid_count);
            $distribution[$slug] = [
                'count'  => $count,
                'pct'    => $pct,
                'status' => $status,
            ];
        }

        // --- Build blind spots list (absent or under-represented) ---
        $blind_spots = [];
        foreach ($distribution as $slug => $data) {
            if (in_array($data['status'], ['absent', 'under-represented'])) {
                $blind_spots[] = [
                    'type'           => $slug,
                    'label'          => self::BARTLE_LABELS[$slug],
                    'status'         => $data['status'],
                    'threshold_note' => $data['count'] . ' of ' . $valid_count . ' members',
                ];
            }
        }

        $mi_recommendation_bundle = self::build_mi_recommendation_bundle($mi_averages, $valid_count);

        return [
            'mi'                     => $mi_averages,
            'cdt'                    => $cdt_averages,
            'bartle'                 => $bartle_averages,
            'adaptability'           => self::compute_scorecard_adaptability($employee_ids),
            'mi_recommendation_intro' => $mi_recommendation_bundle['intro'],
            'mi_recommendations'      => $mi_recommendation_bundle['recommendations'],
            'player_type_distribution' => $distribution,
            'member_dominant_types'  => $member_dominant_types,
            'blind_spots'            => $blind_spots,
            'member_count'           => $valid_count,
            'mi_member_count'        => $mi_valid_count,
            'computed_at'            => time(),
        ];
    }

    private static function get_top_mi_dimensions($mi_averages, $limit = 3) {
        if (empty($mi_averages) || !is_array($mi_averages)) {
            return [];
        }

        $filtered = array_filter($mi_averages, function ($score, $slug) {
            return isset(self::MI_LABELS[$slug]) && floatval($score) > 0;
        }, ARRAY_FILTER_USE_BOTH);

        arsort($filtered);
        return array_slice($filtered, 0, $limit, true);
    }

    private static function build_mi_recommendation_intro_fallback($top_mi, $member_count) {
        if (empty($top_mi)) {
            return '';
        }

        $labels = array_map(function ($slug) {
            return self::MI_LABELS[$slug] ?? ucfirst(str_replace('-', ' ', $slug));
        }, array_keys($top_mi));

        if (count($labels) === 1) {
            $mix_text = $labels[0];
        } elseif (count($labels) === 2) {
            $mix_text = $labels[0] . ' and ' . $labels[1];
        } else {
            $mix_text = $labels[0] . ', ' . $labels[1] . ', and ' . $labels[2];
        }

        return "This scorecard's strongest collective intelligences are {$mix_text}. For a {$member_count}-person team, culture activities will land best when they reinforce how this group naturally connects, thinks, and recharges together.";
    }

    private static function maybe_generate_ai_mi_recommendation_copy($top_mi, $base_recommendations, $member_count) {
        if (empty($top_mi) || empty($base_recommendations) || !class_exists('Micro_Coach_AI')) {
            return null;
        }

        $api_key = Micro_Coach_AI::get_openai_api_key();
        if (empty($api_key)) {
            return null;
        }

        $cache_payload = [
            'member_count' => intval($member_count),
            'top_mi' => $top_mi,
        ];
        $cache_key = 'mc_mi_mix_' . md5(wp_json_encode($cache_payload));
        $cached = get_transient($cache_key);
        if (is_array($cached)) {
            return $cached;
        }

        $prompt_payload = [
            'member_count' => intval($member_count),
            'top_intelligences' => array_map(function ($slug, $score) use ($base_recommendations) {
                return [
                    'slug' => $slug,
                    'label' => self::MI_LABELS[$slug] ?? $slug,
                    'score' => round(floatval($score), 1),
                    'baseline_summary' => $base_recommendations[$slug]['why_it_matters'] ?? '',
                    'activities' => $base_recommendations[$slug]['activities'] ?? [],
                ];
            }, array_keys($top_mi), array_values($top_mi)),
        ];

        $system = "You write concise employer-facing team culture recommendations based on a team's top multiple intelligences.\n" .
            "Return only valid JSON with this shape:\n" .
            "{\n" .
            "  \"intro\": \"2 sentences max\",\n" .
            "  \"rationales\": {\n" .
            "    \"slug\": \"2 sentences max\"\n" .
            "  }\n" .
            "}\n" .
            "Guidelines:\n" .
            "- Keep the tone practical, specific, and upbeat.\n" .
            "- Do not mention psychology, diagnostics, or therapy.\n" .
            "- Do not invent activities; only explain why the curated activities fit this team's mix.\n" .
            "- Make each rationale distinct and tied to the intelligence in question.";

        $response = wp_remote_post('https://api.openai.com/v1/chat/completions', [
            'timeout' => 12,
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode([
                'model' => Micro_Coach_AI::get_selected_model(),
                'messages' => [
                    ['role' => 'system', 'content' => $system],
                    ['role' => 'user', 'content' => wp_json_encode($prompt_payload)],
                ],
                'temperature' => 0.4,
                'response_format' => ['type' => 'json_object'],
            ]),
        ]);

        if (is_wp_error($response)) {
            return null;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        $content = $body['choices'][0]['message']['content'] ?? '';
        if (!is_string($content) || $content === '') {
            return null;
        }

        $parsed = json_decode($content, true);
        if (!is_array($parsed)) {
            return null;
        }

        $result = [
            'intro' => sanitize_textarea_field($parsed['intro'] ?? ''),
            'rationales' => [],
        ];

        foreach (($parsed['rationales'] ?? []) as $slug => $text) {
            $slug = sanitize_key($slug);
            if (!isset(self::MI_LABELS[$slug])) {
                continue;
            }
            $result['rationales'][$slug] = sanitize_textarea_field($text);
        }

        set_transient($cache_key, $result, 12 * HOUR_IN_SECONDS);
        return $result;
    }

    private static function build_mi_recommendation_bundle($mi_averages, $member_count) {
        $top_mi = self::get_top_mi_dimensions($mi_averages, 3);
        if (empty($top_mi)) {
            return [
                'intro' => '',
                'recommendations' => [],
            ];
        }

        $base = [];
        foreach ($top_mi as $slug => $score) {
            $library = self::MI_RECOMMENDATION_LIBRARY[$slug] ?? null;
            if (!$library) {
                continue;
            }

            $base[$slug] = [
                'slug' => $slug,
                'label' => self::MI_LABELS[$slug] ?? ucfirst(str_replace('-', ' ', $slug)),
                'score' => round(floatval($score), 1),
                'why_it_matters' => $library['why_it_matters'],
                'activities' => array_slice($library['activities'], 0, 3),
                'ai_rationale' => null,
                'source' => 'curated-fallback',
            ];
        }

        $ai_copy = self::maybe_generate_ai_mi_recommendation_copy($top_mi, $base, $member_count);
        $intro = self::build_mi_recommendation_intro_fallback($top_mi, $member_count);
        if (is_array($ai_copy) && !empty($ai_copy['intro'])) {
            $intro = $ai_copy['intro'];
        }

        if (is_array($ai_copy)) {
            foreach ($base as $slug => &$item) {
                if (!empty($ai_copy['rationales'][$slug])) {
                    $item['ai_rationale'] = $ai_copy['rationales'][$slug];
                    $item['source'] = 'hybrid-ai';
                }
            }
            unset($item);
        }

        return [
            'intro' => $intro,
            'recommendations' => array_values($base),
        ];
    }

    private static function normalize_mi_scores($mi_raw) {
        $scores = [];
        if (empty($mi_raw['part1Scores']) || !is_array($mi_raw['part1Scores'])) {
            return $scores;
        }

        foreach ($mi_raw['part1Scores'] as $slug => $raw) {
            if (isset(self::MI_LABELS[$slug])) {
                $scores[$slug] = round($raw / 40 * 100);
            }
        }

        return !empty($scores) ? MC_Helpers::apply_ipsative($scores) : [];
    }

    private static function normalize_cdt_scores($cdt_raw) {
        $scores = [];
        if (empty($cdt_raw['sortedScores']) || !is_array($cdt_raw['sortedScores'])) {
            return $scores;
        }

        foreach ($cdt_raw['sortedScores'] as $pair) {
            $slug = $pair[0] ?? '';
            $raw  = $pair[1] ?? null;
            if (isset(self::CDT_LABELS[$slug]) && $raw !== null) {
                $scores[$slug] = round($raw / 50 * 100);
            }
        }

        return !empty($scores) ? MC_Helpers::apply_ipsative($scores) : [];
    }

    private static function normalize_bartle_scores($bartle_raw) {
        $scores = [];
        if (empty($bartle_raw['sortedScores']) || !is_array($bartle_raw['sortedScores'])) {
            return $scores;
        }

        foreach ($bartle_raw['sortedScores'] as $pair) {
            $slug = $pair[0] ?? '';
            $raw  = $pair[1] ?? null;
            if (isset(self::BARTLE_LABELS[$slug]) && $raw !== null) {
                $scores[$slug] = round($raw / 50 * 100);
            }
        }

        return !empty($scores) ? MC_Helpers::apply_ipsative($scores) : [];
    }

    private static function build_dimension_comparisons($labels, $scorecard_scores, $candidate_scores, $mode = 'alignment', $scenarios = []) {
        $comparisons = [];

        foreach ($labels as $slug => $label) {
            $team_score      = round(floatval($scorecard_scores[$slug] ?? 0), 1);
            $candidate_score = round(floatval($candidate_scores[$slug] ?? 0), 1);
            $gap             = round(abs($team_score - $candidate_score), 1);
            $direction       = $candidate_score < $team_score ? 'below' : ($candidate_score > $team_score ? 'above' : 'aligned');

            if ($mode === 'friction') {
                if ($gap >= 30) {
                    $status = 'high';
                    $band_label = 'High friction';
                } elseif ($gap >= 15) {
                    $status = 'moderate';
                    $band_label = 'Moderate friction';
                } else {
                    $status = 'low';
                    $band_label = 'Low friction';
                }
            } else {
                if ($gap < 15) {
                    $status = 'high';
                    $band_label = 'High alignment';
                } elseif ($gap < 30) {
                    $status = 'moderate';
                    $band_label = 'Moderate alignment';
                } else {
                    $status = 'low';
                    $band_label = 'Low alignment';
                }
            }

            $comparison = [
                'slug'            => $slug,
                'label'           => $label,
                'team_score'      => $team_score,
                'candidate_score' => $candidate_score,
                'gap'             => $gap,
                'direction'       => $direction,
                'status'          => $status,
                'band_label'      => $band_label,
            ];

            if (!empty($scenarios[$slug]) && isset($scenarios[$slug][$direction])) {
                $comparison['scenario'] = $scenarios[$slug][$direction];
            }

            $comparisons[] = $comparison;
        }

        return $comparisons;
    }

    private static function summarize_adaptability_score($display_score) {
        $status = 'developing';
        $label = 'Developing Adaptability';
        $summary = 'This profile suggests adaptability may depend more heavily on structure, pacing, and support in demanding conditions.';

        if ($display_score >= 67) {
            $status = 'high';
            $label = 'High Adaptability';
            $summary = 'This profile suggests the candidate typically stays flexible, decisive, and composed when demands increase.';
        } elseif ($display_score >= 34) {
            $status = 'moderate';
            $label = 'Moderate Adaptability';
            $summary = 'This profile suggests solid adaptability with a few pressure points that may benefit from coaching or environmental support.';
        }

        return [
            'status' => $status,
            'label' => $label,
            'summary' => $summary,
        ];
    }

    private static function build_adaptability_index_from_results($strain_results) {
        if (empty($strain_results['strain_index'])) {
            return null;
        }

        $si = $strain_results['strain_index'];
        $overall_raw = floatval($si['overall_strain'] ?? 0);
        $display_score = round((1 - $overall_raw) * 100, 1);
        $display_score = max(0, min(100, $display_score));

        $score_summary = self::summarize_adaptability_score($display_score);

        $normalized = $si['normalized'] ?? [];
        $sub_indices = [
            'rumination' => [
                'label' => 'Processing Flexibility',
                'description' => 'How easily the person can stay mentally flexible, shift perspective, and avoid getting stuck replaying the same problem.',
                'raw_score' => round(floatval($normalized['rumination'] ?? 0) * 100, 1),
                'display_score' => round((1 - floatval($normalized['rumination'] ?? 0)) * 100, 1),
            ],
            'avoidance' => [
                'label' => 'Decision Clarity',
                'description' => 'How readily the person can move toward decisions instead of stalling, deferring, or circling around difficult choices.',
                'raw_score' => round(floatval($normalized['avoidance'] ?? 0) * 100, 1),
                'display_score' => round((1 - floatval($normalized['avoidance'] ?? 0)) * 100, 1),
            ],
            'emotional_flood' => [
                'label' => 'Composure Under Pressure',
                'description' => 'How well the person is likely to stay steady, focused, and functional when pressure or emotional intensity rises.',
                'raw_score' => round(floatval($normalized['emotional_flood'] ?? 0) * 100, 1),
                'display_score' => round((1 - floatval($normalized['emotional_flood'] ?? 0)) * 100, 1),
            ],
        ];

        return [
            'score' => $display_score,
            'raw_score' => round($overall_raw * 100, 1),
            'band' => [
                'status' => $score_summary['status'],
                'label' => $score_summary['label'],
            ],
            'summary' => $score_summary['summary'],
            'sub_indices' => $sub_indices,
            'raw_scores' => $si['raw_scores'] ?? [],
            'generated_at' => $si['generated_at'] ?? '',
        ];
    }

    private static function build_adaptability_index($candidate_id) {
        $strain_results = get_user_meta($candidate_id, 'strain_index_results', true);

        if (empty($strain_results) && class_exists('MC_Strain_Index_Scorer')) {
            $strain_results = MC_Strain_Index_Scorer::calculate_from_user_meta($candidate_id);
        }

        return self::build_adaptability_index_from_results($strain_results);
    }

    private static function compute_scorecard_adaptability($user_ids) {
        $overall_total = 0;
        $sub_totals = [
            'rumination' => 0,
            'avoidance' => 0,
            'emotional_flood' => 0,
        ];
        $count = 0;

        foreach ($user_ids as $uid) {
            $strain_results = get_user_meta($uid, 'strain_index_results', true);
            if (empty($strain_results) && class_exists('MC_Strain_Index_Scorer')) {
                $strain_results = MC_Strain_Index_Scorer::calculate_from_user_meta($uid);
            }

            $adapt = self::build_adaptability_index_from_results($strain_results);
            if (empty($adapt)) {
                continue;
            }

            $count++;
            $overall_total += floatval($adapt['score'] ?? 0);
            $sub_totals['rumination'] += floatval($adapt['sub_indices']['rumination']['display_score'] ?? 0);
            $sub_totals['avoidance'] += floatval($adapt['sub_indices']['avoidance']['display_score'] ?? 0);
            $sub_totals['emotional_flood'] += floatval($adapt['sub_indices']['emotional_flood']['display_score'] ?? 0);
        }

        if ($count === 0) {
            return null;
        }

        $overall_score = round($overall_total / $count, 1);
        $summary = self::summarize_adaptability_score($overall_score);

        return [
            'score' => $overall_score,
            'member_count' => $count,
            'band' => [
                'status' => $summary['status'],
                'label' => $summary['label'],
            ],
            'summary' => $summary['summary'],
            'sub_indices' => [
                'rumination' => [
                    'label' => 'Processing Flexibility',
                    'description' => 'How easily the group tends to stay mentally flexible, shift perspective, and avoid getting stuck replaying the same problem.',
                    'display_score' => round($sub_totals['rumination'] / $count, 1),
                ],
                'avoidance' => [
                    'label' => 'Decision Clarity',
                    'description' => 'How readily the group tends to move toward decisions instead of stalling, deferring, or circling around difficult choices.',
                    'display_score' => round($sub_totals['avoidance'] / $count, 1),
                ],
                'emotional_flood' => [
                    'label' => 'Composure Under Pressure',
                    'description' => 'How well the group tends to stay steady, focused, and functional when pressure or emotional intensity rises.',
                    'display_score' => round($sub_totals['emotional_flood'] / $count, 1),
                ],
            ],
        ];
    }

    /**
     * Map a count + team size to a blind spot status label.
     */
    private static function get_blind_spot_status($count, $total) {
        if ($count === 0)                                       return 'absent';
        if ($count === 1)                                       return 'under-represented';
        if ($count >= 4 || ($total > 0 && $count / $total >= 0.4)) return 'dominant';
        return 'present';
    }

    // ─────────────────────────────────────────────────────────────────────────
    // SECTION 3: CULTURE FIT ENGINE
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Calculate culture fit for a single candidate against a computed scorecard.
     *
     * @param int   $candidate_id   WP user ID of the candidate
     * @param array $scorecard      Output from compute_scorecard()
     * @param array $report_config  Per-scorecard config (framing, cdt_low_flag)
     * @return array|null
     */
    public static function calculate_culture_fit($candidate_id, $scorecard, $report_config = []) {
        $mi_raw     = get_user_meta($candidate_id, 'miq_quiz_results',     true);
        $cdt_raw    = get_user_meta($candidate_id, 'cdt_quiz_results',    true);
        $bartle_raw = get_user_meta($candidate_id, 'bartle_quiz_results', true);

        if (empty($cdt_raw) || empty($bartle_raw)) {
            return ['error' => 'Candidate has not completed CDT and/or Bartle assessments.'];
        }

        $framing      = $report_config['framing']      ?? 'executive';
        $cdt_low_flag = $report_config['cdt_low_flag'] ?? 40;

        $candidate_mi = self::normalize_mi_scores($mi_raw);

        // --- Build candidate CDT scores (0-100) ---
        $candidate_cdt = self::normalize_cdt_scores($cdt_raw);

        // --- Build candidate Bartle scores (0-100) ---
        $candidate_bartle = self::normalize_bartle_scores($bartle_raw);

        // ── CDT Gap Analysis ──────────────────────────────────────────────────
        $cdt_gaps         = [];
        $alignment_sum    = 0;
        $alignment_count  = 0;

        foreach (self::CDT_LABELS as $slug => $label) {
            $team_score      = $scorecard['cdt'][$slug] ?? 0;
            $candidate_score = $candidate_cdt[$slug]    ?? 0;
            $gap             = abs($team_score - $candidate_score);
            $direction       = $candidate_score < $team_score ? 'below' : 'above';

            $friction = 'low';
            if ($gap >= 30)      $friction = 'high';
            elseif ($gap >= 15)  $friction = 'moderate';

            $scenario = self::CDT_SCENARIOS[$slug][$direction] ?? '';

            $cdt_gaps[$slug] = [
                'label'           => $label,
                'team_score'      => $team_score,
                'candidate_score' => $candidate_score,
                'gap'             => $gap,
                'direction'       => $direction,
                'friction'        => $friction,
                'scenario'        => $scenario,
            ];

            $alignment_sum   += (100 - $gap);
            $alignment_count++;
        }

        $cdt_alignment_score = $alignment_count > 0
            ? round($alignment_sum / $alignment_count)
            : 0;

        $cdt_low_alert = $cdt_alignment_score < $cdt_low_flag;
        $mi_comparisons = self::build_dimension_comparisons(self::MI_LABELS, $scorecard['mi'] ?? [], $candidate_mi, 'alignment');
        $cdt_comparisons = self::build_dimension_comparisons(self::CDT_LABELS, $scorecard['cdt'] ?? [], $candidate_cdt, 'friction', self::CDT_SCENARIOS);
        $bartle_comparisons = self::build_dimension_comparisons(self::BARTLE_LABELS, $scorecard['bartle'] ?? [], $candidate_bartle, 'alignment');
        $adaptability_index = self::build_adaptability_index($candidate_id);
        if (!empty($adaptability_index)) {
            $adaptability_index['scorecard_average'] = $scorecard['adaptability']['score'] ?? null;
            $adaptability_index['scorecard_band'] = $scorecard['adaptability']['band'] ?? null;
            $adaptability_index['scorecard_member_count'] = $scorecard['adaptability']['member_count'] ?? null;

            foreach (array_keys($adaptability_index['sub_indices'] ?? []) as $sub_key) {
                $adaptability_index['sub_indices'][$sub_key]['scorecard_average'] =
                    $scorecard['adaptability']['sub_indices'][$sub_key]['display_score'] ?? null;
            }
        }

        // ── Bartle Type Fit ───────────────────────────────────────────────────
        arsort($candidate_bartle);
        $dominant_type   = array_key_first($candidate_bartle);
        $secondary_keys  = array_keys($candidate_bartle);
        $secondary_type  = $secondary_keys[1] ?? null;

        if (!$dominant_type) {
            return [
                'error' => 'Candidate has no valid Bartle results to calculate fit. Please ensure assessments are fully completed.',
            ];
        }

        $blind_spots       = $scorecard['blind_spots']            ?? [];
        $blind_spot_slugs  = array_column($blind_spots, 'type');
        $distribution      = $scorecard['player_type_distribution'] ?? [];

        $team_dominant_type = null;
        $team_max_count     = 0;
        foreach ($distribution as $slug => $d) {
            if ($d['count'] > $team_max_count) {
                $team_max_count    = $d['count'];
                $team_dominant_type = $slug;
            }
        }

        // Determine fit label
        $fills_blind_spot = in_array($dominant_type, $blind_spot_slugs);
        $matches_team_dominant = ($dominant_type === $team_dominant_type);

        if ($matches_team_dominant && ($distribution[$dominant_type]['status'] ?? '') === 'dominant') {
            $fit_label    = 'Resonant';
            $fit_narrative = self::build_resonant_narrative($dominant_type, $team_dominant_type, $distribution, $scorecard['member_count']);
        } elseif ($fills_blind_spot) {
            $fit_label    = 'Culture Add';
            $fit_narrative = self::build_culture_add_narrative($dominant_type, $blind_spots, $scorecard['member_count']);
        } else {
            $fit_label    = 'Divergent';
            $fit_narrative = self::build_divergent_narrative($dominant_type, $team_dominant_type, $distribution, $scorecard['member_count']);
        }

        // ── Recommendations ───────────────────────────────────────────────────
        $onboarding_mods  = self::build_onboarding_mods($cdt_gaps, $framing);
        $cultural_dynamics = self::build_cultural_dynamics(
            $dominant_type,
            $secondary_type,
            $scorecard['member_dominant_types'],
            $cdt_gaps,
            $framing
        );

        // Top level aggregate for the UI
        $bartle_fit_score = ($fit_label === 'Resonant' ? 100 : ($fit_label === 'Culture Add' ? 85 : 50));
        $total_fit_pct    = round(($cdt_alignment_score + $bartle_fit_score) / 2);

        return [
            'fit_percent'         => $total_fit_pct,
            'fit_label'           => $fit_label,
            'cdt_alignment_score' => $cdt_alignment_score,
            'cdt_low_alert'       => $cdt_low_alert,
            'cdt_gaps'            => $cdt_gaps,
            'mi_comparisons'      => $mi_comparisons,
            'cdt_comparisons'     => $cdt_comparisons,
            'bartle_comparisons'  => $bartle_comparisons,
            'adaptability_index'  => $adaptability_index,
            'bartle' => [
                'dominant_type'  => $dominant_type,
                'secondary_type' => $secondary_type,
                'scores'         => $candidate_bartle,
                'fit_label'      => $fit_label,
                'fit_narrative'  => $fit_narrative,
            ],
            'recommendations' => [
                'onboarding_modifications' => $onboarding_mods,
                'cultural_dynamics'        => $cultural_dynamics,
            ],
        ];
    }

    // ─── Bartle narrative builders ─────────────────────────────────────────────

    private static function build_resonant_narrative($dominant, $team_dominant, $distribution, $member_count) {
        $label = self::BARTLE_LABELS[$dominant] ?? $dominant;
        $pct   = $distribution[$dominant]['pct'] ?? 0;
        return "Strong cultural resonance. This candidate is {$label}-dominant, which aligns closely with the team's primary engagement style ({$pct}% of {$member_count} team members share this as their dominant type). They are likely to feel immediately at home in how this team approaches their work.";
    }

    private static function build_culture_add_narrative($dominant, $blind_spots, $member_count) {
        $label = self::BARTLE_LABELS[$dominant] ?? $dominant;
        $desc  = self::BARTLE_DESCRIPTIONS[$dominant] ?? '';
        $absent_note = '';
        foreach ($blind_spots as $bs) {
            if ($bs['type'] === $dominant) {
                $absent_note = $bs['status'] === 'absent'
                    ? "Currently, 0 of {$member_count} team members carry this as their dominant type."
                    : "Currently, only 1 of {$member_count} team members reflects this style prominently.";
                break;
            }
        }
        return "Potential culture add. This candidate is {$label}-dominant — {$desc}. {$absent_note} Bringing this perspective into the team could address a genuine composition gap. Worth discussing explicitly how this player type is valued rather than leaving the candidate to assimilate to the prevailing culture.";
    }

    private static function build_divergent_narrative($dominant, $team_dominant, $distribution, $member_count) {
        $cand_label = self::BARTLE_LABELS[$dominant]      ?? $dominant;
        $team_label = self::BARTLE_LABELS[$team_dominant] ?? $team_dominant;
        $team_pct   = $distribution[$team_dominant]['pct'] ?? 0;
        $cand_desc  = self::BARTLE_DESCRIPTIONS[$dominant] ?? '';
        return "Divergent engagement style — not a disqualifier, but worth discussing directly. This candidate is primarily {$cand_label}-dominant ({$cand_desc}), while the team skews strongly {$team_label} ({$team_pct}% of {$member_count} members). They may experience the team's culture as driving in a different direction than their natural motivations. An honest pre-boarding conversation about culture and expectations is strongly recommended.";
    }

    // ─── Recommendation builders ───────────────────────────────────────────────

    private static function build_onboarding_mods($cdt_gaps, $framing) {
        $mods = [];

        foreach ($cdt_gaps as $slug => $gap_data) {
            if ($gap_data['friction'] === 'low') continue;

            $label     = $gap_data['label'];
            $direction = $gap_data['direction'];
            $friction  = $gap_data['friction'];
            $severity  = $friction === 'high' ? '🔴 High friction' : '🟡 Moderate friction';

            if ($slug === 'ambiguity-tolerance' && $direction === 'below') {
                $mods[] = "{$severity} — {$label}: Define the role charter, decision rights, and reporting boundaries explicitly before Day 1. This candidate may prefer more structured mandates than the team typically operates with.";
            } elseif ($slug === 'ambiguity-tolerance' && $direction === 'above') {
                $mods[] = "{$severity} — {$label}: Consider assigning this candidate to open-ended or exploratory initiatives early. They may find highly structured environments under-stimulating.";
            } elseif ($slug === 'discomfort-regulation' && $direction === 'below') {
                $mods[] = "{$severity} — {$label}: Proactively communicate team norms around high-pressure cycles. Don't assume familiarity — surface these expectations early in onboarding.";
            } elseif ($slug === 'discomfort-regulation' && $direction === 'above') {
                $mods[] = "{$severity} — {$label}: Ensure the candidate understands the team's stress signals. Their own high regulation may cause them to underestimate pressure others are feeling.";
            } elseif ($slug === 'self-confrontation-capacity' && $direction === 'below') {
                $mods[] = "{$severity} — {$label}: Establish psychological safety before initiating direct feedback cycles. The team's norm for self-reflection may feel intense initially.";
            } elseif ($slug === 'value-conflict-navigation' && $direction === 'below') {
                $mods[] = "{$severity} — {$label}: Provide decision frameworks for ethical grey areas. This candidate may find the grey-area calls the team navigates regularly to be more taxing.";
            } elseif ($slug === 'growth-orientation' && $direction === 'below') {
                $mods[] = "{$severity} — {$label}: Make growth structures visible (goals, reviews, trajectory conversations). Don't assume self-driven growth is expected without explicit scaffolding.";
            } elseif ($slug === 'growth-orientation' && $direction === 'above') {
                $mods[] = "{$severity} — {$label}: Discuss career trajectory and development paths during the offer conversation. This candidate may outpace available growth opportunities if these aren't defined.";
            }
        }

        if (empty($mods)) {
            $mods[] = 'CDT alignment is strong across all dimensions. Standard onboarding approach is suitable — no significant friction zones detected.';
        }

        return $mods;
    }

    private static function build_cultural_dynamics($dominant, $secondary, $member_types, $cdt_gaps, $framing) {
        $dynamics = [];
        $cand_label = self::BARTLE_LABELS[$dominant] ?? $dominant;

        // Count team composition by type
        $type_counts = [];
        $type_names  = [];
        foreach ($member_types as $m) {
            $type_counts[$m['type']] = ($type_counts[$m['type']] ?? 0) + 1;
            $type_names[$m['type']][]  = $m['name'];
        }

        $total = count($member_types);

        // Main composition dynamic
        arsort($type_counts);
        $dominant_team_type  = array_key_first($type_counts);
        $team_type_label     = self::BARTLE_LABELS[$dominant_team_type] ?? $dominant_team_type;
        $team_type_count     = $type_counts[$dominant_team_type] ?? 0;

        if ($dominant !== $dominant_team_type) {
            $dynamics[] = "This candidate enters as a {$cand_label}-dominant individual into a largely {$team_type_label}-driven team ({$team_type_count} of {$total} members). Expect healthy tension in how priorities are framed and decisions are made — this difference in motivational style is worth surfacing early rather than leaving the candidate to discover it organically.";
        } else {
            $dynamics[] = "As a {$cand_label}-dominant candidate joining a team where this style is already prevalent, cultural integration is likely to feel natural. The main watch area is echo-chamber risk — the team may benefit from the candidate being able to flex into less represented styles when needed.";
        }

        // High friction CDT callouts (named)
        foreach ($cdt_gaps as $slug => $gap_data) {
            if ($gap_data['friction'] !== 'high') continue;
            $label     = $gap_data['label'];
            $direction = $gap_data['direction'];

            if ($slug === 'self-confrontation-capacity' && $direction === 'below') {
                $dynamics[] = "Your team scores notably high on Self-Confrontation Capacity. Leaders who join this culture often find direct feedback cycles more intense than expected. This isn't a fit problem, but it warrants an honest conversation during onboarding about how feedback is given and received here.";
            } elseif ($slug === 'discomfort-regulation' && $direction === 'below') {
                $dynamics[] = "The team operates with high Discomfort Regulation — they tend to stay composed under sustained pressure. Watch for this candidate underestimating the intensity of high-pressure periods (e.g., board prep, budget cycles) relative to what they've experienced before.";
            } elseif ($slug === 'growth-orientation' && $direction === 'above') {
                $dynamics[] = "This candidate's Growth Orientation is notably higher than the team average. They may introduce expectations for velocity and development that the team isn't accustomed to. Consider framing this as an asset in your offer narrative — but be transparent about current growth structures.";
            }
        }

        // If no high friction dynamics flagged, add a positive note
        if (count($dynamics) === 1) {
            $dynamics[] = 'No high-friction cultural dynamics detected. Standard integration monitoring is appropriate.';
        }

        return $dynamics;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // SECTION 4: AJAX HANDLERS
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * AJAX: Create a new scorecard.
     */
    public static function ajax_create_scorecard() {
        check_ajax_referer('mc_culture_scorecard_nonce', 'nonce');

        if (!current_user_can(MC_Roles::CAP_MANAGE_EMPLOYEES) && !current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permission denied.']);
        }

        $employer_id = self::resolve_employer_id();
        $label = sanitize_text_field($_POST['label'] ?? '');
        if (empty($label)) {
            wp_send_json_error(['message' => 'Scorecard name is required.']);
        }

        $scorecards = self::get_scorecards($employer_id);
        $new_id = 'scorecard_' . wp_generate_password(8, false);
        
        $new_scorecard = [
            'id'                => $new_id,
            'label'             => $label,
            'last_selected_ids' => [],
            'report_config'     => [
                'framing'      => 'executive',
                'cdt_low_flag' => 40,
            ],
            'created_at'  => time(),
            'updated_at'  => time(),
        ];

        $scorecards[] = $new_scorecard;
        update_user_meta($employer_id, 'mc_culture_scorecards_' . $employer_id, $scorecards);

        wp_send_json_success(['message' => 'Scorecard created.', 'scorecard' => $new_scorecard]);
    }

    /**
     * AJAX: Rename a scorecard.
     */
    public static function ajax_rename_scorecard() {
        check_ajax_referer('mc_culture_scorecard_nonce', 'nonce');

        if (!current_user_can(MC_Roles::CAP_MANAGE_EMPLOYEES) && !current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permission denied.']);
        }

        $employer_id = self::resolve_employer_id();
        $scorecard_id = sanitize_text_field($_POST['scorecard_id'] ?? '');
        $new_label = sanitize_text_field($_POST['label'] ?? '');

        if (empty($scorecard_id) || empty($new_label)) {
            wp_send_json_error(['message' => 'Scorecard ID and name are required.']);
        }

        $scorecards = self::get_scorecards($employer_id);
        $found = false;

        foreach ($scorecards as &$sc) {
            if ($sc['id'] === $scorecard_id) {
                $sc['label'] = $new_label;
                $sc['updated_at'] = time();
                $found = true;
                break;
            }
        }
        unset($sc);

        if (!$found) {
            wp_send_json_error(['message' => 'Scorecard not found.']);
        }

        update_user_meta($employer_id, 'mc_culture_scorecards_' . $employer_id, $scorecards);

        wp_send_json_success(['message' => 'Scorecard renamed.', 'scorecard_id' => $scorecard_id, 'new_label' => $new_label]);
    }

    /**
     * AJAX: Delete a scorecard.
     */
    public static function ajax_delete_scorecard() {
        check_ajax_referer('mc_culture_scorecard_nonce', 'nonce');

        if (!current_user_can(MC_Roles::CAP_MANAGE_EMPLOYEES) && !current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permission denied.']);
        }

        $employer_id = self::resolve_employer_id();
        $scorecard_id = sanitize_text_field($_POST['scorecard_id'] ?? '');

        if (empty($scorecard_id)) {
            wp_send_json_error(['message' => 'Scorecard ID is required.']);
        }

        $scorecards = self::get_scorecards($employer_id);
        
        // Don't delete the last remaining scorecard
        if (count($scorecards) <= 1) {
            wp_send_json_error(['message' => 'Cannot delete the last remaining scorecard.']);
        }

        $initial_count = count($scorecards);
        $scorecards = array_filter($scorecards, function($sc) use ($scorecard_id) {
            return $sc['id'] !== $scorecard_id;
        });
        
        // Re-index array
        $scorecards = array_values($scorecards);

        if (count($scorecards) === $initial_count) {
            wp_send_json_error(['message' => 'Scorecard not found.']);
        }

        update_user_meta($employer_id, 'mc_culture_scorecards_' . $employer_id, $scorecards);

        wp_send_json_success(['message' => 'Scorecard deleted.']);
    }

    /**
     * AJAX: Get the list of all scorecards for the employer.
     */
    public static function ajax_get_scorecards_list() {
        check_ajax_referer('mc_culture_scorecard_nonce', 'nonce');

        if (!current_user_can(MC_Roles::CAP_MANAGE_EMPLOYEES) && !current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permission denied.']);
        }

        $employer_id = self::resolve_employer_id();
        $scorecards = self::get_scorecards($employer_id);

        wp_send_json_success(['scorecards' => $scorecards]);
    }

    /**
     * AJAX: Save the employee selection for a scorecard.
     */
    public static function ajax_save_selection() {
        check_ajax_referer('mc_culture_scorecard_nonce', 'nonce');

        if (!current_user_can(MC_Roles::CAP_MANAGE_EMPLOYEES) && !current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permission denied.']);
        }

        $employer_id  = self::resolve_employer_id();
        $scorecard_id = sanitize_text_field($_POST['scorecard_id'] ?? 'scorecard_1');
        $selected_ids = isset($_POST['selected_ids']) ? array_map('intval', (array) $_POST['selected_ids']) : [];

        self::save_selection($employer_id, $scorecard_id, $selected_ids);

        wp_send_json_success(['message' => 'Selection saved.', 'count' => count($selected_ids)]);
    }

    /**
     * AJAX: Compute and return the full scorecard for a given set of employee IDs.
     */
    public static function ajax_get_scorecard() {
        check_ajax_referer('mc_culture_scorecard_nonce', 'nonce');

        if (!current_user_can(MC_Roles::CAP_MANAGE_EMPLOYEES) && !current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permission denied.']);
        }

        $employee_ids = isset($_POST['employee_ids']) ? array_map('intval', (array) $_POST['employee_ids']) : [];

        if (empty($employee_ids)) {
            wp_send_json_error(['message' => 'No employees selected.']);
        }

        $scorecard = self::compute_scorecard($employee_ids);

        if (isset($scorecard['error'])) {
            wp_send_json_error(['message' => $scorecard['error']]);
        }

        // Label constants for frontend
        $scorecard['mi_labels']     = self::MI_LABELS;
        $scorecard['cdt_labels']    = self::CDT_LABELS;
        $scorecard['bartle_labels'] = self::BARTLE_LABELS;

        wp_send_json_success($scorecard);
    }

    /**
     * AJAX: Compute culture fit for a specific candidate vs. the active scorecard.
     */
    public static function ajax_get_culture_fit() {
        check_ajax_referer('mc_culture_scorecard_nonce', 'nonce');

        if (!current_user_can(MC_Roles::CAP_MANAGE_EMPLOYEES) && !current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permission denied.']);
        }

        $employer_id  = self::resolve_employer_id();
        $candidate_id = intval($_POST['candidate_id'] ?? 0);
        $scorecard_id = sanitize_text_field($_POST['scorecard_id'] ?? 'scorecard_1');

        if (!$candidate_id) {
            wp_send_json_error(['message' => 'Invalid candidate.']);
        }

        // Verify all 3 assessments are complete
        if (!self::candidate_is_ready($candidate_id)) {
            wp_send_json_error(['message' => 'Candidate has not completed all three assessments (MI, CDT, Bartle).']);
        }

        $sc_data   = self::get_scorecard_by_id($employer_id, $scorecard_id);
        $sel_ids   = $sc_data['last_selected_ids'] ?? [];

        if (empty($sel_ids)) {
            wp_send_json_error(['message' => 'No employees selected in this scorecard. Add employees to compare against.']);
        }

        $scorecard = self::compute_scorecard($sel_ids);

        if (isset($scorecard['error'])) {
            wp_send_json_error(['message' => $scorecard['error']]);
        }

        $report_config = $sc_data['report_config'] ?? [];
        $fit           = self::calculate_culture_fit($candidate_id, $scorecard, $report_config);

        if (isset($fit['error'])) {
            wp_send_json_error(['message' => $fit['error']]);
        }

        $candidate_info = get_userdata($candidate_id);
        $fit['candidate_id']      = $candidate_id;
        $fit['candidate_name']    = $candidate_info ? $candidate_info->display_name : 'Candidate';
        $fit['scorecard_label']   = $sc_data['label'] ?? 'Company Baseline';
        $fit['mi_labels']         = self::MI_LABELS;
        $fit['cdt_labels']        = self::CDT_LABELS;
        $fit['bartle_labels']     = self::BARTLE_LABELS;

        wp_send_json_success($fit);
    }

    /**
     * AJAX: Generate unified candidate PDF.
     * Streams the PDF directly to the browser.
     */
    public static function ajax_generate_candidate_pdf() {
        // No nonce via check_ajax_referer because we stream — verify manually
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'mc_culture_scorecard_nonce')) {
            wp_send_json_error(['message' => 'Security check failed.']);
        }

        if (!current_user_can(MC_Roles::CAP_MANAGE_EMPLOYEES) && !current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permission denied.']);
        }

        $employer_id  = self::resolve_employer_id();
        $candidate_id = intval($_POST['candidate_id'] ?? 0);
        $scorecard_id = sanitize_text_field($_POST['scorecard_id'] ?? 'scorecard_1');

        if (!$candidate_id) {
            wp_die('Invalid candidate.');
        }

        if (!self::candidate_is_ready($candidate_id)) {
            wp_die('Candidate has not completed all three assessments. The PDF cannot be generated yet.');
        }

        // Get data
        $candidate_info = get_userdata($candidate_id);
        $mi_raw         = get_user_meta($candidate_id, 'miq_quiz_results',   true);
        $cdt_raw        = get_user_meta($candidate_id, 'cdt_quiz_results',   true);
        $bartle_raw     = get_user_meta($candidate_id, 'bartle_quiz_results', true);

        // Culture fit (if scorecard exists)
        $sc_data   = self::get_scorecard_by_id($employer_id, $scorecard_id);
        $sel_ids   = $sc_data['last_selected_ids'] ?? [];
        $culture_fit  = null;
        $scorecard    = null;

        if (!empty($sel_ids)) {
            $scorecard = self::compute_scorecard($sel_ids);
            if (!isset($scorecard['error'])) {
                $culture_fit = self::calculate_culture_fit($candidate_id, $scorecard, $sc_data['report_config'] ?? []);
            }
        }

        // Build the HTML for the PDF
        $html = self::build_pdf_html($candidate_info, $mi_raw, $cdt_raw, $bartle_raw, $culture_fit, $scorecard, $sc_data);

        // Generate PDF via Dompdf
        $autoload = MC_QUIZ_PLATFORM_PATH . 'vendor/autoload.php';
        if (file_exists($autoload)) {
            require_once $autoload;
        }

        if (!class_exists('Dompdf\\Dompdf')) {
            wp_die('PDF library (Dompdf) is not available. Please ensure the vendor/autoload.php is present in ' . esc_html(MC_QUIZ_PLATFORM_PATH));
        }

        $dompdf = new Dompdf\Dompdf();
        $dompdf->loadHtml($html);

        // Tall single page to avoid mid-section breaks
        $dompdf->setPaper([0, 0, 612, 3000], 'portrait');
        $dompdf->render();

        $candidate_name = $candidate_info ? sanitize_file_name($candidate_info->display_name) : 'Candidate';
        $filename       = 'Assessment_Report_' . $candidate_name . '_' . date('Y-m-d') . '.pdf';

        $dompdf->stream($filename, ['Attachment' => true]);
        exit;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // SECTION 5: PDF HTML BUILDER
    // ─────────────────────────────────────────────────────────────────────────

    private static function build_pdf_html($candidate_info, $mi_raw, $cdt_raw, $bartle_raw, $culture_fit, $scorecard, $sc_data) {
        $name = $candidate_info ? esc_html($candidate_info->display_name) : 'Candidate';
        $date = date('F j, Y');

        // ── Shared styles ──────────────────────────────────────────────────────
        $css = '
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #1e293b; margin: 0; padding: 0; }
        .page { padding: 40px 48px; }
        .cover { background-color: #1e40af; color: #ffffff; padding: 60px 48px 48px; }
        .cover h1 { font-size: 28px; font-weight: bold; margin: 0 0 8px; }
        .cover p  { font-size: 14px; margin: 4px 0; opacity: 0.9; }
        .section-header { padding: 18px 24px; color: #fff; font-size: 20px; font-weight: bold; margin: 32px 0 20px; border-radius: 8px; }
        .section-culture  { background: #1e40af; }
        .section-mi       { background: #0f766e; }
        .section-cdt      { background: #7c3aed; }
        .section-bartle   { background: #b45309; }
        .section-adapt    { background: #0f172a; }
        .section-divider  { border-top: 2px dashed #cbd5e1; margin: 48px 0 32px; text-align: center; }
        .section-divider span { background: #fff; padding: 0 16px; color: #64748b; font-size: 14px; text-transform: uppercase; letter-spacing: 2px; position: relative; top: -10px; }
        .score-hero { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 20px 24px; margin-bottom: 16px; }
        .score-hero h2 { margin: 0 0 4px; font-size: 22px; color: #1e40af; }
        .score-hero p  { margin: 0; color: #475569; font-size: 12px; }
        .fit-label { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: bold; margin-left: 8px; }
        .fit-resonant  { background: #dcfce7; color: #166534; }
        .fit-add       { background: #dbeafe; color: #1e40af; }
        .fit-divergent { background: #fef3c7; color: #92400e; }
        .alert-low { background: #fef2f2; border: 1px solid #fca5a5; border-radius: 6px; padding: 12px 16px; margin-bottom: 16px; }
        .alert-low strong { color: #991b1b; }
        .gap-row { margin-bottom: 14px; }
        .gap-label { font-weight: bold; font-size: 12px; margin-bottom: 4px; }
        .gap-label .friction-high { color: #dc2626; }
        .gap-label .friction-moderate { color: #d97706; }
        .gap-label .friction-low { color: #16a34a; }
        .bar-track { background: #e2e8f0; height: 10px; border-radius: 5px; position: relative; margin-bottom: 4px; }
        .bar-team      { background: #94a3b8; height: 10px; border-radius: 5px; position: absolute; top: 0; left: 0; }
        .bar-candidate { background: #2563eb; height: 10px; border-radius: 5px; position: absolute; top: 0; left: 0; opacity: 0.8; }
        .bar-legend { font-size: 10px; color: #64748b; }
        .scenario-box { background: #f8fafc; border-left: 3px solid #cbd5e1; padding: 8px 12px; font-size: 11px; color: #475569; margin-top: 6px; }
        .rec-list { margin: 0; padding: 0 0 0 18px; }
        .rec-list li { margin-bottom: 8px; line-height: 1.5; }
        .dim-row { margin-bottom: 10px; }
        .dim-bar-wrap { background: #e2e8f0; height: 8px; border-radius: 4px; }
        .dim-bar { height: 8px; border-radius: 4px; background: #2563eb; }
        .blind-spot-list { margin: 0; padding: 0; list-style: none; }
        .blind-spot-list li { padding: 6px 10px; margin-bottom: 6px; border-radius: 4px; font-size: 11px; }
        .status-absent { background: #fef2f2; color: #991b1b; }
        .status-under-represented { background: #fefce8; color: #92400e; }
        table.dims { width: 100%; border-collapse: collapse; }
        table.dims td { padding: 4px 6px; vertical-align: middle; }
        ';

        // ── Cover Page ─────────────────────────────────────────────────────────
        $scorecard_label = esc_html($sc_data['label'] ?? 'Company Baseline');

        $html = '<!DOCTYPE html><html><head><meta charset="UTF-8">
        <style>' . $css . '</style></head><body>';

        $html .= '<div class="cover">';
        $html .= '<p style="font-size:11px;opacity:0.7;margin-bottom:24px;text-transform:uppercase;letter-spacing:1px;">Candidate Assessment Report</p>';
        $html .= '<h1>' . $name . '</h1>';
        $html .= '<p>Generated: ' . $date . '</p>';
        if ($culture_fit && !isset($culture_fit['error'])) {
            $html .= '<p>Culture profile compared against: <strong>' . $scorecard_label . '</strong></p>';
        }
        $html .= '<div style="margin-top:28px;">';
        $html .= self::cover_badge('Intelligences', !empty($mi_raw['part1Scores']));
        $html .= self::cover_badge('Growth Strengths', !empty($cdt_raw['sortedScores']));
        $html .= self::cover_badge('Motivators', !empty($bartle_raw['sortedScores']));
        $html .= '</div>';
        $html .= '</div>';

        $html .= '<div class="page">';

        // ── Section 2: Culture Fit (leads) ────────────────────────────────────
        $html .= '<div class="section-header section-culture">Culture Fit — vs. ' . $scorecard_label . '</div>';

        if ($culture_fit && !isset($culture_fit['error'])) {
            $cdt_score  = $culture_fit['cdt_alignment_score'];
            $fit_label  = $culture_fit['bartle']['fit_label'];
            $fit_class  = 'fit-' . strtolower(str_replace(' ', '-', $fit_label));

            // Hero scores
            $html .= '<div class="score-hero"><table width="100%"><tr>';
            $html .= '<td width="50%"><p style="color:#64748b;font-size:10px;text-transform:uppercase;letter-spacing:.05em;margin:0 0 4px;">CDT Alignment</p>';
            $html .= '<h2 style="margin:0;font-size:32px;color:' . self::score_colour($cdt_score) . ';">' . $cdt_score . '<span style="font-size:16px;color:#94a3b8;"> / 100</span></h2></td>';
            $html .= '<td width="50%"><p style="color:#64748b;font-size:10px;text-transform:uppercase;letter-spacing:.05em;margin:0 0 4px;">Player Type Fit</p>';
            $html .= '<p style="margin:0;font-size:18px;font-weight:bold;">' . $fit_label . '</p></td>';
            $html .= '</tr></table></div>';

            if ($culture_fit['cdt_low_alert']) {
                $html .= '<div class="alert-low"><strong>⚠ Low CDT Alignment:</strong> Score below 40 indicates significant growth-style gaps across multiple dimensions. Review the dimension breakdown carefully — this may not be a dealbreaker but warrants explicit onboarding planning.</div>';
            }

            // Bartle narrative
            $html .= '<p style="color:#334155;line-height:1.6;margin-bottom:20px;">' . esc_html($culture_fit['bartle']['fit_narrative']) . '</p>';

            // CDT gap chart
            $html .= '<p style="font-weight:bold;font-size:13px;color:#0f172a;margin-bottom:10px;">CDT Dimension Gaps</p>';
            foreach ($culture_fit['cdt_gaps'] as $slug => $g) {
                $friction_color = $g['friction'] === 'high' ? '#dc2626' : ($g['friction'] === 'moderate' ? '#d97706' : '#16a34a');
                $friction_label = ucfirst($g['friction']) . ' friction';
                $team_w   = $g['team_score'];
                $cand_w   = $g['candidate_score'];
                $html .= '<div class="gap-row">';
                $html .= '<div class="gap-label">' . esc_html($g['label']) . ' <span style="color:' . $friction_color . ';font-size:10px;">(' . $friction_label . ')</span></div>';
                $html .= '<table class="dims" style="margin-bottom:2px;"><tr>';
                $html .= '<td style="width:60px;font-size:10px;color:#64748b;">Team avg</td>';
                $html .= '<td><div class="bar-track"><div class="bar-team" style="width:' . $team_w . '%;background:#94a3b8;"></div></div></td>';
                $html .= '<td style="width:30px;font-size:10px;text-align:right;">' . $team_w . '</td>';
                $html .= '</tr><tr>';
                $html .= '<td style="width:60px;font-size:10px;color:#64748b;">Candidate</td>';
                $html .= '<td><div class="bar-track"><div class="bar-candidate" style="width:' . $cand_w . '%;"></div></div></td>';
                $html .= '<td style="width:30px;font-size:10px;text-align:right;">' . $cand_w . '</td>';
                $html .= '</tr></table>';
                $html .= '<div class="scenario-box">' . esc_html($g['scenario']) . '</div>';
                $html .= '</div>';
            }

            // Recommendations
            $html .= '<p style="font-weight:bold;font-size:13px;color:#0f172a;margin:20px 0 8px;">🚀 Onboarding Modifications</p>';
            $html .= '<ul class="rec-list">';
            foreach ($culture_fit['recommendations']['onboarding_modifications'] as $mod) {
                $html .= '<li>' . esc_html($mod) . '</li>';
            }
            $html .= '</ul>';

            $html .= '<p style="font-weight:bold;font-size:13px;color:#0f172a;margin:20px 0 8px;">🧭 Cultural Dynamics to Watch</p>';
            $html .= '<ul class="rec-list">';
            foreach ($culture_fit['recommendations']['cultural_dynamics'] as $dyn) {
                $html .= '<li>' . esc_html($dyn) . '</li>';
            }
            $html .= '</ul>';

        } else {
            $html .= '<p style="color:#64748b;font-style:italic;">Culture Fit analysis not yet available — set up your Culture Profile to include this section.</p>';
        }

        // ── Supporting evidence divider ────────────────────────────────────────
        $html .= '<div class="section-divider"><span>Supporting Evidence</span></div>';

        // ── Section 3: Intelligences (MI) ─────────────────────────────────────
        $html .= '<div class="section-header section-mi">Intelligences (MI)</div>';
        if (!empty($mi_raw['part1Scores'])) {
            $mi_scores = self::normalize_mi_scores($mi_raw);
            arsort($mi_scores);

            if (!empty($culture_fit['mi_comparisons'])) {
                $html .= '<p style="color:#475569;line-height:1.6;margin-bottom:14px;">Compares the candidate\'s intelligence profile to the selected scorecard baseline so hiring teams can see where the person matches the team\'s natural working mix and where they expand it.</p>';
                $html .= self::render_pdf_comparison_rows($culture_fit['mi_comparisons'], '#0f766e');
            }

            $top3 = array_slice($mi_scores, 0, 3, true);
            $html .= '<p style="font-weight:bold;margin:16px 0 8px;">Top Intelligences</p>';
            $html .= '<ul class="rec-list">';
            foreach ($top3 as $slug => $score) {
                $label = self::MI_LABELS[$slug] ?? $slug;
                $html .= '<li><strong>' . esc_html($label) . '</strong> (' . $score . '/100)</li>';
            }
            $html .= '</ul>';
        } else {
            $html .= '<p style="color:#64748b;font-style:italic;">MI assessment not completed.</p>';
        }

        // ── Section 4: Growth Strengths (CDT) ─────────────────────────────────
        $html .= '<div class="section-header section-cdt">Growth Strengths (CDT)</div>';
        if (!empty($culture_fit['cdt_comparisons'])) {
            $html .= '<p style="color:#475569;line-height:1.6;margin-bottom:14px;">Shows where the candidate\'s growth-strength profile sits above or below the team average across each CDT dimension. Higher gap levels indicate where onboarding or management style may need to adapt.</p>';
            $html .= self::render_pdf_comparison_rows($culture_fit['cdt_comparisons'], '#7c3aed', true);
        } elseif (!empty($cdt_raw['sortedScores'])) {
            $cdt_scores_sorted = self::normalize_cdt_scores($cdt_raw);
            arsort($cdt_scores_sorted);
            foreach ($cdt_scores_sorted as $slug => $score) {
                $label  = self::CDT_LABELS[$slug] ?? $slug;
                $html .= '<div class="dim-row"><table class="dims"><tr>';
                $html .= '<td style="width:180px;font-size:11px;">' . esc_html($label) . '</td>';
                $html .= '<td><div class="dim-bar-wrap"><div class="dim-bar" style="width:' . $score . '%;background:#7c3aed;"></div></div></td>';
                $html .= '<td style="width:30px;font-size:11px;text-align:right;">' . $score . '</td>';
                $html .= '</tr></table></div>';
            }
        } else {
            $html .= '<p style="color:#64748b;font-style:italic;">CDT assessment not completed.</p>';
        }

        // ── Section 5: Motivators (Bartle) ────────────────────────────────────
        $html .= '<div class="section-header section-bartle">Motivators (Player Types)</div>';
        if (!empty($bartle_raw['sortedScores'])) {
            $b_scores = self::normalize_bartle_scores($bartle_raw);
            arsort($b_scores);
            $b_dominant  = array_key_first($b_scores);
            $b_dom_label = self::BARTLE_LABELS[$b_dominant] ?? $b_dominant;
            $b_desc      = self::BARTLE_DESCRIPTIONS[$b_dominant] ?? '';

            $html .= '<p style="margin-bottom:12px;">Dominant type: <strong>' . esc_html($b_dom_label) . '</strong> — ' . esc_html($b_desc) . '.</p>';
            if (!empty($culture_fit['bartle']['fit_narrative'])) {
                $html .= '<p style="color:#475569;line-height:1.6;margin-bottom:14px;">' . esc_html($culture_fit['bartle']['fit_narrative']) . '</p>';
            }
            if (!empty($culture_fit['bartle_comparisons'])) {
                $html .= self::render_pdf_comparison_rows($culture_fit['bartle_comparisons'], '#b45309');
            }
        } else {
            $html .= '<p style="color:#64748b;font-style:italic;">Bartle assessment not completed.</p>';
        }

        // ── Section 6: Adaptability Index ─────────────────────────────────────
        $html .= '<div class="section-header section-adapt">Adaptability Index</div>';
        if (!empty($culture_fit['adaptability_index'])) {
            $adapt = $culture_fit['adaptability_index'];
            $band_color = '#64748b';
            if (($adapt['band']['status'] ?? '') === 'high') {
                $band_color = '#166534';
            } elseif (($adapt['band']['status'] ?? '') === 'moderate') {
                $band_color = '#92400e';
            }

            $html .= '<div class="score-hero"><table width="100%"><tr>';
            $html .= '<td width="35%"><p style="color:#64748b;font-size:10px;text-transform:uppercase;letter-spacing:.05em;margin:0 0 4px;">Adaptability Score</p>';
            $html .= '<h2 style="margin:0;font-size:32px;color:' . $band_color . ';">' . esc_html($adapt['score']) . '<span style="font-size:16px;color:#94a3b8;"> / 100</span></h2></td>';
            $html .= '<td width="65%"><p style="margin:0;font-size:18px;font-weight:bold;color:' . $band_color . ';">' . esc_html($adapt['band']['label'] ?? 'Adaptability') . '</p>';
            $html .= '<p style="margin:8px 0 0;color:#475569;line-height:1.6;">' . esc_html($adapt['summary'] ?? '') . '</p>';
            if (isset($adapt['scorecard_average'])) {
                $html .= '<p style="margin:10px 0 0;color:#64748b;font-size:12px;">Target scorecard average: <strong>' . esc_html(round(floatval($adapt['scorecard_average']))) . ' / 100</strong></p>';
            }
            $html .= '</td>';
            $html .= '</tr></table></div>';

            $html .= '<p style="color:#475569;line-height:1.6;margin-bottom:12px;">This index is a neutral internal summary derived from the same underlying assessment patterns already captured in the assessments. Higher values indicate stronger adaptability capacity.</p>';

            foreach (($adapt['sub_indices'] ?? []) as $sub) {
                $label = $sub['label'] ?? 'Adaptability Dimension';
                $score = floatval($sub['display_score'] ?? 0);
                $scorecard_avg = floatval($sub['scorecard_average'] ?? 0);
                $html .= '<div class="gap-row">';
                $html .= '<div class="gap-label">' . esc_html($label) . '</div>';
                $html .= '<table class="dims" style="margin-bottom:2px;"><tr>';
                $html .= '<td style="width:78px;font-size:10px;color:#64748b;">Scorecard avg</td>';
                $html .= '<td><div class="bar-track"><div class="bar-team" style="width:' . $scorecard_avg . '%;background:#94a3b8;"></div></div></td>';
                $html .= '<td style="width:36px;font-size:10px;text-align:right;">' . esc_html(round($scorecard_avg)) . '</td>';
                $html .= '</tr><tr>';
                $html .= '<td style="width:78px;font-size:10px;color:#64748b;">Candidate</td>';
                $html .= '<td><div class="bar-track"><div class="bar-candidate" style="width:' . $score . '%;background:#0f172a;"></div></div></td>';
                $html .= '<td style="width:36px;font-size:10px;text-align:right;">' . esc_html(round($score)) . '</td>';
                $html .= '</tr></table></div>';
                if (!empty($sub['description'])) {
                    $html .= '<p style="margin:0 0 12px 0; color:#64748b; font-size:11px; line-height:1.5; padding-left:6px;">' . esc_html($sub['description']) . '</p>';
                }
            }
        } else {
            $html .= '<p style="color:#64748b;font-style:italic;">Adaptability data is not available for this candidate yet.</p>';
        }

        $html .= '</div></body></html>';

        return $html;
    }

    // ─── PDF helpers ───────────────────────────────────────────────────────────

    private static function cover_badge($label, $complete) {
        $bg    = $complete ? 'rgba(255,255,255,0.2)' : 'rgba(0,0,0,0.2)';
        $icon  = $complete ? '✓' : '○';
        return '<div style="display:inline-block; margin-right:15px; background:' . $bg . ';padding:8px 14px;border-radius:4px;font-size:11px;">' . $icon . ' ' . $label . '</div>';
    }

    private static function score_colour($score) {
        if ($score >= 70) return '#166534';
        if ($score >= 50) return '#92400e';
        return '#991b1b';
    }

    private static function render_pdf_comparison_rows($comparisons, $candidate_colour, $show_scenarios = false) {
        $html = '';

        foreach ($comparisons as $comparison) {
            $band_color = '#16a34a';
            if (($comparison['status'] ?? '') === 'moderate') {
                $band_color = '#d97706';
            } elseif (($comparison['status'] ?? '') === 'high' && $show_scenarios) {
                $band_color = '#dc2626';
            } elseif (($comparison['status'] ?? '') === 'low' && !$show_scenarios) {
                $band_color = '#dc2626';
            }

            $html .= '<div class="gap-row">';
            $html .= '<div class="gap-label">' . esc_html($comparison['label'] ?? 'Dimension') . ' <span style="color:' . $band_color . ';font-size:10px;">(' . esc_html($comparison['band_label'] ?? '') . ')</span></div>';
            $html .= '<table class="dims" style="margin-bottom:2px;"><tr>';
            $html .= '<td style="width:60px;font-size:10px;color:#64748b;">Team avg</td>';
            $html .= '<td><div class="bar-track"><div class="bar-team" style="width:' . floatval($comparison['team_score'] ?? 0) . '%;background:#94a3b8;"></div></div></td>';
            $html .= '<td style="width:36px;font-size:10px;text-align:right;">' . esc_html(round(floatval($comparison['team_score'] ?? 0))) . '</td>';
            $html .= '</tr><tr>';
            $html .= '<td style="width:60px;font-size:10px;color:#64748b;">Candidate</td>';
            $html .= '<td><div class="bar-track"><div class="bar-candidate" style="width:' . floatval($comparison['candidate_score'] ?? 0) . '%;background:' . esc_attr($candidate_colour) . ';"></div></div></td>';
            $html .= '<td style="width:36px;font-size:10px;text-align:right;">' . esc_html(round(floatval($comparison['candidate_score'] ?? 0))) . '</td>';
            $html .= '</tr></table>';
            if ($show_scenarios && !empty($comparison['scenario'])) {
                $html .= '<div class="scenario-box">' . esc_html($comparison['scenario']) . '</div>';
            }
            $html .= '</div>';
        }

        return $html;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // SECTION 6: HELPERS
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Check all 3 assessments are complete for a candidate.
     */
    public static function candidate_is_ready($candidate_id) {
        $mi     = get_user_meta($candidate_id, 'miq_quiz_results',    true);
        $cdt    = get_user_meta($candidate_id, 'cdt_quiz_results',    true);
        $bartle = get_user_meta($candidate_id, 'bartle_quiz_results', true);

        if (empty($mi) || empty($cdt) || empty($bartle)) {
            return false;
        }

        // Deep check for sortedScores or part1Scores to ensure it's not just a partial record
        $mi_ready  = !empty($mi['part1Scores'])   || !empty($mi['sortedScores']);
        $cdt_ready = !empty($cdt['sortedScores'])  || !empty($cdt['part1Scores']);
        $bar_ready = !empty($bartle['sortedScores']) || !empty($bartle['part1Scores']);

        return $mi_ready && $cdt_ready && $bar_ready;
    }

    /**
     * Resolve which employer account to use (handles admin switching).
     */
    private static function resolve_employer_id() {
        $current = get_current_user_id();
        // If admin has switched to an employer, they ARE that employer
        // Otherwise, use current user
        return $current;
    }
}
