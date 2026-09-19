// Issue #398: the share popup existed twice — once here (as
// toggleSharePopupAlert) and once inline in experiences/show.blade.php
// (toggleSharePopupExp). They were near-identical and neither was usable from a
// keyboard: no Escape handler, no focus move, and no ARIA on the trigger or the
// popup. This is the single implementation both pages load.
//
// Behaviour:
//   - the trigger carries aria-expanded/aria-haspopup, so a screen reader
//     announces it as a button that opens something;
//   - the popup carries role="dialog" + aria-label, so it is announced as a
//     named container rather than a pile of divs;
//   - opening moves focus to the first share link, closing returns it to the
//     trigger (keyboard users are never stranded);
//   - Escape closes the popup (the outside-click path is kept).

/**
 * Wire one share button/popup pair.
 *
 * @param {Object}  ids
 * @param {string}  ids.trigger  the share button id
 * @param {string}  ids.popup    the popup container id
 * @param {string}  ids.facebook the Facebook share link id
 * @param {string}  ids.x        the X share link id
 */
function initSharePopup(ids) {
    var trigger = document.getElementById(ids.trigger);
    var popup = document.getElementById(ids.popup);
    if (!trigger || !popup) {
        return;
    }

    var facebook = document.getElementById(ids.facebook);
    var x = document.getElementById(ids.x);
    var lastFocused = null;

    // Issue #398: markup is the contract the test asserts against, but the
    // attributes are also set here so a page that ships without them still
    // ends up correct once this script runs.
    trigger.setAttribute('aria-haspopup', 'true');
    trigger.setAttribute('aria-expanded', 'false');
    popup.setAttribute('role', 'dialog');
    popup.setAttribute('aria-label', 'Chia sẻ');

    function shareUrls() {
        var url = encodeURIComponent(window.location.href);
        if (facebook) {
            facebook.href = 'https://www.facebook.com/sharer/sharer.php?u=' + url;
        }
        if (x) {
            x.href = 'https://twitter.com/intent/tweet?url=' + url;
        }
    }

    function isOpen() {
        return popup.style.display === 'block';
    }

    function close(returnFocus) {
        popup.style.display = 'none';
        trigger.setAttribute('aria-expanded', 'false');
        document.removeEventListener('click', onOutsideClick);
        document.removeEventListener('keydown', onEscape);
        if (returnFocus && lastFocused) {
            lastFocused.focus();
        }
    }

    function open() {
        shareUrls();
        lastFocused = document.activeElement;
        popup.style.display = 'block';
        trigger.setAttribute('aria-expanded', 'true');
        // Move focus to the first share link so keyboard users land inside the
        // dialog; Escape below returns them to the trigger.
        var first = facebook || popup.querySelector('a, button');
        if (first) {
            first.focus();
        }
        document.addEventListener('click', onOutsideClick);
        document.addEventListener('keydown', onEscape);
    }

    function onOutsideClick(e) {
        if (!popup.contains(e.target) && e.target !== trigger) {
            close(false);
        }
    }

    function onEscape(e) {
        if (e.key === 'Escape' || e.key === 'Esc') {
            e.preventDefault();
            close(true);
        }
    }

    trigger.addEventListener('click', function (e) {
        e.stopPropagation();
        if (isOpen()) {
            close(false);
        } else {
            open();
        }
    });
}
