document.getElementById('like-btn-exp')?.addEventListener('click', async function(e) {
    e.preventDefault();
    const btn = this;
    const liked = btn.getAttribute('data-liked') === '1';
    const id = btn.getAttribute('data-id');
    const type = btn.getAttribute('data-type');
    btn.disabled = true;
    try {
        const res = await fetch(liked ? LIKE_DESTROY_ROUTE : LIKE_STORE_ROUTE, {
            method: liked ? 'DELETE' : 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': CSRF_TOKEN,
                'Accept': 'application/json',
            },
            body: JSON.stringify({ type, id })
        });
        const data = await res.json();
        if (data.success) {
            btn.setAttribute('data-liked', liked ? '0' : '1');
            document.getElementById('like-count-exp').textContent = data.count;
            document.getElementById('like-text-exp').textContent = liked ? 'Thích' : 'Đã Thích';
            btn.classList.toggle('liked', !liked);
        } else if(data.redirect) {
            window.location.href = data.redirect;
        }
    } catch (err) {
        alert('Có lỗi xảy ra!');
    }
    btn.disabled = false;
}); 