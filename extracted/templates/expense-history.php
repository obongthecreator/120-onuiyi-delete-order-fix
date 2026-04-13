<?php
/**
 * Card Expense History Page Template
 */

if (!defined('ABSPATH')) {
    exit;
}

$page_title = 'Card Expense History - 120 Stand Inventory';
include STAND120_PLUGIN_DIR . 'templates/partials/header.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; margin-bottom: 24px;">
    <h1 class="page-title" style="margin-bottom: 0;">
        <i class="fas fa-history"></i>
        Card Expense History
    </h1>
    <a href="<?php echo home_url('/120-stand/expense-record/'); ?>" class="btn btn-primary">
        <i class="fas fa-arrow-left"></i> Back
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
            <i class="fas fa-filter"></i> Filter
        </button>
    </div>
</div>

<div class="glass-card">
    <div class="table-responsive">
        <table class="table" id="expenseHistoryTable">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Description</th>
                    <th>Amount (₦)</th>
                </tr>
            </thead>
            <tbody id="expenseHistoryBody"></tbody>
        </table>
    </div>
    
    <div class="pagination">
        <button class="pagination-btn" id="prevPage" disabled><i class="fas fa-chevron-left"></i> Previous</button>
        <span class="pagination-info">Page <span id="currentPage">1</span> of <span id="totalPages">1</span></span>
        <button class="pagination-btn" id="nextPage">Next <i class="fas fa-chevron-right"></i></button>
    </div>
</div>

<script>
(function($) {
    let currentPage = 1;
    
    $(document).ready(function() {
        loadExpenseHistory();
        
        $('#filterBtn').on('click', () => { currentPage = 1; loadExpenseHistory(); });
        $('#prevPage').on('click', () => { if (currentPage > 1) { currentPage--; loadExpenseHistory(); }});
        $('#nextPage').on('click', () => { currentPage++; loadExpenseHistory(); });
    });
    
    function loadExpenseHistory() {
        Stand120.ajax('get_expense_history', {
            date_from: $('#dateFrom').val(),
            date_to: $('#dateTo').val(),
            page: currentPage,
            per_page: 50
        }).then(response => {
            if (response.success) {
                const $tbody = $('#expenseHistoryBody').empty();
                const records = response.data.records || [];
                const dailyTotals = {};
                
                // Build daily totals map
                if (response.data.daily_totals) {
                    response.data.daily_totals.forEach(dt => {
                        dailyTotals[dt.expense_date] = parseFloat(dt.daily_total) || 0;
                    });
                }
                
                if (records.length === 0) {
                    $tbody.append('<tr><td colspan="3" style="text-align:center;color:var(--text-muted)">No expense records found</td></tr>');
                } else {
                    let lastDate = '';
                    let dateItemCount = 0;
                    
                    // Group records by date and add daily total rows
                    const groupedRecords = {};
                    records.forEach(r => {
                        if (!groupedRecords[r.expense_date]) {
                            groupedRecords[r.expense_date] = [];
                        }
                        groupedRecords[r.expense_date].push(r);
                    });
                    
                    Object.keys(groupedRecords).sort().reverse().forEach(date => {
                        const items = groupedRecords[date];
                        const total = dailyTotals[date] || 0;
                        
                        // Date header row
                        $tbody.append(`<tr style="background: var(--bg-glass);">
                            <td colspan="2" style="font-weight: 600; color: var(--primary-color);">
                                <i class="fas fa-calendar-day"></i> ${date}
                            </td>
                            <td class="formatted-number" style="font-weight: 600; color: var(--primary-color);">
                                <span class="naira">₦</span>${Stand120.formatNumber(total)}
                            </td>
                        </tr>`);
                        
                        // Individual items
                        items.forEach(r => {
                            $tbody.append(`<tr>
                                <td></td>
                                <td>${r.description || '—'}</td>
                                <td class="formatted-number"><span class="naira">₦</span>${Stand120.formatNumber(r.amount)}</td>
                            </tr>`);
                        });
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