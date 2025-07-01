// Tự động focus vào textarea khi vào trang
document.getElementById('chat-input')?.focus();
// Tự động cuộn xuống cuối khi vào trang hoặc có tin nhắn mới
function scrollToBottom() {
    var chatBox = document.getElementById('chat-messages');
    if(chatBox) chatBox.scrollTop = chatBox.scrollHeight;
}
scrollToBottom(); 