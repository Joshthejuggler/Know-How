# Mind-Map Expansion - Implementation Guide

## Overview
Add expandable, multi-depth Mind-Map with node expansion, re-rooting, breadcrumbs, lightweight tooltips, and saved careers integration.

## Phase 1: State Management & Data Structure

### Add to LabModeApp initialization:
```javascript
// Mind-Map state
mindMapState: {
    centerId: 'seed',
    nodes: {},
    edges: [],
    openRequests: new Set(),
    history: [],
    expandedNodes: new Set()
},
savedCareers: new Set(), // Track hearted careers
```

### Node Structure:
```javascript
{
    id: 'career-123',
    title: 'Wildlife Conservator',
    type: 'career', // or 'seed'
    lane: 'parallel',
    depth: 1,
    parentId: 'seed',
    fit: 0.85,
    similarity: 0.72,
    data: { /* full career object */ },
    mi: ['Naturalist', 'Interpersonal'],
    cdt_top: 'Complexity Tolerance',
    bartle: 'Explorer'
}
```

## Phase 2: Backend API Endpoint

### New PHP Endpoint: `ajax_get_related_careers`
```php
public function ajax_get_related_careers() {
    check_ajax_referer('mc_lab_nonce', 'nonce');
    
    $career_title = sanitize_text_field($_POST['career_title']);
    $lane = sanitize_text_field($_POST['lane']); // adjacent|parallel|wildcard
    $limit = intval($_POST['limit']) ?: 8;
    $novelty = floatval($_POST['novelty']) ?: 0.5;
    
    // Call AI to generate related careers
    $related = $this->generate_related_careers($career_title, $lane, $limit, $novelty);
    
    wp_send_json_success($related);
}

private function generate_related_careers($seed_career, $lane, $limit, $novelty) {
    // Prepare prompt for AI based on lane type
    $lane_prompts = [
        'adjacent' => 'very similar careers with easy transitions',
        'parallel' => 'careers using similar skills in different industries',
        'wildcard' => 'unexpected careers that match the profile'
    ];
    
    $system_prompt = "Generate {$limit} {$lane_prompts[$lane]} related to: {$seed_career}";
    
    // Call OpenAI API (reuse existing Micro_Coach_AI class)
    // Return array of career objects with id, title, fit, similarity, etc.
}
```

## Phase 3: JavaScript - Expand Node Function

### Add method to fetch and expand:
```javascript
expandNode: function(nodeId, lane = 'parallel') {
    const requestKey = `${nodeId}|${lane}`;
    
    if (this.mindMapState.openRequests.has(requestKey)) {
        console.log('Already fetching:', requestKey);
        return;
    }
    
    // Get node data
    const node = this.mindMapState.nodes[nodeId];
    if (!node) {
        console.error('Node not found:', nodeId);
        return;
    }
    
    // Mark as expanded
    this.mindMapState.expandedNodes.add(requestKey);
    this.mindMapState.openRequests.add(requestKey);
    
    // Show loading state on node
    d3.select(`#node-${nodeId}`).classed('loading', true);
    
    // Fetch related careers
    $.ajax({
        url: labMode.ajaxUrl,
        method: 'POST',
        data: {
            action: 'mc_lab_get_related_careers',
            nonce: labMode.nonce,
            career_title: node.title,
            lane: lane,
            limit: 8,
            novelty: this.careerFilters?.novelty_bias || 0.5
        },
        success: (response) => {
            this.mindMapState.openRequests.delete(requestKey);
            d3.select(`#node-${nodeId}`).classed('loading', false);
            
            if (response.success) {
                this.addChildrenToMap(nodeId, lane, response.data);
                console.log('career_map_expand', { nodeId, lane, count: response.data.length });
            }
        },
        error: () => {
            this.mindMapState.openRequests.delete(requestKey);
            d3.select(`#node-${nodeId}`).classed('loading', false);
            alert('Failed to load related careers');
        }
    });
},
```

### Add children to map:
```javascript
addChildrenToMap: function(parentId, lane, children) {
    const parentNode = this.mindMapState.nodes[parentId];
    const newDepth = parentNode.depth + 1;
    
    // Check depth limit
    if (newDepth > 3) {
        console.warn('Max depth reached, not adding children');
        return;
    }
    
    // Add new nodes (deduplicate)
    children.forEach((child, i) => {
        const childId = `${parentId}-${lane}-${i}`;
        
        // Check if career already exists
        const existing = Object.values(this.mindMapState.nodes)
            .find(n => n.title === child.title);
        
        if (existing) {
            // Add edge to existing node instead of duplicating
            this.mindMapState.edges.push({
                source: parentId,
                target: existing.id,
                similarity: child.similarity
            });
        } else {
            // Add new node
            this.mindMapState.nodes[childId] = {
                id: childId,
                title: child.title,
                type: 'career',
                lane: lane,
                depth: newDepth,
                parentId: parentId,
                fit: child.fit,
                similarity: child.similarity,
                data: child,
                mi: child.mi || [],
                cdt_top: child.cdt_top,
                bartle: child.bartle
            };
            
            // Add edge
            this.mindMapState.edges.push({
                source: parentId,
                target: childId,
                similarity: child.similarity
            });
        }
    });
    
    // Re-render map
    this.updateMindMapVisualization();
},
```

## Phase 4: Re-root Function

```javascript
setMapCenter: function(nodeId) {
    const node = this.mindMapState.nodes[nodeId];
    if (!node) return;
    
    // Save current center to history
    this.mindMapState.history.push(this.mindMapState.centerId);
    
    // Set new center
    this.mindMapState.centerId = nodeId;
    
    // Recalculate depths from new center
    this.recalculateDepths(nodeId);
    
    // Re-render
    this.updateMindMapVisualization();
    
    // Update breadcrumbs
    this.updateBreadcrumbs();
    
    // Analytics
    console.log('career_map_reroot', { nodeId });
},

recalculateDepths: function(centerId) {
    // BFS to recalculate depths from new center
    const queue = [{ id: centerId, depth: 0 }];
    const visited = new Set();
    
    while (queue.length > 0) {
        const { id, depth } = queue.shift();
        if (visited.has(id)) continue;
        visited.add(id);
        
        const node = this.mindMapState.nodes[id];
        if (node) {
            node.depth = depth;
            
            // Find children
            this.mindMapState.edges
                .filter(e => e.source === id)
                .forEach(e => {
                    if (!visited.has(e.target)) {
                        queue.push({ id: e.target, depth: depth + 1 });
                    }
                });
        }
    }
},
```

## Phase 5: Lightweight Tooltip

```javascript
showNodeTooltip: function(event, nodeData) {
    const tooltip = d3.select('#mindmap-tooltip');
    if (tooltip.empty()) {
        d3.select('#career-mindmap-canvas')
            .append('div')
            .attr('id', 'mindmap-tooltip')
            .style('position', 'absolute')
            .style('display', 'none');
    }
    
    const html = `
        <div class="tooltip-header">
            <h5>${this.escapeHtml(nodeData.title)}</h5>
            <span class="tooltip-lane badge-${nodeData.lane}">${nodeData.lane}</span>
        </div>
        <div class="tooltip-stats">
            <span class="tooltip-stat">Fit: ${Math.round(nodeData.fit * 100)}%</span>
            <span class="tooltip-stat">Sim: ${Math.round(nodeData.similarity * 100)}%</span>
        </div>
        <div class="tooltip-badges">
            ${nodeData.mi.slice(0, 2).map(mi => `<span class="badge-mi-sm">${mi}</span>`).join('')}
            ${nodeData.bartle ? `<span class="badge-bartle-sm">${nodeData.bartle}</span>` : ''}
        </div>
        <div class="tooltip-actions">
            <button class="tooltip-btn" onclick="LabModeApp.saveCareerFromMap('${nodeData.id}')">♥ Save</button>
            <button class="tooltip-btn" onclick="LabModeApp.dismissCareerFromMap('${nodeData.id}')">✕ Not interested</button>
        </div>
    `;
    
    d3.select('#mindmap-tooltip')
        .html(html)
        .style('left', (event.pageX + 10) + 'px')
        .style('top', (event.pageY - 10) + 'px')
        .style('display', 'block');
},

hideNodeTooltip: function() {
    d3.select('#mindmap-tooltip').style('display', 'none');
},
```

## Phase 6: Breadcrumbs

```javascript
updateBreadcrumbs: function() {
    const path = this.getPathToNode(this.mindMapState.centerId);
    const breadcrumbsHtml = path.map((nodeId, i) => {
        const node = this.mindMapState.nodes[nodeId];
        const isLast = i === path.length - 1;
        return `
            <span class="breadcrumb-item ${isLast ? 'active' : ''}" 
                  onclick="${isLast ? '' : `LabModeApp.setMapCenter('${nodeId}')`}">
                ${node.title}
            </span>
            ${isLast ? '' : '<span class="breadcrumb-sep">›</span>'}
        `;
    }).join('');
    
    $('#mindmap-breadcrumbs').html(breadcrumbsHtml);
},

getPathToNode: function(nodeId) {
    const path = [];
    let current = nodeId;
    
    while (current) {
        path.unshift(current);
        const node = this.mindMapState.nodes[current];
        current = node ? node.parentId : null;
    }
    
    return path;
},
```

## Phase 7: CSS for Tooltips & Breadcrumbs

```css
/* Lightweight Tooltip */
#mindmap-tooltip {
    background: white;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    padding: 12px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    z-index: 1000;
    max-width: 280px;
}

.tooltip-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
}

.tooltip-header h5 {
    margin: 0;
    font-size: 14px;
    font-weight: 600;
}

.tooltip-lane {
    font-size: 10px;
    padding: 2px 6px;
    border-radius: 4px;
}

.tooltip-stats {
    display: flex;
    gap: 12px;
    font-size: 11px;
    color: #6b7280;
    margin-bottom: 8px;
}

.tooltip-badges {
    display: flex;
    gap: 4px;
    flex-wrap: wrap;
    margin-bottom: 8px;
}

.badge-mi-sm, .badge-bartle-sm {
    font-size: 10px;
    padding: 2px 6px;
    border-radius: 4px;
}

.tooltip-actions {
    display: flex;
    gap: 6px;
}

.tooltip-btn {
    flex: 1;
    padding: 6px;
    font-size: 11px;
    border: 1px solid #d1d5db;
    border-radius: 4px;
    background: white;
    cursor: pointer;
}

.tooltip-btn:hover {
    background: #f9fafb;
}

/* Breadcrumbs */
#mindmap-breadcrumbs {
    margin-bottom: 12px;
    font-size: 13px;
    color: #6b7280;
}

.breadcrumb-item {
    cursor: pointer;
    transition: color 0.2s;
}

.breadcrumb-item:not(.active):hover {
    color: #3b82f6;
}

.breadcrumb-item.active {
    color: #0f172a;
    font-weight: 600;
    cursor: default;
}

.breadcrumb-sep {
    margin: 0 6px;
}
```

## Phase 8: Saved Careers View

Add a "Saved Careers" filter to Cards view to show only hearted careers.

## Implementation Order

1. ✅ Backend endpoint for related careers
2. ✅ JavaScript expand/re-root functions
3. ✅ Lightweight tooltip (replace drawer)
4. ✅ Breadcrumbs navigation
5. ✅ Save/dismiss from tooltip
6. ✅ Double-click to re-root
7. ✅ Context menu for lane selection
8. ✅ CSS for tooltips and breadcrumbs
9. ✅ Mobile adaptations
10. ✅ Analytics events

## Testing Checklist

- [ ] Click node expands children
- [ ] Double-click re-roots graph
- [ ] Breadcrumbs update and are clickable
- [ ] Tooltip shows on hover
- [ ] Save button hearts career
- [ ] Depth limit enforced (3 levels)
- [ ] Duplicate nodes create edges instead
- [ ] Back button works
- [ ] Mobile tap interactions work
- [ ] Analytics events fire

## Estimated Work

- Backend: 2 hours
- Frontend expansion: 3 hours  
- Tooltip & breadcrumbs: 1 hour
- Testing & polish: 1 hour
- **Total: 7 hours**
