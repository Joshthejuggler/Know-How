(function () {
    // --- Debugging: Log the raw data received from PHP ---
    console.log('CDT Quiz Data Received:', typeof cdt_quiz_data !== 'undefined' ? cdt_quiz_data : 'Error: cdt_quiz_data is not defined.');
    if (typeof cdt_quiz_data === 'undefined') { return; }

    // --- Setup ---
    const { currentUser, ajaxUrl, ajaxNonce, loginUrl, data, nextStepUrl, nextStepTitle, predictionData, ageGroup, ageNonce } = cdt_quiz_data;
    const { cats: CATS, questions: QUESTIONS, likert: LIKERT, dimensionDetails: DIMENSION_DETAILS } = data || {};
    const isLoggedIn = !!currentUser;
    const isAdmin = !!(currentUser && currentUser.isAdmin);
    const isEmployee = !!(currentUser && currentUser.isEmployee);
    const container = document.getElementById('cdt-quiz-container');
    if (!container) return;
    // New dev tools elements
    const devTools = document.getElementById('cdt-dev-tools');
    const autoBtn = document.getElementById('cdt-autofill-run');



    // --- Static Content for Results Page ---
    const DIMENSION_DETAILS_FALLBACK = {
        'ambiguity-tolerance': {
            title: 'Ambiguity Tolerance',
            definition: 'Your ability to stay functional and creative when the path forward isn\'t clear, without rushing to premature closure or getting paralyzed by uncertainty.',
            growth_snapshot: '⚡ You may feel stuck when things aren\'t clear, either waiting too long to act or forcing closure too early.',
            watch_out_for: 'If you cling to clarity too soon, you may shut down new ideas. If you delay too long, you frustrate others and miss opportunities.',
            character_sketch_intro: 'These phrases capture how ambiguity tolerance shows up in your daily interactions. They reflect the mindset and behaviors of someone who can navigate uncertainty with grace.',
            character_sketch_phrases: [
                '"Let\'s not force an answer yet—let\'s see what emerges."',
                'You notice possibilities others miss when details are fuzzy.',
                'You stay calm when plans change at the last minute.'
            ],
            relational_applications: {
                'relationships': 'You reassure anxious partners or friends by normalizing not-yet-knowing.',
                'teams': 'You keep brainstorming open, preventing premature closure.',
                'leadership': 'You model composure and curiosity during ambiguous phases.'
            },
            quick_prompts: [
                'Make one decision this week at ~70% clarity; schedule a review in 7 days.',
                'Timebox ambiguity: explore options for 48 hours, then choose and commit.'
            ]
        },
        'value-conflict-navigation': {
            title: 'Value Conflict Navigation',
            definition: 'Your capacity to recognize when deeply held values clash and navigate the tension without denial, paralysis, or unprincipled compromise.',
            growth_snapshot: '⚡ When your values clash (e.g., honesty vs. loyalty), decision-making can stall or get rationalized.',
            watch_out_for: 'If you try to satisfy every value equally, you may rationalize or freeze instead of choosing.',
            character_sketch_intro: 'These phrases reveal what it looks like when someone faces competing values. They show the internal struggle and external behaviors that emerge when core principles collide.',
            character_sketch_phrases: [
                '"I want to be honest—but I also don\'t want to hurt them."',
                'You feel torn when loyalty and fairness pull in different directions.',
                'You explain choices in circles to avoid naming the tradeoff.'
            ],
            relational_applications: {
                'relationships': 'Saying which value leads in this situation builds trust ("kind honesty over quick comfort").',
                'teams': 'Surfacing the value tradeoff ("speed vs. quality") clarifies decisions.',
                'leadership': 'Consistent value hierarchies prevent whiplash and cynicism.'
            },
            quick_prompts: [
                'List your top 5 values; mark the one that leads in today\'s decision.',
                'Write one sentence: "When X conflicts with Y, I will choose ___ because ___."'
            ]
        },
        'self-confrontation-capacity': {
            title: 'Self-Confrontation Capacity',
            definition: 'Your willingness to honestly examine gaps between your stated values and actual behavior, without defensive rationalization or destructive self-criticism.',
            growth_snapshot: '⚡ You may defend, deny, or rationalize instead of admitting mismatches between beliefs and actions.',
            watch_out_for: 'Defend/deny/rationalize stalls growth; harsh self-criticism kills momentum.',
            character_sketch_intro: 'These phrases demonstrate what authentic self-confrontation looks like in practice. They show someone who can face their own contradictions with both honesty and grace.',
            character_sketch_phrases: [
                '"I was wrong there—and here\'s how I\'ll fix it."',
                'You can say the awkward truth about your own part.',
                'You notice small drifts from your standards and course-correct.'
            ],
            relational_applications: {
                'relationships': 'Owning your part disarms defensiveness and invites repair.',
                'teams': 'Publicly closing the loop on mistakes builds trust ("here\'s what I changed").',
                'leadership': 'Modeling truth-with-grace normalizes learning over image management.'
            },
            quick_prompts: [
                'Pair confession with change: "I did X; next time I will do Y."',
                'Weekly note: one inconsistency I noticed; one step I\'ll take this week.'
            ]
        },
        'discomfort-regulation': {
            title: 'Emotional Regulation under Dissonance',
            definition: 'Your ability to manage emotional intensity when facing contradictions, staying present and responsive rather than shutting down or lashing out.',
            growth_snapshot: '⚡ When dissonance spikes, you may shut down or overreact, missing the chance to respond wisely.',
            watch_out_for: 'Over-regulating hides real emotion; under-regulating derails the moment with reactivity.',
            character_sketch_intro: 'These phrases show what emotional regulation looks like when tension runs high. They capture the calm presence and emotional intelligence that help navigate charged moments.',
            character_sketch_phrases: [
                '"Give me a second—I need a breath before I respond."',
                'You can put words to what others are feeling but can\'t say.',
                'You steady the room when tension spikes.'
            ],
            relational_applications: {
                'relationships': 'Naming shared emotions ("we\'re both on edge") reduces defensiveness.',
                'teams': 'Short pauses (10–30 seconds) prevent spirals and sharpen thinking.',
                'leadership': 'Calm + candor signals safety: emotions are valid and workable.'
            },
            quick_prompts: [
                'Use Name-Acknowledge-Ask: "I\'m feeling X. I think you might be feeling Y. Can we slow down?"',
                'Body check: unclench jaw/shoulders, 4×4 breathing, then speak.'
            ]
        },
        'conflict-resolution-tolerance': {
            title: 'Conflict Resolution Tolerance',
            definition: 'Your capacity to remain engaged during interpersonal conflict long enough to understand root issues and work toward genuine resolution.',
            growth_snapshot: '⚡ You may avoid or postpone hard conversations, which can leave problems unresolved.',
            watch_out_for: 'Avoiding or rushing through conflict to restore comfort can leave core issues untouched.',
            character_sketch_intro: 'These phrases illustrate what conflict avoidance looks like in everyday situations. They show the patterns that emerge when someone struggles to stay present during disagreement.',
            character_sketch_phrases: [
                '"I don\'t want this to turn into a fight—can we skip it?"',
                'You keep the peace today, but the same issue returns next week.',
                'You rehearse what you wish you\'d said—after the moment has passed.'
            ],
            relational_applications: {
                'relationships': 'Naming the issue kindly and early prevents resentful buildup.',
                'teams': 'Structured turn-taking and reflective listening make debates productive.',
                'leadership': 'Clear norms ("we address tensions directly") create psychological safety.'
            },
            quick_prompts: [
                'Use the sentence starter: "What I hear you saying is … Did I get that right?"',
                'Name and schedule: "There\'s tension here. Can we set 20 minutes tomorrow to address it?"'
            ]
        }
    };

    const ALL_DIMENSION_DETAILS = DIMENSION_DETAILS || DIMENSION_DETAILS_FALLBACK;

    let quizState = {
        scores: {},
        sortedScores: [],
        ageGroup: 'adult',
        autoFilled: false,
        reviewPrompted: false,
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
            <div class="cdt-quiz-card cdt-results-section bg-secondary">
                <h2 class="cdt-section-title">Get Your Full Results & Action Plan</h2>
                <p>Enter your name and email to create your free account. We'll instantly email you a copy of your results and show them on the next screen.</p>
                <form id="cdt-register-form">
                    <div class="mi-form-field">
                        <label for="cdt_reg_first_name">First Name</label>
                        <input type="text" id="cdt_reg_first_name" required>
                    </div>
                    <div class="mi-form-field">
                        <label for="cdt_reg_email">Email Address</label>
                        <input type="email" id="cdt_reg_email" required>
                    </div>
                    <div class="form-submit-wrapper">
                        <button type="button" id="cdt_register_btn" class="cdt-quiz-button cdt-quiz-button-primary">Email My Results & Create Account</button>
                    </div>
                    <p id="cdt_reg_status" class="form-status"></p>
                </form>
                <p class="form-secondary-action">Already have an account? <a href="${loginUrl}">Log in here</a>.</p>
            </div>`;

        const regBtn = $id('cdt_register_btn');
        const statusEl = $id('cdt_reg_status');
        $id('cdt-register-form').addEventListener('submit', e => e.preventDefault());
        regBtn.addEventListener('click', () => {
            const email = $id('cdt_reg_email').value.trim();
            const firstName = $id('cdt_reg_first_name').value.trim();

            if (!firstName) { statusEl.innerHTML = 'Please enter your first name.'; statusEl.style.color = 'red'; return; }
            if (!email || !/\S+@\S+\.\S+/.test(email)) { statusEl.innerHTML = 'Please enter a valid email address.'; statusEl.style.color = 'red'; return; }

            statusEl.innerHTML = 'Creating your account...';
            statusEl.style.color = 'inherit';
            regBtn.disabled = true;

            const body = new URLSearchParams({
                action: 'miq_magic_register',
                _ajax_nonce: data.miqNonce,
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
        const steps = container.querySelectorAll('.cdt-step');
        if (!steps.length) return;

        steps.forEach(step => {
            const type = step.getAttribute('data-type');
            let radios = Array.from(step.querySelectorAll('input[type=radio]'));
            if (!radios.length) return;
            if (type === 'scenario' || type === 'forced') {
                // Options use index values and carry data-score. Pick any.
                const r = radios[Math.floor(Math.random() * radios.length)];
                r.checked = true;
            } else {
                // Likert 1..5
                const pick = Math.floor(Math.random() * 5) + 1;
                const radio = step.querySelector(`input[value="${pick}"]`) || radios[0];
                if (radio) radio.checked = true;
            }
        });

        // After filling all, calculate and show results directly.
        quizState.autoFilled = true;
        calculateAndShowResults();
    }

    /**
     * Generates and saves results for ALL THREE quizzes (MI, CDT, Bartle) based on a profile.
     * profile: 'average', 'negative' (high strain), 'excellent' (low strain)
     */
    function generateAndSaveAll(profile) {
        if (!confirm(`This will overwrite existing results for MI, CDT, AND Bartle quizzes with ${profile} data. Continue?`)) return;

        const btnId = `cdt-autofill-${profile === 'negative' ? 'neg' : (profile === 'excellent' ? 'exc' : 'avg')}`;
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
                _ajax_nonce: ajaxNonce, // Using same nonce as available
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
    // renderAgeGate removed as per requirement


    function getUrlAge() {
        try { const p = new URLSearchParams(window.location.search); return p.get('age'); } catch (e) { return null; }
    }

    function renderQuiz() {
        if (devTools && !isEmployee) devTools.style.display = 'block'; // Show dev tools
        const questionSet = QUESTIONS[quizState.ageGroup] || QUESTIONS['adult'];
        if (!questionSet) {
            container.innerHTML = `<div class="cdt-quiz-card"><p>Sorry, no questions could be found for your selected group.</p></div>`;
            return;
        }

        // Build a normalized list of items, supporting both legacy and typed schema.
        const items = [];
        Object.entries(questionSet).forEach(([slug, arr]) => {
            arr.forEach(q => {
                if (q && (q.type || q.options || q.pair)) {
                    // New typed schema
                    items.push({
                        cat: slug,
                        type: q.type || 'likert',
                        text: q.text || '',
                        options: Array.isArray(q.options) ? q.options : null,
                        pair: q.pair || null,
                        // By default, all items except contradiction pair affect max score; scoring logic will handle exact max.
                        affectsMax: (q.type !== 'pair')
                    });
                } else {
                    // Legacy schema (Likert with reverse flag)
                    items.push({ cat: slug, type: 'likert', text: q.text, reverse: !!q.reverse, affectsMax: true });
                }
            });
        });
        const questions = shuffle(items);
        // Build lookup of pair keys to their question texts for admin notes.
        const pairMap = {};
        questions.forEach(q => {
            if (q && q.type === 'pair' && q.pair) {
                if (!pairMap[q.pair]) pairMap[q.pair] = [];
                pairMap[q.pair].push(q.text || '');
            }
        });

        let html = `
            <div class="cdt-progress-container"><div class="cdt-progress-bar"></div></div>
            <div class="cdt-steps-container">`;

        // Helper to generate an admin-friendly scoring note per question
        const adminNoteFor = (qNorm) => {
            if (!isAdmin || !qNorm) return '';
            const type = qNorm.type || 'likert';
            if (type === 'scenario') {
                const optionLines = (qNorm.options || []).map(o => `${o.text} = ${o.score} points`).join('; ');
                return `<div class="cdt-admin-note">Scoring: Scenario-based. Options award 0–3 points (higher = more dissonance tolerance). The first scenario in this category is multiplied by your confidence (1–5 → 0.25, 0.5, 0.75, 1.0, 1.25). Option mapping: ${optionLines}.</div>`;
            }
            if (type === 'forced') {
                const scores = (qNorm.options || []).map(o => `${o.text} = ${o.score}`).join('; ');
                return `<div class="cdt-admin-note">Scoring: Forced-choice. CDT-aligned option = 3 points; the other = 0 points. Mapping: ${scores}.</div>`;
            }
            if (type === 'virtue') {
                return `<div class="cdt-admin-note">Scoring: Virtue trap. Likert (1–5) is reverse-scored to 0–4 (Agree strongly = 0, Disagree strongly = 4). Higher = greater CDT alignment.</div>`;
            }
            if (type === 'pair') {
                const pairKeyRaw = qNorm.pair || '';
                const pairKey = pairKeyRaw ? `(${pairKeyRaw})` : '';
                const others = (pairMap[pairKeyRaw] || []).filter(t => t && t !== qNorm.text);
                const pairedText = others.length ? ` Paired with: “${others.join('” / “')}”.` : '';
                return `<div class="cdt-admin-note">Scoring: Contradiction pair ${pairKey}. This item adds no base points. If both paired statements are rated high (≥ 4), deduct 2 points once from this category.${pairedText}</div>`;
            }
            if (type === 'others') {
                return `<div class="cdt-admin-note">Scoring: How others see me. Likert (1–5) scored as-is (1–5 points).</div>`;
            }
            if (type === 'confidence') {
                return `<div class="cdt-admin-note">Scoring: Confidence prompt. Provides a multiplier only (1–5 → 0.25×, 0.5×, 0.75×, 1.0×, 1.25×) applied to the first scenario-based question in this category.</div>`;
            }
            // Legacy likert
            if (qNorm.type === 'likert') {
                return `<div class="cdt-admin-note">Scoring: Likert (1–5)${qNorm.reverse ? ', reverse-scored to 1–5 → 5–1' : ''}.</div>`;
            }
            return '';
        };

        questions.forEach((q, i) => {
            const stepAttrs = [`data-step="${i}"`, `data-cat="${q.cat}"`, `data-type="${q.type || 'likert'}"`, `data-affects-max="${q.affectsMax ? 'true' : 'false'}"`];
            if (q.pair) stepAttrs.push(`data-pair="${q.pair}"`);

            let innerHtml = '';
            if (q.type === 'scenario' && Array.isArray(q.options)) {
                // Options have explicit per-option scores
                // Use four-equal layout so tiles look balanced
                innerHtml += `<div class="cdt-quiz-likert-options cdt-four-equal">`;
                q.options.forEach((opt, idx) => {
                    innerHtml += `<label class="cdt-quiz-option-wrapper"><input type="radio" name="q_${i}" value="${idx}" data-score="${parseInt(opt.score, 10) || 0}" required><span>${opt.text}</span></label>`;
                });
                innerHtml += `</div>`;
                // Encode a hint for max computation
                stepAttrs.push('data-max-base="3"');
            } else if (q.type === 'forced' && Array.isArray(q.options)) {
                // Two-option forced choice: mark container for equalized layout
                innerHtml += `<div class="cdt-quiz-likert-options cdt-two-equal">`;
                q.options.forEach((opt, idx) => {
                    innerHtml += `<label class="cdt-quiz-option-wrapper"><input type="radio" name="q_${i}" value="${idx}" data-score="${parseInt(opt.score, 10) || 0}" required><span>${opt.text}</span></label>`;
                });
                innerHtml += `</div>`;
                stepAttrs.push('data-max-base="3"');
            } else if (q.type === 'virtue') {
                // Likert 1-5, reverse mapped to 0-4 (Agree -> 0, Disagree -> 4)
                let opts = '';
                for (let v = 1; v <= 5; v++) {
                    opts += `<label class="cdt-quiz-option-wrapper"><input type="radio" name="q_${i}" value="${v}" required><span>${LIKERT[v]}</span></label>`;
                }
                innerHtml += `<div class="cdt-quiz-likert-options">${opts}</div>`;
                stepAttrs.push('data-reverse-scale="0_4"');
                stepAttrs.push('data-max-base="4"');
            } else if (q.type === 'pair') {
                // Likert 1-5, contributes no base score; used for inconsistency penalty
                let opts = '';
                for (let v = 1; v <= 5; v++) {
                    opts += `<label class="cdt-quiz-option-wrapper"><input type="radio" name="q_${i}" value="${v}" required><span>${LIKERT[v]}</span></label>`;
                }
                innerHtml += `<div class="cdt-quiz-likert-options">${opts}</div>`;
                stepAttrs.push('data-max-base="0"');
            } else if (q.type === 'others') {
                // Likert 1-5, scored as-is (1..5)
                let opts = '';
                for (let v = 1; v <= 5; v++) {
                    opts += `<label class="cdt-quiz-option-wrapper"><input type="radio" name="q_${i}" value="${v}" required><span>${LIKERT[v]}</span></label>`;
                }
                innerHtml += `<div class="cdt-quiz-likert-options">${opts}</div>`;
                stepAttrs.push('data-max-base="5"');
            } else if (q.type === 'confidence') {
                // Likert 1-5, mapped to multiplier [0.25, 0.5, 0.75, 1.0, 1.25]
                let opts = '';
                for (let v = 1; v <= 5; v++) {
                    opts += `<label class="cdt-quiz-option-wrapper"><input type="radio" name="q_${i}" value="${v}" required><span>${LIKERT[v]}</span></label>`;
                }
                innerHtml += `<div class="cdt-quiz-likert-options">${opts}</div>`;
                stepAttrs.push('data-max-base="0"');
            } else {
                // Legacy likert (or default)
                let opts = '';
                for (let v = 1; v <= 5; v++) {
                    opts += `<label class="cdt-quiz-option-wrapper"><input type="radio" name="q_${i}" value="${v}" required><span>${LIKERT[v]}</span></label>`;
                }
                innerHtml += `<div class="cdt-quiz-likert-options">${opts}</div>`;
                if (q.reverse) stepAttrs.push('data-reverse="true"');
                stepAttrs.push('data-max-base="5"');
            }

            const adminNote = adminNoteFor(q);
            const adminAwardBlock = isAdmin ? `<div class="cdt-admin-award" data-admin-award></div>` : '';
            html += `<div class="cdt-step" ${stepAttrs.join(' ')} style="display:none;">
                        <div class="cdt-quiz-card">
                            <p class="cdt-quiz-question-text">${q.text}</p>
                            ${innerHtml}
                            ${adminNote}
                            ${adminAwardBlock}
                        </div>
                     </div>`;
        });
        html += `</div><div class="cdt-quiz-footer">
            <button type="button" id="cdt-prev-btn" class="cdt-quiz-button cdt-quiz-button-secondary" disabled>Previous</button>
            <button type="button" id="cdt-finish-btn" class="cdt-quiz-button cdt-quiz-button-primary" style="display:none;">Finish</button>
        </div>`;
        container.innerHTML = html;

        const steps = container.querySelectorAll('.cdt-step');
        const prevBtn = $id('cdt-prev-btn');
        const finishBtn = $id('cdt-finish-btn');
        const bar = container.querySelector('.cdt-progress-bar');
        let currentStep = 0;
        const totalSteps = steps.length;

        const computeAdminAwardForStep = (stepEl) => {
            if (!isAdmin || !stepEl) return;
            const target = stepEl.querySelector('[data-admin-award]');
            if (!target) return;
            const type = stepEl.getAttribute('data-type') || 'likert';
            const input = stepEl.querySelector('input:checked');
            if (!input) { target.innerHTML = ''; return; }
            const rawVal = parseInt(input.value, 10);
            let baseScore = 0;
            let label = '';
            if (type === 'scenario' || type === 'forced') {
                const optScore = parseInt(input.getAttribute('data-score'), 10);
                baseScore = isNaN(optScore) ? 0 : optScore;

                // For scenarios, check if this might get a confidence multiplier
                if (type === 'scenario') {
                    const cat = stepEl.getAttribute('data-cat');
                    // Find confidence multiplier for this category
                    let confidenceMultiplier = null;
                    const allSteps = container.querySelectorAll('.cdt-step');
                    Array.from(allSteps).forEach(s => {
                        if (s.getAttribute('data-cat') === cat && s.getAttribute('data-type') === 'confidence') {
                            const confInput = s.querySelector('input:checked');
                            if (confInput) {
                                const confVal = parseInt(confInput.value, 10);
                                const map = { 1: 0.25, 2: 0.5, 3: 0.75, 4: 1.0, 5: 1.25 };
                                confidenceMultiplier = map[confVal] || 1.0;
                            }
                        }
                    });

                    // Count how many scenarios in this category have been answered so far
                    let scenariosSoFar = 0;
                    Array.from(allSteps).forEach(s => {
                        if (s.getAttribute('data-cat') === cat &&
                            s.getAttribute('data-type') === 'scenario' &&
                            s.querySelector('input:checked')) {
                            scenariosSoFar++;
                        }
                    });

                    if (confidenceMultiplier !== null && scenariosSoFar === 1) {
                        // This is the first scenario in this category, so it gets the multiplier
                        const multipliedScore = baseScore * confidenceMultiplier;
                        const categoryName = CATS[cat] || cat || 'this category';
                        label = `<strong>Awarded: ${multipliedScore.toFixed(2)} pts</strong> (${baseScore} × ${confidenceMultiplier} confidence multiplier for ${categoryName})`;
                    } else if (confidenceMultiplier !== null && scenariosSoFar > 1) {
                        // This is a subsequent scenario, no multiplier
                        label = `<strong>Awarded: ${baseScore} pts</strong> (no multiplier - only first scenario in category gets confidence boost)`;
                    } else {
                        // No confidence set yet or unknown
                        label = `<strong>Awarded: ${baseScore} pts</strong> (may be modified by confidence multiplier)`;
                    }
                } else {
                    label = `<strong>Awarded: ${baseScore} pts</strong>`;
                }
            } else if (type === 'virtue') {
                baseScore = Math.max(0, 5 - rawVal); // 0..4
                label = `<strong>Awarded: ${baseScore} pts</strong> (reverse Likert)`;
            } else if (type === 'others') {
                baseScore = rawVal; // 1..5
                label = `<strong>Awarded: ${baseScore} pts</strong>`;
            } else if (type === 'pair') {
                const pairKey = stepEl.getAttribute('data-pair');
                const cat = stepEl.getAttribute('data-cat');
                const categoryName = CATS[cat] || cat || 'this category';

                // Find the paired item(s) and their current answers
                const allSteps = container.querySelectorAll('.cdt-step');
                const pairItems = [];
                let pairedAnswers = [];

                Array.from(allSteps).forEach(s => {
                    if (s.getAttribute('data-pair') === pairKey && s !== stepEl) {
                        const pairText = s.querySelector('.cdt-quiz-question-text')?.textContent || 'Unknown question';
                        pairItems.push(pairText.substring(0, 60) + (pairText.length > 60 ? '...' : ''));

                        const pairInput = s.querySelector('input:checked');
                        if (pairInput) {
                            pairedAnswers.push(parseInt(pairInput.value, 10));
                        }
                    }
                });

                // Determine penalty status and points awarded
                let penaltyStatus = '';
                let pointsAwarded = '0 pts';
                const currentAnswer = rawVal;
                const allAnswers = [...pairedAnswers, currentAnswer];
                const highAnswers = allAnswers.filter(v => v >= 4);

                if (pairedAnswers.length === 0) {
                    penaltyStatus = ' - <em>waiting for paired item</em>';
                } else if (pairedAnswers.length > 0) {
                    if (highAnswers.length >= 2) {
                        pointsAwarded = '-2 pts';
                        penaltyStatus = ` - <span style="color: #d63384;"><strong>PENALTY TRIGGERED!</strong> Both items rated ≥4</span>`;
                    } else if (currentAnswer >= 4 && pairedAnswers.some(v => v >= 4)) {
                        pointsAwarded = '-2 pts';
                        penaltyStatus = ` - <span style="color: #d63384;"><strong>PENALTY TRIGGERED!</strong> Both items rated ≥4</span>`;
                    } else if (currentAnswer >= 4) {
                        penaltyStatus = ` - <span style="color: #fd7e14;">High rating (${currentAnswer}), penalty if pair also rated ≥4</span>`;
                    } else {
                        penaltyStatus = ' - <span style="color: #198754;">No penalty (at least one item < 4)</span>';
                    }
                }

                const pairInfo = pairItems.length > 0 ? `<br><small>Paired with: "${pairItems.join('", "')}"</small>` : '';
                label = `<strong>Awarded: ${pointsAwarded}</strong> (contradiction pair ${pairKey})${penaltyStatus}${pairInfo}`;
            } else if (type === 'confidence') {
                const map = { 1: 0.25, 2: 0.5, 3: 0.75, 4: 1.0, 5: 1.25 };
                const multiplier = map[rawVal] || 1.0;
                const cat = stepEl.getAttribute('data-cat');
                const categoryName = CATS[cat] || cat || 'this category';
                label = `<strong>Awarded: ${multiplier}× multiplier for scenarios in ${categoryName}</strong>`;
            } else {
                const isReverse = stepEl.getAttribute('data-reverse') === 'true';
                baseScore = isReverse ? (6 - rawVal) : rawVal;
                label = `<strong>Awarded: ${baseScore} pts</strong>${isReverse ? ' (reverse Likert)' : ''}`;
            }
            target.innerHTML = label;
        };

        const updateNavState = () => {
            const hasAnswer = !!steps[currentStep].querySelector('input:checked');
            prevBtn.disabled = (currentStep === 0);
            // Show finish button only on last step and only if answered
            if (currentStep >= totalSteps - 1) {
                finishBtn.style.display = hasAnswer ? 'inline-block' : 'none';
            } else {
                finishBtn.style.display = 'none';
            }
        };

        const showStep = (k) => {
            steps.forEach((s, j) => s.style.display = j === k ? 'block' : 'none');
            currentStep = k;
            bar.style.width = ((k + 1) / totalSteps * 100) + '%';
            updateNavState();
            computeAdminAwardForStep(steps[k]);
        };

        prevBtn.addEventListener('click', () => { if (currentStep > 0) { showStep(currentStep - 1); } });
        finishBtn.addEventListener('click', () => {
            // Ensure all questions are answered
            const allAnswered = Array.from(steps).every(s => s.querySelector('input:checked'));
            if (!allAnswered) {
                // jump to first unanswered
                const idx = steps.findIndex(s => !s.querySelector('input:checked'));
                if (idx >= 0) showStep(idx);
                return;
            }
            if (isAdmin && !quizState.autoFilled) {
                const proceed = confirm('All questions answered. Finish and see results now? Click Cancel to review.');
                if (!proceed) return;
            }
            calculateAndShowResults();
        });

        steps.forEach((s, stepIndex) => s.querySelectorAll('input[type=radio]').forEach(inp => inp.addEventListener('change', () => {
            computeAdminAwardForStep(s);
            updateNavState();

            // Auto-advance to next question after a short delay (matching MI quiz timing)
            setTimeout(() => {
                if (stepIndex < totalSteps - 1) {
                    showStep(stepIndex + 1);
                }
            }, 120);
        })));

        showStep(0);
    }

    function calculateAndShowResults() {
        const scores = {};
        const maxByCat = {};
        const scenarioCount = {};
        const forcedCount = {};
        const virtueCount = {};
        const othersCount = {};
        const pairAnswers = {}; // { pairKey: { cat, vals: [v1, v2, ...] } }
        const confidenceByCat = {}; // { cat: multiplier }
        const appliedScenarioMultiplier = {}; // { cat: boolean }

        Object.keys(CATS).forEach(k => { scores[k] = 0; maxByCat[k] = 0; scenarioCount[k] = 0; forcedCount[k] = 0; virtueCount[k] = 0; othersCount[k] = 0; appliedScenarioMultiplier[k] = false; });

        const steps = Array.from(container.querySelectorAll('.cdt-step'));

        // Pass 1: collect confidence answers first so multipliers are available regardless of order
        steps.forEach(s => {
            const cat = s.getAttribute('data-cat');
            const type = s.getAttribute('data-type') || 'likert';
            if (!cat || type !== 'confidence') return;
            const input = s.querySelector('input:checked');
            if (!input) return;
            const rawVal = parseInt(input.value, 10);
            const map = { 1: 0.25, 2: 0.5, 3: 0.75, 4: 1.0, 5: 1.25 };
            confidenceByCat[cat] = map[rawVal] || 1.0;
        });

        // Pass 2: compute base scores and gather counts/pairs
        steps.forEach(s => {
            const cat = s.getAttribute('data-cat');
            const type = s.getAttribute('data-type') || 'likert';
            if (!cat) return;
            const input = s.querySelector('input:checked');
            if (!input) return;
            const rawVal = parseInt(input.value, 10);

            if (type === 'pair') {
                const pairKey = s.getAttribute('data-pair');
                if (pairKey) {
                    if (!pairAnswers[pairKey]) pairAnswers[pairKey] = { cat, vals: [] };
                    pairAnswers[pairKey].vals.push(rawVal);
                }
                return; // no base score
            }

            let baseScore = 0;
            if (type === 'scenario' || type === 'forced') {
                const optScore = parseInt(input.getAttribute('data-score'), 10);
                baseScore = isNaN(optScore) ? 0 : optScore;
                if (type === 'scenario') scenarioCount[cat] = (scenarioCount[cat] || 0) + 1;
                if (type === 'forced') forcedCount[cat] = (forcedCount[cat] || 0) + 1;
            } else if (type === 'virtue') {
                baseScore = Math.max(0, 5 - rawVal); // 0..4
                virtueCount[cat] = (virtueCount[cat] || 0) + 1;
            } else if (type === 'others') {
                baseScore = rawVal; // 1..5
                othersCount[cat] = (othersCount[cat] || 0) + 1;
            } else if (type === 'confidence') {
                // already handled in pass 1
                return;
            } else {
                // Legacy likert
                const isReverse = s.getAttribute('data-reverse') === 'true';
                baseScore = isReverse ? (6 - rawVal) : rawVal;
            }

            if (type === 'scenario' && !appliedScenarioMultiplier[cat]) {
                const mult = confidenceByCat[cat] || 1.0;
                baseScore = baseScore * mult;
                appliedScenarioMultiplier[cat] = true;
            }

            scores[cat] += baseScore;

            // Capture answer
            const text = s.querySelector('.cdt-quiz-question-text')?.textContent || ('Question ' + (steps.indexOf(s) + 1));
            // Store selected value or score. For consistency with MI, let's store the raw value provided by the user.
            // But scenarios/forced have data-score. Let's store the mapped score if available, or raw value.
            // Actually, for display purposes "Strongly Agree" (5) is better than "1".
            // Let's store the value the user selected (1-5) or the index for scenarios.
            quizState.answers[text] = rawVal;
        });

        // Compute max per category based on counts and multiplier max for first scenario
        Object.keys(CATS).forEach(cat => {
            let max = 0;
            if ((scenarioCount[cat] || 0) > 0) {
                // One scenario can be boosted by max confidence (1.25)
                max += 3 * 1.25 + (scenarioCount[cat] - 1) * 3;
            }
            max += (forcedCount[cat] || 0) * 3;
            max += (virtueCount[cat] || 0) * 4; // virtue mapped 0..4
            max += (othersCount[cat] || 0) * 5;
            // Legacy items in this cat (if any) are not tracked; approximate by scanning DOM for legacy in this cat
            const legacySteps = steps.filter(s => s.getAttribute('data-cat') === cat && !['scenario', 'forced', 'virtue', 'others', 'pair', 'confidence'].includes(s.getAttribute('data-type')));
            max += legacySteps.length * 5;
            maxByCat[cat] = max;
        });

        // Apply contradiction-pair penalties: if both answers in a pair are high (>=4), deduct 2 from that category
        Object.keys(pairAnswers).forEach(k => {
            const entry = pairAnswers[k];
            const vals = entry.vals || [];
            if (vals.length >= 2) {
                const high = vals.filter(v => v >= 4).length;
                if (high >= 2) {
                    scores[entry.cat] = Math.max(0, (scores[entry.cat] || 0) - 2);
                }
            }
        });

        quizState.scores = scores;
        quizState.maxByCat = maxByCat;
        quizState.sortedScores = Object.entries(scores)
            .filter(([k]) => !k.startsWith('si-')) // Exclude Strain Index categories
            .sort((a, b) => b[1] - a[1]);

        // Check if user needs to register before showing results
        if (!isLoggedIn) {
            showLoginRegister();
            return;
        }

        if (isEmployee) {
            saveResultsToServer();
            renderEmployeeComplete();
        } else {
            saveResultsToServer();
            renderResults();
        }
    }

    function renderResults() {
        // Ensure any staging UI is hidden when showing results
        try {
            const stageEl = document.getElementById('cdt-stage');
            if (stageEl) stageEl.style.display = 'none';
            const toolbar = document.getElementById('cdt-toolbar');
            if (toolbar) toolbar.style.display = 'none';
            const containerEl = document.getElementById('cdt-quiz-container');
            if (containerEl) containerEl.style.display = 'block';
        } catch (e) { }
        if (devTools) devTools.style.display = 'none'; // Hide dev tools
        const { sortedScores, ageGroup } = quizState;
        const userFirstName = currentUser ? currentUser.firstName : 'Valued User';

        // Focus on the two lowest scoring dimensions for growth opportunities
        const bottomTwoDimensions = sortedScores.slice(-2).reverse(); // Get last 2, reverse to show lowest first
        const lowestDimensionSlug = bottomTwoDimensions[0] ? bottomTwoDimensions[0][0] : null;
        const secondLowestDimensionSlug = bottomTwoDimensions[1] ? bottomTwoDimensions[1][0] : null;
        const topDimensionSlug = sortedScores[0][0];
        const maxScore = (quizState.maxByCat && quizState.maxByCat[topDimensionSlug]) ? quizState.maxByCat[topDimensionSlug] : ((QUESTIONS[ageGroup]?.[topDimensionSlug]?.length || 10) * 5);

        // --- Helper Functions for Building HTML ---
        const bar = (score, max) => {
            const pct = Math.max(0, Math.min(100, (score / max) * 100));
            const col = pct >= 75 ? '#4CAF50' : (pct < 40 ? '#f44336' : '#ffc107');
            return `<div class="cdt-bar-wrapper"><div class="cdt-bar-inner" style="width:${pct}%;background-color:${col};"></div></div>`;
        };

        const createDetailCard = (slug, type) => {
            const details = ALL_DIMENSION_DETAILS[slug];
            if (!details) return '';

            const scoreData = sortedScores.find(s => s[0] === slug);
            const score = scoreData ? scoreData[1] : 0;
            const localMax = (quizState.maxByCat && quizState.maxByCat[slug]) ? quizState.maxByCat[slug] : maxScore;
            const isHighScorer = score >= (localMax * 0.6); // Consider scores >= 60% as high

            // For low scores, focus on the growth perspective
            if (type === 'low') {
                return `
                    <div class="cdt-dimension-card cdt-growth-focus">
                        <h3 class="cdt-dimension-card-title">Focus Area for Growth: ${details.title}</h3>
                        
                        <div class="cdt-definition">
                            <h4>What This Means</h4>
                            <p>${details.definition || ''}</p>
                        </div>
                        
                        <div class="cdt-dimension-header">
                            <span class="cdt-dimension-title">Your Score</span>
                            <span class="cdt-dimension-score">${score} / ${localMax}</span>
                        </div>
                        ${bar(score, localMax)}
                        
                        <div class="cdt-detail-section">
                            <div class="cdt-growth-snapshot">
                                <h4>Growth Snapshot</h4>
                                <p>${details.growth_snapshot || ''}</p>
                            </div>
                            
                            <div class="cdt-watch-out">
                                <h4>Watch Out For</h4>
                                <p>${details.watch_out_for || ''}</p>
                            </div>
                            
                            <div class="cdt-character-sketch">
                                <h4>Character Sketch</h4>
                                <p class="cdt-character-sketch-intro">${details.character_sketch_intro || ''}</p>
                                <ul class="cdt-character-sketch-phrases">${(details.character_sketch_phrases || []).map(phrase => `<li>${phrase}</li>`).join('')}</ul>
                            </div>
                            
                            <div class="cdt-relational-applications">
                                <h4>Relational Applications</h4>
                                <div class="cdt-relational-grid">
                                    ${details.relational_applications ? Object.entries(details.relational_applications).map(([key, value]) => `
                                        <div class="cdt-relational-item">
                                            <strong>${key.charAt(0).toUpperCase() + key.slice(1)}:</strong> ${value}
                                        </div>
                                    `).join('') : ''}
                                </div>
                            </div>
                            
                            <div class="cdt-quick-prompts">
                                <h4>Quick Prompts</h4>
                                <ul class="cdt-quick-prompts-list">${(details.quick_prompts || []).map(prompt => `<li>${prompt}</li>`).join('')}</ul>
                            </div>
                        </div>
                    </div>
                `;
            } else {
                // High scoring card - keep existing structure but add new elements
                const ageGroupKey = quizState.ageGroup === 'graduate' ? 'graduate' : (quizState.ageGroup === 'teen' ? 'teen' : 'adult');
                const ageGroupDetails = details[ageGroupKey] || details['adult'];
                const watchOutText = isHighScorer ? ageGroupDetails.watchOutHigh : ageGroupDetails.watchOutLow;

                return `
                    <div class="cdt-dimension-card cdt-strength-focus">
                        <h3 class="cdt-dimension-card-title">Your Greatest Strength: ${details.title}</h3>
                        
                        <div class="cdt-definition">
                            <h4>What This Means</h4>
                            <p>${details.definition || ''}</p>
                        </div>
                        
                        <div class="cdt-dimension-header">
                            <span class="cdt-dimension-title">Your Score</span>
                            <span class="cdt-dimension-score">${score} / ${localMax}</span>
                        </div>
                        ${bar(score, localMax)}
                        
                        <div class="cdt-detail-section">
                            <div class="cdt-growth-snapshot">
                                <h4>Growth Snapshot</h4>
                                <p>${details.growth_snapshot || ''}</p>
                            </div>
                            
                            <div class="cdt-watch-out">
                                <h4>Watch Out For</h4>
                                <p>${details.watch_out_for || watchOutText || ''}</p>
                            </div>
                            
                            <div class="cdt-character-sketch">
                                <h4>Character Sketch</h4>
                                <p class="cdt-character-sketch-intro">${details.character_sketch_intro || ''}</p>
                                <ul class="cdt-character-sketch-phrases">${(details.character_sketch_phrases || []).map(phrase => `<li>${phrase}</li>`).join('')}</ul>
                            </div>
                            
                            <div class="cdt-relational-applications">
                                <h4>Relational Applications</h4>
                                <div class="cdt-relational-grid">
                                    ${details.relational_applications ? Object.entries(details.relational_applications).map(([key, value]) => `
                                        <div class="cdt-relational-item">
                                            <strong>${key.charAt(0).toUpperCase() + key.slice(1)}:</strong> ${value}
                                        </div>
                                    `).join('') : ''}
                                </div>
                            </div>
                            
                            <div class="cdt-quick-prompts">
                                <h4>Quick Prompts</h4>
                                <ul class="cdt-quick-prompts-list">${(details.quick_prompts || []).map(prompt => `<li>${prompt}</li>`).join('')}</ul>
                            </div>
                        </div>
                    </div>
                `;
            }
        };

        // --- Build HTML Sections ---
        const headerHtml = `
            <div class="results-main-header">
                <div class="site-branding">
                    <span class="site-title">The Science of Teamwork</span>
                </div>
            </div>
            <div class="cdt-results-header">
                <h1>Your Growth Strengths Results</h1>
                <h2>Results for ${userFirstName}</h2>
                <p class="cdt-results-metadata">Generated on: ${new Date().toLocaleDateString()}</p>
                <p class="cdt-results-summary">Your Growth Strengths profile reveals how you respond to inner conflict, contradictions, and uncertainty — and how you can grow from them.</p>
            </div>`;

        const overviewHtml = `
            <div class="cdt-results-section">
                <h3 class="cdt-section-title">Your Profile Overview</h3>
                <div class="cdt-overview-list">
                    ${sortedScores.map(([slug, score]) => {
            const details = ALL_DIMENSION_DETAILS[slug] || {};
            return `
                        <div class="cdt-overview-item">
                            <div class="cdt-overview-header">
                                <span class="cdt-dimension-title">${details.title || CATS[slug]}</span>
                                <span class="cdt-dimension-score">${score} / ${(quizState.maxByCat && quizState.maxByCat[slug]) ? quizState.maxByCat[slug] : maxScore}</span>
                            </div>
                            ${bar(score, (quizState.maxByCat && quizState.maxByCat[slug]) ? quizState.maxByCat[slug] : maxScore)}
                        </div>
                    `}).join('')}
                </div>
            </div>`;

        const dimensionsHtml = `
            <div class="cdt-results-section">
                <h3 class="cdt-section-title">Your Growth Focus Areas</h3>
                <p class="cdt-growth-intro">Your journey of growth starts with understanding where you have the most room to develop. These two areas represent your greatest opportunities for building resilience and expanding your capacity.</p>
            </div>
            <div class="cdt-detail-cards-container">
                ${lowestDimensionSlug ? createDetailCard(lowestDimensionSlug, 'low') : ''}
                ${secondLowestDimensionSlug ? createDetailCard(secondLowestDimensionSlug, 'low') : ''}
            </div>`;

        let nextStepsHtml = '';
        if (nextStepUrl) {
            nextStepsHtml = `
                <div class="cdt-results-section cdt-next-steps-section">
                    <h3 class="cdt-section-title">Your Next Step: Discover Your Core Motivations</h3>
                    <p>You've explored how you handle inner conflict. Now, discover what truly motivates you. The Core Motivation Assessment reveals your primary drivers in challenges, learning, and collaboration.</p>
                    <div class="cdt-results-actions">
                        <a href="${nextStepUrl}" class="cdt-quiz-button cdt-quiz-button-primary">${nextStepTitle}</a>
                    </div>
                </div>`;
        }

        let predictionHtml = '';
        if (predictionData && predictionData.templates && predictionData.miResults && predictionData.cdtResults) {
            const { miResults, cdtResults, templates, miCategories, cdtCategories } = predictionData;

            const bartleScores = { explorer: 0, achiever: 0, socializer: 0, strategist: 0 };
            const miMap = {
                'logical-mathematical': ['achiever', 'strategist'], 'linguistic': ['socializer'],
                'spatial': ['explorer'], 'bodily-kinesthetic': ['achiever'],
                'musical': ['explorer', 'socializer'], 'interpersonal': ['socializer', 'strategist'],
                'intrapersonal': ['explorer'], 'naturalistic': ['explorer'],
            };
            const cdtMap = {
                'ambiguity-tolerance': ['explorer'],
                'value-conflict-navigation': ['socializer'],
                'self-confrontation-capacity': ['achiever'],
                'discomfort-regulation': ['achiever', 'strategist'],
                'growth-orientation': ['explorer']
            };

            if (miResults.top3 && miResults.top3.length) {
                let points = 3;
                miResults.top3.forEach(miSlug => {
                    if (miMap[miSlug]) {
                        miMap[miSlug].forEach(bartleType => { bartleScores[bartleType] += points; });
                    }
                    points--;
                });
            }

            if (cdtResults.sortedScores && cdtResults.sortedScores.length) {
                const cdtSlug = cdtResults.sortedScores[0][0];
                if (cdtMap[cdtSlug]) {
                    cdtMap[cdtSlug].forEach(bartleType => { bartleScores[bartleType] += 3; });
                }
            }

            const sortedBartle = Object.entries(bartleScores).sort((a, b) => b[1] - a[1]);
            const predictedType = sortedBartle.length ? sortedBartle[0][0] : null;

            if (predictedType && templates[predictedType]) {
                const template = templates[predictedType][Math.floor(Math.random() * templates[predictedType].length)];
                const miNames = miResults.top3.map(slug => miCategories[slug] || '');
                const miStrengthsStr = 'a combination of ' + miNames.filter(n => n).join(', ');
                const cdtSlug = cdtResults.sortedScores[0][0];
                const cdtStrengthsStr = 'a high capacity for ' + (cdtCategories[cdtSlug] || 'navigating challenges');
                const predictionParagraph = template.replace('{mi_strengths}', miStrengthsStr).replace('{cdt_strengths}', cdtStrengthsStr);

                predictionHtml = `<div class="cdt-results-section cdt-prediction-section"><h3 class="cdt-section-title">Your Personalized Bartle Prediction</h3><p>${predictionParagraph}</p></div>`;
            }
        }

        // --- Admin Scoring Breakdown Table ---
        let adminBreakdownHtml = '';
        if (isAdmin || quizState.autoFilled) {
            const createAdminBreakdownTable = () => {
                const steps = Array.from(container.querySelectorAll('.cdt-step'));
                const scoreRows = [];
                const penalties = {};
                const confidenceMultipliers = {};
                const appliedScenarioMultipliers = {};

                // First pass: collect confidence multipliers
                steps.forEach(s => {
                    const cat = s.getAttribute('data-cat');
                    const type = s.getAttribute('data-type') || 'likert';
                    if (cat && type === 'confidence') {
                        const input = s.querySelector('input:checked');
                        if (input) {
                            const rawVal = parseInt(input.value, 10);
                            const map = { 1: 0.25, 2: 0.5, 3: 0.75, 4: 1.0, 5: 1.25 };
                            confidenceMultipliers[cat] = map[rawVal] || 1.0;
                        }
                    }
                });

                // Second pass: process all questions
                steps.forEach(s => {
                    const cat = s.getAttribute('data-cat');
                    const type = s.getAttribute('data-type') || 'likert';
                    const questionText = s.querySelector('.cdt-quiz-question-text')?.textContent || 'Unknown question';
                    const categoryName = CATS[cat] || cat || 'Unknown';
                    const input = s.querySelector('input:checked');

                    if (!input) return;

                    const rawVal = parseInt(input.value, 10);
                    let score = 0;
                    let reason = '';

                    if (type === 'scenario' || type === 'forced') {
                        const optScore = parseInt(input.getAttribute('data-score'), 10);
                        score = isNaN(optScore) ? 0 : optScore;

                        if (type === 'scenario' && !appliedScenarioMultipliers[cat]) {
                            const mult = confidenceMultipliers[cat] || 1.0;
                            score = score * mult;
                            reason = `${type} (${optScore} × ${mult} confidence multiplier)`;
                            appliedScenarioMultipliers[cat] = true;
                        } else if (type === 'scenario') {
                            reason = `${type} (no multiplier - only first scenario gets confidence boost)`;
                        } else {
                            reason = type;
                        }
                    } else if (type === 'virtue') {
                        score = Math.max(0, 5 - rawVal);
                        reason = `virtue (Likert ${rawVal} → ${score}, reverse scored)`;
                    } else if (type === 'others') {
                        score = rawVal;
                        reason = `others (Likert ${rawVal} as-is)`;
                    } else if (type === 'pair') {
                        score = 0;
                        reason = 'contradiction pair (no base score)';
                        // We'll handle penalties separately
                    } else if (type === 'confidence') {
                        score = 0;
                        const mult = confidenceMultipliers[cat] || 1.0;
                        reason = `confidence (${mult}× multiplier for scenarios)`;
                    } else {
                        // Legacy likert
                        const isReverse = s.getAttribute('data-reverse') === 'true';
                        score = isReverse ? (6 - rawVal) : rawVal;
                        reason = `likert${isReverse ? ' (reverse)' : ''}`;
                    }

                    scoreRows.push({
                        question: questionText.length > 80 ? questionText.substring(0, 80) + '...' : questionText,
                        category: categoryName,
                        score: score.toFixed(2),
                        reason: reason
                    });
                });

                // Handle contradiction penalties
                const pairAnswers = {};
                steps.forEach(s => {
                    const type = s.getAttribute('data-type');
                    const pairKey = s.getAttribute('data-pair');
                    const cat = s.getAttribute('data-cat');
                    if (type === 'pair' && pairKey && cat) {
                        const input = s.querySelector('input:checked');
                        if (input) {
                            const rawVal = parseInt(input.value, 10);
                            if (!pairAnswers[pairKey]) pairAnswers[pairKey] = { cat, vals: [] };
                            pairAnswers[pairKey].vals.push(rawVal);
                        }
                    }
                });

                Object.keys(pairAnswers).forEach(pairKey => {
                    const entry = pairAnswers[pairKey];
                    const vals = entry.vals || [];
                    if (vals.length >= 2) {
                        const high = vals.filter(v => v >= 4).length;
                        if (high >= 2) {
                            const categoryName = CATS[entry.cat] || entry.cat || 'Unknown';
                            scoreRows.push({
                                question: `Contradiction penalty for pair ${pairKey}`,
                                category: categoryName,
                                score: '-2.00',
                                reason: `both items rated ≥4 (${vals.join(', ')})`
                            });
                        }
                    }
                });

                // Calculate totals by category
                const categoryTotals = {};
                scoreRows.forEach(row => {
                    if (!categoryTotals[row.category]) categoryTotals[row.category] = 0;
                    categoryTotals[row.category] += parseFloat(row.score);
                });

                const grandTotal = Object.values(categoryTotals).reduce((sum, val) => sum + val, 0);

                let tableHtml = `
                    <div class="cdt-results-section cdt-admin-breakdown">
                        <h3 class="cdt-section-title">Admin: Detailed Scoring Breakdown</h3>
                        <table class="cdt-admin-table">
                            <thead>
                                <tr>
                                    <th style="text-align: left; width: 40%;">Question</th>
                                    <th style="text-align: left; width: 20%;">Category</th>
                                    <th style="text-align: center; width: 15%;">Score</th>
                                    <th style="text-align: left; width: 25%;">Reason</th>
                                </tr>
                            </thead>
                            <tbody>`;

                scoreRows.forEach(row => {
                    const scoreColor = parseFloat(row.score) < 0 ? 'color: #d63384;' : '';
                    tableHtml += `
                        <tr>
                            <td style="font-size: 0.9em;">${row.question}</td>
                            <td style="font-size: 0.9em;">${row.category}</td>
                            <td style="text-align: center; ${scoreColor} font-weight: bold;">${row.score}</td>
                            <td style="font-size: 0.9em; color: #666;">${row.reason}</td>
                        </tr>`;
                });

                // Add category subtotals
                tableHtml += '<tr style="border-top: 2px solid #ddd; font-weight: bold;"><td colspan="4" style="padding-top: 15px; font-size: 1.1em;">Category Totals:</td></tr>';
                Object.entries(categoryTotals).sort((a, b) => b[1] - a[1]).forEach(([cat, total]) => {
                    tableHtml += `
                        <tr style="background-color: #f8f9fa;">
                            <td colspan="2" style="font-weight: bold; padding-left: 20px;">${cat}</td>
                            <td style="text-align: center; font-weight: bold;">${total.toFixed(2)}</td>
                            <td></td>
                        </tr>`;
                });

                // Add grand total
                tableHtml += `
                    <tr style="border-top: 3px solid #333; background-color: #e9ecef;">
                        <td colspan="2" style="font-weight: bold; font-size: 1.1em;">GRAND TOTAL</td>
                        <td style="text-align: center; font-weight: bold; font-size: 1.2em;">${grandTotal.toFixed(2)}</td>
                        <td></td>
                    </tr>`;

                tableHtml += `
                            </tbody>
                        </table>
                        <style>
                            .cdt-admin-table { width: 100%; border-collapse: collapse; margin: 20px 0; font-size: 0.95em; }
                            .cdt-admin-table th, .cdt-admin-table td { padding: 8px 12px; border: 1px solid #ddd; }
                            .cdt-admin-table th { background-color: #f1f3f4; font-weight: 600; }
                            .cdt-admin-table tr:nth-child(even) { background-color: #fafafa; }
                            .cdt-admin-breakdown { margin-top: 30px; }
                        </style>
                    </div>`;

                return tableHtml;
            };

            adminBreakdownHtml = createAdminBreakdownTable();
        }

        let resultsHtml = `
            <div id="cdt-results-content">
                ${headerHtml}
                ${overviewHtml}
                ${dimensionsHtml}
                ${predictionHtml}
                ${nextStepsHtml}
                ${adminBreakdownHtml}
            </div>
            <div id="cdt-results-actions" class="cdt-results-actions"></div>`;
        container.innerHTML = resultsHtml;

        // --- Add Button Event Listeners ---
        const actionsContainer = $id('cdt-results-actions');
        const createActionButton = (text, classes, onClick) => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = classes;
            btn.innerHTML = text; // Use innerHTML to support icons
            btn.addEventListener('click', onClick);
            return btn;
        };

        function getPdfHtml() {
            // Clone the results content to modify it for the PDF without affecting the screen.
            const resultsClone = $id('cdt-results-content').cloneNode(true);

            // Make logo bigger for PDF
            const logoInClone = resultsClone.querySelector('.site-logo');
            if (logoInClone) {
                logoInClone.style.height = '60px';
                logoInClone.style.width = 'auto';
            }

            // Remove the "Next Steps" section from the clone.
            const nextStepsSection = resultsClone.querySelector('.cdt-next-steps-section');
            if (nextStepsSection) {
                nextStepsSection.remove();
            }
            const emojiRegex = /[\u{2696}\u{1F9E9}\u{1F4D4}\u{1F504}\u{1F5D1}]\u{FE0F}?/gu;
            return resultsClone.innerHTML.replace(emojiRegex, '').trim();
        }

        if (isLoggedIn) {
            saveResultsToServer(getPdfHtml());

            const downloadBtn = createActionButton('⬇️ Download PDF', 'cdt-quiz-button cdt-quiz-button-primary', (e) => {
                const btn = e.currentTarget;
                btn.textContent = 'Generating...';
                btn.disabled = true;

                const pdfHtml = getPdfHtml();

                const body = new URLSearchParams({ action: 'cdt_generate_pdf', _ajax_nonce: ajaxNonce, results_html: pdfHtml });
                fetch(ajaxUrl, { method: 'POST', body })
                    .then(response => response.ok ? response.blob() : Promise.reject('Network response was not ok.'))
                    .then(blob => {
                        const url = window.URL.createObjectURL(blob);
                        const a = document.createElement('a');
                        a.style.display = 'none';
                        a.href = url;
                        a.download = `cdt-quiz-results-${new Date().toISOString().slice(0, 10)}.pdf`;
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
                        btn.innerHTML = '⬇️ Download PDF';
                        btn.disabled = false;
                    });
            });
            actionsContainer.appendChild(downloadBtn);

            const retakeBtn = createActionButton('🔄 Retake Quiz', 'cdt-quiz-button cdt-quiz-button-secondary', () => {
                if (confirm('Are you sure? Your saved results will be overwritten when you complete the new quiz.')) {
                    renderQuiz();
                    window.scrollTo(0, 0);
                }
            });
            actionsContainer.appendChild(retakeBtn);

            const deleteBtn = createActionButton('🗑️ Delete Results', 'cdt-quiz-button cdt-quiz-button-danger', (e) => {
                if (!confirm('Are you sure you want to permanently delete your saved results? This cannot be undone.')) return;
                const btn = e.currentTarget;
                btn.textContent = 'Deleting...';
                btn.disabled = true;
                fetch(ajaxUrl, {
                    method: 'POST',
                    body: new URLSearchParams({ action: 'cdt_delete_user_results', _ajax_nonce: ajaxNonce })
                })
                    .then(r => {
                        if (!r.ok) {
                            // If it's a 403, it's almost certainly a nonce issue.
                            if (r.status === 403) {
                                alert('Your security session has expired. Please refresh the page and try again.');
                            } else {
                                alert(`An unexpected server error occurred (Status: ${r.status}). Please try again later.`);
                            }
                            throw new Error(`Network response was not ok, status: ${r.status}`);
                        }
                        return r.json();
                    })
                    .then(j => {
                        if (j.success) {
                            currentUser.savedResults = null;
                            quizState = { scores: {}, sortedScores: [], ageGroup: 'adult' };
                            alert('Your results have been deleted.');
                            renderAgeGate();
                            window.scrollTo(0, 0);
                        } else {
                            alert('Error: ' + (j.data || 'Could not delete results.'));
                            btn.innerHTML = '🗑️ Delete Results';
                            btn.disabled = false;
                        }
                    })
            });
            actionsContainer.appendChild(deleteBtn);
        }
    }

    function renderEmployeeComplete() {
        try {
            const stageEl = document.getElementById('cdt-stage');
            if (stageEl) stageEl.style.display = 'none';
            const toolbar = document.getElementById('cdt-toolbar');
            if (toolbar) toolbar.style.display = 'none';
            const containerEl = document.getElementById('cdt-quiz-container');
            if (containerEl) containerEl.style.display = 'block';
        } catch (e) { }
        if (devTools) devTools.style.display = 'none';

        // Auto-redirect to next assessment if available
        if (nextStepUrl) {
            container.innerHTML = `
                <div class="cdt-quiz-card" style="text-align:center; padding: 3em 2em;">
                    <p style="font-size:1.1em; color:#4a5568;">Responses saved. Loading next assessment...</p>
                </div>`;
            window.location.href = nextStepUrl;
            return;
        }

        // Fallback if no next URL
        container.innerHTML = `
            <div class="cdt-quiz-card" style="text-align:center; padding: 3em 2em;">
                <h2 class="cdt-section-title">Assessment Complete</h2>
                <p style="font-size:1.1em; color:#4a5568; margin:1em 0;">Thank you for completing this assessment! Your responses have been recorded.</p>
            </div>`;
        window.scrollTo(0, 0);
    }

    function saveResultsToServer(resultsHtml = '') {
        if (!isLoggedIn) return;

        // Strip emojis for PDF generation
        const emojiRegex = /[\u{2696}\u{1F9E9}\u{1F4D4}\u{1F504}\u{1F5D1}]\u{FE0F}?/gu;
        const pdfHtml = resultsHtml.replace(emojiRegex, '').trim();

        const bodyParams = {
            action: 'cdt_save_user_results',
            _ajax_nonce: ajaxNonce,
            user_id: currentUser.id,
            results: JSON.stringify(quizState)
        };

        if (resultsHtml) {
            bodyParams.results_html = pdfHtml;
        }

        fetch(ajaxUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams(bodyParams)
        });
    }

    // --- Initial Load ---


    // --- Initial Load ---
    function init() {
        try {
            if (autoBtn) {
                autoBtn.addEventListener('click', autoFill);
            }

            const autoAvg = document.getElementById('cdt-autofill-avg');
            const autoNeg = document.getElementById('cdt-autofill-neg');
            const autoExc = document.getElementById('cdt-autofill-exc');

            if (autoAvg) autoAvg.addEventListener('click', () => generateAndSaveAll('average'));
            if (autoNeg) autoNeg.addEventListener('click', () => generateAndSaveAll('negative'));
            if (autoExc) autoExc.addEventListener('click', () => generateAndSaveAll('excellent'));

            // Relaxed completion check: allow if scores exist, even if sortedScores is missing
            if (isLoggedIn && currentUser.savedResults && (currentUser.savedResults.sortedScores || currentUser.savedResults.scores)) {
                quizState = currentUser.savedResults;
                // Regenerate sortedScores if missing
                if (!quizState.sortedScores && quizState.scores) {
                    quizState.sortedScores = Object.entries(quizState.scores)
                        .filter(([k]) => !k.startsWith('si-'))
                        .sort((a, b) => b[1] - a[1]);
                }
                // Regenerate scores if missing (Fix for existing users with missing scores)
                if (!quizState.scores && quizState.sortedScores) {
                    quizState.scores = {};
                    quizState.sortedScores.forEach(item => {
                        if (Array.isArray(item) && item.length >= 2) {
                            quizState.scores[item[0]] = item[1];
                        }
                    });
                }

                // Force hide stage immediately to prevent flash or stuck screen
                const stageEl = document.getElementById('cdt-stage');
                if (stageEl) stageEl.style.display = 'none';

                if (isEmployee) {
                    renderEmployeeComplete();
                } else {
                    renderResults();
                }
            } else {
                // Wire up staging Start button if present
                var stageBtn = document.getElementById('cdt-start-btn');
                var stage = document.getElementById('cdt-stage');
                var toolbar = document.getElementById('cdt-toolbar');
                var aboutBtn = document.getElementById('cdt-about-btn');
                var aboutBtnTop = document.getElementById('cdt-about-top');
                var aboutModal = document.getElementById('cdt-about-modal');
                function toggleAbout() {
                    if (!aboutModal) return false;
                    var cont = document.getElementById('cdt-quiz-container');
                    var tool = document.getElementById('cdt-toolbar');
                    var stageEl = document.getElementById('cdt-stage');
                    var show = (aboutModal.style.display === 'none' || !aboutModal.style.display);

                    if (window.console) console.log('CDT About Toggle', { show, modalDisplay: aboutModal.style.display });

                    if (show) {
                        if (cont) { aboutModal.dataset.prevCont = cont.style.display || ''; cont.style.display = 'none'; }
                        if (tool) { aboutModal.dataset.prevTool = tool.style.display || ''; tool.style.display = 'none'; }
                        if (stageEl) { aboutModal.dataset.prevStage = stageEl.style.display || ''; stageEl.style.display = 'none'; }
                        aboutModal.style.display = 'block';
                        try { aboutModal.scrollIntoView({ behavior: 'smooth', block: 'start' }); } catch (e) { }

                        // Add click outside listener
                        setTimeout(() => {
                            document.addEventListener('click', closeAboutOnClickOutside);
                        }, 10);
                    } else {
                        aboutModal.style.display = 'none';
                        if (cont && ('prevCont' in aboutModal.dataset)) cont.style.display = aboutModal.dataset.prevCont;
                        if (tool && ('prevTool' in aboutModal.dataset)) tool.style.display = aboutModal.dataset.prevTool;
                        if (stageEl && ('prevStage' in aboutModal.dataset)) stageEl.style.display = aboutModal.dataset.prevStage || 'block';

                        // Remove listener
                        document.removeEventListener('click', closeAboutOnClickOutside);
                    }
                    return false;
                }

                function closeAboutOnClickOutside(e) {
                    if (!aboutModal || aboutModal.style.display === 'none') return;
                    // Check if click is inside modal or on one of the toggle buttons
                    if (aboutModal.contains(e.target)) return;
                    if (aboutBtn && aboutBtn.contains(e.target)) return;
                    if (aboutBtnTop && aboutBtnTop.contains(e.target)) return;

                    toggleAbout();
                }
                if (aboutBtn && !aboutBtn.getAttribute('data-cdt-about-bound')) {
                    aboutBtn.addEventListener('click', function (e) { e.preventDefault(); if (window.console) console.log('CDT About top-bar button click'); toggleAbout(); });
                    aboutBtn.setAttribute('data-cdt-about-bound', '1');
                }
                if (aboutBtnTop) {
                    aboutBtnTop.removeAttribute('onclick');
                    if (!aboutBtnTop.getAttribute('data-cdt-about-bound')) {
                        aboutBtnTop.addEventListener('click', function (e) { e.preventDefault(); if (window.console) console.log('CDT About inline button click'); toggleAbout(); });
                        aboutBtnTop.setAttribute('data-cdt-about-bound', '1');
                    }
                }
                window._cdtAboutToggle = function (e) { if (e && e.preventDefault) e.preventDefault(); return toggleAbout(); };
                // Start CTA inside About
                document.addEventListener('click', function (ev) {
                    var t = ev.target;
                    if (t && t.id === 'cdt-about-start-btn') {
                        ev.preventDefault();
                        if (aboutModal) aboutModal.style.display = 'none';
                        var start = document.getElementById('cdt-start-btn');
                        if (start) start.click();
                    }
                });
                if (stageBtn && stage) {
                    stageBtn.addEventListener('click', function () {
                        if (toolbar) toolbar.style.display = 'block';
                        if (stage) stage.style.display = 'none';
                        startQuizDirectly();
                    });
                } else {
                    startQuizDirectly();
                }
            }
        } catch (e) {
            console.error("An error occurred during CDT quiz initialization:", e);
            if (container) {
                container.innerHTML = `<div class="cdt-quiz-card"><p style="color:red;"><strong>Error:</strong> The quiz could not be loaded. Please check the browser console for details.</p></div>`;
            }
        }
    }

    function startQuizDirectly() {
        // Hide stage if visible (Fix for missing start button issue)
        const stage = document.getElementById('cdt-stage');
        if (stage) stage.style.display = 'none';
        // Initialize quizState if empty
        if (!quizState || Object.keys(quizState).length === 0) {
            quizState = { scores: {}, sortedScores: [] };
        }
        // All users default to adult now
        quizState.ageGroup = 'adult';
        // Persist locally just in case
        try { localStorage.setItem('mc_age_group', 'adult'); } catch (e) { }

        const container = document.getElementById('cdt-quiz-container');
        if (container) container.style.display = 'block';

        renderQuiz();
    }

    init();
})();
