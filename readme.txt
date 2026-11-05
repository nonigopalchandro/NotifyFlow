=== NotifyFlow ===
Contributors: devecity
Tags: notifications, comment notifications, notification bell, user engagement, activity feed
Requires at least: 6.2
Requires PHP: 7.4
Tested up to: 7.1
Stable tag: 1.0.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

On-site notifications for comments, replies, mentions, reactions, followed authors, and topics.

== Description ==

NotifyFlow adds an in-site notification feed for registered WordPress users. The notification bell includes an unread badge, activity filters, pagination, individual and bulk mark-as-read controls, and personal notification preferences.

Features:
* Notifications for approved comments and nested replies from registered users. Replies can notify the parent-comment author and the post author without duplicate recipients.
* @username mentions for registered WordPress users in approved comments.
* Post reactions: Like (👍), Haha (😂), Angry (😡), Cry (😢), and Care (🥰). Users can change or remove their reaction, with one reaction per user and post. Older Love/Celebrate reaction records remain readable for compatibility.
* Follow authors, categories, and tags. The included follow shortcodes provide author and category controls; tag follows are supported by the REST API.
* In-page unread-count refresh every five seconds while the page is visible and online.
* Feed filters for all, unread, comments, replies, mentions, reactions, followed authors, and followed topics.
* User-specific preferences for comments, replies, mentions, reactions, followed authors, followed topics, and custom notifications.
* Administrator settings for accent color, notification panel width, reaction/follow feature toggles, notification retention, and data deletion on uninstall.
* Daily cleanup of expired notification rows when retention is set above zero. A value of zero retains notifications indefinitely; each cleanup run removes up to 5,000 expired rows.
* WordPress personal-data export and erasure integration.
* A PHP API and action hook for trusted server-side integrations that create generic custom notifications.
* Authenticated REST endpoints scope notification reads and updates to the current user. Reaction and follow actions require login and validate their targets.
* No mandatory external notification service. Profile images use compatible custom avatar metadata and WordPress avatar resolution; depending on site configuration, Gravatar or another external avatar provider may receive visitor request metadata.

Limitations:
* Only registered, logged-in commenters generate comment/reply notifications; anonymous comment authors are ignored.
* The notification bell, reactions, and follow controls require a logged-in user. The bell shortcode can be placed in a supported shortcode area. Reaction controls display on a published singular post. Author and category follow controls display on a single post view.
* [notifyflow_author] displays the current post author's follow button. [notifyflow_category] displays separate follow buttons for categories assigned to the current post. The legacy [notifyflow_follow] shortcode renders both controls together.
* The follow API accepts user, category, and tag targets, but this release does not include a tag-follow shortcode UI.
* Reaction grouping applies within the current API page and groups by post and reaction type; it does not group across all pages or an unlimited time window.
* Custom notifications use a generic message and link to the supplied published post. Arbitrary custom message text is not stored.
* Browser notification permission and browser-alert UI are not included. True background Web Push, WebSockets, email digests, WooCommerce, BuddyPress/BuddyBoss, bbPress, and external push providers are not integrated in this release.
* The plugin requires the WordPress REST API and JavaScript-enabled browser.

== Installation ==

1. Back up your site and database before installing or updating.
2. In the WordPress dashboard, go to Plugins > Add New Plugin > Upload Plugin.
3. Upload the NotifyFlow ZIP from the package's Installable Plugin folder, install it, and activate it.
4. Open Settings > NotifyFlow to configure the appearance, features, and retention settings.
5. Add [notifyflow_bell] to a page, template, or supported shortcode area. Optional: [notifyflow_bell position="left"].
6. On single posts, place [notifyflow_author] and [notifyflow_category] independently if desired. The legacy [notifyflow_follow] shortcode renders both controls together.
7. Test with at least two registered users on a staging site. Test comments, nested replies, mentions, reactions, follows, preferences, mark-as-read behavior, and retention.

== Shortcodes ==

* [notifyflow_bell] - Notification bell, unread badge, feed, filters, mark-as-read controls, and preferences. Optional attributes: label and position="left|right".
* [notifyflow_reactions] - Reaction buttons and count on a published singular post; requires a logged-in user and enabled reactions.
* [notifyflow_author] - Follow button for the current post author on a single post view.
* [notifyflow_category] - Follow buttons for categories assigned to the current post on a single post view.
* [notifyflow_follow] - Backward-compatible combined author and category follow controls.

== REST API ==

All routes use the /wp-json/notifyflow/v1/ namespace and require an authenticated WordPress user.

* GET /notifications (supports page, per_page from 1 to 50, and type=all|unread|comment|reply|mention|reaction_like|follow_author|follow_topic|custom)
* GET /unread-count
* POST /notifications/{id}/read
* POST /notifications/read-all
* GET /preferences
* POST /preferences (comment, reply, mention, reaction_like, follow_author, follow_topic, custom boolean fields)
* GET /reactions?post_id=123
* POST /reactions (post_id, reaction_type=like|haha|angry|cry|care; legacy love|celebrate values are accepted)
* POST /follow (target_type=user|category|tag, target_id)

Cookie-authenticated REST requests should include the standard X-WP-Nonce header. Notification reads and updates are scoped to the current authenticated user.

== Developer integration ==

Trusted server-side code can create a generic custom notification linked to a published post:

`NotifyFlow_Plugin::create_notification( $recipient_user_id, $actor_user_id, $event_id, $post_id );`

Use a stable, unique positive event ID to avoid duplicate event/recipient rows. Pass valid recipient and actor user IDs and a published post ID. Do not pass untrusted request values directly to this method. The method returns true when a row is inserted, false when skipped or already present, or a WP_Error for invalid values. A new row fires the `notifyflow_notification_created` action with recipient ID, actor ID, notification type, object ID, and post ID.

== Privacy and data retention ==

Notification records, reactions, follows, and notification preferences are stored in this WordPress site's database. NotifyFlow does not send activity data to an external NotifyFlow server. Depending on the site's avatar configuration, WordPress avatar APIs may use Gravatar or another external provider that can receive visitor IP and request metadata.

The retention setting deletes expired notification rows only; reactions and follows are retained until their associated user, post, or term is deleted, the WordPress personal-data eraser removes the user's data, or the administrator enables data deletion on uninstall. Deactivation does not delete data. The WordPress personal-data exporter and eraser include NotifyFlow user data. Review the generated privacy-policy content and adapt it to the site's actual configuration and installed integrations.

== Changelog ==

= 1.0.0 =
* First public release.
