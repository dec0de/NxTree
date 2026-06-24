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
        const parts = String(markdown || '').replace(/\r\n?/g, '\n').split(/(\n[ \t]*\n(?:[ \t]*\n)*)/);
        let html = '';

        for (const part of parts) {
            if (!part) {
                continue;
            }
            if (/^\n[ \t]*\n/.test(part)) {
                const blankLines = part.split('\n').length - 2;
                for (let i = 1; i < blankLines; i++) {
                    html += '<div class="tree-markdown-spacer" aria-hidden="true"></div>';
                }
                continue;
            }
            html += renderer.render(part);
        }

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
