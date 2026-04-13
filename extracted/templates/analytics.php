<?php
/**
 * Analytics Page Template
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!Stand120_Auth::is_admin()) {
    wp_redirect(home_url('/120-stand/'));
    exit;
}

$page_title = 'Analytics - 120 Stand Inventory';
$current_user = Stand120_Auth::get_current_user_data();

include STAND120_PLUGIN_DIR . 'templates/partials/header.php';
?>

<style>
    .analytics-chart-container {
        position: relative;
        height: 300px;
        margin-bottom: 16px;
    }
    .charts-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
    }
    .insight-item {
        display: flex;
        align-items: flex-start;
        gap: 10px;
        padding: 12px 16px;
        margin-bottom: 8px;
        background: rgba(139, 0, 0, 0.04);
        border-left: 3px solid var(--primary-color, #8B0000);
        border-radius: 0 8px 8px 0;
        font-size: 0.95rem;
        line-height: 1.5;
        color: var(--text-color, #333);
    }
    .insight-item iconify-icon {
        font-size: 1.2rem;
        flex-shrink: 0;
        margin-top: 2px;
    }
    .insight-item.warning {
        background: rgba(244, 67, 54, 0.06);
        border-left-color: #f44336;
    }
    .insight-item.success {
        background: rgba(76, 175, 80, 0.06);
        border-left-color: #4caf50;
    }
    .insights-list {
        list-style: none;
        padding: 0;
        margin: 0;
    }
    #insightsLoading, #availabilityLoading {
        text-align: center;
        color: var(--text-muted, #888);
        padding: 20px;
    }
    .availability-recommendation {
        margin-top: 16px;
        padding: 12px 16px;
        background: rgba(139, 0, 0, 0.04);
        border-radius: 8px;
        font-size: 0.95rem;
        line-height: 1.6;
        color: var(--text-color, #333);
    }
    @media (max-width: 768px) {
        .charts-row {
            grid-template-columns: 1fr;
        }
        .analytics-chart-container {
            height: 250px;
        }
    }
</style>

<h1 class="page-title">
    <iconify-icon icon="solar:graph-up-linear"></iconify-icon>
    Analytics Dashboard
</h1>

<!-- Period Filter -->
<div class="filter-section">
    <div class="filter-group">
        <label>Period</label>
        <select id="periodFilter" class="form-control">
            <option value="daily">Daily</option>
            <option value="weekly">Weekly</option>
            <option value="monthly">Monthly</option>
            <option value="yearly">Yearly</option>
        </select>
    </div>
    <div class="filter-group">
        <label>Date</label>
        <input type="date" id="periodDate" class="form-control" value="<?php echo date('Y-m-d'); ?>">
    </div>
    <div class="filter-group">
        <label>&nbsp;</label>
        <button id="loadAnalytics" class="btn btn-primary">
            <iconify-icon icon="solar:refresh-linear"></iconify-icon> Load Analytics
        </button>
    </div>
</div>

<!-- Overview Cards -->
<div class="summary-cards">
    <div class="summary-card glass-card">
        <div class="summary-card-icon">
            <iconify-icon icon="solar:graph-up-linear"></iconify-icon>
        </div>
        <span class="summary-card-label">Revenue</span>
        <span id="revenueAnalytics" class="summary-card-value"><span class="naira">₦</span>0</span>
    </div>

    <div class="summary-card glass-card">
        <div class="summary-card-icon">
            <iconify-icon icon="solar:pie-chart-2-linear"></iconify-icon>
        </div>
        <span class="summary-card-label">Profit</span>
        <span id="profitAnalytics" class="summary-card-value"><span class="naira">₦</span>0</span>
    </div>

    <div class="summary-card glass-card">
        <div class="summary-card-icon">
            <iconify-icon icon="solar:minus-circle-linear"></iconify-icon>
        </div>
        <span class="summary-card-label">Expenses</span>
        <span id="expensesAnalytics" class="summary-card-value"><span class="naira">₦</span>0</span>
    </div>

    <div class="summary-card glass-card">
        <div class="summary-card-icon">
            <iconify-icon icon="solar:arrow-down-linear"></iconify-icon>
        </div>
        <span class="summary-card-label">Loss</span>
        <span id="lossAnalytics" class="summary-card-value"><span class="naira">₦</span>0</span>
    </div>
</div>

<div class="summary-cards">
    <div class="summary-card glass-card">
        <div class="summary-card-icon">
            <iconify-icon icon="solar:money-bag-linear"></iconify-icon>
        </div>
        <span class="summary-card-label">Total Sales</span>
        <span id="totalSalesAnalytics" class="summary-card-value"><span class="naira">₦</span>0</span>
    </div>

    <div class="summary-card glass-card">
        <div class="summary-card-icon">
            <iconify-icon icon="solar:cart-large-2-linear"></iconify-icon>
        </div>
        <span class="summary-card-label">Total Orders</span>
        <span id="totalOrdersAnalytics" class="summary-card-value">0</span>
    </div>

    <div class="summary-card glass-card">
        <div class="summary-card-icon">
            <iconify-icon icon="solar:calculator-linear"></iconify-icon>
        </div>
        <span class="summary-card-label">Avg Order Value</span>
        <span id="avgOrderAnalytics" class="summary-card-value"><span class="naira">₦</span>0</span>
    </div>
</div>

<!-- Revenue Distribution & Top Products Charts -->
<div class="charts-row">
    <div class="glass-card">
        <h3 style="margin-bottom: 20px; color: var(--primary-color);">
            <iconify-icon icon="solar:pie-chart-2-linear"></iconify-icon> Revenue Distribution
        </h3>
        <div class="analytics-chart-container" style="position: relative; height: 300px; margin-bottom: 16px;">
            <canvas id="revenueDistributionChart"></canvas>
        </div>
    </div>
    <div class="glass-card">
        <h3 style="margin-bottom: 20px; color: var(--primary-color);">
            <iconify-icon icon="solar:cup-star-linear"></iconify-icon> Top Products by Revenue
        </h3>
        <div class="analytics-chart-container" style="position: relative; height: 300px; margin-bottom: 16px;">
            <canvas id="topProductsPieChart"></canvas>
        </div>
    </div>
</div>

<!-- Daily Sales Trend Chart -->
<div class="glass-card">
    <h3 style="margin-bottom: 20px; color: var(--primary-color);">
        <iconify-icon icon="solar:chart-2-linear"></iconify-icon> Daily Sales Trend
    </h3>
    <div class="analytics-chart-container" style="position: relative; height: 300px; margin-bottom: 16px;">
        <canvas id="dailySalesTrendChart"></canvas>
    </div>
</div>

<!-- AI-Powered Smart Selling Insights -->
<div class="glass-card">
    <h3 style="margin-bottom: 20px; color: var(--primary-color);">
        💡 Smart Selling Insights
    </h3>
    <div id="insightsLoading">
        <iconify-icon icon="solar:refresh-linear" style="animation: spin 1s linear infinite;"></iconify-icon>
        Analyzing data...
    </div>
    <ul id="insightsList" class="insights-list" style="display:none;"></ul>
</div>

<!-- Product Availability Timing Recommendations -->
<div class="glass-card">
    <h3 style="margin-bottom: 20px; color: var(--primary-color);">
        ⏰ Product Availability Schedule
    </h3>
    <div id="availabilityLoading">
        <iconify-icon icon="solar:refresh-linear" style="animation: spin 1s linear infinite;"></iconify-icon>
        Loading insights...
    </div>
    <div id="availabilityContent" style="display:none;">
        <div class="table-responsive">
            <table class="table" id="availabilityTable">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Best Time of Day</th>
                        <th>Peak Qty</th>
                        <th>Best Day</th>
                        <th>Peak Day Qty</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
        <div id="availabilityRecommendations" class="availability-recommendation"></div>
    </div>
</div>

<!-- Target Setting Section -->
<div class="glass-card">
    <h3 style="margin-bottom: 20px; color: var(--primary-color);">
        <iconify-icon icon="solar:target-linear"></iconify-icon> Sales Target Recommendation
    </h3>
    <div style="display: flex; gap: 12px; align-items: flex-end; flex-wrap: wrap; margin-bottom: 20px;">
        <div class="filter-group">
            <label>Target Amount (₦)</label>
            <input type="text" id="targetAmount" class="form-control number-input" placeholder="Enter target amount">
        </div>
        <div class="filter-group">
            <label>&nbsp;</label>
            <button id="calculateTarget" class="btn btn-primary">
                <iconify-icon icon="solar:calculator-linear"></iconify-icon> Calculate
            </button>
        </div>
    </div>
    <div id="targetResult" style="display:none;">
        <div id="targetSummary" style="color: var(--text-muted); margin-bottom: 12px;"></div>
        <div class="table-responsive">
            <table class="table" id="targetRecommendationTable">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Unit Price</th>
                        <th>Suggested Qty</th>
                        <th>Avg Daily Sales</th>
                        <th>Projected Revenue</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>

<!-- Product Revenue Attribution -->
<div class="glass-card">
    <h3 style="margin-bottom: 20px; color: var(--primary-color);">
        <iconify-icon icon="solar:graph-up-linear"></iconify-icon> Product Revenue Attribution
    </h3>
    <div class="table-responsive">
        <table class="table" id="productRevenueTable">
            <thead>
                <tr>
                    <th>Product</th>
                    <th>Qty Sold</th>
                    <th>Unit Price</th>
                    <th>Revenue</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</div>

<!-- Detailed Expenses Breakdown -->
<div class="glass-card">
    <h3 style="margin-bottom: 20px; color: var(--primary-color);">
        <iconify-icon icon="solar:minus-circle-linear"></iconify-icon> Detailed Expenses Breakdown
    </h3>
    <div class="table-responsive">
        <table class="table" id="detailedExpensesTable">
            <thead>
                <tr>
                    <th>Description</th>
                    <th>Quantity</th>
                    <th>Total Amount</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</div>

<!-- Staff Performance -->
<div class="glass-card">
    <h3 style="margin-bottom: 20px; color: var(--primary-color);">
        <iconify-icon icon="solar:users-group-two-rounded-linear"></iconify-icon> Staff Performance
    </h3>
    <div class="table-responsive">
        <table class="table" id="staffPerformanceTable">
            <thead>
                <tr>
                    <th>Staff Name</th>
                    <th>Orders</th>
                    <th>Total Sales</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</div>

<!-- Top Products -->
<div class="glass-card">
    <h3 style="margin-bottom: 20px; color: var(--primary-color);">
        <iconify-icon icon="solar:cup-star-linear"></iconify-icon> Top Selling Products
    </h3>
    <div class="table-responsive">
        <table class="table" id="topProductsTable">
            <thead>
                <tr>
                    <th>Product</th>
                    <th>Quantity Sold</th>
                    <th>Revenue</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</div>

<!-- Order Preparation Analytics -->
<div class="glass-card">
    <h3 style="margin-bottom: 20px; color: var(--primary-color);">
        <iconify-icon icon="solar:chef-hat-linear"></iconify-icon> Order Preparation Analytics
    </h3>
    <div id="prepSummary" style="color: var(--text-muted); margin-bottom: 12px;"></div>
    <div class="table-responsive">
        <table class="table" id="prepAnalyticsTable">
            <thead>
                <tr>
                    <th>Menu Item</th>
                    <th>Total Added</th>
                    <th>Total Sold</th>
                    <th>Closing</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</div>

<!-- Stock Inventory Analytics -->
<div class="glass-card">
    <h3 style="margin-bottom: 20px; color: var(--primary-color);">
        <iconify-icon icon="solar:box-linear"></iconify-icon> Stock Inventory Analytics
    </h3>
    <div id="stockSummary" style="color: var(--text-muted); margin-bottom: 12px;"></div>
    <div class="table-responsive">
        <table class="table" id="stockAnalyticsTable">
            <thead>
                <tr>
                    <th>Product</th>
                    <th>Added Packs</th>
                    <th>Used Packs</th>
                    <th>Closing Packs</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</div>

<!-- Chopping Inventory Analytics -->
<div class="glass-card">
    <h3 style="margin-bottom: 20px; color: var(--primary-color);">
        <iconify-icon icon="solar:scissors-linear"></iconify-icon> Chopping Inventory Analytics
    </h3>
    <div id="chopSummary" style="color: var(--text-muted); margin-bottom: 12px;"></div>
    <div class="table-responsive">
        <table class="table" id="chopAnalyticsTable">
            <thead>
                <tr>
                    <th>Fruit</th>
                    <th>Imported Whole</th>
                    <th>Prepared Whole</th>
                    <th>Packs Gotten</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</div>

<!-- Import Records Analytics -->
<div class="glass-card">
    <h3 style="margin-bottom: 20px; color: var(--primary-color);">
        <iconify-icon icon="solar:delivery-linear"></iconify-icon> Import Records Analytics
    </h3>
    <div id="importSummary" style="color: var(--text-muted); margin-bottom: 12px;"></div>
    <div class="table-responsive">
        <table class="table" id="importAnalyticsTable">
            <thead>
                <tr>
                    <th>Product</th>
                    <th>Type</th>
                    <th>Quantity Imported</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</div>

<!-- Financial Summary Analytics -->
<div class="glass-card">
    <h3 style="margin-bottom: 20px; color: var(--primary-color);">
        <iconify-icon icon="solar:wallet-linear"></iconify-icon> Financial Summary Analytics
    </h3>
    <div class="table-responsive">
        <table class="table" id="financialAnalyticsTable">
            <thead>
                <tr>
                    <th>Total Sales</th>
                    <th>Cash Sales</th>
                    <th>Transfer Sales</th>
                    <th>Delivery Fees</th>
                    <th>Extras</th>
                    <th>Expenses</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
<script>
(function($) {
    let revenueDistChart = null;
    let topProductsPieChart = null;
    let dailySalesTrendChart = null;

    const chartColors = [
        '#8B0000', '#D4A017', '#2E7D32', '#1565C0', '#6A1B9A',
        '#EF6C00', '#00838F', '#AD1457', '#4E342E', '#37474F',
        '#C62828', '#F9A825', '#2E7D32', '#0277BD', '#7B1FA2'
    ];

    $(document).ready(function() {
        loadAnalytics();
        loadAvailabilityData();

        $('#loadAnalytics').on('click', function() {
            loadAnalytics();
            loadAvailabilityData();
        });
        $('#periodFilter, #periodDate').on('change', function() {
            loadAnalytics();
            loadAvailabilityData();
        });
        $('#calculateTarget').on('click', calculateTarget);
    });

    function destroyCharts() {
        if (revenueDistChart) { revenueDistChart.destroy(); revenueDistChart = null; }
        if (topProductsPieChart) { topProductsPieChart.destroy(); topProductsPieChart = null; }
        if (dailySalesTrendChart) { dailySalesTrendChart.destroy(); dailySalesTrendChart = null; }
    }

    function renderRevenueDistributionChart(financials) {
        const ctx = document.getElementById('revenueDistributionChart');
        if (!ctx) return;

        const cashSales = parseFloat(financials.cash_sales) || 0;
        const transferSales = parseFloat(financials.transfer_sales) || 0;
        const deliveryFees = parseFloat(financials.delivery_fees) || 0;
        const total = cashSales + transferSales + deliveryFees;

        if (total === 0) {
            if (revenueDistChart) { revenueDistChart.destroy(); revenueDistChart = null; }
            ctx.parentElement.innerHTML = '<p style="color: var(--text-muted); text-align: center; padding-top: 100px;">No revenue data for this period</p>';
            return;
        }

        revenueDistChart = new Chart(ctx, {
            type: 'pie',
            data: {
                labels: ['Cash Sales', 'Transfer Sales', 'Delivery Fees'],
                datasets: [{
                    data: [cashSales, transferSales, deliveryFees],
                    backgroundColor: ['#8B0000', '#D4A017', '#2E7D32'],
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom', labels: { padding: 16, usePointStyle: true } },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const val = context.parsed;
                                const pct = total > 0 ? ((val / total) * 100).toFixed(1) : 0;
                                return context.label + ': ₦' + Stand120.formatNumber(val) + ' (' + pct + '%)';
                            }
                        }
                    }
                }
            }
        });
    }

    function renderTopProductsPieChart(topProducts) {
        const ctx = document.getElementById('topProductsPieChart');
        if (!ctx) return;

        if (!topProducts || topProducts.length === 0) {
            if (topProductsPieChart) { topProductsPieChart.destroy(); topProductsPieChart = null; }
            ctx.parentElement.innerHTML = '<p style="color: var(--text-muted); text-align: center; padding-top: 100px;">No product data for this period</p>';
            return;
        }

        const sliceCount = Math.min(topProducts.length, 8);
        const sliced = topProducts.slice(0, sliceCount);
        const labels = sliced.map(p => p.product_name);
        const values = sliced.map(p => parseFloat(p.revenue) || 0);
        const colors = sliced.map((_, i) => chartColors[i % chartColors.length]);
        const total = values.reduce((a, b) => a + b, 0);

        topProductsPieChart = new Chart(ctx, {
            type: 'pie',
            data: {
                labels: labels,
                datasets: [{
                    data: values,
                    backgroundColor: colors,
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom', labels: { padding: 12, usePointStyle: true, font: { size: 11 } } },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const val = context.parsed;
                                const pct = total > 0 ? ((val / total) * 100).toFixed(1) : 0;
                                return context.label + ': ₦' + Stand120.formatNumber(val) + ' (' + pct + '%)';
                            }
                        }
                    }
                }
            }
        });
    }

    function renderDailySalesTrendChart(dailySales) {
        const ctx = document.getElementById('dailySalesTrendChart');
        if (!ctx) return;

        if (!dailySales || dailySales.length === 0) {
            if (dailySalesTrendChart) { dailySalesTrendChart.destroy(); dailySalesTrendChart = null; }
            ctx.parentElement.innerHTML = '<p style="color: var(--text-muted); text-align: center; padding-top: 100px;">No sales data for this period</p>';
            return;
        }

        const labels = dailySales.map(d => {
            const date = new Date(d.order_date);
            return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
        });
        const values = dailySales.map(d => parseFloat(d.total) || 0);

        dailySalesTrendChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Daily Sales (₦)',
                    data: values,
                    backgroundColor: 'rgba(139, 0, 0, 0.7)',
                    borderColor: '#8B0000',
                    borderWidth: 1,
                    borderRadius: 4,
                    hoverBackgroundColor: '#5C0000'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) { return '₦' + Stand120.formatNumber(value); }
                        },
                        grid: { color: 'rgba(0,0,0,0.05)' }
                    },
                    x: {
                        grid: { display: false }
                    }
                },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return '₦' + Stand120.formatNumber(context.parsed.y);
                            }
                        }
                    }
                }
            }
        });
    }

    function generateSmartInsights(data) {
        const insights = [];
        const revenue = parseFloat(data.revenue) || 0;
        const profit = parseFloat(data.profit) || 0;
        const totalAllExpenses = parseFloat(data.total_all_expenses) || 0;
        const loss = parseFloat(data.loss) || 0;
        const totalSales = parseFloat(data.total_sales) || 0;
        const totalOrders = parseInt(data.total_orders) || 0;
        const avgOrder = totalOrders > 0 ? totalSales / totalOrders : 0;
        const topProducts = data.top_products || [];
        const productRevenue = data.product_revenue || [];
        const dailySales = data.daily_sales || [];
        const dowSales = data.dow_sales || [];
        const preparation = data.preparation || {};

        // Best selling product
        if (topProducts.length > 0) {
            const best = topProducts[0];
            const qtySold = parseInt(best.qty_sold) || 0;
            const secondBest = topProducts.length > 1 ? topProducts[1].product_name : null;
            let tip = 'Your best selling product is <strong>' + best.product_name + '</strong> with <strong>' + Stand120.formatNumber(qtySold) + '</strong> units sold.';
            if (secondBest) {
                tip += ' Consider bundling it with <strong>' + secondBest + '</strong> for combo deals.';
            }
            insights.push({ icon: 'solar:star-linear', text: tip, type: '' });
        }

        // Revenue concentration
        if (topProducts.length > 0 && revenue > 0) {
            const topRev = parseFloat(topProducts[0].revenue) || 0;
            const pct = ((topRev / revenue) * 100).toFixed(1);
            if (parseFloat(pct) > 40) {
                insights.push({
                    icon: 'solar:danger-triangle-linear',
                    text: '<strong>' + topProducts[0].product_name + '</strong> accounts for <strong>' + pct + '%</strong> of total revenue. Consider diversifying to reduce over-dependence on a single product.',
                    type: 'warning'
                });
            } else {
                insights.push({
                    icon: 'solar:verified-check-linear',
                    text: 'Revenue is well distributed. Top product (<strong>' + topProducts[0].product_name + '</strong>) is <strong>' + pct + '%</strong> of revenue — healthy diversification.',
                    type: 'success'
                });
            }
        }

        // Profit margin
        if (revenue > 0) {
            const profitMargin = ((profit / revenue) * 100).toFixed(1);
            if (profit < 0) {
                insights.push({
                    icon: 'solar:danger-circle-linear',
                    text: '<strong>Warning:</strong> Operating at a loss of ₦' + Stand120.formatNumber(Math.abs(profit)) + '. Review expenses or consider increasing prices.',
                    type: 'warning'
                });
            } else if (parseFloat(profitMargin) < 15) {
                insights.push({
                    icon: 'solar:info-circle-linear',
                    text: 'Profit margin is <strong>' + profitMargin + '%</strong> — relatively thin. Look for ways to reduce costs or add premium offerings.',
                    type: 'warning'
                });
            } else {
                insights.push({
                    icon: 'solar:graph-up-linear',
                    text: 'Healthy profit margin of <strong>' + profitMargin + '%</strong>. Keep up the great work!',
                    type: 'success'
                });
            }
        }

        // Day-of-week trend
        if (dowSales && dowSales.length > 0) {
            let bestDay = dowSales[0];
            let worstDay = dowSales[0];
            dowSales.forEach(d => {
                if ((parseFloat(d.total) || 0) > (parseFloat(bestDay.total) || 0)) bestDay = d;
                if ((parseFloat(d.total) || 0) < (parseFloat(worstDay.total) || 0)) worstDay = d;
            });
            const bestDayName = bestDay.day_name || 'Best Day';
            const worstDayName = worstDay.day_name || 'Worst Day';
            if (bestDay.dow !== worstDay.dow) {
                insights.push({
                    icon: 'solar:calendar-linear',
                    text: 'Revenue peaks on <strong>' + bestDayName + '</strong> (₦' + Stand120.formatNumber(bestDay.total) + '). Schedule promotions for <strong>' + worstDayName + '</strong> to boost slower days.',
                    type: ''
                });
            }
        }

        // Average order value tips
        if (avgOrder > 0 && totalOrders > 0) {
            const suggestedCombo = Math.ceil(avgOrder * 1.3 / 100) * 100;
            insights.push({
                icon: 'solar:cart-check-linear',
                text: 'Average order is <strong>₦' + Stand120.formatNumber(avgOrder) + '</strong>. Consider combo deals above <strong>₦' + Stand120.formatNumber(suggestedCombo) + '</strong> to increase the average order value.',
                type: ''
            });
        }

        // Expense control
        if (totalAllExpenses > 0 && revenue > 0) {
            const expPct = ((totalAllExpenses / revenue) * 100).toFixed(1);
            if (parseFloat(expPct) > 30) {
                insights.push({
                    icon: 'solar:money-bag-linear',
                    text: 'Expenses are <strong>' + expPct + '%</strong> of revenue (₦' + Stand120.formatNumber(totalAllExpenses) + '). This is above the 30% threshold — review spending to improve margins.',
                    type: 'warning'
                });
            }
        }

        // Stock turnover from preparation data
        if (preparation.records && preparation.records.length > 0) {
            let highWaste = [];
            preparation.records.forEach(rec => {
                const added = parseFloat(rec.total_added) || 0;
                const sold = parseFloat(rec.total_sold) || 0;
                if (added > 0) {
                    const turnover = (sold / added) * 100;
                    if (turnover < 50) {
                        highWaste.push(rec.product_name);
                    }
                }
            });
            if (highWaste.length > 0) {
                insights.push({
                    icon: 'solar:trash-bin-trash-linear',
                    text: 'Low stock turnover for: <strong>' + highWaste.join(', ') + '</strong>. Less than 50% of prepared stock was sold — consider preparing smaller batches.',
                    type: 'warning'
                });
            }
        }

        // Render insights
        const $list = $('#insightsList').empty();
        if (insights.length === 0) {
            $list.append('<li class="insight-item">No insights available for this period. Try loading more data.</li>');
        } else {
            insights.forEach(insight => {
                $list.append(
                    '<li class="insight-item ' + insight.type + '">' +
                    '<iconify-icon icon="' + insight.icon + '"></iconify-icon>' +
                    '<span>' + insight.text + '</span>' +
                    '</li>'
                );
            });
        }
        $('#insightsLoading').hide();
        $list.show();
    }

    function loadAvailabilityData() {
        $('#availabilityLoading').show();
        $('#availabilityContent').hide();

        Stand120.ajax('get_sales_insights', {}).then(function(response) {
            $('#availabilityLoading').hide();

            if (response.success && response.data) {
                const items = response.data.recommendations || [];
                const $tbody = $('#availabilityTable tbody').empty();
                const recommendations = [];

                if (Array.isArray(items) && items.length > 0) {
                    items.forEach(function(item) {
                        const productName = item.product_name || 'Unknown';
                        const bestTime = item.best_time || '-';
                        const peakQty = item.best_time_qty || 0;
                        const bestDay = item.best_day || '-';
                        const peakDayQty = item.best_day_qty || 0;

                        $tbody.append(
                            '<tr>' +
                            '<td>' + productName + '</td>' +
                            '<td>' + bestTime + '</td>' +
                            '<td class="formatted-number">' + Stand120.formatNumber(peakQty) + '</td>' +
                            '<td>' + bestDay + '</td>' +
                            '<td class="formatted-number">' + Stand120.formatNumber(peakDayQty) + '</td>' +
                            '</tr>'
                        );

                        if (bestTime !== '-') {
                            recommendations.push('Have <strong>' + productName + '</strong> ready by <strong>' + bestTime + '</strong> as it sells most during that period.');
                        }
                    });
                } else {
                    $tbody.append('<tr><td colspan="5" style="text-align:center;color:var(--text-muted)">No availability data yet</td></tr>');
                }

                const $recs = $('#availabilityRecommendations');
                if (recommendations.length > 0) {
                    $recs.html('<strong>Recommendations:</strong><br>' + recommendations.join('<br>'));
                } else {
                    $recs.html('<em style="color: var(--text-muted);">Not enough data to generate timing recommendations yet.</em>');
                }

                $('#availabilityContent').show();
            } else {
                $('#availabilityContent').show();
                $('#availabilityTable tbody').html('<tr><td colspan="5" style="text-align:center;color:var(--text-muted)">Could not load availability data</td></tr>');
                $('#availabilityRecommendations').html('');
            }
        }).fail(function() {
            $('#availabilityLoading').hide();
            $('#availabilityContent').show();
            $('#availabilityTable tbody').html('<tr><td colspan="5" style="text-align:center;color:var(--text-muted)">Failed to load availability data</td></tr>');
            $('#availabilityRecommendations').html('');
        });
    }

    function calculateTarget() {
        const targetAmount = Stand120.parseNumber($('#targetAmount').val());
        if (!targetAmount || targetAmount <= 0) {
            Stand120.showAlert('danger', 'Please enter a valid target amount');
            return;
        }

        const $btn = $('#calculateTarget');
        $btn.prop('disabled', true).html('<iconify-icon icon="solar:refresh-linear" class="spin"></iconify-icon> Calculating...');

        Stand120.ajax('get_target_recommendation', {
            target_amount: targetAmount
        }).then(response => {
            if (response.success) {
                const data = response.data;
                $('#targetResult').show();

                const statusText = data.achievable
                    ? '<span style="color: #4caf50;">✓ Target is achievable</span>'
                    : '<span style="color: #ff9800;">⚠ Target may not be fully achievable with current products</span>';
                $('#targetSummary').html(
                    'Target: <span class="naira">₦</span><strong>' + Stand120.formatNumber(data.target) + '</strong> | ' +
                    'Projected Total: <span class="naira">₦</span><strong>' + Stand120.formatNumber(data.total_projected) + '</strong> | ' +
                    statusText
                );

                const $tbody = $('#targetRecommendationTable tbody').empty();
                if (data.recommendations && data.recommendations.length > 0) {
                    data.recommendations.forEach(rec => {
                        $tbody.append('<tr>' +
                            '<td>' + rec.product_name + '</td>' +
                            '<td class="formatted-number"><span class="naira">₦</span>' + Stand120.formatNumber(rec.price) + '</td>' +
                            '<td class="formatted-number">' + Stand120.formatNumber(rec.suggested_qty) + '</td>' +
                            '<td class="formatted-number">' + Stand120.formatNumber(rec.avg_daily_sales) + '</td>' +
                            '<td class="formatted-number"><span class="naira">₦</span>' + Stand120.formatNumber(rec.projected_revenue) + '</td>' +
                        '</tr>');
                    });
                } else {
                    $tbody.append('<tr><td colspan="5" style="text-align:center;color:var(--text-muted)">No products available for recommendation</td></tr>');
                }
            } else {
                Stand120.showAlert('danger', response.data?.message || 'Failed to calculate target recommendation');
            }
        }).catch(function(error) {
            Stand120.showAlert('danger', 'Network error — please try again');
            console.error('Target recommendation error:', error);
        }).finally(function() {
            $btn.prop('disabled', false).html('<iconify-icon icon="solar:calculator-linear"></iconify-icon> Calculate');
        });
    }

    function loadAnalytics() {
        // Show insights loading state
        $('#insightsLoading').show();
        $('#insightsList').hide();

        Stand120.ajax('get_analytics', {
            type: 'overview',
            period: $('#periodFilter').val(),
            date: $('#periodDate').val()
        }).then(response => {
            if (response.success) {
                const data = response.data.analytics;

                // Update overview cards
                const totalSales = parseFloat(data.total_sales) || 0;
                const totalOrders = parseInt(data.total_orders) || 0;
                const avgOrder = totalOrders > 0 ? totalSales / totalOrders : 0;
                const revenue = parseFloat(data.revenue) || 0;
                const profit = parseFloat(data.profit) || 0;
                const totalAllExpenses = parseFloat(data.total_all_expenses) || 0;
                const loss = parseFloat(data.loss) || 0;

                $('#revenueAnalytics').html('<span class="naira">₦</span>' + Stand120.formatNumber(revenue));
                $('#profitAnalytics').html('<span class="naira">₦</span>' + Stand120.formatNumber(profit)).css('color', profit >= 0 ? '#4caf50' : '#f44336');
                $('#expensesAnalytics').html('<span class="naira">₦</span>' + Stand120.formatNumber(totalAllExpenses));
                $('#lossAnalytics').html('<span class="naira">₦</span>' + Stand120.formatNumber(loss)).css('color', loss > 0 ? '#f44336' : 'inherit');

                $('#totalSalesAnalytics').html('<span class="naira">₦</span>' + Stand120.formatNumber(totalSales));
                $('#totalOrdersAnalytics').text(Stand120.formatNumber(totalOrders));
                $('#avgOrderAnalytics').html('<span class="naira">₦</span>' + Stand120.formatNumber(avgOrder));

                // Destroy existing charts and render new ones
                destroyCharts();
                renderRevenueDistributionChart(data.financials || {});
                renderTopProductsPieChart(data.top_products || []);
                renderDailySalesTrendChart(data.daily_sales || []);

                // Generate smart insights
                generateSmartInsights(data);

                // Render product revenue attribution
                const $revenueBody = $('#productRevenueTable tbody').empty();
                if (data.product_revenue && data.product_revenue.length > 0) {
                    data.product_revenue.forEach(product => {
                        $revenueBody.append('<tr>' +
                            '<td>' + product.product_name + '</td>' +
                            '<td class="formatted-number">' + Stand120.formatNumber(product.qty_sold) + '</td>' +
                            '<td class="formatted-number"><span class="naira">₦</span>' + Stand120.formatNumber(product.unit_price || 0) + '</td>' +
                            '<td class="formatted-number"><span class="naira">₦</span>' + Stand120.formatNumber(product.revenue) + '</td>' +
                        '</tr>');
                    });
                } else {
                    $revenueBody.append('<tr><td colspan="4" style="text-align:center;color:var(--text-muted)">No data</td></tr>');
                }

                // Render detailed expenses breakdown
                const $expBody = $('#detailedExpensesTable tbody').empty();
                if (data.detailed_expenses && data.detailed_expenses.length > 0) {
                    data.detailed_expenses.forEach(exp => {
                        $expBody.append('<tr>' +
                            '<td>' + exp.description + '</td>' +
                            '<td class="formatted-number">' + Stand120.formatNumber(exp.total_qty) + '</td>' +
                            '<td class="formatted-number"><span class="naira">₦</span>' + Stand120.formatNumber(exp.total_amount) + '</td>' +
                        '</tr>');
                    });
                } else {
                    $expBody.append('<tr><td colspan="3" style="text-align:center;color:var(--text-muted)">No data</td></tr>');
                }

                // Render staff performance
                const $staffBody = $('#staffPerformanceTable tbody').empty();
                if (data.staff_performance && data.staff_performance.length > 0) {
                    data.staff_performance.forEach(staff => {
                        $staffBody.append('<tr>' +
                            '<td>' + staff.full_name + '</td>' +
                            '<td class="formatted-number">' + Stand120.formatNumber(staff.order_count || 0) + '</td>' +
                            '<td class="formatted-number"><span class="naira">₦</span>' + Stand120.formatNumber(staff.total_sales || 0) + '</td>' +
                        '</tr>');
                    });
                } else {
                    $staffBody.append('<tr><td colspan="3" style="text-align:center;color:var(--text-muted)">No data</td></tr>');
                }

                // Render order preparation analytics
                const prepSummary = data.preparation?.summary || {};
                $('#prepSummary').text(
                    'Total Added: ' + Stand120.formatNumber(prepSummary.total_added || 0) + ' | ' +
                    'Total Sold: ' + Stand120.formatNumber(prepSummary.total_sold || 0) + ' | ' +
                    'Closing: ' + Stand120.formatNumber(prepSummary.closing_value || 0)
                );

                const $prepBody = $('#prepAnalyticsTable tbody').empty();
                if (data.preparation?.records?.length) {
                    data.preparation.records.forEach(record => {
                        $prepBody.append('<tr>' +
                            '<td>' + record.product_name + '</td>' +
                            '<td class="formatted-number">' + Stand120.formatNumber(record.total_added || 0) + '</td>' +
                            '<td class="formatted-number">' + Stand120.formatNumber(record.total_sold || 0) + '</td>' +
                            '<td class="formatted-number">' + Stand120.formatNumber(record.closing_value || 0) + '</td>' +
                        '</tr>');
                    });
                } else {
                    $prepBody.append('<tr><td colspan="4" style="text-align:center;color:var(--text-muted)">No data</td></tr>');
                }

                // Render stock inventory analytics
                const stockSummary = data.stock?.summary || {};
                $('#stockSummary').text(
                    'Added: ' + Stand120.formatNumber(stockSummary.added_packs || 0) + ' | ' +
                    'Used: ' + Stand120.formatNumber(stockSummary.used_packs || 0) + ' | ' +
                    'Closing: ' + Stand120.formatNumber(stockSummary.closing_packs || 0)
                );

                const $stockBody = $('#stockAnalyticsTable tbody').empty();
                if (data.stock?.records?.length) {
                    data.stock.records.forEach(record => {
                        $stockBody.append('<tr>' +
                            '<td>' + record.product_name + '</td>' +
                            '<td class="formatted-number">' + Stand120.formatNumber(record.added_packs || 0) + '</td>' +
                            '<td class="formatted-number">' + Stand120.formatNumber(record.used_packs || 0) + '</td>' +
                            '<td class="formatted-number">' + Stand120.formatNumber(record.closing_packs || 0) + '</td>' +
                        '</tr>');
                    });
                } else {
                    $stockBody.append('<tr><td colspan="4" style="text-align:center;color:var(--text-muted)">No data</td></tr>');
                }

                // Render chopping inventory analytics
                const chopSummary = data.chopping?.summary || {};
                $('#chopSummary').text(
                    'Imported: ' + Stand120.formatNumber(chopSummary.import_whole || 0) + ' | ' +
                    'Prepared: ' + Stand120.formatNumber(chopSummary.prepared_whole || 0) + ' | ' +
                    'Packs: ' + Stand120.formatNumber(chopSummary.packs_gotten || 0)
                );

                const $chopBody = $('#chopAnalyticsTable tbody').empty();
                if (data.chopping?.records?.length) {
                    data.chopping.records.forEach(record => {
                        $chopBody.append('<tr>' +
                            '<td>' + record.product_name + '</td>' +
                            '<td class="formatted-number">' + Stand120.formatNumber(record.import_whole || 0) + '</td>' +
                            '<td class="formatted-number">' + Stand120.formatNumber(record.prepared_whole || 0) + '</td>' +
                            '<td class="formatted-number">' + Stand120.formatNumber(record.packs_gotten || 0) + '</td>' +
                        '</tr>');
                    });
                } else {
                    $chopBody.append('<tr><td colspan="4" style="text-align:center;color:var(--text-muted)">No data</td></tr>');
                }

                // Render import records analytics
                const importSummary = data.imports?.summary || {};
                $('#importSummary').text(
                    'Total Imported: ' + Stand120.formatNumber(importSummary.quantity_imported || 0)
                );

                const $importBody = $('#importAnalyticsTable tbody').empty();
                if (data.imports?.records?.length) {
                    data.imports.records.forEach(record => {
                        $importBody.append('<tr>' +
                            '<td>' + record.product_name + '</td>' +
                            '<td>' + record.product_type + '</td>' +
                            '<td class="formatted-number">' + Stand120.formatNumber(record.quantity_imported || 0) + '</td>' +
                        '</tr>');
                    });
                } else {
                    $importBody.append('<tr><td colspan="3" style="text-align:center;color:var(--text-muted)">No data</td></tr>');
                }

                // Render financial summary analytics
                const financials = data.financials || {};
                const $financialBody = $('#financialAnalyticsTable tbody').empty();
                $financialBody.append('<tr>' +
                    '<td class="formatted-number"><span class="naira">₦</span>' + Stand120.formatNumber(financials.total_sales || 0) + '</td>' +
                    '<td class="formatted-number"><span class="naira">₦</span>' + Stand120.formatNumber(financials.cash_sales || 0) + '</td>' +
                    '<td class="formatted-number"><span class="naira">₦</span>' + Stand120.formatNumber(financials.transfer_sales || 0) + '</td>' +
                    '<td class="formatted-number"><span class="naira">₦</span>' + Stand120.formatNumber(financials.delivery_fees || 0) + '</td>' +
                    '<td class="formatted-number"><span class="naira">₦</span>' + Stand120.formatNumber(financials.extras_amount || 0) + '</td>' +
                    '<td class="formatted-number"><span class="naira">₦</span>' + Stand120.formatNumber(financials.expenses_amount || 0) + '</td>' +
                '</tr>');

                // Render top products table
                const $productsBody = $('#topProductsTable tbody').empty();
                if (data.top_products && data.top_products.length > 0) {
                    data.top_products.forEach(product => {
                        $productsBody.append('<tr>' +
                            '<td>' + product.product_name + '</td>' +
                            '<td class="formatted-number">' + Stand120.formatNumber(product.qty_sold) + '</td>' +
                            '<td class="formatted-number"><span class="naira">₦</span>' + Stand120.formatNumber(product.revenue) + '</td>' +
                        '</tr>');
                    });
                } else {
                    $productsBody.append('<tr><td colspan="3" style="text-align:center;color:var(--text-muted)">No data</td></tr>');
                }
            }
        });
    }
})(jQuery);
</script>

<?php include STAND120_PLUGIN_DIR . 'templates/partials/footer.php'; ?>
