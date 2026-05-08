/**
 * AI assistant for Cachet (yguerdat fork).
 *
 * Adds a "Suggerer avec l'IA" button next to the title field on Incident and
 * Schedule (planned maintenance) Filament forms. The button opens a modal where
 * the operator types a brief note; the backend calls Anthropic and returns a
 * suggested {title, description}, which is then injected into the form.
 *
 * Always suggestive: the operator must validate before saving.
 */
(function () {
    'use strict';

    const cfg = window.eseancesAi || null;
    if (!cfg || !cfg.enabled) {
        return;
    }

    const STYLE_ID = 'eseances-ai-style';
    const BUTTON_ID = 'eseances-ai-button';
    const MODAL_ID = 'eseances-ai-modal';

    function injectStyles() {
        if (document.getElementById(STYLE_ID)) return;
        const style = document.createElement('style');
        style.id = STYLE_ID;
        style.textContent = [
            '.es-ai-btn { display: inline-flex; align-items: center; gap: 0.35rem;',
            '    margin-top: 0.4rem; padding: 0.35rem 0.7rem; border-radius: 0.5rem;',
            '    background: rgb(244, 63, 94); color: white; font-size: 0.8rem;',
            '    font-weight: 500; cursor: pointer; border: 0;',
            '    box-shadow: 0 1px 2px rgba(0,0,0,0.08); }',
            '.es-ai-btn:hover { background: rgb(225, 29, 72); }',
            '.es-ai-btn:disabled { opacity: 0.6; cursor: wait; }',
            '.es-ai-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.5);',
            '    z-index: 9999; display: flex; align-items: center; justify-content: center; }',
            '.es-ai-modal { background: white; color: rgb(24,24,27); border-radius: 0.75rem;',
            '    width: min(92vw, 640px); padding: 1.5rem; box-shadow: 0 25px 50px rgba(0,0,0,0.25); }',
            '.dark .es-ai-modal, [data-theme="dark"] .es-ai-modal { background: rgb(24,24,27); color: rgb(228,228,231); }',
            '.es-ai-modal h2 { font-size: 1.1rem; font-weight: 600; margin: 0 0 0.5rem; }',
            '.es-ai-modal p { font-size: 0.85rem; opacity: 0.75; margin: 0 0 1rem; }',
            '.es-ai-textarea { width: 100%; min-height: 7rem; padding: 0.75rem; border-radius: 0.5rem;',
            '    border: 1px solid rgb(212,212,216); font-size: 0.9rem; font-family: inherit;',
            '    resize: vertical; box-sizing: border-box; }',
            '.dark .es-ai-textarea { background: rgb(39,39,42); border-color: rgb(63,63,70); color: inherit; }',
            '.es-ai-actions { display: flex; gap: 0.5rem; justify-content: flex-end; margin-top: 1rem; }',
            '.es-ai-cancel { padding: 0.45rem 0.9rem; border-radius: 0.5rem; border: 1px solid rgb(212,212,216);',
            '    background: transparent; color: inherit; cursor: pointer; font-size: 0.85rem; }',
            '.es-ai-submit { padding: 0.45rem 0.9rem; border-radius: 0.5rem; border: 0;',
            '    background: rgb(4, 193, 71); color: white; cursor: pointer; font-size: 0.85rem; font-weight: 500; }',
            '.es-ai-submit:disabled { opacity: 0.6; cursor: wait; }',
            '.es-ai-error { color: rgb(220, 38, 38); font-size: 0.85rem; margin-top: 0.5rem; }',
        ].join('\n');
        document.head.appendChild(style);
    }

    function detectMode() {
        const path = window.location.pathname;
        if (path.includes('/incident-templates/')) return null;
        if (path.includes('/incidents/')) return 'incident';
        if (path.includes('/updates/')) return 'incident-update';
        if (path.includes('/schedules/')) return 'maintenance';
        return null;
    }

    function findTitleInput() {
        return document.querySelector('input[wire\\:model="data.name"]')
            || document.querySelector('input[wire\\:model\\.live="data.name"]')
            || document.querySelector('input[name="data.name"]')
            || null;
    }

    /**
     * Find the Livewire form-page component that owns the form. We anchor on
     * the title input because Filament's MarkdownEditor binds 'data.message'
     * via Alpine's $wire.$entangle (not a wire:model attribute), so a DOM
     * lookup on the message field returns nothing. Both fields live on the
     * same component anyway.
     */
    function getFormComponent() {
        const anchor = findTitleInput();
        if (!anchor || !window.Livewire) return null;
        const wireRoot = anchor.closest('[wire\\:id]');
        if (!wireRoot) return null;
        const id = wireRoot.getAttribute('wire:id');
        return window.Livewire.find(id) || null;
    }

    function setLivewireField(component, fieldName, value) {
        if (!component) return false;
        try {
            component.set('data.' + fieldName, value);
            return true;
        } catch (e) {
            console.warn('[eseances-ai] set failed for', fieldName, e);
            return false;
        }
    }

    function buildModal(mode, onSubmit) {
        const titleByMode = {
            'incident': "Suggerer le texte d'un incident",
            'incident-update': "Suggerer le texte d'une mise a jour",
            'maintenance': "Suggerer le texte d'une maintenance",
        };
        const placeholderByMode = {
            'incident': "ex. : API webhook tombe a 14h, on voit des 502, retours utilisateurs en cours",
            'incident-update': "ex. : DB recovered, latence redevenue normale, on garde sous surveillance 30 min",
            'maintenance': "ex. : migration pgsql 15->16 dimanche soir, env preprod teste OK",
        };

        const overlay = document.createElement('div');
        overlay.className = 'es-ai-overlay';
        overlay.id = MODAL_ID;

        const modal = document.createElement('div');
        modal.className = 'es-ai-modal';
        modal.setAttribute('role', 'dialog');
        modal.setAttribute('aria-modal', 'true');

        const h2 = document.createElement('h2');
        h2.textContent = titleByMode[mode] || "Suggerer du texte";
        modal.appendChild(h2);

        const intro = document.createElement('p');
        intro.textContent = "Decrivez la situation en quelques mots. Claude proposera un texte que vous pourrez relire et corriger avant publication.";
        modal.appendChild(intro);

        const textarea = document.createElement('textarea');
        textarea.className = 'es-ai-textarea';
        textarea.placeholder = placeholderByMode[mode] || '';
        textarea.setAttribute('autofocus', 'autofocus');
        modal.appendChild(textarea);

        const errorBox = document.createElement('div');
        errorBox.className = 'es-ai-error';
        errorBox.hidden = true;
        modal.appendChild(errorBox);

        const actions = document.createElement('div');
        actions.className = 'es-ai-actions';

        const cancelBtn = document.createElement('button');
        cancelBtn.type = 'button';
        cancelBtn.className = 'es-ai-cancel';
        cancelBtn.textContent = 'Annuler';
        actions.appendChild(cancelBtn);

        const submitBtn = document.createElement('button');
        submitBtn.type = 'button';
        submitBtn.className = 'es-ai-submit';
        submitBtn.textContent = 'Generer';
        actions.appendChild(submitBtn);

        modal.appendChild(actions);
        overlay.appendChild(modal);
        document.body.appendChild(overlay);

        const close = () => overlay.remove();
        cancelBtn.addEventListener('click', close);
        overlay.addEventListener('click', (e) => { if (e.target === overlay) close(); });
        const escHandler = (e) => {
            if (e.key === 'Escape') {
                close();
                document.removeEventListener('keydown', escHandler);
            }
        };
        document.addEventListener('keydown', escHandler);

        submitBtn.addEventListener('click', async () => {
            const note = textarea.value.trim();
            if (note.length < 3) {
                errorBox.textContent = "Merci d'ecrire au moins quelques mots.";
                errorBox.hidden = false;
                return;
            }
            errorBox.hidden = true;
            submitBtn.disabled = true;
            submitBtn.textContent = "Generation...";
            try {
                await onSubmit(note);
                close();
            } catch (err) {
                errorBox.textContent = err.message || "Erreur lors de la generation.";
                errorBox.hidden = false;
                submitBtn.disabled = false;
                submitBtn.textContent = "Generer";
            }
        });

        setTimeout(() => textarea.focus(), 50);
    }

    async function callBackend(mode, note) {
        const endpointKey = mode === 'incident-update' ? 'incidentUpdate' : mode;
        const url = cfg.endpoints[endpointKey];
        if (!url) throw new Error("Endpoint IA introuvable.");

        const res = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': cfg.csrf,
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body: JSON.stringify({ note: note }),
        });
        if (!res.ok) {
            const data = await res.json().catch(() => ({}));
            throw new Error(data.error || ('Erreur HTTP ' + res.status));
        }
        return await res.json();
    }

    function applySuggestion(mode, suggestion) {
        const component = getFormComponent();
        if (!component) {
            console.warn('[eseances-ai] No Livewire form component found, suggestion lost.');
            return;
        }

        if (suggestion.title && mode !== 'incident-update') {
            setLivewireField(component, 'name', suggestion.title);
        }
        if (suggestion.description) {
            setLivewireField(component, 'message', suggestion.description);
        }
    }

    function placeButton() {
        if (document.getElementById(BUTTON_ID)) return;

        const mode = detectMode();
        if (!mode) return;

        const titleInput = findTitleInput();
        if (!titleInput) return;

        const wrapper = titleInput.closest('.fi-fo-field-wrp, .fi-fo-component-ctn, [wire\\:id]') || titleInput.parentElement;
        if (!wrapper) return;

        const button = document.createElement('button');
        button.id = BUTTON_ID;
        button.type = 'button';
        button.className = 'es-ai-btn';
        button.textContent = "Suggerer avec l'IA";
        button.addEventListener('click', () => {
            buildModal(mode, async (note) => {
                const suggestion = await callBackend(mode, note);
                applySuggestion(mode, suggestion);
            });
        });

        wrapper.appendChild(button);
    }

    function watch() {
        injectStyles();
        placeButton();

        const observer = new MutationObserver(() => placeButton());
        observer.observe(document.body, { childList: true, subtree: true });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', watch);
    } else {
        watch();
    }
})();
