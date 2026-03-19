# Strain Index Implementation Guide

## Overview
The Strain Index scoring system has been restructured from a standalone assessment into a backend-only metric derived from integrated questions in the MI, CDT, and Bartle quizzes.

## Key Components

### 1. Question Integration
30 Strain Index questions have been distributed across three existing quizzes:
- **MI Quiz:** 10 questions (4 Rumination, 3 Avoidance, 3 Emotional Flood)
- **CDT Quiz:** 10 questions (3 Rumination, 4 Avoidance, 3 Emotional Flood)
- **Bartle Quiz:** 10 questions (4 Rumination, 3 Avoidance, 3 Emotional Flood)

**Categories:**
Questions are assigned to new categories with neutral display names to hide their purpose from employees:
- `si-rumination` -> "Processing Style"
- `si-avoidance` -> "Decision Dynamics"
- `si-emotional-flood` -> "Engagement Style"

### 2. Frontend Visibility Control
Strict visibility rules are enforced via JavaScript in each quiz module:
- **Files Modified:** `mi-quiz.js`, `cdt-quiz/quiz.js`, `bartle-quiz/quiz.js`
- **Logic:** Results filtering logic explicitly excludes any category starting with `si-` from the employee-facing results display.

### 3. Backend Scoring (`MC_Strain_Index_Scorer`)
- **File:** `includes/class-mc-strain-index-scorer.php`
- **Method:** `calculate_from_user_meta($user_id)`
- **Process:**
    1. Retrieves quiz results from user meta (`miq_quiz_results`, `cdt_quiz_results`, `bartle_quiz_results`).
    2. Aggregates scores for Rumination, Avoidance, and Emotional Flood across all quizzes.
    3. Normalizes scores to a 0-1 scale.
    4. Calculates Overall Strain (Average of sub-indices).
    5. Saves results to `wp_mc_strain_index_results` table and `strain_index_results` user meta.

### 4. Trigger Mechanism
- **File:** `includes/class-mc-funnel.php`
- **Method:** `check_completion_and_notify`
- **Logic:** The Strain Index calculation is triggered automatically whenever a user completes a quiz step, ensuring up-to-date scores.

### 5. Employer Dashboard Integration
The Strain Index results are integrated into the Employer Dashboard's "Deep Analysis" modal.

- **File Modified:** `includes/class-mc-employer-dashboard.php`
- **Data Retrieval:** The dashboard retrieves `strain_index_results` user meta for each employee.
- **Visualization:**
    - A new "Strain Index Analysis" card is added to the report modal.
    - **Overall Strain:** Displayed as a gauge (Green < 0.33, Yellow < 0.66, Red >= 0.66).
    - **Sub-Indices:** Bar charts for Rumination, Avoidance, and Emotional Flood.
- **Visibility:** This section is **only** visible in the Employer Dashboard. It is NOT present in any employee-facing views.

### 6. Database Schema
The system uses a custom table `wp_mc_strain_index_results` to store historical data.

```sql
CREATE TABLE wp_mc_strain_index_results (
    id bigint(20) NOT NULL AUTO_INCREMENT,
    user_id bigint(20) NOT NULL,
    assessment_date datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
    rumination_score float,
    avoidance_score float,
    emotional_flood_score float,
    overall_strain float,
    full_results longtext,
    PRIMARY KEY  (id),
    KEY user_id (user_id)
) $charset_collate;
```

## Verification
- **Script:** `test-strain-integration.php` (Simulates quiz completion and verifies DB storage).
- **Manual Check:**
    - **Employee:** Take quizzes -> Verify NO Strain info in results.
    - **Employer:** View Employee Report -> Verify Strain Index gauge and breakdown are visible.
