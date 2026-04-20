function bstCopyTourLink(event) {
    const url = window.location.href;
    const btn = event.currentTarget;

    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(url).then(() => {
            bstShowCopySuccess(btn);
        }).catch(() => {
            bstCopyFallback(url, btn);
        });
    } else {
        bstCopyFallback(url, btn);
    }
}

function bstCopyFallback(text, btn) {
    const textArea = document.createElement('textarea');
    textArea.value = text;
    textArea.style.position = 'fixed';
    textArea.style.left = '-999999px';
    textArea.style.top = '-999999px';
    document.body.appendChild(textArea);
    textArea.focus();
    textArea.select();

    try {
        document.execCommand('copy');
        bstShowCopySuccess(btn);
    } catch (err) {
        alert('Failed to copy link. Please copy manually: ' + text);
    }

    document.body.removeChild(textArea);
}

function bstShowCopySuccess(btn) {
    btn.classList.add('copied');
    btn.setAttribute('title', 'Copied!');

    setTimeout(() => {
        btn.classList.remove('copied');
        btn.setAttribute('title', 'Copy link to clipboard');
    }, 2000);
}
