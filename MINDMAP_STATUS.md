# Career Mind-Map - Implementation Status

## ✅ Completed

1. **D3.js Integration** - Enqueued D3.js v7.8.5 in `micro-coach-ai-lab.php` (lines 577-584)
2. **Documentation** - Created `CAREER_MINDMAP_IMPLEMENTATION.md` with complete implementation guide
3. **Filter System** - Already implemented in existing codebase (lines 2827-3241 in lab-mode.js)

## 🚧 Next Steps

### Phase 1: Add Layout State & Tab System

#### 1. Add careerLayout state to LabModeApp
In `assets/lab-mode.js`, add to initialization (around line 16):
```javascript
careerLayout: localStorage.getItem('career_layout') || 'cards',
```

#### 2. Add View Tabs to Career Explorer
Update `renderCareerExplorerTab()` method (around line 2756) to add view tabs BEFORE the mini-tabs:

```javascript
render CareerExplorerTab: function() {
    // Initialize filters
    if (!this.careerFilters) {
        this.careerFilters = this.getDefaultFilters();
        this.loadFiltersFromStorage();
    }
    
    const html = `
        <div class="career-explorer-container">
            <div class="career-explorer-header">
                <h2>⚡ Career Explorer</h2>
                <p class="career-explorer-subtitle">Discover roles aligned with your profile, with smart filters and labor-market insights.</p>
            </div>
            
            <!-- ADD THIS: View Layout Tabs -->
            <div class="career-view-tabs">
                <button class="career-view-tab ${this.careerLayout === 'cards' ? 'active' : ''}" data-view="cards" onclick="LabModeApp.switchCareerView('cards')">
                    📇 Cards
                </button>
                <button class="career-view-tab ${this.careerLayout === 'map' ? 'active' : ''}" data-view="map" onclick="LabModeApp.switchCareerView('map')">
                    🗺️ Mind-Map
                </button>
            </div>
            
            <!-- Existing mini tabs remain the same -->
            <div class="career-mini-tabs">
                ...
            </div>
            ...
        `;
    
    return html;
},
```

#### 3. Add switchCareerView() method
Add after `renderCareerExplorerTab()` method:

```javascript
switchCareerView: function(layout) {
    const oldLayout = this.careerLayout;
    this.careerLayout = layout;
    localStorage.setItem('career_layout', layout);
    
    // Update URL
    const url = new URL(window.location);
    url.searchParams.set('layout', layout);
    window.history.pushState({}, '', url);
    
    // Update tab UI
    $('.career-view-tab').removeClass('active');
    $(`.career-view-tab[data-view="${layout}"]`).addClass('active');
    
    // Analytics
    console.log('career_layout_switched', { from: oldLayout, to: layout });
    
    // Re-render results if they exist
    if (this.currentCareerData) {
        this.displayCareerSuggestions(
            this.currentCareerData,
            this.currentCareerInterest,
            this.isDiceRoll
        );
    }
},
```

### Phase 2: Update displayCareerSuggestions()

Modify the method (around line 3234) to conditionally render based on layout:

```javascript
displayCareerSuggestions: function(data, seedCareer, isDice) {
    // Store for feedback actions and re-rendering
    this.currentCareerInterest = seedCareer;
    this.isDiceRoll = isDice;
    this.currentCareerData = data;
    
    const resultsContainer = document.getElementById('career-explorer-results');
    
    // Choose rendering based on layout
    let contentHtml;
    if (this.careerLayout === 'map') {
        contentHtml = this.renderMindMapView(data, seedCareer, isDice);
    } else {
        contentHtml = this.renderCardsView(data, seedCareer, isDice);
    }
    
    const html = `
        <div class="career-map-results ${isDice ? 'dice-roll' : ''}">
            ${contentHtml}
            <div class="career-map-actions">
                <button class="lab-btn lab-btn-secondary" onclick="document.getElementById('career-interest-input').value=''; document.getElementById('career-interest-input').focus(); document.getElementById('career-explorer-results').style.display='none';">
                    Explore Another Career
                </button>
            </div>
        </div>
    `;
    
    resultsContainer.innerHTML = html;
    resultsContainer.style.display = 'block';
    
    // Initialize mind-map if that's the active layout
    if (this.careerLayout === 'map') {
        setTimeout(() => {
            this.initializeMindMap(data, seedCareer);
        }, 100);
    }
    
    // Scroll to results
    resultsContainer.scrollIntoView({ behavior: 'smooth', block: 'start' });
},
```

### Phase 3: Add renderCardsView() method

Extract current cards rendering into separate method:

```javascript
renderCardsView: function(data, seedCareer, isDice) {
    return `
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
        
        <div class="career-clusters">
            ${this.renderCareerClusterEnhanced('Adjacent Careers', 'Very similar; easy transitions', data.adjacent, 'adjacent')}
            ${this.renderCareerClusterEnhanced('Parallel Careers', 'Similar strengths, different industries', data.parallel, 'parallel')}
            ${this.renderCareerClusterEnhanced('Wildcard Careers', 'Unexpected options based on your unique profile', data.wildcard, 'wildcard')}
        </div>
    `;
},
```

### Phase 4: Add Mind-Map Methods

Add these 6 new methods after renderCardsView():

1. `renderMindMapView(data, seedCareer, isDice)` - Returns HTML structure
2. `initializeMindMap(data, seedCareer)` - Creates D3 visualization
3. `prepareMindMapNodes(data, seedCareer)` - Formats node data
4. `prepareMindMapLinks(nodes)` - Creates link data
5. `showMindMapNodeDrawer(nodeData)` - Shows career details drawer
6. `closeMindMapDrawer()` - Hides drawer
7. `mindMapDrag(simulation)` - D3 drag behavior
8. `mindMapNodeAction(action, nodeId)` - Handle node actions (save/dismiss)

All code for these methods is in `CAREER_MINDMAP_IMPLEMENTATION.md` (lines 95-331).

### Phase 5: Add CSS Styles

Append to `assets/lab-mode.css`:

1. View tabs styles (lines 337-368 in implementation guide)
2. Mind-map container styles (lines 372-515 in implementation guide)

Total: ~180 lines of CSS

## Files to Modify

1. ✅ `micro-coach-ai-lab.php` - D3.js enqueued
2. ⏳ `assets/lab-mode.js` - Add 8 new methods + modify 2 existing
3. ⏳ `assets/lab-mode.css` - Add ~180 lines of CSS

## Estimated Work

- JavaScript changes: ~400 lines of new code
- CSS changes: ~180 lines
- Testing: 30 minutes
- **Total: 2-3 hours of implementation**

## Current Branch Status

All changes so far have been committed and pushed to GitHub:
- Commit `6209da9`: Career Card V2
- Commit `ce2d2db`: Career Explorer filters docs + backups

## Testing Plan

After implementation:
1. Click Mind-Map tab - should show D3 visualization
2. Click Cards tab - should show existing card grid
3. Tab switch should persist to localStorage
4. URL should update with `?layout=map` or `?layout=cards`
5. Clicking nodes should show drawer
6. Dragging nodes should work
7. Save/dismiss buttons should work
8. Mobile should gracefully degrade

## Notes

- Filters already work across both views (no changes needed)
- Cards view remains completely untouched
- Mind-map uses same AJAX endpoints and data structure
- D3.js is loaded but won't affect performance until mind-map is rendered
