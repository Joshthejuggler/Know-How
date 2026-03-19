# CDT Card Troubleshooting Guide

## If nothing is displaying, follow these steps:

### 1. Check Prerequisites
- **Are you logged in as an admin user?** (needed for debug output)
- **Have you completed all three assessments?** (MI Quiz, CDT Quiz, Bartle Quiz)
- **Are you on the dashboard page?** (not individual quiz pages)
- **Is the "Your Self-Discovery Profile" tab visible and active?**

### 2. Check Browser Developer Tools

#### View Page Source
1. Right-click → "View Page Source"
2. Search for "CDT Debug:" in the HTML comments
3. Look for debug output like:
   ```html
   <!-- CDT Debug: cdt_results exist: yes, cdt_categories exist: yes, cdt_top_slug: ambiguity-tolerance, cdt_bottom_slug: discomfort-regulation, cdt_second_bottom_slug: value-conflict-navigation, cdt_sorted count: 5 -->
   ```

#### Check Console for JavaScript Errors
1. Press F12 → Console tab
2. Look for any red error messages
3. Refresh the page and check for new errors

### 3. Check CSS Loading
1. In Developer Tools → Network tab
2. Refresh the page
3. Look for "dashboard.css" - should load with version 1.0.3
4. If it shows an older version, clear your browser cache

### 4. Verify Quiz Completion
1. Go to WordPress Admin → Users → Your User
2. Check user meta for:
   - `miq_quiz_results`
   - `cdt_quiz_results` 
   - `bartle_quiz_results`

### 5. Expected Debug Output

If everything is working, you should see:
```html
<!-- CDT Debug: cdt_results exist: yes, cdt_categories exist: yes, cdt_top_slug: [some-slug], cdt_bottom_slug: [some-slug], cdt_second_bottom_slug: [some-slug], cdt_sorted count: 5 -->
<!-- CDT Data Objects: cdt_top: {"label":"Some Label","pct":75}, cdt_bottom: {"label":"Growth Area 1","pct":45}, cdt_second_bottom: {"label":"Growth Area 2","pct":50} -->
```

### 6. Common Issues

#### No Debug Output at All
- Check if you're logged in as an admin
- The entire dashboard section might not be loading (check prerequisites)

#### Debug Shows "cdt_results exist: no"
- CDT Quiz not completed or results not saved
- Complete the CDT Quiz again

#### Debug Shows "cdt_categories exist: no"
- CDT questions file not loading properly
- Check file permissions on `/quizzes/cdt-quiz/questions.php`

#### Debug Shows null values for all slugs
- CDT results exist but are in wrong format
- May need to retake the CDT Quiz

#### CSS Not Loading (version shows 1.0.2 or older)
- Clear browser cache
- Check if WordPress is caching plugins
- Hard refresh (Ctrl+F5 / Cmd+Shift+R)

### 7. Quick Fixes to Try

1. **Clear all caches** (browser, WordPress plugins, etc.)
2. **Hard refresh** the dashboard page
3. **Switch to a different tab** and back to "Your Self-Discovery Profile"
4. **Log out and log back in**
5. **Try in an incognito/private browser window**

### 8. If Still Not Working

The debug comments will tell us exactly what's happening. Share the debug output you see in the page source, and we can identify the specific issue.