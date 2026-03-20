(function () {
    // --- Setup ---
    const { currentUser, ajaxUrl, ajaxNonce, miqNonce, loginUrl, data, dashboardUrl, progress } = bartle_quiz_data;
    const { cats: CATS, questions: QUESTIONS, likert: LIKERT } = data;
    const isLoggedIn = !!currentUser;
    const isEmployee = !!(currentUser && currentUser.isEmployee);
    const container = document.getElementById('bartle-quiz-container');
    if (!container) return;
    // New dev tools elements
    const devTools = document.getElementById('bartle-dev-tools');
    const autoBtn = document.getElementById('bartle-autofill-run');

    let quizState = {
        scores: {},
        sortedScores: [],
        ageGroup: null,
        needsAgeGroup: false,
        answers: {}
    };

    // --- Utility Functions ---
    function shuffle(a) { for (let i = a.length - 1; i > 0; i--) { const j = Math.floor(Math.random() * (i + 1));[a[i], a[j]] = [a[j], a[i]]; } return a; }
    const $id = (id) => document.getElementById(id);

    /**
     * Show login/registration prompt for non-logged-in users
     */
    function showLoginRegister() {
        container.innerHTML = `
            <div class="bartle-quiz-card bartle-results-section bg-secondary">
                <h2 class="bartle-section-title">Get Your Full Results & Action Plan</h2>
                <p>Enter your name and email to create your free account. We'll instantly email you a copy of your results and show them on the next screen.</p>
                <form id="bartle-register-form">
                    <div class="mi-form-field">
                        <label for="bartle_reg_first_name">First Name</label>
                        <input type="text" id="bartle_reg_first_name" required>
                    </div>
                    <div class="mi-form-field">
                        <label for="bartle_reg_email">Email Address</label>
                        <input type="email" id="bartle_reg_email" required>
                    </div>
                    <div class="form-submit-wrapper">
                        <button type="button" id="bartle_register_btn" class="bartle-quiz-button bartle-quiz-button-primary">Email My Results & Create Account</button>
                    </div>
                    <p id="bartle_reg_status" class="form-status"></p>
                </form>
                <p class="form-secondary-action">Already have an account? <a href="${loginUrl}">Log in here</a>.</p>
            </div>`;

        const regBtn = $id('bartle_register_btn');
        const statusEl = $id('bartle_reg_status');
        $id('bartle-register-form').addEventListener('submit', e => e.preventDefault());
        regBtn.addEventListener('click', () => {
            const email = $id('bartle_reg_email').value.trim();
            const firstName = $id('bartle_reg_first_name').value.trim();

            if (!firstName) { statusEl.innerHTML = 'Please enter your first name.'; statusEl.style.color = 'red'; return; }
            if (!email || !/\S+@\S+\.\S+/.test(email)) { statusEl.innerHTML = 'Please enter a valid email address.'; statusEl.style.color = 'red'; return; }

            statusEl.innerHTML = 'Creating your account...';
            statusEl.style.color = 'inherit';
            regBtn.disabled = true;

            const body = new URLSearchParams({
                action: 'miq_magic_register',
                _ajax_nonce: miqNonce,
                email: email,
                first_name: firstName,
                results_html: '',
                results_data: JSON.stringify(quizState)
            });

            fetch(ajaxUrl, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body })
                .then(r => r.json())
                .then(j => {
                    if (j.success) {
                        statusEl.innerHTML = j.data;
                        statusEl.style.color = 'green';
                        setTimeout(() => window.location.reload(), 1500);
                    } else {
                        let errorMsg = 'An unknown error occurred. Please try again.';
                        if (j.data) {
                            errorMsg = typeof j.data === 'string' ? j.data : (j.data.message || JSON.stringify(j.data));
                        }
                        statusEl.innerHTML = 'Error: ' + errorMsg;
                        statusEl.style.color = 'red';
                        regBtn.disabled = false;
                    }
                }).catch(() => {
                    statusEl.innerHTML = 'An unexpected error occurred. Please try again.';
                    statusEl.style.color = 'red';
                    regBtn.disabled = false;
                });
        });
    }

    /**
     * Instantly fills out the quiz with random answers and shows the results.
     * This is a development tool for testing.
     */
    function autoFill() {
        const steps = container.querySelectorAll('.bartle-step');
        if (!steps.length) return;

        steps.forEach(step => {
            const pick = Math.floor(Math.random() * 5) + 1; // Random value from 1 to 5
            const radio = step.querySelector(`input[value="${pick}"]`);
            if (radio) {
                radio.checked = true;
            }
        });

        // After filling all, calculate and show results directly.
        calculateAndShowResults();
    }

    /**
     * Generates and saves results for ALL THREE quizzes (MI, CDT, Bartle) based on a profile.
     * profile: 'average', 'negative' (high strain), 'excellent' (low strain)
     */
    function generateAndSaveAll(profile) {
        if (!confirm(`This will overwrite existing results for MI, CDT, AND Bartle quizzes with ${profile} data. Continue?`)) return;

        const btnId = `bartle-autofill-${profile === 'negative' ? 'neg' : (profile === 'excellent' ? 'exc' : 'avg')}`;
        const btn = document.getElementById(btnId);
        if (btn) {
            btn.textContent = 'Processing...';
            btn.disabled = true;
        }

        // 1. Define base scores based on profile
        // Average: ~3 (Moderate)
        // Negative: ~5 (High Strain)
        // Excellent: ~1 (Low Strain)
        let baseScore = 3;
        if (profile === 'negative') baseScore = 5;
        if (profile === 'excellent') baseScore = 1;

        // 2. Generate MI Data
        // MI has 8 intelligences + 3 strain categories (rumination, avoidance, emotional-flood)
        // MI Strain: 4 Rumination, 3 Avoidance, 3 Flood.
        const miData = {
            scores: {},
            sortedScores: [],
            ageGroup: 'adult'
        };
        const miCats = ['linguistic', 'logical', 'spatial', 'musical', 'kinesthetic', 'interpersonal', 'intrapersonal', 'naturalist'];
        miCats.forEach(cat => miData.scores[cat] = Math.floor(Math.random() * 10) + 15); // Random normal scores
        // Strain scores (max: 5 per q)
        miData.scores['si-rumination'] = 4 * baseScore;
        miData.scores['si-avoidance'] = 3 * baseScore;
        miData.scores['si-emotional-flood'] = 3 * baseScore;

        // 3. Generate CDT Data
        // CDT has 5 dimensions + 3 strain categories
        // CDT Strain: 3 Rumination, 4 Avoidance, 3 Flood.
        const cdtData = {
            scores: {},
            sortedScores: [],
            ageGroup: 'adult'
        };
        const cdtCats = ['ambiguity-tolerance', 'value-conflict-navigation', 'self-confrontation-capacity', 'discomfort-regulation', 'conflict-resolution-tolerance'];
        cdtCats.forEach(cat => cdtData.scores[cat] = 15); // Dummy score
        cdtData.scores['si-rumination'] = 3 * baseScore;
        cdtData.scores['si-avoidance'] = 4 * baseScore;
        cdtData.scores['si-emotional-flood'] = 3 * baseScore;

        // 4. Generate Bartle Data
        // Bartle has 4 types + 3 strain categories
        // Bartle Strain: 4 Rumination, 3 Avoidance, 3 Flood.
        const bartleData = {
            scores: {},
            sortedScores: [],
            ageGroup: 'adult'
        };
        const bartleCats = ['explorer', 'achiever', 'socializer', 'strategist'];
        bartleCats.forEach(cat => bartleData.scores[cat] = 15); // Dummy score
        bartleData.scores['si-rumination'] = 4 * baseScore;
        bartleData.scores['si-avoidance'] = 3 * baseScore;
        bartleData.scores['si-emotional-flood'] = 3 * baseScore;

        // 5. Send AJAX requests
        // 5. Send AJAX requests (Sequential)
        fetch(ajaxUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                action: 'miq_save_user_results',
                _ajax_nonce: ajaxNonce,
                user_id: currentUser.id,
                results: JSON.stringify(miData)
            })
        })
            .then(r => r.json())
            .then(d1 => {
                console.log('MI Saved:', d1);
                return fetch(ajaxUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({
                        action: 'cdt_save_user_results',
                        _ajax_nonce: ajaxNonce,
                        user_id: currentUser.id,
                        results: JSON.stringify(cdtData)
                    })
                });
            })
            .then(r => r.json())
            .then(d2 => {
                console.log('CDT Saved:', d2);
                return fetch(ajaxUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({
                        action: 'bartle_save_user_results',
                        _ajax_nonce: ajaxNonce,
                        user_id: currentUser.id,
                        results: JSON.stringify(bartleData)
                    })
                });
            })
            .then(r => r.json())
            .then(d3 => {
                console.log('Bartle Saved:', d3);
                alert(`All quizzes updated with ${profile} profile! Reloading...`);
                window.location.reload();
            })
            .catch(err => {
                console.error(err);
                alert('Error saving data. Check console.');
                if (btn) {
                    btn.textContent = `All ${profile.charAt(0).toUpperCase() + profile.slice(1)}`;
                    btn.disabled = false;
                }
            });

    }

    // --- Render Functions ---
    function renderIntro() {
        // Start quiz immediately; age gating happens at signup only.
        quizState.ageGroup = quizState.ageGroup || 'adult';
        renderQuiz();
    }

    function renderAgeGate() {
        if (devTools) devTools.style.display = 'none'; // Hide dev tools
        container.innerHTML = `
            <div class="bartle-quiz-card">
              <h2 class="bartle-section-title">Core Motivation Assessment</h2>
              <div class="bartle-intro-text">
                <p>The Core Motivation Assessment is designed to uncover what truly motivates you when you engage with challenges, learning, or everyday work. Based on a well-established motivational framework, it helps you understand what keeps you engaged, satisfied, and energized.</p>
                <p>Instead of just asking what you like, the quiz digs into what keeps you engaged, satisfied, and energized. Are you driven by curiosity, progress, connection, or competition?</p>
                
                <h4>The Four Player Types</h4>
                <ul>
                    <li><strong>Explorer (Discovery):</strong> Motivated by curiosity, learning, and uncovering hidden possibilities.</li>
                    <li><strong>Achiever (Achievement):</strong> Motivated by goals, progress, and measurable success.</li>
                    <li><strong>Socializer (Social):</strong> Motivated by relationships, teamwork, and shared growth.</li>
                    <li><strong>Strategist (Competition):</strong> Motivated by challenge, analysis, and proving oneself.</li>
                </ul>

                <h4>How the Quiz Works</h4>
                <ul>
                    <li>You’ll answer 40 statements (10 for each Player Type).</li>
                    <li>Each statement is rated on a 1–5 scale (from “Not at all like me” to “Very much like me”).</li>
                    <li>Your responses are scored to reveal not just your primary type, but also your secondary motivations.</li>
                </ul>

                <h4>Why This Matters</h4>
                <p>The Core Motivation Assessment is the third layer in the Self-Discovery journey:</p>
                <ul>
                    <li><strong>Multiple Intelligences</strong> → how you learn.</li>
                    <li><strong>Growth Strengths</strong> → how you handle conflict and uncertainty.</li>
                    <li><strong>Core Motivations</strong> → what motivates you to keep going.</li>
                </ul>
                <p>When you combine these, you get a fuller picture of your learning style, your resilience, and your drive.</p>
              </div>

              <h3 class="bartle-start-prompt">To begin, please select the option that best describes you:</h3>
              <div class="bartle-age-options">
                <button type="button" class="bartle-quiz-button" data-age-group="teen">Teen / High School</button>
                <button type="button" class="bartle-quiz-button" data-age-group="graduate">Student / Recent Graduate</button>
                <button type="button" class="bartle-quiz-button" data-age-group="adult">Adult / Professional</button>
              </div>
              <div class="bartle-quiz-notice">
                <p>${isLoggedIn ? 'Your results will be saved to your profile.' : `You can <a href="${loginUrl}">log in or create an account</a> to save your results.`}</p>
              </div>
            </div>`;

        container.querySelectorAll('.bartle-quiz-button[data-age-group]').forEach(btn => {
            btn.addEventListener('click', (e) => {
                quizState.ageGroup = e.currentTarget.dataset.ageGroup;
                renderQuiz();
            });
        });
    }

    function renderQuiz() {
        if (devTools && !isEmployee) devTools.style.display = 'block'; // Show dev tools
        const questionSet = QUESTIONS[quizState.ageGroup] || QUESTIONS['adult'];
        if (!questionSet) {
            container.innerHTML = `<div class="bartle-quiz-card"><p>Sorry, no questions could be found for your selected group.</p></div>`;
            return;
        }

        const items = [];
        Object.entries(questionSet).forEach(([slug, arr]) => {
            arr.forEach(q => items.push({ cat: slug, text: q.text, reverse: q.reverse }));
        });
        const questions = shuffle(items);

        let html = `
            <div class="bartle-progress-container"><div class="bartle-progress-bar"></div></div>
            <div class="bartle-steps-container">`;

        questions.forEach((q, i) => {
            let opts = '';
            for (let v = 1; v <= 5; v++) {
                opts += `<label class="bartle-quiz-option-wrapper"><input type="radio" name="q_${i}" value="${v}" required><span>${LIKERT[v]}</span></label>`;
            }
            html += `<div class="bartle-step" data-step="${i}" data-cat="${q.cat}" data-reverse="${q.reverse}" style="display:none;">
                        <div class="bartle-quiz-card">
                            <p class="bartle-quiz-question-text">${q.text}</p>
                            <div class="bartle-quiz-likert-options">${opts}</div>
                        </div>
                     </div>`;
        });
        html += `</div><div class="bartle-quiz-footer"><button type="button" id="bartle-prev-btn" class="bartle-quiz-button bartle-quiz-button-secondary" disabled>Previous</button></div>`;
        container.innerHTML = html;

        const steps = container.querySelectorAll('.bartle-step');
        const prevBtn = $id('bartle-prev-btn');
        const bar = container.querySelector('.bartle-progress-bar');
        let currentStep = 0;
        const totalSteps = steps.length;

        const showStep = (k) => {
            steps.forEach((s, j) => s.style.display = j === k ? 'block' : 'none');
            prevBtn.disabled = (k === 0);
            bar.style.width = ((k + 1) / totalSteps * 100) + '%';

            const allAnswered = Array.from(steps).every(s => s.querySelector('input:checked'));
            if (allAnswered) {
                calculateAndShowResults();
            }
        };

        prevBtn.addEventListener('click', () => { if (currentStep > 0) { currentStep--; showStep(currentStep); } });
        steps.forEach((s, k) => s.querySelectorAll('input[type=radio]').forEach(inp => inp.addEventListener('change', () => {
            setTimeout(() => {
                if (k < totalSteps - 1) {
                    currentStep = k + 1;
                    showStep(currentStep);
                } else {
                    showStep(k); // Stay on last step to check if all are answered
                }
            }, 150);
        })));

        showStep(0);
    }

    function calculateAndShowResults() {
        const scores = {};
        Object.keys(CATS).forEach(k => scores[k] = 0);

        container.querySelectorAll('.bartle-step').forEach(s => {
            const cat = s.getAttribute('data-cat');
            const isReverse = s.getAttribute('data-reverse') === 'true';
            const val = s.querySelector('input:checked')?.value;

            if (cat && val) {
                let score = parseInt(val, 10);
                if (isReverse) {
                    score = 6 - score; // Reverse score (1->5, 2->4, 3->3, 4->2, 5->1)
                }
                scores[cat] += score;

                // Capture answer
                const text = s.querySelector('.bartle-quiz-question-text')?.textContent || '';
                if (text) quizState.answers[text] = val; // Store raw input (1-5)
            }
        });

        quizState.scores = scores;
        quizState.sortedScores = Object.entries(scores)
            .filter(([k]) => !k.startsWith('si-')) // Exclude Strain Index categories
            .sort((a, b) => b[1] - a[1]);

        // Check if user needs to register before showing results
        if (!isLoggedIn) {
            showLoginRegister();
            return;
        }

        if (isEmployee) {
            saveResultsToServer()
                .then(() => renderEmployeeComplete())
                .catch(() => renderEmployeeComplete());
        } else {
            renderResults();
        }
    }

    function renderResults() {
        // Ensure any staging UI is hidden when showing results
        try {
            const stageEl = document.getElementById('bartle-stage');
            if (stageEl) stageEl.style.display = 'none';
            const toolbar = document.getElementById('bartle-toolbar');
            if (toolbar) toolbar.style.display = 'none';
            const containerEl = document.getElementById('bartle-quiz-container');
            if (containerEl) containerEl.style.display = 'block';
        } catch (e) { }
        if (devTools) devTools.style.display = 'none'; // Hide dev tools
        const { sortedScores, ageGroup } = quizState;
        const maxScore = (QUESTIONS[ageGroup]?.[sortedScores[0]?.[0]]?.length || 10) * 5;
        const userFirstName = currentUser ? currentUser.firstName : 'Valued User';

        const bar = (score, max) => {
            const pct = Math.max(0, Math.min(100, (score / max) * 100));
            const col = pct >= 75 ? '#4CAF50' : (pct < 40 ? '#f44336' : '#ffc107');
            return `<div class="bartle-bar-wrapper"><div class="bartle-bar-inner" style="width:${pct}%;background-color:${col};"></div></div>`;
        };

        const headerHtml = `
            <div class="results-main-header">
                <div class="site-branding">
                    <span class="site-title">The Science of Teamwork</span>
                </div>
            </div>
            <div class="bartle-results-header">
                <h1>Your Core Motivation Results</h1>
                <h2>Results for ${userFirstName}</h2>
                <p class="bartle-results-metadata">Generated on: ${new Date().toLocaleDateString()}</p>
                <p class="bartle-results-summary">This quiz uncovers what truly motivates you when you engage with games, challenges, or even everyday learning.</p>
            </div>`;

        const overviewHtml = `
            <div class="bartle-results-section">
                <h3 class="bartle-section-title">Your Profile Overview</h3>
                <div class="bartle-overview-list">
                    ${sortedScores.map(([slug, score]) => `
                        <div class="bartle-overview-item">
                            <div class="bartle-overview-header">
                                <span class="bartle-dimension-title">${CATS[slug]}</span>
                                <span class="bartle-dimension-score">${score} / ${maxScore}</span>
                            </div>
                            ${bar(score, maxScore)}
                        </div>
                    `).join('')}
                </div>
            </div>`;

        let nextStepsHtml = '';
        if (dashboardUrl) {
            if (isEmployee) {
                nextStepsHtml = `
                    <div class="bartle-results-section bartle-next-steps-section">
                        <h3 class="bartle-section-title">Assessment Complete</h3>
                        <p>Your <em>Self‑Discovery Profile</em> is complete. View your full results on your dashboard.</p>
                        <div class="bartle-results-actions">
                            <a href="${dashboardUrl}" class="bartle-quiz-button bartle-quiz-button-primary">View Your Profile</a>
                        </div>
                    </div>`;
            } else {
                const labUrl = dashboardUrl + (dashboardUrl.includes('?') ? '&' : '?') + 'tab=lab';
                nextStepsHtml = `
                    <div class="bartle-results-section bartle-next-steps-section">
                        <h3 class="bartle-section-title">You've Unlocked Lab Mode</h3>
                        <p>Your <em>Self‑Discovery Profile</em> is complete. Now let our <strong>AI Coach</strong> turn your strengths and peer insights into short, high‑leverage experiments — tailored to your goals, schedule, and risk comfort.</p>
                        <div class="bartle-results-actions">
                            <a href="${labUrl}" class="bartle-quiz-button bartle-quiz-button-primary">🚀 Open Lab Mode</a>
                            <a href="${dashboardUrl}" class="bartle-quiz-button bartle-quiz-button-secondary">View Your Profile</a>
                        </div>
                    </div>`;
            }
        }

        let resultsHtml = `
            <div id="bartle-results-content">
                ${headerHtml}
                ${overviewHtml}
                ${nextStepsHtml}
            </div>
            <div id="bartle-results-actions" class="bartle-results-actions"></div>`;
        container.innerHTML = resultsHtml;

        const actionsContainer = $id('bartle-results-actions');
        const createActionButton = (text, classes, onClick) => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = classes;
            btn.innerHTML = text;
            btn.addEventListener('click', onClick);
            return btn;
        };

        // Download PDF button (available for all users)
        const downloadBtnClasses = dashboardUrl ? 'bartle-quiz-button bartle-quiz-button-secondary' : 'bartle-quiz-button bartle-quiz-button-primary';
        const downloadBtn = createActionButton('⬇️ Download PDF', downloadBtnClasses, (e) => {
            const btn = e.currentTarget;
            btn.textContent = 'Generating...';
            btn.disabled = true;

            // Clone the results content and adjust for PDF without changing on-screen content
            const resultsNode = $id('bartle-results-content');
            if (!resultsNode) { btn.textContent = '⬇️ Download PDF'; btn.disabled = false; return; }
            const resultsClone = resultsNode.cloneNode(true);

            // Ensure logo renders at a consistent size in PDF
            const logoInClone = resultsClone.querySelector('.site-logo');
            if (logoInClone) {
                logoInClone.style.height = '60px';
                logoInClone.style.width = 'auto';
            }

            // Strip emojis for PDF reliability
            const emojiRegex = /[\u{1F9E0}\u{2705}\u{1F680}\u{26A1}\u{1F4CA}\u{1F9ED}\u{2B07}\u{1F504}\u{1F5D1}]\u{FE0F}?/gu;
            const pdfHtml = resultsClone.innerHTML.replace(emojiRegex, '').trim();

            const body = new URLSearchParams({ action: 'bartle_generate_pdf', _ajax_nonce: ajaxNonce, results_html: pdfHtml });
            fetch(ajaxUrl, { method: 'POST', body })
                .then(response => response.ok ? response.blob() : Promise.reject('Network response was not ok.'))
                .then(blob => {
                    const url = window.URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.style.display = 'none';
                    a.href = url;
                    a.download = `bartle-quiz-results-${new Date().toISOString().slice(0, 10)}.pdf`;
                    document.body.appendChild(a);
                    a.click();
                    window.URL.revokeObjectURL(url);
                    a.remove();
                })
                .catch(error => {
                    console.error('PDF Download Error:', error);
                    alert('Sorry, there was an error generating the PDF.');
                })
                .finally(() => {
                    btn.textContent = '⬇️ Download PDF';
                    btn.disabled = false;
                });
        });
        actionsContainer.appendChild(downloadBtn);

        if (isLoggedIn) {
            saveResultsToServer();

            const retakeBtn = createActionButton('🔄 Retake Quiz', 'bartle-quiz-button bartle-quiz-button-secondary', () => {
                if (confirm('Are you sure? Your saved results will be overwritten when you complete the new quiz.')) {
                    // Reset quiz state
                    quizState = {
                        scores: {},
                        sortedScores: [],
                        ageGroup: bartle_quiz_data.userAgeGroup || 'adult',
                        needsAgeGroup: false
                    };
                    renderIntro();
                    window.scrollTo(0, 0);
                }
            });
            actionsContainer.appendChild(retakeBtn);

            const deleteBtn = createActionButton('🗑️ Delete Results', 'bartle-quiz-button bartle-quiz-button-danger', (e) => {
                if (!confirm('Are you sure you want to permanently delete your saved results? This cannot be undone.')) return;
                const btn = e.currentTarget;
                btn.textContent = 'Deleting...';
                btn.disabled = true;
                fetch(ajaxUrl, {
                    method: 'POST',
                    body: new URLSearchParams({ action: 'bartle_delete_user_results', _ajax_nonce: ajaxNonce })
                })
                    .then(r => r.json())
                    .then(j => {
                        if (j.success) {
                            currentUser.savedResults = null;
                            quizState = {
                                scores: {},
                                sortedScores: [],
                                ageGroup: bartle_quiz_data.userAgeGroup || 'adult',
                                needsAgeGroup: false
                            };
                            alert('Your results have been deleted.');
                            renderIntro();
                            window.scrollTo(0, 0);
                        } else {
                            alert('Error: ' + (j.data || 'Could not delete results.'));
                            btn.innerHTML = '🗑️ Delete Results';
                            btn.disabled = false;
                        }
                    });
            });
            actionsContainer.appendChild(deleteBtn);
        }
    }

    function renderEmployeeComplete() {
        try {
            const stageEl = document.getElementById('bartle-stage');
            if (stageEl) stageEl.style.display = 'none';
            const toolbar = document.getElementById('bartle-toolbar');
            if (toolbar) toolbar.style.display = 'none';
            const containerEl = document.getElementById('bartle-quiz-container');
            if (containerEl) containerEl.style.display = 'block';
        } catch (e) { }
        if (devTools) devTools.style.display = 'none';

        const logoutUrl = bartle_quiz_data.logoutUrl || '/wp-login.php?action=logout';

        container.innerHTML = `
            <div class="bartle-quiz-card" style="text-align:center; padding: 3em 2em;">
                <h2 class="bartle-section-title">You're All Done!</h2>
                <p style="font-size:1.1em; color:#4a5568; margin:1em 0;">Thank you for completing your assessments! Your responses have been recorded.</p>
                <p style="font-size:1.05em; color:#4a5568; margin:0.5em 0 1.5em;">Your team lead will review your results and discuss them with you. You can now log out.</p>
                <a href="${logoutUrl}" class="bartle-quiz-button bartle-quiz-button-primary" style="display:inline-block;">Log Out</a>
            </div>`;

        window.scrollTo(0, 0);
    }

    function saveResultsToServer() {
        if (!isLoggedIn) return Promise.resolve();

        return fetch(ajaxUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                action: 'bartle_save_user_results',
                _ajax_nonce: ajaxNonce,
                user_id: currentUser.id,
                results: JSON.stringify(quizState)
            })
        })
        .then(r => {
            if (!r.ok) throw new Error('Save failed: ' + r.status);
            return r.json();
        })
        .then(j => {
            if (!j.success) throw new Error('Save rejected: ' + (j.data || 'unknown'));
            return j;
        })
        .catch(err => {
            console.error('Bartle save error:', err);
            throw err;
        });
    }

    // --- Initial Load ---
    function init() {
        if (autoBtn) {
            autoBtn.addEventListener('click', autoFill);
        }

        const autoAvg = document.getElementById('bartle-autofill-avg');
        const autoNeg = document.getElementById('bartle-autofill-neg');
        const autoExc = document.getElementById('bartle-autofill-exc');

        if (autoAvg) autoAvg.addEventListener('click', () => generateAndSaveAll('average'));
        if (autoNeg) autoNeg.addEventListener('click', () => generateAndSaveAll('negative'));
        if (autoExc) autoExc.addEventListener('click', () => generateAndSaveAll('excellent'));

        // Check if user has completed quiz
        if (isLoggedIn && currentUser.savedResults && currentUser.savedResults.sortedScores) {
            quizState = currentUser.savedResults;
            if (isEmployee) {
                renderEmployeeComplete();
            } else {
                renderResults();
            }
            return;
        }

        // All users default to adult
        quizState.ageGroup = 'adult';
        // Persist locally just in case
        try { localStorage.setItem('mc_age_group', 'adult'); } catch (e) { }

        renderIntro();
    }

    init();
})();
