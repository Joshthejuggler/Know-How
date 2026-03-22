# Career Mind-Map View - Implementation Guide

## Overview
Add a second layout view (Mind-Map) to Career Explorer using D3.js for visualization, while keeping the existing Cards view untouched.

## Technology Stack
- **Visualization**: D3.js v7 (force simulation)
- **DOM**: jQuery for event handling
- **State**: Extend existing `LabModeApp` object
- **Persistence**: localStorage + URL params
- **Backend**: Existing AJAX endpoints

## Implementation Plan

### Phase 1: Tab System & Layout Switching

#### 1.1 Add Layout State to LabModeApp
```javascript
// In LabModeApp initialization
careerLayout: 'cards', // 'cards' | 'map'
```

#### 1.2 Update renderCareerExplorerTab()
Add view tabs above the existing mini-tabs (Explore/Saved):

```html
<!-- View Layout Tabs -->
<div class="career-view-tabs">
    <button class="career-view-tab active" data-view="cards" onclick="LabModeApp.switchCareerView('cards')">
        📇 Cards
    </button>
    <button class="career-view-tab" data-view="map" onclick="LabModeApp.switchCareerView('map')">
        🗺️ Mind-Map
    </button>
</div>
```

#### 1.3 Add switchCareerView() Method
```javascript
switchCareerView: function(layout) {
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
    console.log('career_layout_switched', { to: layout });
    
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

### Phase 2: D3.js Integration

#### 2.1 Enqueue D3.js in PHP
In `micro-coach-ai-lab.php`:

```php
wp_enqueue_script(
    'd3js',
    'https://d3js.org/d3.v7.min.js',
    array(),
    '7.8.5',
    true
);
```

#### 2.2 Add Mind-Map Container
In displayCareerSuggestions(), add conditional rendering:

```html
${layout === 'map' 
    ? this.renderMindMapView(data, seedCareer)
    : this.renderCardsView(data, seedCareer)
}
```

### Phase 3: Mind-Map Visualization

#### 3.1 Create renderMindMapView() Method
```javascript
renderMindMapView: function(data, seedCareer) {
    return `
        <div class="career-mindmap-container">
            <div class="mindmap-header">
                <h3>Mind-Map: ${seedCareer || 'Your Profile'}</h3>
                <div class="mindmap-legend">
                    <span class="legend-item"><span class="legend-dot lane-adjacent"></span> Adjacent</span>
                    <span class="legend-item"><span class="legend-dot lane-parallel"></span> Parallel</span>
                    <span class="legend-item"><span class="legend-dot lane-wildcard"></span> Wildcard</span>
                </div>
            </div>
            <div id="career-mindmap-canvas"></div>
            <div class="mindmap-node-drawer" id="mindmap-drawer" style="display: none;"></div>
        </div>
    `;
},
```

#### 3.2 Initialize D3 After Rendering
```javascript
initializeMindMap: function(data, seedCareer) {
    const width = $('#career-mindmap-canvas').width();
    const height = 600;
    
    // Create SVG
    const svg = d3.select('#career-mindmap-canvas')
        .append('svg')
        .attr('width', width)
        .attr('height', height)
        .attr('viewBox', [0, 0, width, height]);
    
    // Prepare node data
    const nodes = this.prepareMindMapNodes(data, seedCareer);
    const links = this.prepareMindMapLinks(nodes);
    
    // Force simulation
    const simulation = d3.forceSimulation(nodes)
        .force('link', d3.forceLink(links).id(d => d.id).distance(150))
        .force('charge', d3.forceManyBody().strength(-300))
        .force('center', d3.forceCenter(width / 2, height / 2))
        .force('collision', d3.forceCollide().radius(40));
    
    // Render links
    const link = svg.append('g')
        .selectAll('line')
        .data(links)
        .join('line')
        .attr('class', 'mindmap-link')
        .attr('stroke', '#94a3b8')
        .attr('stroke-width', d => d.similarity * 3);
    
    // Render nodes
    const node = svg.append('g')
        .selectAll('g')
        .data(nodes)
        .join('g')
        .attr('class', d => `mindmap-node ${d.type}`)
        .call(this.mindMapDrag(simulation));
    
    // Node circles
    node.append('circle')
        .attr('r', d => d.type === 'seed' ? 30 : 20)
        .attr('class', d => d.lane ? `node-${d.lane}` : 'node-seed')
        .attr('stroke-width', d => d.fit * 5);
    
    // Node labels
    node.append('text')
        .text(d => d.title)
        .attr('dy', 35)
        .attr('text-anchor', 'middle')
        .attr('class', 'node-label');
    
    // Node interactions
    node.on('click', (event, d) => {
        this.showMindMapNodeDrawer(d);
    });
    
    // Update positions on tick
    simulation.on('tick', () => {
        link
            .attr('x1', d => d.source.x)
            .attr('y1', d => d.source.y)
            .attr('x2', d => d.target.x)
            .attr('y2', d => d.target.y);
        
        node.attr('transform', d => `translate(${d.x},${d.y})`);
    });
},
```

#### 3.3 Prepare Node Data
```javascript
prepareMindMapNodes: function(data, seedCareer) {
    const nodes = [];
    
    // Center node
    nodes.push({
        id: 'seed',
        title: seedCareer || 'Your Profile',
        type: 'seed',
        x: 0,
        y: 0
    });
    
    // Adjacent careers
    if (data.adjacent) {
        data.adjacent.forEach((career, i) => {
            nodes.push({
                id: `adj-${i}`,
                title: career.title,
                type: 'career',
                lane: 'adjacent',
                fit: career.profile_match?.fit || 0.5,
                similarity: career.profile_match?.similarity || 0.7,
                data: career
            });
        });
    }
    
    // Parallel careers
    if (data.parallel) {
        data.parallel.forEach((career, i) => {
            nodes.push({
                id: `par-${i}`,
                title: career.title,
                type: 'career',
                lane: 'parallel',
                fit: career.profile_match?.fit || 0.5,
                similarity: career.profile_match?.similarity || 0.5,
                data: career
            });
        });
    }
    
    // Wildcard careers
    if (data.wildcard) {
        data.wildcard.forEach((career, i) => {
            nodes.push({
                id: `wild-${i}`,
                title: career.title,
                type: 'career',
                lane: 'wildcard',
                fit: career.profile_match?.fit || 0.5,
                similarity: career.profile_match?.similarity || 0.3,
                data: career
            });
        });
    }
    
    return nodes;
},

prepareMindMapLinks: function(nodes) {
    const links = [];
    const seed = nodes.find(n => n.type === 'seed');
    
    nodes.forEach(node => {
        if (node.type === 'career') {
            links.push({
                source: seed.id,
                target: node.id,
                similarity: node.similarity
            });
        }
    });
    
    return links;
},
```

#### 3.4 Node Drawer
```javascript
showMindMapNodeDrawer: function(nodeData) {
    if (!nodeData.data) return; // Skip seed node
    
    const career = nodeData.data;
    const drawer = $('#mindmap-drawer');
    
    const html = `
        <div class="drawer-header">
            <h4>${career.title}</h4>
            <button class="drawer-close" onclick="LabModeApp.closeMindMapDrawer()">✕</button>
        </div>
        <div class="drawer-body">
            <p>${career.why_it_fits}</p>
            ${career.profile_match ? this.renderProfileMatchSection(career.profile_match, false) : ''}
            ${career.meta ? this.renderRequirementsSection(career.meta, false) : ''}
            ${career.meta ? this.renderWorkStyleSection(career.meta, false) : ''}
        </div>
        <div class="drawer-actions">
            <button class="lab-btn lab-btn-secondary" onclick="LabModeApp.mindMapNodeAction('not_interested', '${nodeData.id}')">
                Not interested
            </button>
            <button class="lab-btn lab-btn-primary" onclick="LabModeApp.mindMapNodeAction('save', '${nodeData.id}')">
                Save ♥
            </button>
        </div>
    `;
    
    drawer.html(html).slideDown(200);
    
    // Analytics
    console.log('career_map_node_opened', { nodeId: nodeData.id, lane: nodeData.lane });
},

closeMindMapDrawer: function() {
    $('#mindmap-drawer').slideUp(200);
},
```

#### 3.5 Drag Behavior
```javascript
mindMapDrag: function(simulation) {
    function dragstarted(event) {
        if (!event.active) simulation.alphaTarget(0.3).restart();
        event.subject.fx = event.subject.x;
        event.subject.fy = event.subject.y;
    }
    
    function dragged(event) {
        event.subject.fx = event.x;
        event.subject.fy = event.y;
    }
    
    function dragended(event) {
        if (!event.active) simulation.alphaTarget(0);
        event.subject.fx = null;
        event.subject.fy = null;
    }
    
    return d3.drag()
        .on('start', dragstarted)
        .on('drag', dragged)
        .on('end', dragended);
},
```

### Phase 4: Styling

#### 4.1 Tab Styles
```css
/* View Layout Tabs */
.career-view-tabs {
    display: flex;
    gap: 8px;
    margin-bottom: 16px;
    padding-bottom: 16px;
    border-bottom: 1px solid #e5e7eb;
}

.career-view-tab {
    padding: 10px 20px;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    background: #ffffff;
    color: #4b5563;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
}

.career-view-tab:hover {
    background: #f9fafb;
    border-color: #9ca3af;
}

.career-view-tab.active {
    background: #3b82f6;
    color: #ffffff;
    border-color: #3b82f6;
}
```

#### 4.2 Mind-Map Styles
```css
/* Mind-Map Container */
.career-mindmap-container {
    position: relative;
    background: #ffffff;
    border-radius: 12px;
    padding: 20px;
}

.mindmap-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.mindmap-legend {
    display: flex;
    gap: 16px;
    font-size: 13px;
}

.legend-item {
    display: flex;
    align-items: center;
    gap: 6px;
}

.legend-dot {
    width: 12px;
    height: 12px;
    border-radius: 50%;
}

.legend-dot.lane-adjacent {
    background: #3B82F6;
}

.legend-dot.lane-parallel {
    background: #8B5CF6;
}

.legend-dot.lane-wildcard {
    background: #F59E0B;
}

/* SVG Canvas */
#career-mindmap-canvas {
    width: 100%;
    min-height: 600px;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    background: #f9fafb;
}

/* Nodes */
.mindmap-node {
    cursor: pointer;
}

.mindmap-node circle {
    stroke: #ffffff;
    transition: all 0.2s;
}

.mindmap-node:hover circle {
    r: 25;
    filter: drop-shadow(0 4px 6px rgba(0, 0, 0, 0.1));
}

.node-seed {
    fill: #1e293b;
}

.node-adjacent {
    fill: #3B82F6;
}

.node-parallel {
    fill: #8B5CF6;
}

.node-wildcard {
    fill: #F59E0B;
}

.node-label {
    font-size: 11px;
    fill: #1e293b;
    font-weight: 500;
    pointer-events: none;
}

/* Links */
.mindmap-link {
    opacity: 0.6;
}

/* Node Drawer */
.mindmap-node-drawer {
    position: absolute;
    top: 80px;
    right: 20px;
    width: 320px;
    max-height: 500px;
    overflow-y: auto;
    background: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    z-index: 10;
}

.drawer-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 16px;
    border-bottom: 1px solid #e5e7eb;
}

.drawer-header h4 {
    margin: 0;
    font-size: 16px;
}

.drawer-close {
    background: none;
    border: none;
    font-size: 20px;
    cursor: pointer;
    color: #6b7280;
}

.drawer-body {
    padding: 16px;
}

.drawer-actions {
    display: flex;
    gap: 8px;
    padding: 16px;
    border-top: 1px solid #e5e7eb;
}
```

### Phase 5: Mobile Adaptation

```javascript
// Detect mobile and render swimlanes instead
if (window.innerWidth < 768) {
    this.renderMindMapMobile(data, seedCareer);
} else {
    this.initializeMindMap(data, seedCareer);
}
```

## Analytics Events

```javascript
// On view switch
console.log('career_layout_switched', { from: oldLayout, to: newLayout });

// On node click
console.log('career_map_node_opened', { nodeId, lane });

// On node action
console.log('career_map_node_action', { action, nodeId, lane });
```

## Testing Checklist

- [ ] Tab switching preserves filter state
- [ ] URL params sync on tab change
- [ ] localStorage persists layout preference
- [ ] Mind-map renders with D3
- [ ] Node click opens drawer
- [ ] Drag nodes works
- [ ] Mobile shows swimlanes
- [ ] Cards view unchanged
- [ ] Analytics fire correctly
- [ ] Keyboard navigation works

## Files to Modify

1. `assets/lab-mode.js` - Add mind-map methods
2. `assets/lab-mode.css` - Add mind-map styles
3. `micro-coach-ai-lab.php` - Enqueue D3.js

## Rollback

If issues arise, set `careerLayout = 'cards'` as default and hide the Mind-Map tab.
