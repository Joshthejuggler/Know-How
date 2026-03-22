# Custom Request Feature Troubleshooting Guide

## Overview

This guide helps diagnose and fix issues with the Custom Request feature in the Lab Mode "Refine Experiment" modal.

## Quick Diagnostic Steps

### Step 1: Check Browser Console
1. Open browser Developer Tools (F12)
2. Go to the Console tab
3. Navigate to Lab Mode and open an experiment for iteration
4. Enter text in Custom Request field and click "Apply Custom Change"
5. Look for any JavaScript errors in red

**Common Console Errors:**
- `Uncaught TypeError: Cannot read property 'nonce' of undefined` → Missing labMode configuration
- `AJAX error: 403 Forbidden` → Permission or nonce issue
- `AJAX error: 404 Not Found` → AJAX endpoint not registered
- `AJAX error: 500 Internal Server Error` → PHP error on backend

### Step 2: Check Network Tab
1. In Developer Tools, go to Network tab
2. Try submitting a custom request
3. Look for the `admin-ajax.php` request
4. Check the status code and response

**Expected:** Status 200 with JSON response
**If 403:** Permission/authentication issue
**If 404:** AJAX action not registered
**If 500:** Server error (check PHP logs)

### Step 3: Check PHP Error Logs
Look for entries containing "Lab Mode Iterate" in your WordPress error logs.

**Common PHP Errors:**
- "Nonce verification failed" → Security token issue
- "Permission denied" → User doesn't have proper permissions
- "OpenAI API key not configured" → Missing API key
- "Failed to decode AI response" → AI API error

## Detailed Troubleshooting

### Issue: Button Doesn't Enable When Typing

**Symptoms:**
- Text is entered but "Apply Custom Change" button remains disabled
- Character counter not updating

**Causes & Solutions:**
1. **JavaScript not loaded properly**
   - Check console for script loading errors
   - Verify `lab-mode-iterate.js` is enqueued
   
2. **jQuery conflicts**
   - Other plugins might be interfering with jQuery
   - Check for `$ is not defined` errors
   
3. **CSS class conflicts**
   - Button might have conflicting CSS classes
   - Check if `.disabled` class is being overridden

**Debug Steps:**
```javascript
// Test in browser console:
$('#iteration-custom-text').val('test text').trigger('input');
console.log('Button disabled?', $('.iteration-custom-btn').prop('disabled'));
```

### Issue: Request Stuck on "AI is refining your experiment..."

**Symptoms:**
- Loading spinner shows indefinitely
- No error message appears
- Modal becomes unresponsive

**Most Common Causes:**

1. **AJAX Request Never Completes**
   - Check Network tab for pending requests
   - Look for timeout or connection errors
   
2. **PHP Fatal Error on Backend**
   - Check WordPress error logs
   - Common: Memory exhaustion, missing classes
   
3. **OpenAI API Issues**
   - API key invalid/expired
   - Rate limiting
   - API service downtime
   
4. **JavaScript Error in Success Handler**
   - Check console during the request
   - Error in `handleIterateSuccess` function

**Debug Steps:**
```javascript
// Check if request is still loading
console.log('Is loading?', window.IterationPanel?.isLoading);

// Force clear loading state (temporary fix)
$('.iteration-loading').hide();
$('.iteration-modifier-groups').show();
$('.iteration-modifier-btn').prop('disabled', false);
```

### Issue: Custom Request Appears to Work But Nothing Happens

**Symptoms:**
- No loading state shown
- No error message
- Experiment doesn't change

**Causes:**
1. **Event handler not bound**
   - Custom button click not captured
   - Check for `data-mod-type="Custom"` attribute
   
2. **Modifier not created properly**
   - Check console logs for modifier object
   
3. **AJAX request not sent**
   - Check Network tab for outgoing requests

**Debug Steps:**
```javascript
// Test custom modifier handling
$('.iteration-custom-btn').trigger('click');

// Check if sendModifier is called
console.log('Send modifier function:', typeof window.IterationPanel?.sendModifier);
```

### Issue: Permission/Authentication Errors

**Symptoms:**
- "Insufficient permissions" error
- 403 Forbidden responses
- "Security verification failed" message

**Solutions:**
1. **Refresh the page** - Nonce might be expired
2. **Check user permissions** - User needs Lab Mode access
3. **Verify plugin activation** - Main plugin must be active
4. **Check for caching** - Page caching might serve old nonces

### Issue: AI Processing Errors

**Symptoms:**
- "Failed to iterate experiment" with API-related messages
- Long delays before error messages
- Inconsistent behavior

**Solutions:**
1. **Check OpenAI API key**
   - Verify key is set in plugin settings
   - Test key validity with simple API call
   
2. **Check API quotas and limits**
   - Verify OpenAI account has available credits
   - Check for rate limiting
   
3. **Simplify custom request**
   - Try shorter, simpler modification requests
   - Avoid complex or ambiguous language

## Configuration Checklist

### WordPress Environment
- [ ] WordPress 5.0 or higher
- [ ] PHP 7.4 or higher
- [ ] cURL extension enabled
- [ ] OpenSSL extension enabled
- [ ] Memory limit at least 128MB

### Plugin Configuration
- [ ] Main Micro Coach AI plugin activated
- [ ] Lab Mode feature enabled
- [ ] OpenAI API key configured
- [ ] User has proper permissions

### Browser Environment
- [ ] Modern browser (Chrome 70+, Firefox 65+, Safari 12+)
- [ ] JavaScript enabled
- [ ] No ad blockers interfering with AJAX
- [ ] Local storage available

## Emergency Reset Procedures

### Reset Modal State
```javascript
// Force close and reopen iteration modal
if (window.IterationPanel) {
    window.IterationPanel.close();
    // Wait a moment, then reopen with original experiment
}
```

### Clear Plugin Caches
```php
// Add to WordPress admin or wp-config.php temporarily
delete_transient('lab_mode_cache');
wp_cache_flush();
```

### Reset User Session
1. Log out of WordPress
2. Clear browser cache and cookies
3. Log back in
4. Try Custom Request again

## Getting Additional Help

### Information to Gather
When reporting issues, include:
1. WordPress version
2. Plugin version
3. Browser and version
4. Complete console error messages
5. Network request details
6. PHP error log entries
7. Steps to reproduce

### Debug Mode
Enable WordPress debug mode in `wp-config.php`:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

### Test Environment
Use the included test files:
1. `debug-custom-request.html` - Frontend testing
2. `test-ajax-endpoint.php` - Backend testing

## Prevention

### Best Practices
1. Keep plugins updated
2. Regular WordPress maintenance
3. Monitor error logs
4. Test in staging environment first
5. Backup before making changes

### User Guidelines
1. Keep custom requests clear and specific
2. Avoid overly complex modifications
3. Stay under 500 character limit
4. Use simple, actionable language
5. Refresh page if experiencing issues