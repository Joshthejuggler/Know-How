# Quiz Funnel Feature

## Overview

The Quiz Funnel is a visual progress system that guides users through your Self-Discovery assessments in a structured, step-by-step manner. It shows users their progress, unlocks quizzes sequentially, and provides a clear path through their learning journey.

## Features

### ✅ Visual Progress Tracking
- Beautiful funnel-style interface showing all assessment steps
- Clear visual indicators for completed, available, and locked states
- Responsive design that works on all devices

### ✅ Progressive Unlocking
- First assessment is always available
- Subsequent assessments unlock only after completing previous ones
- Smart unlock logic prevents users from getting lost

### ✅ Admin Control
- Drag-and-drop reordering of assessments
- Custom titles for each step
- Optional placeholder for future assessments
- Live preview of changes

### ✅ User Experience
- Click to start available assessments
- Hover tooltips explain locked steps
- Smooth transitions and loading states
- Accessibility-compliant navigation

## Quick Start

### 1. Display the Funnel
Add the funnel to any page using the shortcode:
```
[quiz_funnel]
```

### 2. Configure in Admin
Navigate to: **WordPress Admin > Quiz Platform > Funnel**
- Drag and drop to reorder steps
- Click on titles to edit them
- Configure placeholder step (optional)
- Preview changes in real-time

### 3. Customize Appearance
Use the `style` parameter for different layouts:
```
[quiz_funnel style="compact"]
[quiz_funnel show_description="false"]
```

## Technical Implementation

### Data Structure
The funnel stores its configuration in WordPress options:
```php
$config = [
    'steps' => ['mi-quiz', 'cdt-quiz', 'bartle-quiz', 'placeholder'],
    'titles' => [
        'mi-quiz' => 'Multiple Intelligences Assessment',
        'cdt-quiz' => 'Cognitive Dissonance Tolerance Quiz',
        'bartle-quiz' => 'Player Type Discovery',
        'placeholder' => 'Advanced Self-Discovery Module'
    ],
    'placeholder' => [
        'title' => 'Advanced Self-Discovery Module',
        'description' => 'Coming soon...',
        'target' => '',
        'enabled' => false
    ]
];
```

### Key Components

#### MC_Funnel Class (`includes/class-mc-funnel.php`)
- Manages funnel configuration and state
- Handles completion status logic
- Provides URL resolution for quiz steps

#### Frontend Assets
- `assets/funnel.css` - Responsive styles
- `assets/funnel.js` - Interactive functionality
- Automatic enqueuing when shortcode is used

#### Admin Interface
- `assets/funnel-admin.css` - Admin styling
- `assets/funnel-admin.js` - Drag-and-drop and AJAX
- Integrated into Quiz Platform settings

### Unlock Logic
1. **First Step**: Always unlocked
2. **Subsequent Steps**: Unlocked when previous step is completed
3. **Placeholder**: Only unlocked if enabled and all quizzes completed

### Completion Detection
Uses existing quiz result meta keys:
- `miq_quiz_results` (Multiple Intelligences)
- `cdt_quiz_results` (Cognitive Dissonance Tolerance)
- `bartle_quiz_results` (Player Type)

## Customization

### CSS Customization
Override funnel styles by adding custom CSS:
```css
.mc-funnel-step.available {
    background: linear-gradient(135deg, #your-color 0%, #your-color-2 100%);
}
```

### JavaScript Events
Listen for completion events to refresh the funnel:
```javascript
$(document).trigger('mc-quiz-completed', {
    quizId: 'mi-quiz',
    quizTitle: 'Multiple Intelligences'
});
```

### Shortcode Parameters
- `show_description` - Show/hide quiz descriptions (default: "true")
- `style` - Layout style: "default" or "compact" (default: "default")

## Database Schema

### Options Table
- `mc_quiz_funnel_config` - Stores complete funnel configuration

### User Meta
- Existing quiz result keys for completion detection
- Leverages dashboard cache for performance

### Cache Keys
- `dashboard_data_{user_id}` - Includes funnel completion state
- Automatically cleared when quiz results change

## Development Notes

### Adding New Quizzes
1. Register quiz with `Micro_Coach_Core::register_quiz()`
2. Quiz automatically appears in funnel configuration
3. Admin can reorder and customize title

### Performance Considerations
- Completion status cached per user
- Dashboard data cached for 15 minutes
- Page finding results cached for 24 hours

### Browser Compatibility
- Modern browsers (ES6+)
- Graceful degradation for older browsers
- Accessibility-compliant (ARIA, keyboard navigation)

## Troubleshooting

### Funnel Not Appearing
1. Check if `MC_Funnel` class is loaded
2. Verify shortcode syntax: `[quiz_funnel]`
3. Check browser console for JavaScript errors

### Quiz Not Unlocking
1. Verify previous quiz completion in user meta
2. Check funnel configuration in admin
3. Clear dashboard cache if needed

### Admin Interface Issues
1. Check user has `manage_options` capability
2. Verify AJAX nonces are working
3. Check browser console for errors

## Version History

### v10.0.0 (Current)
- ✅ Complete funnel system implementation
- ✅ Admin interface with drag-and-drop
- ✅ Progressive unlock system
- ✅ Responsive design and accessibility
- ✅ Integration with existing quiz platform

## Support

For technical support or feature requests, please refer to the main plugin documentation or contact the development team.