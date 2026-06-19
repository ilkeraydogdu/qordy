/**
 * Orders Management Page JavaScript
 * Handles order listing, filtering, and status updates
 */

// Import logger (fallback to console if not available)
const logger = (typeof window !== 'undefined' && window.logger) || {
    debug: () => {},
    info: () => {},
    warn: console.warn ? console.warn.bind(console) : () => {},
    error: console.error ? console.error.bind(console) : () => {},
    log: () => {}
};

// OrdersPage module
const OrdersPage = {
    config: {
        baseUrl: '',
        apiPrefix: '/api/business',
        translations: {}
    },
    
    state: {
        currentStatusFilter: 'all',
        currentDateFilter: 'all',
        currentSearchQuery: '',
        allOrders: []
    },
    
    /**
     * Initialize orders page
     * @param {Object} config - Configuration object with baseUrl and translations
     */
    init: function(config) {
        this.config = { ...this.config, ...config };
        this.setupEventListeners();
        this.loadOrders();
        this.initRealtimeUpdates();
    },
    
    /**
     * Setup event listeners
     */
    setupEventListeners: function() {
        // Search input - bind both oninput and keyup for reliability
        const searchInput = document.getElementById('searchInput');
        if (searchInput) {
            searchInput.addEventListener('input', () => this.filterOrders());
            searchInput.addEventListener('keyup', () => this.filterOrders());
        }
        
        // Re-render on window resize
        let resizeTimer;
        window.addEventListener('resize', () => {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(() => {
                this.loadOrders();
            }, 250);
        });
    },
    
    /**
     * Refresh orders
     */
    refreshOrders: function() {
        this.loadOrders();
    },
    
    /**
     * Export orders
     */
    exportOrders: function() {
        // Get current filters
        const status = this.state.currentStatusFilter || 'all';
        const dateFilter = this.state.currentDateFilter || 'all';
        const searchQuery = this.state.currentSearchQuery || '';
        
        // Calculate date range if needed
        let startDate = null;
        let endDate = null;
        
        if (typeof dateFilter === 'object' && dateFilter.start && dateFilter.end) {
            startDate = dateFilter.start;
            endDate = dateFilter.end;
        } else if (dateFilter !== 'all') {
            const now = new Date();
            switch (dateFilter) {
                case 'today':
                    startDate = now.toISOString().split('T')[0];
                    endDate = now.toISOString().split('T')[0];
                    break;
                case 'week':
                    const dayOfWeek = now.getDay();
                    const weekStart = new Date(now);
                    weekStart.setDate(now.getDate() - dayOfWeek);
                    startDate = weekStart.toISOString().split('T')[0];
                    endDate = now.toISOString().split('T')[0];
                    break;
                case 'month':
                    startDate = new Date(now.getFullYear(), now.getMonth(), 1).toISOString().split('T')[0];
                    endDate = new Date(now.getFullYear(), now.getMonth() + 1, 0).toISOString().split('T')[0];
                    break;
            }
        }
        
        // Show format selection (simplified - can be enhanced with modal)
        const format = prompt('Dışa aktarma formatı seçin:\n1 - CSV\n2 - Excel\n3 - PDF\n\nNumara girin (1, 2 veya 3):', '1');
        
        let exportFormat = 'csv';
        if (format === '2') {
            exportFormat = 'excel';
        } else if (format === '3') {
            exportFormat = 'pdf';
        }
        
        if (!format) {
            return; // User cancelled
        }
        
        // Build export URL
        const params = new URLSearchParams({
            format: exportFormat,
            status: status,
            date_filter: typeof dateFilter === 'object' ? 'custom' : dateFilter
        });
        
        if (startDate) params.append('start_date', startDate);
        if (endDate) params.append('end_date', endDate);
        if (searchQuery) params.append('search', searchQuery);
        
        const exportUrl = `${this.config.baseUrl}${this.config.apiPrefix || '/api/business'}/export-orders?${params.toString()}`;
        
        // Open in new window to trigger download
        window.open(exportUrl, '_blank');
        
        if (window.NotificationManager) {
            window.NotificationManager.success('Dışa aktarma başlatıldı...');
        }
    },
    
    /**
     * Toggle status filter menu (legacy - kept for compatibility)
     */
    toggleStatusFilter: function() {
        const menu = document.getElementById('statusFilterMenu');
        if (menu) menu.classList.toggle('hidden');
    },
    
    /**
     * Filter by status - chip based
     */
    filterByStatus: function(status) {
        this.state.currentStatusFilter = status;
        // Update active chip
        document.querySelectorAll('.filter-chip[data-status]').forEach(c => c.classList.remove('active', 'q-btn--ink'));
        const activeChip = document.querySelector(`.filter-chip[data-status="${status}"]`);
        if (activeChip) activeChip.classList.add('active', 'q-btn--ink');
        // Durum değişince API'den yeniden yükle (iptal/eksiltilen dahil)
        this.loadOrders();
    },
    
    /**
     * Toggle date filter menu (legacy - kept for compatibility)
     */
    toggleDateFilter: function() {
        const menu = document.getElementById('dateFilterMenu');
        if (menu) menu.classList.toggle('hidden');
    },
    
    /**
     * Filter by date range - chip based
     */
    filterByDate: function(dateRange) {
        this.state.currentDateFilter = dateRange;
        // Update active chip
        document.querySelectorAll('.filter-chip[data-date]').forEach(c => c.classList.remove('active', 'q-btn--ink'));
        const activeChip = document.querySelector(`.filter-chip[data-date="${dateRange}"]`);
        if (activeChip) activeChip.classList.add('active', 'q-btn--ink');
        
        if (dateRange === 'custom') {
            const customRange = document.getElementById('customDateRange');
            if (customRange) customRange.classList.remove('hidden');
        } else {
            const customRange = document.getElementById('customDateRange');
            if (customRange) customRange.classList.add('hidden');
            // Tarih değişince API'den yeniden yükle (iptal/eksiltilen kayıtlar dahil)
            this.loadOrders();
        }
    },
    
    /**
     * Apply custom date range
     */
    applyCustomDateRange: function() {
        const startDate = document.getElementById('startDate')?.value;
        const endDate = document.getElementById('endDate')?.value;
        
        if (!startDate || !endDate) {
            if (window.NotificationManager) {
                window.NotificationManager.warning('Lütfen başlangıç ve bitiş tarihlerini seçin.');
            }
            return;
        }
        
        if (startDate > endDate) {
            if (window.NotificationManager) {
                window.NotificationManager.warning('Başlangıç tarihi bitiş tarihinden sonra olamaz.');
            }
            return;
        }
        
        this.state.currentDateFilter = { start: startDate, end: endDate };
        // Özel tarih aralığı ile API'den yeniden yükle
        this.loadOrders();
    },
    
    /**
     * Filter orders by search query (debounced)
     */
    filterOrders: function() {
        const searchInput = document.getElementById('searchInput');
        if (searchInput) {
            this.state.currentSearchQuery = searchInput.value.toLowerCase().trim();
        }
        // Debounce to avoid excessive re-renders
        clearTimeout(this._searchTimer);
        this._searchTimer = setTimeout(() => {
            this.applyFilters();
        }, 150);
    },
    
    /**
     * Apply all filters
     */
    applyFilters: function() {
        let filtered = [...this.state.allOrders];
        
        // Apply status filter
        if (this.state.currentStatusFilter !== 'all') {
            filtered = filtered.filter(o => o.status === this.state.currentStatusFilter);
        }
        
        // Apply date filter
        if (this.state.currentDateFilter !== 'all') {
            const now = new Date();
            let startDate, endDate;
            
            if (typeof this.state.currentDateFilter === 'object') {
                startDate = new Date(this.state.currentDateFilter.start);
                endDate = new Date(this.state.currentDateFilter.end);
                endDate.setHours(23, 59, 59, 999);
            } else {
                switch (this.state.currentDateFilter) {
                    case 'today':
                        startDate = new Date(now.getFullYear(), now.getMonth(), now.getDate());
                        endDate = new Date(now.getFullYear(), now.getMonth(), now.getDate(), 23, 59, 59, 999);
                        break;
                    case 'week':
                        const dayOfWeek = now.getDay();
                        startDate = new Date(now);
                        startDate.setDate(now.getDate() - dayOfWeek);
                        startDate.setHours(0, 0, 0, 0);
                        endDate = new Date(now);
                        endDate.setHours(23, 59, 59, 999);
                        break;
                    case 'month':
                        startDate = new Date(now.getFullYear(), now.getMonth(), 1);
                        endDate = new Date(now.getFullYear(), now.getMonth() + 1, 0, 23, 59, 59, 999);
                        break;
                    default:
                        startDate = null;
                        endDate = null;
                }
            }
            
            if (startDate && endDate) {
                filtered = filtered.filter(order => {
                    const orderDate = new Date(order.date);
                    return orderDate >= startDate && orderDate <= endDate;
                });
            }
        }
        
        // Apply search filter - Sipariş numarası öncelikli (order ID, kısa ID, masa, müşteri)
        if (this.state.currentSearchQuery) {
            const q = this.state.currentSearchQuery;
            filtered = filtered.filter(order => {
                const orderId = (order.id || '').toLowerCase();
                const shortId4 = orderId.length > 4 ? orderId.slice(-4) : orderId;
                const shortId6 = orderId.length > 6 ? orderId.slice(-6) : orderId;
                const shortId8 = orderId.length > 8 ? orderId.slice(-8) : orderId;
                const table = (order.table || '').toLowerCase();
                const customer = (order.customer || '').toLowerCase();
                const zone = (order.zone_name || '').toLowerCase();
                // Sipariş numarası öncelikli eşleşme
                return orderId.includes(q) ||
                       shortId4.includes(q) ||
                       shortId6.includes(q) ||
                       shortId8.includes(q) ||
                       table.includes(q) ||
                       customer.includes(q) ||
                       zone.includes(q);
            });
        }
        
        this.renderOrders(filtered);
    },
    
    /**
     * Update summary statistics
     */
    updateSummary: function(orders) {
        const totalCount = orders.length;
        // Revenue must mirror the canonical OrderRepository predicate
        // (status != CANCELLED AND (is_paid OR status = SERVED)). Summing every
        // order — including cancelled/unpaid — overstates the figure and drifts
        // from the dashboard/finance/analytics totals.
        const totalAmount = orders.reduce((sum, order) => {
            const isRevenue = order.status !== 'cancelled' && (order.is_paid === true || order.status === 'served');
            return isRevenue ? sum + (order.amount || 0) : sum;
        }, 0);
        const pendingCount = orders.filter(o => o.status === 'pending').length;
        const readyCount = orders.filter(o => o.status === 'ready').length;
        
        const formatCurrency = window.Utils?.formatCurrency || function(amount) {
            return new Intl.NumberFormat('tr-TR', { style: 'currency', currency: 'TRY' }).format(amount);
        };
        
        const totalOrdersCount = document.getElementById('totalOrdersCount');
        const totalOrdersAmount = document.getElementById('totalOrdersAmount');
        const pendingOrdersCount = document.getElementById('pendingOrdersCount');
        const readyOrdersCount = document.getElementById('readyOrdersCount');
        
        if (totalOrdersCount) totalOrdersCount.textContent = totalCount;
        if (totalOrdersAmount) totalOrdersAmount.textContent = formatCurrency(totalAmount);
        if (pendingOrdersCount) pendingOrdersCount.textContent = pendingCount;
        if (readyOrdersCount) readyOrdersCount.textContent = readyCount;
    },
    
    /**
     * Get date range for API based on current date filter
     */
    getApiDateRange: function() {
        const now = new Date();
        let startDate, endDate;
        const df = this.state.currentDateFilter;
        if (typeof df === 'object' && df.start && df.end) {
            startDate = df.start;
            endDate = df.end;
        } else if (df === 'today') {
            startDate = endDate = now.toISOString().split('T')[0];
        } else if (df === 'week') {
            const d = new Date(now);
            d.setDate(now.getDate() - now.getDay());
            startDate = d.toISOString().split('T')[0];
            endDate = now.toISOString().split('T')[0];
        } else if (df === 'month') {
            startDate = new Date(now.getFullYear(), now.getMonth(), 1).toISOString().split('T')[0];
            endDate = new Date(now.getFullYear(), now.getMonth() + 1, 0).toISOString().split('T')[0];
        } else {
            // all veya varsayılan: son 30 gün
            const d = new Date(now);
            d.setDate(now.getDate() - 30);
            startDate = d.toISOString().split('T')[0];
            endDate = now.toISOString().split('T')[0];
        }
        return { startDate, endDate };
    },
    
    /**
     * Load orders from API
     */
    async loadOrders() {
        try {
            const { startDate, endDate } = this.getApiDateRange();
            const status = this.state.currentStatusFilter || 'all';
            const params = new URLSearchParams({ start_date: startDate, end_date: endDate, status: status, limit: 500 });
            const apiUrl = `${this.config.baseUrl}/api/orders?${params.toString()}`;
            logger.debug('Loading orders from:', apiUrl);
            
            const response = await fetch(apiUrl, {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                credentials: 'same-origin'
            });
            
            logger.debug('Response status:', response.status, response.statusText);
            
            if (!response.ok) {
                logger.error('HTTP error:', response.status, response.statusText);
                const errorText = await response.text();
                logger.error('Error response:', errorText);
                this.renderOrders([]);
                
                if (window.NotificationManager) {
                    window.NotificationManager.error('Siparişler yüklenirken hata oluştu: ' + response.status);
                }
                return;
            }
            
            const data = await response.json();
            logger.debug('API response:', data);
            
            if (data.error || !data.success) {
                logger.error('Error loading orders:', data.error || 'Unknown error');
                this.renderOrders([]);
                
                if (window.NotificationManager) {
                    window.NotificationManager.error(data.error || 'Siparişler yüklenemedi');
                }
                return;
            }
            
            // Map database format to view format
            const ordersData = data.orders || (Array.isArray(data) ? data : []);
            logger.debug('Orders data:', ordersData.length, 'orders found');
            
            if (!Array.isArray(ordersData)) {
                logger.error('Orders data is not an array:', ordersData);
                this.renderOrders([]);
                return;
            }
            
            const orders = ordersData.map(order => {
                // Map status from uppercase to lowercase
                const statusMap = {
                    'PENDING': 'pending',
                    'PREPARING': 'preparing',
                    'READY': 'ready',
                    'SERVED': 'served',
                    'CANCELLED': 'cancelled',
                    'REFUNDED': 'refunded'
                };
                
                return {
                    id: order.order_id || order.id,
                    table_id: order.table_id || '',
                    table: order.table_name || order.table_name_from_db || order.table || 'Bilinmiyor',
                    table_floor: order.table_floor || '',
                    table_section: order.table_section || '',
                    zone_id: order.zone_id || '',
                    zone_name: order.zone_name || 'Bölgesiz',
                    zone_floor: order.zone_floor || '',
                    zone_description: order.zone_description || '',
                    customer: order.created_by || order.customer_name || 'QR Sipariş',
                    amount: parseFloat(order.total_amount || 0),
                    is_paid: parseInt(order.is_paid || 0, 10) === 1,
                    status: statusMap[order.status] || order.status?.toLowerCase() || 'pending',
                    date: order.created_at || order.date || ''
                };
            });

            logger.debug('Mapped orders:', orders.length);
            this.state.allOrders = orders;
            this.applyFilters();
        } catch (error) {
            logger.error('Error loading orders:', error);
            logger.error('Error stack:', error.stack);
            this.renderOrders([]);
            
            if (window.NotificationManager) {
                window.NotificationManager.error('Siparişler yüklenirken bir hata oluştu. Lütfen sayfayı yenileyin.');
            }
        }
    },
    
    /**
     * Render orders to DOM - Sipariş numarası odaklı liste, her sipariş için işlem butonları
     */
    renderOrders: function(orders) {
        this.updateSummary(orders);
        const zoneView = document.getElementById('ordersZoneView');
        const emptyStateView = document.getElementById('emptyStateView');
        
        const formatCurrency = window.Utils?.formatCurrency || function(amount) {
            return new Intl.NumberFormat('tr-TR', { style: 'currency', currency: 'TRY' }).format(amount);
        };
        
        const escapeHtml = window.Utils?.escapeHtml || function(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        };

        // Show empty state if no orders
        if (!orders || orders.length === 0) {
            if (zoneView) {
                if (emptyStateView) {
                    emptyStateView.classList.remove('hidden');
                    zoneView.innerHTML = '';
                    zoneView.appendChild(emptyStateView);
                }
            }
            return;
        }

        // Hide empty state
        if (emptyStateView) emptyStateView.classList.add('hidden');

        // Siparişleri tarihe göre sırala (en yeni en üstte)
        const sortedOrders = [...orders].sort((a, b) => {
            const dateA = new Date(a.date || 0).getTime();
            const dateB = new Date(b.date || 0).getTime();
            return dateB - dateA;
        });

        if (zoneView) {
            let html = `
                <div class="q-card overflow-hidden fade-in">
                    <div class="overflow-x-auto">
                        <table class="q-table">
                            <thead>
                                <tr>
                                    <th>Sipariş No</th>
                                    <th>Masa</th>
                                    <th class="text-right">Tutar</th>
                                    <th>Durum</th>
                                    <th>Tarih</th>
                                    <th class="text-right" style="width:10rem">İşlemler</th>
                                </tr>
                            </thead>
                            <tbody>
            `;
            
            sortedOrders.forEach((order, idx) => {
                const orderId = order.id || '';
                const shortId = orderId.length > 8 ? orderId.slice(-8) : orderId;
                const tableName = escapeHtml(order.table || 'Bilinmiyor');
                const amount = formatCurrency(order.amount || 0);
                const statusBadge = this.getStatusBadge((order.status || '').toString().toLowerCase() || 'pending');
                const orderDate = order.date ? new Date(order.date).toLocaleString('tr-TR', {
                    day: '2-digit', month: '2-digit', year: 'numeric',
                    hour: '2-digit', minute: '2-digit'
                }) : '-';
                
                html += `
                    <tr class="order-row">
                        <td>
                            <span class="font-mono font-bold">#${escapeHtml(shortId)}</span>
                            <span class="text-xs block" style="color:var(--color-text-muted)">${escapeHtml(orderId)}</span>
                        </td>
                        <td>${tableName}</td>
                        <td class="text-right font-semibold">${amount}</td>
                        <td>${statusBadge}</td>
                        <td class="text-xs" style="color:var(--color-text-secondary)">${orderDate}</td>
                        <td class="text-right">
                            <div class="q-toolbar" style="justify-content:flex-end;gap:6px;">
                                <button type="button" onclick="showOrderDetails('${escapeHtml(orderId)}')" class="q-btn q-btn--primary q-btn--sm" title="Detay">Detay</button>
                                <button type="button" onclick="printOrder('${escapeHtml(orderId)}')" class="q-btn q-btn--ghost q-btn--sm" title="Yazdır">Yazdır</button>
                                <button type="button" onclick="showTableSessionsModal('${(order.table_id || '').replace(/'/g, "\\'")}', '${String(order.table || 'Bilinmiyor').replace(/\\/g, '\\\\').replace(/'/g, "\\'")}')" class="q-btn q-btn--soft q-btn--sm" title="Masa">Masa</button>
                            </div>
                        </td>
                    </tr>
                `;
            });
            
            html += `
                            </tbody>
                        </table>
                    </div>
                </div>
            `;
            
            zoneView.innerHTML = html;
        }
    },
    
    /**
     * Get status badge HTML
     */
    getStatusBadge: function(status) {
        const s = (status || '').toString().toLowerCase().trim();
        const badges = {
            'pending': `<span class="q-badge q-badge--info">${this.config.translations.waiting || 'Beklemede'}</span>`,
            'preparing': `<span class="q-badge q-badge--warning">${this.config.translations.preparing || 'Hazırlanıyor'}</span>`,
            'ready': `<span class="q-badge q-badge--live">${this.config.translations.readyToServe || 'Hazır'}</span>`,
            'served': `<span class="q-badge q-badge--success">${this.config.translations.served || this.config.translations.completed || 'Tamamlandı'}</span>`,
            'cancelled': `<span class="q-badge q-badge--danger">${this.config.translations.cancelledStatus || 'İptal'}</span>`,
            'refunded': `<span class="q-badge q-badge--neutral">İade</span>`
        };
        return badges[s] || (s ? `<span class="q-badge q-badge--neutral">${s}</span>` : badges['pending']);
    },
    
    /**
     * Get action button HTML
     * Removed - Status updates should be done in kitchen page, not admin orders page
     */
    getActionButton: function(order) {
        // No action buttons in admin orders page - status updates are handled in kitchen
        return '';
    },
    
    /**
     * Show order details modal
     */
    showOrderDetails: function(orderId) {
        const modal = document.getElementById('orderDetailsModal');
        const content = document.getElementById('orderDetailsContent');
        
        if (!modal || !content) return;
        
        // Fetch order details from API
        fetch(`${this.config.baseUrl}${this.config.apiPrefix || '/api/business'}/qodmin/getOrder?id=${orderId}`)
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    const msg = this.config.translations.orderDetailsFailed || 'Sipariş detayları yüklenemedi';
                    if (window.NotificationManager) {
                        window.NotificationManager.error(msg);
                    }
                    return;
                }
                
                // Map status for button display
                const statusMap = {
                    'PENDING': 'pending',
                    'PREPARING': 'preparing',
                    'READY': 'ready',
                    'SERVED': 'served',
                    'CANCELLED': 'cancelled',
                    'REFUNDED': 'refunded'
                };
                const mappedStatus = statusMap[data.status] || data.status?.toLowerCase() || 'pending';
                
                const formatCurrency = window.Utils?.formatCurrency || function(amount) {
                    return new Intl.NumberFormat('tr-TR', { style: 'currency', currency: 'TRY' }).format(amount);
                };
                
                const escapeHtml = window.Utils?.escapeHtml || function(text) {
                    const div = document.createElement('div');
                    div.textContent = text;
                    return div.innerHTML;
                };
                
                content.innerHTML = `
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 sm:gap-6 mb-4 sm:mb-6">
                        <div class="q-card q-card--pad" style="background:var(--color-surface-muted);">
                            <h3 class="q-label" style="font-size:var(--font-size-sm);margin-bottom:var(--space-1);">Sipariş Bilgileri</h3>
                            <div class="space-y-2 text-sm">
                                <div class="flex justify-between">
                                    <span class="text-slate-500">${this.config.translations.orderId || 'Sipariş ID'}:</span>
                                    <span class="font-bold">#${escapeHtml(data.order_id || orderId)}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-slate-500">${this.config.translations.table || 'Masa'}:</span>
                                    <span class="font-bold">${escapeHtml(data.table_name || this.config.translations.unknown || 'Bilinmiyor')}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-slate-500">${this.config.translations.status || 'Durum'}:</span>
                                    ${this.getStatusBadge((data.status || '').toString().toLowerCase() || 'pending')}
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-slate-500">Tarih:</span>
                                    <span class="font-bold">${data.created_at ? new Date(data.created_at).toLocaleString('tr-TR') : '-'}</span>
                                </div>
                            </div>
                        </div>
                        <div class="q-card q-card--pad" style="background:var(--color-surface-muted);">
                            <h3 class="q-label" style="font-size:var(--font-size-sm);margin-bottom:var(--space-1);">Ödeme Bilgileri</h3>
                            <div class="space-y-2 text-sm">
                                <div class="flex justify-between">
                                    <span class="text-slate-500">${this.config.translations.total || 'Toplam'}:</span>
                                    <span class="font-black text-lg">${formatCurrency(data.total_amount || 0)}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-slate-500">${this.config.translations.payment || 'Ödeme'}:</span>
                                    <span class="font-bold">${escapeHtml(data.payment_method || this.config.translations.cash || 'Nakit')}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="mb-4 sm:mb-6">
                        <h3 class="font-black text-sm sm:text-base mb-3">${this.config.translations.orderItems || 'Sipariş Öğeleri'}</h3>
                        <div class="q-card q-card--pad" style="background:var(--color-surface-muted);">
                            ${(data.items || []).length === 0 ? 
                                '<div class="text-center py-4 text-slate-400 text-sm">' + 
                                (this.config.translations.orderItems || 'Sipariş Öğeleri') + ' bulunamadı.</div>' 
                                : (window.Utils && window.Utils.groupOrderItemsForDisplay ? window.Utils.groupOrderItemsForDisplay(data.items) : data.items).map(item => {
                                    const itemName = item.name || item.item_name || item.menu_item_name || this.config.translations.product || 'Ürün';
                                    const quantity = parseInt(item.quantity || 1);
                                    const price = parseFloat(item.price || 0);
                                    const total = price * quantity;
                                    const note = item.note || item.notes || '';
                                    
                                    return '<div class="flex justify-between items-start">' +
                                        '<div class="flex-1">' +
                                        '<div class="font-bold text-sm">' + escapeHtml(itemName) + '</div>' +
                                        '<div class="text-xs text-slate-500">' +
                                        (this.config.translations.quantity || 'Adet') + ': ' + quantity +
                                        (note ? ' • Not: ' + escapeHtml(note) : '') +
                                        '</div>' +
                                        '</div>' +
                                        '<div class="font-black text-right ml-4">' + formatCurrency(total) + '</div>' +
                                        '</div>';
                                }).join('')
                            }
                        </div>
                    </div>
                    <div class="q-toolbar" style="gap:var(--space-3);">
                        <button type="button" onclick="closeOrderModal()" class="q-btn q-btn--secondary" style="flex:1;">
                            ${this.config.translations.close || 'Kapat'}
                        </button>
                        <button type="button" onclick="printOrder('${escapeHtml(orderId)}')" class="q-btn q-btn--primary" style="flex:1;">
                            ${this.config.translations.print || 'Yazdır'}
                        </button>
                    </div>
                    <div class="q-card q-card--pad" style="margin-top:var(--space-3);background:var(--color-amber-soft);border-color:var(--color-brand-accent);">
                        <p class="text-xs font-medium" style="color:var(--color-brand-accent-hover);">
                            Durum güncellemeleri (Hazırlama, Hazır, Servis) mutfak sayfasından yapılır.
                        </p>
                    </div>
                `;
                
                modal.classList.remove('hidden');
                modal.classList.add('flex');
            })
            .catch(error => {
                if (window.logger) {
                    window.logger.error('Error:', error);
                }
                const msg = this.config.translations.errorLoading || 'Sipariş detayları yüklenirken hata oluştu';
                if (window.NotificationManager) {
                    window.NotificationManager.error(msg);
                }
            });
    },
    
    /**
     * Close order modal
     */
    closeOrderModal: function() {
        const modal = document.getElementById('orderDetailsModal');
        if (modal) {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }
    },
    
    /**
     * Show table sessions modal
     * Displays customer sessions (groups of orders) for a specific table
     */
    showTableSessionsModal: function(tableId, tableName) {
        const modal = document.getElementById('tableSessionsModal');
        const content = document.getElementById('tableSessionsContent');
        
        if (!modal || !content) return;
        
        // Show loading state
        content.innerHTML = `
            <div class="q-empty" style="padding:var(--space-8);">
                <span class="q-spinner" aria-hidden="true"></span>
                <p class="q-hint">Yükleniyor…</p>
            </div>
        `;
        
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        
        // Fetch table sessions from API
        fetch(`${this.config.baseUrl}/api/orders/table-sessions?table_id=${encodeURIComponent(tableId)}`, {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            credentials: 'same-origin'
        })
            .then(response => response.json())
            .then(data => {
                if (!data.success || data.error) {
                    const msg = data.error || 'Masa oturumları yüklenemedi';
                    content.innerHTML = `
                        <div class="text-center py-8">
                            <p class="text-red-600 font-bold">${msg}</p>
                        </div>
                    `;
                    if (window.NotificationManager) {
                        window.NotificationManager.error(msg);
                    }
                    return;
                }
                
                const tableData = data.data;
                if (!tableData || !tableData.sessions || tableData.sessions.length === 0) {
                    content.innerHTML = `
                        <div class="text-center py-8">
                            <p class="text-slate-600 font-medium">Bu masa için oturum bulunamadı.</p>
                        </div>
                    `;
                    return;
                }
                
                const formatCurrency = window.Utils?.formatCurrency || function(amount) {
                    return new Intl.NumberFormat('tr-TR', { style: 'currency', currency: 'TRY' }).format(amount);
                };
                
                const escapeHtml = window.Utils?.escapeHtml || function(text) {
                    const div = document.createElement('div');
                    div.textContent = text;
                    return div.innerHTML;
                };
                
                const formatDate = function(dateString) {
                    if (!dateString) return '-';
                    const date = new Date(dateString);
                    return date.toLocaleString('tr-TR', {
                        year: 'numeric',
                        month: '2-digit',
                        day: '2-digit',
                        hour: '2-digit',
                        minute: '2-digit'
                    });
                };
                
                let html = `
                    <div class="mb-4 pb-3 border-b border-slate-200">
                        <h2 class="text-xl font-black text-slate-900 mb-1.5">${escapeHtml(tableName)}</h2>
                        <div class="flex gap-3 text-xs text-slate-600">
                            <span><strong>${tableData.total_sessions}</strong> Oturum</span>
                            <span><strong>${tableData.total_orders}</strong> Sipariş</span>
                            <span class="font-black text-slate-900">${formatCurrency(tableData.total_amount)}</span>
                        </div>
                    </div>
                    <div class="space-y-3 max-h-[60vh] overflow-y-auto">
                `;
                
                // Render each session
                tableData.sessions.forEach((session, index) => {
                    const sessionTotal = formatCurrency(session.total_amount);
                    const startTime = formatDate(session.start_time);
                    const endTime = formatDate(session.end_time);
                    const isEnded = session.is_ended || false;
                    const paymentStatus = session.payment_status || 'unpaid';
                    
                    html += `
                        <div class="q-card" style="padding:0;overflow:hidden;">
                            <!-- Session Header -->
                            <div class="q-card q-card--pad" style="background:var(--color-brand-accent);color:#fff;border:none;border-radius:0;">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <h3 class="font-black text-sm mb-1">Oturum ${session.session_number}</h3>
                                        <div class="text-xs text-slate-300">
                                            <div>${startTime} - ${endTime}</div>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <div class="font-black text-base mb-1">${sessionTotal}</div>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-lg text-[10px] font-bold ${
                                            isEnded 
                                                ? 'bg-emerald-500/20 text-emerald-300' 
                                                : 'bg-orange-500/20 text-orange-300'
                                        }">
                                            ${isEnded ? 'Tamamlandı' : 'Devam Ediyor'}
                                        </span>
                                    </div>
                                </div>
                                <div class="mt-1.5 text-[10px] text-slate-400">
                                    ${session.order_count} sipariş
                                </div>
                            </div>
                            
                            <!-- Session Orders -->
                            <div class="p-3 space-y-2">
                    `;
                    
                    session.orders.forEach(order => {
                        const orderStatus = order.status || 'PENDING';
                        const statusBadge = this.getStatusBadge(orderStatus.toLowerCase());
                        const orderDate = formatDate(order.created_at);
                        const orderAmount = formatCurrency(parseFloat(order.total_amount || 0));
                        
                        html += `
                            <div class="bg-white rounded-xl p-3 border border-slate-200 hover:shadow-sm transition-all duration-200">
                                <div class="flex justify-between items-start mb-2">
                                    <div class="flex-1">
                                        <div class="font-black text-xs text-slate-900 mb-1">
                                            #${escapeHtml(order.order_id || '')}
                                        </div>
                                        <div class="text-[10px] text-slate-500">${orderDate}</div>
                                    </div>
                                    <div class="text-right ml-3">
                                        <div class="font-black text-sm text-slate-900 mb-1">${orderAmount}</div>
                                        ${statusBadge}
                                    </div>
                                </div>
                                <div class="flex justify-between items-center pt-2 border-t border-slate-100">
                                    <span class="text-[10px] text-slate-500">
                                        ${escapeHtml(order.created_by || order.customer_name || 'QR Sipariş')}
                                    </span>
                                    <div class="flex gap-2">
                                        <button type="button" onclick="showOrderDetails('${escapeHtml(order.order_id || '')}'); closeTableSessionsModal();" 
                                                class="q-btn q-btn--primary q-btn--sm">
                                            Detay
                                        </button>
                                    </div>
                                </div>
                            </div>
                        `;
                    });
                    
                    html += `
                            </div>
                        </div>
                    `;
                });
                
                html += `
                    </div>
                `;
                
                content.innerHTML = html;
            })
            .catch(error => {
                logger.error('Error loading table sessions:', error);
                content.innerHTML = `
                    <div class="text-center py-8">
                        <p class="text-red-600 font-bold">Masa oturumları yüklenirken bir hata oluştu.</p>
                    </div>
                `;
                if (window.NotificationManager) {
                    window.NotificationManager.error('Bir hata oluştu. Lütfen tekrar deneyin.');
                }
            });
    },
    
    /**
     * Close table sessions modal
     */
    closeTableSessionsModal: function() {
        const modal = document.getElementById('tableSessionsModal');
        if (modal) {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }
    },
    
    /**
     * Toggle zone accordion
     */
    toggleZone: function(zoneId, forceOpen = false) {
        const content = document.getElementById(zoneId + '-content');
        const icon = document.getElementById(zoneId + '-icon');
        
        if (!content || !icon) return;
        
        const isHidden = content.classList.contains('hidden');
        
        if (forceOpen && isHidden) {
            content.classList.remove('hidden');
            icon.classList.add('rotate-180');
        } else if (!forceOpen) {
            if (isHidden) {
                content.classList.remove('hidden');
                icon.classList.add('rotate-180');
            } else {
                content.classList.add('hidden');
                icon.classList.remove('rotate-180');
            }
        }
    },
    
    /**
     * Update order status
     */
    updateOrderStatus: function(orderId, newStatus) {
        fetch(`${this.config.baseUrl}${this.config.apiPrefix || '/api/business'}/qodmin/update-order-status`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ order_id: orderId, status: newStatus })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                this.loadOrders();
                this.closeOrderModal();
            } else {
                const msg = 'Hata: ' + (data.error || 'Sipariş durumu güncellenemedi');
                if (window.NotificationManager) {
                    window.NotificationManager.error(msg);
                }
            }
        })
        .catch(error => {
            logger.error('Error:', error);
            if (window.NotificationManager) {
                window.NotificationManager.error('Bir hata oluştu');
            }
        });
    },
    
    /**
     * Print order via bridge app
     */
    printOrder: function(orderId) {
        if (!orderId) {
            if (window.NotificationManager) {
                window.NotificationManager.error('Sipariş ID bulunamadı.');
            }
            return;
        }
        
        fetch(`${this.config.baseUrl}${this.config.apiPrefix || '/api/business'}/qodmin/order/${orderId}/print`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
            },
            credentials: 'same-origin',
            body: JSON.stringify({ payment_method: 'CASH' })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                if (window.NotificationManager) {
                    window.NotificationManager.success('Yazdırma kuyruğuna eklendi. Köprü uygulaması ile yazdırılacak.');
                }
            } else {
                if (window.NotificationManager) {
                    window.NotificationManager.error('Hata: ' + (data.error || 'Yazdırılamadı'));
                }
            }
        })
        .catch(error => {
            logger.error('Error:', error);
            if (window.NotificationManager) {
                window.NotificationManager.error('Yazdırma sırasında bir hata oluştu.');
            }
        });
    },
    
    /**
     * Download order PDF
     */
    downloadOrderPDF: function(orderId) {
        if (!orderId) {
            if (window.NotificationManager) {
                window.NotificationManager.error('Sipariş ID bulunamadı.');
            }
            return;
        }
        
        const pdfUrl = `${this.config.baseUrl}${this.config.apiPrefix || '/api/business'}/qodmin/order/${orderId}/pdf?payment_method=CASH`;
        window.open(pdfUrl, '_blank');
        
        if (window.NotificationManager) {
            window.NotificationManager.success('PDF açılıyor...');
        }
    },
    
    /**
     * Send order PDF to customer email
     */
    sendOrderPDFEmail: function(orderId, customerEmail) {
        if (!orderId) {
            if (window.NotificationManager) {
                window.NotificationManager.error('Sipariş ID bulunamadı.');
            }
            return;
        }
        
        if (!customerEmail) {
            customerEmail = prompt('Müşteri e-posta adresini girin:');
            if (!customerEmail) {
                return;
            }
        }
        
        if (!customerEmail || !customerEmail.includes('@')) {
            if (window.NotificationManager) {
                window.NotificationManager.error('Geçerli bir e-posta adresi girin.');
            }
            return;
        }
        
        if (window.NotificationManager) {
            window.NotificationManager.info('E-posta gönderiliyor...');
        }
        
        fetch(`${this.config.baseUrl}${this.config.apiPrefix || '/api/business'}/qodmin/order/${orderId}/send-email`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
            },
            credentials: 'same-origin',
            body: JSON.stringify({ email: customerEmail, payment_method: 'CASH' })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                if (window.NotificationManager) {
                    window.NotificationManager.success('E-posta başarıyla gönderildi.');
                }
            } else {
                if (window.NotificationManager) {
                    window.NotificationManager.error('Hata: ' + (data.error || 'E-posta gönderilemedi'));
                }
            }
        })
        .catch(error => {
            logger.error('Error:', error);
            if (window.NotificationManager) {
                window.NotificationManager.error('E-posta gönderimi sırasında bir hata oluştu.');
            }
        });
    },
    
    /**
     * Initialize real-time updates: WebSocket when connected, polling when disconnected
     */
    initRealtimeUpdates: function() {
        if (typeof window.realtimeService !== 'undefined' && window.realtimeService) {
            window.realtimeService.start('orders', (data) => {
                if (data && (data.type === 'ORDER_UPDATE' || data.type === 'order.updated' || 
                    data.type === 'ORDER_CREATED' || data.type === 'order.created')) {
                    this.loadOrders();
                } else if (Array.isArray(data)) {
                    this.state.allOrders = data;
                    if (typeof this.renderOrders === 'function') this.renderOrders();
                    else this.loadOrders();
                } else {
                    this.loadOrders();
                }
            }, { interval: 20000, useCustomLoader: true });
            
            window.realtimeService.onStatusChange((status) => {
                if (status === 'connected' && this._ordersPollInterval) {
                    clearInterval(this._ordersPollInterval);
                    this._ordersPollInterval = null;
                } else if (status !== 'connected' && !this._ordersPollInterval) {
                    this._ordersPollInterval = setInterval(() => this.loadOrders(), 20000);
                }
            });
            
            if (window.realtimeService.connectionStatus !== 'connected') {
                this._ordersPollInterval = setInterval(() => this.loadOrders(), 20000);
            }
            logger.debug('Admin orders: WebSocket + polling fallback');
        } else {
            this._ordersPollInterval = setInterval(() => this.loadOrders(), 20000);
            logger.debug('Admin orders: Polling only');
        }
    }
};

// Global functions for backward compatibility (onclick handlers)
window.refreshOrders = function() { OrdersPage.refreshOrders(); };
window.exportOrders = function() { OrdersPage.exportOrders(); };
window.toggleStatusFilter = function() { OrdersPage.toggleStatusFilter(); };
window.filterByStatus = function(status) { OrdersPage.filterByStatus(status); };
window.toggleDateFilter = function() { OrdersPage.toggleDateFilter(); };
window.filterByDate = function(dateRange) { OrdersPage.filterByDate(dateRange); };
window.applyCustomDateRange = function() { OrdersPage.applyCustomDateRange(); };
window.filterOrders = function() { OrdersPage.filterOrders(); };
window.closeOrderModal = function() { OrdersPage.closeOrderModal(); };
window.closeTableSessionsModal = function() { OrdersPage.closeTableSessionsModal(); };
window.toggleZone = function(zoneId) { OrdersPage.toggleZone(zoneId); };
window.showOrderDetails = function(orderId) { OrdersPage.showOrderDetails(orderId); };
window.printOrder = function(orderId) { OrdersPage.printOrder(orderId); };
window.showTableSessionsModal = function(tableId, tableName) { OrdersPage.showTableSessionsModal(tableId, tableName); };

