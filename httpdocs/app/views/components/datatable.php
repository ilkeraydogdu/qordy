<?php
/**
 * DataTable Component - Reusable Data Table with Filtering, Sorting, and Search
 * Usage: include this file and call renderDataTable() function
 * 
 * @param array $config Configuration array:
 *   - 'id' => string (required) - Unique ID for the table
 *   - 'columns' => array (required) - Column definitions
 *   - 'data' => array (required) - Data array
 *   - 'filters' => array (optional) - Filter configuration
 *   - 'search' => bool (optional) - Enable search (default: true)
 *   - 'pagination' => bool (optional) - Enable pagination (default: true)
 *   - 'perPage' => int (optional) - Items per page (default: 10)
 *   - 'actions' => array (optional) - Action buttons configuration
 */

if (!function_exists('cleanDataForJson')) {
    /**
     * Recursively clean data for JSON encoding
     * Removes resources, closures, and invalid characters
     */
    function cleanDataForJson($data) {
        if (is_array($data)) {
            $cleaned = [];
            foreach ($data as $key => $value) {
                // Clean array keys
                $cleanKey = is_string($key) ? mb_convert_encoding($key, 'UTF-8', 'UTF-8') : $key;
                $cleaned[$cleanKey] = cleanDataForJson($value);
            }
            return $cleaned;
        } elseif (is_object($data)) {
            // Convert objects to arrays
            return cleanDataForJson((array) $data);
        } elseif (is_resource($data)) {
            // Skip resources
            return null;
        } elseif (is_string($data)) {
            // Clean string: remove invalid UTF-8 characters
            $cleaned = mb_convert_encoding($data, 'UTF-8', 'UTF-8');
            // Remove control characters except newlines and tabs
            $cleaned = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $cleaned);
            return $cleaned;
        } elseif (is_bool($data) || is_numeric($data) || is_null($data)) {
            return $data;
        } else {
            // Unknown type, convert to string
            return (string) $data;
        }
    }
}

if (!function_exists('safeJsonEncode')) {
    /**
     * Safely encode data to JSON with error handling
     */
    function safeJsonEncode($data, $default = '[]') {
        try {
            // Clean data first
            $cleaned = cleanDataForJson($data);
            
            // Encode with error handling
            $json = json_encode($cleaned, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_IGNORE);
            
            if ($json === false) {
                $error = json_last_error_msg();
                if (class_exists('\App\Core\Logger')) {
                    \App\Core\Logger::error('JSON encode failed in DataTable', [
                        'error' => $error,
                        'json_error_code' => json_last_error()
                    ]);
                }
                return $default;
            }
            
            return $json;
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('Exception in safeJsonEncode', ['error' => $e->getMessage()]);
            }
            return $default;
        }
    }
}

if (!function_exists('renderDataTable')) {
    function renderDataTable(array $config) {
        // Start output buffering to catch any PHP errors
        ob_start();
        
        try {
            $id = $config['id'] ?? 'datatable';
            $columns = $config['columns'] ?? [];
            $data = $config['data'] ?? [];
            $filters = $config['filters'] ?? [];
            $enableSearch = $config['search'] ?? true;
            $searchPlaceholder = $config['searchPlaceholder'] ?? 'Ara...';
            $enablePagination = $config['pagination'] ?? true;
            $perPage = $config['perPage'] ?? 10;
            $actions = $config['actions'] ?? [];
            $emptyMessage = $config['emptyMessage'] ?? 'Veri bulunamadı';
            
            // Ensure data is an array
            if (!is_array($data)) {
                $data = [];
            }
            
            // Ensure columns is an array
            if (!is_array($columns)) {
                $columns = [];
            }
            
            // Ensure config is an array
            if (!is_array($config)) {
                $config = [];
            }
            
            // Generate unique IDs
            $searchId = "{$id}-search";
            $filterId = "{$id}-filters";
            $tableId = "{$id}-table";
            $paginationId = "{$id}-pagination";
        
        ?>
        <div id="<?php echo htmlspecialchars($id); ?>" class="datatable-container">
            <!-- Search and Filters Bar -->
            <div class="mb-4 sm:mb-6 flex flex-col sm:flex-row gap-3 sm:gap-4">
                <?php if ($enableSearch): ?>
                <div class="flex-1">
                    <input type="text" 
                           id="<?php echo htmlspecialchars($searchId); ?>" 
                           placeholder="<?php echo htmlspecialchars($searchPlaceholder); ?>" 
                           class="w-full p-3 sm:p-4 bg-white rounded-xl sm:rounded-2xl border-2 border-slate-100 focus:border-orange-300 outline-none font-bold text-sm sm:text-base text-slate-900 transition-all"/>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($filters)): ?>
                <div id="<?php echo htmlspecialchars($filterId); ?>" class="flex flex-wrap gap-2 sm:gap-3">
                    <?php foreach ($filters as $filterKey => $filterConfig): 
                        $filterType = $filterConfig['type'] ?? 'select';
                        $filterLabel = $filterConfig['label'] ?? $filterKey;
                        $filterOptions = $filterConfig['options'] ?? [];
                        $filterIdAttr = "{$id}-filter-{$filterKey}";
                    ?>
                        <?php if ($filterType === 'select'): ?>
                            <select id="<?php echo htmlspecialchars($filterIdAttr); ?>" 
                                    class="px-3 sm:px-4 py-2 sm:py-3 bg-white rounded-xl sm:rounded-2xl border-2 border-slate-100 focus:border-orange-300 outline-none font-bold text-xs sm:text-sm text-slate-900 transition-all">
                                <option value=""><?php echo htmlspecialchars($filterLabel); ?></option>
                                <?php foreach ($filterOptions as $optionValue => $optionLabel): ?>
                                    <option value="<?php echo htmlspecialchars($optionValue); ?>">
                                        <?php echo htmlspecialchars($optionLabel); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        <?php elseif ($filterType === 'date'): ?>
                            <input type="date" 
                                   id="<?php echo htmlspecialchars($filterIdAttr); ?>" 
                                   class="px-3 sm:px-4 py-2 sm:py-3 bg-white rounded-xl sm:rounded-2xl border-2 border-slate-100 focus:border-orange-300 outline-none font-bold text-xs sm:text-sm text-slate-900 transition-all"/>
                        <?php elseif ($filterType === 'daterange'): ?>
                            <input type="date" 
                                   id="<?php echo htmlspecialchars($filterIdAttr); ?>-start" 
                                   placeholder="Başlangıç"
                                   class="px-3 sm:px-4 py-2 sm:py-3 bg-white rounded-xl sm:rounded-2xl border-2 border-slate-100 focus:border-orange-300 outline-none font-bold text-xs sm:text-sm text-slate-900 transition-all"/>
                            <input type="date" 
                                   id="<?php echo htmlspecialchars($filterIdAttr); ?>-end" 
                                   placeholder="Bitiş"
                                   class="px-3 sm:px-4 py-2 sm:py-3 bg-white rounded-xl sm:rounded-2xl border-2 border-slate-100 focus:border-orange-300 outline-none font-bold text-xs sm:text-sm text-slate-900 transition-all"/>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Table -->
            <div class="bg-white rounded-2xl sm:rounded-3xl border border-slate-50 shadow-soft overflow-hidden">
                <div class="overflow-x-auto">
                    <table id="<?php echo htmlspecialchars($tableId); ?>" class="w-full">
                        <thead class="bg-slate-50 border-b">
                            <tr>
                                <?php foreach ($columns as $column): ?>
                                    <th class="p-4 sm:p-6 text-left text-[8px] sm:text-[9px] lg:text-[10px] font-black text-slate-600 uppercase tracking-widest">
                                        <?php echo htmlspecialchars($column['label'] ?? ''); ?>
                                    </th>
                                <?php endforeach; ?>
                                <?php if (!empty($actions)): ?>
                                    <th class="p-4 sm:p-6 text-right text-[8px] sm:text-[9px] lg:text-[10px] font-black text-slate-600 uppercase tracking-widest">
                                        İşlemler
                                    </th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody id="<?php echo htmlspecialchars($tableId); ?>-body">
                            <!-- Data will be rendered here by JavaScript -->
                        </tbody>
                    </table>
                </div>
                
                <!-- Empty State -->
                <div id="<?php echo htmlspecialchars($tableId); ?>-empty" class="hidden p-12 text-center text-slate-600">
                    <?php echo htmlspecialchars($emptyMessage); ?>
                </div>
            </div>
            
            <!-- Pagination -->
            <?php if ($enablePagination): ?>
            <div id="<?php echo htmlspecialchars($paginationId); ?>" class="mt-4 sm:mt-6 flex justify-center items-center gap-2 sm:gap-3">
                <!-- Pagination will be rendered here by JavaScript -->
            </div>
            <?php endif; ?>
        </div>
        
        <?php
        // Flush the HTML output buffer so HTML is rendered
        if (ob_get_level() > 0) {
            ob_end_flush();
        }
        // Start new buffer for JavaScript
        ob_start();
        ?>
        <script>
        (function() {
            // Ensure helper functions are available in this scope
            // These should be defined globally before DataTable is rendered
            const formatReservationDateFn = window.formatReservationDate || (typeof formatReservationDate !== 'undefined' ? formatReservationDate : function(dateString) {
                if (!dateString) return '';
                try {
                    const date = new Date(dateString + 'T00:00:00');
                    if (isNaN(date.getTime())) return dateString;
                    const day = String(date.getDate()).padStart(2, '0');
                    const month = String(date.getMonth() + 1).padStart(2, '0');
                    const year = date.getFullYear();
                    return `${day}.${month}.${year}`;
                } catch (e) {
                    return dateString;
                }
            });
            
            const getStatusLabelFn = window.getStatusLabel || (typeof getStatusLabel !== 'undefined' ? getStatusLabel : function(status) {
                const statusOptions = {
                    'PENDING': 'Beklemede',
                    'CONFIRMED': 'Onaylandı',
                    'CANCELLED': 'İptal',
                    'COMPLETED': 'Tamamlandı',
                    'NO_SHOW': 'Gelmedi'
                };
                return statusOptions[status] || status || 'PENDING';
            });
            
            const escapeHtmlFn = window.escapeHtml || (typeof escapeHtml !== 'undefined' ? escapeHtml : function(text) {
                if (text === null || text === undefined) return '';
                const div = document.createElement('div');
                div.textContent = String(text);
                return div.innerHTML;
            });
            
            <?php
            // Safely encode JavaScript variables
            $jsTableId = safeJsonEncode($tableId, '""');
            $jsConfig = safeJsonEncode($config, '{}');
            $jsData = safeJsonEncode($data, '[]');
            $jsPerPage = is_numeric($perPage) ? (int)$perPage : 10;
            ?>
            const tableId = <?php echo $jsTableId; ?>;
            const config = <?php echo $jsConfig; ?>;
            const rawData = <?php echo $jsData; ?>;
            
            // Ensure rawData is an array
            let filteredData = Array.isArray(rawData) ? [...rawData] : [];
            let currentPage = 1;
            const perPage = <?php echo $jsPerPage; ?>;
            
            // Initialize DataTable
            function initDataTable() {
                // Debug: Check if data is available
                console.log('DataTable: Initializing', {
                    tableId: tableId,
                    dataCount: Array.isArray(rawData) ? rawData.length : 0,
                    dataType: typeof rawData,
                    configColumns: config.columns ? config.columns.length : 0,
                    rawDataSample: rawData.length > 0 ? rawData[0] : null
                });
                
                if (!Array.isArray(rawData)) {
                    console.error('DataTable: rawData is not an array!', rawData);
                    rawData = [];
                }
                
                if (rawData.length === 0) {
                    console.warn('DataTable: No data available for table', tableId);
                }
                
                renderTable();
                
                <?php if ($enableSearch): ?>
                const searchInput = document.getElementById(<?php echo json_encode($searchId); ?>);
                if (searchInput) {
                    searchInput.addEventListener('input', debounce(handleSearch, 300));
                }
                <?php endif; ?>
                
                <?php if (!empty($filters)): ?>
                <?php foreach ($filters as $filterKey => $filterConfig): ?>
                    <?php 
                    $filterType = $filterConfig['type'] ?? 'select';
                    $filterIdAttr = "{$id}-filter-{$filterKey}";
                    ?>
                    <?php if ($filterType === 'select'): ?>
                        const filter<?php echo ucfirst($filterKey); ?> = document.getElementById(<?php echo json_encode($filterIdAttr); ?>);
                        if (filter<?php echo ucfirst($filterKey); ?>) {
                            filter<?php echo ucfirst($filterKey); ?>.addEventListener('change', handleFilter);
                        }
                    <?php elseif ($filterType === 'date'): ?>
                        const filter<?php echo ucfirst($filterKey); ?> = document.getElementById(<?php echo json_encode($filterIdAttr); ?>);
                        if (filter<?php echo ucfirst($filterKey); ?>) {
                            filter<?php echo ucfirst($filterKey); ?>.addEventListener('change', handleFilter);
                        }
                    <?php elseif ($filterType === 'daterange'): ?>
                        const filter<?php echo ucfirst($filterKey); ?>Start = document.getElementById(<?php echo json_encode($filterIdAttr . '-start'); ?>);
                        const filter<?php echo ucfirst($filterKey); ?>End = document.getElementById(<?php echo json_encode($filterIdAttr . '-end'); ?>);
                        if (filter<?php echo ucfirst($filterKey); ?>Start) {
                            filter<?php echo ucfirst($filterKey); ?>Start.addEventListener('change', handleFilter);
                        }
                        if (filter<?php echo ucfirst($filterKey); ?>End) {
                            filter<?php echo ucfirst($filterKey); ?>End.addEventListener('change', handleFilter);
                        }
                    <?php endif; ?>
                <?php endforeach; ?>
                <?php endif; ?>
            }
            
            function handleSearch(e) {
                const searchTerm = e.target.value.toLowerCase().trim();
                applyFilters(searchTerm);
            }
            
            function handleFilter() {
                applyFilters();
            }
            
            function applyFilters(searchTerm = null) {
                <?php if ($enableSearch): ?>
                const searchValue = searchTerm !== null ? searchTerm : document.getElementById(<?php echo json_encode($searchId); ?>)?.value.toLowerCase().trim() || '';
                <?php else: ?>
                const searchValue = '';
                <?php endif; ?>
                
                filteredData = rawData.filter(item => {
                    // Search filter
                    if (searchValue) {
                        const searchableText = config.columns
                            .map(col => {
                                const field = col.field || col.key || '';
                                const value = item[field] || '';
                                return String(value).toLowerCase();
                            })
                            .join(' ');
                        
                        if (!searchableText.includes(searchValue)) {
                            return false;
                        }
                    }
                    
                    // Column filters
                    <?php foreach ($filters as $filterKey => $filterConfig): 
                        $filterType = $filterConfig['type'] ?? 'select';
                        $filterField = $filterConfig['field'] ?? $filterKey;
                        $filterIdAttr = "{$id}-filter-{$filterKey}";
                    ?>
                        <?php if ($filterType === 'select'): ?>
                            const filter<?php echo ucfirst($filterKey); ?>Value = document.getElementById(<?php echo json_encode($filterIdAttr); ?>)?.value || '';
                            if (filter<?php echo ucfirst($filterKey); ?>Value && item[<?php echo json_encode($filterField); ?>] !== filter<?php echo ucfirst($filterKey); ?>Value) {
                                return false;
                            }
                        <?php elseif ($filterType === 'date'): ?>
                            const filter<?php echo ucfirst($filterKey); ?>Value = document.getElementById(<?php echo json_encode($filterIdAttr); ?>)?.value || '';
                            if (filter<?php echo ucfirst($filterKey); ?>Value) {
                                const itemDate = item[<?php echo json_encode($filterField); ?>] || '';
                                if (itemDate !== filter<?php echo ucfirst($filterKey); ?>Value) {
                                    return false;
                                }
                            }
                        <?php elseif ($filterType === 'daterange'): ?>
                            const filter<?php echo ucfirst($filterKey); ?>Start = document.getElementById(<?php echo json_encode($filterIdAttr . '-start'); ?>)?.value || '';
                            const filter<?php echo ucfirst($filterKey); ?>End = document.getElementById(<?php echo json_encode($filterIdAttr . '-end'); ?>)?.value || '';
                            if (filter<?php echo ucfirst($filterKey); ?>Start || filter<?php echo ucfirst($filterKey); ?>End) {
                                const itemDate = item[<?php echo json_encode($filterField); ?>] || '';
                                if (filter<?php echo ucfirst($filterKey); ?>Start && itemDate < filter<?php echo ucfirst($filterKey); ?>Start) {
                                    return false;
                                }
                                if (filter<?php echo ucfirst($filterKey); ?>End && itemDate > filter<?php echo ucfirst($filterKey); ?>End) {
                                    return false;
                                }
                            }
                        <?php endif; ?>
                    <?php endforeach; ?>
                    
                    return true;
                });
                
                currentPage = 1;
                renderTable();
            }
            
            function renderTable() {
                const tbody = document.getElementById(tableId + '-body');
                const emptyState = document.getElementById(tableId + '-empty');
                
                if (!tbody) {
                    console.error('DataTable: tbody element not found:', tableId + '-body');
                    return;
                }
                
                // Debug log
                console.log('DataTable: Rendering table', {
                    filteredDataCount: filteredData.length,
                    currentPage: currentPage,
                    perPage: perPage
                });
                
                <?php if ($enablePagination): ?>
                const start = (currentPage - 1) * perPage;
                const end = start + perPage;
                const paginatedData = filteredData.slice(start, end);
                <?php else: ?>
                const paginatedData = filteredData;
                <?php endif; ?>
                
                if (paginatedData.length === 0) {
                    tbody.innerHTML = '';
                    if (emptyState) emptyState.classList.remove('hidden');
                    renderPagination();
                    return;
                }
                
                if (emptyState) emptyState.classList.add('hidden');
                
                tbody.innerHTML = paginatedData.map(item => {
                    let row = '<tr class="hover:bg-slate-50 transition-all border-b border-slate-100 text-slate-900">';
                    
                    <?php foreach ($columns as $column): 
                        $field = $column['field'] ?? $column['key'] ?? '';
                        $render = $column['render'] ?? null;
                    ?>
                        row += '<td class="p-4 sm:p-6">';
                        <?php if ($render): ?>
                            <?php 
                            // Process render template
                            $renderCode = $render;
                            // Replace template literal placeholders with JavaScript template literal syntax
                            $renderCode = str_replace('${item.', '${item.', $renderCode);
                            ?>
                            (function() {
                                let template = <?php echo safeJsonEncode($renderCode, '""'); ?>;
                                
                                // Replace custom function calls using the functions defined in this scope
                                template = template.replace(/\$\{formatReservationDate\(item\.([^}]+)\)\}/g, (match, field) => {
                                    try {
                                        return formatReservationDateFn(item[field] || '');
                                    } catch (e) {
                                        console.error('Error in formatReservationDate:', e);
                                        return item[field] || '';
                                    }
                                });
                                
                                template = template.replace(/\$\{getStatusLabel\(item\.([^}]+)\)\}/g, (match, field) => {
                                    try {
                                        return getStatusLabelFn(item[field] || '');
                                    } catch (e) {
                                        console.error('Error in getStatusLabel:', e);
                                        return item[field] || '';
                                    }
                                });
                                
                                // ${item.field|raw} => inject field value as HTML without escaping.
                                // Useful for pre-rendered badges/tags where the PHP side already
                                // produced safe HTML (it must escape its own user content!).
                                template = template.replace(/\$\{item\.([a-zA-Z0-9_]+)\|raw\}/g, (match, field) => {
                                    const v = item[field];
                                    return v === null || v === undefined ? '' : String(v);
                                });

                                // Default: HTML-escape any plain ${item.field} reference.
                                template = template.replace(/\$\{item\.([a-zA-Z0-9_]+)\}/g, (match, field) => {
                                    try {
                                        return escapeHtmlFn(String(item[field] ?? ''));
                                    } catch (e) {
                                        console.error('Error in escapeHtml:', e);
                                        return String(item[field] ?? '');
                                    }
                                });
                                
                                row += template;
                            })();
                        <?php else: ?>
                            (function() {
                                try {
                                    row += escapeHtmlFn(String(item[<?php echo safeJsonEncode($field, '""'); ?>] || ''));
                                } catch (e) {
                                    console.error('Error in escapeHtml:', e);
                                    row += String(item[<?php echo safeJsonEncode($field, '""'); ?>] || '');
                                }
                            })();
                        <?php endif; ?>
                        row += '</td>';
                    <?php endforeach; ?>
                    
                    <?php if (!empty($actions)): ?>
                        row += '<td class="p-4 sm:p-6 text-right text-slate-900">';
                        row += '<div class="flex justify-end gap-2">';
                        <?php foreach ($actions as $action): 
                            $actionType = $action['type'] ?? 'button';
                            $actionLabel = $action['label'] ?? '';
                            $actionOnClick = $action['onClick'] ?? '';
                            $actionClass = $action['class'] ?? '';
                        ?>
                            <?php if ($actionType === 'button'): ?>
                                (function() {
                                    let onClick = <?php echo safeJsonEncode($actionOnClick, '""'); ?>;
                                    let actionClass = <?php echo safeJsonEncode($actionClass, '""'); ?>;
                                    let actionLabel = <?php echo safeJsonEncode($actionLabel, '""'); ?>;
                                    
                                    // Replace ${item.field} with actual item values
                                    onClick = onClick.replace(/\$\{item\.([^}]+)\}/g, (match, field) => {
                                        const value = String(item[field] || '');
                                        return value;
                                    });
                                    
                                    // Debug log for first row
                                    if (paginatedData.indexOf(item) === 0 && actionLabel === 'Görüntüle') {
                                        console.log('Button onClick debug:', {
                                            original: <?php echo safeJsonEncode($actionOnClick, '""'); ?>,
                                            processed: onClick,
                                            item_id: item.reservation_id
                                        });
                                    }
                                    
                                    // Build button with proper escaping
                                    const escapedOnClick = onClick.replace(/"/g, '&quot;');
                                    row += `<button onclick="${escapedOnClick}" class="${actionClass}">${actionLabel}</button>`;
                                })();
                            <?php endif; ?>
                        <?php endforeach; ?>
                        row += '</div>';
                        row += '</td>';
                    <?php endif; ?>
                    
                    row += '</tr>';
                    return row;
                }).join('');
                
                renderPagination();
            }
            
            <?php if ($enablePagination): ?>
            function renderPagination() {
                const paginationEl = document.getElementById(<?php echo json_encode($paginationId); ?>);
                if (!paginationEl) return;
                
                const totalPages = Math.ceil(filteredData.length / perPage);
                
                if (totalPages <= 1) {
                    paginationEl.innerHTML = '';
                    return;
                }
                
                const goToPageKey = 'datatable_goToPage_' + tableId;
                window[goToPageKey] = function(page) {
                    const totalPgs = Math.ceil(filteredData.length / perPage);
                    if (page < 1 || page > totalPgs) return;
                    currentPage = page;
                    renderTable();
                    document.getElementById(tableId)?.scrollIntoView({ behavior: 'smooth', block: 'start' });
                };
                
                let html = '';
                
                // Previous button
                html += `<button type="button" data-page="${currentPage - 1}" 
                         ${currentPage === 1 ? 'disabled' : ''} 
                         class="px-3 sm:px-4 py-2 rounded-lg font-bold text-xs sm:text-sm text-slate-900 ${currentPage === 1 ? 'opacity-50 cursor-not-allowed' : 'hover:bg-slate-100'}">Önceki</button>`;
                
                // Page numbers
                for (let i = 1; i <= totalPages; i++) {
                    if (i === 1 || i === totalPages || (i >= currentPage - 2 && i <= currentPage + 2)) {
                        html += `<button type="button" data-page="${i}" 
                                 class="px-3 sm:px-4 py-2 rounded-lg font-bold text-xs sm:text-sm ${i === currentPage ? 'bg-slate-900 text-white' : 'hover:bg-slate-100'}">${i}</button>`;
                    } else if (i === currentPage - 3 || i === currentPage + 3) {
                        html += `<span class="px-2 text-slate-600">...</span>`;
                    }
                }
                
                // Next button
                html += `<button type="button" data-page="${currentPage + 1}" 
                         ${currentPage === totalPages ? 'disabled' : ''} 
                         class="px-3 sm:px-4 py-2 rounded-lg font-bold text-xs sm:text-sm text-slate-900 ${currentPage === totalPages ? 'opacity-50 cursor-not-allowed' : 'hover:bg-slate-100'}">Sonraki</button>`;
                
                paginationEl.innerHTML = html;
                paginationEl.querySelectorAll('button[data-page]').forEach(function(btn) {
                    if (btn.disabled) return;
                    const p = parseInt(btn.getAttribute('data-page'), 10);
                    btn.addEventListener('click', function() { window[goToPageKey](p); });
                });
            }
            
            function goToPage(page) {
                const totalPages = Math.ceil(filteredData.length / perPage);
                if (page < 1 || page > totalPages) return;
                currentPage = page;
                renderTable();
                document.getElementById(tableId)?.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
            <?php endif; ?>
            
            // Use Utils.escapeHtml from utils.js (loaded globally)
            
            function debounce(func, wait) {
                let timeout;
                return function executedFunction(...args) {
                    const later = () => {
                        clearTimeout(timeout);
                        func(...args);
                    };
                    clearTimeout(timeout);
                    timeout = setTimeout(later, wait);
                };
            }
            
            // Initialize on DOM ready
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', initDataTable);
            } else {
                initDataTable();
            }
        })();
        </script>
        <?php
        // Get JavaScript output
        $jsOutput = ob_get_clean();
        
        // Simply output the JavaScript - HTML tags in JS strings are normal
        echo $jsOutput;
        
        } catch (\Exception $e) {
            // Clean any output on error
            ob_end_clean();
            
            // Log error
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('DataTable render error', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
            
            // Show error message to user (safe, no user data)
            echo '<div class="p-4 bg-red-50 border border-red-200 rounded-lg text-red-700">';
            echo '<strong>Hata:</strong> Tablo yüklenirken bir sorun oluştu.';
            echo '</div>';
        } catch (\Throwable $e) {
            // Clean any output on fatal error
            ob_end_clean();
            
            // Log error
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('DataTable fatal error', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
            
            // Show error message to user
            echo '<div class="p-4 bg-red-50 border border-red-200 rounded-lg text-red-700">';
            echo '<strong>Hata:</strong> Tablo yüklenirken bir sorun oluştu.';
            echo '</div>';
        }
    }
}

