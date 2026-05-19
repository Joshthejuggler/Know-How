/**
 * Lab Mode Debug Tool
 * Paste this into the browser console to debug Lab Mode issues
 */

function debugLabMode() {
    console.log('🔍 Lab Mode Debug Tool - Starting diagnostics...');
    
    // Check if LabModeApp exists
    console.log('1. Checking LabModeApp availability:');
    if (typeof LabModeApp === 'undefined') {
        console.error('❌ LabModeApp is not defined!');
        return;
    } else {
        console.log('✅ LabModeApp found:', LabModeApp);
    }
    
    // Check if AI Loading Overlay exists
    console.log('2. Checking AI Loading Overlay availability:');
    if (typeof AILoadingOverlay === 'undefined') {
        console.warn('⚠️ AILoadingOverlay is not available (will use fallback)');
    } else {
        console.log('✅ AILoadingOverlay found:', AILoadingOverlay);
    }
    
    // Check if jQuery is available
    console.log('3. Checking jQuery availability:');
    if (typeof jQuery === 'undefined' || typeof $ === 'undefined') {
        console.error('❌ jQuery is not available!');
        return;
    } else {
        console.log('✅ jQuery found:', jQuery.fn.jquery);
    }
    
    // Check if the Start Lab Mode button exists
    console.log('4. Checking Start Lab Mode button:');
    const button = $('.lab-start-btn');
    if (button.length === 0) {
        console.error('❌ Start Lab Mode button not found!');
    } else {
        console.log('✅ Button found:', button);
        console.log('Button classes:', button.attr('class'));
        console.log('Button text:', button.text());
    }
    
    // Check if event handlers are bound
    console.log('5. Checking event handlers:');
    const events = $._data(document, 'events');
    if (events && events.click) {
        console.log('✅ Click events bound:', events.click.length);
        const labEvents = events.click.filter(e => e.selector && e.selector.includes('lab-start-btn'));
        console.log('Lab start button handlers:', labEvents.length);
    } else {
        console.error('❌ No click events found on document!');
    }
    
    // Test button click manually
    console.log('6. Testing button click manually:');
    try {
        if (button.length > 0) {
            console.log('Attempting to trigger click...');
            button.trigger('click');
        }
    } catch (error) {
        console.error('❌ Error clicking button:', error);
    }
    
    // Check Lab Mode state
    console.log('7. Lab Mode current state:');
    console.log('Current step:', LabModeApp.currentStep);
    console.log('Profile data:', LabModeApp.profileData ? 'Available' : 'Not loaded');
    console.log('Qualifiers:', LabModeApp.qualifiers ? 'Available' : 'Not set');
    
    // Check WordPress AJAX setup
    console.log('8. WordPress AJAX setup:');
    if (typeof labMode !== 'undefined') {
        console.log('✅ labMode config found:', labMode);
    } else {
        console.error('❌ labMode configuration not found!');
    }
    
    console.log('🏁 Debug complete! Check for ❌ errors above.');
}

// Test the button functionality specifically
function testStartLabModeButton() {
    console.log('🧪 Testing Start Lab Mode button...');
    
    const button = $('.lab-start-btn');
    if (button.length === 0) {
        console.error('❌ Button not found!');
        return;
    }
    
    // Manually call the function
    try {
        console.log('Calling startProfileInputs manually...');
        LabModeApp.startProfileInputs({ preventDefault: () => {} });
    } catch (error) {
        console.error('❌ Error calling startProfileInputs:', error);
    }
}

// Quick fix to rebind events if they're missing
function rebindLabModeEvents() {
    console.log('🔧 Rebinding Lab Mode events...');
    if (typeof LabModeApp !== 'undefined' && LabModeApp.bindEvents) {
        LabModeApp.bindEvents();
        console.log('✅ Events rebound!');
    }
}

// Auto-run diagnostics
console.log('🚀 Lab Mode Debug Tool loaded. Available functions:');
console.log('- debugLabMode() - Full diagnostic');
console.log('- testStartLabModeButton() - Test button functionality');  
console.log('- rebindLabModeEvents() - Rebind event handlers');
console.log('Running auto-diagnostics in 1 second...');

setTimeout(debugLabMode, 1000);