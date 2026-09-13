// Issue #182: this file used to run its logic at parse time (top-level
// getElementById calls). The blade loads it synchronously at the TOP of the
// content section (support/show.blade.php) while #chat-messages and
// #chat-input are parsed further down the same document, so both lookups
// always returned null, the ?. / if guards swallowed that silently, and
// every statement was dead code since the file's first commit — the
// textarea never autofocused and long threads never scrolled to the bottom
// (the inline @section('scripts') block only scrolls inside fetchMessages,
// which early-returns on first load because lastMessageCount is seeded from
// the server-rendered count, so no code path ever fired the initial scroll).
// Now deferred to DOMContentLoaded like alerts_show.js / dashboard.js.
document.addEventListener('DOMContentLoaded', function () {
    // Tự động focus vào textarea khi vào trang
    const chatInput = document.getElementById('chat-input');
    if (chatInput) {
        chatInput.focus();
    }

    // Tự động cuộn xuống cuối khi vào trang
    const chatBox = document.getElementById('chat-messages');
    if (chatBox) {
        chatBox.scrollTop = chatBox.scrollHeight;
    }
});
