console.log(">>> MI QUIZ FILE LOADED <<<");
(function () {
  console.log('MI Quiz JS loaded - Version 9.8.2');
  const { currentUser, ajaxUrl, ajaxNonce, loginUrl, data, cdtQuizUrl, ageGroup, ageNonce } = miq_quiz_data;
  const { cats: CATS, q1: Q1, q2: Q2, career: CAREER, lev: LEV, grow: GROW, skills: SKILLS, pairs: PAIRS, likert: LIKERT, cdtPrompts } = data;
  const isLoggedIn = !!currentUser;

  const $id = (s) => document.getElementById(s);
  const ageGate = $id('mi-age-gate'), form1 = $id('mi-quiz-form-part1'), form2 = $id('mi-quiz-form-part2'),
    inter = $id('mi-quiz-intermission'), resultsDiv = $id('mi-quiz-results'),
    devTools = $id('mi-dev-tools'), autoBtn = $id('mi-autofill-run');

  let age = 'adult', top3 = [], detailed = {}, top5 = [], bottom3 = [], part1Scores = {};

  function shuffle(a) { for (let i = a.length - 1; i > 0; i--) { const j = Math.floor(Math.random() * (i + 1));[a[i], a[j]] = [a[j], a[i]]; } return a; }

  function build(form, items, onDone) {
    if (!items.length) { alert('No questions available.'); return; }
    let h = `
      <div class="mi-progress-wrapper">
        <div class="mi-progress-container">
          <div class="mi-progress-bar"></div>
        </div><div class="mi-progress-counter"></div></div>
      <div class="mi-steps-container">`;
    items.forEach((q, i) => {
      const qid = form.id + '_q_' + i;
      let opts = ''; for (let v = 1; v <= 5; v++) {
        opts += `<label class="mi-quiz-option-wrapper"><input type="radio" name="${qid}" value="${v}" required><span class="likert-num">${v}</span><span class="likert-text">${LIKERT[v]}</span></label>`;
      }
      h += `<div class="mi-step" data-step="${i}" data-cat="${q.cat || ''}" data-subcat="${q.subcat || ''}" style="display:none;">
              <div class="mi-quiz-card">
                <p class="mi-quiz-question-text">${q.text || ''}</p>
                <div class="mi-quiz-likert-options">${opts}</div>
              </div>
            </div>`;
    });
    h += `</div><div class="mi-quiz-footer"><button type="button" class="mi-prev mi-quiz-button" disabled>Previous</button>
          <button type="button" class="mi-submit-final mi-quiz-button" style="display:none;">Next</button></div>`;
    form.innerHTML = h;

    const steps = form.querySelectorAll('.mi-step'), prev = form.querySelector('.mi-prev'),
      next = form.querySelector('.mi-submit-final'), bar = form.querySelector('.mi-progress-bar'),
      ctr = form.querySelector('.mi-progress-counter');
    let i = 0, n = steps.length;
    const show = (k) => {
      steps.forEach((s, j) => s.style.display = j === k ? 'block' : 'none');
      prev.disabled = (k === 0); if (next) next.style.display = (k === n - 1) ? 'inline-block' : 'none';
      bar.style.width = ((k + 1) / n * 100) + '%'; ctr.textContent = (n - k) + ' questions remaining';
    };
    prev.addEventListener('click', () => { if (i > 0) { i--; show(i); } });
    steps.forEach((s, k) => s.querySelectorAll('input[type=radio]').forEach(inp => inp.addEventListener('change', () => { setTimeout(() => { if (k < n - 1) { i = k + 1; show(i); } }, 120); })));
    next && next.addEventListener('click', () => onDone(steps));
    show(0);
  }

  function part1Items() {
    const src = Q1[age] || Q1['adult'] || {};
    const out = []; Object.entries(src).forEach(([slug, arr]) => Array.isArray(arr) && arr.forEach(text => out.push({ cat: slug, text })));
    return shuffle(out);
  }
  function part2Items() {
    const src = Q2[age] || Q2['adult'] || {};
    const out = [];
    top3.forEach(sl => {
      const groups = src[sl] || {};
      Object.entries(groups).forEach(([sub, arr]) => Array.isArray(arr) && arr.forEach(text => out.push({ cat: sl, subcat: sub, text })));
    });
    return shuffle(out);
  }

  function autoFill(form, both = false) {
    const steps = form.querySelectorAll('.mi-step'), submit = form.querySelector('.mi-submit-final');

    // --- New logic for more random results ---
    let biasMap = {};
    if (form === form1) {
      const categories = shuffle(Object.keys(CATS));
      // Create a clear separation of scores to ensure random top/bottom 3
      categories.forEach((cat, index) => {
        if (index < 3) {
          biasMap[cat] = 'high'; // These will be the top 3
        } else if (index < 6) {
          biasMap[cat] = 'low'; // These will be in the bottom
        } else {
          biasMap[cat] = 'mid'; // The rest are in the middle
        }
      });
    }
    // --- End new logic ---

    let k = 0; (function tick() {
      if (k >= steps.length) { submit?.click(); if (both && form === form1) { setTimeout(() => { $id('mi-start-part2')?.click(); setTimeout(() => autoFill(form2, false), 200); }, 200); } return; }

      let pick;
      const step = steps[k];
      const category = step.dataset.cat;

      if (form === form1 && biasMap[category]) {
        const bias = biasMap[category];
        if (bias === 'high') { pick = Math.floor(Math.random() * 2) + 4; } // 4 or 5
        else if (bias === 'low') { pick = Math.floor(Math.random() * 2) + 1; } // 1 or 2
        else { pick = Math.floor(Math.random() * 2) + 3; } // 3 or 4
      } else {
        // Original random logic for part 2 or if something goes wrong
        pick = Math.floor(Math.random() * 5) + 1;
      }

      const radio = step.querySelector('input[value="' + pick + '"]');
      if (radio) { radio.checked = true; radio.dispatchEvent(new Event('change', { bubbles: true })); }
      k++; setTimeout(tick, 16);
    })();
  }
  autoBtn && autoBtn.addEventListener('click', () => autoFill(form1, true));

  // --- Comprehensive Auto-Fill Logic ---
  const autoAvg = $id('mi-autofill-avg');
  const autoNeg = $id('mi-autofill-neg');
  const autoExc = $id('mi-autofill-exc');

  function generateAndSaveAll(profile) {
    if (!confirm(`Are you sure you want to overwrite ALL quiz results (MI, CDT, Bartle) with ${profile} data? This cannot be undone.`)) return;

    const btn = profile === 'average' ? autoAvg : (profile === 'negative' ? autoNeg : autoExc);
    const originalText = btn.textContent;
    btn.textContent = 'Processing...';
    btn.disabled = true;

    // Score mappings (Avg=3, Neg=5 [High Strain], Exc=1 [Low Strain])
    const baseScore = profile === 'average' ? 3 : (profile === 'negative' ? 5 : 1);

    // Helper to get score based on question count
    const getScore = (count) => count * baseScore;

    // --- 1. MI Data Generation ---
    const miScores = {};
    // Fill standard categories with random valid scores (5-15)
    Object.keys(CATS).forEach(k => miScores[k] = Math.floor(Math.random() * 10) + 5);

    // Overwrite Strain Index categories
    // MI: Rumination (4), Avoidance (3), Flood (3)
    miScores['si-rumination'] = getScore(4);
    miScores['si-avoidance'] = getScore(3);
    miScores['si-emotional-flood'] = getScore(3);

    const miResults = {
      detailed: {}, // Simplified
      top5: [],
      bottom3: [],
      top3: Object.keys(CATS).filter(k => !k.startsWith('si-')).slice(0, 3),
      age: 'adult',
      part1Scores: miScores
    };

    // --- 2. CDT Data Generation ---
    const cdtScores = {
      // CDT: Rumination (3), Avoidance (4), Flood (3)
      'si-rumination': getScore(3),
      'si-avoidance': getScore(4),
      'si-emotional-flood': getScore(3),
      // Dummy other scores
      'binary': 15, 'absolute': 15, 'relative': 15
    };
    const cdtResults = {
      scores: cdtScores,
      sortedScores: [['binary', 15], ['absolute', 15], ['relative', 15]],
      ageGroup: 'adult'
    };

    // --- 3. Bartle Data Generation ---
    const bartleScores = {
      // Bartle: Rumination (4), Avoidance (3), Flood (3)
      'si-rumination': getScore(4),
      'si-avoidance': getScore(3),
      'si-emotional-flood': getScore(3),
      // Dummy other scores
      'achiever': 15, 'explorer': 15, 'socializer': 15, 'killer': 15
    };
    const bartleResults = {
      scores: bartleScores,
      sortedScores: [['achiever', 15], ['explorer', 15], ['socializer', 15], ['killer', 15]],
      ageGroup: 'adult'
    };

    // --- Save All (Sequential to ensure completion check works) ---
    fetch(ajaxUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({ action: 'miq_save_user_results', _ajax_nonce: ajaxNonce, user_id: currentUser.id, results: JSON.stringify(miResults) })
    })
      .then(r => r.json())
      .then(d1 => {
        console.log('MI Saved:', d1);
        return fetch(ajaxUrl, {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: new URLSearchParams({ action: 'cdt_save_user_results', _ajax_nonce: ajaxNonce, user_id: currentUser.id, results: JSON.stringify(cdtResults) })
        });
      })
      .then(r => r.json())
      .then(d2 => {
        console.log('CDT Saved:', d2);
        return fetch(ajaxUrl, {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: new URLSearchParams({ action: 'bartle_save_user_results', _ajax_nonce: ajaxNonce, user_id: currentUser.id, results: JSON.stringify(bartleResults) })
        });
      })
      .then(r => r.json())
      .then(d3 => {
        console.log('Bartle Saved:', d3);
        alert('All three quizzes have been completed with ' + profile + ' data! The page will now reload.');
        window.location.reload();
      })
      .catch(err => {
        console.error('Error saving quizzes:', err);
        alert('An error occurred while saving results. Check console for details.');
        btn.textContent = originalText;
        btn.disabled = false;
      });
  }

  if (autoAvg) autoAvg.addEventListener('click', () => generateAndSaveAll('average'));
  if (autoNeg) autoNeg.addEventListener('click', () => generateAndSaveAll('negative'));
  if (autoExc) autoExc.addEventListener('click', () => generateAndSaveAll('excellent'));

  // Use a predictable key for localStorage to hold results for a user who needs to log in.
  const PENDING_RESULTS_KEY = 'miq_pending_results';
  function storeQuizResults(results) {
    localStorage.setItem(PENDING_RESULTS_KEY, JSON.stringify(results));
  }
  function getStoredQuizResults() {
    const storedData = localStorage.getItem(PENDING_RESULTS_KEY);
    try { return storedData ? JSON.parse(storedData) : null; } catch (e) { return null; }
  }
  function clearStoredQuizResults() {
    localStorage.removeItem(PENDING_RESULTS_KEY);
  }

  function showLoginRegister(emailHtml, resultsData) {
    resultsDiv.innerHTML = `
          <div class="mi-results-section bg-secondary">
              <h2 class="mi-section-title">Get Your Full Results & Action Plan</h2>
              <p>Enter your name and email to create your free account. We'll instantly email you a copy of your results and show them on the next screen.</p>
              <form id="mi-magic-register-form">
                  <div class="mi-form-field">
                      <label for="mi_reg_first_name">First Name</label>
                      <input type="text" id="mi_reg_first_name" required>
                  </div>
                  <div class="mi-form-field">
                      <label for="mi_reg_email">Email Address</label>
                      <input type="email" id="mi_reg_email" required>
                  </div>
                  <div class="form-submit-wrapper">
                      <button type="button" id="mi_magic_register_btn" class="mi-quiz-button">Email My Results & Create Account</button>
                  </div>
                  <p id="mi_reg_status" class="form-status"></p>
              </form>
              <p class="form-secondary-action">Already have an account? <a href="${loginUrl}">Log in here</a>.</p>
          </div>`;
    form2.style.display = 'none';
    resultsDiv.style.display = 'block';

    const regBtn = $id('mi_magic_register_btn');
    const statusEl = $id('mi_reg_status');
    $id('mi-magic-register-form').addEventListener('submit', e => e.preventDefault());
    regBtn.addEventListener('click', () => {
      const email = $id('mi_reg_email').value.trim();
      const firstName = $id('mi_reg_first_name').value.trim();

      if (!firstName) { statusEl.innerHTML = 'Please enter your first name.'; statusEl.style.color = 'red'; return; }
      if (!email || !/\S+@\S+\.\S+/.test(email)) { statusEl.innerHTML = 'Please enter a valid email address.'; statusEl.style.color = 'red'; return; }

      statusEl.innerHTML = 'Creating your account...';
      statusEl.style.color = 'inherit';
      regBtn.disabled = true;

      const body = new URLSearchParams({
        action: 'miq_magic_register',
        _ajax_nonce: ajaxNonce,
        email: email,
        first_name: firstName,
        results_html: emailHtml,
        results_data: JSON.stringify(resultsData) // Send the raw data
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

  function getUrlAge() {
    try { const p = new URLSearchParams(window.location.search); return p.get('age'); } catch (e) { return null; }
  }
  function startPart1(initialAge) {
    console.log('MI Quiz: startPart1 called');
    age = 'adult';
    try { localStorage.setItem('mc_age_group', age); } catch (e) { }

    // Ensure container is visible and stage is hidden
    const stage = $id('mi-stage');
    const container = $id('mi-quiz-container');

    console.log('MI Quiz: Toggling visibility in startPart1', { stage: stage ? 'found' : 'missing', container: container ? 'found' : 'missing' });

    if (stage) stage.style.display = 'none';
    if (container) {
      container.style.display = 'block';
      console.log('MI Quiz: Container display set to block');
    }

    if (ageGate) ageGate.style.display = 'none';
    if (devTools) devTools.style.display = 'block';

    if (form1) form1.style.display = 'block';
    if (resultsDiv) resultsDiv.style.display = 'none';



    build(form1, part1Items(), steps => {
      const scores = {}; Object.keys(CATS).forEach(k => scores[k] = 0);
      steps.forEach(s => {
        const cat = s.getAttribute('data-cat'), v = s.querySelector('input[type=radio]:checked')?.value;
        if (cat && v) scores[cat] = (scores[cat] || 0) + parseInt(v, 10);
      });
      part1Scores = scores;
      top3 = Object.entries(scores)
        .filter(([k]) => !k.startsWith('si-')) // Exclude Strain Index categories
        .sort((a, b) => b[1] - a[1])
        .slice(0, 3)
        .map(([k]) => k);
      $id('mi-top3-list').innerHTML = top3.map(sl => `<li>${CATS[sl] || sl}</li>`).join('');
      form1.style.display = 'none'; devTools && (devTools.style.display = 'none'); inter.style.display = 'block';
    });

    const sbtn = form1.querySelector('.mi-submit-final');
    if (sbtn) { sbtn.id = 'mi-submit-part1'; sbtn.textContent = 'Submit Part 1'; }
  }

  // Keep age buttons functional if present, but we no longer require them
  if (ageGate) {
    ageGate.querySelectorAll('.mi-quiz-button').forEach(btn => {
      btn.addEventListener('click', e => startPart1(e.currentTarget.dataset.ageGroup || 'adult'));
    });
  }

  // Start Part 2
  $id('mi-start-part2').addEventListener('click', () => {
    inter.style.display = 'none';
    const items = part2Items();
    if (!items.length) { showResults([]); return; }
    devTools && (devTools.style.display = 'block'); form2.style.display = 'block';
    build(form2, items, showResults);
    const sbtn = form2.querySelector('.mi-submit-final'); if (sbtn) sbtn.textContent = 'Show My Results';
    window.scrollTo(0, 0)
  });

  const bar = (score, max = 15, slug = 'default') => {
    const pct = Math.max(0, Math.min(100, (score / max) * 100));
    let slugClass = '';
    if (slug) {
      slugClass = 'bar-' + slug.replace('bodily-', '').split('-')[0];
    }
    return `<div class="bar-wrapper"><div class="bar-inner ${slugClass}" style="width:${pct}%;"></div></div>`;
  };

  function fallbackGrow(slug, sub) {
    const parent = CATS[slug] || slug;
    return [
      `Practice ${sub.toLowerCase()} in short, daily reps (5–10 minutes).`,
      `Pair with someone strong in ${parent} for feedback on ${sub.toLowerCase()}.`,
      `Pick one tiny weekly challenge to stretch your ${sub.toLowerCase()} and track it.`
    ];
  }

  // Helper function to create skills key from top 3 intelligences
  function createSkillsKey(intelligences) {
    if (!intelligences || intelligences.length < 3) return null;

    // Map the slugs to the proper names for skills key
    const nameMap = {
      'logical-mathematical': 'Logical-Mathematical',
      'linguistic': 'Linguistic',
      'spatial': 'Visual-Spatial',
      'bodily-kinesthetic': 'Bodily-Kinesthetic',
      'musical': 'Musical',
      'interpersonal': 'Interpersonal',
      'intrapersonal': 'Intrapersonal',
      'naturalistic': 'Naturalistic'
    };

    // Sort the intelligences alphabetically to match the skills data structure
    const sortedNames = intelligences.map(slug => nameMap[slug] || slug).sort();
    return sortedNames.join('+');
  }

  // Helper functions for triad and pair scenarios
  function getTriadScenario(intelligence1, intelligence2, intelligence3) {
    // For now, triads are not implemented in the data structure
    // We could synthesize from the existing skills data if needed
    return null;
  }

  function getPairScenario(intelligence1, intelligence2) {
    if (!PAIRS) return null;

    // Convert slugs to capitalized names for the pair key
    const nameMap = {
      'logical-mathematical': 'Logical-Mathematical',
      'linguistic': 'Linguistic',
      'spatial': 'Visual-Spatial',
      'bodily-kinesthetic': 'Bodily-Kinesthetic',
      'musical': 'Musical',
      'interpersonal': 'Interpersonal',
      'intrapersonal': 'Intrapersonal',
      'naturalistic': 'Naturalistic'
    };

    const name1 = nameMap[intelligence1] || intelligence1;
    const name2 = nameMap[intelligence2] || intelligence2;
    const pairKey = [name1, name2].sort().join('+');

    const statements = PAIRS[pairKey];
    return statements ? { statements } : null;
  }

  function getRandomStatements(statements, count = 2) {
    if (!statements || !Array.isArray(statements) || statements.length === 0) return [];
    const shuffled = [...statements].sort(() => Math.random() - 0.5);
    return shuffled.slice(0, Math.min(count, statements.length));
  }

  function calculatePercentile(score, maxScore) {
    // Simple percentile calculation - could be enhanced with actual distribution data
    const percentage = (score / maxScore) * 100;
    if (percentage >= 80) return 90;
    if (percentage >= 70) return 75;
    if (percentage >= 60) return 60;
    if (percentage >= 50) return 50;
    if (percentage >= 40) return 35;
    if (percentage >= 30) return 25;
    if (percentage >= 20) return 15;
    return 10;
  }

  function showResults(p2Steps) {
    detailed = {};
    const src = Q2[age] || Q2['adult'] || {};
    top3.forEach(sl => { detailed[sl] = {}; Object.keys(src[sl] || {}).forEach(sub => detailed[sl][sub] = 0); });
    (p2Steps || []).forEach(s => {
      const cat = s.getAttribute('data-cat'), sub = s.getAttribute('data-subcat');
      const v = s.querySelector('input[type=radio]:checked')?.value;
      if (v && detailed[cat] && sub in detailed[cat]) detailed[cat][sub] += parseInt(v, 10);
    });

    const subs = [];
    Object.entries(detailed).forEach(([slug, obj]) => {
      const parent = CATS[slug] || slug; Object.entries(obj).forEach(([name, score]) => subs.push({ name, score, parent, slug }));
    });
    subs.sort((a, b) => b.score - a.score);
    top5 = subs.slice(0, 5); bottom3 = subs.slice(-3).reverse();

    const resultsData = { detailed, top5, bottom3, top3, age, part1Scores };

    if (!isLoggedIn) {
      const emailHtml = generateResultsHtml();
      showLoginRegister(emailHtml, resultsData);
      return;
    }

    // For logged-in users, save and email immediately.
    // 1. Save to DB and update Next Step
    const saveBody = new URLSearchParams({ action: 'miq_save_user_results', _ajax_nonce: ajaxNonce, user_id: currentUser.id, results: JSON.stringify(resultsData) });
    fetch(ajaxUrl, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: saveBody })
      .then(r => r.json())
      .then(j => {
        if (j.success && j.data && j.data.nextStepUrl) {
          // Update global data
          if (!window.miq_quiz_data) window.miq_quiz_data = {};
          window.miq_quiz_data.nextStepUrl = j.data.nextStepUrl;
          window.miq_quiz_data.nextStepTitle = j.data.nextStepTitle;

          // Update UI if results are visible
          const resultsDiv = document.getElementById('mi-quiz-results');
          if (resultsDiv && resultsDiv.style.display !== 'none') {
            const nextBtn = resultsDiv.querySelector('.mi-quiz-button-next-step');
            if (nextBtn) {
              // Button exists, update it
              nextBtn.href = j.data.nextStepUrl;
              nextBtn.textContent = j.data.nextStepTitle || 'Continue';
            } else {
              // Button doesn't exist, inject the section
              const nextHtml = `
                            <div class="mi-results-section mi-next-steps-section">
                                <h2 class="mi-section-title">Your Next Step</h2>
                                <div style="text-align: center; margin-top: 1em;">
                                    <a href="${j.data.nextStepUrl}" class="mi-quiz-button mi-quiz-button-primary mi-quiz-button-next-step">${j.data.nextStepTitle || 'Continue'}</a>
                                </div>
                            </div>`;
              resultsDiv.querySelector('#mi-results-content').insertAdjacentHTML('beforeend', nextHtml);
            }
          }
        }
      })
      .catch(err => console.error('Error saving results:', err));

    // 2. Email results
    const emailHtml = generateResultsHtml();
    const emailBody = new URLSearchParams({ action: 'miq_email_results', _ajax_nonce: ajaxNonce, email: currentUser.email, first_name: currentUser.firstName || 'Quiz Taker', last_name: currentUser.lastName || '', results_html: emailHtml });
    fetch(ajaxUrl, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: emailBody });

    // 3. Render the results on screen
    renderResults();
  }

  function generateResultsHtml() {
    const userFirstName = currentUser ? currentUser.firstName : 'Valued User';
    const titleHtml = `
      <div class="results-main-header">
          <div class="site-branding">
            <span class="site-title">What You're Good At</span>
        </div>
      </div>
      <div class="mi-results-header">
        <h1>Your Multiple Intelligences Results</h1>
        <h2>Results for ${userFirstName}</h2>
        <p class="mi-results-metadata">Generated on: ${new Date().toLocaleDateString()}</p>
      </div>
    `;

    const names = top3.map(sl => CATS[sl] || sl);
    const summary = names.length >= 3 ? `Your detailed profile highlights a powerful combination of ${names[0]}, ${names[1]}, and ${names[2]}.` : 'Here is your detailed profile.';

    let top5Html = `<div class="mi-results-section bg-secondary">
      <h2 class="mi-section-title">✅ Your Top 5 Strengths</h2>
      <div class="mi-strengths-grid">`;
    top5.forEach(it => {
      const tips = (LEV?.[age]?.[it.slug]?.[it.name] || []);
      top5Html += `<div class="mi-strength-card">
        <h4>${it.name}</h4>
        <p><em>Part of ${it.parent}</em></p>
        <strong class="leverage-title">To leverage this:</strong>
        <ul class="leverage-list">${tips.map(t => `<li>${t}</li>`).join('')}</ul>
      </div>`;
    });
    top5Html += `</div></div>`;

    // Generate amalgamated career/hobby suggestions
    let careerHtml = `<div class="mi-results-section">
      <h2 class="mi-section-title">🚀 Potential Applications</h2>
      <div class="mi-career-grid">`;
    top3.forEach(sl => {
      const s = CAREER?.[age]?.[sl]; if (!s) return;
      const slugClass = sl.replace('bodily-', '').split('-')[0];
      // Amalgamate careers and hobbies into one list
      const allSuggestions = [...(s.careers || []), ...(s.hobbies || [])];
      careerHtml += `<div class="mi-career-card">
        <div class="mi-career-card-header" style="background-color: var(--mi-color-${slugClass});">
          <h4>For your ${CATS[sl] || sl}</h4>
        </div>
        <div class="mi-career-card-body">
          <ul>${allSuggestions.map(c => `<li>${c}</li>`).join('')}</ul>
        </div>
      </div>`;
    });
    careerHtml += `</div></div>`;

    // Generate scenarios based on pair combinations
    let scenariosHtml = '';

    if (top3.length >= 3) {
      const [top1, top2, top3_intelligence] = top3;

      // Get the third intelligence score and calculate its percentile
      const thirdScore = part1Scores[top3_intelligence] || 0;
      const maxPossibleScore = 25; // Assuming 5 questions per intelligence with max score 5
      const thirdPercentile = calculatePercentile(thirdScore, maxPossibleScore);

      // Start with triad scenario using existing skills data as fallback
      const skillsKey = createSkillsKey(top3);
      const potentialSkills = SKILLS?.[age]?.[skillsKey];
      let hasTriadContent = false;

      if (potentialSkills && potentialSkills.length > 0) {
        // Use existing skills as triad scenario
        const selectedSkills = getRandomStatements(potentialSkills, 2);
        scenariosHtml += `<div class="mi-results-section bg-secondary">
          <h2 class="mi-section-title">✨ Your Unique Combination</h2>
          <p class="mi-scenarios-intro">Your powerful blend of ${names.join(', ')} creates unique possibilities:</p>
          <div class="mi-scenario-card triad-scenario">
            <h3 class="mi-scenario-title">Your Three-Way Strengths</h3>
            <div class="mi-scenario-statements">
              ${selectedSkills.map(skill => `<p class="mi-scenario-statement">• ${skill}</p>`).join('')}
            </div>
          </div>`;
        hasTriadContent = true;
      }

      // Conditional pair scenarios based on third score threshold
      const pairScenarios = [];

      if (thirdPercentile < 20) {
        // Third score is low - show supporting pairs, prioritizing top1+top2
        const top1Top2Pair = getPairScenario(top1, top2);
        if (top1Top2Pair) {
          pairScenarios.push({
            scenario: top1Top2Pair,
            intelligences: [top1, top2]
          });
        }

        // Optionally add one more pair if available
        const top1Top3Pair = getPairScenario(top1, top3_intelligence);
        const top2Top3Pair = getPairScenario(top2, top3_intelligence);

        if (top1Top3Pair && pairScenarios.length < 2) {
          pairScenarios.push({
            scenario: top1Top3Pair,
            intelligences: [top1, top3_intelligence]
          });
        } else if (top2Top3Pair && pairScenarios.length < 2) {
          pairScenarios.push({
            scenario: top2Top3Pair,
            intelligences: [top2, top3_intelligence]
          });
        }
      } else {
        // Third score is strong - show 1-2 pairs from all possible combinations
        const allPairs = [
          { scenario: getPairScenario(top1, top2), intelligences: [top1, top2] },
          { scenario: getPairScenario(top1, top3_intelligence), intelligences: [top1, top3_intelligence] },
          { scenario: getPairScenario(top2, top3_intelligence), intelligences: [top2, top3_intelligence] }
        ].filter(pair => pair.scenario); // Only include pairs that exist in our data

        // Randomly select 1-2 pairs
        const selectedPairs = allPairs.sort(() => Math.random() - 0.5).slice(0, Math.min(2, allPairs.length));
        pairScenarios.push(...selectedPairs);
      }

      // Render pair scenarios
      if (pairScenarios.length > 0) {
        if (!hasTriadContent) {
          scenariosHtml += `<div class="mi-results-section bg-secondary">
            <h2 class="mi-section-title">✨ Your Intelligence Combinations</h2>
            <p class="mi-scenarios-intro">Your intelligence combinations create these special strengths:</p>`;
        } else {
          scenariosHtml += `<div class="mi-pair-scenarios">
            <h3 class="mi-pairs-title">Supporting Pair Strengths</h3>`;
        }

        pairScenarios.forEach(({ scenario, intelligences }) => {
          const pairStatements = getRandomStatements(scenario.statements, Math.random() < 0.5 ? 1 : 2);
          const pairNames = intelligences.map(sl => CATS[sl] || sl);

          scenariosHtml += `<div class="mi-scenario-card pair-scenario">
            <h4 class="mi-scenario-subtitle">${pairNames.join(' + ')}</h4>
            <div class="mi-scenario-statements">
              ${pairStatements.map(statement => `<p class="mi-scenario-statement">• ${statement}</p>`).join('')}
            </div>
          </div>`;
        });

        scenariosHtml += `</div>`;
      }

      if (hasTriadContent || pairScenarios.length > 0) {
        scenariosHtml += `</div>`;
      }
    }

    // Fallback to traditional skills if no scenarios available
    if (!scenariosHtml) {
      const skillsKey = createSkillsKey(top3);
      const potentialSkills = SKILLS?.[age]?.[skillsKey];
      if (potentialSkills && potentialSkills.length > 0) {
        scenariosHtml = `<div class="mi-results-section bg-secondary">
          <h2 class="mi-section-title">✨ Your Unique Potential</h2>
          <p class="mi-skills-intro">Based on your unique combination of ${top3.map(sl => CATS[sl] || sl).join(', ')}, here are some things you might excel at:</p>
          <div class="mi-skills-grid">
            ${potentialSkills.map(skill => `<div class="mi-skill-card">${skill}</div>`).join('')}
          </div>
        </div>`;
      }
    }

    // Simplified detailed results - no accordions
    let detailedHtml = `<div class="mi-results-section">
      <h2 class="mi-section-title">📊 Your Detailed Intelligence Profile</h2>
      <p class="summary">${summary}</p>`;
    top3.forEach(sl => {
      const slugClass = sl.replace('bodily-', '').split('-')[0];
      detailedHtml += `<h3 class="mi-subsection-title" style="border-left-color: var(--mi-color-${slugClass});">${CATS[sl] || sl}</h3>
      <div class="mi-detailed-grid">`;
      Object.entries(detailed[sl] || {}).forEach(([sub, score]) => {
        detailedHtml += `<div class="detailed-score"><strong>${sub}:</strong> ${score} / 15 ${bar(score, 15, sl)}</div>`;
      });
      detailedHtml += `</div>`;
    });
    detailedHtml += `</div>`;

    return titleHtml + detailedHtml + top5Html + careerHtml + scenariosHtml;
  }

  function renderResults() {
    // Ensure any staging UI is hidden when showing results
    try {
      const stageEl = document.getElementById('mi-stage');
      if (stageEl) stageEl.style.display = 'none';
      const containerEl = document.getElementById('mi-quiz-container');
      if (containerEl) containerEl.style.display = 'block';
    } catch (e) { }

    resultsDiv.innerHTML = `
        <div id="mi-results-content">${generateResultsHtml()}</div>
        <div id="mi-results-actions" class="results-actions-container"></div>
    `;

    const actionsContainer = $id('mi-results-actions');

    // Helper to create buttons, reducing code repetition.
    function createActionButton(text, classes, onClick) {
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = classes;
      btn.textContent = text;
      btn.addEventListener('click', onClick);
      return btn;
    }

    // --- Create and Append Buttons ---

    // Download PDF Button
    const downloadBtn = createActionButton('⬇️ Download PDF', 'mi-quiz-button mi-quiz-button-primary', (e) => {
      const btn = e.currentTarget;
      btn.textContent = 'Generating...';
      btn.disabled = true;

      // Clone the results content to modify it for the PDF without affecting the screen.
      const resultsClone = $id('mi-results-content').cloneNode(true);
      const logoInClone = resultsClone.querySelector('.site-logo');
      if (logoInClone) {
        logoInClone.style.height = '60px';
        logoInClone.style.width = 'auto';
      }

      // Strip emojis for the PDF version to prevent rendering issues.
      const emojiRegex = /[\u{1F9E0}\u{2705}\u{1F680}\u{26A1}\u{1F4CA}\u{1F9ED}\u{2B07}\u{1F504}\u{1F5D1}]\u{FE0F}?/gu;
      const pdfHtml = resultsClone.innerHTML.replace(emojiRegex, '').trim();

      const body = new URLSearchParams({ action: 'miq_generate_pdf', _ajax_nonce: ajaxNonce, results_html: pdfHtml });
      fetch(ajaxUrl, { method: 'POST', body })
        .then(response => response.ok ? response.blob() : Promise.reject('Network response was not ok.'))
        .then(blob => {
          const url = window.URL.createObjectURL(blob);
          const a = document.createElement('a');
          a.style.display = 'none';
          a.href = url;
          a.download = `mi-quiz-results-${new Date().toISOString().slice(0, 10)}.pdf`;
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

    // Lab Mode is only available from the main dashboard after all assessments are complete
    // No Lab Mode button should appear on individual quiz results pages

    // --- Add Logged-In User Buttons ---
    if (isLoggedIn) {
      // Retake Quiz Button
      const retakeBtn = createActionButton('🔄 Retake Quiz', 'mi-quiz-button mi-quiz-button-secondary', () => {
        if (confirm('Are you sure? Your saved results will be overwritten when you complete the new quiz.')) {
          resultsDiv.style.display = 'none';
          resultsDiv.innerHTML = '';
          startPart1();
          window.scrollTo(0, 0);
        }
      });
      actionsContainer.appendChild(retakeBtn);

      // Delete Results Button
      const deleteBtn = createActionButton('🗑️ Delete My Results', 'mi-quiz-button mi-quiz-button-danger', (e) => {
        if (!confirm('Are you sure you want to permanently delete your saved results? This cannot be undone.')) return;

        const btn = e.currentTarget;
        btn.textContent = 'Deleting...';
        btn.disabled = true;

        const body = new URLSearchParams({ action: 'miq_delete_user_results', _ajax_nonce: ajaxNonce });
        fetch(ajaxUrl, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body })
          .then(r => r.json())
          .then(j => {
            if (j.success) {
              currentUser.savedResults = null;
              top3 = []; detailed = {}; top5 = []; bottom3 = [];
              alert('Your results have been deleted.');
              resultsDiv.style.display = 'none';
              resultsDiv.innerHTML = '';
              startPart1();
              window.scrollTo(0, 0);
            } else {
              alert('Error: ' + (j.data || 'Could not delete results.'));
              btn.textContent = '🗑️ Delete My Results';
              btn.disabled = false;
            }
          });
      });
      actionsContainer.appendChild(deleteBtn);
    }

    // --- Generic Next Step CTA (preferred) ---
    try {
      const nextUrl = (window.miq_quiz_data && window.miq_quiz_data.nextStepUrl) ? window.miq_quiz_data.nextStepUrl : '';
      const nextTitle = (window.miq_quiz_data && window.miq_quiz_data.nextStepTitle) ? window.miq_quiz_data.nextStepTitle : '';
      if (nextUrl) {
        const nextHtml = `
                <div class="mi-results-section mi-next-steps-section">
                    <h2 class="mi-section-title">Your Next Step</h2>
                    <div style="text-align: center; margin-top: 1em;">
                        <a href="${nextUrl}" class="mi-quiz-button mi-quiz-button-primary mi-quiz-button-next-step">${nextTitle || 'Continue'}</a>
                    </div>
                </div>`;
        resultsDiv.insertAdjacentHTML('beforeend', nextHtml);
      } else if (cdtPrompts && cdtQuizUrl && top3 && top3.length >= 3) {
        // Fallback: legacy CDT prompt based on MI triad
        const sortedTop3 = [...top3].sort();
        const promptKey = sortedTop3.join('_');
        const promptData = cdtPrompts[promptKey];
        if (promptData && promptData.prompt) {
          const cdtPromptHtml = `
                    <div class="mi-results-section cdt-prompt-section">
                        <h2 class="mi-section-title">🧭 Your Next Step: The Skill of Self-Discovery</h2>
                        <p>${promptData.prompt}</p>
                        <div style="text-align: center; margin-top: 1.5em;">
                            <a href="${cdtQuizUrl}" class="mi-quiz-button mi-quiz-button-primary mi-quiz-button-next-step">Take the CDT Quiz Now</a>
                        </div>
                    </div>`;
          resultsDiv.insertAdjacentHTML('beforeend', cdtPromptHtml);
        }
      }
    } catch (e) { }


    // --- Finalize Display ---
    devTools && (devTools.style.display = 'none');
    form2.style.display = 'none'; resultsDiv.style.display = 'block';
    window.scrollTo(0, 0);
  }

  // --- Initialization ---
  function init() {
    console.log('MI Quiz: Init started');
    console.log('MI Quiz Data:', typeof miq_quiz_data !== 'undefined' ? miq_quiz_data : 'undefined');

    if (typeof currentUser === 'undefined' || !currentUser) {
      console.log('MI Quiz: No currentUser found');
    } else {
      console.log('MI Quiz: CurrentUser:', currentUser);
    }

    if (autoBtn) {
      autoBtn.addEventListener('click', autoFill);
    }

    if (autoAvg) autoAvg.addEventListener('click', () => generateAndSaveAll('average'));
    if (autoNeg) autoNeg.addEventListener('click', () => generateAndSaveAll('negative'));
    if (autoExc) autoExc.addEventListener('click', () => generateAndSaveAll('excellent'));

    // On page load, check for any existing results to display
    if (currentUser) {
      // Force adult age if not set
      try {
        const localAge = (getUrlAge() || localStorage.getItem('mc_age_group'));
        if (localAge && (!ageGroup || ageGroup === 'adult')) {
          fetch(ajaxUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ action: 'mc_save_age_group', _ajax_nonce: ageNonce || '', age_group: localAge })
          }).catch(() => { });
        }
      } catch (e) { }

      if (currentUser.savedResults) {
        console.log('MI Quiz: Found saved results, rendering results.');
        const r = currentUser.savedResults;
        age = r.age || 'adult';
        top3 = r.top3 || [];
        detailed = r.detailed || {};
        top5 = r.top5 || [];
        bottom3 = r.bottom3 || [];
        part1Scores = r.part1Scores || {};

        if (ageGate) ageGate.style.display = 'none';

        // Ensure stage is hidden for results view
        const stage = $id('mi-stage');
        if (stage) stage.style.display = 'none';

        renderResults();
        if (resultsDiv) resultsDiv.style.display = 'block';
        return;
      } else {
        console.log('MI Quiz: No saved results found for user.');
      }
    }

    // If a staging start button is present, wait until clicked
    const stageBtn = document.getElementById('mi-stage-start');
    const stage = document.getElementById('mi-stage');
    const containerEl = document.getElementById('mi-quiz-container');

    console.log('MI Quiz: Stage elements check:', {
      stageBtn: stageBtn ? 'found' : 'missing',
      stage: stage ? 'found' : 'missing',
      containerEl: containerEl ? 'found' : 'missing'
    });

    // Hide any section wrappers if needed (keep existing logic)
    (function () {
      const wrapper = document.querySelector('.quiz-wrapper');
      if (!wrapper || !wrapper.parentElement) return;
      const shouldHide = (el) => {
        if (!el || !el.classList) return true;
        if (el.classList.contains('quiz-funnel-card')) return false;
        if (el.id === 'mi-about-modal') return false;
        return true;
      };
      let el = wrapper.previousElementSibling; while (el) { if (shouldHide(el)) el.style.display = 'none'; el = el.previousElementSibling; }
      el = wrapper.nextElementSibling; while (el) { if (shouldHide(el)) el.style.display = 'none'; el = el.nextElementSibling; }
    })();

    if (stageBtn && stage) {
      console.log('MI Quiz: Stage button found, adding listener');
      stageBtn.addEventListener('click', () => {
        console.log('MI Quiz: Stage button clicked');
        if (stage) stage.style.display = 'none';
        if (containerEl) containerEl.style.display = 'block';
        startPart1();
      });

      // If stage is mistakenly hidden but we haven't started, show it? 
      // Or if it's hidden, maybe we should auto-start?
      if (stage.style.display === 'none') {
        console.log('MI Quiz: Stage hidden on load, auto-starting');
        startPart1();
      }
    } else {
      console.log('MI Quiz: No stage button or stage element found. Starting Part 1 immediately.');
      if (containerEl) containerEl.style.display = 'block';
      startPart1();
    }
  }

  // Expose and Run
  window.miQuizInit = init;
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();

