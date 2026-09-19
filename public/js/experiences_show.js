// Issue #351: the four console.log/console.error calls that echoed the raw
// server body into every visitor's console are gone (a rendered exception or
// user text has no business in a production console). Errors are caught and
// reported — nothing is silently swallowed.
// Issue #414: the reports were raw alert(), a blocking semantics-free dialog;
// now they go into the server-side live region the blade ships.
function announceLikeFailure(btn) {
    var host = btn.parentElement && btn.parentElement.querySelector('.like-status');
    if (!host) return;
    host.textContent = 'Không thể thực hiện thao tác. Vui lòng thử lại.';
}

document.getElementById('like-btn-exp')?.addEventListener('click', async function(e) {
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
        let data;
        try {
            data = JSON.parse(text);
        } catch (err) {
            announceLikeFailure(btn);
            btn.disabled = false;
            return;
        }
        if (data.success) {
            btn.setAttribute('data-liked', liked ? '0' : '1');
            document.getElementById('like-count-exp').textContent = data.count;
            document.getElementById('like-text-exp').textContent = liked ? 'Thích' : 'Đã Thích';
            btn.classList.toggle('liked', !liked);
        } else if(data.redirect) {
            window.location.href = data.redirect;
        } else {
            announceLikeFailure(btn);
        }
    } catch (err) {
        announceLikeFailure(btn);
    }
    btn.disabled = false;
});
