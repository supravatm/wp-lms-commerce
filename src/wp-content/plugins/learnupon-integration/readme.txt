=== LearnUpon Integration ===
Contributors: integration-development
Tags: learnupon, woocommerce, learndash, lms, api
Requires at least: 6.4
Requires PHP: 8.1
Stable tag: 0.1.0
License: GPLv2 or later

Connects WordPress with LearnUpon using LearnUpon's REST API. Optional WooCommerce and LearnDash integration creates a LearnDash group for completed purchases of products explicitly configured for group purchases.

== Installation ==
1. Upload the ZIP from Plugins > Add New Plugin > Upload Plugin.
2. Activate LearnUpon Integration.
3. Open LearnUpon in the admin menu and configure the HTTPS portal URL and API credentials.
4. Test the connection.
5. Activate WooCommerce and LearnDash if you need the optional group-purchase flow.

== Product setup for group purchases ==
This initial release uses product metadata keys:
- _lui_group_purchase_enabled = yes
- _lui_course_id = numeric LearnUpon course ID

These can be added by a developer or product editor plugin. A product UI and seat-allocation UI are not included in this MVP. On completed orders, the plugin creates a LearnDash group and assigns the purchasing customer as group leader. LearnDash group access changes trigger a LearnUpon user sync and course enrollment.

== Security and operational notes ==
- LearnUpon API calls require HTTPS and Basic HTTP authentication using API keys.
- Never use production credentials in local development.
- API passwords are stored in WordPress options; production deployments should use environment-managed secrets and tightly restrict database/admin access.
- WP-Cron runs when site traffic triggers it. For reliable production retries, configure a real system cron to invoke wp-cron.php or use a persistent queue such as Action Scheduler.
- Validate group-purchase behavior against your installed LearnDash version and test with a sandbox portal before production.
- The plugin does not send WordPress passwords to LearnUpon. New LearnUpon users receive a generated random password; configure LearnUpon's invitation/password workflow for your portal before enabling automatic account creation.
