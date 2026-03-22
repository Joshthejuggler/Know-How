# Enhanced Flow Testing Guide

This document outlines how to test the new enhanced user flow after Bartle quiz completion.

## Overview

The enhanced flow provides a progressive reveal experience for new users who complete the Bartle quiz after January 1, 2024. Users who completed Bartle before this date continue with the legacy experience.

## Quick Testing Workflow

**For immediate testing without creating new users:**

1. **Login as any existing user**
2. **Go to Bartle quiz page** 
3. **Click "🔄 Retake Quiz"** (if you have previous results)
4. **Complete the quiz** - System will automatically set `enhanced_flow: true`
5. **Click "View Your Self-Discovery Profile"** - Go to dashboard
6. **Experience the enhanced flow:**
   - Only Self-Discovery Profile tab visible initially
   - All dashboard overview sections hidden (progress, insights & activity)
   - Scroll down the Self-Discovery Profile to reveal "🚀 Try Your AI Coach" button
   - Click button to unlock:
     - All hidden dashboard sections reappear with animations
     - All tabs become visible with reordered layout
     - AI Coach tab becomes active

## Testing Requirements

### 1. Create Two Test Users

#### Legacy User (Pre-Migration)
```php
// In WordPress admin or via wp-cli, create a user and manually set old Bartle results:
$user_id = 123; // Replace with actual user ID
update_user_meta($user_id, 'bartle_quiz_results', [
    'completed_at' => 1672531200, // Before 2024-01-01 (timestamp for 2023-01-01)
    'sortedScores' => [
        ['explorer', 25],
        ['achiever', 20],
        ['socializer', 15],
        ['strategist', 10]
    ],
    // Note: no 'enhanced_flow' flag - this indicates legacy user
]);
```

#### New User (Post-Migration) 
Create a fresh user account and complete the Bartle quiz through the normal flow. The system will automatically set `enhanced_flow: true` since they're completing after the cutoff date.

**OR for testing existing users:**
Any user can retake the Bartle quiz to experience the enhanced flow. When they retake and complete the quiz, the system will:
- Set `enhanced_flow: true` (since current timestamp is after cutoff)
- Clear any existing `ai_coach_unlocked` flag
- Allow them to experience the progressive reveal again

### 2. Test Scenarios

#### Legacy User Tests
1. **Login as legacy user**
2. **Navigate to dashboard** - Should see all tabs immediately visible
3. **Verify no enhanced experience** - No "Try Your AI Coach" button should appear
4. **All tabs should be accessible** - AI Coach, Saved, Profile, Detailed Results
5. **Confirm normal behavior** - Everything works as before

#### New User Tests (Enhanced Flow)
1. **Complete Bartle quiz as new user** - Should see enhanced completion message
2. **Click "View Your Self-Discovery Profile"** - Should go to dashboard
3. **Initial state verification:**
   - Only "Self-Discovery Profile" tab should be visible
   - Other tabs (AI Coach, Saved, Detailed Results) should be hidden
   - Dashboard overview sections should be hidden (progress card, insights & activity)
   - Body should have `mc-enhanced-profile-only` class
4. **Scroll down the profile page** - "🚀 Try Your AI Coach" button should appear
5. **Click the button** - Should trigger unlock sequence:
   - Button shows "Unlocking AI Coach..." 
   - AJAX request to mark AI as unlocked
   - All hidden dashboard sections reappear (progress, insights & activity)
   - All tabs become visible with fade-in animation
   - Tabs reorder to: AI Coach, Saved, Profile, Detailed Results
   - AI Coach tab becomes active
   - Success message appears
6. **Refresh page** - Should maintain unlocked state (no re-hiding of tabs)

### 3. Mobile Testing

#### Responsive Behavior
- Test scroll threshold on smaller screens
- Verify button styling on mobile devices  
- Confirm tap interactions work properly
- Check animation performance on mobile

#### Mobile-Specific Checks
- Button should be appropriately sized (1rem font, adjusted padding)
- Scroll detection should account for mobile viewport differences
- Touch interactions should feel responsive

### 4. Cross-Browser Testing

Test the enhanced flow in:
- Chrome (latest)
- Firefox (latest) 
- Safari (latest)
- Edge (latest)

### 5. Error Handling

#### Network Failures
- Disconnect internet during "Try Your AI Coach" click
- Should show error message and re-enable button
- User should be able to retry

#### AJAX Failures  
- Test with invalid nonce (modify browser developer tools)
- Should show appropriate error message
- Should not break the interface

### 6. AI Coach Generation Error Messages

#### Testing OpenAI API Issues
1. **Invalid API Key Test:**
   - Go to Quiz Platform → Settings → AI Integration
   - Set OpenAI API Key to invalid value (e.g., "sk-invalid123")
   - Navigate to AI Coach tab and click "Show experiments that fit my settings"
   - Should display warning banner: ⚠️ "OpenAI API key not configured. Using backup experiment generator."
   - Banner should have yellow/amber styling (warning class)
   - Experiments should still generate using fallback system

2. **Missing API Key Test:**
   - Clear the OpenAI API Key field completely
   - Click "Show experiments that fit my settings" 
   - Should display warning banner with appropriate error message
   - Fallback experiments should still be generated

3. **Network Timeout Test:**
   - Use a valid API key but simulate network issues
   - Should display warning banner: ⚠️ "OpenAI request timed out. Using backup experiment generator."
   - Should use shorter timeout (15 seconds) to prevent UI freezing
   - Fallback to backup experiments should work seamlessly

4. **API Service Unavailable Test:**
   - When OpenAI API returns 500+ server errors
   - Should display warning banner: ⚠️ "OpenAI temporarily unavailable. Using backup experiment generator."
   - Should show retry behavior with longer timeout on second attempt
   - Eventually fall back to backup experiments if retry fails

#### Banner Visual Verification
- Warning banners should have amber/yellow background (`#fef3c7`)
- Warning text should be dark amber (`#92400e`)
- Info banners should have blue background (`#eff6ff`) 
- Banner should appear prominently above experiment results
- Banner should be dismissible by scrolling or interaction
- Multiple attempts should update banner message appropriately

#### Error Message User Experience
- Error messages should be friendly and non-technical
- Should clearly indicate that experiments are still available (using backup)
- Should not block the user from proceeding
- Should provide context about why AI generation failed
- Should encourage the user that they still get value from backup experiments

### 7. Performance Testing

#### Script Loading
- Verify `dashboard-enhanced.js` only loads for enhanced flow users
- Check that legacy users don't get unnecessary assets
- Confirm CSS only loads when needed

#### Animation Performance
- Test fade-in animations for smoothness
- Verify no layout shifts during tab reordering
- Check for any JavaScript errors in console

## Expected Behaviors Summary

### Legacy Users (Pre-2024)
- ✅ See all dashboard tabs immediately  
- ✅ No enhanced flow experience
- ✅ No "Try Your AI Coach" button
- ✅ Normal tab behavior and ordering

### Enhanced Flow Users (Post-2024)
- ✅ See enhanced Bartle completion message
- ✅ Initially see only "Self-Discovery Profile" tab
- ✅ "Try Your AI Coach" button appears on scroll
- ✅ Button unlocks and reorders tabs
- ✅ State persists across page loads
- ✅ All animations work smoothly

## Database Verification

### User Meta Fields
Check these user meta fields for enhanced flow users:
- `bartle_quiz_results['enhanced_flow']` = `true`
- `ai_coach_unlocked` = `1` (after button click)

### Legacy User Meta
Legacy users should have:
- `bartle_quiz_results` without `enhanced_flow` key
- No `ai_coach_unlocked` meta field

## Troubleshooting

### Common Issues
1. **Button not appearing** - Check scroll position and viewport height calculation
2. **AJAX failing** - Verify nonce is correct and user is logged in  
3. **Tabs not reordering** - Check DOM manipulation in browser dev tools
4. **CSS not loading** - Confirm enhanced CSS is enqueued properly

### Debug Tools
- Browser developer tools console for JavaScript errors
- Network tab to verify AJAX requests
- Elements tab to inspect DOM changes and CSS classes

## Rollback Plan

If issues arise, the enhanced flow can be disabled by:
1. Setting `ENHANCED_FLOW_START` constant to a future date
2. All users will get legacy experience until re-enabled
3. No data loss - enhanced flow flags remain in database

## Success Criteria

- ✅ Legacy users unaffected by changes
- ✅ New users get progressive reveal experience  
- ✅ Enhanced flow works across all major browsers
- ✅ Mobile experience is smooth and responsive
- ✅ No JavaScript errors or performance issues
- ✅ State persistence works correctly
- ✅ Error handling gracefully recovers from failures
