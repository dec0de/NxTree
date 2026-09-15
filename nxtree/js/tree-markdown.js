(function() {
    'use strict';

    function escapeHtml(value) {
        return String(value).replace(/[&<>"']/g, char => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;',
        })[char]);
    }

    function installTaskLists(md) {
        md.core.ruler.after('inline', 'tree_task_lists', state => {
            for (let i = 2; i < state.tokens.length; i++) {
                const inlineToken = state.tokens[i];
                const paragraphToken = state.tokens[i - 1];
                const listItemToken = state.tokens[i - 2];
                if (inlineToken.type !== 'inline' || paragraphToken.type !== 'paragraph_open' || listItemToken.type !== 'list_item_open') {
                    continue;
                }
                const firstChild = inlineToken.children && inlineToken.children[0];
                if (!firstChild || firstChild.type !== 'text') {
                    continue;
                }
                const match = firstChild.content.match(/^\[( |x|X)\]\s+/);
                if (!match) {
                    continue;
                }
                const checkbox = new state.Token('html_inline', '', 0);
                checkbox.content = `<input type="checkbox" disabled${match[1].toLowerCase() === 'x' ? ' checked' : ''}> `;
                firstChild.content = firstChild.content.slice(match[0].length);
                inlineToken.children.unshift(checkbox);
                listItemToken.attrJoin('class', 'task-list-item');
            }
        });
    }

    function createRenderer() {
        const markdownIt = window.markdownit || globalThis.markdownit;
        if (!markdownIt) {
            return null;
        }
        const md = markdownIt({
            html: false,
            linkify: true,
            typographer: false,
            breaks: true,
        });
        installTaskLists(md);
        return md;
    }

    const renderer = createRenderer();

    function renderWithPreservedBlankLines(markdown) {
        let html = '';
        let currentLines = [];
        let blankLines = 0;
        let fence = null;

        function renderCurrentLines() {
            if (currentLines.length > 0) {
                html += renderer.render(currentLines.join('\n'));
                currentLines = [];
            }
        }

        function renderBlankLines() {
            if (blankLines > 1) {
                for (let index = 1; index < blankLines; index++) {
                    html += '<div class="tree-markdown-spacer" aria-hidden="true"></div>';
                }
            }
            blankLines = 0;
        }

        function updateFence(line) {
            const match = line.match(/^ {0,3}(`{3,}|~{3,})/);
            if (!match) {
                return;
            }

            const marker = match[1][0];
            const length = match[1].length;
            if (fence === null) {
                fence = { marker, length };
            } else if (fence.marker === marker && length >= fence.length) {
                fence = null;
            }
        }

        const lines = String(markdown || '').replace(/\r\n?/g, '\n').split('\n');
        for (const line of lines) {
            if (fence === null && /^[ \t]*$/.test(line)) {
                blankLines++;
                continue;
            }

            renderBlankLines();
            currentLines.push(line);
            updateFence(line);
        }

        renderBlankLines();
        renderCurrentLines();

        return html;
    }

    window.TreeMarkdown = {
        render(markdown) {
            if (!renderer) {
                return `<p>${escapeHtml(markdown || '')}</p>`;
            }
            return renderWithPreservedBlankLines(markdown);
        },
    };
})();
