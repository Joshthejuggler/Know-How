# Peer Assessment Authentication Flow Audit

## Current Sequence

```
1. User clicks peer link: /johari-x-mi-assessment/?jmi=UUID
2. Page loads, johari-mi-quiz.js init() function runs
3. Detects peerUuid from URL params
4. Calls renderPeerAssessment()
5. Checks if user is logged in (!isLoggedIn)
6. Shows generic login screen with WordPress default login/register links
7. User clicks "Sign Up" → wp-login.php?action=register&redirect_to=...
8. User fills form, submits registration
9. WordPress creates user account
10. handle_peer_registration_login() PHP method attempts auto-login
11. Auto-login often fails due to timing/detection issues
12. User lands back on peer assessment page but still not logged in
13. User sees same login screen or refresh button
```

## Decision Points Analysis

### JavaScript (johari-mi-quiz.js)

**Line 82-84**: `peerUuid = urlParams.get('jmi')`
- ✅ Correctly extracts UUID from URL
- ⚠️ No validation of UUID format

**Line 103-106**: Peer assessment mode detection
```javascript
if (peerUuid) {
    isAuthorMode = false;
    appState = 'peer-assessment';
    renderPeerAssessment();
}
```
- ✅ Correctly enters peer mode
- ✅ Sets proper app state

**Line 181**: Login state check
```javascript
if (!isLoggedIn) {
```
- ⚠️ Uses hybrid detection: `!!currentUser || document.body.classList.contains('logged-in')`
- ❌ Body class detection is unreliable after registration
- ❌ currentUser may be null even when logged in

**Line 183-184**: URL construction
```javascript
const loginUrl = `${window.location.origin}/wp-login.php?redirect_to=${encodeURIComponent(currentUrl)}`;
const registerUrl = `${window.location.origin}/wp-login.php?action=register&redirect_to=${encodeURIComponent(currentUrl)}`;
```
- ✅ Preserves current URL with JMI parameter
- ✅ Proper URL encoding

**Line 95-101**: Post-registration reload logic
```javascript
const justRegistered = urlParams.get('registered') || urlParams.get('login');
if (justRegistered && peerUuid && !isLoggedIn) {
    setTimeout(() => { window.location.reload(); }, 2000);
}
```
- ❌ Relies on URL params that WordPress doesn't set
- ❌ 2-second delay is arbitrary and may not be enough

### PHP (module.php handle_peer_registration_login)

**Line 1024**: Entry condition check
```php
if (!isset($_GET['jmi']) || is_user_logged_in()) { return; }
```
- ✅ Only processes peer assessment pages
- ✅ Skips if already logged in

**Line 1029-1036**: Registration success detection
```php
$has_registration_success = (
    isset($_GET['checkemail']) && $_GET['checkemail'] === 'registered'
) || (
    isset($_GET['registration']) && $_GET['registration'] === 'complete'
) || (
    isset($_GET['redirect_to']) && strpos($_GET['redirect_to'], 'jmi=') !== false
);
```
- ❌ WordPress doesn't set 'registration=complete' parameter
- ⚠️ 'checkemail=registered' only for email verification flows
- ❌ 'redirect_to' check is backward logic

**Line 1047-1053**: Recent user detection
```php
$recent_user = $wpdb->get_row($wpdb->prepare(
    "SELECT * FROM {$wpdb->users} 
     WHERE user_registered > %s 
     ORDER BY user_registered DESC 
     LIMIT 1",
    date('Y-m-d H:i:s', strtotime('-2 minutes'))
));
```
- ❌ Race condition: Multiple users registering simultaneously
- ❌ Time window too broad (2 minutes)
- ❌ No association between registration and current session

**Line 1061-1064**: Referrer check
```php
$referrer = wp_get_referer();
if (!$referrer || strpos($referrer, 'jmi=') === false) { return; }
```
- ❌ wp_get_referer() often empty after form submission
- ❌ Referrer can be spoofed or blocked by browser settings

**Line 1070-1071**: Auto-login execution
```php
wp_set_current_user($recent_user->ID);
wp_set_auth_cookie($recent_user->ID, true);
```
- ✅ Proper WordPress authentication functions
- ⚠️ No error handling if auth cookie fails

## Identified Pain Points

### Critical Issues
1. **Unreliable Auto-Login Detection**: The method to detect successful registration is fundamentally flawed
2. **Race Conditions**: Recent user lookup can match wrong user in concurrent scenarios  
3. **Brittle Referrer Logic**: Depends on unreliable HTTP referrer header
4. **Inconsistent Login State**: JavaScript login detection fails after registration

### UX Issues
1. **Generic Login Screen**: No explanation of why account is needed
2. **No Context**: Users don't understand peer assessment value
3. **Confusing Flow**: Users get stuck in refresh loops
4. **No Progress Indicators**: No feedback during registration/login process

### Technical Debt
1. **Duplicate Code**: Multiple login status check implementations
2. **Hard-coded Timeouts**: Magic numbers (2 seconds, 2 minutes)
3. **Missing Error Handling**: Silent failures with no logging
4. **No UUID Validation**: Malformed UUIDs could cause issues

## Recommended Fixes

### Immediate (High Priority)
1. Replace heuristic user detection with proper WordPress hooks
2. Store JMI UUID in secure session/transient during registration flow
3. Implement proper AJAX-based login state polling
4. Create explanatory custom login screen

### Medium Priority  
1. Add comprehensive error logging and user feedback
2. Implement UUID validation and sanitization
3. Create automated tests for registration flow
4. Add progressive enhancement for JS-disabled users

### Nice to Have
1. Remember user preferences for future peer assessments
2. Add social login options (Google, Facebook)
3. Implement email-based verification flow
4. Add admin dashboard for monitoring failed registrations