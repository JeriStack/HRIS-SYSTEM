/**
 * NEXORA HR - Early theme initializer.
 * Load synchronously in <head> (before stylesheets paint) on standalone pages
 * such as error pages, so they honor the saved Light/Dark choice without a flash.
 * Resolution order: saved choice (nexora_theme, legacy hris_theme) -> OS preference -> light.
 */
(function () {
    var theme = 'light';
    try {
        var saved = window.localStorage.getItem('nexora_theme') || window.localStorage.getItem('hris_theme');
        if (saved === 'dark' || saved === 'light') {
            theme = saved;
        } else if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
            theme = 'dark';
        }
    } catch (e) {
        if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
            theme = 'dark';
        }
    }
    document.documentElement.setAttribute('data-theme', theme);
}());
