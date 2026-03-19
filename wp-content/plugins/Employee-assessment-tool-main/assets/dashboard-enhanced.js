(function() {
    'use strict';

    const { ajaxUrl, nonce } = dashboard_enhanced_data || {};
    if (!ajaxUrl || !nonce) {
        console.warn('Dashboard enhanced data not available');
        return;
    }

    // Wait for DOM to be ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    function init() {
        const profileTab = document.getElementById('tab-composite');
        if (!profileTab) return;

        // Add AI Coach preview section at the top
        const aiPreview = document.createElement('div');
        aiPreview.className = 'mc-ai-coach-preview';
        aiPreview.innerHTML = `
            <div class="mc-ai-preview-content">
                <div class="mc-ai-preview-icon">✨</div>
                <!-- Alternative icons to try:
                     🧠 (brain) - intelligence/thinking
                     💡 (lightbulb) - insights/ideas  
                     🎯 (target) - precision/personalized
                     🔮 (crystal ball) - insights/future
                     ⚡ (lightning) - power/speed
                     🌟 (star) - excellence/quality
                     🎪 (circus tent) - just kidding, don't use this one
                -->
                <div class="mc-ai-preview-text">
                    <h3>Your AI Coach is Ready</h3>
                    <p>Once you review your Self-Discovery Profile below, unlock personalized experiments designed just for your strengths and motivations.</p>
                </div>
                <div class="mc-ai-preview-arrow">↓</div>
            </div>
        `;
        
        // Insert at the top of the profile tab
        profileTab.insertBefore(aiPreview, profileTab.firstChild);

        // Create the "Try Your AI Coach" button
        const cta = document.createElement('button');
        cta.id = 'mc-try-coach-btn';
        cta.textContent = '🚀 Try Your AI Coach';
        cta.className = 'mc-primary-btn';
        cta.style.display = 'none';
        cta.style.margin = '2rem auto 0';
        cta.style.display = 'block';
        cta.style.fontSize = '1.2rem';
        cta.style.padding = '12px 24px';
        cta.style.backgroundColor = '#007cba';
        cta.style.color = 'white';
        cta.style.border = 'none';
        cta.style.borderRadius = '5px';
        cta.style.cursor = 'pointer';
        cta.style.fontWeight = 'bold';
        cta.style.display = 'none'; // Hidden initially

        // Append to profile tab
        profileTab.appendChild(cta);

        // Add scroll listener for progressive reveal
        let revealed = false;
        function checkScroll() {
            if (revealed) return;
            
            const scrollY = window.pageYOffset || document.documentElement.scrollTop;
            const windowHeight = window.innerHeight;
            const profileTabTop = profileTab.offsetTop;
            const profileTabHeight = profileTab.offsetHeight;
            const threshold = 200; // Pixels from bottom before revealing

            if (scrollY + windowHeight >= profileTabTop + profileTabHeight - threshold) {
                cta.style.display = 'block';
                cta.style.animation = 'fadeIn 0.5s ease-in';
                revealed = true;
            }
        }

        window.addEventListener('scroll', checkScroll);
        window.addEventListener('resize', checkScroll);

        // Add click handler to unlock coach
        cta.addEventListener('click', unlockCoach);

        // Check initial position in case content is short
        setTimeout(checkScroll, 100);
    }

    function unlockCoach() {
        const cta = document.getElementById('mc-try-coach-btn');
        if (!cta) return;

        // Disable button during request
        cta.disabled = true;
        cta.textContent = 'Unlocking AI Coach...';

        // Post to server to mark AI as unlocked
        const formData = new FormData();
        formData.append('action', 'mc_mark_ai_unlocked');
        formData.append('_ajax_nonce', nonce);

        fetch(ajaxUrl, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Remove enhanced profile only class
                document.body.classList.remove('mc-enhanced-profile-only');
                
                // Show previously hidden dashboard sections
                const sectionsToShow = [
                    '.quiz-dashboard-hero',
                    '.quiz-dashboard-section-title',
                    '.quiz-dashboard-lower-grid',
                    '.quiz-dashboard-resources'
                ];
                sectionsToShow.forEach(selector => {
                    const elements = document.querySelectorAll(selector);
                    elements.forEach(el => {
                        el.style.display = '';
                        el.classList.add('fade-in');
                    });
                });
                
                // Show all hidden tabs
                const tabLinks = document.querySelectorAll('.dashboard-tabs .tab-link');
                tabLinks.forEach(tab => {
                    tab.style.display = 'inline-block';
                    tab.classList.add('fade-in');
                });

                // Reorder tabs: AI Coach first, then Saved, Profile, Detailed
                const tabContainer = document.querySelector('.dashboard-tabs');
                if (tabContainer) {
                    const aiTab = document.querySelector('[data-tab="tab-ai"]');
                    const savedTab = document.querySelector('[data-tab="tab-saved"]');
                    const profileTab = document.querySelector('[data-tab="tab-composite"]');
                    const detailedTab = document.querySelector('[data-tab="tab-path"]');

                    // Reorder the tabs
                    if (aiTab) tabContainer.appendChild(aiTab);
                    if (savedTab) tabContainer.appendChild(savedTab);
                    if (profileTab) tabContainer.appendChild(profileTab);
                    if (detailedTab) tabContainer.appendChild(detailedTab);
                }

                // Hide the CTA button and AI preview
                cta.style.display = 'none';
                const aiPreview = document.querySelector('.mc-ai-coach-preview');
                if (aiPreview) {
                    aiPreview.style.animation = 'fadeOut 0.3s ease-out';
                    setTimeout(() => aiPreview.remove(), 300);
                }

                // Switch to Lab Mode tab if available, otherwise AI Coach
                const labTabButton = document.querySelector('[data-tab="tab-lab"]');
                const aiTabButton = document.querySelector('[data-tab="tab-ai"]');
                if (labTabButton) {
                    labTabButton.click();
                } else if (aiTabButton) {
                    aiTabButton.click();
                }

                // Add some visual feedback
                const message = document.createElement('div');
                const targetTab = labTabButton ? 'Lab Mode' : 'AI Coach';
                message.textContent = `✅ AI Coach unlocked! Welcome to ${targetTab} - your personalized experiment generator.`;
                message.style.cssText = `
                    position: fixed;
                    top: 20px;
                    right: 20px;
                    background: #28a745;
                    color: white;
                    padding: 12px 20px;
                    border-radius: 5px;
                    font-weight: bold;
                    z-index: 10000;
                    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
                `;
                document.body.appendChild(message);
                setTimeout(() => message.remove(), 4000);

            } else {
                console.error('Failed to unlock AI coach:', data);
                cta.disabled = false;
                cta.textContent = '🚀 Try Your AI Coach';
                alert('Failed to unlock AI Coach. Please try again.');
            }
        })
        .catch(error => {
            console.error('Error unlocking AI coach:', error);
            cta.disabled = false;
            cta.textContent = '🚀 Try Your AI Coach';
            alert('An error occurred. Please try again.');
        });
    }

    // Add CSS for fade-in animation if not already present
    if (!document.getElementById('mc-enhanced-styles')) {
        const style = document.createElement('style');
        style.id = 'mc-enhanced-styles';
        style.textContent = `
            @keyframes fadeIn {
                from { opacity: 0; transform: translateY(10px); }
                to { opacity: 1; transform: translateY(0); }
            }
            .fade-in {
                animation: fadeIn 0.4s ease-in;
            }
            #mc-try-coach-btn:hover {
                background-color: #005a87 !important;
                transform: translateY(-1px);
            }
        `;
        document.head.appendChild(style);
    }

})();
