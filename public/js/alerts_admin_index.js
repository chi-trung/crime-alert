document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('form.form-reject').forEach(function(form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            Swal.fire({
                title: 'Bạn có chắc chắn muốn từ chối cảnh báo này?',
                text: 'Hành động này sẽ từ chối cảnh báo và không thể hoàn tác!',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ffc107',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Từ chối',
                cancelButtonText: 'Hủy',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    form.submit();
                }
            });
        });
    });
}); 