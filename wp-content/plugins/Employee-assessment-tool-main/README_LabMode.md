# AI Coach Lab Mode - Technical Documentation

## Overview

Lab Mode is an experimental extension to the AI Coach that provides a data-rich, experiment-driven workflow. It guides users through a systematic process: Import assessment results → Add personal qualifiers → Generate tailored experiments → Execute → Reflect → Recalibrate.

## Architecture

### Core Components

1. **Micro_Coach_AI_Lab** - Main class managing the Lab Mode functionality
2. **Database Schema** - Three custom tables for experiments, feedback, and user preferences
3. **Content Libraries** - Deterministic JSON files for MI combinations and archetype templates
4. **Frontend Application** - JavaScript-based UI with multiple workflow stages
5. **AI Integration** - Enhanced prompting system using deterministic content

### File Structure

```
micro-coach-ai-lab.php          # Main Lab Mode class
assets/
  lab-mode.js                   # Frontend JavaScript application
  lab-mode.css                  # Styling for Lab Mode interface
  lab_libraries/
    mi_combinations.json        # MI triads and pairs data
    archetype_templates.json    # Experiment generation templates
```

## Database Schema

### Tables

#### `mc_lab_experiments`
- **id** - Auto-increment primary key
- **user_id** - WordPress user ID (foreign key)
- **experiment_data** - JSON data for the experiment (title, steps, criteria, etc.)
- **profile_data** - User's assessment profile snapshot
- **archetype** - Experiment type: 'Discover', 'Build', or 'Share'
- **status** - 'Draft', 'Active', 'Completed', or 'Archived'
- **created_at** - Timestamp
- **updated_at** - Timestamp (auto-updated)

#### `mc_lab_feedback`
- **id** - Auto-increment primary key
- **experiment_id** - Foreign key to experiments table
- **user_id** - WordPress user ID
- **difficulty** - Rating 1-5 (1=too easy, 5=too hard)
- **fit** - Rating 1-5 (1=poor match, 5=perfect fit)
- **learning** - Rating 1-5 (1=no growth, 5=major growth)
- **notes** - Optional text feedback
- **next_action** - 'Repeat', 'Evolve', or 'Archive'
- **evolve_notes** - Optional improvement suggestions
- **submitted_at** - Timestamp

#### `mc_lab_user_preferences`
- **id** - Auto-increment primary key
- **user_id** - WordPress user ID (unique key)
- **contexts** - JSON object of context preferences and weights
- **risk_bias** - Decimal bias towards risk level (-1.0 to 1.0)
- **solo_group_bias** - Decimal bias towards solo vs group activities (-1.0 to 1.0)
- **updated_at** - Timestamp (auto-updated)

## Content Libraries

### MI Combinations (`mi_combinations.json`)

Contains deterministic data for Multiple Intelligence combinations:

- **triads** - 56 unique three-way MI combinations with names, descriptions, and experiment seeds
- **pairs** - 28 two-way MI combinations for variant generation

Structure:
```json
{
  "triads": {
    "linguistic_logical-mathematical_spatial": {
      "name": "The Strategic Communicator",
      "description": "Combines clear communication with analytical thinking and visual problem-solving.",
      "experiment_seeds": [...]
    }
  },
  "pairs": {
    "linguistic_logical-mathematical": {
      "name": "The Systematic Communicator",
      "focus": "Clear, structured communication and analysis"
    }
  }
}
```

### Archetype Templates (`archetype_templates.json`)

Contains scaffolding for the three experiment archetypes:

- **Discover** - Exploration and learning focused experiments
- **Build** - Creation and prototyping focused experiments  
- **Share** - Communication and teaching focused experiments

Each archetype includes:
- `titlePatterns` - Templates for generating experiment titles
- `stepTemplates` - Multiple step sequence templates
- `rationales` - Templates for explaining why the experiment fits
- `successCriteria` - Templates for measurable success criteria

## User Flow

### 1. Landing Page
- Welcome message explaining Lab Mode
- Two options: "Start" or "View Past Experiments"
- Only visible if all core assessments (MI, CDT, Bartle) are complete

### 2. Profile Inputs
Three sections on a single page:

**A. Assessment Results (Read-only)**
- Multiple Intelligences top 3 with scores
- All 5 CDT dimensions with rankings
- Primary and secondary Bartle player types
- Links to edit original assessments

**B. Self-Qualification**
- For each top 3 MI: what you enjoy doing, what you currently do
- For lowest 2 CDT: where this trips you up, what has helped before
- Text areas with 1-3 bullet point guidance

**C. Mad-Lib Curiosity**
- 3 curiosity phrases (required)
- 3 role model names (optional)
- Constraint sliders: risk tolerance, budget, time per week, solo/group preference
- Context tags (comma-separated)

### 3. Experiments Generation
- AI generates 3 experiments (one per archetype)
- Each experiment card shows:
  - Title and archetype badge
  - Rationale tied to user's MI/CDT profile
  - 3-5 concrete steps
  - Effort estimates (time/budget) and risk level
  - 3 checkable success criteria
  - Action buttons: "Start" and "Regenerate Variant"

### 4. Run & Reflect
- Checklist interface for steps and success criteria
- "Complete & Reflect" button opens reflection form
- Likert scale ratings for difficulty, fit, and learning value
- Optional notes field
- Next action selection: Repeat, Evolve, or Archive
- Evolution notes if "Evolve" is selected

### 5. Recalibration Summary
- Shows what changed in user preferences
- Updated bias values
- Human-readable summary of adjustments
- Options to generate next iteration, view history, or start fresh

### 6. History Timeline
- Chronological list of all experiments
- Status badges and key metadata
- Links to restart the workflow

## AI Integration

### Prompt Structure

The system uses a structured prompt with:

1. **System Instructions** - Fixed guidance for AI behavior
2. **User Data** - JSON payload including:
   - Assessment profile data
   - User qualifiers and constraints
   - Archetype templates
   - CDT coaching dataset
   - MI combination library

### Deterministic Generation

- Templates provide structure for consistent output
- Variables are filled from user-specific data
- Multiple template options prevent repetitive results
- JSON schema enforces expected output format

### Recalibration Logic

Feedback automatically adjusts future suggestions:
- **fit ≤ 2**: Decrease weight of used contexts
- **learning ≥ 4 & difficulty 3-4**: Mark as "sweet spot", increase similar constraints
- **difficulty ≥ 5**: Reduce risk level, prefer simpler variants

## API Endpoints

All endpoints require authentication and use nonce verification:

- `mc_lab_get_profile_data` - Fetch user's assessment results
- `mc_lab_save_qualifiers` - Store user qualification inputs
- `mc_lab_generate_experiments` - Generate AI experiments
- `mc_lab_start_experiment` - Mark experiment as active
- `mc_lab_submit_reflection` - Submit feedback and trigger recalibration
- `mc_lab_get_history` - Fetch user's experiment timeline
- `mc_lab_recalibrate` - Generate evolved experiments from feedback

## Permissions

Lab Mode access requires:
- User must be logged in
- User must have `edit_posts` capability OR `manage_options` capability
- Feature flag `mc_lab_mode_enabled` must be set to '1'

## Installation & Setup

### 1. Enable Lab Mode
In WordPress admin, go to Quiz Platform → Settings → AI Integration and check "Lab Mode (Experimental)".

### 2. Configure AI
Ensure OpenAI API key is configured in the same settings section.

### 3. Database Tables
Tables are created automatically on first admin load when Lab Mode is enabled.

### 4. Content Libraries
The JSON library files are included and loaded automatically. If files are missing, fallback templates are used.

## Extension Points

### Adding New Archetype Templates

Edit `assets/lab_libraries/archetype_templates.json`:

```json
{
  "NewArchetype": {
    "titlePatterns": ["Pattern with {variables}"],
    "stepTemplates": [["Step 1", "Step 2", "Step 3"]],
    "rationales": ["Rationale template with {variables}"],
    "successCriteria": [["Criteria 1", "Criteria 2", "Criteria 3"]]
  }
}
```

### Adding New MI Combinations

Edit `assets/lab_libraries/mi_combinations.json` to add new triads or pairs.

### Extending Recalibration Logic

Modify the `recalibrate_user_preferences()` method to add new adjustment rules based on user feedback patterns.

### Custom Frontend Components

The JavaScript application is modular. New workflow steps can be added by:
1. Adding new methods to `LabModeApp`
2. Creating corresponding AJAX endpoints
3. Adding UI templates and styling

## Development Notes

### Testing
- Database schema changes can be tested by deactivating/reactivating the plugin
- AI integration can be tested with test API keys
- Frontend can be developed with mock AJAX responses

### Performance
- Assessment data is cached in user meta to avoid repeated queries
- Content libraries are loaded once and cached during request
- Database operations use prepared statements for security

### Security
- All AJAX endpoints verify nonces and user permissions
- User input is sanitized before database storage
- API keys are stored securely and never exposed to frontend

## Troubleshooting

### Lab Mode Tab Not Visible
- Check that all assessments (MI, CDT, Bartle) are completed
- Verify feature flag is enabled in settings
- Ensure user has sufficient permissions

### Experiment Generation Fails
- Check OpenAI API key configuration
- Verify API quotas and limits
- Check browser console for JavaScript errors

### Database Errors
- Ensure WordPress has database creation permissions
- Check for plugin conflicts that might interfere with table creation
- Verify sufficient database storage space

## Future Enhancements

Potential areas for development:
- Additional archetype templates for specialized workflows
- Integration with external content sources
- Advanced analytics and pattern recognition
- Collaborative experiments for team environments
- Export functionality for experiment results
