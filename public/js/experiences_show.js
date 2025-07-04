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
        console.log('Raw response:', text); // Log raw response
        let data;
        try {
            data = JSON.parse(text);
        } catch (err) {
            console.error('Không parse được JSON:', text);
            alert('Có lỗi xảy ra! (JSON parse error)');
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
            alert('Có lỗi xảy ra! (API error)');
            console.error('API error:', data);
        }
    } catch (err) {
        alert('Có lỗi xảy ra! (JS error)');
        console.error('JS error:', err);
    }
    btn.disabled = false;
}); 