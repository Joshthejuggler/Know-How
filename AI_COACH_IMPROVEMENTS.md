# AI Coach Error Handling Improvements

## Overview
Enhanced the AI Coach experiment generation system to provide better user feedback when OpenAI API requests fail, while maintaining seamless fallback to backup experiments.

## Changes Made

### Backend Improvements (`micro-coach-ai.php`)

#### 1. Early API Key Validation
- Added validation to detect invalid or missing API keys before making HTTP requests
- Prevents unnecessary network calls for obviously invalid keys
- Handles placeholder patterns like `sk-XXXX0000` that admins might use during setup

#### 2. Improved Timeout Handling
- Reduced initial timeout from 45 seconds to 15 seconds to prevent UI freezing
- Added specific handling for timeout errors vs other network errors
- Prevents retries on timeout/network issues (which are unlikely to succeed)

#### 3. Enhanced Error Logging
- Added detailed error logging for timeout and network failures
- Logs specific error codes and messages for admin debugging
- Preserves diagnostic information while providing user-friendly messages

#### 4. User-Friendly Error Messages
- Added `error` field to JSON response with human-readable messages
- Maps technical failures to friendly explanations:
  - `timeout` → "OpenAI request timed out. Using backup experiment generator."
  - `network_error` → "Could not connect to OpenAI. Using backup experiment generator."
  - `invalid_api_key` → "OpenAI API key not configured. Using backup experiment generator."
  - Default → "OpenAI temporarily unavailable. Using backup experiment generator."

#### 5. Better Retry Logic
- More intelligent retry decisions based on error type
- Longer timeout (30 seconds) for retry attempts
- Avoids retrying timeouts and network errors that are unlikely to succeed

### Frontend Improvements (`micro-coach-core.php`)

#### 1. Enhanced Banner Display
- Updated JavaScript to check for `error` field first in API responses
- Prioritizes user-friendly error messages over technical fallback reasons
- Added visual styling distinctions for different message types

#### 2. Visual Styling
- Added `warning` class with amber/yellow styling for error messages
- Added `info` class with blue styling for informational messages  
- Uses ⚠️ emoji to draw attention to error messages
- Maintains existing styling for normal operations

#### 3. Progressive Error Display
- Shows user-friendly error message if available
- Falls back to technical reason if no user message provided
- Gracefully handles missing or malformed error data

## User Experience Improvements

### Before Changes
- Users saw technical error messages like "http_401" or "parse_error"
- Long 45-second timeouts could freeze the interface
- No clear indication that backup experiments were still valuable
- Generic "placeholder" messaging confused users

### After Changes
- Clear, friendly error messages explain what happened
- Quick 15-second timeout keeps interface responsive
- Explicit messaging that backup experiments are still being provided
- Visual distinction between errors and normal fallback usage
- Users understand they're still getting value despite OpenAI issues

## Error Message Examples

| Scenario | User Sees |
|----------|-----------|
| No API Key | ⚠️ OpenAI API key not configured. Using backup experiment generator. |
| Invalid Key | ⚠️ OpenAI API key not configured. Using backup experiment generator. |
| Network Timeout | ⚠️ OpenAI request timed out. Using backup experiment generator. |
| Service Down | ⚠️ OpenAI temporarily unavailable. Using backup experiment generator. |
| Connection Failed | ⚠️ Could not connect to OpenAI. Using backup experiment generator. |

## Testing

### Automated Testing
- Test invalid API key scenarios
- Test network timeout conditions
- Test API service unavailability
- Test missing API key cases
- Verify fallback experiment generation still works

### Manual Testing
See `ENHANCED_FLOW_TESTING.md` section 6 for comprehensive testing procedures.

## Technical Details

### API Key Validation Pattern
```php
$key_valid = !empty($api_key) && strlen($api_key) > 20 && !preg_match('/^sk-[a-zA-Z0-9]{4,6}[X0x]+$/', $api_key);
```

### Error Response Structure
```json
{
  "success": true,
  "data": {
    "shortlist": [...],
    "more": [...],
    "used_fallback": true,
    "fallback_reason": "timeout",
    "error": "OpenAI request timed out. Using backup experiment generator."
  }
}
```

### CSS Classes
```css
.ai-banner.warning { 
  background: #fef3c7; 
  border: 1px solid #f59e0b; 
  color: #92400e; 
}
```

## Backwards Compatibility

All changes are backwards compatible:
- Existing API responses still work without `error` field
- Legacy fallback messaging remains functional
- No database schema changes required
- No breaking changes to JavaScript APIs

## Future Improvements

Potential enhancements for future development:
- Add retry button in error banner for user-initiated retry
- Store error statistics for admin monitoring
- Add different retry strategies based on error type
- Implement exponential backoff for retries
- Add user preference for backup vs AI experiments