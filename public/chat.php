<?php
require_once __DIR__ . '/../views/header.php';

require_login();
if (!has_role('admin') && !has_role('supervisor') && !has_role('technician')) {
    redirect('dashboard.php');
}
?>

<div class="container-fluid p-0 position-relative" style="height: calc(100vh - 85px); margin: -1.5rem; width: calc(100% + 3rem);">
    <div class="row h-100 g-0">
        <div class="col-12 h-100">
            <div class="card border-0 h-100 d-flex flex-column shadow-none bg-transparent">
                
                
                <div class="card-header border-bottom border-white border-opacity-10 bg-dark bg-opacity-25 py-3 flex-shrink-0 backdrop-blur-md">
                    <div class="d-flex justify-content-between align-items-center px-4">
                        <div class="d-flex align-items-center gap-3">
                            <div class="stat-icon text-primary bg-primary bg-opacity-10" style="width: 40px; height: 40px; border-radius: 10px;">
                                <i class="fas fa-hashtag"></i>
                            </div>
                            <div>
                                <h5 class="mb-0 text-white fw-bold">IT Operations</h5>
                                <div class="d-flex align-items-center mt-1">
                                    <span class="badge bg-success bg-opacity-10 text-success rounded-pill p-1 me-2" style="width: 8px; height: 8px;"></span>
                                    <span class="small text-secondary">General Channel</span>
                                </div>
                            </div>
                        </div>
                        <div class="d-flex gap-2">
                             <button class="btn btn-icon btn-sm btn-ghost-secondary text-white-50" title="Search"><i class="fas fa-search"></i></button>
                             <button class="btn btn-icon btn-sm btn-ghost-secondary text-white-50" title="Participants"><i class="fas fa-users"></i></button>
                        </div>
                    </div>
                </div>

                
                <div id="chat-messages" class="card-body p-4 overflow-auto custom-scrollbar flex-grow-1" style="scroll-behavior: smooth; background: radial-gradient(circle at center, rgba(15, 23, 42, 0) 0%, rgba(15, 23, 42, 0.5) 100%);">
                     
                    <div class="h-100 d-flex flex-column align-items-center justify-content-center opacity-50" id="loading-spinner">
                        <div class="spinner-border text-primary mb-3" role="status"></div>
                        <span class="small text-secondary text-uppercase fw-bold ls-wider">Loading history...</span>
                    </div>
                </div>

                
                <div class="card-footer border-top border-white border-opacity-10 bg-dark bg-opacity-25 p-4 flex-shrink-0 backdrop-blur-md">
                     
                    <form id="chat-form" class="d-flex gap-3 align-items-end px-2">
                        <div class="flex-grow-1 position-relative">
                            <textarea id="message-input" class="form-control bg-dark border-secondary border-opacity-50 text-white py-3 ps-4 pe-5 rounded-4 shadow-inner custom-scrollbar" placeholder="Type your message..." rows="1" style="resize: none; overflow: hidden; min-height: 50px; scrollbar-width: none;"></textarea>
                            <button type="button" class="btn btn-link position-absolute end-0 bottom-0 mb-2 text-secondary me-3"><i class="fas fa-paperclip"></i></button>
                        </div>
                        <button type="submit" class="btn btn-primary rounded-circle shadow-lg d-flex align-items-center justify-content-center hover-scale flex-shrink-0" style="width: 50px; height: 50px;">
                            <i class="fas fa-paper-plane"></i>
                        </button>
                    </form>
                    <div class="text-center mt-2">
                         <span class="small text-secondary opacity-50"><i class="fas fa-lock me-1"></i> Messages are end-to-end encrypted</span>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<style>
    /* Chat Bubbles - Messenger Style */
    .message-bubble {
        padding: 0.8rem 1.2rem;
        position: relative;
        max-width: 75%; /* Restrict max width */
        width: fit-content; /* Wrap tightly around content */
        word-wrap: break-word; /* Legacy support */
        word-break: break-word; /* Break long words */
        overflow-wrap: break-word; /* Standard property */
        white-space: pre-wrap; /* Preserve newlines from textarea */
        font-size: 0.95rem;
        line-height: 1.5;
    }

    .message-bubble-me {
        background: #0084FF; /* Messenger Blue */
        color: white;
        border-radius: 18px 18px 4px 18px; /* Classic bubble shape */
        box-shadow: 0 1px 2px rgba(0,0,0,0.1);
        margin-left: auto; /* Ensure it sticks to right */
    }
    
    .message-bubble-other {
        background: #3E4042; /* Dark mode grey */
        color: #E4E6EB;
        border-radius: 18px 18px 18px 4px;
        border: none;
    }

    .message-meta {
        font-size: 0.7rem;
        margin-top: 0.3rem;
        opacity: 0.6;
        margin-bottom: 0.2rem;
    }

    /* Scrollbar */
    .custom-scrollbar::-webkit-scrollbar { width: 6px; }
    .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
    .custom-scrollbar::-webkit-scrollbar-thumb { background: rgba(255, 255, 255, 0.1); border-radius: 10px; }
    .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: rgba(255, 255, 255, 0.2); }

    /* Animations */
    @keyframes slideInUp {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }
    .msg-anim { animation: slideInUp 0.3s ease-out forwards; }
</style>

<script>
const chatMessages = document.getElementById('chat-messages');
const chatForm = document.getElementById('chat-form');
const messageInput = document.getElementById('message-input');
const loadingSpinner = document.getElementById('loading-spinner');
let lastMessageId = 0;
let firstMessageId = null; 
let isFirstLoad = true;
let isUserScrolling = false;

// Handle scroll logic
chatMessages.addEventListener('scroll', () => {
    isUserScrolling = chatMessages.scrollTop + chatMessages.clientHeight < chatMessages.scrollHeight - 50;
});

function scrollToBottom(force = false) {
    if (!isUserScrolling || force) {
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }
}

function fetchMessages() {
    fetch(`api/chat_handler.php?action=fetch&last_id=${lastMessageId}`)
        .then(response => response.json())
        .then(data => {
            if (loadingSpinner && isFirstLoad) loadingSpinner.remove();

            if (data.error) {
                console.error('API Error:', data.error);
                return;
            }

            if (data.messages && data.messages.length > 0) {
                if (isFirstLoad) {
                     const loadMoreBtn = `
                        <div class="text-center mb-4" id="load-more-container">
                            <button class="btn btn-sm btn-outline-secondary rounded-pill px-3" onclick="loadOlderMessages()">
                                <i class="fas fa-history me-1"></i> Load Older Messages
                            </button>
                        </div>
                     `;
                     chatMessages.insertAdjacentHTML('afterbegin', loadMoreBtn);
                     firstMessageId = data.messages[0].id;
                }

                data.messages.forEach(msg => {
                    if (msg.id <= lastMessageId) return;
                    lastMessageId = Math.max(lastMessageId, msg.id);
                    
                    const isMe = msg.user_id == <?php echo $_SESSION['user_id']; ?>;
                    
                    const html = `
                        <div class="d-flex mb-3 ${isMe ? 'justify-content-end' : ''} msg-anim">
                            ${!isMe ? `
                                <div class="avatar-circle bg-secondary bg-opacity-25 text-white rounded-circle d-flex align-items-center justify-content-center me-2 flex-shrink-0" style="width: 38px; height: 38px;">
                                    ${msg.full_name.charAt(0)}
                                </div>
                            ` : ''}
                            
                            <div class="d-flex flex-column ${isMe ? 'align-items-end' : 'align-items-start'}" style="max-width: 100%;">
                                ${!isMe ? `<div class="small text-secondary mb-1 ms-1">${msg.full_name}</div>` : ''}
                                <div class="message-bubble ${isMe ? 'message-bubble-me' : 'message-bubble-other'}">
                                    ${msg.message}
                                </div>
                                <div class="message-meta ${isMe ? 'text-end me-1' : 'ms-1'} text-secondary">
                                    ${msg.formatted_time}
                                </div>
                            </div>
                        </div>
                    `;
                    
                    chatMessages.insertAdjacentHTML('beforeend', html);
                });
                
                scrollToBottom(isFirstLoad); 
                isFirstLoad = false;
            } else if (isFirstLoad) {
                 chatMessages.innerHTML = `
                    <div class="h-100 d-flex flex-column align-items-center justify-content-center text-secondary opacity-50">
                        <i class="fas fa-comments fa-3x mb-3"></i>
                        <p class="mb-0">No messages yet. Say hello!</p>
                    </div>`;
                 isFirstLoad = false;
            }
        })
        .catch(err => {
            console.error('Fetch Error:', err);
            if (loadingSpinner && isFirstLoad) {
                loadingSpinner.innerHTML = `
                    <div class="text-danger">
                        <i class="fas fa-exclamation-triangle fa-3x mb-3"></i>
                        <p class="mb-0">Failed to load messages</p>
                    </div>`;
            }
        });
}

function loadOlderMessages() {
    const firstMessage = chatMessages.querySelector('.message-wrapper');
    if (!firstMessage) return;
    
    const firstMessageId = parseInt(firstMessage.dataset.messageId);
    if (isNaN(firstMessageId)) return;
    
    fetch(`api/chat_handler.php?action=fetch&before_id=${firstMessageId}`)
        .then(response => response.json())
        .then(data => {
            if (data.messages && data.messages.length > 0) {
                const html = data.messages.map(msg => {
                    const isMe = msg.user_id == <?php echo $_SESSION['user_id']; ?>;
                    return `
                        <div class="message-wrapper d-flex ${isMe ? 'justify-content-end' : 'justify-content-start'} mb-3" data-message-id="${msg.id}">
                            <div class="message-bubble ${isMe ? 'message-bubble-me' : 'message-bubble-other'}">
                                <div class="fw-bold small mb-1 ${isMe ? 'text-primary' : 'text-info'}">${msg.full_name}</div>
                                ${msg.message}
                            </div>
                            <div class="message-meta ${isMe ? 'text-end me-1' : 'ms-1'} text-secondary">
                                ${msg.formatted_time}
                            </div>
                        </div>
                    `;
                }).join('');
                
                chatMessages.insertAdjacentHTML('afterbegin', html);
            }
        })
        .catch(err => console.error('Fetch Error:', err));
}

// --- UPDATED: Textarea Logic ---

const originalHeight = '50px';

// Auto-expand input
messageInput.addEventListener('input', function() {
    this.style.height = originalHeight; 
    this.style.height = (this.scrollHeight) + 'px';
});

// Enter to send
messageInput.addEventListener('keydown', function(e) {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        chatForm.dispatchEvent(new Event('submit'));
    }
});

chatForm.addEventListener('submit', function(e) {
    e.preventDefault();
    const message = messageInput.value.trim();
    if (!message) return;

    fetch('api/chat_handler.php?action=send', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ message: message })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            messageInput.value = '';
            messageInput.style.height = originalHeight; // Reset height
            fetchMessages(); 
            scrollToBottom(true);
        } else {
            alert('Error: ' + data.error);
        }
    })
    .catch(err => console.error(err));
});

// Init
fetchMessages();

let chatPollInterval = null;
const CHAT_POLL_MS = 10000;

function startChatPolling() {
    if (chatPollInterval) return;
    chatPollInterval = setInterval(fetchMessages, CHAT_POLL_MS);
}

function stopChatPolling() {
    if (chatPollInterval) {
        clearInterval(chatPollInterval);
        chatPollInterval = null;
    }
}

document.addEventListener('visibilitychange', () => {
    if (document.hidden) {
        stopChatPolling();
    } else {
        fetchMessages();
        startChatPolling();
    }
});

startChatPolling();
</script>

<?php require_once __DIR__ . '/../views/footer.php'; ?>
