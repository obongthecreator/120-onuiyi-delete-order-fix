/**
 * 120 Stand Inventory - Service Worker Registration
 * Version: 1.6.0
 *
 * Includes a recovery mechanism: if the main page fetch fails repeatedly
 * (e.g. a stuck SW), the script unregisters the SW and reloads to restore
 * normal network connectivity.
 */

if ('serviceWorker' in navigator) {
    window.addEventListener('load', function() {
        var swUrl = stand120_ajax.plugin_url + 'assets/js/sw.js';

        // ----- Recovery: detect stuck service worker -----
        // If the page took an abnormally long time or the SW is returning
        // errors, give the user a way to recover automatically.
        var SW_FAIL_KEY = 'stand120_sw_fail_count';
        var failCount = parseInt(sessionStorage.getItem(SW_FAIL_KEY) || '0', 10);

        // If we've failed 3+ consecutive page loads with a SW in control,
        // nuke the SW and all caches so the browser goes back to raw network.
        if (failCount >= 3 && navigator.serviceWorker.controller) {
            navigator.serviceWorker.getRegistrations().then(function(registrations) {
                registrations.forEach(function(reg) { reg.unregister(); });
            });
            if (window.caches) {
                caches.keys().then(function(names) {
                    names.forEach(function(name) { caches.delete(name); });
                });
            }
            sessionStorage.removeItem(SW_FAIL_KEY);
            window.location.reload();
            return;
        }

        // Reset counter on successful page load (if we got here, the page rendered)
        sessionStorage.setItem(SW_FAIL_KEY, '0');

        // ----- Normal registration -----
        navigator.serviceWorker.register(swUrl, { updateViaCache: 'none' })
            .then(function(registration) {
                // Check for updates on every page load
                registration.update();

                registration.onupdatefound = function() {
                    var installingWorker = registration.installing;
                    installingWorker.onstatechange = function() {
                        if (installingWorker.state === 'installed' && navigator.serviceWorker.controller) {
                            // New SW installed while an old one was controlling.
                            // The new SW calls skipWaiting() so it will activate immediately.
                            // Clear old caches from the main thread as a safety net.
                            if (window.caches) {
                                caches.keys().then(function(names) {
                                    names.forEach(function(name) {
                                        if (name.startsWith('stand120-')) {
                                            caches.delete(name);
                                        }
                                    });
                                });
                            }
                        }
                    };
                };
            })
            .catch(function() {
                // SW registration failed — app works fine without it
            });

        // When a new SW takes over, reload to get fresh assets
        var refreshing = false;
        navigator.serviceWorker.addEventListener('controllerchange', function() {
            if (!refreshing) {
                refreshing = true;
                window.location.reload();
            }
        });
    });
}
