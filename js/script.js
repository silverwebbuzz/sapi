$(document).ready(function() {
    // Initialize DataTables
    $('#ordersTable, .billing-table').DataTable({
        "order": [[1, 'desc']],
        "pageLength": 25,
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

    //Generate Invoice Function
    window.generateInvoice = function (shopId, orderId, invoiceStatus) {
        fetch(`${BASE_URL}/invoice/generatepdf.php?shop_id=${shopId}&order_id=${orderId}&invoicestatus=${invoiceStatus}`)
            .then(response => response.text())
            .then(data => {
                showMessage('Invoice processed successfully.', 'success');
                setTimeout(() => location.reload(), 2000);
            })
            .catch(error => {
                showMessage('Failed to process invoice.', 'error');
            });
    };

    // Show Message (success or error)
    window.showMessage = function (message, type = 'success') {
        var $box = $('#message-box');
        $box.text(message)
            .css('background-color', type === 'success' ? '#28a745' : '#dc3545')
            .fadeIn();

        setTimeout(() => {
            $box.fadeOut();
        }, 3000); // ⏱️ 3 seconds
    };

    // ALL action buttons use event delegation on document so they survive
    // DataTables pagination, sort, and search (which detach/re-attach rows).
    console.log('[script.js] binding delegated click handlers');

    // View Invoice — open modal
    $(document).on('click', '.view-invoice-btn', function (e) {
        e.preventDefault();
        var invoiceId = $(this).data('invoice-id');
        $('#invoiceFrame').attr('src', 'data:application/pdf;base64,' + invoiceId);
        $('#invoiceModal').show();
    });

    // Generate Invoice
    $(document).on('click', '.js-generate-invoice', function (e) {
        e.preventDefault();
        window.generateInvoice(
            $(this).data('shop-id'),
            $(this).data('order-id'),
            $(this).data('invoice-status')
        );
    });

    // Send Email to customer
    $(document).on('click', '.js-send-email', function (e) {
        e.preventDefault();
        window.sendEmail(
            $(this).data('shop-id'),
            $(this).data('order-id'),
            $(this).data('email-status')
        );
    });

    // Send Email to store owner
    $(document).on('click', '.js-send-email-owner', function (e) {
        e.preventDefault();
        window.sendEmailToOwner(
            $(this).data('shop-id'),
            $(this).data('order-id')
        );
    });

    // Close Modal Function
    window.closeInvoiceModal = function () {
        $('#invoiceModal').hide();
        $('#invoiceFrame').attr('src', '');
    };

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

    // Plan Upgrade Buttons
    $('.btn-upgrade').on('click', function() {
        if (!$(this).hasClass('current')) {
            const planName = $(this).closest('.plan-card').find('h4').text();
            if (confirm(`Are you sure you want to upgrade to ${planName} plan?`)) {
                // Here you would typically redirect to payment page or make an AJAX call
                //alert(`Upgraded to ${planName} plan!`);
            }
        }
    });

    // Cancel Subscription Button
    $('.btn-cancel').on('click', function() {
        if (confirm('Are you sure you want to cancel your subscription?')) {
            // Here you would typically make an AJAX call to cancel subscription
           // alert('Subscription cancelled!');
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

    // Display Modal
    function showMessageModal(message) {
        $('#messageText').text(message);
        $('#messageModal').show();
    }
    // close Modal
    function closeMessageModal() {
        $('#messageModal').hide();
        $('#messageText').text('');
    }

    // Run on page load and hash change
    $(window).on('load hashchange', activateTabFromHash);
});

// Move these functions outside document.ready
// Send Email Function
window.sendEmail = function (shopId, orderId, emailStatus) {
    fetch(`${BASE_URL}/invoice/sendemail.php?shop_id=${shopId}&order_id=${orderId}&emailstatus=${emailStatus}`)
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                showMessage(data.message, 'success');
                setTimeout(() => location.reload(), 2000);
            } else {
                showMessage(data.message, 'error');
                if (data.message.includes('upgrade your plan')) {
                    setTimeout(() => {
                        if (confirm('Would you like to upgrade your plan now?')) {
                            window.location.href = `${BASE_URL}/invoice/change-plan?shop=${shopId}`;
                        }
                    }, 1000);
                }
            }
        })
        .catch(error => {
            showMessage('Failed to send email.', 'error');
        });
};

// Send Email to Store Owner Function
window.sendEmailToOwner = function (shopId, orderId) {
    fetch(`${BASE_URL}/invoice/sendemail.php?shop_id=${shopId}&order_id=${orderId}&personal_copy=true`)
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                showMessage(data.message, 'success');
                setTimeout(() => location.reload(), 2000);
            } else {
                showMessage(data.message, 'error');
            }
        })
        .catch(error => {
            showMessage('Failed to send email to store owner.', 'error');
        });
};