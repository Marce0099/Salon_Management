/**
 * Compact (mini) sidebar paging arrows.
 *
 * In compact mode (body.nav-sm) the top-level menu list can be taller than
 * the viewport. #sidebar-menu is never given an `overflow` rule for nav-sm
 * (that would clip the flyout submenus, which intentionally render outside
 * the 70px column via `position:absolute; left:100%`), so instead of native
 * scrolling this pages the list with `transform: translateY()`, snapped to
 * top-level <li> boundaries and clamped to the content's start/end.
 *
 * All setup below runs inside a document-ready callback registered AFTER
 * custom.js's own (custom.js binds #menu_toggle's nav-md/nav-sm class-toggle
 * handler inside its own ready callback) so that, on click, the class toggle
 * always runs before this script's recalculation.
 */
$(function () {
	var $body = $('body'),
		$sidebarMenu = $('#sidebar-menu'),
		$scrollUp = $('#sidebar-scroll-up'),
		$scrollDown = $('#sidebar-scroll-down'),
		menuEl = $sidebarMenu[0],
		ARROW_SPACE = 24;

	if (!menuEl || !$scrollUp.length || !$scrollDown.length) {
		return;
	}

	var offset = 0,
		maxOffset = 0,
		itemTops = [],
		naturalMenuTop = null;

	function isCompact() {
		return $body.hasClass('nav-sm');
	}

	function items() {
		return $sidebarMenu.find('.menu_section > ul.side-menu > li');
	}

	// offsetTop/offsetHeight are layout-only metrics, unaffected by the CSS
	// `transform` this script applies for paging (unlike getBoundingClientRect,
	// which reflects the mid-transition rendered position and would be stale
	// immediately after a transform change, since transitions animate over
	// their duration rather than jumping straight to the target value).
	function containerHeight() {
		if (naturalMenuTop === null) {
			naturalMenuTop = menuEl.getBoundingClientRect().top;
		}
		return $(window).height() - naturalMenuTop - (ARROW_SPACE * 2);
	}

	function applyOffset(px) {
		offset = px;
		$sidebarMenu.css('transform', offset ? 'translateY(-' + offset + 'px)' : '');
	}

	function collapseOpenFlyout() {
		$sidebarMenu.find('li.active, li.active-sm').removeClass('active active-sm');
		$sidebarMenu.find('li ul').slideUp(0);
	}

	// Paging never clips #sidebar-menu with `overflow` (that would also clip
	// the flyout submenus' horizontal escape, see file header), so items
	// scrolled out of the current page aren't hidden by any container box —
	// left alone, a translated-away item can still land visually behind the
	// logo/arrow band and remain clickable there. Explicitly hide+disable
	// out-of-page items instead. visibility (not display) keeps their layout
	// box intact so offsetTop stays stable for the position table.
	function updateItemVisibility($items, available) {
		$items.each(function (i) {
			var inView = itemBottom(i) > offset + 0.5 && itemTops[i] < offset + available - 0.5;
			$(this).toggleClass('sidebar-page-hidden', !inView);
		});
	}

	function recalc() {
		var $items = items();

		if (!isCompact()) {
			applyOffset(0);
			$scrollUp.removeClass('show');
			$scrollDown.removeClass('show');
			$items.removeClass('sidebar-page-hidden');
			return;
		}

		itemTops = $items.map(function () {
			return this.offsetTop;
		}).get();

		var contentHeight = menuEl.offsetHeight,
			available = containerHeight();

		maxOffset = Math.max(0, contentHeight - available);

		if (maxOffset <= 0) {
			applyOffset(0);
			$scrollUp.removeClass('show');
			$scrollDown.removeClass('show');
			$items.removeClass('sidebar-page-hidden');
			return;
		}

		if (offset > maxOffset) {
			applyOffset(maxOffset);
		}

		$scrollUp.toggleClass('show', offset > 0);
		$scrollDown.toggleClass('show', offset < maxOffset);
		updateItemVisibility($items, available);
	}

	function itemBottom(index) {
		return (index + 1 < itemTops.length) ? itemTops[index + 1] : menuEl.offsetHeight;
	}

	function lastFullyVisibleIndex(fromOffset, available) {
		var lastIdx = -1;
		for (var i = 0; i < itemTops.length; i++) {
			if (itemTops[i] < fromOffset - 0.5) {
				continue;
			}
			if (itemBottom(i) <= fromOffset + available + 0.5) {
				lastIdx = i;
			} else {
				break;
			}
		}
		return lastIdx;
	}

	function topIndexForOffset(off) {
		var idx = 0;
		for (var i = 0; i < itemTops.length; i++) {
			if (itemTops[i] <= off + 0.5) {
				idx = i;
			} else {
				break;
			}
		}
		return idx;
	}

	$scrollDown.on('click', function () {
		if (!isCompact() || offset >= maxOffset || !itemTops.length) {
			return;
		}
		collapseOpenFlyout();

		var available = containerHeight(),
			lastIdx = lastFullyVisibleIndex(offset, available),
			nextIdx = lastIdx + 1,
			newOffset = (nextIdx < itemTops.length) ? Math.min(itemTops[nextIdx], maxOffset) : maxOffset;

		applyOffset(newOffset);
		recalc();
	});

	$scrollUp.on('click', function () {
		if (!isCompact() || offset <= 0 || !itemTops.length) {
			return;
		}
		collapseOpenFlyout();

		var available = containerHeight(),
			curIdx = topIndexForOffset(offset),
			targetIdx = curIdx;

		for (var i = curIdx; i >= 0; i--) {
			if (itemTops[curIdx] - itemTops[i] <= available) {
				targetIdx = i;
			} else {
				break;
			}
		}

		applyOffset(Math.max(0, itemTops[targetIdx]));
		recalc();
	});

	$('#menu_toggle').on('click', function () {
		recalc();
	});

	$(window).smartresize(function () {
		recalc();
	});

	recalc();
});
