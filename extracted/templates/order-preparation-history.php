<?php
/**
 * Order Preparation History Page Template
 */

if (!defined('ABSPATH')) {
    exit;
}

$page_title = 'Order Preparation History - 120 Stand Inventory';
include STAND120_PLUGIN_DIR . 'templates/partials/header.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; margin-bottom: 24px;">
    <h1 class="page-title" style="margin-bottom: 0;">
        <iconify-icon icon="solar:history-linear"></iconify-icon>
        Order Preparation History
    </h1>
    <a href="<?php echo home_url('/120-stand/order-preparation/'); ?>" class="btn btn-primary">
        <iconify-icon icon="solar:arrow-left-linear"></iconify-icon> Back
    </a>
</div>

<div class="filter-section">
    <div class="filter-group">
        <label>From Date</label>
        <input type="date" id="dateFrom" class="form-control" value="<?php echo date('Y-m-01'); ?>">
    </div>
    <div class="filter-group">
        <label>To Date</label>
        <input type="date" id="dateTo" class="form-control" value="<?php echo date('Y-m-d'); ?>">
    </div>
    <div class="filter-group">
        <label>&nbsp;</label>
        <button id="filterBtn" class="btn btn-primary">
            <iconify-icon icon="solar:filter-linear"></iconify-icon> Filter
        </button>
    </div>
</div>

<div class="glass-card">
    <div class="table-responsive">
        <table class="table" id="historyTable">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Menu Item</th>
                    <th>Opening</th>
                    <th>Added</th>
                    <th>Sold</th>
                    <th>Closing</th>
                    <th>Remarks</th>
                    <th>Staff</th>
                </tr>
            </thead>
            <tbody id="historyBody"></tbody>
        </table>
    </div>
    
    <div class="pagination">
        <button class="pagination-btn" id="prevPage" disabled><iconify-icon icon="solar:alt-arrow-left-linear"></iconify-icon> Previous</button>
        <span class="pagination-info">Page <span id="currentPage">1</span> of <span id="totalPages">1</span></span>
        <button class="pagination-btn" id="nextPage">Next <iconify-icon icon="solar:alt-arrow-right-linear"></iconify-icon></button>
    </div>
</div>

<script>
(function($) {
    let currentPage = 1;
    
    $(document).ready(function() {
        loadHistory();
        
        $('#filterBtn').on('click', () => { currentPage = 1; loadHistory(); });
        $('#prevPage').on('click', () => { if (currentPage > 1) { currentPage--; loadHistory(); }});
        $('#nextPage').on('click', () => { currentPage++; loadHistory(); });
    });
    
    function loadHistory() {
        Stand120.ajax('get_order_preparation_history', {
            date_from: $('#dateFrom').val(),
            date_to: $('#dateTo').val(),
            page: currentPage,
            per_page: 20
        }).then(response => {
            if (response.success) {
                const $tbody = $('#historyBody').empty();
                if (response.data.records.length === 0) {
                    $tbody.append('<tr><td colspan="8" style="text-align:center;color:var(--text-muted)">No records found</td></tr>');
                } else {
                    response.data.records.forEach(r => {
                        $tbody.append(`<tr>
                            <td>${r.prep_date}</td>
                            <td>${r.product_name}</td>
                            <td class="formatted-number">${Stand120.formatNumber(r.opening_value)}</td>
                            <td class="formatted-number">${Stand120.formatNumber(r.total_added)}</td>
                            <td class="formatted-number">${Stand120.formatNumber(r.total_sold)}</td>
                            <td class="formatted-number">${Stand120.formatNumber(r.closing_value)}</td>
                            <td>${r.remarks || '-'}</td>
                            <td>${r.staff_name || '-'}</td>
                        </tr>`);
                    });
                }
                $('#currentPage').text(response.data.page);
                $('#totalPages').text(response.data.total_pages);
                $('#prevPage').prop('disabled', response.data.page <= 1);
                $('#nextPage').prop('disabled', response.data.page >= response.data.total_pages);
            }
        });
    }
})(jQuery);
</script>

<?php include STAND120_PLUGIN_DIR . 'templates/partials/footer.php'; ?>
