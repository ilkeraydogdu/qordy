/**
 * Reports Page JavaScript
 * Handles real-time data updates, filters, and interactions
 */

const ReportsPage = {
    config: {
        baseUrl: '',
        apiPrefix: '/api/business', // Default to business, can be overridden
        currentPeriod: 'this_month',
        startDate: '',
        endDate: '',
        selectedTableId: '',
        autoRefreshInterval: null,
        autoRefreshEnabled: false
    },

    /**
     * Initialize reports page
     */
    init: function(config) {
        this.config = { ...this.config, ...config };
        this.setupEventListeners();
        this.updateExportLinks();
        
        // Optional: Enable auto-refresh every 30 seconds
        // this.enableAutoRefresh(30000);
    },

    /**
     * Setup event listeners
     */
    setupEventListeners: function() {
        // Date range form submission
        const dateRangeForm = document.getElementById('dateRangeForm');
        if (dateRangeForm) {
            dateRangeForm.addEventListener('submit', (e) => {
                e.preventDefault();
                this.loadCustomDateRange();
            });
        }

        // Period buttons
        document.querySelectorAll('.period-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const period = e.target.getAttribute('data-period') || e.target.onclick.toString().match(/'([^']+)'/)?.[1];
                if (period) {
                    this.setPeriod(period);
                }
            });
        });
    },

    /**
     * Set time period filter
     */
    setPeriod: function(period) {
        this.config.currentPeriod = period;
        this.updatePeriodButtons(period);
        this.loadReports(period, null, null, this.config.selectedTableId);
    },

    /**
     * Update period button states - chip style
     */
    updatePeriodButtons: function(activePeriod) {
        document.querySelectorAll('.period-btn').forEach(btn => {
            const period = btn.getAttribute('data-period');
            if (period === activePeriod) {
                btn.classList.add('active');
            } else {
                btn.classList.remove('active');
            }
        });
    },

    /**
     * Set table filter
     */
    setTableFilter: function(tableId) {
        this.config.selectedTableId = tableId || '';
        const period = this.config.currentPeriod;
        const startDate = document.getElementById('startDate')?.value || this.config.startDate;
        const endDate = document.getElementById('endDate')?.value || this.config.endDate;
        this.loadReports(period, startDate, endDate, tableId);
    },

    /**
     * Load custom date range
     */
    loadCustomDateRange: function() {
        const startDate = document.getElementById('startDate')?.value;
        const endDate = document.getElementById('endDate')?.value;
        
        if (!startDate || !endDate) {
            if (window.NotificationManager) {
                window.NotificationManager.warning('Lütfen başlangıç ve bitiş tarihlerini seçin.');
            }
            return;
        }

        this.loadReports('custom', startDate, endDate, this.config.selectedTableId);
    },

    /**
     * Load reports data
     */
    loadReports: function(period, startDate, endDate, tableId) {
        this.showLoading();

        const params = new URLSearchParams();
        if (period && period !== 'custom') {
            params.append('period', period);
        }
        if (startDate) {
            params.append('start_date', startDate);
        }
        if (endDate) {
            params.append('end_date', endDate);
        }
        if (tableId) {
            params.append('table_id', tableId);
        }

        // Super-admin scope: propagate the currently-selected business so the
        // API keeps resolving data for the intended tenant, not the empty one.
        const urlBusinessId = new URLSearchParams(window.location.search).get('business_id');
        if (urlBusinessId) {
            params.append('business_id', urlBusinessId);
        }

        // Use configured API prefix or determine from URL
        const apiPrefix = this.config.apiPrefix || (window.location.pathname.includes('/qodmin/') ? '/api/qodmin' : '/api/business');

        fetch(`${this.config.baseUrl}${apiPrefix}/reports-data?${params.toString()}`, {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
            },
            credentials: 'same-origin'
        })
        .then(response => response.json())
        .then(data => {
            this.hideLoading();
            if (data.success && data.data) {
                this.updatePage(data.data);
                this.updateExportLinks(data.data.date_range);
            } else {
                console.error('Error loading reports:', data.error || 'Unknown error');
                if (window.NotificationManager) {
                    window.NotificationManager.error('Rapor verileri yüklenirken bir hata oluştu.');
                }
            }
        })
        .catch(error => {
            this.hideLoading();
            console.error('Error:', error);
            if (window.NotificationManager) {
                window.NotificationManager.error('Rapor verileri yüklenirken bir hata oluştu.');
            }
        });
    },

    /**
     * Update page with new data
     */
    updatePage: function(data) {
        // Update sales report
        if (data.sales_report) {
            this.updateElement('totalOrders', data.sales_report.total_orders || 0);
            this.updateElement('totalRevenue', this.formatCurrency(data.sales_report.total_revenue || 0));
            this.updateElement('avgOrderValue', this.formatCurrency(data.sales_report.avg_order_value || 0));
            this.updateElement('completedOrders', data.sales_report.completed_orders || 0);
        }

        // Update financial summary
        if (data.profit_loss_report) {
            this.updateElement('totalRevenueFinancial', this.formatCurrency(data.profit_loss_report.total_revenue || 0));
            this.updateElement('totalExpenses', this.formatCurrency(data.profit_loss_report.total_expenses || 0));
            this.updateElement('netProfit', this.formatCurrency(data.profit_loss_report.net_profit || 0));
            this.updateElement('profitMargin', (data.profit_loss_report.profit_margin || 0).toFixed(1) + '%');
        }

        // Update customer report
        if (data.customer_report) {
            this.updateElement('uniqueCustomers', data.customer_report.unique_customers || 0);
            this.updateElement('totalVisits', data.customer_report.total_visits || 0);
            this.updateElement('avgSpent', this.formatCurrency(data.customer_report.avg_spent || 0));
        }

        // Update employee performance table
        if (data.employee_performance) {
            this.updateEmployeeTable(data.employee_performance);
        }

        // Update tables report
        if (data.tables_report) {
            this.updateTablesReport(data.tables_report, data.date_range);
            
            // Update table filter dropdown with only tables that have orders
            this.updateTableFilterDropdown(data.tables_report);
            
            // If single table is selected, show order history
            if (this.config.selectedTableId && data.tables_report.length === 1 && data.tables_report[0].orders) {
                this.updateTableOrdersHistory(data.tables_report[0].orders);
            }
        }

        // Update date range in form
        if (data.date_range) {
            const startDateInput = document.getElementById('startDate');
            const endDateInput = document.getElementById('endDate');
            if (startDateInput) startDateInput.value = data.date_range.start;
            if (endDateInput) endDateInput.value = data.date_range.end;
            this.config.startDate = data.date_range.start;
            this.config.endDate = data.date_range.end;
        }
    },

    /**
     * Update element text content
     */
    updateElement: function(id, value) {
        const element = document.getElementById(id);
        if (element) {
            element.textContent = value;
        }
    },

    /**
     * Update employee performance - card-based design
     */
    updateEmployeeTable: function(employees) {
        const container = document.getElementById('employeeTableBody');
        if (!container) return;

        if (!employees || employees.length === 0) {
            container.innerHTML = `
                <div class="text-center py-8">
                    <svg class="w-10 h-10 text-slate-200 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                    <p class="text-xs font-medium text-slate-400">Bu tarih aralığında personel performans verisi bulunamadı.</p>
                </div>`;
            return;
        }

        const maxSales = Math.max(...employees.map(e => parseFloat(e.total_sales || 0)), 1);
        const totalSalesAll = employees.reduce((sum, e) => sum + parseFloat(e.total_sales || 0), 0);
        const rankColors = ['bg-amber-50 text-amber-700 border-amber-200', 'bg-slate-50 text-slate-600 border-slate-200', 'bg-orange-50 text-orange-700 border-orange-200'];

        container.innerHTML = employees.map((emp, index) => {
            const salesPercent = maxSales > 0 ? Math.round((parseFloat(emp.total_sales || 0)) / maxSales * 100) : 0;
            const sharePercent = totalSalesAll > 0 ? Math.round(parseFloat(emp.total_sales || 0) / totalSalesAll * 100 * 10) / 10 : 0;
            const avgOrderValue = (emp.orders_handled || 0) > 0 ? (parseFloat(emp.total_sales || 0)) / (emp.orders_handled || 1) : 0;
            const rankColor = rankColors[index] || rankColors[1];

            return `
                <div class="emp-row rounded-xl border border-slate-200/60 p-4 transition-all">
                    <div class="flex items-start gap-3">
                        <div class="w-8 h-8 rounded-lg ${rankColor} border flex items-center justify-center flex-shrink-0">
                            <span class="text-xs font-bold">${index + 1}</span>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center justify-between mb-2">
                                <div>
                                    <h3 class="text-sm font-semibold text-slate-900">${this.escapeHtml(emp.name || 'Bilinmeyen')}</h3>
                                    <p class="text-[10px] text-slate-400 mt-0.5">
                                        ${emp.orders_handled || 0} sipariş &middot; 
                                        ort. ${this.formatCurrency(avgOrderValue)} &middot;
                                        %${sharePercent} pay
                                    </p>
                                </div>
                                <div class="text-right">
                                    <div class="text-sm font-bold text-slate-900">${this.formatCurrency(emp.total_sales || 0)}</div>
                                </div>
                            </div>
                            <div class="w-full bg-slate-100 rounded-full h-1.5 overflow-hidden">
                                <div class="emp-bar reports-emp-bar h-1.5 rounded-full" style="width: ${salesPercent}%"></div>
                            </div>
                            <div class="flex items-center gap-4 mt-2">
                                <span class="text-[10px] font-medium text-slate-500">${emp.orders_handled || 0} sipariş</span>
                                <span class="text-[10px] font-medium text-slate-500">ort. ${this.formatCurrency(avgOrderValue)}</span>
                                <span class="text-[10px] font-medium text-slate-500">%${sharePercent} pay</span>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        }).join('');
    },

    /**
     * Update tables report
     */
    updateTablesReport: function(tables, dateRange) {
        const tablesReportDiv = document.getElementById('tablesReport');
        if (!tablesReportDiv) return;

        // Filter: Only show tables with orders (total_orders > 0)
        // Exception: If a table is selected, show it even if no orders
        const filteredTables = tables.filter(table => {
            const hasOrders = (table.total_orders || 0) > 0;
            const isSelected = this.config.selectedTableId && (table.table_id === this.config.selectedTableId);
            return hasOrders || isSelected;
        });

        if (!filteredTables || filteredTables.length === 0) {
            tablesReportDiv.style.display = 'none';
            return;
        }

        tablesReportDiv.style.display = 'block';
        const isSingleTable = this.config.selectedTableId && filteredTables.length === 1;
        
        const title = isSingleTable ? 'Masa Detay Raporu' : 'Masa Bazlı Raporlar';
        const titleElement = tablesReportDiv.querySelector('h2');
        if (titleElement) {
            titleElement.textContent = title;
        }
        
        // Update total tables count
        const totalTablesCount = tablesReportDiv.querySelector('#totalTablesCount');
        if (totalTablesCount && !isSingleTable) {
            totalTablesCount.textContent = filteredTables.length;
        }

        // Update tree view container
        const treeViewContainer = tablesReportDiv.querySelector('#treeViewContainer');
        if (treeViewContainer && !isSingleTable) {
            // Group tables by zone
            const tablesByZone = {};
            filteredTables.forEach(table => {
                const zoneName = table.zone || 'Diğer';
                if (!tablesByZone[zoneName]) {
                    tablesByZone[zoneName] = [];
                }
                tablesByZone[zoneName].push(table);
            });
            
            // Generate tree view HTML
            let treeViewHTML = '';
            Object.keys(tablesByZone).sort().forEach(zoneName => {
                const zoneTables = tablesByZone[zoneName];
                const zoneId = 'zone_' + zoneName.replace(/\s+/g, '_').toLowerCase();
                const zoneRevenue = zoneTables.reduce((sum, t) => sum + (parseFloat(t.total_revenue) || 0), 0);
                const zoneOrders = zoneTables.reduce((sum, t) => sum + (parseInt(t.total_orders) || 0), 0);
                const activeTablesCount = zoneTables.filter(t => (t.total_orders || 0) > 0).length;
                
                treeViewHTML += `
                    <div class="border border-slate-200 rounded-xl overflow-hidden zone-section" data-zone="${zoneName}">
                        <div class="bg-gradient-to-r from-orange-50 to-amber-50 p-4 cursor-pointer hover:from-orange-100 hover:to-amber-100 transition-all" onclick="toggleZone('${zoneId}')">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <svg class="w-5 h-5 text-orange-500 transform transition-transform zone-arrow" id="arrow_${zoneId}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                    </svg>
                                    <div>
                                        <h3 class="text-lg font-black text-slate-900">${this.escapeHtml(zoneName)}</h3>
                                        <p class="text-xs text-slate-600 font-medium">${zoneTables.length} masa • ${activeTablesCount} aktif</p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-4">
                                    <div class="text-right">
                                        <div class="text-sm text-slate-600 font-bold">Sipariş</div>
                                        <div class="text-lg font-black text-slate-900">${zoneOrders}</div>
                                    </div>
                                    <div class="text-right">
                                        <div class="text-sm text-slate-600 font-bold">Gelir</div>
                                        <div class="text-lg font-black text-orange-600">${this.formatCurrency(zoneRevenue)}</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div id="${zoneId}" class="zone-content">
                            <div class="p-4 bg-slate-50">
                                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-3">
                                    ${zoneTables.map(table => {
                                        const hasOrders = (table.total_orders || 0) > 0;
                                        return `
                                            <div class="bg-white p-3 rounded-lg border border-slate-200 hover:border-orange-300 hover:shadow-md transition-all table-card ${!hasOrders ? 'inactive-table' : ''}" 
                                                 data-orders="${table.total_orders || 0}"
                                                 data-table-name="${(table.table_name || '').toLowerCase()}"
                                                 data-zone="${zoneName.toLowerCase()}"
                                                 data-revenue="${table.total_revenue || 0}">
                                                <div class="flex items-center justify-between mb-2">
                                                    <div class="flex items-center gap-2">
                                                        <span class="w-2 h-2 ${hasOrders ? 'bg-green-500' : 'bg-slate-300'} rounded-full"></span>
                                                        <h4 class="font-black text-slate-900">${this.escapeHtml(table.table_name || 'Bilinmeyen')}</h4>
                                                    </div>
                                                </div>
                                                <div class="space-y-1 text-xs">
                                                    <div class="flex justify-between">
                                                        <span class="text-slate-600 font-medium">Sipariş:</span>
                                                        <span class="font-bold text-slate-900">${table.total_orders || 0}</span>
                                                    </div>
                                                    <div class="flex justify-between">
                                                        <span class="text-slate-600 font-medium">Gelir:</span>
                                                        <span class="font-bold text-slate-900">${this.formatCurrency(table.total_revenue || 0)}</span>
                                                    </div>
                                                    <div class="flex justify-between">
                                                        <span class="text-slate-600 font-medium">Ortalama:</span>
                                                        <span class="font-bold text-slate-900">${this.formatCurrency(table.avg_order_value || 0)}</span>
                                                    </div>
                                                    ${table.active_days ? `
                                                    <div class="flex justify-between">
                                                        <span class="text-slate-600 font-medium">Aktif Gün:</span>
                                                        <span class="font-bold text-slate-900">${table.active_days}</span>
                                                    </div>
                                                    ` : ''}
                                                </div>
                                            </div>
                                        `;
                                    }).join('')}
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            });
            treeViewContainer.innerHTML = treeViewHTML;
            
            // Expand all zones
            setTimeout(() => {
                document.querySelectorAll('.zone-content').forEach(zone => {
                    zone.classList.remove('hidden');
                });
                document.querySelectorAll('.zone-arrow').forEach(arrow => {
                    arrow.style.transform = 'rotate(180deg)';
                });
            }, 50);
        }
        
        // Update table view
        const tbody = tablesReportDiv.querySelector('#tablesTableBody');
        if (tbody) {
            tbody.innerHTML = filteredTables.map(table => {
                const actionsCell = isSingleTable ? 
                    `<td class="p-2 sm:p-3 md:p-4 text-xs sm:text-sm md:text-base">
                        <button type="button" onclick="toggleTableDetails('${table.table_id || ''}')" class="q-btn q-btn--primary q-btn--sm">
                            Detayları Gör
                        </button>
                    </td>` : '';
                
                const tableName = this.escapeHtml(table.table_name || 'Bilinmeyen');
                const zone = this.escapeHtml(table.zone || '-');
                const totalOrders = table.total_orders || 0;
                const totalRevenue = table.total_revenue || 0;
                const avgOrderValue = table.avg_order_value || 0;
                const activeDays = table.active_days || 0;
                
                return `
                    <tr class="table-row hover:bg-slate-50 transition-colors"
                        data-table-name="${tableName.toLowerCase()}"
                        data-zone="${zone.toLowerCase()}"
                        data-orders="${totalOrders}"
                        data-revenue="${totalRevenue}"
                        data-active-days="${activeDays}">
                        <td class="p-2 sm:p-3 md:p-4 text-xs sm:text-sm md:text-base font-black text-slate-900">${tableName}</td>
                        <td class="p-2 sm:p-3 md:p-4 text-xs sm:text-sm md:text-base font-bold text-slate-700">${zone}</td>
                        <td class="p-2 sm:p-3 md:p-4 text-xs sm:text-sm md:text-base font-bold text-slate-700">${totalOrders}</td>
                        <td class="p-2 sm:p-3 md:p-4 text-xs sm:text-sm md:text-base font-black text-slate-900">${this.formatCurrency(totalRevenue)}</td>
                        <td class="p-2 sm:p-3 md:p-4 text-xs sm:text-sm md:text-base font-bold text-slate-600">${this.formatCurrency(avgOrderValue)}</td>
                        <td class="p-2 sm:p-3 md:p-4 text-xs sm:text-sm md:text-base font-bold text-slate-600">${activeDays}</td>
                        ${actionsCell}
                    </tr>
                `;
            }).join('');
        }
        
        // Update summary stats
        if (!isSingleTable && filteredTables.length > 1) {
            const totalRevenue = filteredTables.reduce((sum, t) => sum + (parseFloat(t.total_revenue) || 0), 0);
            const totalOrders = filteredTables.reduce((sum, t) => sum + (parseInt(t.total_orders) || 0), 0);
            const avgRevenue = filteredTables.length > 0 ? totalRevenue / filteredTables.length : 0;
            const activeCount = filteredTables.filter(t => (t.total_orders || 0) > 0).length;
            
            this.updateElement('tablesTotalRevenue', this.formatCurrency(totalRevenue));
            this.updateElement('tablesTotalOrders', totalOrders);
            this.updateElement('tablesAvgRevenue', this.formatCurrency(avgRevenue));
            this.updateElement('tablesActiveCount', activeCount);
        }
        
        // Re-initialize tables manager after updating table data
        if (!isSingleTable && typeof TablesManager !== 'undefined') {
            setTimeout(() => {
                TablesManager.collectTables();
                TablesManager.applyFilters();
            }, 100);
        }
        
        // Update table orders history if single table is selected
        if (isSingleTable && filteredTables[0] && filteredTables[0].orders) {
            this.updateTableOrdersHistory(filteredTables[0].orders);
        } else {
            let historyDiv = document.getElementById('tableOrdersHistory');
            if (historyDiv) {
                historyDiv.style.display = 'none';
            }
        }
    },

    /**
     * Update table filter dropdown
     */
    updateTableFilterDropdown: function(tables) {
        const tableFilter = document.getElementById('tableFilter');
        if (!tableFilter) return;
        
        // Filter: Only show tables with orders
        const tablesWithOrders = tables.filter(t => (t.total_orders || 0) > 0);
        
        // Get current selected value
        const currentValue = tableFilter.value;
        
        // Clear existing options except "Tüm Masalar"
        tableFilter.innerHTML = '<option value="">Tüm Masalar</option>';
        
        // Add tables with orders
        tablesWithOrders.forEach(table => {
            const option = document.createElement('option');
            option.value = table.table_id || '';
            option.textContent = (table.table_name || 'Bilinmeyen') + ' - ' + (table.zone || '');
            if (currentValue === (table.table_id || '')) {
                option.selected = true;
            }
            tableFilter.appendChild(option);
        });
    },

    /**
     * Update table orders history
     */
    updateTableOrdersHistory: function(orders) {
        let historyDiv = document.getElementById('tableOrdersHistory');
        if (!historyDiv) {
            // Create the history div if it doesn't exist
            const tablesReportDiv = document.getElementById('tablesReport');
            if (tablesReportDiv && tablesReportDiv.parentNode) {
                const newDiv = document.createElement('div');
                newDiv.id = 'tableOrdersHistory';
                newDiv.className = 'bg-white p-3 sm:p-4 md:p-6 lg:p-8 rounded-lg sm:rounded-xl md:rounded-2xl lg:rounded-3xl border border-slate-50 shadow-soft';
                tablesReportDiv.parentNode.insertBefore(newDiv, tablesReportDiv.nextSibling);
            } else {
                return;
            }
            historyDiv = document.getElementById('tableOrdersHistory');
        }
        
        if (!historyDiv) return;
        
        historyDiv.style.display = 'block';
        
        if (!orders || orders.length === 0) {
            historyDiv.innerHTML = `
                <h2 class="text-base sm:text-lg md:text-xl lg:text-2xl font-black text-slate-900 mb-3 sm:mb-4 md:mb-6">
                    Masa Sipariş Geçmişi
                </h2>
                <div class="text-center py-8 text-slate-400 font-bold text-sm sm:text-base">
                    Bu tarih aralığında bu masaya ait sipariş bulunamadı.
                </div>
            `;
            return;
        }
        
        historyDiv.innerHTML = `
            <h2 class="text-base sm:text-lg md:text-xl lg:text-2xl font-black text-slate-900 mb-3 sm:mb-4 md:mb-6">
                Masa Sipariş Geçmişi
            </h2>
            <div class="space-y-4">
                ${orders.map(order => {
                    const orderDate = order.created_at ? new Date(order.created_at).toLocaleString('tr-TR', {
                        day: '2-digit',
                        month: '2-digit',
                        year: 'numeric',
                        hour: '2-digit',
                        minute: '2-digit'
                    }) : '';
                    
                    const itemsHtml = order.items && order.items.length > 0 ? `
                        <div class="mt-3 pt-3 border-t border-slate-200">
                            <h4 class="text-xs sm:text-sm font-black text-slate-700 mb-2">Sipariş Detayları:</h4>
                            <div class="space-y-2">
                                ${(window.Utils && window.Utils.groupOrderItemsForDisplay ? window.Utils.groupOrderItemsForDisplay(order.items) : order.items).map(item => `
                                    <div class="flex justify-between items-center text-xs sm:text-sm">
                                        <div class="flex items-center gap-2">
                                            <span class="font-bold text-slate-900">${this.escapeHtml(item.menu_item_name || 'Bilinmeyen')}</span>
                                            <span class="text-slate-500">x${item.quantity || 1}</span>
                                            ${item.category_name ? `<span class="px-2 py-0.5 bg-slate-100 text-slate-600 rounded text-[9px]">${this.escapeHtml(item.category_name)}</span>` : ''}
                                        </div>
                                        <div class="font-black text-slate-900">
                                            ${this.formatCurrency((item.price || 0) * (item.quantity || 1))}
                                        </div>
                                    </div>
                                `).join('')}
                            </div>
                        </div>
                    ` : '';
                    
                    const noteHtml = order.customer_note ? `
                        <div class="mt-3 pt-3 border-t border-slate-200">
                            <div class="text-xs sm:text-sm text-slate-600">
                                <span class="font-bold">Not:</span> ${this.escapeHtml(order.customer_note)}
                            </div>
                        </div>
                    ` : '';
                    
                    return `
                        <div class="border border-slate-200 rounded-lg p-4 hover:bg-slate-50 transition-colors">
                            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-3">
                                <div>
                                    <div class="flex items-center gap-2 mb-1">
                                        <span class="text-xs sm:text-sm font-black text-slate-900">Sipariş #${this.escapeHtml(order.order_id || '')}</span>
                                        <span class="px-2 py-1 bg-slate-100 text-slate-700 rounded text-[9px] sm:text-[10px] font-bold uppercase">
                                            ${this.escapeHtml(({ PENDING: 'Beklemede', PREPARING: 'Hazırlanıyor', READY: 'Hazır', SERVED: 'Tamamlandı', CANCELLED: 'İptal', REFUNDED: 'İade' }[order.status] || order.status || 'Bilinmiyor'))}
                                        </span>
                                        ${order.is_paid ? '<span class="px-2 py-1 bg-emerald-100 text-emerald-700 rounded text-[9px] sm:text-[10px] font-bold">Ödendi</span>' : ''}
                                    </div>
                                    <div class="text-xs text-slate-500">
                                        ${orderDate}
                                    </div>
                                </div>
                                <div class="text-right">
                                    <div class="text-lg sm:text-xl font-black text-slate-900">
                                        ${this.formatCurrency(order.total_amount || 0)}
                                    </div>
                                    <div class="text-xs text-slate-500">
                                        ${order.item_count || 0} ürün
                                    </div>
                                </div>
                            </div>
                            ${itemsHtml}
                            ${noteHtml}
                        </div>
                    `;
                }).join('')}
            </div>
        `;
    },

    /**
     * Update export links with current date range
     */
    updateExportLinks: function(dateRange) {
        const dateRangeToUse = dateRange || {
            start: this.config.startDate,
            end: this.config.endDate
        };

        document.querySelectorAll('#export-menu a').forEach(link => {
            const url = new URL(link.href);
            url.searchParams.set('start_date', dateRangeToUse.start);
            url.searchParams.set('end_date', dateRangeToUse.end);
            link.href = url.toString();
        });
    },

    /**
     * Show loading indicator
     */
    showLoading: function() {
        const loadingIndicator = document.getElementById('loadingIndicator');
        if (loadingIndicator) {
            loadingIndicator.classList.remove('hidden');
        }
    },

    /**
     * Hide loading indicator
     */
    hideLoading: function() {
        const loadingIndicator = document.getElementById('loadingIndicator');
        if (loadingIndicator) {
            loadingIndicator.classList.add('hidden');
        }
    },

    /**
     * Format currency
     */
    formatCurrency: function(amount) {
        // Use Utils.formatCurrency if available
        if (window.Utils && window.Utils.formatCurrency) {
            return window.Utils.formatCurrency(amount);
        }
        // Fallback implementation
        return new Intl.NumberFormat('tr-TR', {
            style: 'currency',
            currency: 'TRY',
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }).format(amount);
    },

    /**
     * Escape HTML
     */
    escapeHtml: function(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    },

    /**
     * Enable auto-refresh
     */
    enableAutoRefresh: function(interval = 30000) {
        if (this.config.autoRefreshInterval) {
            clearInterval(this.config.autoRefreshInterval);
        }

        this.config.autoRefreshEnabled = true;
        this.config.autoRefreshInterval = setInterval(() => {
            if (this.config.autoRefreshEnabled) {
                const period = this.config.currentPeriod;
                const startDate = document.getElementById('startDate')?.value || this.config.startDate;
                const endDate = document.getElementById('endDate')?.value || this.config.endDate;
                this.loadReports(period, startDate, endDate, this.config.selectedTableId);
            }
        }, interval);
    },

    /**
     * Disable auto-refresh
     */
    disableAutoRefresh: function() {
        if (this.config.autoRefreshInterval) {
            clearInterval(this.config.autoRefreshInterval);
            this.config.autoRefreshInterval = null;
        }
        this.config.autoRefreshEnabled = false;
    }
};

// Global functions for onclick handlers
function setPeriod(period) {
    if (typeof ReportsPage !== 'undefined') {
        ReportsPage.setPeriod(period);
    }
}

function setTableFilter(tableId) {
    if (typeof ReportsPage !== 'undefined') {
        ReportsPage.setTableFilter(tableId);
    }
}

// Table filtering, sorting and pagination
let TablesManager = {
    allTables: [],
    filteredTables: [],
    currentPage: 1,
    itemsPerPage: 20,
    currentSort: 'revenue_desc',
    
    init: function() {
        // Store all tables from DOM
        this.collectTables();
        this.applyFilters();
    },
    
    collectTables: function() {
        const rows = document.querySelectorAll('#tablesTableBody .table-row');
        this.allTables = Array.from(rows).map(row => ({
            element: row,
            name: row.dataset.tableName || '',
            zone: row.dataset.zone || '',
            orders: parseInt(row.dataset.orders || 0),
            revenue: parseFloat(row.dataset.revenue || 0),
            activeDays: parseInt(row.dataset.activeDays || 0)
        }));
        this.filteredTables = [...this.allTables];
    },
    
    filterTables: function(searchTerm) {
        if (!searchTerm || searchTerm.trim() === '') {
            this.filteredTables = [...this.allTables];
        } else {
            const term = searchTerm.toLowerCase().trim();
            this.filteredTables = this.allTables.filter(table => 
                table.name.includes(term) || table.zone.includes(term)
            );
        }
        this.currentPage = 1;
        this.applyFilters();
    },
    
    sortTables: function(sortType) {
        this.currentSort = sortType;
        this.filteredTables.sort((a, b) => {
            switch(sortType) {
                case 'revenue_desc':
                    return b.revenue - a.revenue;
                case 'revenue_asc':
                    return a.revenue - b.revenue;
                case 'orders_desc':
                    return b.orders - a.orders;
                case 'orders_asc':
                    return a.orders - b.orders;
                case 'name_asc':
                    return a.name.localeCompare(b.name, 'tr');
                case 'name_desc':
                    return b.name.localeCompare(a.name, 'tr');
                case 'zone_asc':
                    return a.zone.localeCompare(b.zone, 'tr');
                case 'zone_desc':
                    return b.zone.localeCompare(a.zone, 'tr');
                case 'active_days_desc':
                    return b.activeDays - a.activeDays;
                case 'active_days_asc':
                    return a.activeDays - b.activeDays;
                default:
                    return 0;
            }
        });
        this.currentPage = 1;
        this.applyFilters();
    },
    
    changeItemsPerPage: function(items) {
        if (items === 'all') {
            this.itemsPerPage = this.filteredTables.length;
        } else {
            this.itemsPerPage = parseInt(items) || 20;
        }
        this.currentPage = 1;
        this.applyFilters();
    },
    
    applyFilters: function() {
        // Hide all rows first
        this.allTables.forEach(table => {
            table.element.style.display = 'none';
        });
        
        // Calculate pagination
        const totalPages = Math.ceil(this.filteredTables.length / this.itemsPerPage);
        const startIndex = (this.currentPage - 1) * this.itemsPerPage;
        const endIndex = startIndex + this.itemsPerPage;
        const currentPageTables = this.filteredTables.slice(startIndex, endIndex);
        
        // Show filtered and sorted rows
        currentPageTables.forEach(table => {
            table.element.style.display = '';
        });
        
        // Update pagination UI
        this.updatePagination(totalPages, startIndex + 1, Math.min(endIndex, this.filteredTables.length));
        
        // Update summary stats
        this.updateSummaryStats();
    },
    
    updatePagination: function(totalPages, from, to) {
        const currentPageEl = document.getElementById('currentPage');
        const totalPagesEl = document.getElementById('totalPages');
        const showingFromEl = document.getElementById('showingFrom');
        const showingToEl = document.getElementById('showingTo');
        const totalFilteredEl = document.getElementById('totalFiltered');
        const totalTablesCountEl = document.getElementById('totalTablesCount');
        
        if (currentPageEl) currentPageEl.textContent = this.currentPage;
        if (totalPagesEl) totalPagesEl.textContent = totalPages;
        if (showingFromEl) showingFromEl.textContent = from;
        if (showingToEl) showingToEl.textContent = to;
        if (totalFilteredEl) totalFilteredEl.textContent = this.filteredTables.length;
        if (totalTablesCountEl) totalTablesCountEl.textContent = this.filteredTables.length;
        
        // Update pagination buttons
        const firstBtn = document.getElementById('firstPageBtn');
        const prevBtn = document.getElementById('prevPageBtn');
        const nextBtn = document.getElementById('nextPageBtn');
        const lastBtn = document.getElementById('lastPageBtn');
        
        if (firstBtn) {
            firstBtn.disabled = this.currentPage === 1;
        }
        if (prevBtn) {
            prevBtn.disabled = this.currentPage === 1;
        }
        if (nextBtn) {
            nextBtn.disabled = this.currentPage >= totalPages;
        }
        if (lastBtn) {
            lastBtn.disabled = this.currentPage >= totalPages;
        }
    },
    
    updateSummaryStats: function() {
        const totalRevenue = this.filteredTables.reduce((sum, t) => sum + t.revenue, 0);
        const totalOrders = this.filteredTables.reduce((sum, t) => sum + t.orders, 0);
        const avgRevenue = this.filteredTables.length > 0 ? totalRevenue / this.filteredTables.length : 0;
        const activeCount = this.filteredTables.filter(t => t.orders > 0).length;
        
        const totalRevenueEl = document.getElementById('tablesTotalRevenue');
        const totalOrdersEl = document.getElementById('tablesTotalOrders');
        const avgRevenueEl = document.getElementById('tablesAvgRevenue');
        const activeCountEl = document.getElementById('tablesActiveCount');
        
        if (totalRevenueEl && ReportsPage) {
            totalRevenueEl.textContent = ReportsPage.formatCurrency(totalRevenue);
        }
        if (totalOrdersEl) {
            totalOrdersEl.textContent = totalOrders;
        }
        if (avgRevenueEl && ReportsPage) {
            avgRevenueEl.textContent = ReportsPage.formatCurrency(avgRevenue);
        }
        if (activeCountEl) {
            activeCountEl.textContent = activeCount;
        }
    },
    
    goToPage: function(page) {
        const totalPages = Math.ceil(this.filteredTables.length / this.itemsPerPage);
        if (page >= 1 && page <= totalPages) {
            this.currentPage = page;
            this.applyFilters();
        }
    },
    
    goToNextPage: function() {
        const totalPages = Math.ceil(this.filteredTables.length / this.itemsPerPage);
        if (this.currentPage < totalPages) {
            this.currentPage++;
            this.applyFilters();
        }
    },
    
    goToPreviousPage: function() {
        if (this.currentPage > 1) {
            this.currentPage--;
            this.applyFilters();
        }
    },
    
    goToLastPage: function() {
        const totalPages = Math.ceil(this.filteredTables.length / this.itemsPerPage);
        this.currentPage = totalPages;
        this.applyFilters();
    }
};

// Global functions for table management
function filterTables() {
    const searchInput = document.getElementById('tableSearchInput');
    if (!searchInput) return;
    
    const term = (searchInput.value || '').toLowerCase().trim();
    
    // Filter tree view (visible by default)
    const treeViewContainer = document.getElementById('treeViewContainer');
    if (treeViewContainer) {
        const zoneSections = treeViewContainer.querySelectorAll('.zone-section');
        zoneSections.forEach(section => {
            const zoneCards = section.querySelectorAll('.table-card, .table-mini-card');
            let visibleCount = 0;
            
            zoneCards.forEach(card => {
                const tableName = (card.dataset.tableName || card.querySelector('.truncate, [class*="font-semibold"]')?.textContent || '').toLowerCase();
                const zoneName = (card.dataset.zone || '').toLowerCase();
                
                if (!term || tableName.includes(term) || zoneName.includes(term)) {
                    card.style.display = '';
                    visibleCount++;
                } else {
                    card.style.display = 'none';
                }
            });
            
            // Hide entire zone section if no cards match
            section.style.display = visibleCount === 0 && term ? 'none' : '';
        });
    }
    
    // Also filter table view (hidden by default but used when toggled)
    if (TablesManager) {
        TablesManager.filterTables(searchInput.value);
    }
}

function sortTables() {
    const sortSelect = document.getElementById('tableSortSelect');
    if (!sortSelect) return;
    
    const sortType = sortSelect.value;
    
    // Sort tree view cards within each zone
    const treeViewContainer = document.getElementById('treeViewContainer');
    if (treeViewContainer) {
        const zoneSections = treeViewContainer.querySelectorAll('.zone-section');
        zoneSections.forEach(section => {
            const grid = section.querySelector('.grid');
            if (!grid) return;
            const cards = Array.from(grid.querySelectorAll('.table-card, .table-mini-card'));
            
            cards.sort((a, b) => {
                const aRev = parseFloat(a.dataset.revenue || 0);
                const bRev = parseFloat(b.dataset.revenue || 0);
                const aOrd = parseInt(a.dataset.orders || 0);
                const bOrd = parseInt(b.dataset.orders || 0);
                const aName = (a.dataset.tableName || '');
                const bName = (b.dataset.tableName || '');
                
                switch (sortType) {
                    case 'revenue_desc': return bRev - aRev;
                    case 'revenue_asc': return aRev - bRev;
                    case 'orders_desc': return bOrd - aOrd;
                    case 'orders_asc': return aOrd - bOrd;
                    case 'name_asc': return aName.localeCompare(bName, 'tr');
                    default: return 0;
                }
            });
            
            cards.forEach(card => grid.appendChild(card));
        });
    }
    
    // Also sort table view
    if (TablesManager) {
        TablesManager.sortTables(sortType);
    }
}

function sortTablesByColumn(column) {
    if (!TablesManager) return;
    
    let sortType = TablesManager.currentSort;
    
    // Toggle between asc/desc for the clicked column
    switch(column) {
        case 'name':
            sortType = sortType === 'name_asc' ? 'name_desc' : 'name_asc';
            break;
        case 'zone':
            sortType = sortType === 'zone_asc' ? 'zone_desc' : 'zone_asc';
            break;
        case 'orders':
            sortType = sortType === 'orders_desc' ? 'orders_asc' : 'orders_desc';
            break;
        case 'revenue':
            sortType = sortType === 'revenue_desc' ? 'revenue_asc' : 'revenue_desc';
            break;
        case 'active_days':
            sortType = sortType === 'active_days_desc' ? 'active_days_asc' : 'active_days_desc';
            break;
    }
    
    const sortSelect = document.getElementById('tableSortSelect');
    if (sortSelect) {
        sortSelect.value = sortType;
    }
    
    TablesManager.sortTables(sortType);
}

function changeItemsPerPage() {
    const itemsSelect = document.getElementById('itemsPerPageSelect');
    if (itemsSelect && TablesManager) {
        TablesManager.changeItemsPerPage(itemsSelect.value);
    }
}

function goToPage(page) {
    if (TablesManager) {
        TablesManager.goToPage(page);
    }
}

function goToNextPage() {
    if (TablesManager) {
        TablesManager.goToNextPage();
    }
}

function goToPreviousPage() {
    if (TablesManager) {
        TablesManager.goToPreviousPage();
    }
}

function goToLastPage() {
    if (TablesManager) {
        TablesManager.goToLastPage();
    }
}

// Initialize tables manager when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    // Wait a bit for the page to fully render
    setTimeout(function() {
        const tbody = document.getElementById('tablesTableBody');
        if (tbody) {
            const firstRow = tbody.querySelector('.table-row');
            if (firstRow && typeof TablesManager !== 'undefined') {
                // Check if already initialized or needs initialization
                if (TablesManager.allTables.length === 0) {
                    TablesManager.init();
                } else {
                    // Re-collect if tables are updated via AJAX
                    TablesManager.collectTables();
                    TablesManager.applyFilters();
                }
            }
        }
    }, 500);
});

