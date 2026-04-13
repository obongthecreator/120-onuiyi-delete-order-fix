/**
 * 120 Stand Inventory Management - Main JavaScript
 */

(function($) {
    'use strict';

    // Global App Object
    window.Stand120 = {
        // Eagerly read config so that template $(document).ready() handlers
        // (registered before main.js loads) already have ajax_url & nonce
        // available when they call Stand120.ajax().
        config: window.stand120_ajax || {},
        data: {},
        cache: {},
        
        /**
         * Initialize the application
         */
        init: function() {
            this.config = window.stand120_ajax || {};
            this.bindEvents();
            this.initComponents();
            this.startClock();
            this.checkOnlineStatus();
        },
        
        /**
         * Bind global events
         */
        bindEvents: function() {
            // Hamburger menu
            $(document).on('click', '.hamburger-menu', this.toggleMobileSidebar);
            $(document).on('click', '.mobile-sidebar-overlay', this.closeMobileSidebar);
            
            // Scroll to top
            $(window).on('scroll', this.handleScroll);
            $(document).on('click', '.scroll-to-top', this.scrollToTop);
            
            // Online/Offline detection
            window.addEventListener('online', () => this.handleOnline());
            window.addEventListener('offline', () => this.handleOffline());
            
            // Form auto-save
            $(document).on('input', '.auto-save-input', this.debounce(this.handleAutoSave, 500));
            
            // Number formatting
            $(document).on('input', '.number-input', this.formatNumberInput);
            $(document).on('focus', '.number-input', this.clearNumberFormat);
            $(document).on('blur', '.number-input', this.applyNumberFormat);
            
            // Smart input behavior - clear 0 on focus for quantity inputs
            $(document).on('focus', '.qty-input, .table-input[type="number"]', function() {
                const val = $(this).val();
                if (val === '0' || val === 0) {
                    $(this).val('');
                }
            });
            
            // Restore 0 on blur if empty for quantity inputs
            $(document).on('blur', '.qty-input, .table-input[type="number"]', function() {
                const val = $(this).val();
                if (val === '' || val === null || val === undefined) {
                    $(this).val('0');
                }
            });
            
            // Modal events
            $(document).on('click', '.modal-close, .modal-cancel', this.closeModal);
            $(document).on('click', '.modal-overlay', function(e) {
                if (e.target === this) Stand120.closeModal();
            });
            
            // Tabs
            $(document).on('click', '.tab-btn', this.handleTabClick);
        },
        
        /**
         * Initialize components
         */
        initComponents: function() {
            this.initScrollToTop();
            this.initTooltips();
            this.loadCachedData();
        },
        
        /**
         * Clock interval ID (prevents duplicate intervals)
         */
        _clockInterval: null,

        /**
         * Start real-time clock
         */
        startClock: function() {
            // Guard: never start a second interval
            if (this._clockInterval) {
                return;
            }

            var updateClock = function() {
                var now = new Date();
                var timeStr = now.toLocaleTimeString('en-US', {
                    hour: '2-digit',
                    minute: '2-digit',
                    second: '2-digit',
                    hour12: true
                });
                var dateStr = now.toLocaleDateString('en-US', {
                    weekday: 'long',
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric'
                });

                var $clock = $('.digital-clock');
                if ($clock.length) {
                    $clock.text(timeStr);
                }
                var $date = $('.date-text');
                if ($date.length) {
                    $date.text(dateStr);
                }
            };

            updateClock();
            this._clockInterval = setInterval(updateClock, 1000);
        },
        
        /**
         * Toggle mobile sidebar
         */
        toggleMobileSidebar: function() {
            $('.hamburger-menu').toggleClass('active');
            $('.mobile-sidebar').toggleClass('active');
            $('.mobile-sidebar-overlay').toggleClass('active');
            $('body').toggleClass('sidebar-open');
        },
        
        /**
         * Close mobile sidebar
         */
        closeMobileSidebar: function() {
            $('.hamburger-menu').removeClass('active');
            $('.mobile-sidebar').removeClass('active');
            $('.mobile-sidebar-overlay').removeClass('active');
            $('body').removeClass('sidebar-open');
        },
        
        /**
         * Handle scroll events
         */
        handleScroll: function() {
            const scrollTop = $(window).scrollTop();
            const docHeight = $(document).height() - $(window).height();
            const scrollPercent = (scrollTop / docHeight) * 100;
            
            // Show/hide scroll to top button
            if (scrollTop > 300) {
                $('.scroll-to-top').addClass('visible');
            } else {
                $('.scroll-to-top').removeClass('visible');
            }
            
            // Update progress ring
            const circumference = 2 * Math.PI * 25;
            const offset = circumference - (scrollPercent / 100) * circumference;
            $('.scroll-progress-ring .progress').css('stroke-dashoffset', offset);
        },
        
        /**
         * Initialize scroll to top button
         */
        initScrollToTop: function() {
            const circumference = 2 * Math.PI * 25;
            $('.scroll-progress-ring .progress').css({
                'stroke-dasharray': circumference,
                'stroke-dashoffset': circumference
            });
        },
        
        /**
         * Scroll to top
         */
        scrollToTop: function() {
            $('html, body').animate({ scrollTop: 0 }, 500);
        },
        
        /**
         * Check online status
         */
        checkOnlineStatus: function() {
            if (!navigator.onLine) {
                this.handleOffline();
            }
        },
        
        /**
         * Handle online event
         */
        handleOnline: function() {
            $('.offline-banner').removeClass('visible');
            this.syncOfflineData();
        },
        
        /**
         * Handle offline event
         */
        handleOffline: function() {
            $('.offline-banner').addClass('visible');
        },
        
        /**
         * Load cached data from localStorage
         */
        loadCachedData: function() {
            const cached = localStorage.getItem('stand120_cache');
            if (cached) {
                this.cache = JSON.parse(cached);
            }
        },
        
        /**
         * Save data to cache
         */
        saveToCache: function(key, data) {
            this.cache[key] = {
                data: data,
                timestamp: Date.now()
            };
            localStorage.setItem('stand120_cache', JSON.stringify(this.cache));
        },
        
        /**
         * Get cached data
         */
        getFromCache: function(key, maxAge = 300000) {
            const cached = this.cache[key];
            if (cached && (Date.now() - cached.timestamp) < maxAge) {
                return cached.data;
            }
            return null;
        },
        
        /**
         * Save data for offline sync
         */
        saveOfflineData: function(type, data) {
            let offlineQueue = JSON.parse(localStorage.getItem('stand120_offline_queue') || '[]');
            offlineQueue.push({
                type: type,
                data: data,
                local_id: 'local_' + Date.now(),
                timestamp: Date.now()
            });
            localStorage.setItem('stand120_offline_queue', JSON.stringify(offlineQueue));
        },
        
        /**
         * Sync offline data
         */
        syncOfflineData: function() {
            const offlineQueue = JSON.parse(localStorage.getItem('stand120_offline_queue') || '[]');
            
            if (offlineQueue.length === 0) return;
            
            this.ajax('sync_offline_data', {
                offline_data: JSON.stringify(offlineQueue)
            }).then(response => {
                if (response.success) {
                    localStorage.setItem('stand120_offline_queue', '[]');
                    this.showAlert('success', 'Offline data synced successfully!');
                }
            }).catch(() => {
                // Keep data for next sync attempt
            });
        },
        
        /**
         * AJAX helper
         */
        ajax: function(action, data = {}) {
            return new Promise((resolve, reject) => {
                data.action = 'stand120_action';
                data.stand120_action = action;
                data.nonce = this.config.nonce;
                
                $.ajax({
                    url: this.config.ajax_url,
                    type: 'POST',
                    data: data,
                    success: function(response) {
                        resolve(response);
                    },
                    error: function(xhr, status, error) {
                        reject(error);
                    }
                });
            });
        },
        
        /**
         * Show loading overlay
         */
        showLoading: function(message = 'Loading...') {
            $('.loading-overlay').addClass('active');
            $('.loading-overlay .loading-text').text(message);
        },
        
        /**
         * Hide loading overlay
         */
        hideLoading: function() {
            $('.loading-overlay').removeClass('active');
        },
        
        /**
         * Show alert as popup (centered modal)
         */
        showAlert: function(type, message, autoRefresh = false) {
            // Create popup overlay
            const popupHtml = `
                <div class="alert-popup-overlay" style="
                    position: fixed;
                    top: 0;
                    left: 0;
                    right: 0;
                    bottom: 0;
                    background: rgba(0,0,0,0.5);
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    z-index: 10000;
                ">
                    <div class="alert-popup" style="
                        background: white;
                        padding: 30px 40px;
                        border-radius: 16px;
                        text-align: center;
                        max-width: 400px;
                        box-shadow: 0 10px 40px rgba(0,0,0,0.2);
                        animation: popupSlide 0.3s ease;
                    ">
                        <div style="
                            width: 60px;
                            height: 60px;
                            border-radius: 50%;
                            background: ${type === 'success' ? '#10b981' : type === 'danger' ? '#ef4444' : type === 'warning' ? '#f59e0b' : '#3b82f6'};
                            display: flex;
                            align-items: center;
                            justify-content: center;
                            margin: 0 auto 16px;
                        ">
                            <i class="fas fa-${type === 'success' ? 'check' : type === 'danger' ? 'times' : type === 'warning' ? 'exclamation' : 'info'}" style="color: white; font-size: 28px;"></i>
                        </div>
                        <h3 style="margin: 0 0 12px; color: #1a1a1a; font-size: 1.3rem;">${type === 'success' ? 'Success!' : type === 'danger' ? 'Error!' : type === 'warning' ? 'Warning!' : 'Info'}</h3>
                        <p style="margin: 0 0 20px; color: #666; font-size: 1rem;">${message}</p>
                        <button class="alert-popup-close btn btn-primary" style="min-width: 120px;">OK</button>
                    </div>
                </div>
            `;
            
            // Add animation style if not exists
            if (!$('#popup-animation-style').length) {
                $('head').append(`
                    <style id="popup-animation-style">
                        @keyframes popupSlide {
                            from { transform: scale(0.8); opacity: 0; }
                            to { transform: scale(1); opacity: 1; }
                        }
                    </style>
                `);
            }
            
            // Remove existing popups
            $('.alert-popup-overlay').remove();
            
            // Add popup to body
            $('body').append(popupHtml);
            
            // Handle close
            $('.alert-popup-close, .alert-popup-overlay').on('click', function(e) {
                if (e.target === this || $(this).hasClass('alert-popup-close')) {
                    $('.alert-popup-overlay').fadeOut(200, function() {
                        $(this).remove();
                        if (autoRefresh) {
                            window.location.reload();
                        }
                    });
                }
            });
            
            // Auto-close after 3 seconds for success, then refresh if needed
            if (type === 'success' && autoRefresh) {
                setTimeout(() => {
                    $('.alert-popup-overlay').fadeOut(200, function() {
                        $(this).remove();
                        window.location.reload();
                    });
                }, 2000);
            }
        },
        
        /**
         * Show modal
         */
        showModal: function(options) {
            const modal = $(`
                <div class="modal-overlay active">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h3 class="modal-title">${options.title || 'Confirm'}</h3>
                            <button class="modal-close"><i class="fas fa-times"></i></button>
                        </div>
                        <div class="modal-body">
                            ${options.content || ''}
                        </div>
                        <div class="modal-footer">
                            ${options.showCancel !== false ? '<button class="btn btn-secondary modal-cancel">Cancel</button>' : ''}
                            <button class="btn btn-primary modal-confirm">${options.confirmText || 'Confirm'}</button>
                        </div>
                    </div>
                </div>
            `);
            
            $('body').append(modal);
            
            return new Promise((resolve, reject) => {
                modal.find('.modal-confirm').on('click', function() {
                    Stand120.closeModal();
                    resolve(true);
                });
                
                modal.find('.modal-cancel, .modal-close').on('click', function() {
                    Stand120.closeModal();
                    resolve(false);
                });
            });
        },
        
        /**
         * Close modal
         */
        closeModal: function() {
            $('.modal-overlay').removeClass('active');
            setTimeout(() => {
                $('.modal-overlay').remove();
            }, 300);
        },
        
        /**
         * Format number with commas
         */
        formatNumber: function(num) {
            if (!num && num !== 0) return '';
            return parseFloat(num).toLocaleString('en-NG', {
                minimumFractionDigits: 0,
                maximumFractionDigits: 2
            });
        },
        
        /**
         * Parse formatted number
         */
        parseNumber: function(str) {
            if (!str) return 0;
            return parseFloat(str.toString().replace(/,/g, '')) || 0;
        },
        
        /**
         * Escape HTML entities
         */
        escapeHtml: function(str) {
            const div = document.createElement('div');
            div.appendChild(document.createTextNode(str));
            return div.innerHTML;
        },
        
        /**
         * Format number input on blur
         */
        formatNumberInput: function() {
            const $input = $(this);
            const value = $input.val().replace(/,/g, '');
            if (value && !isNaN(value)) {
                $input.val(Stand120.formatNumber(value));
            }
        },
        
        /**
         * Clear number format on focus
         */
        clearNumberFormat: function() {
            const $input = $(this);
            const value = $input.val().replace(/,/g, '');
            $input.val(value);
        },
        
        /**
         * Apply number format
         */
        applyNumberFormat: function() {
            const $input = $(this);
            const value = $input.val();
            if (value && !isNaN(value)) {
                $input.val(Stand120.formatNumber(value));
            }
        },
        
        /**
         * Handle auto-save
         */
        handleAutoSave: function() {
            const $input = $(this);
            const saveAction = $input.data('save-action');
            const saveData = {};
            
            // Collect all data from the row/form
            $input.closest('tr, .form-group').find('[data-field]').each(function() {
                saveData[$(this).data('field')] = $(this).val();
            });
            
            if (saveAction && Object.keys(saveData).length > 0) {
                Stand120.ajax(saveAction, saveData).then(response => {
                    if (response.success) {
                        $input.addClass('saved');
                        setTimeout(() => $input.removeClass('saved'), 1000);
                    }
                });
            }
        },
        
        /**
         * Handle tab click
         */
        handleTabClick: function() {
            const $btn = $(this);
            const tabId = $btn.data('tab');
            
            // Update button states
            $('.tab-btn').removeClass('active');
            $btn.addClass('active');
            
            // Update content states
            $('.tab-content').removeClass('active');
            $(`#${tabId}`).addClass('active');
        },
        
        /**
         * Initialize tooltips
         */
        initTooltips: function() {
            // Tooltips are handled via CSS
        },
        
        /**
         * Debounce function
         */
        debounce: function(func, wait) {
            let timeout;
            return function(...args) {
                clearTimeout(timeout);
                timeout = setTimeout(() => func.apply(this, args), wait);
            };
        },
        
        /**
         * Calculate table totals
         */
        calculateTableTotals: function($table) {
            let grandTotal = 0;
            
            $table.find('tbody tr').each(function() {
                const $row = $(this);
                const price = Stand120.parseNumber($row.find('.price-cell').text());
                const qty = parseInt($row.find('.qty-input').val()) || 0;
                const total = price * qty;
                
                $row.find('.total-cell').text(Stand120.formatNumber(total));
                grandTotal += total;
            });
            
            return grandTotal;
        }
    };

    // Initialize when DOM is ready (stand120_ajax is set by an inline script
    // that appears before this file, so it is always available by this point).
    $(function() {
        Stand120.init();
    });

})(jQuery);

/**
 * All page modules are wrapped in a jQuery closure so that $ remains
 * available even if WordPress's bundled jQuery calls jQuery.noConflict().
 * Each module is attached to window so inline template scripts can
 * reference them by name (e.g. TakeOrder.init()).
 */
;(function($) {

/**
 * Take Order Module
 */
window.TakeOrder = {
    items: [],
    isSubmitting: false,
    
    init: function() {
        const self = this;
        this.bindEvents();
        this.calculateTotals(); // Initial calculation
        
        // Initialize payment method click handlers directly
        $('.payment-option').on('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            // Remove selected class from all options
            $('.payment-option').removeClass('selected');
            // Add selected class to clicked option
            $(this).addClass('selected');
            
            // Check the radio button
            const $radio = $(this).find('input[type="radio"]');
            $radio.prop('checked', true);
            
            // Trigger the payment method change
            self.handlePaymentMethodChange();
        });
    },
    
    bindEvents: function() {
        const self = this;
        
        // Quantity input change - direct event binding for immediate response
        $(document).off('input.takeorder change.takeorder keyup.takeorder', '.qty-input');
        $(document).on('input.takeorder change.takeorder keyup.takeorder', '.qty-input', function() {
            self.calculateTotals();
        });
        
        // Delivery fee input change
        $(document).off('input.takeorder change.takeorder keyup.takeorder', '#deliveryFee');
        $(document).on('input.takeorder change.takeorder keyup.takeorder', '#deliveryFee', function() {
            self.calculateTotals();
        });
        
        // Cash and transfer amount change
        $(document).off('input.takeorder change.takeorder keyup.takeorder', '#cashAmount, #transferAmount');
        $(document).on('input.takeorder change.takeorder keyup.takeorder', '#cashAmount, #transferAmount', function() {
            self.calculateTotals();
        });
        
        // Submit button
        $(document).off('click.takeorder', '#submitOrder');
        $(document).on('click.takeorder', '#submitOrder', this.handleSubmit.bind(this));
    },
    
    loadMenuItems: function() {
        Stand120.ajax('get_menu_items').then(response => {
            if (response.success) {
                this.renderMenuItems(response.data.items);
            }
        });
    },
    
    renderMenuItems: function(items) {
        const $tbody = $('#orderTable tbody');
        $tbody.empty();
        
        items.forEach(item => {
            const row = `
                <tr data-product-id="${item.id}">
                    <td>${item.name}</td>
                    <td class="price-cell formatted-number">₦${Stand120.formatNumber(item.price)}</td>
                    <td>
                        <input type="number" class="table-input qty-input" value="0" min="0" data-price="${item.price}">
                    </td>
                    <td class="total-cell formatted-number">₦0</td>
                </tr>
            `;
            $tbody.append(row);
        });
    },
    
    calculateTotals: function() {
        let subtotal = 0;
        
        $('#orderTable tbody tr').each(function() {
            const $row = $(this);
            const priceAttr = $row.find('.qty-input').data('price');
            const price = parseFloat(priceAttr) || 0;
            const qty = parseInt($row.find('.qty-input').val()) || 0;
            const total = price * qty;
            
            // Update the total cell immediately
            $row.find('.total-cell').text('₦' + Stand120.formatNumber(total));
            subtotal += total;
        });
        
        // Parse delivery fee, remove commas if present
        const deliveryFeeVal = $('#deliveryFee').val() || '0';
        const deliveryFee = parseFloat(deliveryFeeVal.toString().replace(/,/g, '')) || 0;
        const grandTotal = subtotal + deliveryFee;
        
        // Update display immediately
        $('#subtotal').text('₦' + Stand120.formatNumber(subtotal));
        $('#grandTotal').text('₦' + Stand120.formatNumber(grandTotal));
        
    },
    
    handlePaymentMethodChange: function() {
        const method = $('input[name="payment_method"]:checked').val();
    
        // Hide all payment sections first
        $('#cashSection').hide();
        $('#transferSection').hide();
        $('#confirmationSection').hide();
        $('#cashAmount').prop('disabled', true).val('');
        $('#transferAmount').prop('disabled', true).val('');
        
        if (method === 'both') {
            // Both - show both sections and confirmation
            $('#cashSection').slideDown(200);
            $('#transferSection').slideDown(200);
            $('#confirmationSection').slideDown(200);
            $('#cashAmount').prop('disabled', false);
            $('#transferAmount').prop('disabled', false);
        } else if (method === 'cash') {
            // Cash only - show cash section
            $('#cashSection').slideDown(200);
            $('#cashAmount').prop('disabled', false);
        } else if (method === 'transfer') {
            // Transfer/Card - show confirmation
            $('#confirmationSection').slideDown(200);
        }
        
        this.calculateTotals();
    },
    
    collectOrderData: function() {
        const items = [];
        
        $('#orderTable tbody tr').each(function() {
            const $row = $(this);
            const qty = parseInt($row.find('.qty-input').val()) || 0;
            
            if (qty > 0) {
                items.push({
                    product_id: $row.data('product-id'),
                    product_name: $row.find('td:first').text(),
                    price: parseFloat($row.find('.qty-input').data('price')),
                    quantity: qty,
                    total: parseFloat($row.find('.qty-input').data('price')) * qty
                });
            }
        });
        
        const paymentMethod = $('input[name="payment_method"]:checked').val();
        
        return {
            items: JSON.stringify(items),
            payment_method: paymentMethod,
            cash_amount: Stand120.parseNumber($('#cashAmount').val()),
            transfer_amount: Stand120.parseNumber($('#transferAmount').val()),
            delivery_fee: Stand120.parseNumber($('#deliveryFee').val()),
            payment_confirmed: $('#paymentConfirmed').is(':checked') ? 1 : 0
        };
    },
    
    handleSubmit: async function(e) {
        e.preventDefault();
        
        if (this.isSubmitting) {
            return;
        }
        
        const data = this.collectOrderData();
        const items = JSON.parse(data.items);
        const paymentMethod = data.payment_method;
        
        // Validation
        if (items.length === 0) {
            Stand120.showAlert('danger', 'Please add at least one item to the order.');
            return;
        }
        
        // Validate payment method is selected
        if (!paymentMethod) {
            Stand120.showAlert('danger', 'Please select a payment method.');
            return;
        }
        
        // Check if confirmation is needed (for transfer or both)
        if ((paymentMethod === 'transfer' || paymentMethod === 'both') && !data.payment_confirmed) {
            Stand120.showAlert('warning', 'Please confirm payment has been received before submitting.');
            return;
        }
        
        // Show confirmation modal
        const grandTotal = Stand120.parseNumber($('#grandTotal').text().replace('₦', ''));
        let paymentDisplay = paymentMethod === 'both' ? 'Transfer/Card + Cash' : 
                            (paymentMethod === 'transfer' ? 'Transfer/Card' : 'Cash');
        
        const confirmContent = `
            <div class="order-summary">
                <p><strong>Total Items:</strong> ${items.length}</p>
                <p><strong>Payment Method:</strong> ${paymentDisplay}</p>
                <p><strong>Grand Total:</strong> ₦${Stand120.formatNumber(grandTotal)}</p>
            </div>
            <p class="mt-3">Are you sure you want to submit this order?</p>
        `;
        
        const confirmed = await Stand120.showModal({
            title: 'Confirm Order Submission',
            content: confirmContent,
            confirmText: 'Submit Order'
        });
        
        if (!confirmed) return;
        
        this.isSubmitting = true;
        $('#submitOrder').prop('disabled', true).html('<span class="loading-spinner"></span> Submitting...');
        
        // Check if offline
        if (!navigator.onLine) {
            data.offline = true;
            Stand120.saveOfflineData('order', data);
            Stand120.showAlert('info', 'Order saved offline. It will sync when you\'re back online.');
            this.resetForm();
            this.isSubmitting = false;
            $('#submitOrder').prop('disabled', false).html('<i class="fas fa-check-circle"></i> Submit Order');
            return;
        }
        
        // Log the data being sent for debugging
        
        Stand120.ajax('submit_order', data).then(response => {
            if (response.success) {
                // Show success popup and auto-refresh page
                Stand120.showAlert('success', 'Order #' + (response.data.order_id || '') + ' submitted successfully!', true);
                this.resetForm();
            } else {
                Stand120.showAlert('danger', response.data?.message || 'Failed to submit order.');
            }
        }).catch(error => {
            console.error('Order submission error:', error);
            Stand120.showAlert('danger', 'An error occurred. Please try again.');
        }).finally(() => {
            this.isSubmitting = false;
            $('#submitOrder').prop('disabled', false).html('<i class="fas fa-check-circle"></i> Submit Order');
        });
    },
    
    resetForm: function() {
        $('.qty-input').val(0);
        $('#deliveryFee, #cashAmount, #transferAmount').val('');
        $('#paymentConfirmed').prop('checked', false);
        $('input[name="payment_method"]').prop('checked', false);
        $('.payment-option').removeClass('selected');
        $('#cashSection, #transferSection, #confirmationSection').hide();
        this.calculateTotals();
    }
};

/**
 * Order Preparation Module
 */
window.OrderPreparation = {
    data: [],
    
    init: function() {
        this.bindEvents();
        this.loadData();
    },
    
    bindEvents: function() {
        const self = this;
        
        // Real-time calculation on input change - using multiple events for responsiveness
        $(document).off('input.orderprep change.orderprep keyup.orderprep', '.prep-added, .prep-sold, .prep-opening');
        $(document).on('input.orderprep change.orderprep keyup.orderprep', '.prep-added, .prep-sold, .prep-opening', function(e) {
            self.handleInputChange(e);
        });
    },
    
    loadData: function() {
        const date = $('#prepDate').val() || new Date().toISOString().split('T')[0];
        
        Stand120.ajax('get_order_preparation', { date: date }).then(response => {
            if (response.success) {
                this.data = response.data.data || [];
                this.renderTable();
            }
        });
    },
    
    renderTable: function() {
        const $tbody = $('#prepTable tbody');
        $tbody.empty();
        
        const isAdmin = Stand120.config.is_admin;
        
        if (!this.data || this.data.length === 0) {
            $tbody.append('<tr><td colspan="5" style="text-align: center; color: var(--text-muted);">No fruits found. Admin can add fruits in the Admin Panel.</td></tr>');
            return;
        }
        
        this.data.forEach(item => {
            const opening = parseFloat(item.opening) || 0;
            const added = parseFloat(item.total_added) || 0;
            const sold = parseFloat(item.total_sold) || 0;
            const closing = opening + added - sold;
            
            const row = `
                <tr data-product-id="${item.product_id}">
                    <td>${item.product_name}</td>
                    <td>
                        <input type="number" class="table-input prep-opening" 
                            value="${opening}" 
                            ${!isAdmin ? 'readonly' : ''} 
                            data-field="opening">
                    </td>
                    <td>
                        <input type="number" class="table-input prep-added auto-save-input" 
                            value="${added}" min="0" 
                            data-field="total_added"
                            data-save-action="save_order_preparation">
                    </td>
                    <td>
                        <input type="number" class="table-input prep-sold auto-save-input" 
                            value="${sold}" min="0" 
                            data-field="total_sold"
                            data-save-action="save_order_preparation">
                    </td>
                    <td class="prep-closing formatted-number">${Stand120.formatNumber(closing)}</td>
                </tr>
            `;
            $tbody.append(row);
        });
    },
    
    handleInputChange: function(e) {
        const $input = $(e.target);
        const $row = $input.closest('tr');
        
        const opening = parseFloat($row.find('.prep-opening').val()) || 0;
        const added = parseFloat($row.find('.prep-added').val()) || 0;
        const sold = parseFloat($row.find('.prep-sold').val()) || 0;
        const closing = opening + added - sold;
        
        // Update closing value immediately
        $row.find('.prep-closing').text(Stand120.formatNumber(closing));
        
        // Log for debugging
        
        // Auto-save with debounce
        clearTimeout($row.data('saveTimeout'));
        $row.data('saveTimeout', setTimeout(() => this.saveRow($row), 500));
    },
    
    saveRow: function($row) {
        const productId = $row.data('product-id');
        const added = parseFloat($row.find('.prep-added').val()) || 0;
        const sold = parseFloat($row.find('.prep-sold').val()) || 0;
        const date = $('#prepDate').val() || new Date().toISOString().split('T')[0];
        
        const data = {
            product_id: productId,
            date: date,
            total_added: added,
            total_sold: sold
        };
        
        // Include opening value so admin inline edits are persisted
        if (Stand120.config.is_admin) {
            data.opening_value = parseFloat($row.find('.prep-opening').val()) || 0;
        }
        
        Stand120.ajax('save_order_preparation', data).then(response => {
            if (response.success) {
                $row.addClass('saved');
                setTimeout(() => $row.removeClass('saved'), 500);
            }
        });
    }
};

/**
 * Stock Inventory Module
 */
window.StockInventory = {
    data: [],
    
    init: function() {
        this.bindEvents();
        this.loadData();
    },
    
    bindEvents: function() {
        const self = this;
        
        // Real-time calculation on input change
        $(document).off('input.stockinv change.stockinv keyup.stockinv', '.stock-used, .stock-opening');
        $(document).on('input.stockinv change.stockinv keyup.stockinv', '.stock-used, .stock-opening', function(e) {
            self.handleInputChange(e);
        });
    },
    
    loadData: function() {
        const date = $('#stockDate').val() || new Date().toISOString().split('T')[0];
        
        Stand120.ajax('get_stock_inventory', { date: date }).then(response => {
            if (response.success) {
                this.data = response.data.data || [];
                this.renderTable();
            }
        });
    },
    
    renderTable: function() {
        const $tbody = $('#stockTable tbody');
        $tbody.empty();
        
        const isAdmin = Stand120.config.is_admin;
        
        if (!this.data || this.data.length === 0) {
            $tbody.append('<tr><td colspan="5" style="text-align: center; color: var(--text-muted);">No products found. Admin can add products in the Admin Panel.</td></tr>');
            return;
        }
        
        this.data.forEach(item => {
            const opening = parseFloat(item.opening) || 0;
            const added = parseFloat(item.added) || 0;
            const used = parseFloat(item.used) || 0;
            const closing = opening + added - used;
            
            const row = `
                <tr data-product-id="${item.product_id}">
                    <td>
                        ${item.product_name}
                        <span class="badge badge-${item.product_type}">${item.product_type}</span>
                    </td>
                    <td>
                        <input type="number" class="table-input stock-opening" 
                            value="${opening}" 
                            ${!isAdmin ? 'readonly' : ''} 
                            data-field="opening">
                    </td>
                    <td class="stock-added formatted-number">${Stand120.formatNumber(added)}</td>
                    <td>
                        <input type="number" class="table-input stock-used auto-save-input" 
                            value="${used}" min="0" 
                            data-field="used_packs"
                            data-save-action="save_stock_inventory">
                    </td>
                    <td class="stock-closing formatted-number">${Stand120.formatNumber(closing)}</td>
                </tr>
            `;
            $tbody.append(row);
        });
    },
    
    handleInputChange: function(e) {
        const $input = $(e.target);
        const $row = $input.closest('tr');
        
        const opening = parseFloat($row.find('.stock-opening').val()) || 0;
        const addedText = $row.find('.stock-added').text().replace(/,/g, '');
        const added = parseFloat(addedText) || 0;
        const used = parseFloat($row.find('.stock-used').val()) || 0;
        const closing = opening + added - used;
        
        // Update closing value immediately
        $row.find('.stock-closing').text(Stand120.formatNumber(closing));
        
        // Log for debugging
        
        // Auto-save with debounce
        clearTimeout($row.data('saveTimeout'));
        $row.data('saveTimeout', setTimeout(() => this.saveRow($row), 500));
    },
    
    saveRow: function($row) {
        const productId = $row.data('product-id');
        const used = parseFloat($row.find('.stock-used').val()) || 0;
        const date = $('#stockDate').val() || new Date().toISOString().split('T')[0];
        
        const data = {
            product_id: productId,
            date: date,
            used_packs: used
        };
        
        // Include opening value so admin inline edits are persisted
        if (Stand120.config.is_admin) {
            data.opening_packs = parseFloat($row.find('.stock-opening').val()) || 0;
        }
        
        Stand120.ajax('save_stock_inventory', data).then(response => {
            if (response.success) {
                $row.addClass('saved');
                setTimeout(() => $row.removeClass('saved'), 500);
            }
        });
    }
};

/**
 * Chopping Inventory Module
 */
window.ChoppingInventory = {
    data: [],
    
    init: function() {
        this.bindEvents();
        this.loadData();
    },
    
    bindEvents: function() {
        const self = this;
        
        // Real-time calculation on input change
        $(document).off('input.chopinv change.chopinv keyup.chopinv', '.chop-prepared, .chop-packs, .chop-remarks, .chop-opening');
        $(document).on('input.chopinv change.chopinv keyup.chopinv', '.chop-prepared, .chop-packs, .chop-remarks, .chop-opening', function(e) {
            self.handleInputChange(e);
        });
    },
    
    loadData: function() {
        const date = $('#chopDate').val() || new Date().toISOString().split('T')[0];
        
        Stand120.ajax('get_chopping_inventory', { date: date }).then(response => {
            if (response.success) {
                this.data = response.data.data || [];
                this.renderTable();
            }
        });
    },
    
    renderTable: function() {
        const $tbody = $('#chopTable tbody');
        $tbody.empty();
        
        const isAdmin = Stand120.config.is_admin;
        
        if (!this.data || this.data.length === 0) {
            $tbody.append('<tr><td colspan="7" style="text-align: center; color: var(--text-muted);">No fruits found. Admin can add fruits in the Admin Panel.</td></tr>');
            return;
        }
        
        this.data.forEach(item => {
            const opening = parseFloat(item.opening) || 0;
            const importVal = parseFloat(item.import) || 0;
            const prepared = parseFloat(item.prepared) || 0;
            const closing = opening + importVal - prepared;
            const packs = parseFloat(item.packs_gotten) || 0;
            
            const row = `
                <tr data-product-id="${item.product_id}">
                    <td>${item.product_name}</td>
                    <td>
                        <input type="number" class="table-input chop-opening" 
                            value="${opening}" 
                            ${!isAdmin ? 'readonly' : ''}>
                    </td>
                    <td class="chop-import formatted-number">${Stand120.formatNumber(importVal)}</td>
                    <td>
                        <input type="number" class="table-input chop-prepared auto-save-input" 
                            value="${prepared}" min="0">
                    </td>
                    <td class="chop-closing formatted-number">${Stand120.formatNumber(closing)}</td>
                    <td>
                        <input type="number" class="table-input chop-packs auto-save-input" 
                            value="${packs}" min="0">
                    </td>
                    <td>
                        <input type="text" class="table-input chop-remarks auto-save-input" 
                            value="${item.remarks || ''}" placeholder="Add remarks...">
                    </td>
                </tr>
            `;
            $tbody.append(row);
        });
    },
    
    handleInputChange: function(e) {
        const $input = $(e.target);
        const $row = $input.closest('tr');
        
        const opening = parseFloat($row.find('.chop-opening').val()) || 0;
        const importText = $row.find('.chop-import').text().replace(/,/g, '');
        const importVal = parseFloat(importText) || 0;
        const prepared = parseFloat($row.find('.chop-prepared').val()) || 0;
        const closing = opening + importVal - prepared;
        
        // Update closing value immediately
        $row.find('.chop-closing').text(Stand120.formatNumber(closing));
        
        // Log for debugging
        
        // Auto-save with debounce
        clearTimeout($row.data('saveTimeout'));
        $row.data('saveTimeout', setTimeout(() => this.saveRow($row), 500));
    },
    
    saveRow: function($row) {
        const productId = $row.data('product-id');
        const prepared = parseFloat($row.find('.chop-prepared').val()) || 0;
        const packs = parseFloat($row.find('.chop-packs').val()) || 0;
        const remarks = $row.find('.chop-remarks').val();
        const date = $('#chopDate').val() || new Date().toISOString().split('T')[0];
        
        const data = {
            product_id: productId,
            date: date,
            prepared: prepared,
            packs_gotten: packs,
            remarks: remarks
        };
        
        // Include opening value so admin inline edits are persisted
        if (Stand120.config.is_admin) {
            data.opening_whole = parseFloat($row.find('.chop-opening').val()) || 0;
        }
        
        Stand120.ajax('save_chopping_inventory', data).then(response => {
            if (response.success) {
                $row.addClass('saved');
                setTimeout(() => $row.removeClass('saved'), 500);
            }
        });
    }
};

/**
 * Import Record Module
 */
window.ImportRecord = {
    data: [],
    
    init: function() {
        this.bindEvents();
        this.loadData();
    },
    
    bindEvents: function() {
        $(document).on('input', '.import-qty', this.handleInputChange.bind(this));
    },
    
    loadData: function() {
        const date = $('#importDate').val() || new Date().toISOString().split('T')[0];
        
        Stand120.ajax('get_import_records', { date: date }).then(response => {
            if (response.success) {
                this.data = response.data.data;
                this.renderTable();
            }
        });
    },
    
    renderTable: function() {
        const $tbody = $('#importTable tbody');
        $tbody.empty();
        
        this.data.forEach(item => {
            const statusClass = item.sync_status === 'synced' ? 'status-synced' : 
                               item.sync_status === 'syncing' ? 'status-syncing' : 'status-pending';
            
            const row = `
                <tr data-product-id="${item.product_id}">
                    <td>
                        ${item.product_name}
                        <span class="badge badge-${item.product_type}">${item.product_type}</span>
                    </td>
                    <td>
                        <input type="number" class="table-input import-qty auto-save-input" 
                            value="${item.quantity}" min="0">
                    </td>
                    <td>
                        <span class="status-badge ${statusClass}">
                            <i class="fas fa-${item.sync_status === 'synced' ? 'check' : 'sync'}"></i>
                            ${item.sync_status}
                        </span>
                    </td>
                </tr>
            `;
            $tbody.append(row);
        });
    },
    
    handleInputChange: function(e) {
        const $input = $(e.target);
        const $row = $input.closest('tr');
        
        // Update status to syncing
        $row.find('.status-badge')
            .removeClass('status-synced status-pending')
            .addClass('status-syncing')
            .html('<i class="fas fa-sync fa-spin"></i> syncing');
        
        // Auto-save
        this.saveRow($row);
    },
    
    saveRow: function($row) {
        const productId = $row.data('product-id');
        const quantity = parseFloat($row.find('.import-qty').val()) || 0;
        const date = $('#importDate').val() || new Date().toISOString().split('T')[0];
        
        Stand120.ajax('save_import_record', {
            product_id: productId,
            date: date,
            quantity: quantity
        }).then(response => {
            if (response.success) {
                $row.find('.status-badge')
                    .removeClass('status-syncing status-pending')
                    .addClass('status-synced')
                    .html('<i class="fas fa-check"></i> synced');
            }
        });
    }
};

/**
 * Market Expense Module
 */
window.MarketExpense = {
    items: [],
    
    init: function() {
        this.bindEvents();
        this.loadData();
    },
    
    bindEvents: function() {
        const self = this;
        
        $(document).on('click', '#addExpenseRow', function() {
            self.addRow();
        });
        
        $(document).on('click', '.remove-expense-row', function() {
            $(this).closest('tr').remove();
            self.calculateTotal();
        });
        
        $(document).on('input change', '.expense-amount', function() {
            self.calculateTotal();
        });
        
        $(document).on('click', '#saveExpenses', function() {
            self.saveData();
        });
    },
    
    loadData: function() {
        const date = $('#expenseDate').val() || new Date().toISOString().split('T')[0];
        
        Stand120.ajax('get_expenses', { date: date }).then(response => {
            if (response.success) {
                this.items = response.data.items || [];
                this.renderTable();
                this.calculateTotal();
            }
        });
    },
    
    renderTable: function() {
        const $tbody = $('#expenseBody');
        $tbody.empty();
        
        if (this.items.length === 0) {
            // Add one empty row by default
            this.addRow();
            return;
        }
        
        this.items.forEach(item => {
            const amount = parseFloat(item.amount) || 0;
            const row = `
                <tr data-id="${item.id || ''}">
                    <td>
                        <input type="text" class="table-input expense-description" 
                            value="${this.escapeHtml(item.description || '')}" 
                            placeholder="Enter description...">
                    </td>
                    <td>
                        <input type="text" class="table-input number-input expense-amount" 
                            value="${amount > 0 ? amount : ''}" 
                            placeholder="0">
                    </td>
                    <td>
                        <button class="btn btn-sm btn-danger remove-expense-row" title="Remove">
                            <i class="fas fa-times"></i>
                        </button>
                    </td>
                </tr>
            `;
            $tbody.append(row);
        });
    },
    
    addRow: function() {
        const row = `
            <tr data-id="">
                <td>
                    <input type="text" class="table-input expense-description" 
                        value="" 
                        placeholder="Enter description...">
                </td>
                <td>
                    <input type="text" class="table-input number-input expense-amount" 
                        value="" 
                        placeholder="0">
                </td>
                <td>
                    <button class="btn btn-sm btn-danger remove-expense-row" title="Remove">
                        <i class="fas fa-times"></i>
                    </button>
                </td>
            </tr>
        `;
        $('#expenseBody').append(row);
    },
    
    calculateTotal: function() {
        let total = 0;
        $('.expense-amount').each(function() {
            const val = $(this).val() || '0';
            total += parseFloat(val.toString().replace(/,/g, '')) || 0;
        });
        $('#expenseTotal').html('<span class="naira">\u20A6</span>' + Stand120.formatNumber(total));
        return total;
    },
    
    saveData: function() {
        const date = $('#expenseDate').val() || new Date().toISOString().split('T')[0];
        const items = [];
        
        $('#expenseBody tr').each(function() {
            const description = $(this).find('.expense-description').val() || '';
            const amountVal = $(this).find('.expense-amount').val() || '0';
            const amount = parseFloat(amountVal.toString().replace(/,/g, '')) || 0;
            
            if (description.trim() || amount > 0) {
                items.push({
                    description: description.trim(),
                    amount: amount
                });
            }
        });
        
        Stand120.showLoading('Saving expenses...');
        
        Stand120.ajax('submit_expenses', {
            date: date,
            items: JSON.stringify(items)
        }).then(response => {
            Stand120.hideLoading();
            if (response.success) {
                Stand120.showAlert('success', 'Market expenses saved successfully');
                // Refresh data to get IDs
                this.loadData();
            } else {
                Stand120.showAlert('danger', response.data?.message || 'Failed to save expenses');
            }
        }).catch(() => {
            Stand120.hideLoading();
            Stand120.showAlert('danger', 'Failed to save expenses');
        });
    },
    
    escapeHtml: function(str) {
        const div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }
};

/**
 * Financial Summary Module
 */
window.FinancialSummary = {
    data: {},
    saveTimeout: null,
    
    init: function() {
        this.bindEvents();
        this.loadData();
    },
    
    bindEvents: function() {
        const self = this;
        
        // Real-time calculation on extras and expenses input
        $(document).off('input.finsummary change.finsummary keyup.finsummary', '#extrasAmount, #expensesAmount, #marketCardExpense');
        $(document).on('input.finsummary change.finsummary keyup.finsummary', '#extrasAmount, #expensesAmount, #marketCardExpense', function() {
            self.calculateCashLeft();
            self.debouncedSave();
        });
        
        // Auto-save for remarks
        $(document).off('input.finsummary change.finsummary', '#extrasRemark, #expensesRemark');
        $(document).on('input.finsummary change.finsummary', '#extrasRemark, #expensesRemark', function() {
            self.debouncedSave();
        });
        
        // Admin can edit old cash - recalculate and save when changed
        if ($('#oldCash').is('input')) {
            $(document).off('input.finsummary change.finsummary keyup.finsummary', '#oldCash');
            $(document).on('input.finsummary change.finsummary keyup.finsummary', '#oldCash', function() {
                self.calculateCashLeft();
                self.debouncedSave();
            });
        }
    },
    
    debouncedSave: function() {
        clearTimeout(this.saveTimeout);
        this.saveTimeout = setTimeout(() => this.saveData(), 500);
    },
    
    loadData: function() {
        const date = $('#finDate').val() || new Date().toISOString().split('T')[0];
        
        Stand120.ajax('get_financial_summary', { date: date }).then(response => {
            if (response.success) {
                this.data = response.data || {};
                this.renderData();
            }
        });
    },
    
    renderData: function() {
        const data = this.data;
        
        $('#totalSales').text('₦' + Stand120.formatNumber(data.total_sales || 0));
        $('#transferSales').text('₦' + Stand120.formatNumber(data.transfer_sales || 0));
        $('#cashSales').text('₦' + Stand120.formatNumber(data.cash_sales || 0));
        $('#deliveryFees').text('₦' + Stand120.formatNumber(data.delivery_fees || 0));
        
        // Old Cash: if it's an input (admin), set value; otherwise set text
        if ($('#oldCash').is('input')) {
            $('#oldCash').val(data.old_cash || '');
        } else {
            $('#oldCash').text('₦' + Stand120.formatNumber(data.old_cash || 0));
        }
        
        $('#extrasAmount').val(data.extras_amount || '');
        $('#extrasRemark').val(data.extras_remark || '');
        $('#expensesAmount').val(data.expenses_amount || '');
        $('#expensesRemark').val(data.expenses_remark || '');
        // Market Card Expense Left is manually editable
        $('#marketCardExpense').val(data.market_card_expense || '');
        
        this.calculateCashLeft();
    },
    
    handleInputChange: function() {
        this.calculateCashLeft();
        this.debouncedSave();
    },
    
    calculateCashLeft: function() {
        const cashSalesText = $('#cashSales').text().replace(/[₦,]/g, '');
        const cashSales = parseFloat(cashSalesText) || 0;
        
        // Old Cash: read from input if admin, or from text
        let oldCash = 0;
        if ($('#oldCash').is('input')) {
            const oldCashVal = $('#oldCash').val() || '0';
            oldCash = parseFloat(oldCashVal.toString().replace(/,/g, '')) || 0;
        } else {
            const oldCashText = $('#oldCash').text().replace(/[₦,]/g, '');
            oldCash = parseFloat(oldCashText) || 0;
        }
        
        const extrasVal = $('#extrasAmount').val() || '0';
        const extras = parseFloat(extrasVal.toString().replace(/,/g, '')) || 0;
        
        // Expenses amount is an editable input
        const expensesVal = $('#expensesAmount').val() || '0';
        const expenses = parseFloat(expensesVal.toString().replace(/,/g, '')) || 0;
        
        // Card Expense Cash Left is manually editable
        const mceVal = $('#marketCardExpense').val() || '0';
        const marketCardExpense = parseFloat(mceVal.toString().replace(/,/g, '')) || 0;
        
        const cashLeft = (cashSales + oldCash + marketCardExpense + extras) - expenses;
        
        // Update cash left immediately
        $('#cashLeft').text('₦' + Stand120.formatNumber(cashLeft));
        
        // Log for debugging
    },
    
    saveData: function() {
        const date = $('#finDate').val() || new Date().toISOString().split('T')[0];
        
        const data = {
            date: date,
            extras_amount: Stand120.parseNumber($('#extrasAmount').val()),
            extras_remark: $('#extrasRemark').val(),
            expenses_amount: Stand120.parseNumber($('#expensesAmount').val()),
            expenses_remark: $('#expensesRemark').val(),
            market_card_expense: Stand120.parseNumber($('#marketCardExpense').val())
        };
        
        // If admin is editing old_cash, include it
        if ($('#oldCash').is('input')) {
            data.old_cash = Stand120.parseNumber($('#oldCash').val());
        }
        
        Stand120.ajax('save_financial_summary', data);
    }
};

/**
 * Product Summary Module
 */
window.ProductSummary = {
    init: function() {
        this.bindEvents();
        this.loadData();
    },
    
    bindEvents: function() {
        $(document).on('click', '#filterBtn', this.loadData.bind(this));
    },
    
    loadData: function() {
        const dateFrom = $('#dateFrom').val() || new Date().toISOString().split('T')[0];
        const dateTo = $('#dateTo').val() || new Date().toISOString().split('T')[0];
        
        Stand120.ajax('get_product_summary', {
            date_from: dateFrom,
            date_to: dateTo
        }).then(response => {
            if (response.success) {
                this.renderSummary(response.data.summary);
                this.renderTable(response.data.records);
            }
        });
    },
    
    renderSummary: function(summary) {
        $('#totalProductsSold').text(Stand120.formatNumber(summary.total_products_sold));
        $('#totalRevenue').text('₦' + Stand120.formatNumber(summary.total_revenue));
        $('#activeStaff').text(summary.active_staff_today);
    },
    
    renderTable: function(records) {
        const $tbody = $('#summaryTable tbody');
        $tbody.empty();
        
        records.forEach(record => {
            const row = `
                <tr>
                    <td>${record.time}</td>
                    <td>${record.date}</td>
                    <td>${record.product}</td>
                    <td>${record.staff || '-'}</td>
                    <td class="formatted-number">${record.quantity}</td>
                    <td class="formatted-number">₦${Stand120.formatNumber(record.amount)}</td>
                </tr>
            `;
            $tbody.append(row);
        });
    }
};

/**
 * Admin Panel Module
 */
window.AdminPanel = {
    init: function() {
        this.bindEvents();
        this.loadData();
        this.loadOpeningValues();
    },
    
    bindEvents: function() {
        $(document).on('click', '#addProduct', this.addProductRow.bind(this));
        $(document).on('click', '.delete-product', this.deleteProduct.bind(this));
        $(document).on('click', '#saveProducts', this.saveProducts.bind(this));
        $(document).on('click', '#addStaff', this.showAddStaffModal.bind(this));
        $(document).on('click', '.edit-staff', this.editStaff.bind(this));
        $(document).on('click', '.delete-staff', this.deleteStaff.bind(this));
        $(document).on('click', '#saveOpeningValues', this.saveOpeningValues.bind(this));
        $(document).on('change', '#openingDate', this.loadOpeningValues.bind(this));
        $(document).on('click', '#clearAllRecords', this.clearAllRecords.bind(this));
        $(document).on('click', '#clearCache', this.clearCache.bind(this));
        $(document).on('click', '#exportData', this.exportData.bind(this));
    },
    
    loadData: function() {
        Stand120.ajax('get_products').then(response => {
            if (response.success) {
                this.renderProducts(response.data.products);
            }
        });
        
        Stand120.ajax('get_staff').then(response => {
            if (response.success) {
                this.renderStaff(response.data.staff);
            }
        });
    },
    
    renderProducts: function(products) {
        const $tbody = $('#productsTable tbody');
        $tbody.empty();
        
        products.forEach(product => {
            const row = `
                <tr data-id="${product.id}">
                    <td><input type="text" class="table-input product-name" value="${product.name}"></td>
                    <td><input type="number" class="table-input product-price" value="${product.price}"></td>
                    <td>
                        <select class="table-input product-type">
                            <option value="menu" ${product.type === 'menu' ? 'selected' : ''}>Menu Item</option>
                            <option value="fruit" ${product.type === 'fruit' ? 'selected' : ''}>Fruit</option>
                            <option value="non_fruit" ${product.type === 'non_fruit' ? 'selected' : ''}>Non-Fruit</option>
                        </select>
                    </td>
                    <td>
                        <button class="btn btn-sm btn-danger delete-product"><i class="fas fa-trash"></i></button>
                    </td>
                </tr>
            `;
            $tbody.append(row);
        });
    },
    
    addProductRow: function() {
        const self = this;
        const content = `
            <form id="addProductForm">
                <div class="form-group">
                    <label class="form-label">Product Name</label>
                    <input type="text" class="form-control" name="name" required placeholder="Enter product name">
                </div>
                <div class="form-group">
                    <label class="form-label">Price (₦)</label>
                    <input type="number" class="form-control" name="price" required placeholder="0" min="0" step="0.01">
                </div>
                <div class="form-group">
                    <label class="form-label">Type</label>
                    <select class="form-control" name="type">
                        <option value="menu">Menu Item</option>
                        <option value="fruit">Fruit</option>
                        <option value="non_fruit">Non-Fruit</option>
                    </select>
                </div>
            </form>
        `;
        
        Stand120.showModal({
            title: 'Add New Product',
            content: content,
            confirmText: 'Add Product'
        }).then(confirmed => {
            if (confirmed) {
                const formData = {};
                $('#addProductForm').serializeArray().forEach(item => {
                    formData[item.name] = item.value;
                });
                
                if (!formData.name || !formData.name.trim()) {
                    Stand120.showAlert('danger', 'Product name is required');
                    return;
                }
                
                Stand120.showLoading('Adding product...');
                
                Stand120.ajax('add_product', formData).then(response => {
                    Stand120.hideLoading();
                    if (response.success) {
                        Stand120.showAlert('success', 'Product added successfully');
                        self.loadData();
                    } else {
                        Stand120.showAlert('danger', response.data?.message || 'Failed to add product');
                    }
                }).catch(err => {
                    Stand120.hideLoading();
                    Stand120.showAlert('danger', 'Failed to add product. Please try again.');
                });
            }
        });
    },
    
    deleteProduct: async function(e) {
        const $row = $(e.target).closest('tr');
        const id = $row.data('id');
        
        if (id === 'new') {
            $row.remove();
            return;
        }
        
        const confirmed = await Stand120.showModal({
            title: 'Delete Product',
            content: 'Are you sure you want to delete this product?',
            confirmText: 'Delete'
        });
        
        if (confirmed) {
            Stand120.ajax('delete_product', { id: id }).then(response => {
                if (response.success) {
                    $row.remove();
                    Stand120.showAlert('success', 'Product deleted successfully');
                }
            });
        }
    },
    
    saveProducts: function() {
        const products = [];
        
        $('#productsTable tbody tr').each(function() {
            const $row = $(this);
            const rowId = $row.data('id');
            // Only update existing products (new products are added via the Add Product modal)
            if (rowId && rowId !== 'new') {
                products.push({
                    id: rowId,
                    name: $row.find('.product-name').val(),
                    price: $row.find('.product-price').val(),
                    type: $row.find('.product-type').val()
                });
            }
        });
        
        if (products.length === 0) {
            Stand120.showAlert('info', 'No products to save');
            return;
        }
        
        // Validate: ensure all products have names
        const emptyNames = products.filter(p => !p.name || !p.name.trim());
        if (emptyNames.length) {
            Stand120.showAlert('danger', 'All products must have a name');
            return;
        }
        
        Stand120.showLoading('Saving products...');
        
        const self = this;
        // Save each product individually
        const promises = products.map(product => {
            return Stand120.ajax('update_product', product);
        });
        
        Promise.allSettled(promises).then((results) => {
            Stand120.hideLoading();
            const failures = results.filter(r => r.status === 'rejected' || (r.status === 'fulfilled' && !r.value.success));
            if (failures.length) {
                const firstFail = failures[0];
                const msg = firstFail.status === 'fulfilled' ? (firstFail.value.data?.message || 'Failed to save some products') : 'Network error saving products';
                Stand120.showAlert('danger', msg);
            } else {
                Stand120.showAlert('success', 'Products saved successfully');
            }
            self.loadData();
        });
    },
    
    renderStaff: function(staff) {
        const $tbody = $('#staffTable tbody');
        $tbody.empty();
        
        staff.forEach(s => {
            const row = `
                <tr data-id="${s.id}">
                    <td>${s.full_name}</td>
                    <td>${s.phone || '-'}</td>
                    <td>${s.role}</td>
                    <td>${s.status}</td>
                    <td>
                        <button class="btn btn-sm btn-secondary edit-staff"><i class="fas fa-edit"></i></button>
                        <button class="btn btn-sm btn-danger delete-staff"><i class="fas fa-trash"></i></button>
                    </td>
                </tr>
            `;
            $tbody.append(row);
        });
    },
    
    showAddStaffModal: function() {
        const content = `
            <form id="staffForm">
                <div class="form-group">
                    <label class="form-label">Full Name</label>
                    <input type="text" class="form-control" name="full_name" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Username</label>
                    <input type="text" class="form-control" name="username" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Email</label>
                    <input type="email" class="form-control" name="email" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Phone</label>
                    <input type="text" class="form-control" name="phone">
                </div>
                <div class="form-group">
                    <label class="form-label">Password</label>
                    <input type="password" class="form-control" name="password" required>
                </div>
            </form>
        `;
        
        Stand120.showModal({
            title: 'Add Staff',
            content: content,
            confirmText: 'Add Staff'
        }).then(confirmed => {
            if (confirmed) {
                const formData = {};
                $('#staffForm').serializeArray().forEach(item => {
                    formData[item.name] = item.value;
                });
                
                Stand120.ajax('create_staff', formData).then(response => {
                    if (response.success) {
                        Stand120.showAlert('success', 'Staff added successfully');
                        this.loadData();
                    } else {
                        Stand120.showAlert('danger', response.data?.message || 'Failed to add staff');
                    }
                });
            }
        });
    },
    
    editStaff: function(e) {
        const $row = $(e.target).closest('tr');
        const staffId = $row.data('id');
        const currentName = $row.find('td').eq(0).text().trim();
        const currentPhone = $row.find('td').eq(1).text().trim();
        const currentStatus = $row.find('td').eq(3).text().trim();
        
        const content = `
            <form id="staffEditForm">
                <div class="form-group">
                    <label class="form-label">Full Name</label>
                    <input type="text" class="form-control" name="full_name" value="${currentName}" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Phone</label>
                    <input type="text" class="form-control" name="phone" value="${currentPhone === '-' ? '' : currentPhone}">
                </div>
                <div class="form-group">
                    <label class="form-label">Status</label>
                    <select class="form-control" name="status">
                        <option value="active" ${currentStatus === 'active' ? 'selected' : ''}>Active</option>
                        <option value="inactive" ${currentStatus === 'inactive' ? 'selected' : ''}>Inactive</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">New Password (optional)</label>
                    <input type="password" class="form-control" name="password" placeholder="Leave blank to keep current">
                </div>
            </form>
        `;
        
        Stand120.showModal({
            title: 'Edit Staff',
            content: content,
            confirmText: 'Save Changes'
        }).then(confirmed => {
            if (confirmed) {
                const formData = {};
                $('#staffEditForm').serializeArray().forEach(item => {
                    formData[item.name] = item.value;
                });
                
                formData.staff_id = staffId;
                
                Stand120.ajax('update_staff', formData).then(response => {
                    if (response.success) {
                        Stand120.showAlert('success', 'Staff updated successfully');
                        this.loadData();
                    } else {
                        Stand120.showAlert('danger', response.data?.message || 'Failed to update staff');
                    }
                });
            }
        });
    },
    
    deleteStaff: async function(e) {
        const $row = $(e.target).closest('tr');
        const staffId = $row.data('id');
        
        const confirmed = await Stand120.showModal({
            title: 'Delete Staff',
            content: 'Are you sure you want to delete this staff member?',
            confirmText: 'Delete'
        });
        
        if (confirmed) {
            Stand120.ajax('delete_staff', { staff_id: staffId }).then(response => {
                if (response.success) {
                    $row.remove();
                    Stand120.showAlert('success', 'Staff deleted successfully');
                }
            });
        }
    },
    
    loadOpeningValues: function() {
        const date = $('#openingDate').val() || new Date().toISOString().split('T')[0];
        
        const requests = [
            Stand120.ajax('get_order_preparation', { date: date }),
            Stand120.ajax('get_stock_inventory', { date: date }),
            Stand120.ajax('get_chopping_inventory', { date: date })
        ];
        
        Promise.all(requests).then(([prepResponse, stockResponse, chopResponse]) => {
            if (prepResponse?.success) {
                this.renderOpeningTable('#prepOpeningTable tbody', prepResponse.data.data, 'opening');
            }
            if (stockResponse?.success) {
                this.renderOpeningTable('#stockOpeningTable tbody', stockResponse.data.data, 'opening');
            }
            if (chopResponse?.success) {
                this.renderOpeningTable('#chopOpeningTable tbody', chopResponse.data.data, 'opening');
            }
        });
    },
    
    renderOpeningTable: function(selector, items, openingField) {
        const $tbody = $(selector);
        $tbody.empty();
        
        if (!items || items.length === 0) {
            $tbody.append('<tr><td colspan="2" style="text-align:center;color:var(--text-muted)">No data</td></tr>');
            return;
        }
        
        items.forEach(item => {
            const openingValue = parseFloat(item[openingField]) || 0;
            const row = `
                <tr data-product-id="${item.product_id}">
                    <td>${item.product_name}</td>
                    <td>
                        <input type="number" class="table-input opening-value" value="${openingValue}" min="0">
                    </td>
                </tr>
            `;
            $tbody.append(row);
        });
    },
    
    saveOpeningValues: function() {
        const date = $('#openingDate').val() || new Date().toISOString().split('T')[0];
        
        const collectValues = (selector) => {
            const values = [];
            $(selector).find('tr[data-product-id]').each(function() {
                const productId = $(this).data('product-id');
                const value = parseFloat($(this).find('.opening-value').val()) || 0;
                values.push({ product_id: productId, value: value });
            });
            return values;
        };
        
        const requests = [
            Stand120.ajax('update_all_opening_values', {
                table: 'order_preparation',
                date: date,
                values: collectValues('#prepOpeningTable tbody')
            }),
            Stand120.ajax('update_all_opening_values', {
                table: 'stock_inventory',
                date: date,
                values: collectValues('#stockOpeningTable tbody')
            }),
            Stand120.ajax('update_all_opening_values', {
                table: 'chopping_inventory',
                date: date,
                values: collectValues('#chopOpeningTable tbody')
            })
        ];
        
        Stand120.showLoading('Saving opening values...');
        Promise.all(requests).then((results) => {
            Stand120.hideLoading();
            const failed = results.find(result => !result.success);
            if (failed) {
                Stand120.showAlert('danger', failed.data?.message || 'Failed to save opening values');
                return;
            }
            Stand120.showAlert('success', 'Opening values saved');
        }).catch(() => {
            Stand120.hideLoading();
            Stand120.showAlert('danger', 'Failed to save opening values');
        });
    },
    
    clearAllRecords: async function() {
        const confirmed = await Stand120.showModal({
            title: 'Clear All Records',
            content: 'This will permanently delete all orders, histories, and analytics records. This action cannot be undone.',
            confirmText: 'Clear All'
        });
        
        if (confirmed) {
            const secondConfirm = await this.confirmClearAllRecords();
            if (!secondConfirm) {
                return;
            }
            
            Stand120.showLoading('Clearing records...');
            Stand120.ajax('clear_all_records').then(response => {
                Stand120.hideLoading();
                if (response.success) {
                    Stand120.showAlert('success', 'All records have been cleared');
                    this.loadData();
                    this.loadOpeningValues();
                } else {
                    Stand120.showAlert('danger', response.data?.message || 'Failed to clear records');
                }
            }).catch(() => {
                Stand120.hideLoading();
                Stand120.showAlert('danger', 'Failed to clear records');
            });
        }
    },
    
    confirmClearAllRecords: function() {
        const modal = $(`
            <div class="modal-overlay active">
                <div class="modal-content">
                    <div class="modal-header">
                        <h3 class="modal-title">Final Confirmation</h3>
                        <button class="modal-close"><i class="fas fa-times"></i></button>
                    </div>
                    <div class="modal-body">
                        <p>Type <strong>CLEAR</strong> to confirm you want to delete all records.</p>
                        <input type="text" class="form-control" id="clearConfirmInput" placeholder="Type CLEAR">
                        <p class="clear-confirm-error" style="color: var(--danger-color); margin-top: 8px; display: none;">Please type CLEAR to continue.</p>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-secondary modal-cancel">Cancel</button>
                        <button class="btn btn-danger modal-confirm">Yes, Clear</button>
                    </div>
                </div>
            </div>
        `);
        
        $('body').append(modal);
        
        return new Promise((resolve) => {
            modal.find('.modal-confirm').on('click', function() {
                const value = modal.find('#clearConfirmInput').val().trim().toUpperCase();
                if (value !== 'CLEAR') {
                    modal.find('.clear-confirm-error').show();
                    return;
                }
                Stand120.closeModal();
                resolve(true);
            });
            
            modal.find('.modal-cancel, .modal-close').on('click', function() {
                Stand120.closeModal();
                resolve(false);
            });
        });
    },
    
    clearCache: function() {
        localStorage.removeItem('stand120_cache');
        localStorage.removeItem('stand120_offline_queue');
        Stand120.showAlert('success', 'Cache cleared successfully');
    },
    
    exportData: function() {
        const today = new Date();
        const startDate = new Date(today);
        startDate.setDate(today.getDate() - 30);
        const todayValue = today.toISOString().split('T')[0];
        const startValue = startDate.toISOString().split('T')[0];
        const content = `
            <form id="exportForm">
                <div class="form-group">
                    <label class="form-label">Data Type</label>
                    <select class="form-control" name="type">
                        <option value="orders">Orders</option>
                        <option value="financial">Financial Summary</option>
                        <option value="stock">Stock Inventory</option>
                        <option value="preparation">Order Preparation</option>
                        <option value="chopping">Chopping Inventory</option>
                        <option value="imports">Import Records</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">From Date</label>
                    <input type="date" class="form-control" name="date_from" value="${startValue}">
                </div>
                <div class="form-group">
                    <label class="form-label">To Date</label>
                    <input type="date" class="form-control" name="date_to" value="${todayValue}">
                </div>
            </form>
        `;
        
        Stand120.showModal({
            title: 'Export Data',
            content: content,
            confirmText: 'Download'
        }).then(confirmed => {
            if (!confirmed) {
                return;
            }
            
            const formData = {};
            $('#exportForm').serializeArray().forEach(item => {
                formData[item.name] = item.value;
            });
            
            Stand120.showLoading('Preparing export...');
            Stand120.ajax('export_data', formData).then(response => {
                Stand120.hideLoading();
                if (response.success) {
                    const exportData = response.data?.data || [];
                    const blob = new Blob([JSON.stringify(exportData, null, 2)], { type: 'application/json' });
                    const url = URL.createObjectURL(blob);
                    const link = document.createElement('a');
                    const dateFrom = formData.date_from || '';
                    const dateTo = formData.date_to || '';
                    let rangeLabel = 'complete';
                    if (dateFrom && dateTo) {
                        rangeLabel = `${dateFrom}-to-${dateTo}`;
                    } else if (dateFrom) {
                        rangeLabel = `from-${dateFrom}`;
                    } else if (dateTo) {
                        rangeLabel = `to-${dateTo}`;
                    }
                    link.href = url;
                    link.download = `stand120-${formData.type}-${rangeLabel}.json`;
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                    URL.revokeObjectURL(url);
                } else {
                    Stand120.showAlert('danger', response.data?.message || 'Failed to export data');
                }
            }).catch(() => {
                Stand120.hideLoading();
                Stand120.showAlert('danger', 'Failed to export data');
            });
        });
    }
};

/**
 * Login Module
 */
window.Login = {
    fallbackDelay: 200,
    init: function() {
        this.bindEvents();
    },
    
    bindEvents: function() {
        $(document).on('submit', '#loginForm', this.handleLogin.bind(this));
    },
    
    handleLogin: function(e) {
        e.preventDefault();
        
        const username = $('#username').val().trim();
        const password = $('#password').val();
        
        if (!username || !password) {
            Stand120.showAlert('danger', 'Please enter username and password');
            return;
        }
        
        $('#loginBtn').prop('disabled', true).html('<span class="loading-spinner"></span> Logging in...');
        
        Stand120.ajax('login', {
            username: username,
            password: password
        }).then(response => {
            if (response.success) {
                // Small delay to ensure cookies are properly set before redirect
                setTimeout(function() {
                    window.location.href = Stand120.config.home_url || '/120-stand/';
                }, 300);
            } else {
                const fallbackDelay = this.fallbackDelay;
                const handleLoginFailure = () => {
                    Stand120.showAlert('danger', response.data?.message || 'Login failed');
                    $('#loginBtn').prop('disabled', false).html('<i class="fas fa-sign-in-alt"></i> Login');
                };
                
                setTimeout(() => {
                    Stand120.ajax('check_login_status')
                        .then(status => {
                            if (status.success && status.data?.is_logged_in) {
                                window.location.href = Stand120.config.home_url || '/120-stand/';
                                return;
                            }
                            handleLoginFailure();
                        })
                        .catch(handleLoginFailure);
                }, fallbackDelay);
            }
        }).catch(() => {
            Stand120.showAlert('danger', 'An error occurred. Please try again.');
            $('#loginBtn').prop('disabled', false).html('<i class="fas fa-sign-in-alt"></i> Login');
        });
    }
};

/**
 * Reconciliation Calendar Module
 */
window.ReconciliationCalendar = {
    currentYear: new Date().getFullYear(),
    currentMonth: new Date().getMonth(),
    statusData: {},
    selectedDate: null,
    
    init: function() {
        this.bindEvents();
        this.renderCalendar();
        this.loadStatus();
    },
    
    bindEvents: function() {
        $(document).on('click', '#prevMonth', () => {
            this.currentMonth--;
            if (this.currentMonth < 0) {
                this.currentMonth = 11;
                this.currentYear--;
            }
            this.renderCalendar();
            this.loadStatus();
        });
        
        $(document).on('click', '#nextMonth', () => {
            this.currentMonth++;
            if (this.currentMonth > 11) {
                this.currentMonth = 0;
                this.currentYear++;
            }
            this.renderCalendar();
            this.loadStatus();
        });
        
        $(document).on('click', '.cal-day:not(.empty):not(.future)', (e) => {
            const date = $(e.currentTarget).data('date');
            if (date) {
                this.openReconcileModal(date);
            }
        });
        
        $(document).on('click', '#submitReconcile', () => {
            this.submitReconciliation();
        });
        
        $(document).on('click', '#cancelReconcile, #closeReconcileComplete', () => {
            this.closeModal();
        });
        
        // Close modal on overlay click
        $(document).on('click', '#reconcileModal', (e) => {
            if (e.target.id === 'reconcileModal') {
                this.closeModal();
            }
        });
    },
    
    renderCalendar: function() {
        const monthNames = ['January', 'February', 'March', 'April', 'May', 'June',
            'July', 'August', 'September', 'October', 'November', 'December'];
        const dayNames = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
        
        $('#calendarMonth').text(monthNames[this.currentMonth] + ' ' + this.currentYear);
        
        const firstDay = new Date(this.currentYear, this.currentMonth, 1).getDay();
        const daysInMonth = new Date(this.currentYear, this.currentMonth + 1, 0).getDate();
        const today = new Date();
        const todayStr = today.getFullYear() + '-' + String(today.getMonth() + 1).padStart(2, '0') + '-' + String(today.getDate()).padStart(2, '0');
        
        let html = '<div class="cal-header">';
        dayNames.forEach(d => { html += '<div>' + d + '</div>'; });
        html += '</div><div class="cal-grid">';
        
        // Empty cells before first day
        for (let i = 0; i < firstDay; i++) {
            html += '<div class="cal-day empty"></div>';
        }
        
        // Day cells
        for (let day = 1; day <= daysInMonth; day++) {
            const dateStr = this.currentYear + '-' + String(this.currentMonth + 1).padStart(2, '0') + '-' + String(day).padStart(2, '0');
            const isFuture = dateStr > todayStr;
            const isToday = dateStr === todayStr;
            
            let statusClass = '';
            let statusText = '';
            
            if (this.statusData[dateStr]) {
                const info = this.statusData[dateStr];
                if (info.is_complete) {
                    statusClass = 'complete';
                    statusText = '<i class="fas fa-lock" style="font-size: 0.7rem;"></i>';
                } else {
                    statusClass = 'partial';
                    statusText = '1/2';
                }
            }
            
            if (isFuture) statusClass += ' future';
            if (isToday) statusClass += ' today';
            
            html += '<div class="cal-day ' + statusClass + '" data-date="' + dateStr + '">';
            html += '<span class="day-num">' + day + '</span>';
            if (statusText) {
                html += '<span class="day-status">' + statusText + '</span>';
            }
            html += '</div>';
        }
        
        html += '</div>';
        $('#reconciliationCalendar').html(html);
    },
    
    loadStatus: function() {
        const startDate = this.currentYear + '-' + String(this.currentMonth + 1).padStart(2, '0') + '-01';
        const daysInMonth = new Date(this.currentYear, this.currentMonth + 1, 0).getDate();
        const endDate = this.currentYear + '-' + String(this.currentMonth + 1).padStart(2, '0') + '-' + String(daysInMonth).padStart(2, '0');
        
        Stand120.ajax('get_reconciliation_status', {
            start_date: startDate,
            end_date: endDate
        }).then(response => {
            if (response.success) {
                this.statusData = response.data.dates || {};
                this.renderCalendar();
            }
        });
    },
    
    openReconcileModal: function(date) {
        this.selectedDate = date;
        const dateObj = new Date(date + 'T00:00:00');
        const formatted = dateObj.toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
        
        $('#reconcileDate').text(formatted);
        $('#reconcileRemark').val('');
        
        // Load details for this date
        Stand120.ajax('get_reconciliation_for_date', { date: date }).then(response => {
            if (response.success) {
                const data = response.data;
                
                // Show previous submissions
                if (data.reconciliations && data.reconciliations.length > 0) {
                    let listHtml = '';
                    data.reconciliations.forEach(r => {
                        const time = new Date(r.created_at).toLocaleString();
                        listHtml += '<div class="reconciliation-item">';
                        listHtml += '<div class="staff-name"><i class="fas fa-user-check"></i> ' + (r.staff_name || 'Admin') + '</div>';
                        listHtml += '<div class="reconcile-time">' + time + '</div>';
                        if (r.remark) {
                            listHtml += '<div class="reconcile-remark"><i class="fas fa-comment"></i> ' + r.remark + '</div>';
                        }
                        listHtml += '</div>';
                    });
                    $('#reconciliationList').html(listHtml);
                    $('#previousReconciliations').show();
                } else {
                    $('#previousReconciliations').hide();
                }
                
                // Show form or complete message
                if (data.is_complete) {
                    $('#reconcileForm').hide();
                    $('#reconcileComplete').show();
                } else {
                    $('#reconcileForm').show();
                    $('#reconcileComplete').hide();
                    
                    if (data.has_submitted) {
                        $('#submitReconcile').html('<i class="fas fa-edit"></i> Update Reconciliation');
                    } else {
                        $('#submitReconcile').html('<i class="fas fa-check"></i> Submit Reconciliation');
                    }
                }
                
                $('#reconcileModal').css('display', 'flex');
            }
        });
    },
    
    submitReconciliation: function() {
        if (!this.selectedDate) return;
        
        const remark = $('#reconcileRemark').val().trim();
        
        $('#submitReconcile').prop('disabled', true).html('<span class="loading-spinner"></span> Submitting...');
        
        Stand120.ajax('submit_reconciliation', {
            date: this.selectedDate,
            remark: remark
        }).then(response => {
            $('#submitReconcile').prop('disabled', false);
            
            if (response.success) {
                Stand120.showAlert('success', response.data.message);
                this.closeModal();
                this.loadStatus();
            } else {
                Stand120.showAlert('danger', response.data?.message || 'Failed to submit reconciliation');
                $('#submitReconcile').html('<i class="fas fa-check"></i> Submit Reconciliation');
            }
        }).catch(() => {
            $('#submitReconcile').prop('disabled', false).html('<i class="fas fa-check"></i> Submit Reconciliation');
            Stand120.showAlert('danger', 'Failed to submit reconciliation');
        });
    },
    
    closeModal: function() {
        $('#reconcileModal').hide();
        this.selectedDate = null;
    }
};

})(jQuery);