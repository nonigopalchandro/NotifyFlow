Contributors: nonigopalchandro
Tags: notifications, comment notifications, notification bell, user engagement, activity feed
Requires at least: 6.2
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

An extensible on-site WordPress notification center for comments, replies, mentions, animated post reactions, followed authors and topics, and configurable appearance and retention.

== Description ==

NotifyFlow provides an on-site activity feed for registered users. Always test updates on a staging copy before deploying to a production website.

Features:
* Notification bell, unread badge, responsive feed, activity filters, mark-as-read, and paginated loading.
* Notifications for approved comments and nested replies. A reply can notify both the direct parent-comment author and the post owner, without duplicate recipients. Notification cards display profile images from supported custom avatar metadata or WordPress avatar providers (including Gravatar), with initials as a resilient fallback.
* @username mentions for registered WordPress users.
* Animated post reactions: Like (👍), Haha (😂), Angry (😡), Cry (😢), and Care (🥰). Reaction emoji animate on hover/selection, and notification items display the matching emoji badge and reaction-specific message. A user can have one reaction per post and change or remove it. Older Love/Celebrate reaction records remain readable for compatibility.
* Follow authors, categories, and tags independently. Followers receive on-site notifications when a followed author publishes a post or a post is published in any category or tag they follow, even when they do not follow the author. Category and tag IDs are queried separately to avoid taxonomy ID collisions.
* Groups repeated reaction notifications for the same post and reaction type within the current feed page.
* Personal notification preferences for comments, replies, mentions, reactions, followed authors, followed topics, and custom events.
* Administrator settings for accent color, notification panel width, feature toggles, and notification retention days.
* Daily cleanup of expired notification rows when retention is set above zero. A value of zero retains notifications indefinitely.
* A developer PHP API: NotifyFlow_Plugin::create_notification( $recipient_user_id, $actor_user_id, $object_id, $post_id ).
* REST reads and updates are scoped to the current authenticated user; reaction/follow actions require login and validate targets.
* Schema upgrades create reaction and follow tables without intentionally deleting existing notification rows.

Limitations:
* Only registered, logged-in commenters generate comment/reply notifications; anonymous comment authors are ignored.
* The reaction and follow shortcodes work for logged-in users. [notifyflow_reactions] targets the current singular post/page. [notifyflow_author] displays the current post author follow button, while [notifyflow_category] displays separate follow buttons for categories assigned to the current post. Both use the accent color configured in Settings → NotifyFlow. The legacy [notifyflow_follow] shortcode continues to display both controls together.
* Browser notification permission and browser-alert UI have been removed. NotifyFlow delivers notifications inside the website; true background Web Push is not included.
* Reaction grouping applies within the current API page and groups by post, not across all pages or an unlimited time window.
* Category-follow controls are available through [notifyflow_category]. The API also accepts tag follow targets, but a tag-follow UI is not included in this build.
* The custom developer API produces a generic custom notification message and links to the supplied post. It does not store arbitrary custom message text.
* WooCommerce, BuddyPress/BuddyBoss, bbPress, email digests, WebSockets, and external push providers are not integrated in this build.
* The plugin requires a modern WordPress site with REST API and a modern browser with JavaScript enabled.
* The plugin has no mandatory third-party service. The notification feed uses the WordPress REST API and checks for new activity every five seconds while the page is visible. Depending on site avatar settings, profile images may be served by Gravatar or a custom avatar provider; those providers may receive the visitor IP address and related request metadata.
* WordPress privacy export and erasure tools include NotifyFlow user data. Deleting plugin data on uninstall is an explicit administrator setting and is disabled by default.

== Installation ==

1. Back up your site and database. Test on staging first.
2. Upload the supplied NotifyFlow ZIP under Plugins > Add New Plugin > Upload Plugin.
3. If WordPress does not offer a direct update, deactivate the old plugin and replace its files through your hosting file manager or SFTP, then reactivate. Do not drop or delete the notifyflow_notifications table.
4. Add [notifyflow_bell] to a page, template, or shortcode-capable widget. Optional: [notifyflow_bell position="left"]. On single posts, place [notifyflow_author] and [notifyflow_category] independently wherever desired. The legacy [notifyflow_follow] shortcode still renders both controls together.
5. Test with at least two registered users and a published post. Test comments, nested replies, mentions, each reaction, author/category follows, live in-page refresh, scrolling, and retention on staging.

== REST API ==

Cookie-authenticated REST requests include the standard X-WP-Nonce header.

* GET /wp-json/notifyflow/v1/notifications (supports page, per_page, and type=all|unread|comment|reply|mention|reaction_like|follow_author|follow_topic|custom)
* GET /wp-json/notifyflow/v1/unread-count
* POST /wp-json/notifyflow/v1/notifications/{id}/read
* POST /wp-json/notifyflow/v1/notifications/read-all
GET /wp-json/notifyflow/v1/preferences
POST /wp-json/notifyflow/v1/preferences (comment, reply, mention, reaction_like, follow_author, follow_topic, custom boolean fields)
GET /wp-json/notifyflow/v1/reactions?post_id=123
POST /wp-json/notifyflow/v1/reactions (post_id, reaction_type=like|haha|angry|cry|care; legacy love|celebrate values remain accepted)
POST /wp-json/notifyflow/v1/follow (target_type=user|category|tag, target_id)

== Developer integration ==

Use the PHP API from trusted server-side plugin code to create a generic custom notification linked to a published or existing post. Use a stable, unique object ID for each event to prevent duplicate rows:

`NotifyFlow_Plugin::create_notification( $recipient_user_id, get_current_user_id(), $event_id, $post_id );`

The method returns true when a row is inserted and false when it is skipped or already exists. It returns a WP_Error when required IDs are invalid. Newly inserted notifications also fire the `notifyflow_notification_created` action with recipient ID, actor ID, notification type, object ID, and post ID. Do not pass untrusted user input directly into this API.

== Privacy and data retention ==

Notification metadata, reactions, follows, and notification preferences are stored in this WordPress site. NotifyFlow does not transmit this data to an external NotifyFlow server. The optional retention setting deletes expired notification rows only; reactions and follows are retained until the associated user, post, or term is deleted, the user invokes the WordPress personal-data eraser, or the administrator explicitly enables data deletion on uninstall. Deactivation does not delete data. Review the generated privacy-policy suggestion and adapt it to the site’s actual configuration and other installed integrations.


== Changelog ==

= 1.0.0 =
* First public release.
* Updated plugin author details and release metadata.