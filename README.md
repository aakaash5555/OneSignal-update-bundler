# OneSignal-update-bundler
it bundles up all the update and send it at once 
=== Update Bundler for OneSignal ===
Contributors: custom
Requires at least: 6.2
Requires PHP: 7.4
Stable tag: 3.0.0

Bundles WordPress post updates into one OneSignal web-push notification and stores delivery/click analytics.

== Features ==
* Automatically queues newly published posts.
* Optionally queues posts when their publication date changes.
* Per-post “Include in next bundle” checkbox for major updates.
* Duplicate-safe queue.
* Manual, hourly, or daily bundled delivery.
* Configurable singular/plural notification copy and landing URL.
* OneSignal delivery and click analytics sync.
* Notification history, CTR totals, and observed best hour/day.
* Native WordPress admin styling designed to remain usable with Night Eye.

== Installation ==
1. Upload the update-bundler-v3 folder to /wp-content/plugins/ or install the ZIP through Plugins > Add New > Upload Plugin.
2. Activate “Update Bundler for OneSignal”.
3. Open Update Bundler > Settings.
4. Enter the OneSignal App ID and REST API key from OneSignal Settings > Keys & IDs.
5. Keep the official OneSignal WordPress plugin active for browser subscription registration.
6. Disable automatic per-post sending in the official OneSignal plugin to avoid duplicate individual notifications.
7. Choose Manual, Hourly, or Daily sending.

== Important limitations ==
* WordPress Cron is traffic-driven. Exact delivery time is not guaranteed on a low-traffic site unless a real server cron calls wp-cron.php.
* OneSignal API reporting for API-sent messages is generally available for about 30 days. The plugin stores the latest synced totals locally.
* “Best hour/day” is descriptive and only appears after at least 10 confirmed deliveries in a bucket. It does not prove causation.
* The plugin targets one OneSignal segment by exact name. Default: Subscribed Users.
