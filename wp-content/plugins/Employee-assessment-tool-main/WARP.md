# WARP.md

This file provides guidance to WARP (warp.dev) when working with code in this repository.

## Overview

This is a WordPress plugin called "Micro-Coach Quiz Platform" that provides a modular assessment system with AI-powered coaching features. The plugin hosts three psychometric assessments: Multiple Intelligences (MI), Cognitive Dissonance Tolerance (CDT), and Bartle Player Types, along with an AI coach that generates personalized "Minimum Viable Experiments" (MVEs) for self-discovery.

## Architecture

### Core Components
- **`mi-quiz-platform.php`** - Main plugin file that handles initialization and loads all modules
- **`micro-coach-core.php`** - Central platform coordinator that manages the quiz dashboard, shortcodes, and module registration system
- **`micro-coach-ai.php`** - AI integration layer that handles OpenAI API communication, experiment generation, and feedback collection

### Modular Quiz System
Individual quizzes are located in `quizzes/[quiz-name]/` directories with a consistent structure:
- `module.php` - Main quiz class with initialization, shortcodes, and AJAX endpoints
- `questions.php` - Quiz data definitions (questions, categories, scoring)
- `quiz.js` - Frontend quiz logic and user interaction
- `quiz.css` - Quiz-specific styling

Currently implemented quizzes:
- **MI Quiz** (`quizzes/mi-quiz/`) - Multiple Intelligences assessment
- **CDT Quiz** (`quizzes/cdt-quiz/`) - Cognitive Dissonance Tolerance assessment  
- **Bartle Quiz** (`quizzes/bartle-quiz/`) - Player Type assessment

### Plugin Flow
1. Users access quizzes through WordPress shortcodes (`[quiz_dashboard]`, `[mi_quiz]`, etc.)
2. The core platform tracks completion status and manages dependencies between quizzes
3. Results are stored in WordPress user meta and optionally in custom database tables
4. Upon completion of all assessments, users can access the AI Coach for personalized experiment suggestions

## Development Workflow

### Dependencies
- **PHP**: WordPress plugin environment
- **Composer**: Manages PHP dependencies (currently only `dompdf/dompdf` for PDF generation)
- **JavaScript**: Vanilla JS for frontend quiz interactions
- **Database**: Uses WordPress `wp_users` meta and custom tables for AI data

### Installation & Setup
```bash
# Install PHP dependencies
composer install

# No build process required - plugin loads directly in WordPress
# Place in wp-content/plugins/ and activate through WordPress admin
```

### Development Commands

**Database Management**:
```bash
# Tables are automatically created when needed
# No manual migration commands required
```

**Asset Management**:
```bash
# No build process - CSS/JS files are loaded directly
# Assets are conditionally enqueued only on pages containing quiz shortcodes
```

**Testing Quizzes**:
- Use WordPress admin "Quiz Platform" menu for settings and data management
- Access quiz modules through their individual admin pages
- AI Debug page provides request/response logging when debug mode is enabled

## Key Files & Configuration

### Plugin Configuration
- Main plugin settings accessed via WordPress admin: "Quiz Platform > Settings"
- AI configuration including OpenAI API key managed through settings interface
- Email notification settings for quiz results configured per module

### Database Tables
The plugin creates custom tables for AI functionality:
- `wp_mc_ai_experiments` - Stores generated experiment data
- `wp_mc_ai_feedback` - Tracks user interactions with experiments
- `wp_miq_subscribers` - Email subscriber list for MI quiz

### Important Constants
```php
MC_QUIZ_PLATFORM_PATH   // Plugin directory path
Micro_Coach_AI::TABLE_EXPERIMENTS  // AI experiments table
Micro_Coach_AI::TABLE_FEEDBACK     // AI feedback table
```

## AI Integration

### OpenAI Integration
- Configure API key through WordPress admin settings
- Supports both GPT-4o-mini and GPT-4o models
- System instructions are admin-configurable for fine-tuning AI responses
- Debug mode captures API requests/responses for development

### Experiment Generation
The AI Coach generates "Minimum Viable Experiments" based on:
- User's quiz results (MI top 3, CDT scores, Bartle player type)
- User-selected filters (cost, time, energy, variety)  
- Brainstorming lenses (Curiosity, Role Models, Opposites, Adjacency)

### Fallback System
When OpenAI API is unavailable, the system generates heuristic-based experiment suggestions using the user's profile data.

## WordPress Integration

### Shortcodes
- `[quiz_dashboard]` - Main dashboard showing all quizzes and AI coach
- `[mi_quiz]` or `[mi-quiz]` - Multiple Intelligences assessment
- `[cdt_quiz]` or `[cdt-quiz]` - Cognitive Dissonance Tolerance assessment  
- `[bartle_quiz]` or `[bartle-quiz]` - Bartle Player Type assessment

### User Flow
1. User visits page with `[quiz_dashboard]` shortcode
2. Completes assessments in order (MI → CDT → Bartle)
3. Results automatically saved to user meta with completion timestamps
4. AI Coach becomes available after completing all assessments
5. Generated experiments can be saved/rated for future reference

### Elementor Compatibility
Special filter ensures quiz shortcodes render properly within Elementor's Shortcode widget.

## Security & Data Handling

- All AJAX endpoints use WordPress nonces for CSRF protection
- User permissions checked before data operations
- OpenAI API key stored securely as WordPress option (never exposed to frontend)
- PDF generation handled server-side with temporary file cleanup
- User data deletion hooks ensure subscriber cleanup on account deletion

## Local Development Notes

- No build process required - direct file editing
- Use WordPress development environment with debug mode enabled
- AI Debug admin page provides detailed logging when debug mode is active
- Quiz questions and categories defined in PHP arrays within each quiz module
