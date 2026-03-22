# Career Explorer with Filters & Dice - Implementation Guide

## Overview

This implementation enhances the Career Explorer with smart filters and a playful "dice" generator to surface careers aligned with user profiles (MI/CDT/Bartle/Johari) plus labor-market relevance.

## Features Implemented

### 1. Smart Filters
- **Demand Horizon**: Trending now, High growth (5y), Future-proof (10y+), Stable, Automation-resistant
- **Education/Training**: No degree, Certificate/Bootcamp, Bachelor, Advanced
- **Work Environment**: Remote-friendly, Hybrid, Outdoor, Hands-on, Solo, Collaborative, Client-facing, Structured, Flexible
- **Role Orientation**: Analytical, Creative, Leadership, Technical, People-centered, Helping, Problem-solving, Adventure/Fieldwork
- **Compensation Band**: Lower, Middle, Upper, High-responsibility
- **Social Impact**: High social impact, Environmental, Community-oriented, Mission-driven
- **Toggles**: Remote-only, Stretch/Opposites

### 2. Dice Button (🎲 Surprise me)
- Sets novelty_bias to 0.8-1.0 (random)
- Increases creativity and unexpected suggestions
- Visual badge on results: "Dice Roll"

### 3. Enhanced Career Cards
New meta chips display:
- Demand horizon tags (e.g., "High growth 5y")
- Education level (e.g., "Certificate")
- Compensation band (e.g., "Middle pay")
- Work environment tags (e.g., "Remote", "Collaborative")
- Social impact tags (e.g., "Environmental")

### 4. Filter Persistence
- Filters saved to `localStorage`
- Persists across sessions
- "Reset filters" button to clear all

### 5. Analytics Logging
Console events logged:
- `career_generate_clicked` - with seed_career, filters, novelty_bias
- `career_dice_clicked` - with seed_career, novelty_bias

## Architecture

### Backend (PHP)

#### New Endpoint: `mc_lab_career_suggest`
- **Location**: `micro-coach-ai-lab.php`
- **Method**: `ajax_career_suggest()`
- **Accepts**:
  - `seed_career` (string, optional)
  - `filters` (JSON string)
  - `novelty_bias` (float, 0-1)
  - `limit_per_bucket` (int, default 6)

#### Enhanced Profile Method
- **Method**: `get_enhanced_career_profile()`
- **Returns**: MI top 3, CDT scores (snake_case), Bartle types, Johari adjectives

#### AI Generation Method
- **Method**: `generate_career_suggestions_with_filters()`
- **OpenAI Model**: Uses selected model (GPT-4o-mini or GPT-4o)
- **Temperature**: Dynamic based on novelty_bias (0.7 + novelty * 0.3)
- **System Prompt**: Comprehensive instructions for filter compliance

### Frontend (JavaScript)

#### New Methods Added to `LabModeApp`

**Filter Management**:
- `getDefaultFilters()` - Returns default filter state
- `loadFiltersFromStorage()` - Loads from localStorage
- `saveFiltersToStorage()` - Saves to localStorage
- `renderFiltersBar()` - Renders filter chips UI
- `toggleFilterDrawer(filterType)` - Shows/hides filter drawers
- `renderFilterDrawer(filterType)` - Renders drawer content
- `updateFilter(filterType, value, checked, isMulti)` - Updates filter state
- `updateFilterChipCounts()` - Updates badge counts
- `resetFilters()` - Clears all filters

**Generation**:
- `generateCareerIdeas()` - Standard generation (novelty 0.25)
- `rollCareerDice()` - High novelty generation (0.8-1.0)
- `callCareerSuggestAPI(seedCareer, noveltyBias, isDice)` - Makes AJAX call

**Rendering**:
- `displayCareerSuggestions(data, seedCareer, isDice)` - Displays results
- `renderCareerClusterEnhanced(title, description, careers, clusterType)` - Renders cluster
- `renderCareerCardEnhanced(career, index, clusterType)` - Renders card with meta
- `renderCareerMeta(meta)` - Renders meta chips
- `formatMetaLabel(category, value)` - Formats labels

### CSS Styles

**New Classes**:
- `.career-input-row` - Input + buttons layout
- `.lab-btn-dice` - Dice button styling (orange gradient)
- `.career-filters-bar` - Filter container
- `.filter-chip` - Filter button with count badge
- `.filter-chip-count` - Badge showing active filter count
- `.filter-toggle` - Checkbox toggle styling
- `.filter-drawer` - Dropdown filter panel
- `.filter-option` - Individual filter option
- `.career-meta-chips` - Meta chip container
- `.meta-chip-*` - Various meta chip styles (demand, education, comp, env, impact)
- `.dice-roll` - Dice badge styling

## Usage

### For Users

1. Navigate to Career Explorer tab
2. (Optional) Enter a seed career or leave blank for profile-based suggestions
3. Click filter chips to open filter drawers
4. Select desired filters (multi-select for most)
5. Click "Generate Career Ideas" or "🎲 Surprise me"
6. Review results in three buckets: Adjacent, Parallel, Wildcard
7. Use card actions: "Not interested", "Save ♥", "Why this fits me"

### For Developers

#### Testing the Endpoint Directly

```javascript
// Example AJAX call
jQuery.ajax({
    url: labMode.ajaxUrl,
    method: 'POST',
    data: {
        action: 'mc_lab_career_suggest',
        nonce: labMode.nonce,
        seed_career: 'UX Designer',
        filters: JSON.stringify({
            demand_horizon: 'high_growth_5y',
            education_levels: ['bachelor'],
            work_env: ['remote_friendly', 'collaborative'],
            role_orientation: ['creative', 'analytical'],
            comp_band: 'middle',
            social_impact: [],
            remote_only: false,
            stretch_opposites: false
        }),
        novelty_bias: 0.25,
        limit_per_bucket: 6
    },
    success: (response) => {
        console.log(response.data);
        // { adjacent: [...], parallel: [...], wildcard: [...] }
    }
});
```

#### Modifying System Prompt

Edit `build_career_suggest_system_prompt()` in `micro-coach-ai-lab.php`:

```php
private function build_career_suggest_system_prompt() {
    return 'You generate career suggestions...
    // Your custom instructions here
    ';
}
```

#### Adding New Filter Options

1. Add to filter options in `renderFilterDrawer()` (lab-mode.js)
2. Add to `formatMetaLabel()` for display mapping
3. Update system prompt if needed
4. Add CSS for new meta chip type if desired

## Data Contracts

### Request Payload

```json
{
  "seed_career": "UX Designer",
  "profile": {
    "mi_top3": [
      {"slug": "spatial", "score": 95},
      {"slug": "logical-mathematical", "score": 93}
    ],
    "cdt_scores": {
      "discomfort_regulation": 64,
      "conflict_resolution_tolerance": 64
    },
    "cdt_top": "discomfort_regulation",
    "cdt_edge": "self_confrontation_capacity",
    "bartle": {"primary": "explorer", "secondary": "socializer"},
    "johari": ["curious", "analytic", "observant"]
  },
  "filters": {
    "demand_horizon": "high_growth_5y",
    "education_levels": ["bachelor"],
    "work_env": ["remote_friendly"],
    "role_orientation": ["analytical", "creative"],
    "comp_band": "middle",
    "social_impact": [],
    "remote_only": false,
    "stretch_opposites": false
  },
  "novelty_bias": 0.25,
  "limit_per_bucket": 6
}
```

### Response Format

```json
{
  "adjacent": [
    {
      "title": "Product Designer",
      "why_it_fits": "Translates your spatial + logical strengths into end-to-end product craft.",
      "profile_match": {
        "mi": ["spatial", "logical-mathematical"],
        "cdt_top": "discomfort_regulation",
        "bartle": "explorer",
        "johari": ["curious", "analytic"]
      },
      "meta": {
        "demand_horizon": "high_growth_5y",
        "education": "bachelor",
        "work_env": ["remote_friendly", "collaborative"],
        "comp_band": "middle",
        "social_impact": []
      }
    }
  ],
  "parallel": [...],
  "wildcard": [...]
}
```

## Acceptance Criteria ✅

- [x] Filters persist to localStorage
- [x] Results respect filters (reduce count if impossible)
- [x] Dice increases novelty and changes suggestions
- [x] "Why this fits?" cites MI/CDT/Bartle/Johari
- [x] No layout regressions to existing Cards/Grid
- [x] All responses from AI are valid JSON only
- [x] Analytics events logged to console

## Browser Compatibility

- Chrome/Edge: ✅
- Firefox: ✅
- Safari: ✅ (localStorage, CSS grid, fetch API)

## Performance Notes

- Filter state: ~1KB in localStorage
- API calls: 30-60 second timeout
- AI response: typically 5-15 seconds
- Temperature scaling improves creative diversity with novelty

## Future Enhancements

1. **URL Query Params**: Add filter state to URL for sharing
2. **Filter Presets**: Quick-select filter combinations
3. **More Meta Fields**: Industry, Company size, Growth stage
4. **Career Comparison**: Side-by-side career comparison modal
5. **Saved Searches**: Save filter combinations for re-use
6. **Export Results**: Download career suggestions as PDF

## Troubleshooting

### Filters not saving
- Check browser localStorage is enabled
- Clear localStorage: `localStorage.removeItem('career_explorer_filters')`

### AI not respecting filters
- Check error logs in browser console
- Verify filter JSON is well-formed
- Check OpenAI API key is configured

### Meta chips not showing
- Ensure AI is returning `meta` object in response
- Check CSS is loaded (lab-mode.css)
- Verify `renderCareerMeta()` is called

## Credits

Implementation based on product brief specifications. OpenAI API integration for AI-powered suggestions.
