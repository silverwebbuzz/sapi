$(document).ready(function() {
    var dtLanguage = {
        "search": "Search:",
        "lengthMenu": "Show _MENU_ entries",
        "info": "Showing _START_ to _END_ of _TOTAL_ entries",
        "infoEmpty": "Showing 0 to 0 of 0 entries",
        "infoFiltered": "(filtered from _MAX_ total entries)",
        "paginate": { "first": "First", "last": "Last", "next": "Next", "previous": "Previous" }
    };

    // Dashboard + orders tables.
    // The Shopify Orders page (order.php) has a checkbox in column 0;
    // the dashboard (index.php) does not. We detect by ID and configure
    // each instance separately so neither breaks.
    if ($('#ordersTable').length) {
        // The Shopify Orders page (order.php) prepends a checkbox column;
        // the dashboard (index.php) does not. Detect and shift the sort
        // column accordingly so the table sorts by Date in both cases.
        var ordersHasCheckbox = $('#ordersTable .orders-row-check').length > 0;
        var dateColumnIndex = ordersHasCheckbox ? 2 : 1;
        var ordersConfig = {
            "order": [[dateColumnIndex, 'desc']],
            "pageLength": 25,
            "lengthMenu": [10, 25, 50, 100],
            "language": dtLanguage,
            "responsive": true
        };
        if (ordersHasCheckbox) {
            ordersConfig.columnDefs = [
                { "orderable": false, "searchable": false, "targets": 0 }
            ];
        }
        $('#ordersTable').DataTable(ordersConfig);
    }

    if ($('.billing-table').length) {
        $('.billing-table').DataTable({
            "order": [[1, 'desc']],
            "pageLength": 25,
            "lengthMenu": [10, 25, 50, 100],
            "language": dtLanguage,
            "responsive": true
        });
    }

    // Bulk download table — first column is the checkbox, not sortable.
    if ($('#bulkInvoicesTable').length) {
        $('#bulkInvoicesTable').DataTable({
            "order": [[2, 'desc']],   // sort by Date column by default
            "pageLength": 25,
            "lengthMenu": [10, 25, 50, 100],
            "language": dtLanguage,
            "columnDefs": [
                { "orderable": false, "searchable": false, "targets": 0 }
            ]
        });
    }

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

    // -------- Bulk Download page --------
    // Delegated so DataTables pagination/search/sort doesn't break it.
    function updateBulkSelectionState() {
        var $boxes  = $('.bulk-row-check');
        var $checked = $boxes.filter(':checked');
        $('#bulk-selected-count').text($checked.length + ' selected');
        // Master checkbox state — only flip the box, don't recurse.
        var $master = $('#bulk-select-all');
        if ($boxes.length === 0) {
            $master.prop('checked', false).prop('indeterminate', false);
        } else if ($checked.length === 0) {
            $master.prop('checked', false).prop('indeterminate', false);
        } else if ($checked.length === $boxes.length) {
            $master.prop('checked', true).prop('indeterminate', false);
        } else {
            $master.prop('checked', false).prop('indeterminate', true);
        }
        // Enable Download button only when at least one row is selected.
        $('#bulk-download-btn').prop('disabled', $checked.length === 0);
    }

    $(document).on('change', '#bulk-select-all', function () {
        // Select/deselect every row checkbox currently in the DOM
        // (DataTables keeps all rows in DOM even when paginating).
        var checked = $(this).prop('checked');
        $('.bulk-row-check:not(:disabled)').prop('checked', checked);
        updateBulkSelectionState();
    });

    $(document).on('change', '.bulk-row-check', function () {
        updateBulkSelectionState();
    });

    // Friendly guard when the form is submitted with nothing selected.
    $(document).on('submit', '#bulk-download-form', function (e) {
        if ($('.bulk-row-check:checked').length === 0) {
            e.preventDefault();
            showMessage('Please select at least one invoice.', 'error');
        }
    });

    // -------- Shopify Orders page: Bulk Generate / Regenerate --------
    function updateOrdersBulkState() {
        var $boxes = $('.orders-row-check');
        var $checked = $boxes.filter(':checked');
        $('#orders-selected-count').text($checked.length + ' selected');

        var $master = $('#orders-select-all');
        if ($boxes.length === 0 || $checked.length === 0) {
            $master.prop('checked', false).prop('indeterminate', false);
        } else if ($checked.length === $boxes.length) {
            $master.prop('checked', true).prop('indeterminate', false);
        } else {
            $master.prop('checked', false).prop('indeterminate', true);
        }
        $('#bulk-generate-btn').prop('disabled', $checked.length === 0);
    }

    $(document).on('change', '#orders-select-all', function () {
        $('.orders-row-check:not(:disabled)').prop('checked', $(this).prop('checked'));
        updateOrdersBulkState();
    });

    $(document).on('change', '.orders-row-check', function () {
        updateOrdersBulkState();
    });

    // Client-driven batching. We hit the existing generatepdf.php one at a
    // time so any single timeout only affects one invoice, not the whole batch.
    // The user has to keep the tab open — that's stated in the modal text.
    var bulkState = { cancelled: false, inFlight: null };

    function showBulkModal(total) {
        bulkState.cancelled = false;
        $('#bulkProgressTitle').text('Generating invoices…');
        $('#bulkProgressStatus').text('Starting…');
        $('#bulkProgressFill').css('width', '0%');
        $('#bulkProgressMeta').text('Please keep this tab open while we process your ' + total + ' invoice' + (total === 1 ? '' : 's') + '.');
        $('#bulkProgressCancel').prop('disabled', false).show();
        $('#bulkProgressClose').hide();
        $('#bulkProgressModal').show();
    }

    function updateBulkProgress(done, total, label, ok) {
        var pct = Math.round((done / total) * 100);
        $('#bulkProgressFill').css('width', pct + '%');
        var prefix = ok === false ? 'Skipped' : 'Processed';
        $('#bulkProgressStatus').text(prefix + ' ' + done + ' of ' + total + ' — ' + (label || ''));
    }

    function finishBulk(done, total, failures) {
        $('#bulkProgressCancel').hide();
        $('#bulkProgressClose').show();
        var msg;
        if (bulkState.cancelled) {
            msg = 'Stopped at ' + done + ' of ' + total + '.';
            $('#bulkProgressTitle').text('Stopped');
        } else if (failures === 0) {
            msg = 'All ' + done + ' invoice' + (done === 1 ? '' : 's') + ' generated successfully.';
            $('#bulkProgressTitle').text('Done');
        } else {
            msg = done + ' completed, ' + failures + ' failed. Try again for the failed ones.';
            $('#bulkProgressTitle').text('Finished with errors');
        }
        $('#bulkProgressMeta').text(msg + ' The page will refresh when you close this.');
    }

    $(document).on('click', '#bulkProgressCancel', function () {
        bulkState.cancelled = true;
        $('#bulkProgressStatus').text('Stopping after current invoice…');
        $(this).prop('disabled', true);
    });

    $(document).on('click', '#bulkProgressClose', function () {
        $('#bulkProgressModal').hide();
        location.reload();
    });

    $(document).on('click', '#bulk-generate-btn', function () {
        var $btn = $(this);
        var shopId = $btn.data('shop-id');
        var cap = parseInt($btn.data('batch-cap'), 10) || 50;
        var remaining = parseInt($btn.data('orders-remaining'), 10);
        if (isNaN(remaining)) remaining = cap;

        var selections = $('.orders-row-check:checked').map(function () {
            return { id: $(this).val(), label: $(this).data('order-label') || ('#' + $(this).val()) };
        }).get();

        if (selections.length === 0) {
            showMessage('Please select at least one invoice.', 'error');
            return;
        }

        // Enforce per-batch cap and plan quota — cap whichever is smaller.
        var limit = Math.min(cap, remaining > 0 ? remaining : cap);
        if (selections.length > limit) {
            var reason = (remaining > 0 && remaining < cap)
                ? 'You have ' + remaining + ' invoice' + (remaining === 1 ? '' : 's') + ' left in your plan this period.'
                : 'You can process up to ' + cap + ' invoices at a time.';
            if (!confirm(reason + '\n\nProcess the first ' + limit + ' selected and skip the rest?')) {
                return;
            }
            selections = selections.slice(0, limit);
        }

        if (remaining === 0) {
            showMessage('Your plan has no remaining invoice quota this period.', 'error');
            return;
        }

        showBulkModal(selections.length);
        runBulkSequentially(shopId, selections);
    });

    function runBulkSequentially(shopId, selections) {
        var total = selections.length;
        var done = 0;
        var failures = 0;
        var i = 0;

        function next() {
            if (bulkState.cancelled || i >= total) {
                finishBulk(done, total, failures);
                return;
            }
            var item = selections[i++];
            $('#bulkProgressStatus').text('Processing ' + (i) + ' of ' + total + ' — ' + item.label);

            bulkState.inFlight = $.ajax({
                url: BASE_URL + '/invoice/generatepdf.php',
                method: 'GET',
                data: { shop_id: shopId, order_id: item.id },
                timeout: 60000
            }).done(function () {
                done++;
                updateBulkProgress(done, total, item.label, true);
            }).fail(function () {
                failures++;
                updateBulkProgress(done + failures, total, item.label, false);
            }).always(function () {
                bulkState.inFlight = null;
                // small breather so a stuck server doesn't get pounded
                setTimeout(next, 150);
            });
        }
        next();
    }

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