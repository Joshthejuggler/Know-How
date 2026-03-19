# Debug Information Styling Fixes

## Problem
The debug information section on experiment cards was displaying with poor contrast:
- Dark background with light text that was difficult to read
- Inconsistent styling between light and dark modes
- Code elements had conflicting CSS styles

## Root Cause
The `.debug-code` and `.debug-code pre` CSS rules were using semi-transparent backgrounds and relying on CSS variables that weren't providing sufficient contrast.

## Fixes Applied

### 1. Light Mode Styling (Default)
**Before:**
```css
.debug-code {
    background: rgba(0, 0, 0, 0.05);
    border: 1px solid rgba(0, 0, 0, 0.1);
}

.debug-code pre {
    color: #2d3748;
}
```

**After:**
```css
.debug-code {
    background: #f8fafc;      /* Light gray background */
    border: 1px solid #e2e8f0; /* Subtle border */
}

.debug-code pre {
    color: #2d3748;           /* Dark text */
    background: transparent;   /* Explicit transparency */
}
```

### 2. Dark Mode Styling
**Before:**
```css
.debug-code {
    background: rgba(255, 255, 255, 0.05);
    border-color: rgba(255, 255, 255, 0.1);
}

.debug-code pre {
    color: #e2e8f0;
}
```

**After:**
```css
.debug-code {
    background: #1a202c;      /* Dark background */
    border-color: #2d3748;    /* Darker border */
}

.debug-code pre {
    color: #e2e8f0;           /* Light text */
    background: transparent;   /* Explicit transparency */
}

.debug-code code {
    color: #e2e8f0 !important; /* Override any conflicting styles */
    background: none !important;
}
```

### 3. Code Element Override
Added important declarations to prevent other CSS from interfering:
```css
.debug-code code {
    font-family: inherit;
    background: none !important;
    padding: 0 !important;
    border-radius: 0;
    color: inherit !important;
    border: none !important;
    font-size: inherit;
}
```

## Expected Results

### Light Mode
- Light gray background (`#f8fafc`)
- Dark text (`#2d3748`) on light background
- Clear, readable contrast

### Dark Mode  
- Dark background (`#1a202c`) 
- Light text (`#e2e8f0`) on dark background
- Proper contrast for readability

### All Modes
- No conflicting styles from other CSS rules
- Consistent monospace font rendering
- Proper text wrapping and overflow handling

## Testing
After applying these fixes:

1. **Light Mode Test:**
   - Open an experiment card
   - Click "Why This?" button
   - Verify debug section has light background with dark, readable text

2. **Dark Mode Test:** 
   - Switch system to dark mode (or use browser dev tools to simulate)
   - Open an experiment card  
   - Click "Why This?" button
   - Verify debug section has dark background with light, readable text

3. **Responsive Test:**
   - Test on different screen sizes
   - Verify code wrapping works properly
   - Check that JSON formatting is preserved

## Files Modified
- `/assets/lab-mode.css` - Updated debug styling rules

The debug information should now be clearly readable in both light and dark modes with proper contrast ratios.