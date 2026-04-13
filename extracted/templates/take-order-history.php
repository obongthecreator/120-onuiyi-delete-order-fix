<?php
/**
 * Take Order History Page Template
 */

if (!defined('ABSPATH')) {
    exit;
}

$page_title = 'Order History - 120 Stand Inventory';
$current_user = Stand120_Auth::get_current_user_data();
$is_admin = Stand120_Auth::is_admin();
$is_super_admin = Stand120_Auth::is_super_admin();

include STAND120_PLUGIN_DIR . 'templates/partials/header.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; margin-bottom: 24px;">
    <h1 class="page-title" style="margin-bottom: 0;">
        <iconify-icon icon="solar:history-linear"></iconify-icon>
        Order History
    </h1>
    <a href="<?php echo home_url('/120-stand/take-order/'); ?>" class="btn btn-primary">
        <iconify-icon icon="solar:add-circle-linear"></iconify-icon> New Order
    </a>
</div>

<!-- Filter Section -->
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

<!-- Orders Table -->
<div class="glass-card">
    <div class="table-responsive">
        <table class="table" id="historyTable">
            <thead>
                <tr>
                    <th>Order #</th>
                    <th>Date</th>
                    <th>Time</th>
                    <th>Staff</th>
                    <th>Items</th>
                    <th>Payment</th>
                    <th>Cash</th>
                    <th>Transfer</th>
                    <th>Total</th>
                    <?php if ($is_admin): ?>
                    <th>Action</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody id="historyBody">
                <!-- Data loaded via JavaScript -->
            </tbody>
        </table>
    </div>
    
    <div class="pagination">
        <button class="pagination-btn" id="prevPage" disabled>
            <iconify-icon icon="solar:alt-arrow-left-linear"></iconify-icon> Previous
        </button>
        <span class="pagination-info">Page <span id="currentPage">1</span> of <span id="totalPages">1</span></span>
        <button class="pagination-btn" id="nextPage">
            Next <iconify-icon icon="solar:alt-arrow-right-linear"></iconify-icon>
        </button>
    </div>
</div>

<script>
(function($) {
    let currentPage = 1;
    const perPage = 20;
    const isAdmin = <?php echo $is_admin ? 'true' : 'false'; ?>;
    const isSuperAdmin = <?php echo $is_super_admin ? 'true' : 'false'; ?>;
    const todayStr = '<?php echo date('Y-m-d'); ?>';
    
    $(document).ready(function() {
        loadOrders();
        
        $('#filterBtn').on('click', function() {
            currentPage = 1;
            loadOrders();
        });
        
        $('#prevPage').on('click', function() {
            if (currentPage > 1) {
                currentPage--;
                loadOrders();
            }
        });
        
        $('#nextPage').on('click', function() {
            currentPage++;
            loadOrders();
        });
        
        // Delete order handler
        $(document).on('click', '.delete-order-btn', async function() {
            const orderId = $(this).data('order-id');
            
            const confirmed = await Stand120.showModal({
                title: 'Delete Order',
                content: '<p>Are you sure you want to delete this order? This will also update the financial summary for that day.</p>',
                confirmText: 'Delete Order'
            });
            
            if (!confirmed) return;
            
            Stand120.showLoading('Deleting order...');
            Stand120.ajax('delete_order', { order_id: orderId }).then(response => {
                Stand120.hideLoading();
                if (response.success) {
                    Stand120.showAlert('success', response.data.message || 'Order deleted successfully');
                    loadOrders();
                } else {
                    Stand120.showAlert('danger', response.data?.message || 'Failed to delete order');
                }
            }).catch(() => {
                Stand120.hideLoading();
                Stand120.showAlert('danger', 'Failed to delete order');
            });
        });
    });
    
    function loadOrders() {
        Stand120.ajax('get_orders', {
            date_from: $('#dateFrom').val(),
            date_to: $('#dateTo').val(),
            page: currentPage,
            per_page: perPage
        }).then(response => {
            if (response.success) {
                renderOrders(response.data.orders);
                updatePagination(response.data);
            }
        });
    }
    
    function renderOrders(orders) {
        const $tbody = $('#historyBody');
        $tbody.empty();
        const colSpan = isAdmin ? 10 : 9;
        
        if (orders.length === 0) {
            $tbody.append(`<tr><td colspan="${colSpan}" style="text-align: center; color: var(--text-muted);">No orders found</td></tr>`);
            return;
        }
        
        let totalCash = 0;
        let totalTransfer = 0;
        let totalGrand = 0;
        
        orders.forEach(order => {
            const items = order.items ? order.items.map(i => i.product_name + ' x' + i.quantity).join(', ') : '-';
            const canDelete = isSuperAdmin || (isAdmin && order.order_date === todayStr);
            const cashAmt = parseFloat(order.cash_amount) || 0;
            const transferAmt = parseFloat(order.transfer_amount) || 0;
            const grandAmt = parseFloat(order.grand_total) || 0;
            
            totalCash += cashAmt;
            totalTransfer += transferAmt;
            totalGrand += grandAmt;
            
            let actionCol = '';
            if (isAdmin) {
                if (canDelete) {
                    actionCol = `<td><button class="btn btn-sm btn-danger delete-order-btn" data-order-id="${order.id}" style="padding: 4px 10px; border-radius: 8px; font-size: 0.8rem;"><iconify-icon icon="solar:trash-bin-trash-linear"></iconify-icon></button></td>`;
                } else {
                    actionCol = `<td><span style="color: var(--text-muted); font-size: 0.75rem;">—</span></td>`;
                }
            }
            
            const row = `
                <tr>
                    <td>#${order.id}</td>
                    <td>${order.order_date}</td>
                    <td>${order.order_time}</td>
                    <td>${order.staff_name || '-'}</td>
                    <td style="max-width: 200px; overflow: hidden; text-overflow: ellipsis;" title="${items}">${items}</td>
                    <td>${order.payment_method}</td>
                    <td class="formatted-number"><span class="naira">₦</span>${Stand120.formatNumber(cashAmt)}</td>
                    <td class="formatted-number"><span class="naira">₦</span>${Stand120.formatNumber(transferAmt)}</td>
                    <td class="formatted-number"><span class="naira">₦</span>${Stand120.formatNumber(grandAmt)}</td>
                    ${actionCol}
                </tr>
            `;
            $tbody.append(row);
        });
        
        // Summary totals row
        const summaryRow = `
            <tr style="font-weight: bold; background: rgba(139, 0, 0, 0.05); border-top: 2px solid var(--primary-color);">
                <td colspan="6" style="text-align: right;">Page Totals:</td>
                <td class="formatted-number"><span class="naira">₦</span>${Stand120.formatNumber(totalCash)}</td>
                <td class="formatted-number"><span class="naira">₦</span>${Stand120.formatNumber(totalTransfer)}</td>
                <td class="formatted-number"><span class="naira">₦</span>${Stand120.formatNumber(totalGrand)}</td>
                ${isAdmin ? '<td></td>' : ''}
            </tr>
        `;
        $tbody.append(summaryRow);
    }
    
    function updatePagination(data) {
        $('#currentPage').text(data.page);
        $('#totalPages').text(data.total_pages);
        $('#prevPage').prop('disabled', data.page <= 1);
        $('#nextPage').prop('disabled', data.page >= data.total_pages);
    }
})(jQuery);
</script>

<?php include STAND120_PLUGIN_DIR . 'templates/partials/footer.php'; ?>
