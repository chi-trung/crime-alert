// Chia sẻ popup
function toggleSharePopupAlert(e) {
    e.stopPropagation();
    var popup = document.getElementById('share-popup-alert');
    var url = window.location.href;
    document.getElementById('share-fb-alert').href = 'https://www.facebook.com/sharer/sharer.php?u=' + encodeURIComponent(url);
    document.getElementById('share-x-alert').href = 'https://twitter.com/intent/tweet?url=' + encodeURIComponent(url);
    popup.style.display = (popup.style.display === 'block' ? 'none' : 'block');
    document.addEventListener('click', closeSharePopupAlert);
}
function closeSharePopupAlert(e) {
    var popup = document.getElementById('share-popup-alert');
    if (popup && !popup.contains(e.target) && e.target.id !== 'share-btn-alert') {
        popup.style.display = 'none';
        document.removeEventListener('click', closeSharePopupAlert);
    }
}

// Like button (nếu có)
document.addEventListener('DOMContentLoaded', function() {
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
                    document.getElementById('like-count-alert').textContent = data.count;
                    document.getElementById('like-text-alert').textContent = liked ? 'Thích' : 'Đã Thích';
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
    }
}); 