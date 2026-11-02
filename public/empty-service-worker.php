<?php
// Set headers to prevent caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Content-Type: application/javascript");

// Generate an empty service worker that does nothing
echo "// Empty service worker - all functionality disabled for development\n";
echo "// Generated on: " . date('Y-m-d H:i:s') . "\n\n";
echo "self.addEventListener('install', event => {\n";
echo "  self.skipWaiting();\n";
echo "});\n\n";
echo "self.addEventListener('activate', event => {\n";
echo "  event.waitUntil(self.clients.claim());\n";
echo "});\n\n";
echo "self.addEventListener('fetch', event => {\n";
echo "  // Pass all requests through to the network\n";
echo "  event.respondWith(fetch(event.request));\n";
echo "});\n";
?>
