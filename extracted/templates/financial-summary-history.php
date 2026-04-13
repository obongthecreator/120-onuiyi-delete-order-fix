<?php
/**
 * Financial Summary History Page Template
 */

if (!defined('ABSPATH')) {
    exit;
}

$page_title = 'Financial Summary History - 120 Stand Inventory';
$is_admin = Stand120_Auth::is_admin();
include STAND120_PLUGIN_DIR . 'templates/partials/header.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; margin-bottom: 24px;">
    <h1 class="page-title" style="margin-bottom: 0;">
        <i class="fas fa-history"></i>
        Financial Summary History
    </h1>
    <a href="<?php echo home_url('/120-stand/financial-summary/'); ?>" class="btn btn-primary">
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

<?php if ($is_admin): ?>
<div style="background: rgba(var(--primary-rgb, 99,102,241), 0.1); border: 1px solid rgba(var(--primary-rgb, 99,102,241), 0.3); border-radius: 8px; padding: 10px 16px; margin-bottom: 16px; font-size: 0.9rem; color: var(--text-color);">
    <i class="fas fa-info-circle" style="color: var(--primary-color);"></i>
    <strong>Admin:</strong> Double-click on <strong>Old Cash</strong>, <strong>Card Exp Cash Left</strong>, or <strong>Cash Left</strong> cells to edit them. Changes will automatically propagate to linked days.
</div>
<?php endif; ?>

<style>
    .editable-cell {
        cursor: pointer;
        position: relative;
        border-bottom: 2px dashed rgba(var(--primary-rgb, 99,102,241), 0.4) !important;
    }
    .editable-cell:hover {
        background: rgba(var(--primary-rgb, 99,102,241), 0.08) !important;
    }
    .editable-cell .edit-icon {
        font-size: 0.65rem;
        opacity: 0.4;
        margin-left: 4px;
        vertical-align: middle;
    }
    .editable-cell:hover .edit-icon {
        opacity: 0.8;
    }
</style>

<div class="glass-card">
    <div class="table-responsive">
        <table class="table" id="historyTable">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Total Sales</th>
                    <th>Cash</th>
                    <th>Transfer</th>
                    <th>Extras</th>
                    <th>Extras Remark</th>
                    <th>Cash Expense</th>
                    <th>Cash Expense Remark</th>
                    <th>Card Exp Cash Left</th>
                    <th>Old Cash</th>
                    <th>Cash Left</th>
                </tr>
            </thead>
            <tbody id="historyBody"></tbody>
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
    const isAdmin = <?php echo $is_admin ? 'true' : 'false'; ?>;
    
    $(document).ready(function() {
        loadHistory();
        
        $('#filterBtn').on('click', () => { currentPage = 1; loadHistory(); });
        $('#prevPage').on('click', () => { if (currentPage > 1) { currentPage--; loadHistory(); }});
        $('#nextPage').on('click', () => { currentPage++; loadHistory(); });
        
        // Admin inline editing for old_cash and cash_left
        if (isAdmin) {
            $(document).on('dblclick', '.editable-cell', function() {
                const $cell = $(this);
                if ($cell.find('input').length) return; // Already editing
                
                const currentVal = $cell.data('raw-value') || 0;
                const field = $cell.data('field');
                const recordId = $cell.data('record-id');
                
                $cell.html(`<input type="text" class="table-input number-input inline-edit-input" value="${currentVal}" style="width:100px;padding:4px 8px;font-size:0.9rem;">`);
                const $input = $cell.find('input').focus().select();
                
                $input.on('blur', function() {
                    saveInlineEdit($cell, recordId, field, $(this).val());
                });
                $input.on('keydown', function(e) {
                    if (e.key === 'Enter') { $(this).blur(); }
                    if (e.key === 'Escape') {
                        $cell.html('<span class="editable-value">₦' + Stand120.formatNumber(currentVal) + ' <i class="fas fa-pencil-alt edit-icon"></i></span>');
                        $cell.data('raw-value', currentVal);
                    }
                });
            });
        }
    });
    
    function saveInlineEdit($cell, recordId, field, newVal) {
        const parsed = parseFloat(String(newVal).replace(/[₦,]/g, '')) || 0;
        
        const data = { record_id: recordId };
        data[field] = parsed;
        
        Stand120.ajax('update_financial_summary_record', data).then(response => {
            if (response.success) {
                $cell.html('₦' + Stand120.formatNumber(parsed));
                $cell.data('raw-value', parsed);
                Stand120.showAlert('success', 'Updated successfully');
                // Reload table to show propagated changes to linked days
                loadHistory();
            } else {
                Stand120.showAlert('danger', response.data?.message || 'Update failed');
                $cell.html('<span class="editable-value">₦' + Stand120.formatNumber($cell.data('raw-value') || 0) + ' <i class="fas fa-pencil-alt edit-icon"></i></span>');
            }
        }).catch(() => {
            Stand120.showAlert('danger', 'Update failed');
            $cell.html('<span class="editable-value">₦' + Stand120.formatNumber($cell.data('raw-value') || 0) + ' <i class="fas fa-pencil-alt edit-icon"></i></span>');
        });
    }
    
    function loadHistory() {
        Stand120.ajax('get_financial_summary_history', {
            date_from: $('#dateFrom').val(),
            date_to: $('#dateTo').val(),
            page: currentPage,
            per_page: 20
        }).then(response => {
            if (response.success) {
                const $tbody = $('#historyBody').empty();
                if (response.data.records.length === 0) {
                    $tbody.append('<tr><td colspan="11" style="text-align:center;color:var(--text-muted)">No records found</td></tr>');
                } else {
                    response.data.records.forEach(r => {
                        const extrasRemark = $('<span>').text(r.extras_remark || '-').html();
                        const expensesRemark = $('<span>').text(r.expenses_remark || '-').html();
                        
                        // Old Cash and Cash Left cells: editable by admin (double-click)
                        const oldCashCell = isAdmin
                            ? `<td class="formatted-number editable-cell" data-field="old_cash" data-record-id="${r.id}" data-raw-value="${r.old_cash}" title="Double-click to edit"><span class="editable-value">₦${Stand120.formatNumber(r.old_cash)} <i class="fas fa-pencil-alt edit-icon"></i></span></td>`
                            : `<td class="formatted-number">₦${Stand120.formatNumber(r.old_cash)}</td>`;
                        
                        const cashLeftCell = isAdmin
                            ? `<td class="formatted-number editable-cell" data-field="cash_left" data-record-id="${r.id}" data-raw-value="${r.cash_left}" style="font-weight:600;color:var(--primary-color);" title="Double-click to edit"><span class="editable-value">₦${Stand120.formatNumber(r.cash_left)} <i class="fas fa-pencil-alt edit-icon"></i></span></td>`
                            : `<td class="formatted-number" style="font-weight:600;color:var(--primary-color)">₦${Stand120.formatNumber(r.cash_left)}</td>`;
                        
                        const cardExpCashLeftCell = isAdmin
                            ? `<td class="formatted-number editable-cell" data-field="market_card_expense" data-record-id="${r.id}" data-raw-value="${r.market_card_expense}" title="Double-click to edit"><span class="editable-value">₦${Stand120.formatNumber(r.market_card_expense)} <i class="fas fa-pencil-alt edit-icon"></i></span></td>`
                            : `<td class="formatted-number">₦${Stand120.formatNumber(r.market_card_expense)}</td>`;
                        
                        $tbody.append(`<tr>
                            <td>${r.summary_date}</td>
                            <td class="formatted-number">₦${Stand120.formatNumber(r.total_sales)}</td>
                            <td class="formatted-number">₦${Stand120.formatNumber(r.cash_sales)}</td>
                            <td class="formatted-number">₦${Stand120.formatNumber(r.transfer_sales)}</td>
                            <td class="formatted-number">₦${Stand120.formatNumber(r.extras_amount)}</td>
                            <td>${extrasRemark}</td>
                            <td class="formatted-number">₦${Stand120.formatNumber(r.expenses_amount)}</td>
                            <td>${expensesRemark}</td>
                            ${cardExpCashLeftCell}
                            ${oldCashCell}
                            ${cashLeftCell}
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