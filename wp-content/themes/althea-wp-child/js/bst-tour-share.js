function bstGetShareButtonsContainer(element) {
    return element ? element.closest('.bst-share-buttons') : null;
}

function bstLogShareEvent(container, method) {
    if (!container || !window.bstShareLog || !window.bstShareLog.ajaxUrl) {
        return;
    }

    var params = new URLSearchParams();
    params.append('action', 'bst_log_share_event');
    params.append('nonce', window.bstShareLog.nonce);
    params.append('method', method);
    params.append('context', container.dataset.shareContext || '');
    params.append('url', container.dataset.shareUrl || window.location.href);
    params.append('title', container.dataset.shareTitle || document.title || '');
    params.append('object_id', container.dataset.shareObjectId || '0');
    params.append('user_agent', navigator.userAgent || '');

    if (navigator.sendBeacon) {
        navigator.sendBeacon(window.bstShareLog.ajaxUrl, params);
        return;
    }

    fetch(window.bstShareLog.ajaxUrl, {
        method: 'POST',
        body: params,
        credentials: 'same-origin',
        keepalive: true
    }).catch(function() {
        // Non-blocking analytics; ignore network errors.
    });
}

function bstCopyTourLink(event) {
    var btn = event.currentTarget;
    var container = bstGetShareButtonsContainer(btn);
    var url = container && container.dataset.shareUrl ? container.dataset.shareUrl : window.location.href;

    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(url).then(function() {
            bstShowCopySuccess(btn, container);
        }).catch(function() {
            bstCopyFallback(url, btn, container);
        });
    } else {
        bstCopyFallback(url, btn, container);
    }
}

function bstCopyFallback(text, btn, container) {
    var textArea = document.createElement('textarea');
    textArea.value = text;
    textArea.style.position = 'fixed';
    textArea.style.left = '-999999px';
    textArea.style.top = '-999999px';
    document.body.appendChild(textArea);
    textArea.focus();
    textArea.select();

    try {
        document.execCommand('copy');
        bstShowCopySuccess(btn, container);
    } catch (err) {
        alert('Failed to copy link. Please copy manually: ' + text);
    }

    document.body.removeChild(textArea);
}

function bstShowCopySuccess(btn, container) {
    btn.classList.add('copied');
    btn.setAttribute('title', 'Copied!');
    bstLogShareEvent(container, 'copy');

    setTimeout(function() {
        btn.classList.remove('copied');
        btn.setAttribute('title', 'Copy link to clipboard');
    }, 2000);
}

document.addEventListener('click', function(event) {
    var action = event.target.closest('.bst-share-action');
    if (!action) {
        return;
    }

    var container = bstGetShareButtonsContainer(action);
    if (!container) {
        return;
    }

    var method = action.dataset.shareMethod;
    if (method === 'copy') {
        return;
    }

    if (method === 'email' || method === 'whatsapp') {
        bstLogShareEvent(container, method);
    }
});

document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.bst-share-copy').forEach(function(button) {
        button.addEventListener('click', bstCopyTourLink);
    });
});
