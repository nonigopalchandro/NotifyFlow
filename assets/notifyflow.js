(function () {
	'use strict';
	const roots = document.querySelectorAll('[data-notifyflow]');
	if (!roots.length) return;

	roots.forEach(function (root) {
		let config = {};
		try { config = JSON.parse(root.getAttribute('data-config') || '{}'); } catch (error) { return; }
		const trigger = root.querySelector('.notifyflow__trigger');
		const panel = root.querySelector('[data-panel]');
		const feed = root.querySelector('[data-feed]');
		const badge = root.querySelector('[data-unread-count]');
		const markAll = root.querySelector('[data-mark-all]');
		const closeButton = root.querySelector('[data-close]');
		const status = root.querySelector('[data-feed-status]');
		const toast = root.querySelector('[data-toast]');
		const filters = root.querySelector('[data-filters]');
		const loadMoreButton = root.querySelector('[data-load-more]');
		const preferencesPanel = root.querySelector('[data-preferences]');
		const preferencesToggle = root.querySelector('[data-preferences-toggle]');
		const preferenceStatus = root.querySelector('[data-pref-status]');
		const strings = config.strings || {};
		let isOpen = false, loading = false, lastUnread = 0, pollTimer = null, toastTimer = null;
		let currentFilter = 'all', currentPage = 1, hasMore = false, loadedItems = [];

		function api(path, options) {
			const opts = Object.assign({}, options || {});
			opts.credentials = 'same-origin';
			opts.headers = Object.assign({ 'X-WP-Nonce': config.nonce, 'Accept': 'application/json' }, opts.headers || {});
			if (opts.body && !opts.headers['Content-Type']) opts.headers['Content-Type'] = 'application/json';
			return fetch((config.restRoot || '') + path, opts).then(function (response) {
				if (!response.ok) throw new Error('Request failed: ' + response.status);
				return response.json();
			});
		}
		function element(tag, className, text) {
			const node = document.createElement(tag);
			if (className) node.className = className;
			if (typeof text === 'string') node.textContent = text;
			return node;
		}
		function setToast(message) {
			toast.textContent = message; toast.hidden = false;
			if (toastTimer) window.clearTimeout(toastTimer);
			toastTimer = window.setTimeout(function () { toast.hidden = true; }, 3500);
		}
		function updateBadge(count) {
			const safeCount = Math.max(0, Number(count) || 0);
			badge.textContent = safeCount > 99 ? '99+' : String(safeCount);
			badge.hidden = safeCount === 0;
			trigger.classList.toggle('has-unread', safeCount > 0);
			trigger.setAttribute('aria-label', (strings.title || 'Notifications') + (safeCount ? ', ' + safeCount + ' unread' : ''));
			lastUnread = safeCount;
		}
		function relativeTime(value) {
			const date = new Date(value);
			if (Number.isNaN(date.getTime())) return '';
			const seconds = Math.max(0, Math.floor((Date.now() - date.getTime()) / 1000));
			if (seconds < 60) return strings.justNow || 'Just now';
			if (seconds < 3600) return (strings.minutesAgo || '%d m ago').replace('%d', String(Math.floor(seconds / 60)));
			if (seconds < 86400) return (strings.hoursAgo || '%d h ago').replace('%d', String(Math.floor(seconds / 3600)));
			if (seconds < 604800) return (strings.daysAgo || '%d d ago').replace('%d', String(Math.floor(seconds / 86400)));
			return date.toLocaleDateString();
		}
		function renderItems(items, append) {
			if (!append) feed.replaceChildren();
			if (!items || !items.length) {
				if (!append) feed.appendChild(element('div', 'notifyflow__state', strings.empty || 'You’re all caught up.'));
				return;
			}
			items.forEach(function (item) {
				const href = item.url || '#';
				const card = element('a', 'notifyflow__item' + (item.is_read ? '' : ' is-unread'));
				card.href = href;
				card.setAttribute('aria-label', (item.message || strings.newActivity || 'New activity') + (item.post_title ? ' ' + item.post_title : ''));
				card.addEventListener('click', function (event) {
					if (item.is_read || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
					event.preventDefault();
					const destination = card.href;
					let navigated = false;
					const go = function () { if (!navigated) { navigated = true; window.location.assign(destination); } };
					const fallback = window.setTimeout(go, 450);
					Promise.all((item.notification_ids || [item.id]).map(function (notificationId) { return api('notifications/' + encodeURIComponent(notificationId) + '/read', { method: 'POST', body: '{}' }); }))
						.then(function () { item.is_read = true; card.classList.remove('is-unread'); updateBadge(Math.max(0, lastUnread - (item.notification_ids || [item.id]).length)); })
						.catch(function () {})
						.finally(function () { window.clearTimeout(fallback); go(); });
				});
				const avatarWrap = element('div', 'notifyflow__avatar-wrap');
				if (item.actor_avatar) {
					const avatar = element('img', 'notifyflow__avatar');
					avatar.src = item.actor_avatar; avatar.alt = ''; avatar.loading = 'lazy'; avatar.referrerPolicy = 'no-referrer';
					avatar.addEventListener('error', function () {
						if (avatar.parentNode) avatar.parentNode.replaceChild(element('span', 'notifyflow__avatar-fallback', (item.actor_name || '?').slice(0, 1).toUpperCase()), avatar);
					}, { once: true });
					avatarWrap.appendChild(avatar);
				} else avatarWrap.appendChild(element('span', 'notifyflow__avatar-fallback', (item.actor_name || '?').slice(0, 1).toUpperCase()));
				if (item.reaction_emoji) {
					const reactionBadge = element('span', 'notifyflow__reaction-badge', item.reaction_emoji);
					reactionBadge.setAttribute('role', 'img');
					reactionBadge.setAttribute('aria-label', (item.reaction_label || strings.reaction || 'Reaction') + ' reaction');
					reactionBadge.title = (item.reaction_label || strings.reaction || 'Reaction') + ' reaction';
					avatarWrap.appendChild(reactionBadge);
				}
				const content = element('div', 'notifyflow__item-content');
				content.appendChild(element('p', 'notifyflow__message', item.message || 'New activity'));
				const meta = element('div', 'notifyflow__meta');
				meta.appendChild(element('time', '', relativeTime(item.created_at)));
				if (item.post_title) meta.appendChild(element('span', 'notifyflow__post-title', item.post_title));
				content.appendChild(meta);
				card.appendChild(avatarWrap); card.appendChild(content);
				if (!item.is_read) card.appendChild(element('span', 'notifyflow__unread-dot'));
				card.appendChild(element('span', 'notifyflow__open-link', strings.openDiscussion || strings.viewPost || 'Open discussion'));
				feed.appendChild(card);
			});
		}
		function loadNotifications(showLoading, append) {
			if (loading) return Promise.resolve();
			loading = true;
			const previousScrollTop = feed.scrollTop;
			if (!append) { currentPage = 1; loadedItems = []; if (showLoading) feed.replaceChildren(element('div', 'notifyflow__state', strings.loading || 'Loading notifications…')); }
			const page = append ? currentPage + 1 : 1;
			const params = new URLSearchParams({ per_page: '20', page: String(page), type: currentFilter });
			return Promise.all([api('notifications?' + params.toString()), api('unread-count')]).then(function (results) {
				const data = results[0] || {}, unread = results[1] || {};
				updateBadge(unread.unread_count);
				const items = data.items || [];
				loadedItems = append ? loadedItems.concat(items) : items;
				currentPage = page; hasMore = !!data.has_more;
				renderItems(items, !!append);
				loadMoreButton.hidden = !hasMore;
				status.textContent = (strings.unreadCount || '%d unread').replace('%d', String(unread.unread_count || 0));
				if (!append && !showLoading) feed.scrollTop = previousScrollTop;
			}).catch(function () {
				if (showLoading || !feed.children.length) feed.replaceChildren(element('div', 'notifyflow__state notifyflow__state--error', strings.couldNotLoad || strings.error || 'Could not load notifications. Please try again.'));
			}).finally(function () { loading = false; });
		}
		function markAllRead() {
			markAll.disabled = true;
			api('notifications/read-all', { method: 'POST', body: '{}' }).then(function () {
				updateBadge(0); return loadNotifications(false, false);
			}).catch(function () { setToast(strings.couldNotUpdate || strings.error || 'Could not update notifications.'); })
			.finally(function () { markAll.disabled = false; });
		}
		function loadPreferences() {
			api('preferences').then(function (prefs) {
				root.querySelectorAll('[data-pref]').forEach(function (input) { input.checked = prefs[input.getAttribute('data-pref')] !== false; });
			}).catch(function () { preferenceStatus.textContent = strings.couldNotLoadPreferences || 'Could not load preferences.'; });
		}
		function savePreferences() {
			const values = {};
			root.querySelectorAll('[data-pref]').forEach(function (input) { values[input.getAttribute('data-pref')] = input.checked; });
			preferenceStatus.textContent = strings.saving || 'Saving…';
			api('preferences', { method: 'POST', body: JSON.stringify(values) }).then(function () {
				preferenceStatus.textContent = strings.preferencesSaved || 'Preferences saved.';
			}).catch(function () { preferenceStatus.textContent = strings.couldNotSavePreferences || 'Could not save preferences. Please try again.'; });
		}
		function openPanel() {
			isOpen = true; panel.hidden = false; trigger.setAttribute('aria-expanded', 'true'); root.classList.add('is-open');
			loadNotifications(true, false); closeButton.focus();
		}
		function closePanel() {
			isOpen = false; panel.hidden = true; trigger.setAttribute('aria-expanded', 'false'); root.classList.remove('is-open'); trigger.focus();
		}
		document.addEventListener('click', function (event) {
			const reactionButton = event.target.closest('[data-nf-reaction] [data-reaction]');
			if (reactionButton) {
				const wrapper = reactionButton.closest('[data-nf-reaction]');
				reactionButton.disabled = true;
				api('reactions', { method: 'POST', body: JSON.stringify({ post_id: Number(wrapper.getAttribute('data-post-id')), reaction_type: reactionButton.getAttribute('data-reaction') }) }).then(function (data) {
					wrapper.querySelector('[data-reaction-count]').textContent = String(data.count || 0);
					wrapper.querySelectorAll('[data-reaction]').forEach(function (button) {
						const selected = data.mine === button.getAttribute('data-reaction');
						button.setAttribute('aria-pressed', selected ? 'true' : 'false');
						button.classList.toggle('is-selected', selected);
					});
					if (data.mine) {
						reactionButton.classList.remove('is-animating');
						void reactionButton.offsetWidth;
						reactionButton.classList.add('is-animating');
						window.setTimeout(function () { reactionButton.classList.remove('is-animating'); }, 700);
					}
					const status = wrapper.querySelector('[data-reaction-status]'); if (status) status.textContent = data.mine ? ' ' + (strings.reactionSaved || 'Reaction saved.') : ' ' + (strings.reactionRemoved || 'Reaction removed.');
				}).catch(function () { setToast(strings.couldNotSaveReaction || 'Could not save your reaction. Please try again.'); }).finally(function () { reactionButton.disabled = false; });
				return;
			}
			const followButton = event.target.closest('[data-nf-follow] [data-follow-type]');
			if (followButton) {
				const wrapper = followButton.closest('[data-nf-follow]');
				followButton.disabled = true;
				api('follow', { method: 'POST', body: JSON.stringify({ target_type: followButton.getAttribute('data-follow-type'), target_id: Number(followButton.getAttribute('data-follow-id')) }) }).then(function (data) {
					followButton.setAttribute('aria-pressed', data.following ? 'true' : 'false');
					const followLabel = followButton.getAttribute('data-follow-label') || 'Follow';
					const followingLabel = followButton.getAttribute('data-following-label') || 'Following';
					followButton.textContent = data.following ? followingLabel : followLabel;
					const categoryName = followButton.closest('.notifyflow-follow__category')?.querySelector('.notifyflow-follow__category-name')?.textContent;
					if (categoryName) followButton.setAttribute('aria-label', (data.following ? followingLabel : followLabel) + ': ' + categoryName);
					const status = wrapper.querySelector('[data-follow-status]'); if (status) status.textContent = data.following ? ' ' + (strings.followSaved || 'Follow saved.') : ' ' + (strings.followRemoved || 'Follow removed.');
				}).catch(function () { const status = wrapper.querySelector('[data-follow-status]'); if (status) status.textContent = ' ' + (strings.couldNotUpdateFollow || 'Could not update follow preference.'); }).finally(function () { followButton.disabled = false; });
			}
		});
		trigger.addEventListener('click', function () { if (isOpen) closePanel(); else openPanel(); });
		closeButton.addEventListener('click', closePanel);
		markAll.addEventListener('click', markAllRead);
		loadMoreButton.addEventListener('click', function () { if (hasMore) loadNotifications(false, true); });
		// Drag-to-scroll on desktop without pointer capture. Pointer capture retargets
		// clicks to the nav itself in some browsers, preventing filter buttons firing.
		// Touch devices keep native horizontal swipe scrolling.
		(function enableFilterDragScroll() {
			let pointerStartX = 0;
			let initialScrollLeft = 0;
			let dragging = false;
			let dragged = false;
			let activePointerId = null;
			filters.addEventListener('pointerdown', function (event) {
				if (event.pointerType === 'touch' || event.button !== 0) return;
				pointerStartX = event.clientX;
				initialScrollLeft = filters.scrollLeft;
				dragging = true;
				dragged = false;
				activePointerId = event.pointerId;
			});
			document.addEventListener('pointermove', function (event) {
				if (!dragging || event.pointerId !== activePointerId) return;
				const delta = event.clientX - pointerStartX;
				if (Math.abs(delta) > 5) { dragged = true; filters.classList.add('is-dragging'); }
				if (dragged) { filters.scrollLeft = initialScrollLeft - delta; event.preventDefault(); }
			}, { passive: false });
			function stopDrag() {
				if (!dragging) return;
				dragging = false;
				activePointerId = null;
				filters.classList.remove('is-dragging');
				if (dragged) {
					filters.dataset.dragged = '1';
					window.setTimeout(function () { delete filters.dataset.dragged; }, 180);
				}
			}
			document.addEventListener('pointerup', stopDrag);
			document.addEventListener('pointercancel', stopDrag);
			filters.addEventListener('click', function (event) {
				if (filters.dataset.dragged === '1') { event.preventDefault(); event.stopPropagation(); }
			}, true);
		})();

		filters.querySelectorAll('[data-filter]').forEach(function (node) {
			node.setAttribute('aria-pressed', node.classList.contains('is-active') ? 'true' : 'false');
		});
		filters.addEventListener('click', function (event) {
			const button = event.target.closest ? event.target.closest('[data-filter]') : null;
			if (!button || !filters.contains(button) || button.disabled) return;
			const nextFilter = button.getAttribute('data-filter') || 'all';
			if (nextFilter === currentFilter) return;
			currentFilter = nextFilter;
			filters.querySelectorAll('[data-filter]').forEach(function (node) {
				const active = node === button;
				node.classList.toggle('is-active', active);
				node.setAttribute('aria-pressed', active ? 'true' : 'false');
			});
			loadNotifications(true, false);
		});
		preferencesToggle.addEventListener('click', function () {
			preferencesPanel.hidden = !preferencesPanel.hidden;
			if (!preferencesPanel.hidden) loadPreferences();
		});
		preferencesPanel.addEventListener('change', function (event) { if (event.target.matches('[data-pref]')) savePreferences(); });
		document.addEventListener('click', function (event) {
			if (isOpen && !root.contains(event.target)) { panel.hidden = true; trigger.setAttribute('aria-expanded', 'false'); root.classList.remove('is-open'); isOpen = false; }
		});
		document.addEventListener('keydown', function (event) { if (event.key === 'Escape' && isOpen) closePanel(); });
		function poll() {
			if (document.visibilityState !== 'visible' || !navigator.onLine) return;
			api('unread-count').then(function (data) {
				const count = Number(data.unread_count) || 0;
				const previousCount = lastUnread;
				updateBadge(count);
				if (isOpen && count !== previousCount) loadNotifications(false, false);
			}).catch(function () {});
		}
		loadNotifications(false, false);
		// Near-real-time in-page updates; no reload, browser permission, or external push service.
		pollTimer = window.setInterval(poll, Math.max(5000, Number(config.pollMs) || 5000));
		document.addEventListener('visibilitychange', function () { if (document.visibilityState === 'visible') poll(); });
		window.addEventListener('online', poll);
		window.addEventListener('pagehide', function () { if (pollTimer) window.clearInterval(pollTimer); });
	});
})();
