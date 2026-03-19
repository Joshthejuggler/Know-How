# AI Loading Overlay Usage Guide

The AI Loading Overlay component provides a fullscreen loading state with animated scrolling text while AI processes are running.

## Basic Usage

```javascript
// Show loading overlay with default messages
AILoadingOverlay.show();

// Hide the overlay
AILoadingOverlay.hide();

// Complete progress animation and auto-hide
AILoadingOverlay.completeProgress();
```

## Advanced Usage

```javascript
// Custom messages and subtitle
AILoadingOverlay.show({
    messages: [
        "Analyzing your data patterns…",
        "Cross-referencing with your preferences…",
        "Calibrating personalized recommendations…",
        "Almost ready with your results…"
    ],
    subtitle: "AI is crafting your personalized suggestions… 🤖"
});

// Manual progress control
AILoadingOverlay.setProgress(25); // Set to 25%
AILoadingOverlay.setProgress(75); // Set to 75%
AILoadingOverlay.completeProgress(); // Complete and hide

// Update subtitle dynamically
AILoadingOverlay.updateSubtitle("Processing additional data…");

// Add messages during operation
AILoadingOverlay.addMessage("Finalizing recommendations based on new data…");
```

## Event Handling

```javascript
// Listen for cancel events
$(document).on('ai-loading-cancelled', function() {
    // User clicked cancel or pressed ESC
    console.log('AI loading was cancelled');
    // Handle cancellation (e.g., abort AJAX requests)
});
```

## Integration with AJAX Requests

```javascript
function performAIAnalysis() {
    // Show loading overlay
    AILoadingOverlay.show({
        messages: [
            "Processing your input data…",
            "Running AI analysis algorithms…",
            "Generating personalized results…",
            "Almost finished with your analysis…"
        ],
        subtitle: "AI is analyzing your profile… 📊"
    });
    
    // Make AJAX request
    $.ajax({
        url: '/api/ai-analysis',
        type: 'POST',
        data: { /* your data */ },
        success: function(response) {
            if (response.success) {
                // Complete progress and show results
                AILoadingOverlay.completeProgress();
                // Results will appear after overlay fades out
            } else {
                AILoadingOverlay.hide();
                showError(response.message);
            }
        },
        error: function() {
            AILoadingOverlay.hide();
            showError('Network error occurred');
        }
    });
    
    // Handle cancellation
    $(document).off('ai-loading-cancelled.analysis').on('ai-loading-cancelled.analysis', function() {
        // Abort the AJAX request if possible
        AILoadingOverlay.hide();
        // Return to previous state
    });
}
```

## Available Methods

| Method | Parameters | Description |
|--------|------------|-------------|
| `show(options)` | `{messages: string[], subtitle: string}` | Display the loading overlay |
| `hide()` | None | Hide the overlay immediately |
| `completeProgress()` | None | Complete progress bar and hide after delay |
| `setProgress(percent)` | `number (0-100)` | Set progress bar percentage |
| `updateSubtitle(text)` | `string` | Update the subtitle text |
| `addMessage(message)` | `string` | Add a new message to rotation |

## Styling Options

The overlay includes several data attributes for context-specific styling:

```javascript
// Add context for specific styling
$('#ai-loading-overlay').attr('data-context', 'experiment-generation');
```

Available contexts:
- `experiment-generation` (adds 🧪 emoji)
- `role-model-analysis` (adds 🎯 emoji)  
- `profile-analysis` (adds 📊 emoji)

## Accessibility Features

- `role="status"` for screen readers
- `aria-live="polite"` for message updates
- ESC key support for cancellation
- Focus management with cancel button
- Reduced motion support for users with motion sensitivity
- High contrast mode compatibility

## Mobile Responsiveness

- Switches to vertical scroll animation on mobile
- Optimized touch targets
- Responsive typography and spacing
- Prevents body scrolling when active

## Browser Support

- Modern browsers with CSS transform support
- Graceful degradation for older browsers
- No external dependencies beyond jQuery