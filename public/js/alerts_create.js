// Issue #349: the positionable map (geocoder search, click-to-drop, reverse
// geocode, "where am I") used to live inline in this file. Extracted to
// alert_map_picker.js so /alerts/{id}/edit gets the same map instead of the
// stripped-down inline copy it had — which also dropped fixLeafletIcons() and
// rendered no marker at all.
document.addEventListener('DOMContentLoaded', function () {
    editPositionableMap({ containerId: 'map' });
    fixLeafletIcons();

    setupImagePreview();
    setupFormValidation();
});

// Xem trước ảnh trước khi upload
function setupImagePreview() {
    var input = document.getElementById('image');
    if (!input) return;
    input.addEventListener('change', function (e) {
        var preview = document.getElementById('image-preview');
        if (!preview) return;
        preview.innerHTML = '';

        if (this.files && this.files[0]) {
            var reader = new FileReader();

            reader.onload = function (e) {
                var imgContainer = document.createElement('div');
                imgContainer.className = 'position-relative d-inline-block';

                var img = document.createElement('img');
                img.src = e.target.result;
                // Issue #368: every server-rendered <img> in this repo carries
                // an alt; this was the only image built without one, so a
                // screen reader announced nothing for the picture the user had
                // just picked.
                img.alt = 'Ảnh xem trước';
                img.className = 'img-fluid rounded-3 shadow-sm border';
                img.style.maxHeight = '300px';

                // The remove button's only content is a glyph: there is no
                // text label to say "discard this image", so the icon is the
                // affordance (#347 doctrine).
                var btnRemove = document.createElement('button');
                btnRemove.type = 'button';
                btnRemove.setAttribute('aria-label', 'Bỏ ảnh này');
                btnRemove.className = 'btn btn-danger btn-sm position-absolute top-0 end-0 m-2 rounded-circle';
                btnRemove.innerHTML = '<i class="fas fa-times"></i>';
                btnRemove.onclick = function () {
                    document.getElementById('image').value = '';
                    preview.innerHTML = '';
                };

                imgContainer.appendChild(img);
                imgContainer.appendChild(btnRemove);
                preview.appendChild(imgContainer);
            };

            reader.readAsDataURL(this.files[0]);
        }
    });
}

// Validation form
function setupFormValidation() {
    'use strict'

    // Fetch all the forms we want to apply custom Bootstrap validation styles to
    var forms = document.querySelectorAll('.needs-validation')

    // Loop over them and prevent submission
    Array.prototype.slice.call(forms)
        .forEach(function (form) {
            form.addEventListener('submit', function (event) {
                if (!form.checkValidity()) {
                    event.preventDefault()
                    event.stopPropagation()
                }

                form.classList.add('was-validated')
            }, false)
        })
}
