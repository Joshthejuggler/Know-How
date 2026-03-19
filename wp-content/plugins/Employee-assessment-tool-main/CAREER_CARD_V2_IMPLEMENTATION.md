# Career Card V2 - Design System Implementation Guide

## Overview
This document outlines the complete refactor of the Career Card component with improved visual hierarchy, grouped metadata sections, enhanced interactions, and accessibility improvements.

## Summary of Changes Required

### 1. JavaScript Changes (`lab-mode.js`)

#### A. Add Compact Mode State Management
```javascript
// Add to LabModeApp initialization
compactMode: false,

// Add toggle method
toggleCompactMode: function(enabled) {
    this.compactMode = enabled;
    localStorage.setItem('career_compact_mode', enabled);
    // Re-render results if they exist
    const resultsContainer = document.getElementById('career-explorer-results');
    if (resultsContainer && resultsContainer.style.display !== 'none') {
        // Trigger re-render with current data
        this.refreshCareerCards();
    }
},
```

#### B. Update `renderCareerCardEnhanced()` Method

Replace lines 3296-3322 with:

```javascript
renderCareerCardEnhanced: function(career, index, clusterType) {
    const cardId = `career-card-${clusterType}-${index}`;
    const titleId = `${cardId}-title`;
    const isCompact = this.compactMode || false;
    
    // Build data attributes for analytics
    const dataAttrs = `
        data-card-id="${cardId}"
        data-mi-match="${career.profile_match?.mi?.join(',') || ''}"
        data-bartle="${career.profile_match?.bartle || ''}"
        data-growth-horizon="${career.meta?.demand_horizon || ''}"
        data-education="${career.meta?.education || ''}"
        data-remote="${career.meta?.work_env?.includes('remote_friendly') ? 'true' : 'false'}"
    `.trim();
    
    return `
        <article 
            class="career-card career-card-v2 career-card-${clusterType} ${isCompact ? 'career-card-compact' : ''}" 
            id="${cardId}" 
            data-career-title="${this.escapeHtml(career.title)}" 
            data-cluster-type="${clusterType}"
            ${dataAttrs}
            role="group"
            aria-labelledby="${titleId}"
        >
            <!-- Title -->
            <h3 class="career-card-title" id="${titleId}">${this.escapeHtml(career.title)}</h3>
            
            <!-- Summary (hidden in compact mode) -->
            ${!isCompact ? `<p class="career-card-summary">${this.escapeHtml(career.why_it_fits)}</p>` : ''}
            
            <!-- Divider -->
            <div class="career-card-divider"></div>
            
            <!-- Profile Match Section -->
            ${this.renderProfileMatchSection(career.profile_match, isCompact)}
            
            <!-- Requirements Section -->
            ${this.renderRequirementsSection(career.meta, isCompact)}
            
            <!-- Work Style Tags Section -->
            ${this.renderWorkStyleSection(career.meta, isCompact)}
            
            <!-- Actions -->
            <div class="career-card-actions">
                <div class="career-actions-row">
                    <button 
                        class="career-action-btn career-action-dismiss" 
                        data-action="not_interested"
                        aria-label="Dismiss ${this.escapeHtml(career.title)}"
                        title="Not interested"
                    >
                        ${isCompact ? '✕' : 'Not interested'}
                    </button>
                    <button 
                        class="career-action-btn career-action-save" 
                        data-action="save"
                        aria-label="Save ${this.escapeHtml(career.title)}"
                        title="Save this career"
                    >
                        ${isCompact ? '♥' : 'Save ♥'}
                    </button>
                </div>
                <a 
                    href="#" 
                    class="career-action-link" 
                    data-action="explain_fit"
                    aria-label="Learn why ${this.escapeHtml(career.title)} fits your profile"
                >
                    Why this fits me
                </a>
            </div>
        </article>
    `;
},

// Helper method for HTML escaping
escapeHtml: function(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
},
```

#### C. Add New Section Rendering Methods

```javascript
// Profile Match Section
renderProfileMatchSection: function(profileMatch, isCompact) {
    if (!profileMatch) return '';
    
    const badgeSize = isCompact ? 'badge-sm' : '';
    let html = '<div class="career-section career-profile-section">';
    html += '<h4 class="career-section-title">Profile Match</h4>';
    html += '<div class="career-badges">';
    
    // MI badges
    if (profileMatch.mi && profileMatch.mi.length > 0) {
        html += profileMatch.mi.map(mi => 
            `<span class="career-badge badge-mi ${badgeSize}">${this.escapeHtml(mi)}</span>`
        ).join('');
    }
    
    // Bartle badge
    if (profileMatch.bartle) {
        html += `<span class="career-badge badge-bartle ${badgeSize}">${this.escapeHtml(profileMatch.bartle)}</span>`;
    }
    
    // CDT top (if available)
    if (profileMatch.cdt_top) {
        html += `<span class="career-badge badge-cdt ${badgeSize}">${this.escapeHtml(profileMatch.cdt_top.replace(/_/g, ' '))}</span>`;
    }
    
    html += '</div></div>';
    return html;
},

// Requirements Section
renderRequirementsSection: function(meta, isCompact) {
    if (!meta || !meta.education) return '';
    
    const badgeSize = isCompact ? 'badge-sm' : '';
    let html = '<div class="career-section career-requirements-section">';
    html += '<h4 class="career-section-title">Requirements</h4>';
    html += '<div class="career-badges">';
    
    // Education with icon
    html += `<span class="career-badge badge-education ${badgeSize}">
        <svg class="badge-icon" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor">
            <path d="M22 10v6M2 10l10-5 10 5-10 5z" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        ${this.formatMetaLabel('education', meta.education)}
    </span>`;
    
    html += '</div></div>';
    return html;
},

// Work Style Tags Section
renderWorkStyleSection: function(meta, isCompact) {
    if (!meta) return '';
    
    const badgeSize = isCompact ? 'badge-sm' : '';
    let html = '<div class="career-section career-workstyle-section">';
    html += '<h4 class="career-section-title">Work Style</h4>';
    html += '<div class="career-badges">';
    
    // Demand horizon with icon
    if (meta.demand_horizon) {
        html += `<span class="career-badge badge-demand ${badgeSize}">
            <svg class="badge-icon" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                <path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            ${this.formatMetaLabel('demand_horizon', meta.demand_horizon)}
        </span>`;
    }
    
    // Pay band with icon
    if (meta.comp_band) {
        html += `<span class="career-badge badge-pay ${badgeSize}">
            <svg class="badge-icon" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                <path d="M12 2v20M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            ${this.formatMetaLabel('comp_band', meta.comp_band)}
        </span>`;
    }
    
    // Work environment (show first, + more)
    if (meta.work_env && meta.work_env.length > 0) {
        const envIcon = meta.work_env.includes('remote_friendly') ? 
            '<svg class="badge-icon" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>' :
            '';
        
        html += `<span class="career-badge badge-remote ${badgeSize}">
            ${envIcon}
            ${this.formatMetaLabel('work_env', meta.work_env[0])}
        </span>`;
        
        if (meta.work_env.length > 1) {
            html += `<span class="career-badge badge-more ${badgeSize}">+${meta.work_env.length - 1}</span>`;
        }
    }
    
    // Social impact
    if (meta.social_impact && meta.social_impact.length > 0) {
        html += `<span class="career-badge badge-impact ${badgeSize}">${this.formatMetaLabel('social_impact', meta.social_impact[0])}</span>`;
        if (meta.social_impact.length > 1) {
            html += `<span class="career-badge badge-more ${badgeSize}">+${meta.social_impact.length - 1}</span>`;
        }
    }
    
    html += '</div></div>';
    return html;
},
```

#### D. Add Skeleton Loader

```javascript
renderSkeletonCards: function(count = 6) {
    return Array(count).fill(0).map((_, i) => `
        <div class="career-card career-card-skeleton" aria-hidden="true">
            <div class="skeleton-title"></div>
            <div class="skeleton-summary"></div>
            <div class="skeleton-divider"></div>
            <div class="skeleton-badges">
                <div class="skeleton-badge"></div>
                <div class="skeleton-badge"></div>
                <div class="skeleton-badge"></div>
            </div>
            <div class="skeleton-badges">
                <div class="skeleton-badge"></div>
            </div>
            <div class="skeleton-actions">
                <div class="skeleton-button"></div>
                <div class="skeleton-button"></div>
            </div>
        </div>
    `).join('');
},
```

#### E. Add Compact Mode Toggle to Results Header

Update `displayCareerSuggestions()` to include toggle:

```javascript
const html = `
    <div class="career-map-results ${isDice ? 'dice-roll' : ''}">
        <div class="career-map-header">
            <div class="career-header-row">
                <div>
                    <h3>${isDice ? '🎲 Dice Roll Results' : (seedCareer ? 'Career Ideas for: ' + seedCareer : 'Career Ideas Based on Your Profile')}</h3>
                    <p>Careers aligned with your MI, CDT, Bartle Type, and Johari profile</p>
                </div>
                <label class="compact-mode-toggle">
                    <input 
                        type="checkbox" 
                        ${this.compactMode ? 'checked' : ''} 
                        onchange="LabModeApp.toggleCompactMode(this.checked)"
                    >
                    <span>Compact mode</span>
                </label>
            </div>
        </div>
        ...
`;
```

---

## 2. CSS Changes (`lab-mode.css`)

### Add to end of file:

```css
/* ===== Career Card V2 Design System ===== */

/* Card Base */
.career-card-v2 {
    background: #FFFFFF;
    border: 1px solid #E6EAF2;
    border-radius: 12px;
    padding: 18px 20px;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    overflow: hidden;
}

.career-card-v2::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    width: 3px;
    background: linear-gradient(180deg, #3b82f6 0%, #60a5fa 100%);
    opacity: 0;
    transition: opacity 0.2s;
}

.career-card-v2:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
    border-color: #93C5FD;
}

.career-card-v2:hover::before {
    opacity: 1;
}

.career-card-v2:focus-within {
    outline: 2px solid #93C5FD;
    outline-offset: 2px;
}

/* Card Title */
.career-card-title {
    margin: 0 0 8px 0;
    font-size: 19px;
    font-weight: 700;
    line-height: 1.3;
    color: #0F172A;
    transition: color 0.2s;
}

.career-card-v2:hover .career-card-title {
    color: #1D4ED8;
}

/* Card Summary */
.career-card-summary {
    margin: 0 0 14px 0;
    font-size: 14.5px;
    line-height: 1.5;
    color: #475569;
}

/* Divider */
.career-card-divider {
    height: 1px;
    background: #EEF2F7;
    margin: 14px 0;
}

/* Sections */
.career-section {
    margin-bottom: 14px;
}

.career-section:last-of-type {
    margin-bottom: 16px;
}

.career-section-title {
    margin: 0 0 8px 0;
    font-size: 11px;
    font-weight: 600;
    letter-spacing: 0.05em;
    text-transform: uppercase;
    color: #64748B;
}

.career-badges {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}

/* Badge System */
.career-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 4px 10px;
    border-radius: 6px;
    font-size: 12.5px;
    font-weight: 500;
    line-height: 1.2;
    white-space: nowrap;
}

.badge-icon {
    flex-shrink: 0;
    width: 12px;
    height: 12px;
}

/* MI Badges - Blue */
.badge-mi {
    background: #DCEBFF;
    color: #2563EB;
    border: 1px solid #BFDBFE;
}

/* Bartle Badge - Amber */
.badge-bartle {
    background: #FEF3C7;
    color: #B45309;
    border: 1px solid #FDE68A;
}

/* CDT Badge - Teal */
.badge-cdt {
    background: #D1FAE5;
    color: #0F766E;
    border: 1px solid #A7F3D0;
}

/* Education Badge - Slate */
.badge-education {
    background: #E6EAF2;
    color: #334155;
    border: 1px solid #CBD5E1;
}

/* Demand Badge - Orange */
.badge-demand {
    background: #FFEAD5;
    color: #C2410C;
    border: 1px solid #FED7AA;
}

/* Pay Badge - Green */
.badge-pay {
    background: #DCFCE7;
    color: #166534;
    border: 1px solid #BBF7D0;
}

/* Remote/Work Badge - Purple */
.badge-remote {
    background: #EDE9FE;
    color: #6D28D9;
    border: 1px solid #DDD6FE;
}

/* Impact Badge - Pink */
.badge-impact {
    background: #FCE7F3;
    color: #9D174D;
    border: 1px solid #FBCFE8;
}

/* More Badge */
.badge-more {
    background: #F1F5F9;
    color: #64748B;
    border: 1px solid #E2E8F0;
    font-size: 11px;
}

/* Small Badges (Compact Mode) */
.badge-sm {
    padding: 3px 8px;
    font-size: 11px;
}

.badge-sm .badge-icon {
    width: 10px;
    height: 10px;
}

/* Actions */
.career-card-actions {
    margin-top: 16px;
}

.career-actions-row {
    display: flex;
    gap: 10px;
    margin-bottom: 10px;
}

.career-action-btn {
    flex: 1;
    padding: 9px 16px;
    border-radius: 8px;
    font-size: 13.5px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.15s;
    border: 1.5px solid;
}

.career-action-dismiss {
    background: #FFFFFF;
    border-color: #D1D5DB;
    color: #6B7280;
}

.career-action-dismiss:hover {
    background: #F9FAFB;
    border-color: #9CA3AF;
}

.career-action-save {
    background: #DC2626;
    border-color: #DC2626;
    color: #FFFFFF;
}

.career-action-save:hover {
    background: #B91C1C;
    transform: scale(1.02);
}

.career-action-save:active {
    animation: heartPulse 0.3s ease;
}

@keyframes heartPulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.1); }
}

.career-action-link {
    display: inline-block;
    font-size: 13px;
    color: #3B82F6;
    text-decoration: none;
    font-weight: 500;
    transition: color 0.15s;
}

.career-action-link:hover {
    color: #1D4ED8;
    text-decoration: underline;
}

/* Compact Mode */
.career-card-compact {
    padding: 14px 16px;
}

.career-card-compact .career-section {
    margin-bottom: 10px;
}

.career-card-compact .career-section-title {
    font-size: 10px;
    margin-bottom: 6px;
}

.career-card-compact .career-badges {
    gap: 4px;
}

.career-card-compact .career-actions-row {
    gap: 8px;
}

.career-card-compact .career-action-btn {
    padding: 8px;
    font-size: 16px;
    line-height: 1;
    min-width: 36px;
    justify-content: center;
}

/* Compact Mode Toggle */
.compact-mode-toggle {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 14px;
    color: #475569;
    cursor: pointer;
    user-select: none;
}

.compact-mode-toggle input[type="checkbox"] {
    cursor: pointer;
}

.career-header-row {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 20px;
}

/* Skeleton Loader */
.career-card-skeleton {
    pointer-events: none;
}

.career-card-skeleton > div {
    background: linear-gradient(90deg, #F1F5F9 0%, #E2E8F0 50%, #F1F5F9 100%);
    background-size: 200% 100%;
    animation: shimmer 1.5s infinite;
    border-radius: 4px;
}

@keyframes shimmer {
    0% { background-position: 200% 0; }
    100% { background-position: -200% 0; }
}

.skeleton-title {
    height: 20px;
    width: 70%;
    margin-bottom: 10px;
}

.skeleton-summary {
    height: 14px;
    width: 100%;
    margin-bottom: 6px;
}

.skeleton-divider {
    height: 1px;
    width: 100%;
    margin: 14px 0;
}

.skeleton-badges {
    display: flex;
    gap: 6px;
    margin-bottom: 12px;
}

.skeleton-badge {
    height: 24px;
    width: 60px;
}

.skeleton-actions {
    display: flex;
    gap: 10px;
    margin-top: 16px;
}

.skeleton-button {
    height: 36px;
    flex: 1;
}

/* Responsive */
@media (max-width: 768px) {
    .career-card-v2 {
        padding: 16px;
    }
    
    .career-header-row {
        flex-direction: column;
        gap: 12px;
    }
    
    .compact-mode-toggle {
        align-self: flex-start;
    }
}
```

---

## 3. Implementation Steps

1. **Test current functionality** - Ensure "Why this fits me" is working
2. **Backup current files** - Copy lab-mode.js and lab-mode.css
3. **Add new CSS** - Append Career Card V2 styles to lab-mode.css
4. **Update JavaScript** - Replace renderCareerCardEnhanced and add new methods
5. **Test incrementally** - Test cards render, then compact mode, then interactions
6. **Add skeleton loader** - Show during loading states
7. **Accessibility audit** - Test with keyboard and screen reader

## 4. Analytics Events to Add

```javascript
// On save
console.log('career_save', { card_id, mi_match, bartle, growth_horizon });

// On dismiss
console.log('career_dismiss', { card_id, cluster_type });

// On explain
console.log('career_explain_open', { card_id, career_title });
```

---

## Notes

- **Do NOT change API contracts** - Work with existing data structure
- **Graceful degradation** - If fields missing, show "—" or hide section
- **Performance** - Virtual scrolling if >20 cards
- **Browser support** - Test in Chrome, Firefox, Safari

This completes the V2 design system specification.
