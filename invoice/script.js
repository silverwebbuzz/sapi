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
        }
    });

    // Vertical Menu Functionality
    $('.menu-item-has-children > a').click(function(e) {
        e.preventDefault();
        $(this).parent().toggleClass('active');
    });

    // Mobile Menu Toggle
    $('.menu-toggle').click(function() {
        $('.vertical-sidebar').toggleClass('mobile-show');
        $('.main-content-wrapper').toggleClass('menu-open');
    });

    // Close menu when clicking outside on mobile
    $(document).click(function(e) {
        if ($(window).width() <= 992) {
            if (!$(e.target).closest('.vertical-sidebar, .menu-toggle').length) {
                $('.vertical-sidebar').removeClass('mobile-show');
                $('.main-content-wrapper').removeClass('menu-open');
            }
        }
    });

    // Responsive adjustments
    function handleResponsive() {
        if ($(window).width() <= 992) {
            $('.main-content-wrapper').css('margin-left', '0');
            $('.menu-toggle').show();
        } else {
            $('.main-content-wrapper').css('margin-left', '260px');
            $('.menu-toggle').hide();
            $('.vertical-sidebar').removeClass('mobile-show');
            $('.main-content-wrapper').removeClass('menu-open');
        }
    }

    // Run on load and resize
    handleResponsive();
    $(window).resize(handleResponsive);
});