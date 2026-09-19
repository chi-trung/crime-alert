// Issue #398: the share popup's keyboard/focus/ARIA behaviour now lives in
// public/js/share_popup.js, which both show pages load. This file keeps the
// alert-side wiring only — the old toggleSharePopupAlert/closeSharePopupAlert
// pair did not handle Escape or focus and is gone.

// Like button (nếu có)
document.addEventListener('DOMContentLoaded', function() {
    // Issue #398: the alert share button's popup wiring. The ids are
    // alert-specific, the behaviour is shared.
    initSharePopup({
        trigger: 'share-btn-alert',
        popup: 'share-popup-alert',
        facebook: 'share-fb-alert',
        x: 'share-x-alert',
    });

    var likeBtn = document.getElementById('like-btn-alert');
    if (likeBtn) {
        likeBtn.addEventListener('click', async function(e) {
            e.preventDefault();
            const btn = this;
            const liked = btn.getAttribute('data-liked') === '1';
            const id = btn.getAttribute('data-id');
            const type = btn.getAttribute('data-type');
            btn.disabled = true;
            try {
                const isUnlike = liked;
                const url = isUnlike ? window.LIKE_DESTROY_URL : window.LIKE_STORE_URL;
                const options = {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': window.CSRF_TOKEN,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ type, id })
                };
                const res = await fetch(url, options);
                const text = await res.text();
                // Issue #351: dropped console.log('Raw response:', text) and
                // console.error(..., text) — the raw server body (which can
                // carry a rendered exception, user text or token noise) does
                // not belong in a visitor's console. The alert() that quotes
                // the fixed Vietnamese string stays, as the user-facing
                // failure notice.
                let data;
                try {
                    data = JSON.parse(text);
                } catch (err) {
                    alert('Có lỗi xảy ra! (JSON parse error)');
                    btn.disabled = false;
                    return;
                }
                if (data.success) {
                    btn.setAttribute('data-liked', liked ? '0' : '1');
                    document.getElementById('like-count-alert').textContent = data.count;
                    document.getElementById('like-text-alert').textContent = liked ? 'Thích' : 'Đã Thích';
                    btn.classList.toggle('liked', !liked);
                } else if(data.redirect) {
                    window.location.href = data.redirect;
                } else {
                    alert('Có lỗi xảy ra! (API error)');
                }
            } catch (err) {
                alert('Có lỗi xảy ra! (JS error)');
            }
            btn.disabled = false;
        });
    }
}); 