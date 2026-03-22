/**
 * Benchmark & Evaluation Unified Logic - JS
 */
jQuery(document).ready(function($) {
    'use strict';

    if (typeof mcBenchmarking === 'undefined') {
        console.error('mcBenchmarking data missing. AJAX features will not work.');
        return;
    }

    // Global state
    let talentChart = null;
    let cultureRadarChart = null;
    let cultureBartleChart = null;
    let scorecards = []; // Loaded from server

    // ─────────────────────────────────────────────────────────────────────────
    // INIT & TAB SWITCHING
    // ─────────────────────────────────────────────────────────────────────────
    function init() {
        loadScorecards(function() {
            // After loading scorecards, initialize the UI
            refreshScorecardDropdowns();
            renderManageCheckboxes();
            updateLiveTeamProfile();
        });
    }

    $('.mc-bench-tab-btn').on('click', function(e) {
        e.preventDefault();
        const targetTab = $(this).data('tab');
        
        $('.mc-bench-tab-btn').removeClass('active');
        $(this).addClass('active');
        
        $('.mc-tab-content').removeClass('active').hide();
        $('#tab-' + targetTab).fadeIn(300).addClass('active');
        
        if (targetTab === 'manage-scorecards') {
            updateLiveTeamProfile();
        }
    });

    // ─────────────────────────────────────────────────────────────────────────
    // DATA FETCHING & CRUD
    // ─────────────────────────────────────────────────────────────────────────
    function loadScorecards(callback) {
        $.post(mcBenchmarking.ajaxUrl, {
            action: 'mc_get_scorecards',
            nonce: mcBenchmarking.cultureNonce
        }, function(response) {
            if (response.success) {
                scorecards = response.data.scorecards;
                if (callback) callback();
            } else {
                alert('Error loading scorecards.');
            }
        });
    }

    function refreshScorecardDropdowns() {
        // Manage Select
        const manageSelect = $('#mc-manage-scorecard-select');
        const evalSelect = $('#mc-eval-scorecard-select');
        
        const activeManageId = manageSelect.val();
        const activeEvalId = evalSelect.val();

        manageSelect.empty();
        evalSelect.empty();

        scorecards.forEach(sc => {
            const option = `<option value="${sc.id}">${sc.label}</option>`;
            manageSelect.append(option);
            evalSelect.append(option);
        });

        // Restore selection if exists
        if (activeManageId && scorecards.find(s => s.id === activeManageId)) {
            manageSelect.val(activeManageId);
        }
        if (activeEvalId && scorecards.find(s => s.id === activeEvalId)) {
            evalSelect.val(activeEvalId);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // TAB 1: MANAGE SCORECARDS
    // ─────────────────────────────────────────────────────────────────────────
    
    // Changing Scorecard Selection
    $('#mc-manage-scorecard-select').on('change', function() {
        renderManageCheckboxes();
        updateLiveTeamProfile();
    });

    // Filtering Employees
    $('#mc-scorecard-search').on('keyup', function() {
        const term = $(this).val().toLowerCase();
        $('.mc-user-item').each(function() {
            const text = $(this).text().toLowerCase();
            $(this).parent().toggle(text.indexOf(term) > -1);
        });
    });

    // Creating Scorecard
    $('#mc-btn-new-scorecard').on('click', function(e) {
        e.preventDefault();
        const name = prompt('Enter a name for the new Scorecard:');
        if (!name) return;

        $.post(mcBenchmarking.ajaxUrl, {
            action: 'mc_create_scorecard',
            nonce: mcBenchmarking.cultureNonce,
            label: name
        }, function(response) {
            if (response.success) {
                loadScorecards(function() {
                    refreshScorecardDropdowns();
                    $('#mc-manage-scorecard-select').val(response.data.scorecard.id).trigger('change');
                });
            } else {
                alert(response.data.message || 'Error creating scorecard.');
            }
        });
    });

    // Renaming Scorecard
    $('#mc-rename-sc').on('click', function(e) {
        e.preventDefault();
        const scId = $('#mc-manage-scorecard-select').val();
        if (!scId) return;

        const currentName = $('#mc-manage-scorecard-select option:selected').text();
        const name = prompt('Rename Scorecard:', currentName);
        if (!name || name === currentName) return;

        $.post(mcBenchmarking.ajaxUrl, {
            action: 'mc_rename_scorecard',
            nonce: mcBenchmarking.cultureNonce,
            scorecard_id: scId,
            label: name
        }, function(response) {
            if (response.success) {
                loadScorecards(function() {
                    refreshScorecardDropdowns();
                });
            } else {
                alert(response.data.message || 'Error renaming scorecard.');
            }
        });
    });

    // Deleting Scorecard
    $('#mc-delete-sc').on('click', function(e) {
        e.preventDefault();
        const scId = $('#mc-manage-scorecard-select').val();
        if (!scId) return;

        if (!confirm('Are you sure you want to delete this scorecard? This cannot be undone.')) return;

        $.post(mcBenchmarking.ajaxUrl, {
            action: 'mc_delete_scorecard',
            nonce: mcBenchmarking.cultureNonce,
            scorecard_id: scId
        }, function(response) {
            if (response.success) {
                loadScorecards(function() {
                    refreshScorecardDropdowns();
                    $('#mc-manage-scorecard-select').trigger('change');
                });
            } else {
                alert(response.data.message || 'Error deleting scorecard.');
            }
        });
    });

    // Bulk Select / Clear Actions
    $('#mc-btn-select-all').on('click', function(e) {
        e.preventDefault();
        // Only target checkboxes that are currently visible (e.g. not hidden by search)
        $('.mc-user-item:visible').find('.mc-culture-checkbox').prop('checked', true);
        syncScorecardCheckboxSelection();
    });

    $('#mc-btn-clear-all').on('click', function(e) {
        e.preventDefault();
        $('.mc-user-item:visible').find('.mc-culture-checkbox').prop('checked', false);
        syncScorecardCheckboxSelection();
    });

    // Toggling specific members
    $(document).on('change', '.mc-culture-checkbox', function() {
        syncScorecardCheckboxSelection();
    });

    function syncScorecardCheckboxSelection() {
        const scId = $('#mc-manage-scorecard-select').val();
        if (!scId) return;

        const selectedIds = $('.mc-culture-checkbox:checked').map(function() { return parseInt($(this).val()); }).get();
        
        // Update local state instantly
        const sc = scorecards.find(s => s.id === scId);
        if (sc) sc.last_selected_ids = selectedIds;

        // Auto-save
        $.post(mcBenchmarking.ajaxUrl, {
            action: 'mc_save_selection',
            nonce: mcBenchmarking.cultureNonce,
            scorecard_id: scId,
            selected_ids: selectedIds
        });

        // Render chart
        updateLiveTeamProfile();
    }

    function renderManageCheckboxes() {
        const scId = $('#mc-manage-scorecard-select').val();
        if (!scId) return;

        const sc = scorecards.find(s => s.id === scId);
        if (!sc) return;

        const selectedIds = sc.last_selected_ids || [];
        $('.mc-culture-checkbox').each(function() {
            const val = parseInt($(this).val());
            $(this).prop('checked', selectedIds.includes(val));
        });
    }

    function updateLiveTeamProfile() {
        const scId = $('#mc-manage-scorecard-select').val();
        const scName = $('#mc-manage-scorecard-select option:selected').text();
        const selectedIds = $('.mc-culture-checkbox:checked').map(function() { return parseInt($(this).val()); }).get();

        if (selectedIds.length === 0) {
            $('#mc-live-scorecard').hide();
            $('#mc-culture-empty').show();
            return;
        }

        $('#mc-culture-empty').hide();
        $('#mc-culture-loading').show();
        $('#mc-live-scorecard').hide();
        $('#mc-scorecard-preview-name').text(scName + ' Profile');

        $.post(mcBenchmarking.ajaxUrl, {
            action: 'mc_get_scorecard',
            nonce: mcBenchmarking.cultureNonce,
            employee_ids: selectedIds
        }, function(response) {
            $('#mc-culture-loading').hide();
            if (response.success) {
                renderCultureScorecard(response.data);
                $('#mc-live-scorecard').fadeIn();
            } else {
                alert(response.data.message || 'Error loading scorecard.');
                $('#mc-culture-empty').show();
            }
        }).fail(function() {
            $('#mc-culture-loading').hide();
            alert('Communication error with server.');
            $('#mc-culture-empty').show();
        });
    }

    function renderCultureScorecard(data) {
        $('#mc-scorecard-member-count').text(data.member_count + ' Members');

        // Radar Chart (CDT)
        initCultureRadar({
            labels: Object.values(data.cdt_labels),
            benchmark: Object.values(data.cdt),
            candidate: []
        });

        // Bar Chart (Bartle)
        initCultureBartle(data.bartle);

        // Team Presence & Blind Spots
        const spots = $('#mc-blind-spots-list');
        spots.empty();
        
        let hasNotables = false;
        
        if (data.player_type_distribution) {
            Object.keys(data.player_type_distribution).forEach(slug => {
                const dist = data.player_type_distribution[slug];
                if (dist.status !== 'present') {
                    hasNotables = true;
                    const labelName = data.bartle_labels ? data.bartle_labels[slug] : slug;
                    const displayStatus = dist.status.replace('-', ' ');
                    
                    spots.append(`
                        <div class="mc-blind-item-premium">
                            <span class="mc-blind-label">${labelName}</span>
                            <div style="display:flex; flex-direction:column; gap:4px;">
                                <span class="mc-blind-status ${dist.status}">${displayStatus}</span>
                                <span style="font-size:12px; color:#64748b; font-weight:500;">Primary for ${dist.count} member${dist.count === 1 ? '' : 's'}</span>
                            </div>
                        </div>
                    `);
                }
            });
        }

        if (!hasNotables) {
            spots.html(`
                <div style="grid-column: 1 / -1; padding: 24px; background: #f8fafc; border-radius: 12px; border: 1px dashed #cbd5e1; display:flex; gap:16px; align-items:center;">
                    <span class="dashicons dashicons-yes-alt" style="color: #10b981; font-size: 32px; width: 32px; height: 32px;"></span>
                    <p style="margin: 0; color: #475569; font-size: 14px; line-height: 1.5;">
                        <strong>Balanced Team Profile:</strong> This active scorecard exhibits a highly balanced distribution of working styles. There are no deeply dominant personas or critical cultural blind spots missing from the group.
                    </p>
                </div>
            `);
        }
    }

    function initCultureRadar(chartData) {
        const ctx = document.getElementById('mc-culture-radar')?.getContext('2d');
        if (!ctx || typeof Chart === 'undefined') return;

        if (cultureRadarChart) cultureRadarChart.destroy();
        cultureRadarChart = new Chart(ctx, {
            type: 'radar',
            data: {
                // Split words by space so Chart.js stacks them on multiple lines
                labels: chartData.labels.map(l => l.split(' ')),
                datasets: [{
                    data: chartData.benchmark,
                    fill: true,
                    backgroundColor: 'rgba(37, 99, 235, 0.2)',
                    borderColor: 'rgb(37, 99, 235)',
                    pointBackgroundColor: 'rgb(37, 99, 235)',
                    borderWidth: 2,
                    pointRadius: 3
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                layout: { padding: 30 },
                scales: {
                    r: { 
                        beginAtZero: true, 
                        max: 100, 
                        ticks: { display: false },
                        pointLabels: {
                            font: { size: 10, weight: '500' },
                            color: '#64748b'
                        }
                    }
                },
                plugins: { legend: { display: false } }
            }
        });
    }

    function initCultureBartle(data) {
        const ctx = document.getElementById('mc-culture-bartle')?.getContext('2d');
        if (!ctx || typeof Chart === 'undefined') return;

        if (cultureBartleChart) cultureBartleChart.destroy();
        
        const labels = Object.keys(data);
        const values = Object.values(data);

        cultureBartleChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    data: values,
                    backgroundColor: ['#3b82f6', '#10b981', '#f59e0b', '#ef4444'],
                    borderRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, max: 100, ticks: { stepSize: 20 } }
                }
            }
        });
    }

    // ─────────────────────────────────────────────────────────────────────────
    // TAB 2: CANDIDATE EVALUATION (Unified)
    // ─────────────────────────────────────────────────────────────────────────
    $('#mc-generate-evaluation').on('click', function() {
        const candidateId = $('#mc-eval-candidate-select').val();
        const scId = $('#mc-eval-scorecard-select').val();
        
        if (!candidateId || !scId) {
            alert('Please select a Target Scorecard and a Candidate.');
            return;
        }

        const sc = scorecards.find(s => s.id === scId);
        if (!sc || !sc.last_selected_ids || sc.last_selected_ids.length === 0) {
            alert('The selected Target Scorecard has no members configured. Please add members to it in the Manage Scorecards tab.');
            return;
        }

        const selectedIds = sc.last_selected_ids;
        const targetName = $('#mc-eval-scorecard-select option:selected').text();

        // States
        $('#mc-bench-empty').hide();
        $('#mc-eval-results').hide();
        $('#mc-bench-loading').fadeIn();
        
        // Update PDF generation inputs
        $('#mc-pdf-candidate-id').val(candidateId);
        $('#mc-pdf-scorecard-id').val(scId);

        // We need to fetch both Talent Comparison (Radar/Traits) AND Culture Scorecard (Narrative) concurrently.
        const reqTalent = $.post(mcBenchmarking.ajaxUrl, {
            action: 'mc_get_benchmark_data',
            nonce: mcBenchmarking.nonce, // Uses bench nonce
            rockstar_ids: selectedIds,
            candidate_id: candidateId
        });

        const reqCulture = $.post(mcBenchmarking.ajaxUrl, {
            action: 'mc_get_culture_fit',
            nonce: mcBenchmarking.cultureNonce,
            candidate_id: candidateId,
            scorecard_id: scId
        });

        $.when(reqTalent, reqCulture).done(function(talentResp, cultureResp) {
            $('#mc-bench-loading').hide();
            
            const rTalent = talentResp[0];
            const rCulture = cultureResp[0];

            if (rTalent.success && rCulture.success) {
                renderUnifiedEvaluation(rTalent.data, rCulture.data, targetName);
                $('#mc-eval-results').fadeIn(400);
            } else {
                alert('Analysis failed. Check if all selected benchmark employees and candidates hold valid assessment data.');
                $('#mc-bench-empty').show();
            }
        }).fail(function() {
            $('#mc-bench-loading').hide();
            alert('Communication error with server.');
            $('#mc-bench-empty').show();
        });
    });

    function renderUnifiedEvaluation(talentData, cultureData, targetName) {
        // Hero Update
        $('#mc-eval-candidate-name').text(cultureData.candidate_name);
        $('#mc-eval-target-name').text(targetName);
        $('#mc-fit-pct').text(talentData.match_percent + '%'); // Use Talent % as main
        
        const tag = $('#mc-fit-label');
        tag.text(cultureData.fit_label);
        if (talentData.match_percent > 80) tag.css({'background':'#dcfce7', 'color':'#166534'});
        else if (talentData.match_percent > 60) tag.css({'background':'#fef9c3', 'color':'#854d0e'});
        else tag.css({'background':'#fee2e2', 'color':'#991b1b'});

        // Radar Chart (MI/CDT Overlay)
        initTalentRadar(talentData.chart_data);

        const list = $('#mc-traits-list');
        list.empty();
        
        // Filter out irrelevant 0/0 baseline gaps and sort highest match to lowest
        const validTraits = talentData.trait_breakdown.filter(t => !(t.candidate === 0 && t.benchmark === 0));
        validTraits.sort((a,b) => b.match - a.match);

        const highMatches = validTraits.filter(t => t.match >= 85);
        const midMatches = validTraits.filter(t => t.match >= 60 && t.match < 85);
        const lowMatches = validTraits.filter(t => t.match < 60);

        const renderSection = (title, traits, colorHex) => {
            if (traits.length === 0) return;
            
            list.append(`<h4 style="margin: 24px 0 16px 0; font-size: 11px; text-transform: uppercase; letter-spacing: 0.1em; color: #94a3b8; border-bottom: 1px solid #e2e8f0; padding-bottom: 6px;">${title}</h4>`);
            
            traits.forEach(trait => {
                const row = $(`
                    <div class="mc-trait-row-premium" style="margin-bottom: 20px;">
                        <div class="mc-trait-name-flex">
                            <span class="mc-trait-label">${trait.name}</span>
                            <div style="text-align:right;">
                                 <span class="mc-trait-val" style="margin-right:16px; font-size:12px; color:#64748b; font-weight:500;">
                                     Team Avg: <strong style="color:#94a3b8;">${Math.round(trait.benchmark)}</strong> 
                                     <span style="margin:0 6px; opacity:0.3;">|</span> 
                                     Candidate: <strong style="color:var(--mc-primary);">${Math.round(trait.candidate)}</strong>
                                 </span>
                                 <span class="mc-trait-val" style="color:${colorHex}; font-weight:700;">${trait.match}% Match</span>
                            </div>
                        </div>
                        <div class="mc-trait-bar-wrap">
                            <div class="bar-bench" style="width: ${trait.benchmark}%"></div>
                            <div class="bar-cand" style="width: ${trait.candidate}%"></div>
                        </div>
                    </div>
                `);
                list.append(row);
            });
        };

        renderSection('High Alignment (85%+)', highMatches, '#0d9488'); // teal-600
        renderSection('Moderate Alignment (60-84%)', midMatches, '#d97706'); // amber-600
        renderSection('Low Alignment (<60%)', lowMatches, '#dc2626'); // red-600

        // Culture Fit Narrative (The text payload)
        const body = $('#mc-fit-report-body');
        body.empty();
        
        if (cultureData.onboarding_mods) {
            body.append(`<h3>Strategic Onboarding & Reframing</h3><p>${cultureData.onboarding_mods}</p>`);
        }
        if (cultureData.cultural_synergy) {
            body.append(`<h3>Cultural Synergy Analysis</h3><p>${cultureData.cultural_synergy}</p>`);
        }
        if (cultureData.recommendations && cultureData.recommendations.length > 0) {
            let recHtml = '<h3>Key Recommendations</h3><ul class="mc-rec-list">';
            cultureData.recommendations.forEach(rec => {
                recHtml += `<li><strong>${rec.title}:</strong> ${rec.desc}</li>`;
            });
            recHtml += '</ul>';
            body.append(recHtml);
        }
    }

    function initTalentRadar(chartData) {
        const ctx = document.getElementById('mc-bench-radar')?.getContext('2d');
        if (!ctx || typeof Chart === 'undefined') return;

        if (talentChart) talentChart.destroy();

        talentChart = new Chart(ctx, {
            type: 'radar',
            data: {
                labels: chartData.labels,
                datasets: [
                    {
                        label: 'Target Benchmark',
                        data: chartData.benchmark,
                        fill: true,
                        backgroundColor: 'rgba(100, 116, 139, 0.35)',
                        borderColor: 'rgba(100, 116, 139, 0.8)',
                        pointBackgroundColor: 'rgba(100, 116, 139, 1)',
                        borderWidth: 2,
                        borderDash: [5, 5],
                        pointRadius: 0
                    },
                    {
                        label: 'Candidate',
                        data: chartData.candidate,
                        fill: true,
                        backgroundColor: 'rgba(37, 99, 235, 0.2)',
                        borderColor: 'rgb(37, 99, 235)',
                        pointBackgroundColor: 'rgb(37, 99, 235)',
                        pointBorderColor: '#fff',
                        pointHoverBackgroundColor: '#fff',
                        pointHoverBorderColor: 'rgb(37, 99, 235)',
                        borderWidth: 3,
                        pointRadius: 4
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    r: { beginAtZero: true, max: 100, ticks: { display: false, stepSize: 20 }, grid: { color: '#e2e8f0' } }
                },
                plugins: { legend: { display: false } }
            }
        });
    }

    // Auto-init
    init();

});
