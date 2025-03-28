$(document).ready(function() {
    // Initialize DataTables
    $('#ordersTable, .billing-table').DataTable({
        "pageLength": 10,
        "lengthMenu": [10, 25, 50, 100],
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

    // Template Selection
    $('.template-card').on('click', function() {
        $('.template-card').removeClass('selected');
        $(this).addClass('selected');
    });

    // Settings Form Submission
    $('.general-settings-form').on('submit', function(e) {
        e.preventDefault();
        const form = $(this);
        const section = form.closest('.settings-section');
        const sectionName = section.attr('id');
        const formData = form.serialize();
        
        // Show loading state
        const submitBtn = form.find('.btn-save');
        submitBtn.prop('disabled', true).html('<i class="icon-spinner"></i> Saving...');
        
        $.ajax({
            url: 'save_settings.php',
            type: 'POST',
            data: formData + '&section=' + sectionName,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    showAlert('success', response.message);
                } else {
                    showAlert('error', response.message || 'Failed to save settings');
                }
            },
            error: function(xhr) {
                showAlert('error', 'An error occurred while saving settings');
            },
            complete: function() {
                submitBtn.prop('disabled', false).text('Save ' + sectionName.charAt(0).toUpperCase() + sectionName.slice(1) + ' Settings');
            }
        });
    });

    function showAlert(type, message) {
        const alertHtml = `<div class="alert alert-${type}">${message}</div>`;
        $('.settings-container').prepend(alertHtml);
        
        // Remove alert after 5 seconds
        setTimeout(() => {
            $('.alert').fadeOut(500, function() {
                $(this).remove();
            });
        }, 5000);
    }

    // Plan Upgrade Buttons
    $('.btn-upgrade').on('click', function() {
        if (!$(this).hasClass('current')) {
            const planName = $(this).closest('.plan-card').find('h4').text();
            if (confirm(`Are you sure you want to upgrade to ${planName} plan?`)) {
                // Here you would typically redirect to payment page or make an AJAX call
                alert(`Upgraded to ${planName} plan!`);
            }
        }
    });

    // Cancel Subscription Button
    $('.btn-cancel').on('click', function() {
        if (confirm('Are you sure you want to cancel your subscription?')) {
            // Here you would typically make an AJAX call to cancel subscription
            alert('Subscription cancelled!');
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


    // Tab Functionality
    $('.settings-tab').on('click', function(e) {
        e.preventDefault();
        
        // Remove active class from all tabs and sections
        $('.settings-tab').removeClass('active');
        $('.settings-section').removeClass('active');
        
        // Add active class to clicked tab
        $(this).addClass('active');
        
        // Show corresponding section
        const target = $(this).attr('href');
        $(target).addClass('active');
    });

    // Activate tab based on URL hash
    function activateTabFromHash() {
        const hash = window.location.hash;
        if (hash) {
            const tab = $(`.settings-tab[href="${hash}"]`);
            if (tab.length) {
                $('.settings-tab').removeClass('active');
                $('.settings-section').removeClass('active');
                tab.addClass('active');
                $(hash).addClass('active');
            }
        }
    }

    // Run on page load and hash change
    $(window).on('load hashchange', activateTabFromHash);