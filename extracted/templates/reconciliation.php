<?php
/**
 * Reconciliation Calendar Page Template
 */

if (!defined('ABSPATH')) {
    exit;
}

// Check admin access
if (!Stand120_Auth::is_admin()) {
    wp_redirect(home_url('/120-stand/'));
    exit;
}

$page_title = 'Reconciliation - 120 Stand Inventory';
$current_user = Stand120_Auth::get_current_user_data();

include STAND120_PLUGIN_DIR . 'templates/partials/header.php';
?>

<!-- Staff Info Bar -->
<div class="staff-info-bar">
    <div class="staff-info">
        <div class="staff-avatar">
            <?php echo strtoupper(substr($current_user['display_name'], 0, 1)); ?>
        </div>
        <div class="staff-details">
            <h4><?php echo esc_html($current_user['display_name']); ?></h4>
            <span>Administrator</span>
        </div>
    </div>
    <div class="datetime-display">
        <div class="date-display">
            <i class="fas fa-calendar-alt"></i>
            <span class="date-text"><?php echo date_i18n('l, F j, Y'); ?></span>
        </div>
        <div class="time-display">
            <i class="fas fa-clock"></i>
            <span class="digital-clock">--:--:--</span>
        </div>
    </div>
</div>

<h1 class="page-title">
    <i class="fas fa-calendar-check"></i>
    Reconciliation Calendar
</h1>

<p style="color: var(--text-muted); margin-bottom: 20px;">
    <i class="fas fa-info-circle"></i>
    Both admins must reconcile each date's records. Once 2 admins have submitted, the date is locked.
</p>

<!-- Calendar Navigation -->
<div class="glass-card" style="margin-bottom: 20px;">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
        <button id="prevMonth" class="btn btn-secondary btn-sm">
            <i class="fas fa-chevron-left"></i> Previous
        </button>
        <h3 id="calendarMonth" style="color: var(--primary-color); margin: 0;"></h3>
        <button id="nextMonth" class="btn btn-secondary btn-sm">
            Next <i class="fas fa-chevron-right"></i>
        </button>
    </div>
</div>

<!-- Legend -->
<div class="glass-card" style="margin-bottom: 20px;">
    <div style="display: flex; gap: 24px; flex-wrap: wrap; align-items: center;">
        <div style="display: flex; align-items: center; gap: 8px;">
            <span style="width: 20px; height: 20px; border-radius: 4px; background: #28a745; display: inline-block;"></span>
            <span style="color: var(--text-secondary); font-size: 0.9rem;">Fully Reconciled (2 admins)</span>
        </div>
        <div style="display: flex; align-items: center; gap: 8px;">
            <span style="width: 20px; height: 20px; border-radius: 4px; background: #ffc107; display: inline-block;"></span>
            <span style="color: var(--text-secondary); font-size: 0.9rem;">Partially Reconciled (1 admin)</span>
        </div>
        <div style="display: flex; align-items: center; gap: 8px;">
            <span style="width: 20px; height: 20px; border-radius: 4px; background: rgba(255,255,255,0.1); border: 1px solid var(--border-glass); display: inline-block;"></span>
            <span style="color: var(--text-secondary); font-size: 0.9rem;">Not Reconciled</span>
        </div>
    </div>
</div>

<!-- Calendar Grid -->
<div class="glass-card">
    <div class="reconciliation-calendar" id="reconciliationCalendar">
        <!-- Calendar rendered via JS -->
    </div>
</div>

<!-- Reconciliation Modal -->
<div id="reconcileModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; z-index: 9999; background: rgba(0,0,0,0.7); backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px); justify-content: center; align-items: center; padding: 20px;">
    <div style="background: var(--glass-bg, #1a1a2e); border: 1px solid var(--border-glass, rgba(255,255,255,0.1)); border-radius: 16px; padding: 32px; max-width: 500px; width: 100%; max-height: 90vh; overflow-y: auto;">
        <h3 style="color: var(--primary-color); margin-bottom: 8px;">
            <i class="fas fa-calendar-check"></i> Reconcile Date
        </h3>
        <p id="reconcileDate" style="color: var(--text-muted); margin-bottom: 20px; font-size: 1.1rem;"></p>
        
        <!-- Previous reconciliations -->
        <div id="previousReconciliations" style="margin-bottom: 20px; display: none;">
            <h4 style="color: var(--text-secondary); margin-bottom: 12px;">
                <i class="fas fa-check-circle"></i> Previous Submissions
            </h4>
            <div id="reconciliationList"></div>
        </div>
        
        <!-- Reconciliation form -->
        <div id="reconcileForm">
            <div class="form-group">
                <label class="form-label">Remark (optional)</label>
                <textarea id="reconcileRemark" class="form-control" rows="3" placeholder="Any remarks for this date's records..."></textarea>
            </div>
            <div style="display: flex; gap: 12px; margin-top: 20px;">
                <button id="submitReconcile" class="btn btn-success" style="flex: 1;">
                    <i class="fas fa-check"></i> Submit Reconciliation
                </button>
                <button id="cancelReconcile" class="btn btn-secondary" style="flex: 1;">
                    Cancel
                </button>
            </div>
        </div>
        
        <!-- Already complete message -->
        <div id="reconcileComplete" style="display: none;">
            <div style="text-align: center; padding: 20px; color: #28a745;">
                <i class="fas fa-lock" style="font-size: 2rem; margin-bottom: 12px;"></i>
                <p style="font-size: 1.1rem; font-weight: 600;">This date has been fully reconciled.</p>
            </div>
            <button id="closeReconcileComplete" class="btn btn-secondary" style="width: 100%; margin-top: 12px;">
                Close
            </button>
        </div>
    </div>
</div>

<style>
.reconciliation-calendar {
    width: 100%;
}
.reconciliation-calendar .cal-header {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 4px;
    margin-bottom: 8px;
}
.reconciliation-calendar .cal-header div {
    text-align: center;
    font-weight: 700;
    color: var(--primary-color);
    padding: 8px 4px;
    font-size: 0.85rem;
}
.reconciliation-calendar .cal-grid {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 4px;
}
.reconciliation-calendar .cal-day {
    aspect-ratio: 1;
    border-radius: 8px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.2s ease;
    border: 1px solid var(--border-glass, rgba(255,255,255,0.1));
    background: rgba(255,255,255,0.03);
    position: relative;
    min-height: 60px;
    padding: 4px;
}
.reconciliation-calendar .cal-day:hover {
    border-color: var(--primary-color);
    background: rgba(139,0,0,0.1);
    transform: scale(1.05);
}
.reconciliation-calendar .cal-day.empty {
    border: none;
    background: none;
    cursor: default;
}
.reconciliation-calendar .cal-day.empty:hover {
    transform: none;
}
.reconciliation-calendar .cal-day .day-num {
    font-size: 1.1rem;
    font-weight: 700;
    color: var(--text-primary, #fff);
}
.reconciliation-calendar .cal-day .day-status {
    font-size: 0.65rem;
    margin-top: 2px;
    text-align: center;
    line-height: 1.2;
}
.reconciliation-calendar .cal-day.complete {
    background: rgba(40,167,69,0.2);
    border-color: #28a745;
}
.reconciliation-calendar .cal-day.complete .day-num {
    color: #28a745;
}
.reconciliation-calendar .cal-day.complete .day-status {
    color: #28a745;
}
.reconciliation-calendar .cal-day.partial {
    background: rgba(255,193,7,0.15);
    border-color: #ffc107;
}
.reconciliation-calendar .cal-day.partial .day-num {
    color: #ffc107;
}
.reconciliation-calendar .cal-day.partial .day-status {
    color: #ffc107;
}
.reconciliation-calendar .cal-day.today {
    box-shadow: 0 0 0 2px var(--primary-color);
}
.reconciliation-calendar .cal-day.future {
    opacity: 0.4;
    cursor: not-allowed;
}
.reconciliation-calendar .cal-day.future:hover {
    transform: none;
    border-color: var(--border-glass, rgba(255,255,255,0.1));
    background: rgba(255,255,255,0.03);
}
.reconciliation-item {
    background: rgba(255,255,255,0.05);
    border: 1px solid var(--border-glass, rgba(255,255,255,0.1));
    border-radius: 8px;
    padding: 12px;
    margin-bottom: 8px;
}
.reconciliation-item .staff-name {
    font-weight: 600;
    color: var(--text-primary, #fff);
}
.reconciliation-item .reconcile-time {
    font-size: 0.8rem;
    color: var(--text-muted);
}
.reconciliation-item .reconcile-remark {
    margin-top: 6px;
    color: var(--text-secondary);
    font-size: 0.9rem;
}
@media (max-width: 600px) {
    .reconciliation-calendar .cal-day {
        min-height: 45px;
    }
    .reconciliation-calendar .cal-day .day-num {
        font-size: 0.9rem;
    }
    .reconciliation-calendar .cal-day .day-status {
        font-size: 0.55rem;
    }
}
</style>

<script>
(function($) {
$(document).ready(function() {
    if (typeof ReconciliationCalendar !== 'undefined') {
        ReconciliationCalendar.init();
    }
});
})(jQuery);
</script>

<?php include STAND120_PLUGIN_DIR . 'templates/partials/footer.php'; ?>