(function () {
    const root = document.getElementById("prm-product-roadmap-app");
    const boot = window.prmProductRoadmap;

    if (!root || !boot || !Array.isArray(boot.sections)) {
        return;
    }

    const COLORS = {
        purple: { dot: "#5745b6", tagBg: "var(--purple-bg)", tagText: "var(--purple-text)" },
        teal: { dot: "#0d735c", tagBg: "var(--teal-bg)", tagText: "var(--teal-text)" },
        coral: { dot: "#a14a1f", tagBg: "var(--coral-bg)", tagText: "var(--coral-text)" },
        amber: { dot: "#956212", tagBg: "var(--amber-bg)", tagText: "var(--amber-text)" },
        blue: { dot: "#2167ac", tagBg: "var(--blue-bg)", tagText: "var(--blue-text)" },
        pink: { dot: "#a33d62", tagBg: "var(--pink-bg)", tagText: "var(--pink-text)" }
    };

    const STATUS_LABELS = {
        todo: "To Do",
        in_progress: "In Progress",
        blocked: "Blocked",
        done: "Done"
    };

    const PRIORITY_LABELS = {
        low: "Low",
        medium: "Medium",
        high: "High"
    };

    const state = {
        sections: cloneSections(boot.sections),
        currentCat: "all",
        search: "",
        savingIds: new Set(),
        modalOpen: false,
        submitting: false,
        uploadingAttachment: false,
        draftAttachments: [],
        originalAttachmentIds: []
    };

    root.innerHTML = `
        <div class="mc-roadmap">
            <header class="mc-roadmap__header">
                <div class="mc-roadmap__header-top">
                    <div class="mc-roadmap__title">
                        <div class="mc-roadmap__eyebrow">Standalone Plugin</div>
                        <h1>Product Roadmap Dashboard</h1>
                        <p>Manage feature work, technical debt, follow-ups, and future bets in a standalone WordPress plugin.</p>
                    </div>
                    <div class="mc-roadmap__controls">
                        <div class="mc-roadmap__progress">
                            <span class="mc-roadmap__progress-text" data-role="progress-text">0 of 0 done</span>
                            <div class="mc-roadmap__progress-track">
                                <div class="mc-roadmap__progress-fill" data-role="progress-fill"></div>
                            </div>
                        </div>
                        <label class="mc-roadmap__search">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="8"></circle><path d="m21 21-4.35-4.35"></path></svg>
                            <input type="search" data-role="search" placeholder="Search tasks, notes, or owners..." aria-label="Search tasks">
                        </label>
                        <button class="mc-roadmap__primary-button" type="button" data-action="new-task">New Task</button>
                    </div>
                </div>
                <nav class="mc-roadmap__tabs" data-role="tabs" aria-label="Task category filters"></nav>
            </header>
            <main class="mc-roadmap__main">
                <section class="mc-roadmap__stats" data-role="stats"></section>
                <section data-role="board"></section>
                <div class="mc-roadmap__empty" data-role="empty">No tasks match this filter.</div>
            </main>
            <div class="mc-roadmap__notice" data-role="notice"></div>
            <div class="mc-roadmap__modal-shell" data-role="modal-shell" hidden>
                <div class="mc-roadmap__modal-backdrop" data-action="close-modal"></div>
                <div class="mc-roadmap__modal" role="dialog" aria-modal="true" aria-labelledby="prm-roadmap-modal-title">
                    <div class="mc-roadmap__modal-header">
                        <div>
                            <p class="mc-roadmap__modal-kicker" data-role="modal-kicker">Create Task</p>
                            <h2 id="prm-roadmap-modal-title" data-role="modal-title">New roadmap task</h2>
                        </div>
                        <button class="mc-roadmap__icon-button" type="button" data-action="close-modal" aria-label="Close task editor">&times;</button>
                    </div>
                    <form class="mc-roadmap__form" data-role="task-form">
                        <input type="hidden" name="itemId">
                        <div class="mc-roadmap__field mc-roadmap__field--full">
                            <label for="prm-roadmap-title">Title</label>
                            <input id="prm-roadmap-title" name="title" type="text" required maxlength="120">
                        </div>
                        <div class="mc-roadmap__field mc-roadmap__field--full">
                            <label for="prm-roadmap-desc">Details</label>
                            <textarea id="prm-roadmap-desc" name="desc" rows="4" placeholder="What needs to happen?"></textarea>
                        </div>
                        <div class="mc-roadmap__field">
                            <label for="prm-roadmap-cat">Category</label>
                            <select id="prm-roadmap-cat" name="cat"></select>
                        </div>
                        <div class="mc-roadmap__field">
                            <label for="prm-roadmap-status">Status</label>
                            <select id="prm-roadmap-status" name="status">
                                <option value="todo">To Do</option>
                                <option value="in_progress">In Progress</option>
                                <option value="blocked">Blocked</option>
                                <option value="done">Done</option>
                            </select>
                        </div>
                        <div class="mc-roadmap__field">
                            <label for="prm-roadmap-priority">Priority</label>
                            <select id="prm-roadmap-priority" name="priority">
                                <option value="low">Low</option>
                                <option value="medium">Medium</option>
                                <option value="high">High</option>
                            </select>
                        </div>
                        <div class="mc-roadmap__field">
                            <label for="prm-roadmap-owner">Owner</label>
                            <input id="prm-roadmap-owner" name="owner" type="text" maxlength="60" placeholder="Josh">
                        </div>
                        <div class="mc-roadmap__field">
                            <label for="prm-roadmap-due">Due Date</label>
                            <input id="prm-roadmap-due" name="dueDate" type="date">
                        </div>
                        <div class="mc-roadmap__field mc-roadmap__field--full">
                            <label for="prm-roadmap-completion-notes">Comments / Completion Notes</label>
                            <textarea id="prm-roadmap-completion-notes" name="completionNotes" rows="4" placeholder="Add notes about progress, implementation details, or how this task was completed."></textarea>
                        </div>
                        <div class="mc-roadmap__field mc-roadmap__field--full">
                            <label>Screenshots</label>
                            <div class="mc-roadmap__paste-zone" data-role="paste-zone" tabindex="0">
                                <strong>Paste screenshots here</strong>
                                <span>Click this area and paste with Cmd+V / Ctrl+V. Screenshots are uploaded into the WordPress media library and linked to this task.</span>
                            </div>
                            <div class="mc-roadmap__attachment-list" data-role="attachment-list"></div>
                        </div>
                        <div class="mc-roadmap__form-footer">
                            <button class="mc-roadmap__secondary-button" type="button" data-action="close-modal">Cancel</button>
                            <button class="mc-roadmap__primary-button" type="submit" data-role="submit-button">Save Task</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    `;

    const els = {
        tabs: root.querySelector('[data-role="tabs"]'),
        stats: root.querySelector('[data-role="stats"]'),
        board: root.querySelector('[data-role="board"]'),
        empty: root.querySelector('[data-role="empty"]'),
        search: root.querySelector('[data-role="search"]'),
        progressText: root.querySelector('[data-role="progress-text"]'),
        progressFill: root.querySelector('[data-role="progress-fill"]'),
        notice: root.querySelector('[data-role="notice"]'),
        modalShell: root.querySelector('[data-role="modal-shell"]'),
        modalTitle: root.querySelector('[data-role="modal-title"]'),
        modalKicker: root.querySelector('[data-role="modal-kicker"]'),
        taskForm: root.querySelector('[data-role="task-form"]'),
        submitButton: root.querySelector('[data-role="submit-button"]'),
        pasteZone: root.querySelector('[data-role="paste-zone"]'),
        attachmentList: root.querySelector('[data-role="attachment-list"]')
    };

    hydrateCategorySelect();

    function cloneSections(sections) {
        return sections.map((section) => ({
            ...section,
            items: Array.isArray(section.items) ? section.items.map((item) => ({ ...item })) : []
        }));
    }

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    function request(action, params) {
        const body = new URLSearchParams({
            action,
            nonce: boot.nonce,
            ...params
        });

        return fetch(boot.ajaxUrl, {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8" },
            body: body.toString()
        }).then(async (response) => {
            const payload = await response.json();
            if (!response.ok || !payload || !payload.success) {
                const message = payload && payload.data && payload.data.message ? payload.data.message : "Task request failed.";
                throw new Error(message);
            }
            return payload.data;
        });
    }

    function hydrateCategorySelect() {
        const select = els.taskForm.elements.cat;
        select.innerHTML = state.sections.map((section) => `
            <option value="${section.cat}">${escapeHtml(section.label)}</option>
        `).join("");
    }

    function getVisibleSections() {
        const query = state.search.trim().toLowerCase();

        return state.sections
            .map((section) => {
                const visibleItems = section.items.filter((item) => {
                    const matchesCategory = state.currentCat === "all" || state.currentCat === section.cat;
                    const searchText = `${item.title} ${item.desc} ${item.owner || ""}`.toLowerCase();
                    const matchesSearch = !query || searchText.includes(query);
                    return matchesCategory && matchesSearch;
                });

                return { ...section, visibleItems };
            })
            .filter((section) => state.currentCat === "all" || section.cat === state.currentCat);
    }

    function getTotals() {
        const allItems = state.sections.flatMap((section) => section.items.map((item) => ({ ...item, cat: section.cat })));

        return {
            total: allItems.length,
            done: allItems.filter((item) => item.status === "done").length,
            inProgress: allItems.filter((item) => item.status === "in_progress").length,
            blocked: allItems.filter((item) => item.status === "blocked").length
        };
    }

    function renderTabs() {
        const tabs = [
            { cat: "all", label: "All", count: state.sections.reduce((sum, section) => sum + section.items.length, 0) },
            ...state.sections.map((section) => ({
                cat: section.cat,
                label: section.label,
                count: section.items.length
            }))
        ];

        els.tabs.innerHTML = tabs.map((tab) => `
            <button class="mc-roadmap__tab ${tab.cat === state.currentCat ? "is-active" : ""}" type="button" data-cat="${tab.cat}">
                ${escapeHtml(tab.label)} <span class="mc-roadmap__tab-count">${tab.count}</span>
            </button>
        `).join("");
    }

    function renderStats() {
        const totals = getTotals();
        const pct = totals.total > 0 ? Math.round((totals.done / totals.total) * 100) : 0;

        els.stats.innerHTML = [
            ["Total Tasks", totals.total, "all roadmap tasks"],
            ["Completed", totals.done, "marked done"],
            ["In Progress", totals.inProgress, "actively moving"],
            ["Blocked", totals.blocked, "needs input or dependency"]
        ].map(([label, value, sub]) => `
            <article class="mc-roadmap__stat">
                <p class="mc-roadmap__stat-label">${label}</p>
                <p class="mc-roadmap__stat-value">${value}</p>
                <p class="mc-roadmap__stat-sub">${sub}</p>
            </article>
        `).join("");

        els.progressText.textContent = `${totals.done} of ${totals.total} done`;
        els.progressFill.style.width = `${pct}%`;
    }

    function renderBoard() {
        const visibleSections = getVisibleSections();
        let visibleItems = 0;

        els.board.innerHTML = visibleSections.map((section) => {
            const color = COLORS[section.color] || COLORS.purple;
            const items = section.visibleItems;
            visibleItems += items.length;

            return `
                <section class="mc-roadmap__section ${items.length ? "" : "is-hidden"}" data-cat="${section.cat}">
                    <div class="mc-roadmap__section-header">
                        <span class="mc-roadmap__section-dot" style="background:${color.dot}"></span>
                        <span class="mc-roadmap__section-title">${escapeHtml(section.label)}</span>
                        <span class="mc-roadmap__section-count">${section.items.length} tasks</span>
                    </div>
                    <div class="mc-roadmap__grid">
                        ${items.map((item) => renderCard(section, item, color)).join("")}
                    </div>
                </section>
            `;
        }).join("");

        els.empty.classList.toggle("is-visible", visibleItems === 0);
    }

    function renderCard(section, item, color) {
        const isSaving = state.savingIds.has(item.id);
        const dueMeta = item.dueDate ? `<span class="mc-roadmap__meta-pill">Due ${escapeHtml(formatDate(item.dueDate))}</span>` : "";
        const ownerMeta = item.owner ? `<span class="mc-roadmap__meta-pill">Owner ${escapeHtml(item.owner)}</span>` : "";
        const notes = item.completionNotes ? `<div class="mc-roadmap__note">${escapeHtml(item.completionNotes)}</div>` : "";
        const attachments = Array.isArray(item.attachments) && item.attachments.length
            ? `<div class="mc-roadmap__thumb-row">${item.attachments.map((attachment) => `
                <a class="mc-roadmap__thumb-link" href="${escapeHtml(attachment.url)}" target="_blank" rel="noreferrer">
                    <img class="mc-roadmap__thumb" src="${escapeHtml(attachment.thumbnailUrl || attachment.url)}" alt="${escapeHtml(attachment.filename || "Task screenshot")}">
                </a>
            `).join("")}</div>`
            : "";

        return `
            <article class="mc-roadmap__card ${item.status === "done" ? "is-done" : ""}" data-item-id="${item.id}">
                <button class="mc-roadmap__toggle" type="button" data-action="toggle" data-item-id="${item.id}" aria-pressed="${item.status === "done" ? "true" : "false"}" ${isSaving ? "disabled" : ""}>
                    <svg width="10" height="10" viewBox="0 0 12 12" fill="none" stroke="white" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="1.5,6.5 4.5,9.5 10.5,2.5"></polyline></svg>
                </button>
                <div class="mc-roadmap__card-body">
                    <div class="mc-roadmap__card-topline">
                        <span class="mc-roadmap__status mc-roadmap__status--${item.status}">${escapeHtml(STATUS_LABELS[item.status] || "To Do")}</span>
                        <span class="mc-roadmap__priority mc-roadmap__priority--${item.priority}">${escapeHtml(PRIORITY_LABELS[item.priority] || "Medium")} priority</span>
                    </div>
                    <h3 class="mc-roadmap__card-title">${escapeHtml(item.title)}</h3>
                    <p class="mc-roadmap__card-desc">${escapeHtml(item.desc || "No details yet.")}</p>
                    <div class="mc-roadmap__meta-row">
                        ${ownerMeta}
                        ${dueMeta}
                        <span class="mc-roadmap__tag" style="background:${color.tagBg};color:${color.tagText}">${escapeHtml(section.label)}</span>
                    </div>
                    ${notes}
                    ${attachments}
                    <div class="mc-roadmap__card-actions">
                        <button class="mc-roadmap__text-button" type="button" data-action="edit" data-item-id="${item.id}">Edit</button>
                        <button class="mc-roadmap__text-button mc-roadmap__text-button--danger" type="button" data-action="delete" data-item-id="${item.id}">Delete</button>
                    </div>
                </div>
            </article>
        `;
    }

    function render() {
        renderTabs();
        renderStats();
        renderBoard();
    }

    function setSections(sections) {
        state.sections = cloneSections(sections);
        hydrateCategorySelect();
        render();
    }

    function findTask(itemId) {
        for (const section of state.sections) {
            const item = section.items.find((candidate) => candidate.id === itemId);
            if (item) {
                return { section, item };
            }
        }

        return null;
    }

    function openModal(task) {
        state.modalOpen = true;
        state.draftAttachments = task && Array.isArray(task.attachments) ? task.attachments.map((attachment) => ({ ...attachment })) : [];
        state.originalAttachmentIds = state.draftAttachments.map((attachment) => Number(attachment.id));
        els.modalShell.hidden = false;
        els.modalKicker.textContent = task ? "Edit Task" : "Create Task";
        els.modalTitle.textContent = task ? "Update roadmap task" : "New roadmap task";
        els.submitButton.textContent = task ? "Save Changes" : "Create Task";

        els.taskForm.reset();
        els.taskForm.elements.itemId.value = task ? task.id : "";
        els.taskForm.elements.title.value = task ? task.title : "";
        els.taskForm.elements.desc.value = task ? task.desc : "";
        els.taskForm.elements.cat.value = task ? findTask(task.id).section.cat : state.currentCat === "all" ? state.sections[0].cat : state.currentCat;
        els.taskForm.elements.status.value = task ? task.status : "todo";
        els.taskForm.elements.priority.value = task ? task.priority : "medium";
        els.taskForm.elements.owner.value = task ? (task.owner || "") : "";
        els.taskForm.elements.dueDate.value = task ? (task.dueDate || "") : "";
        els.taskForm.elements.completionNotes.value = task ? (task.completionNotes || "") : "";
        renderAttachmentList();
        els.taskForm.elements.title.focus();
    }

    function closeModal(preserveUploads = false) {
        if (!preserveUploads) {
            const originalSet = new Set(state.originalAttachmentIds);
            state.draftAttachments.forEach((attachment) => {
                const attachmentId = Number(attachment.id);
                if (!originalSet.has(attachmentId)) {
                    deleteTemporaryAttachment(attachmentId).catch(() => {});
                }
            });
        }

        state.modalOpen = false;
        state.submitting = false;
        state.uploadingAttachment = false;
        state.draftAttachments = [];
        state.originalAttachmentIds = [];
        els.modalShell.hidden = true;
    }

    function renderAttachmentList() {
        if (!Array.isArray(state.draftAttachments) || state.draftAttachments.length === 0) {
            els.attachmentList.innerHTML = `<div class="mc-roadmap__attachment-empty">No screenshots attached yet.</div>`;
            return;
        }

        els.attachmentList.innerHTML = state.draftAttachments.map((attachment) => `
            <div class="mc-roadmap__attachment-card">
                <img class="mc-roadmap__attachment-preview" src="${escapeHtml(attachment.thumbnailUrl || attachment.url)}" alt="${escapeHtml(attachment.filename || "Task screenshot")}">
                <div class="mc-roadmap__attachment-meta">
                    <a href="${escapeHtml(attachment.url)}" target="_blank" rel="noreferrer">${escapeHtml(attachment.filename || "Screenshot")}</a>
                </div>
                <button class="mc-roadmap__text-button mc-roadmap__text-button--danger" type="button" data-action="remove-attachment" data-attachment-id="${attachment.id}">Remove</button>
            </div>
        `).join("");
    }

    function uploadAttachment(file) {
        const body = new FormData();
        body.append("action", "prm_upload_task_image");
        body.append("nonce", boot.nonce);
        body.append("file", file, file.name || "task-screenshot.png");

        return fetch(boot.ajaxUrl, {
            method: "POST",
            body
        }).then(async (response) => {
            const payload = await response.json();
            if (!response.ok || !payload || !payload.success) {
                const message = payload && payload.data && payload.data.message ? payload.data.message : "Image upload failed.";
                throw new Error(message);
            }
            return payload.data.attachment;
        });
    }

    function deleteTemporaryAttachment(attachmentId) {
        return request("prm_delete_task_image", { attachmentId });
    }

    function handlePastedFiles(fileList) {
        const imageFile = Array.from(fileList).find((file) => file && file.type && file.type.startsWith("image/"));
        if (!imageFile) {
            setNotice("Clipboard did not contain an image.");
            return;
        }

        state.uploadingAttachment = true;
        els.pasteZone.classList.add("is-uploading");
        setNotice("Uploading screenshot...");

        uploadAttachment(imageFile)
            .then((attachment) => {
                state.draftAttachments.push(attachment);
                renderAttachmentList();
                setNotice("Screenshot attached.");
            })
            .catch((error) => {
                setNotice(error.message || "Unable to upload screenshot.");
            })
            .finally(() => {
                state.uploadingAttachment = false;
                els.pasteZone.classList.remove("is-uploading");
            });
    }

    function setNotice(message) {
        els.notice.textContent = message;
        els.notice.classList.add("is-visible");
        window.clearTimeout(setNotice.timer);
        setNotice.timer = window.setTimeout(() => {
            els.notice.classList.remove("is-visible");
        }, 2000);
    }

    function formatDate(value) {
        if (!value) {
            return "";
        }

        const [year, month, day] = value.split("-").map(Number);
        if (!year || !month || !day) {
            return value;
        }

        return new Date(year, month - 1, day).toLocaleDateString(undefined, {
            month: "short",
            day: "numeric",
            year: "numeric"
        });
    }

    root.addEventListener("click", (event) => {
        const actionTarget = event.target.closest("[data-action]");
        if (!actionTarget) {
            return;
        }

        const action = actionTarget.getAttribute("data-action");
        const itemId = actionTarget.getAttribute("data-item-id");

        if (action === "new-task") {
            openModal(null);
            return;
        }

        if (action === "close-modal") {
            closeModal();
            return;
        }

        if (action === "remove-attachment") {
            const attachmentId = Number(actionTarget.getAttribute("data-attachment-id"));
            const wasOriginal = state.originalAttachmentIds.includes(attachmentId);
            state.draftAttachments = state.draftAttachments.filter((attachment) => Number(attachment.id) !== attachmentId);
            renderAttachmentList();
            if (!wasOriginal) {
                deleteTemporaryAttachment(attachmentId).catch(() => {
                    setNotice("Unable to remove screenshot from media library.");
                });
            }
            return;
        }

        if (!itemId) {
            return;
        }

        const found = findTask(itemId);
        if (!found) {
            return;
        }

        if (action === "edit") {
            openModal(found.item);
            return;
        }

        if (action === "delete") {
            if (!window.confirm(`Delete "${found.item.title}"?`)) {
                return;
            }

            state.savingIds.add(itemId);
            render();

            request("prm_delete_item", { itemId })
                .then((data) => {
                    setSections(data.sections);
                    setNotice("Task deleted.");
                })
                .catch((error) => {
                    setNotice(error.message || "Unable to delete task.");
                })
                .finally(() => {
                    state.savingIds.delete(itemId);
                    render();
                });
            return;
        }

        if (action === "toggle") {
            state.savingIds.add(itemId);
            const nextDone = found.item.status !== "done";

            request("prm_toggle_item", { itemId, done: nextDone ? "1" : "0" })
                .then((data) => {
                    setSections(data.sections);
                    setNotice(nextDone ? "Task marked done." : "Task reopened.");
                })
                .catch((error) => {
                    setNotice(error.message || "Unable to update task.");
                })
                .finally(() => {
                    state.savingIds.delete(itemId);
                    render();
                });
        }
    });

    els.tabs.addEventListener("click", (event) => {
        const button = event.target.closest("[data-cat]");
        if (!button) {
            return;
        }

        state.currentCat = button.getAttribute("data-cat") || "all";
        render();
    });

    els.search.addEventListener("input", (event) => {
        state.search = event.target.value || "";
        renderBoard();
    });

    els.pasteZone.addEventListener("paste", (event) => {
        if (!event.clipboardData || !event.clipboardData.files) {
            return;
        }
        event.preventDefault();
        handlePastedFiles(event.clipboardData.files);
    });

    els.pasteZone.addEventListener("click", () => {
        els.pasteZone.focus();
    });

    els.taskForm.addEventListener("submit", (event) => {
        event.preventDefault();
        if (state.submitting) {
            return;
        }

        state.submitting = true;
        els.submitButton.disabled = true;

        const formData = new FormData(els.taskForm);
        const payload = {};
        for (const [key, value] of formData.entries()) {
            payload[key] = String(value).trim();
        }
        payload.attachments = JSON.stringify(state.draftAttachments);

        request("prm_save_item", payload)
            .then((data) => {
                setSections(data.sections);
                state.originalAttachmentIds = state.draftAttachments.map((attachment) => Number(attachment.id));
                closeModal(true);
                setNotice(payload.itemId ? "Task updated." : "Task created.");
            })
            .catch((error) => {
                setNotice(error.message || "Unable to save task.");
            })
            .finally(() => {
                state.submitting = false;
                els.submitButton.disabled = false;
            });
    });

    document.addEventListener("keydown", (event) => {
        if (event.key === "Escape" && state.modalOpen) {
            closeModal();
        }
    });

    render();
})();
