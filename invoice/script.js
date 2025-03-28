$(document).ready(function() {
    // Initialize DataTable
    $('#ordersTable').DataTable({
        "pageLength": 10,
        "lengthMenu": [10, 25, 50, 100],
        "order": [[0, "desc"]],
        "language": {
            "search": "Search:",
            "lengthMenu": "Show _MENU_ entries",
            "info": "Showing _START_ to _END_ of _TOTAL_ entries",
            "infoEmpty": "Showing 0 to 0 of 0 entries",
            "infoFiltered": "(filtered from _MAX_ total entries)",
            "paginate": {
                "first": "First",
                "last": "Last",
                "next": "Next",
                "previous": "Previous"
            }
        },
        "responsive": true
    });

    // Mobile Menu Toggle
    $('.menu-toggle').on('click', function() {
        $(this).toggleClass('active');
        $('.horizontal-menu').toggleClass('active');
    });

    // Mobile Dropdown Toggle
    $('.menu-dropdown > a').on('click', function(e) {
        if ($(window).width() <= 992) {
            e.preventDefault();
            $(this).parent().toggleClass('active');
        }
    });

    // Close menu when clicking outside on mobile
    $(document).on('click', function(e) {
        if ($(window).width() <= 992) {
            if (!$(e.target).closest('.horizontal-menu, .menu-toggle').length) {
                $('.menu-toggle').removeClass('active');
                $('.horizontal-menu').removeClass('active');
                $('.menu-dropdown').removeClass('active');
            }
        }
    });

    // Handle window resize
    $(window).on('resize', function() {
        if ($(window).width() > 992) {
            $('.menu-toggle').removeClass('active');
            $('.horizontal-menu').removeClass('active');
            $('.menu-dropdown').removeClass('active');
        }
    });

    // Initialize tooltips if needed
    $('[data-toggle="tooltip"]').tooltip();
});