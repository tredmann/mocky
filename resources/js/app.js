import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;
window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
    wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
    enabledTransports: ['ws', 'wss'],
});

import { EditorView, basicSetup } from 'codemirror';
import { EditorState, Compartment } from '@codemirror/state';
import { json, jsonParseLinter } from '@codemirror/lang-json';
import { html } from '@codemirror/lang-html';
import { xml } from '@codemirror/lang-xml';
import { oneDark } from '@codemirror/theme-one-dark';
import { linter, lintGutter } from '@codemirror/lint';
import { keymap } from '@codemirror/view';

const languageConf = new Compartment();
const themeConf = new Compartment();

function getLanguage(contentType) {
    if (contentType.includes('json')) return json();
    if (contentType.includes('html')) return html();
    if (contentType.includes('xml')) return xml();
    return [];
}

function getLinter(contentType) {
    if (contentType.includes('json')) return linter(jsonParseLinter());
    return [];
}

function isDark() {
    return document.documentElement.classList.contains('dark');
}

function validateSyntax(contentType, value) {
    if (!value || !value.trim()) return true;

    if (contentType.includes('json')) {
        try { JSON.parse(value); return true; } catch { return false; }
    }
    if (contentType.includes('xml')) {
        const parser = new DOMParser();
        const doc = parser.parseFromString(value, 'application/xml');
        return !doc.querySelector('parsererror');
    }
    return true;
}

document.addEventListener('alpine:init', () => {
    Alpine.data('codeEditor', (initialValue, contentType, wireModel) => ({
        view: null,
        hasError: false,

        init() {
            this.setError(!validateSyntax(contentType, initialValue));

            const extensions = [
                basicSetup,
                languageConf.of(getLanguage(contentType)),
                getLinter(contentType),
                lintGutter(),
                themeConf.of(isDark() ? oneDark : []),
                EditorView.updateListener.of((update) => {
                    if (update.docChanged) {
                        const value = update.state.doc.toString();
                        this.$wire[wireModel] = value;
                        this.setError(!validateSyntax(contentType, value));
                    }
                }),
                keymap.of([{
                    key: 'Shift-Alt-f',
                    run: (view) => { this.format(); return true; }
                }]),
                EditorView.lineWrapping,
            ];

            this.view = new EditorView({
                state: EditorState.create({
                    doc: initialValue || '',
                    extensions,
                }),
                parent: this.$refs.editor,
            });

            // Watch for dark mode changes
            const observer = new MutationObserver(() => {
                this.view.dispatch({
                    effects: themeConf.reconfigure(isDark() ? oneDark : []),
                });
            });
            observer.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });

            this.$watch('$wire.' + wireModel, (value) => {
                if (value !== this.view.state.doc.toString()) {
                    this.view.dispatch({
                        changes: { from: 0, to: this.view.state.doc.length, insert: value || '' },
                    });
                }
            });

            this.$cleanup = () => {
                observer.disconnect();
                this.view.destroy();
                this.setError(false);
            };
        },

        setError(value) {
            this.hasError = value;
            this.$dispatch('editor-error', { field: wireModel, hasError: value });
        },

        updateLanguage(newContentType) {
            contentType = newContentType;
            this.view.dispatch({
                effects: languageConf.reconfigure(getLanguage(newContentType)),
            });
            const value = this.view.state.doc.toString();
            this.setError(!validateSyntax(newContentType, value));
        },

        format() {
            const doc = this.view.state.doc.toString();
            if (!doc.trim()) return;

            try {
                let formatted;
                if (contentType.includes('json')) {
                    formatted = JSON.stringify(JSON.parse(doc), null, 2);
                } else if (contentType.includes('xml') || contentType.includes('html')) {
                    // Simple XML/HTML formatting
                    formatted = doc
                        .replace(/></g, '>\n<')
                        .replace(/^\s+/gm, (match, offset, str) => {
                            // Count depth by counting open tags before this line
                            const before = str.substring(0, offset);
                            const opens = (before.match(/<[^/!?][^>]*[^/]>/g) || []).length;
                            const closes = (before.match(/<\/[^>]+>/g) || []).length;
                            return '  '.repeat(Math.max(0, opens - closes));
                        });
                } else {
                    return;
                }

                this.view.dispatch({
                    changes: { from: 0, to: this.view.state.doc.length, insert: formatted },
                });
            } catch (e) {
                // Don't format if parsing fails
            }
        },
    }));
});
