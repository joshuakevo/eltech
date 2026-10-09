// Minimal service worker so the system can be installed as an app.
// Nothing is cached: every request goes to the network, so member data never sits on the device.
self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', (event) => event.waitUntil(self.clients.claim()));
self.addEventListener('fetch', () => {});
