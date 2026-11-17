/* Adventures of the Dice — small progressive enhancements.
   Every page works without JavaScript; this only adds polish. */
document.addEventListener('DOMContentLoaded', () => {
    /* Mobile nav */
    const toggle = document.querySelector('.nav-toggle');
    const nav = document.getElementById('site-nav');
    if (toggle && nav) {
        toggle.addEventListener('click', () => {
            const open = nav.classList.toggle('is-open');
            toggle.setAttribute('aria-expanded', String(open));
        });
    }

    /* Setup: enable Player 2 only for 2-player games, swap board preview by level */
    const form = document.querySelector('[data-setup-form]');
    if (form) {
        const p2 = form.querySelector('#player2');
        const syncPlayers = () => {
            const two = form.querySelector('input[name="player_count"]:checked')?.value === '2';
            p2.disabled = !two;
            p2.required = two;
        };
        const syncPreview = () => {
            const level = form.querySelector('input[name="level"]:checked')?.value;
            form.querySelectorAll('[data-preview]').forEach(el => { el.hidden = el.dataset.preview !== level; });
        };
        form.querySelectorAll('input[name="player_count"]').forEach(r => r.addEventListener('change', syncPlayers));
        form.querySelectorAll('input[name="level"]').forEach(r => r.addEventListener('change', syncPreview));
        syncPlayers();

        /* Move focus to the error summary after a failed submit */
        document.getElementById('error-summary')?.focus();
    }

    /* Game: probability overlay toggle */
    const probToggle = document.querySelector('[data-prob-toggle]');
    const board = document.getElementById('board');
    if (probToggle && board) {
        probToggle.addEventListener('change', () => board.classList.toggle('show-prob', probToggle.checked));
    }

    /* Game: shake the die for a moment before the roll is submitted */
    const rollForm = document.querySelector('[data-roll-form]');
    if (rollForm) {
        rollForm.addEventListener('submit', e => {
            const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            if (reduce || rollForm.dataset.sent) return;
            e.preventDefault();
            rollForm.dataset.sent = '1';
            const btn = rollForm.querySelector('button');
            btn.disabled = true;
            btn.textContent = 'Rolling…';
            rollForm.closest('.roll-area')?.classList.add('is-rolling');
            setTimeout(() => rollForm.submit(), 650);
        });
    }

    /* Confirm destructive actions */
    document.querySelectorAll('form[data-confirm]').forEach(f => {
        f.addEventListener('submit', e => { if (!confirm(f.dataset.confirm)) e.preventDefault(); });
    });
});
