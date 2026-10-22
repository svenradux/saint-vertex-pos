/* Copyright © 2026 Saint Vertex. Original UI integration. */
/* Native settings lifecycle integration; no replacement Vue app or POS behavior. */
document.addEventListener('DOMContentLoaded', () => {
    const brandLogos = root => {
        const images = root instanceof HTMLImageElement ? [root] : root.querySelectorAll('img');
        for (const image of images) {
            if (/^NexoPOS$/i.test(image.alt)) image.alt = 'Vertex POS';
            if (/\/svg\/nexopos-variant-1\.svg$/.test(image.getAttribute('src') || '')) {
                image.src = '/modules/saintvertex/vertex-mark.svg';
            }
        }
    };
    brandLogos(document);
    new MutationObserver(records => {
        for (const record of records) for (const node of record.addedNodes) {
            if (node instanceof Element) brandLogos(node);
        }
    }).observe(document.body, { childList: true, subtree: true });
    nsHooks.addAction('ns-settings-saved', 'saintvertex/apply-saved-theme', ({ instance }) => {
        let effectiveTheme;
        if (instance.url.includes('ns.user-profile')) {
            effectiveTheme = document.querySelector('select[name="theme"]')?.value || VertexBranding.defaultTheme;
        } else if (instance.url.includes('general')) {
            effectiveTheme = ns.user?.attributes?.theme || document.querySelector('select[name="ns_default_theme"]')?.value;
        }
        if (['dark', 'light', 'phosphor'].includes(effectiveTheme) && effectiveTheme !== ns.theme) {
            window.location.reload();
        }
    });
});
