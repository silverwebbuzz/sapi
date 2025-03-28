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

});