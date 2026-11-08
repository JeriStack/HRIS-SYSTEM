/**
 * NEXORA HR - Client-Side Skeleton Loading System
 * Accessible, Lightweight, Dependency-Free Shimmer Placeholder Engine
 */
(function (root, factory) {
    if (typeof define === 'function' && define.amd) {
        define([], factory);
    } else if (typeof module === 'object' && module.exports) {
        module.exports = factory();
    } else {
        root.Skeleton = factory();
    }
}(typeof self !== 'undefined' ? self : this, function () {
    'use strict';

    const WIDTH_CLASSES = [
        'skeleton-w-40',
        'skeleton-w-75',
        'skeleton-w-60',
        'skeleton-w-50',
        'skeleton-w-30',
        'skeleton-w-70'
    ];

    const BAR_HEIGHTS = [45, 75, 30, 85, 60, 90, 50, 68, 40, 80];

    const templates = {
        /**
         * Generate a table skeleton
         */
        table(options = {}) {
            const cols = Math.max(1, parseInt(options.cols || options.columns, 10) || 5);
            const rows = Math.max(1, parseInt(options.rows, 10) || 5);
            const hasToolbar = Boolean(options.hasToolbar);
            const standalone = Boolean(options.standalone);

            let headerCells = '';
            for (let c = 0; c < cols; c++) {
                const w = WIDTH_CLASSES[c % WIDTH_CLASSES.length];
                headerCells += `<th><div class="skeleton-text ${w}" style="height: 0.8125rem; margin-bottom: 0;"></div></th>`;
            }

            let rowHtml = '';
            for (let r = 0; r < rows; r++) {
                let cells = '';
                for (let c = 0; c < cols; c++) {
                    const w = WIDTH_CLASSES[(c + r) % WIDTH_CLASSES.length];
                    cells += `<td><div class="skeleton-text ${w}" style="margin-bottom: 0;"></div></td>`;
                }
                rowHtml += `<tr>${cells}</tr>`;
            }

            const tableMarkup = `
                <table class="skeleton-table" aria-hidden="true">
                    <thead><tr>${headerCells}</tr></thead>
                    <tbody>${rowHtml}</tbody>
                </table>
            `;

            if (standalone) {
                return tableMarkup;
            }

            const toolbarHtml = hasToolbar ? `
                <div class="skeleton-table-toolbar">
                    <div class="skeleton-input" style="max-width: 280px; height: 36px;"></div>
                    <div style="display: flex; gap: 0.5rem;">
                        <div class="skeleton-button" style="width: 100px; height: 36px;"></div>
                        <div class="skeleton-button" style="width: 80px; height: 36px;"></div>
                    </div>
                </div>
            ` : '';

            return `
                <div class="skeleton-table-wrapper" aria-hidden="true">
                    ${toolbarHtml}
                    <div style="overflow-x: auto;">
                        ${tableMarkup}
                    </div>
                </div>
            `;
        },

        /**
         * Generate metric / stat card skeletons
         */
        statCards(options = {}) {
            const count = Math.max(1, parseInt(options.count, 10) || 4);
            const standalone = Boolean(options.standalone);

            let cardsHtml = '';
            for (let i = 0; i < count; i++) {
                cardsHtml += `
                    <div class="skeleton-stat-card" aria-hidden="true">
                        <div class="skeleton-stat-info">
                            <div class="skeleton-heading-sm skeleton-w-50" style="margin-bottom: 0.5rem;"></div>
                            <div class="skeleton-heading-lg skeleton-w-40" style="margin-bottom: 0.5rem;"></div>
                            <div class="skeleton-text skeleton-w-70"></div>
                        </div>
                        <div class="skeleton-icon"></div>
                    </div>
                `;
            }

            return standalone ? cardsHtml : `<div class="skeleton-stat-grid" aria-hidden="true">${cardsHtml}</div>`;
        },

        /**
         * Generate a content card skeleton
         */
        card(options = {}) {
            const lines = Math.max(1, parseInt(options.lines, 10) || 3);
            const hasHeader = options.hasHeader !== false;
            const hasFooter = Boolean(options.hasFooter);
            const hasAvatar = Boolean(options.hasAvatar);

            const headerHtml = hasHeader ? `
                <div class="skeleton-card-header">
                    <div style="display: flex; align-items: center; gap: 0.75rem; flex: 1;">
                        ${hasAvatar ? '<div class="skeleton-avatar"></div>' : ''}
                        <div class="skeleton-heading" style="width: 45%; margin-bottom: 0;"></div>
                    </div>
                    <div class="skeleton-badge"></div>
                </div>
            ` : '';

            let bodyHtml = '';
            for (let i = 0; i < lines; i++) {
                const w = WIDTH_CLASSES[i % WIDTH_CLASSES.length];
                bodyHtml += `<div class="skeleton-text ${w}"></div>`;
            }

            const footerHtml = hasFooter ? `
                <div class="skeleton-card-footer">
                    <div class="skeleton-button" style="width: 90px; height: 34px;"></div>
                    <div class="skeleton-button" style="width: 110px; height: 34px;"></div>
                </div>
            ` : '';

            return `
                <div class="skeleton-card" aria-hidden="true">
                    ${headerHtml}
                    <div class="skeleton-card-body">${bodyHtml}</div>
                    ${footerHtml}
                </div>
            `;
        },

        /**
         * Generate a list skeleton
         */
        list(options = {}) {
            const count = Math.max(1, parseInt(options.count, 10) || 5);
            const hasAvatar = options.hasAvatar !== false;
            const hasBadge = options.hasBadge !== false;

            let items = '';
            for (let i = 0; i < count; i++) {
                items += `
                    <div class="skeleton-list-item">
                        ${hasAvatar ? '<div class="skeleton-avatar"></div>' : ''}
                        <div class="skeleton-list-content">
                            <div class="skeleton-heading-sm skeleton-w-50" style="margin-bottom: 0.35rem;"></div>
                            <div class="skeleton-text skeleton-w-75" style="margin-bottom: 0;"></div>
                        </div>
                        ${hasBadge ? '<div class="skeleton-badge"></div>' : ''}
                    </div>
                `;
            }

            return `<div class="skeleton-list" aria-hidden="true">${items}</div>`;
        },

        /**
         * Generate a chart placeholder skeleton
         */
        chart(options = {}) {
            const bars = Math.max(3, parseInt(options.bars, 10) || 7);
            const hasHeader = options.hasHeader !== false;
            const height = Math.max(120, parseInt(options.height, 10) || 220);

            const headerHtml = hasHeader ? `
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
                    <div>
                        <div class="skeleton-heading" style="width: 160px; margin-bottom: 0.35rem;"></div>
                        <div class="skeleton-text skeleton-w-75" style="width: 220px; height: 0.75rem; margin-bottom: 0;"></div>
                    </div>
                    <div style="display: flex; gap: 0.5rem;">
                        <div class="skeleton-badge" style="width: 60px;"></div>
                        <div class="skeleton-badge" style="width: 60px;"></div>
                    </div>
                </div>
            ` : '';

            let barsHtml = '';
            for (let i = 0; i < bars; i++) {
                const h = BAR_HEIGHTS[i % BAR_HEIGHTS.length];
                barsHtml += `
                    <div class="skeleton-chart-bar-wrap">
                        <div class="skeleton-bar" style="height: ${h}%;"></div>
                        <div class="skeleton-text" style="width: 28px; height: 0.65rem; margin-bottom: 0;"></div>
                    </div>
                `;
            }

            return `
                <div class="skeleton-chart-container" aria-hidden="true">
                    ${headerHtml}
                    <div class="skeleton-chart-bars" style="height: ${height}px;">
                        ${barsHtml}
                    </div>
                </div>
            `;
        },

        /**
         * Generate a profile section skeleton
         */
        profile(options = {}) {
            const fields = Math.max(2, parseInt(options.fields, 10) || 6);

            let fieldsHtml = '';
            for (let i = 0; i < fields; i++) {
                fieldsHtml += `
                    <div>
                        <div class="skeleton-heading-sm skeleton-w-40" style="height: 0.75rem; margin-bottom: 0.35rem;"></div>
                        <div class="skeleton-text skeleton-w-75"></div>
                    </div>
                `;
            }

            return `
                <div aria-hidden="true">
                    <div class="skeleton-profile-header">
                        <div class="skeleton-avatar skeleton-avatar-xl"></div>
                        <div style="flex: 1; min-width: 0;">
                            <div class="skeleton-heading" style="width: 220px; margin-bottom: 0.5rem;"></div>
                            <div class="skeleton-text skeleton-w-40" style="margin-bottom: 0.5rem;"></div>
                            <div style="display: flex; gap: 0.5rem;">
                                <div class="skeleton-badge"></div>
                                <div class="skeleton-badge" style="width: 90px;"></div>
                            </div>
                        </div>
                        <div class="skeleton-button" style="width: 130px;"></div>
                    </div>

                    <div class="skeleton-card">
                        <div class="skeleton-card-header">
                            <div class="skeleton-heading" style="width: 180px; margin-bottom: 0;"></div>
                        </div>
                        <div class="skeleton-card-body">
                            <div class="skeleton-profile-grid">
                                ${fieldsHtml}
                            </div>
                        </div>
                    </div>
                </div>
            `;
        },

        /**
         * Generate a form skeleton
         */
        form(options = {}) {
            const fields = Math.max(1, parseInt(options.fields, 10) || 4);
            const columns = Math.max(1, parseInt(options.columns, 10) || 1);
            const hasButton = options.hasButton !== false;

            let fieldsHtml = '';
            for (let i = 0; i < fields; i++) {
                fieldsHtml += `
                    <div class="skeleton-form-group">
                        <div class="skeleton-form-label"></div>
                        <div class="skeleton-input"></div>
                    </div>
                `;
            }

            const buttonHtml = hasButton ? `
                <div style="display: flex; gap: 0.75rem; margin-top: 1.5rem; justify-content: flex-end;">
                    <div class="skeleton-button" style="width: 90px;"></div>
                    <div class="skeleton-button" style="width: 130px;"></div>
                </div>
            ` : '';

            return `
                <div class="skeleton-card" aria-hidden="true">
                    <div class="skeleton-card-body">
                        <div style="display: grid; grid-template-columns: repeat(${columns}, 1fr); gap: 1.25rem;">
                            ${fieldsHtml}
                        </div>
                        ${buttonHtml}
                    </div>
                </div>
            `;
        }
    };

    /**
     * Resolve DOM target element
     */
    function resolveElement(target) {
        if (!target) return null;
        if (typeof target === 'string') {
            return document.querySelector(target);
        }
        if (target instanceof HTMLElement) {
            return target;
        }
        return null;
    }

    /**
     * Display a skeleton inside the target container
     *
     * @param {string|HTMLElement} target
     * @param {string} templateName Template name or raw HTML string
     * @param {Object} options Configuration and accessibility options
     */
    function show(target, templateName, options = {}) {
        const el = resolveElement(target);
        if (!el) return null;

        // Preserve existing HTML if requested and not already preserved
        if (options.preserve !== false && !el.dataset.skeletonPreserved) {
            el._skeletonSavedHtml = el.innerHTML;
            el.dataset.skeletonPreserved = 'true';
        }

        const loadingText = options.loadingText || 'Loading content...';
        let contentHtml = '';

        if (typeof templateName === 'string' && typeof templates[templateName] === 'function') {
            contentHtml = templates[templateName](options);
        } else if (typeof templateName === 'string') {
            contentHtml = templateName;
        }

        el.setAttribute('aria-busy', 'true');
        el.dataset.loading = 'true';

        if (options.raw) {
            el.innerHTML = contentHtml;
        } else {
            el.innerHTML = `
                <div class="skeleton-container" role="status" aria-busy="true">
                    <span class="sr-only">${loadingText}</span>
                    ${contentHtml}
                </div>
            `;
        }

        el.dispatchEvent(new CustomEvent('skeleton:shown', { bubbles: true, detail: { template: templateName } }));
        return el;
    }

    /**
     * Remove the skeleton and display the real content
     *
     * @param {string|HTMLElement} target
     * @param {string|null} replacementContent Optional real content HTML
     */
    function hide(target, replacementContent = null) {
        const el = resolveElement(target);
        if (!el) return null;

        el.removeAttribute('aria-busy');
        delete el.dataset.loading;

        if (replacementContent !== null) {
            el.innerHTML = replacementContent;
            delete el._skeletonSavedHtml;
            delete el.dataset.skeletonPreserved;
        } else if (el.dataset.skeletonPreserved && typeof el._skeletonSavedHtml === 'string') {
            el.innerHTML = el._skeletonSavedHtml;
            delete el._skeletonSavedHtml;
            delete el.dataset.skeletonPreserved;
        }

        el.dispatchEvent(new CustomEvent('skeleton:hidden', { bubbles: true }));
        return el;
    }

    /**
     * Wrap an async task with automated skeleton showing and hiding
     *
     * @param {string|HTMLElement} target
     * @param {Function} asyncFn
     * @param {string} templateName
     * @param {Object} options
     * @returns {Promise<any>}
     */
    async function wrapAsync(target, asyncFn, templateName, options = {}) {
        show(target, templateName, options);
        try {
            const result = await asyncFn();
            hide(target);
            return result;
        } catch (err) {
            hide(target);
            throw err;
        }
    }

    return {
        templates,
        show,
        hide,
        wrapAsync
    };
}));

