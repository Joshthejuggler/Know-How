# Custom Request JavaScript Error Fixes

## Issue Resolved

**Error:** `Uncaught TypeError: (exp.riskLevel || "medium").toLowerCase is not a function`

**Root Cause:** The `riskLevel` and `archetype` properties in experiment objects were `null`, `undefined`, or non-string values, causing `.toLowerCase()` to fail.

## Fixes Applied

### 1. Fixed `riskLevel` toLowerCase Error (Line 499)
**Before:**
```javascript
<span class="risk-level risk-${(exp.riskLevel || 'medium').toLowerCase()}">
```

**After:**
```javascript
<span class="risk-level risk-${String(exp.riskLevel || 'medium').toLowerCase()}">
```

### 2. Fixed `archetype` toLowerCase Error (Line 472)
**Before:**
```javascript
<span class="archetype-badge archetype-${exp.archetype?.toLowerCase() || 'discover'}">
```

**After:**
```javascript
<span class="archetype-badge archetype-${String(exp.archetype || 'discover').toLowerCase()}">
```

### 3. Enhanced Error Handling and Validation
- Added better input validation with minimum character requirements
- Improved AJAX error handling with specific error messages
- Enhanced loading state management
- Added proper state restoration after errors
- Increased timeout from 30s to 45s for AI processing

### 4. Better User Feedback
- More specific error messages for different failure scenarios
- Input focus management for validation errors  
- Warning prompts for potentially destructive modifications
- Better loading state indicators

## Testing the Fix

### Step 1: Verify JavaScript Loads Without Errors
1. Open browser Developer Tools (F12)
2. Go to Console tab
3. Navigate to Lab Mode
4. Open an experiment for iteration
5. Check for any red JavaScript errors

### Step 2: Test Custom Request Functionality
1. Enter text in the Custom Request field (try "Make this more collaborative")
2. Verify character counter updates
3. Verify button enables when text is entered
4. Click "Apply Custom Change"
5. Check that loading state appears
6. Wait for either success or error message

### Expected Results After Fix
- ✅ No JavaScript TypeError about `.toLowerCase()`
- ✅ Custom Request button should enable/disable properly
- ✅ Loading state should show when request is sent
- ✅ Better error messages if request fails
- ✅ Experiment should update if request succeeds

## If Issues Persist

### Check These Common Problems:

1. **Still Getting Loading Stuck:**
   - Check Network tab for AJAX request status
   - Look for 403 (permission), 404 (endpoint missing), or 500 (server error)
   - Check WordPress error logs for PHP errors

2. **Button Not Enabling:**
   - Verify jQuery is loaded properly
   - Check for other JavaScript errors preventing event handlers

3. **AJAX Request Failing:**
   - Ensure user is logged in with proper permissions
   - Check OpenAI API key is configured
   - Verify nonce is being generated properly

### Debug Commands (Run in Browser Console)
```javascript
// Test button state
console.log('Custom button:', $('.iteration-custom-btn').length);
console.log('Is disabled:', $('.iteration-custom-btn').prop('disabled'));

// Test input handling
$('#iteration-custom-text').val('test').trigger('input');

// Check for IterationPanel object
console.log('IterationPanel available:', typeof window.IterationPanel);

// Check labMode configuration
console.log('labMode config:', window.labMode);
```

## Files Modified

1. `/assets/lab-mode-iterate.js` - Fixed toLowerCase errors and enhanced error handling
2. `/micro-coach-ai-lab.php` - Enhanced backend validation and error messages

The main JavaScript error should now be resolved, allowing the Custom Request feature to function properly.