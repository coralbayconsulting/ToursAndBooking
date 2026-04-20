jQuery(document).ready(function() {
    var minutes = (typeof bstAutoRefresh !== 'undefined') ? bstAutoRefresh.interval : 15;
    setTimeout(function() {
        location.reload();
    }, minutes * 60 * 1000);
});
