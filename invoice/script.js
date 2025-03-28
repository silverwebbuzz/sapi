document.addEventListener('DOMContentLoaded', function() {
    // Search functionality
    const searchInput = document.querySelector('.search-box input');
    searchInput.addEventListener('input', function(e) {
        const searchTerm = e.target.value.toLowerCase();
        const rows = document.querySelectorAll('.orders-table tbody tr');
        
        rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            row.style.display = text.includes(searchTerm) ? '' : 'none';
        });
    });

    // Pagination buttons
    const pageButtons = document.querySelectorAll('.page-btn');
    pageButtons.forEach(button => {
        button.addEventListener('click', function() {
            if (!this.classList.contains('active')) {
                document.querySelector('.page-btn.active').classList.remove('active');
                this.classList.add('active');
                // Here you would typically make an AJAX call to load new page data
            }
        });
    });

    // Entries per page dropdown (would be expanded in a real implementation)
    const entriesSelect = document.querySelector('.entries-select');
    entriesSelect.addEventListener('click', function() {
        // In a real app, this would show a dropdown to change items per page
        console.log('Show entries per page dropdown');
    });

    // Send invoice links
    const emailLinks = document.querySelectorAll('.email-link');
    emailLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const url = this.getAttribute('href');
            // In a real app, this would trigger an email sending process
            console.log('Sending invoice via:', url);
            alert('Invoice email would be sent to: ' + url);
        });
    });
});