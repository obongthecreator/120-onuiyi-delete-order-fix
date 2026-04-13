<?php
/**
 * Import Record History Page Template
 */

if (!defined('ABSPATH')) {
    exit;
}

$page_title = 'Import Record History - 120 Stand Inventory';
include STAND120_PLUGIN_DIR . 'templates/partials/header.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; margin-bottom: 24px;">
    <h1 class="page-title" style="margin-bottom: 0;">
        <iconify-icon icon="solar:history-linear"></iconify-icon>
        Import Record History
    </h1>
    <a href="<?php echo home_url('/120-stand/import-record/'); ?>" class="btn btn-primary">
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
                    <th>Product</th>
                    <th>Type</th>
                    <th>Quantity</th>
                    <th>Status</th>
                    <th>Staff</th>
                    <th>Updated</th>
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
        Stand120.ajax('get_import_history', {
            date_from: $('#dateFrom').val(),
            date_to: $('#dateTo').val(),
            page: currentPage,
            per_page: 20
        }).then(response => {
            if (response.success) {
                const $tbody = $('#historyBody').empty();
                if (response.data.records.length === 0) {
                    $tbody.append('<tr><td colspan="7" style="text-align:center;color:var(--text-muted)">No records found</td></tr>');
                } else {
                    response.data.records.forEach(r => {
                        const statusClass = r.sync_status === 'synced' ? 'status-synced' : 'status-pending';
                        $tbody.append(`<tr>
                            <td>${r.import_date}</td>
                            <td>${r.product_name}</td>
                            <td><span class="badge badge-${r.product_type}">${r.product_type}</span></td>
                            <td class="formatted-number">${Stand120.formatNumber(r.quantity_imported)}</td>
                            <td><span class="status-badge ${statusClass}">${r.sync_status}</span></td>
                            <td>${r.staff_name || '-'}</td>
                            <td>${r.updated_at || '-'}</td>
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

<style>
    .badge { display: inline-block; padding: 3px 8px; border-radius: 12px; font-size: 0.7rem; font-weight: 500; text-transform: uppercase; }
    .badge-fruit { background: rgba(40, 167, 69, 0.2); color: #28a745; }
    .badge-non_fruit { background: rgba(23, 162, 184, 0.2); color: #17a2b8; }
</style>

<?php include STAND120_PLUGIN_DIR . 'templates/partials/footer.php'; ?>
