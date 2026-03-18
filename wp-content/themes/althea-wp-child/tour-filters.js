// Tour Filter Enhancement
document.addEventListener('DOMContentLoaded', function() {
    const filterForm = document.querySelector('.tour-filter-form');
    const filterSelects = document.querySelectorAll('.tour-filter-form select');
    
    // Auto-submit form when filter changes
    filterSelects.forEach(function(select) {
        select.addEventListener('change', function() {
            filterForm.submit();
        });
    });
    
    // Add loading state
    filterForm.addEventListener('submit', function() {
        const button = filterForm.querySelector('button[type="submit"]');
        if (button) {
            button.textContent = 'Filtering...';
            button.disabled = true;
        }
    });
});
