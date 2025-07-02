document.addEventListener('DOMContentLoaded', function() {
  document.querySelectorAll('.notification-item').forEach(function(item) {
    item.addEventListener('click', function(e) {
      e.preventDefault();
      var url = this.getAttribute('href');
      var li = this.closest('li');
      var badge = document.querySelector('.notification-badge');
      // Gọi AJAX đánh dấu đã đọc
      fetch(url, { method: 'GET', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(res => {
          if (res.redirected) {
            window.location.href = res.url;
            return;
          }
          // Hiệu ứng mờ dần
          li.style.transition = 'opacity 0.5s';
          li.style.opacity = 0;
          setTimeout(() => {
            li.remove();
            // Giảm badge
            let count = parseInt(badge?.textContent || '1');
            if (count > 1) badge.textContent = count - 1;
            else if (badge) badge.remove();
          }, 500);
        });
    });
  });
});
