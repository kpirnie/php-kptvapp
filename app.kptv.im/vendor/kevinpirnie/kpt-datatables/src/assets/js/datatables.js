/**
 * DataTables JavaScript - External File
 * 
 * Complete JavaScript functionality for DataTables including
 * AJAX operations, table rendering, pagination, search, 
 * bulk actions, and theme management.
 * 
 * @since   1.0.0
 * @author  Kevin Pirnie <me@kpirnie.com>
 * @package KPT/DataTables
 */

class DataTablesJS {
    constructor(config = {}) {
        // Configuration
        this.tableName = config.tableName || '';
        this.primaryKey = config.primaryKey || 'id';
        this.inlineEditableColumns = config.inlineEditableColumns || [];
        this.perPage = config.perPage || 25;
        this.bulkActionsEnabled = config.bulkActionsEnabled || false;
        this.bulkActions = config.bulkActions || {};
        this.actionConfig = config.actionConfig || {};
        this.columns = config.columns || {};
        this.cssClasses = config.cssClasses || {};
        this.theme = config.theme || 'uikit';
        this.footerAggregations = config.footerAggregations || {};

        // State
        this.currentPage = 1;
        this.sortColumn = config.defaultSortColumn || '';
        this.sortDirection = config.defaultSortDirection || 'ASC';
        this.search = '';
        this.deleteId = null;
        this.selectedIds = new Set();

        // Initialize
        this.init();
    }

    init() {
        this.bindEvents();
        this.loadData();

        // Expose methods globally
        window.DataTables = this;
    }

    // === THEME HELPERS ===
    getThemeClass(type) {
        const classes = {
            uikit: {
                table: { shrink: 'uk-table-shrink', center: 'uk-text-center', muted: 'uk-text-muted' },
                checkbox: 'uk-checkbox',
                input: 'uk-input uk-width-1-1',
                select: 'uk-select uk-width-1-1',
                textarea: 'uk-textarea uk-width-1-1',
                button: { default: 'uk-button uk-button-default', primary: 'uk-button uk-button-primary', small: 'uk-button-small' },
                icon: { link: 'uk-icon-link', success: 'uk-text-success', danger: 'uk-text-danger' },
                pagination: { disabled: 'uk-disabled', active: 'uk-active' },
                flex: { right: 'uk-flex uk-flex-right', between: 'uk-flex-between' },
                margin: { smallRight: 'uk-margin-small-right', smallBottom: 'uk-margin-small-bottom', smallTop: 'uk-margin-small-top' },
                border: { rounded: 'uk-border-rounded' },
                display: { block: 'uk-display-block' }
            },
            bootstrap: {
                table: { shrink: '', center: 'text-center', muted: 'text-muted' },
                checkbox: 'form-check-input',
                input: 'form-control',
                select: 'form-select',
                textarea: 'form-control',
                button: { default: 'btn btn-secondary', primary: 'btn btn-primary', small: 'btn-sm' },
                icon: { link: '', success: 'text-success', danger: 'text-danger' },
                pagination: { disabled: 'disabled', active: 'active' },
                flex: { right: 'd-flex justify-content-end', between: 'justify-content-between' },
                margin: { smallRight: 'me-2', smallBottom: 'mb-2', smallTop: 'mt-2' },
                border: { rounded: 'rounded' },
                display: { block: 'd-block' }
            },
            plain: {
                table: { shrink: 'kp-dt-table-shrink', center: 'kp-dt-text-center', muted: 'kp-dt-text-muted' },
                checkbox: 'kp-dt-checkbox',
                input: 'kp-dt-input kp-dt-width-1-1',
                select: 'kp-dt-select kp-dt-width-1-1',
                textarea: 'kp-dt-textarea kp-dt-width-1-1',
                button: { default: 'kp-dt-button', primary: 'kp-dt-button kp-dt-button-primary', small: 'kp-dt-button-small' },
                icon: { link: 'kp-dt-icon-link', success: 'kp-dt-text-success', danger: 'kp-dt-text-danger' },
                pagination: { disabled: 'kp-dt-disabled', active: 'kp-dt-active' },
                flex: { right: 'kp-dt-flex kp-dt-flex-right', between: 'kp-dt-flex-between' },
                margin: { smallRight: 'kp-dt-margin-small-right', smallBottom: 'kp-dt-margin-small-bottom', smallTop: 'kp-dt-margin-small-top' },
                border: { rounded: 'kp-dt-border-rounded' },
                display: { block: 'kp-dt-display-block' }
            },
            tailwind: {
                table: { shrink: 'w-px whitespace-nowrap', center: 'text-center', muted: 'text-gray-500' },
                checkbox: 'h-4 w-4 rounded border-gray-300',
                input: 'block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500',
                select: 'block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500',
                textarea: 'block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500',
                button: { default: 'inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50', primary: 'inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700', small: 'px-2 py-1 text-xs' },
                icon: { link: 'text-gray-400 hover:text-gray-600', success: 'text-green-500', danger: 'text-red-500' },
                pagination: { disabled: 'opacity-50 cursor-not-allowed', active: 'font-bold text-blue-600' },
                flex: { right: 'flex justify-end', between: 'justify-between' },
                margin: { smallRight: 'mr-2', smallBottom: 'mb-2', smallTop: 'mt-2' },
                border: { rounded: 'rounded' },
                display: { block: 'block' }
            }
        };

        const themeClasses = classes[this.theme] || classes.uikit;
        const parts = type.split('.');
        let result = themeClasses;
        for (const part of parts) {
            result = result?.[part];
        }
        return result || '';
    }

    // Theme-aware notification
    showNotification(message, status = 'success') {
        if (this.theme === 'uikit' && typeof UIkit !== 'undefined') {
            UIkit.notification(message, { status: status });
        } else if (this.theme === 'bootstrap' && typeof KPDataTablesBootstrap !== 'undefined') {
            KPDataTablesBootstrap.notification(message, status);
        } else if (typeof KPDataTablesPlain !== 'undefined') {
            KPDataTablesPlain.notification(message, status);
        } else {
            alert(message);
        }
    }

    // Theme-aware modal show
    showModal(modalId) {
        if (this.theme === 'uikit' && typeof UIkit !== 'undefined') {
            UIkit.modal(`#${modalId}`).show();
        } else if (this.theme === 'bootstrap' && typeof bootstrap !== 'undefined') {
            const modal = new bootstrap.Modal(document.getElementById(modalId));
            modal.show();
        } else if (typeof KPDataTablesPlain !== 'undefined') {
            KPDataTablesPlain.showModal(modalId);
        }
    }

    // Theme-aware modal hide
    hideModal(modalId) {
        if (this.theme === 'uikit' && typeof UIkit !== 'undefined') {
            UIkit.modal(`#${modalId}`).hide();
        } else if (this.theme === 'bootstrap' && typeof bootstrap !== 'undefined') {
            const modalEl = document.getElementById(modalId);
            const modal = bootstrap.Modal.getInstance(modalEl);
            if (modal) modal.hide();
        } else if (typeof KPDataTablesPlain !== 'undefined') {
            KPDataTablesPlain.hideModal(modalId);
        }
        // Cleanup any open select2 dropdowns
        document.querySelectorAll('.kp-select2-dropdown').forEach(dd => {
            dd.style.display = 'none';
        });
    }

    // Theme-aware confirm dialog
    showConfirm(message) {
        return new Promise((resolve, reject) => {
            if (this.theme === 'uikit' && typeof UIkit !== 'undefined') {
                UIkit.modal.confirm(message).then(resolve, reject);
            } else if (this.theme === 'bootstrap' && typeof KPDataTablesBootstrap !== 'undefined') {
                KPDataTablesBootstrap.confirm(message).then(resolve, reject);
            } else if (typeof KPDataTablesPlain !== 'undefined') {
                KPDataTablesPlain.confirm(message).then(resolve, reject);
            } else {
                if (confirm(message)) {
                    resolve();
                } else {
                    reject();
                }
            }
        });
    }

    // Theme-aware icon rendering
    renderIcon(iconName, extraClass = '') {
        if (this.theme === 'uikit') {
            return `<span uk-icon="${iconName}" class="${extraClass}"></span>`;
        } else if (this.theme === 'bootstrap') {
            const iconMap = {
                'check': 'bi-check-lg',
                'close': 'bi-x-lg',
                'pencil': 'bi-pencil',
                'trash': 'bi-trash',
                'plus': 'bi-plus',
                'search': 'bi-search',
                'refresh': 'bi-arrow-clockwise',
                'triangle-up': 'bi-caret-up-fill',
                'triangle-down': 'bi-caret-down-fill',
                'chevron-double-left': 'bi-chevron-double-left',
                'chevron-double-right': 'bi-chevron-double-right'
            };
            return `<i class="bi ${iconMap[iconName] || 'bi-link'} ${extraClass}"></i>`;
        } else {
            // Plain/Tailwind - use SVG icons
            const icons = {
                'check': '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20" fill="currentColor"><polyline fill="none" stroke="currentColor" stroke-width="1.1" points="4,10 8,15 17,4"></polyline></svg>',
                'close': '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20" fill="currentColor"><line fill="none" stroke="currentColor" stroke-width="1.4" x1="1" y1="1" x2="19" y2="19"></line><line fill="none" stroke="currentColor" stroke-width="1.4" x1="19" y1="1" x2="1" y2="19"></line></svg>',
                'pencil': '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20" fill="currentColor"><path fill="none" stroke="currentColor" d="M17.25,6.01 L7.12,16.1 L3.82,17.2 L5.02,13.9 L15.12,3.88 C15.71,3.29 16.66,3.29 17.25,3.88 C17.84,4.47 17.84,5.42 17.25,6.01"></path></svg>',
                'trash': '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20" fill="currentColor"><polyline fill="none" stroke="currentColor" points="6.5 3 6.5 1.5 13.5 1.5 13.5 3"></polyline><polyline fill="none" stroke="currentColor" points="3.5 4 16.5 4 15.5 18.5 4.5 18.5 3.5 4"></polyline></svg>',
                'plus': '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20" fill="currentColor"><line fill="none" stroke="currentColor" x1="10" y1="1" x2="10" y2="19"></line><line fill="none" stroke="currentColor" x1="1" y1="10" x2="19" y2="10"></line></svg>',
                'search': '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20" fill="currentColor"><circle fill="none" stroke="currentColor" stroke-width="1.1" cx="9" cy="9" r="7"></circle><path fill="none" stroke="currentColor" stroke-width="1.1" d="M14,14 L18,18 L14,14 Z"></path></svg>',
                'refresh': '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20" fill="currentColor"><path fill="none" stroke="currentColor" stroke-width="1.1" d="M17.08,11.15 C17.09,11.31 17.1,11.47 17.1,11.64 C17.1,15.53 13.94,18.69 10.05,18.69 C6.16,18.68 3,15.53 3,11.63 C3,7.74 6.16,4.58 10.05,4.58 C10.9,4.58 11.71,4.73 12.46,5"></path><polyline fill="none" stroke="currentColor" points="9.9 2 12.79 4.89 9.79 7.9"></polyline></svg>',
                'triangle-up': '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20" fill="currentColor"><polygon points="10,5 15,14 5,14"></polygon></svg>',
                'triangle-down': '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20" fill="currentColor"><polygon points="10,15 15,6 5,6"></polygon></svg>',
                'chevron-double-left': '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20" fill="currentColor"><polyline fill="none" stroke="currentColor" stroke-width="1.2" points="10,14 6,10 10,6"></polyline><polyline fill="none" stroke="currentColor" stroke-width="1.2" points="14,14 10,10 14,6"></polyline></svg>',
                'chevron-double-right': '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20" fill="currentColor"><polyline fill="none" stroke="currentColor" stroke-width="1.2" points="10,14 14,10 10,6"></polyline><polyline fill="none" stroke="currentColor" stroke-width="1.2" points="6,14 10,10 6,6"></polyline></svg>'
            };
            return `<span class="${extraClass}">${icons[iconName] || ''}</span>`;
        }
    }

    // === EVENT BINDING ===
    bindEvents() {
        // Search input
        document.querySelectorAll('.datatables-search').forEach(searchInput => {
            let searchTimeout;
            searchInput.addEventListener('input', (e) => {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    this.search = e.target.value;
                    this.currentPage = 1;
                    this.loadData();
                }, 300);
            });
        });

        // Page size selector
        document.querySelectorAll('.datatables-page-size').forEach(pageSizeSelect => {
            pageSizeSelect.addEventListener('change', (e) => {
                this.perPage = parseInt(e.target.value);
                this.currentPage = 1;

                // Sync all page size selectors to the same value
                document.querySelectorAll('.datatables-page-size').forEach(select => {
                    select.value = e.target.value;
                });

                this.loadData();
            });
        });

        // Bulk actions
        if (this.bulkActionsEnabled) {
            document.querySelectorAll('.datatables-bulk-action').forEach(bulkSelect => {
                bulkSelect.addEventListener('change', (e) => {
                    document.querySelectorAll('.datatables-bulk-execute').forEach(executeBtn => {
                        executeBtn.disabled = !e.target.value || this.selectedIds.size === 0;
                    });
                });
            });
        }

        // Sortable headers
        document.addEventListener('click', (e) => {
            if (e.target.closest('.sortable-header')) {
                const header = e.target.closest('th[data-sort]');
                if (header) {
                    const column = header.getAttribute('data-sort');
                    if (this.sortColumn === column) {
                        this.sortDirection = this.sortDirection === 'ASC' ? 'DESC' : 'ASC';
                    } else {
                        this.sortColumn = column;
                        this.sortDirection = 'ASC';
                    }
                    this.currentPage = 1;
                    this.loadData();
                    this.updateSortIcons();
                }
            }
        });
    }

    // === DATA LOADING ===
    loadData() {
        const params = new URLSearchParams(
            {
                action: 'fetch_data',
                table: this.tableName,
                page: this.currentPage,
                per_page: this.perPage,
                search: this.search,
                sort_column: this.sortColumn,
                sort_direction: this.sortDirection
            }
        );

        fetch('?' + params.toString())
            .then(response => response.json())
            .then(
                data => {
                    if (data.success) {
                        this.renderTable(data.data);
                        this.renderPagination(data);
                        this.renderInfo(data);
                        this.loadAggregations();
                    } else {
                        console.error('Failed to load data:', data.message);
                        this.showNotification(data.message || 'Failed to load data', 'danger');
                    }
                }
            )
            .catch(
                error => {
                    console.error('Error loading data:', error);
                    this.showNotification('Error loading data', 'danger');
                }
            );
    }

    // === AGGREGATION ===
    loadAggregations() {
        if (!this.footerAggregations || Object.keys(this.footerAggregations).length === 0) {
            return;
        }

        const params = new URLSearchParams({
            action: 'fetch_aggregations',
            table: this.tableName,
            search: this.search
        });

        fetch('?' + params.toString())
            .then(response => response.json())
            .then(data => {
                if (data.success && data.aggregations) {
                    this.renderAggregations(data.aggregations);
                }
            })
            .catch(error => {
                console.error('Error loading aggregations:', error);
            });
    }

    renderAggregations(serverAggregations) {
        // Update "all" scope cells from server data
        document.querySelectorAll('.datatables-agg-cell[data-agg-scope="all"]').forEach(cell => {
            const column = cell.getAttribute('data-agg-column');
            const type = cell.getAttribute('data-agg-type');
            if (serverAggregations[column] && serverAggregations[column][type] !== undefined) {
                cell.textContent = this.formatAggValue(serverAggregations[column][type]);
            }
        });
    }

    calculatePageAggregations(data) {
        if (!this.footerAggregations || Object.keys(this.footerAggregations).length === 0) {
            return;
        }

        if (!data || data.length === 0) {
            document.querySelectorAll('.datatables-agg-cell[data-agg-scope="page"]').forEach(cell => {
                cell.textContent = '—';
            });
            return;
        }

        document.querySelectorAll('.datatables-agg-cell[data-agg-scope="page"]').forEach(cell => {
            const column = cell.getAttribute('data-agg-column');
            const type = cell.getAttribute('data-agg-type');

            let values = data.map(row => {
                let val = row[column];
                if (val === null || val === undefined || val === '') {
                    return 0;
                }
                return parseFloat(val) || 0;
            });

            let result = 0;
            if (type === 'sum') {
                result = values.reduce((a, b) => a + b, 0);
            } else if (type === 'avg') {
                const sum = values.reduce((a, b) => a + b, 0);
                result = values.length > 0 ? sum / values.length : 0;
            }

            cell.textContent = this.formatAggValue(result);
        });
    }

    formatAggValue(value) {
        if (Number.isInteger(value)) {
            return value.toLocaleString();
        }
        return parseFloat(value.toFixed(2)).toLocaleString(undefined, {
            minimumFractionDigits: 0,
            maximumFractionDigits: 2
        });
    }

    // === TABLE RENDERING ===
    renderTable(data) {
        const tbody = document.querySelector('.datatables-tbody');
        if (!tbody) { return; }

        const columnCount = this.getColumnCount();
        const shrinkClass = this.getThemeClass('table.shrink');
        const centerClass = this.getThemeClass('table.center');
        const mutedClass = this.getThemeClass('table.muted');
        const checkboxClass = this.getThemeClass('checkbox');

        if (!data || data.length === 0) {
            tbody.innerHTML = `<tr><td colspan="${columnCount}" class="${centerClass} ${mutedClass}">No records found</td></tr>`;
            return;
        }

        // Get table schema for field type information
        const tableElement = document.querySelector('.datatables-table');
        const tableSchema = tableElement ? JSON.parse(tableElement.dataset.columns || '{}') : {};

        let html = '';
        data.forEach(
            row => {

                // Find the ID value regardless of key format
                const rowId = row['s.id'] || row['id'] || row[this.primaryKey] || Object.values(row)[0];
                const rowClass = this.getRowClass(rowId);
                html += `<tr${rowClass ? ` class="${rowClass} row-select"` : ''} data-id="${rowId}">`;

                // Bulk selection checkbox
                if (this.bulkActionsEnabled) {
                    html += `<td class="${shrinkClass} row-check">`;
                    html += `<label><input type="checkbox" class="${checkboxClass} row-checkbox" value="${rowId}" onchange="DataTables.toggleRowSelection(this)"></label>`;
                    html += '</td>';
                }

                // Action column at start
                if (this.actionConfig.position === 'start') {
                    html += `<td class="${shrinkClass} row-action">`;
                    html += this.renderActionButtons(rowId, row);
                    html += '</td>';
                }

                // Regular columns - simplified structure where key=column, value=label
                Object.keys(this.columns).forEach(
                    column => {
                        // Check for CSS classes using both full column key and alias name
                        let columnClass = this.cssClasses?.columns?.[column] || '';
                        if (!columnClass && column.toLowerCase().includes(' as ')) {
                            const parts = column.split(/\s+as\s+/i);
                            if (parts.length === 2) {
                                const aliasName = parts[1].replace(/[`'"]/g, '');
                                columnClass = this.cssClasses?.columns?.[aliasName] || '';
                            }
                        }
                        const isEditable = this.inlineEditableColumns.includes(column);

                        // Handle aliases - if column contains " AS ", use the alias name to access row data
                        let dataKey = column;
                        if (column.toLowerCase().includes(' as ')) {
                            const parts = column.split(/\s+as\s+/i);
                            if (parts.length === 2) {
                                dataKey = parts[1].replace(/[`'"]/g, ''); // Remove any quotes/backticks
                            }
                        }

                        let cellContent = row[dataKey] ?? '';
                        const tdClass = isEditable ? ' cell-edit' : '';

                        // Get field type from schema
                        const fieldType = tableSchema[column]?.override_type || tableSchema[column]?.type || 'text';

                        // Handle boolean display with icons
                        if (fieldType === 'boolean') {
                            const isActive = cellContent == '1' || cellContent === 'true' || cellContent === true;
                            const iconName = isActive ? 'check' : 'close';
                            const iconClass = isActive ? this.getThemeClass('icon.success') : this.getThemeClass('icon.danger');

                            // Store the raw value for form population
                            const rawValue = cellContent; // Keep original value

                            if (isEditable) {
                                cellContent = `<span class="inline-editable boolean-toggle" data-field="${column}" data-id="${rowId}" data-type="boolean" data-value="${rawValue}" style="cursor: pointer;">`;
                                cellContent += this.renderIcon(iconName, iconClass);
                                cellContent += '</span>';
                            } else {
                                cellContent = `<span data-value="${rawValue}">${this.renderIcon(iconName, iconClass)}</span>`;
                            }

                            // Handle select display with labels
                        } else if (fieldType === 'select') {
                            const selectOptions = tableSchema[column]?.form_options || {};
                            // Convert cellContent to string to ensure proper key lookup
                            const cellContentStr = String(cellContent);
                            // Use nullish coalescing or check if key exists to handle '0' value correctly
                            const displayLabel = cellContentStr in selectOptions ? selectOptions[cellContentStr] : cellContent;

                            if (isEditable) {
                                cellContent = `<span class="inline-editable" data-field="${column}" data-id="${rowId}" data-type="${fieldType}" data-value="${cellContent}" style="cursor: pointer;">${displayLabel}</span>`;
                            } else {
                                cellContent = displayLabel;
                            }

                            // Handle select2 display with fetched labels
                        } else if (fieldType === 'select2') {
                            const labelKey = dataKey + '_label';
                            const displayValue = row[labelKey] || cellContent;

                            if (isEditable) {
                                cellContent = `<span class="inline-editable" data-field="${column}" data-id="${rowId}" data-type="${fieldType}" data-value="${cellContent}" style="cursor: pointer;">${displayValue}</span>`;
                            } else {
                                cellContent = displayValue;
                            }

                            // Handle image display with thumbnails
                        } else if (fieldType === 'image') {
                            const roundedClass = this.getThemeClass('border.rounded');
                            if (cellContent && cellContent.trim()) {
                                const imageSrc = cellContent.startsWith('http') ? cellContent : `/uploads/${cellContent}`;

                                if (isEditable) {
                                    cellContent = `<span class="inline-editable" data-field="${column}" data-id="${rowId}" data-type="${fieldType}" data-value="${cellContent}" style="cursor: pointer;">`;
                                    cellContent += `<img src="${imageSrc}" alt="Image" style="max-width: 50px; max-height: 50px; object-fit: cover;" class="${roundedClass}">`;
                                    cellContent += '</span>';
                                } else {
                                    cellContent = `<img src="${imageSrc}" alt="Image" style="max-width: 50px; max-height: 50px; object-fit: cover;" class="${roundedClass}">`;
                                }
                            } else {
                                cellContent = isEditable ?
                                    `<span class="inline-editable" data-field="${column}" data-id="${rowId}" data-type="${fieldType}" data-value="" style="cursor: pointer;">No image</span>` :
                                    'No image';
                            }

                        } else if (isEditable) {

                            // Add inline-editable class and attributes for non-boolean editable fields
                            cellContent = `<span class="inline-editable" data-field="${column}" data-id="${rowId}" data-type="${fieldType}" style="cursor: pointer;">${cellContent}</span>`;
                        }

                        const classNames = [columnClass, tdClass].filter(c => c).join(' ');
                        html += `<td${classNames ? ` class="${classNames}"` : ''}>${cellContent}</td>`;
                    }
                );

                // Action column at end
                if (this.actionConfig.position === 'end') {
                    html += `<td class="${shrinkClass} row-action">`;
                    html += this.renderActionButtons(rowId, row);
                    html += '</td>';
                }

                html += '</tr>';
            }
        );

        tbody.innerHTML = html;
        this.bindTableEvents();
        this.updateBulkActionButtons();
        this.calculatePageAggregations(data);
    }

    renderActionButtons(rowId, rowData = {}) {
        let html = '';
        const iconLinkClass = this.getThemeClass('icon.link');
        const marginSmallRightClass = this.getThemeClass('margin.smallRight');

        // Store row data for callback use
        if (!window.DataTablesRowData) {
            window.DataTablesRowData = {};
        }
        window.DataTablesRowData[rowId] = rowData;

        // Helper function to replace all placeholders in a string
        const replacePlaceholders = (str) => {
            if (typeof str !== 'string') return str;

            let result = str.replace('{id}', rowId);
            for (const [column, value] of Object.entries(rowData)) {
                const placeholder = '{' + column + '}';
                result = result.replace(new RegExp(placeholder.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), 'g'), value || '');
            }
            return result;
        };

        // Check if we have action groups configured
        if (this.actionConfig.groups && this.actionConfig.groups.length > 0) {

            this.actionConfig.groups.forEach(group => {

                if (Array.isArray(group)) {
                    // Array of built-in actions like ['edit', 'delete']
                    group.forEach(actionItem => {
                        switch (actionItem) {
                            case 'edit':
                                if (this.theme === 'uikit') {
                                    html += '<a href="#" class="uk-icon-link btn-edit uk-margin-tiny-full" uk-icon="pencil" title="Edit Record" uk-tooltip="Edit Record"></a>';
                                } else {
                                    html += `<a href="#" class="${iconLinkClass} btn-edit" title="Edit Record">${this.renderIcon('pencil')}</a>`;
                                }
                                break;
                            case 'delete':
                                if (this.theme === 'uikit') {
                                    html += '<a href="#" class="uk-icon-link btn-delete uk-margin-tiny-full" uk-icon="trash" title="Delete Record" uk-tooltip="Delete Record"></a>';
                                } else {
                                    html += `<a href="#" class="${iconLinkClass} btn-delete" title="Delete Record">${this.renderIcon('trash')}</a>`;
                                }
                                break;
                        }
                    });
                } else if (typeof group === 'object' && group !== null) {

                    // Handle all html* keys with location 'before' first
                    Object.keys(group).filter(key => key.startsWith('html')).forEach(htmlKey => {
                        const htmlConfig = group[htmlKey];
                        if (typeof htmlConfig === 'object' && htmlConfig.location && htmlConfig.content) {
                            if (htmlConfig.location === 'before' || htmlConfig.location === 'both') {
                                html += replacePlaceholders(htmlConfig.content);
                            }
                        } else if (typeof htmlConfig === 'string') {
                            html += replacePlaceholders(htmlConfig);
                        }
                    });

                    // Get action keys (excluding 'html')
                    const actionKeys = Object.keys(group).filter(key => !key.startsWith('html'));

                    actionKeys.forEach(actionKey => {
                        const actionConfig = group[actionKey];

                        if (!actionConfig || typeof actionConfig !== 'object') return;

                        // Check for action-level html before
                        if (actionConfig.html) {
                            if (typeof actionConfig.html === 'object' && actionConfig.html.location && actionConfig.html.content) {
                                if (actionConfig.html.location === 'before' || actionConfig.html.location === 'both') {
                                    html += replacePlaceholders(actionConfig.html.content);
                                }
                            } else if (typeof actionConfig.html === 'string' && !actionConfig.hasCallback && actionConfig.href === undefined && actionConfig.icon === undefined) {
                                // Only skip if this is PURELY an html entry with no action
                                html += replacePlaceholders(actionConfig.html);
                                return;
                            }
                        }

                        if (actionConfig.hasCallback) {
                            // Handle callback action (callback was stripped but hasCallback flag remains)
                            const icon = actionConfig.icon || 'link';
                            const title = actionConfig.title || '';
                            const className = actionConfig.class || 'btn-custom';
                            const confirm = actionConfig.confirm || '';

                            if (this.theme === 'uikit') {
                                html += '<a href="#" class="uk-icon-link ' + className + '" uk-icon="' + icon + '" title="' + title + '" uk-tooltip="' + title + '"';
                            } else {
                                html += '<a href="#" class="' + iconLinkClass + ' ' + className + '" title="' + title + '"';
                            }
                            html += ' data-action="' + actionKey + '"';
                            html += ' data-id="' + rowId + '"';
                            html += ' data-confirm="' + confirm + '"';
                            html += ' onclick="DataTables.executeActionCallback(\'' + actionKey + '\', ' + rowId + ', event)"';
                            html += '>';
                            if (this.theme !== 'uikit') {
                                html += this.renderIcon(icon);
                            }
                            html += '</a>';
                        } else if (actionConfig.href !== undefined || actionConfig.icon !== undefined) {
                            // Handle link-based action
                            const icon = replacePlaceholders(actionConfig.icon || 'link');
                            const title = replacePlaceholders(actionConfig.title || '');
                            const className = replacePlaceholders(actionConfig.class || 'btn-custom');
                            const href = replacePlaceholders(actionConfig.href || '#');
                            const onclick = replacePlaceholders(actionConfig.onclick || '');
                            const attributes = actionConfig.attributes || {};

                            if (this.theme === 'uikit') {
                                html += '<a href="' + href + '" class="uk-icon-link ' + className + '" uk-icon="' + icon + '" title="' + title + '" uk-tooltip="' + title + '"';
                            } else {
                                html += '<a href="' + href + '" class="' + iconLinkClass + ' ' + className + '" title="' + title + '"';
                            }
                            if (onclick) {
                                html += ' onclick="' + onclick + '"';
                            }

                            // Add custom attributes
                            for (const [attrName, attrValue] of Object.entries(attributes)) {
                                const processedValue = replacePlaceholders(String(attrValue));
                                html += ' ' + attrName + '="' + processedValue + '"';
                            }

                            html += '>';
                            if (this.theme !== 'uikit') {
                                html += this.renderIcon(icon);
                            }
                            html += '</a>';
                        }

                        // Check for action-level html after
                        if (actionConfig.html && typeof actionConfig.html === 'object' && actionConfig.html.location && actionConfig.html.content) {
                            if (actionConfig.html.location === 'after' || actionConfig.html.location === 'both') {
                                html += replacePlaceholders(actionConfig.html.content);
                            }
                        }
                    });

                    // Handle all html* keys with location 'after' last
                    Object.keys(group).filter(key => key.startsWith('html')).forEach(htmlKey => {
                        const htmlConfig = group[htmlKey];
                        if (typeof htmlConfig === 'object' && htmlConfig.location && htmlConfig.content) {
                            if (htmlConfig.location === 'after' || htmlConfig.location === 'both') {
                                html += replacePlaceholders(htmlConfig.content);
                            }
                        }
                    });
                }
            });
        } else {
            // Fallback to default buttons if no groups configured
            if (this.actionConfig.show_edit !== false) {
                if (this.theme === 'uikit') {
                    html += '<a href="#" class="uk-icon-link btn-edit uk-margin-tiny-full" uk-icon="pencil" title="Edit Record" uk-tooltip="Edit Record"></a>';
                } else {
                    html += `<a href="#" class="${iconLinkClass} btn-edit" title="Edit Record">${this.renderIcon('pencil')}</a>`;
                }
            }
            if (this.actionConfig.show_delete !== false) {
                if (this.theme === 'uikit') {
                    html += '<a href="#" class="uk-icon-link btn-delete uk-margin-tiny-full" uk-icon="trash" title="Delete Record" uk-tooltip="Delete Record"></a>';
                } else {
                    html += `<a href="#" class="${iconLinkClass} btn-delete" title="Delete Record">${this.renderIcon('trash')}</a>`;
                }
            }
        }

        return html;
    }

    // === PAGINATION ===
    renderInfo(data) {
        const start = (data.page - 1) * data.per_page + 1;
        const end = Math.min(start + data.per_page - 1, data.total);
        const infoText = `Showing ${start} to ${end} of ${data.total} records`;

        document.querySelectorAll('.datatables-info').forEach(info => {
            info.textContent = infoText;
        });
    }

    renderPagination(data) {
        if (data.total_pages <= 1) {
            document.querySelectorAll('.datatables-pagination').forEach(pagination => {
                pagination.innerHTML = '';
            });
            return;
        }

        let html = '';
        const currentPage = parseInt(data.page);
        const totalPages = parseInt(data.total_pages);
        const disabledClass = this.getThemeClass('pagination.disabled');
        const activeClass = this.getThemeClass('pagination.active');

        if (this.theme === 'bootstrap') {
            // Bootstrap pagination structure
            html += `<li class="page-item${currentPage === 1 ? ' disabled' : ''}">`;
            html += `<a class="page-link" ${currentPage === 1 ? '' : `onclick="DataTables.goToPage(1)"`} title="First Page">&laquo;&laquo;</a></li>`;

            html += `<li class="page-item${currentPage === 1 ? ' disabled' : ''}">`;
            html += `<a class="page-link" ${currentPage === 1 ? '' : `onclick="DataTables.goToPage(${currentPage - 1})"`} title="Previous Page">&laquo;</a></li>`;

            if (currentPage > 2) {
                html += '<li class="page-item"><a class="page-link" onclick="DataTables.goToPage(1)">1</a></li>';
                if (currentPage > 3) {
                    html += '<li class="page-item disabled"><span class="page-link">...</span></li>';
                }
            }

            const start = Math.max(1, currentPage - 1);
            const end = Math.min(totalPages, currentPage + 1);
            for (let i = start; i <= end; i++) {
                html += `<li class="page-item${i === currentPage ? ' active' : ''}">`;
                html += `<a class="page-link" ${i === currentPage ? '' : `onclick="DataTables.goToPage(${i})"`}>${i}</a></li>`;
            }

            if (currentPage < totalPages - 1) {
                if (currentPage < totalPages - 2) {
                    html += '<li class="page-item disabled"><span class="page-link">...</span></li>';
                }
                html += `<li class="page-item"><a class="page-link" onclick="DataTables.goToPage(${totalPages})">${totalPages}</a></li>`;
            }

            html += `<li class="page-item${currentPage === totalPages ? ' disabled' : ''}">`;
            html += `<a class="page-link" ${currentPage === totalPages ? '' : `onclick="DataTables.goToPage(${currentPage + 1})"`} title="Next Page">&raquo;</a></li>`;

            html += `<li class="page-item${currentPage === totalPages ? ' disabled' : ''}">`;
            html += `<a class="page-link" ${currentPage === totalPages ? '' : `onclick="DataTables.goToPage(${totalPages})"`} title="Last Page">&raquo;&raquo;</a></li>`;

        } else if (this.theme === 'uikit') {
            // UIKit pagination structure
            html += `<li${currentPage === 1 ? ' class="uk-disabled"' : ''}>`;
            html += `<a ${currentPage === 1 ? '' : ` onclick="DataTables.goToPage(1)"`} title="First Page">`;
            html += '<span uk-icon="chevron-double-left"></span></a></li>';

            html += `<li${currentPage === 1 ? ' class="uk-disabled"' : ''}>`;
            html += `<a ${currentPage === 1 ? '' : ` onclick="DataTables.goToPage(${currentPage - 1})"`} title="Previous Page">`;
            html += '<span uk-pagination-previous></span></a></li>';

            if (currentPage > 2) {
                html += '<li><a onclick="DataTables.goToPage(1)">1</a></li>';
                if (currentPage > 3) {
                    html += '<li class="uk-disabled"><span>...</span></li>';
                }
            }

            const start = Math.max(1, currentPage - 1);
            const end = Math.min(totalPages, currentPage + 1);
            for (let i = start; i <= end; i++) {
                html += `<li${i === currentPage ? ' class="uk-active"' : ''}>`;
                html += `<a ${i === currentPage ? '' : ` onclick="DataTables.goToPage(${i})"`}>${i}</a></li>`;
            }

            if (currentPage < totalPages - 1) {
                if (currentPage < totalPages - 2) {
                    html += '<li class="uk-disabled"><span>...</span></li>';
                }
                html += `<li><a onclick="DataTables.goToPage(${totalPages})">${totalPages}</a></li>`;
            }

            html += `<li${currentPage === totalPages ? ' class="uk-disabled"' : ''}>`;
            html += `<a ${currentPage === totalPages ? '' : ` onclick="DataTables.goToPage(${currentPage + 1})"`} title="Next Page">`;
            html += '<span uk-pagination-next></span></a></li>';

            html += `<li${currentPage === totalPages ? ' class="uk-disabled"' : ''}>`;
            html += `<a ${currentPage === totalPages ? '' : ` onclick="DataTables.goToPage(${totalPages})"`} title="Last Page">`;
            html += '<span uk-icon="chevron-double-right"></span></a></li>';

        } else {
            // Plain/Tailwind pagination structure
            html += `<li${currentPage === 1 ? ` class="${disabledClass}"` : ''}>`;
            html += `<a ${currentPage === 1 ? '' : `onclick="DataTables.goToPage(1)"`} title="First Page">${this.renderIcon('chevron-double-left')}</a></li>`;

            html += `<li${currentPage === 1 ? ` class="${disabledClass}"` : ''}>`;
            html += `<a ${currentPage === 1 ? '' : `onclick="DataTables.goToPage(${currentPage - 1})"`} title="Previous Page">&laquo;</a></li>`;

            if (currentPage > 2) {
                html += '<li><a onclick="DataTables.goToPage(1)">1</a></li>';
                if (currentPage > 3) {
                    html += `<li class="${disabledClass}"><span>...</span></li>`;
                }
            }

            const start = Math.max(1, currentPage - 1);
            const end = Math.min(totalPages, currentPage + 1);
            for (let i = start; i <= end; i++) {
                html += `<li${i === currentPage ? ` class="${activeClass}"` : ''}>`;
                html += `<a ${i === currentPage ? '' : `onclick="DataTables.goToPage(${i})"`}>${i}</a></li>`;
            }

            if (currentPage < totalPages - 1) {
                if (currentPage < totalPages - 2) {
                    html += `<li class="${disabledClass}"><span>...</span></li>`;
                }
                html += `<li><a onclick="DataTables.goToPage(${totalPages})">${totalPages}</a></li>`;
            }

            html += `<li${currentPage === totalPages ? ` class="${disabledClass}"` : ''}>`;
            html += `<a ${currentPage === totalPages ? '' : `onclick="DataTables.goToPage(${currentPage + 1})"`} title="Next Page">&raquo;</a></li>`;

            html += `<li${currentPage === totalPages ? ` class="${disabledClass}"` : ''}>`;
            html += `<a ${currentPage === totalPages ? '' : `onclick="DataTables.goToPage(${totalPages})"`} title="Last Page">${this.renderIcon('chevron-double-right')}</a></li>`;
        }

        document.querySelectorAll('.datatables-pagination').forEach(pagination => {
            pagination.innerHTML = html;
        });
    }

    goToPage(page) {
        this.currentPage = page;
        this.loadData();
    }

    updateSortIcons() {
        document.querySelectorAll('.sort-icon').forEach(
            icon => {
                if (this.theme === 'uikit') {
                    icon.setAttribute('uk-icon', 'triangle-up');
                } else {
                    icon.innerHTML = this.renderIcon('triangle-up');
                }
            }
        );

        const currentSortHeaders = document.querySelectorAll(`th[data-sort="${this.sortColumn}"] .sort-icon`);
        currentSortHeaders.forEach(sortIcon => {
            if (sortIcon) {
                const iconName = this.sortDirection === 'ASC' ? 'triangle-up' : 'triangle-down';
                if (this.theme === 'uikit') {
                    sortIcon.setAttribute('uk-icon', iconName);
                } else {
                    sortIcon.innerHTML = this.renderIcon(iconName);
                }
            }
        });
    }

    // === BULK ACTIONS ===
    toggleSelectAll(checkbox) {
        const rowCheckboxes = document.querySelectorAll('.row-checkbox');
        rowCheckboxes.forEach(
            cb => {
                cb.checked = checkbox.checked;
                this.toggleRowSelection(cb);
            }
        );
    }

    toggleRowSelection(checkbox) {
        const rowId = checkbox.value;
        if (checkbox.checked) {
            this.selectedIds.add(rowId);
        } else {
            this.selectedIds.delete(rowId);
            // Uncheck "select all" if not all rows are selected
            const selectAllCheckbox = document.querySelector('.datatables-select-all');
            if (selectAllCheckbox) {
                selectAllCheckbox.checked = false;
            }
        }
        this.updateBulkActionButtons();
    }

    updateBulkActionButtons() {
        const hasSelection = this.selectedIds.size > 0;

        // Update all bulk action buttons
        document.querySelectorAll('.datatables-bulk-action-btn').forEach(btn => {
            btn.disabled = !hasSelection;
        });
    }

    executeBulkActionDirect(action, event) {


        if (event) {
            event.preventDefault();
        }
        const selectedIds = Array.from(this.selectedIds);

        if (selectedIds.length === 0) {
            this.showNotification('No records selected', 'warning');
            return;
        }

        // Check if action requires confirmation
        const actionButton = document.querySelector(`[data-action="${action}"]`);
        const confirmMessage = actionButton ? actionButton.getAttribute('data-confirm') : '';

        if (confirmMessage) {
            this.showConfirm(confirmMessage).then(
                () => {
                    this.performBulkAction(action, selectedIds);
                },
                () => {
                    // User cancelled
                }
            );
        } else {
            this.performBulkAction(action, selectedIds);
        }
    }

    executeActionCallback(action, rowId, event) {
        if (event) {
            event.preventDefault();
        }

        // Get row data
        const rowData = window.DataTablesRowData ? window.DataTablesRowData[rowId] : {};

        // Find the action configuration
        let actionConfig = null;
        if (this.actionConfig.groups) {
            for (const group of this.actionConfig.groups) {
                if (typeof group === 'object' && !Array.isArray(group)) {
                    if (group[action] && group[action].hasCallback) {
                        actionConfig = group[action];
                        break;
                    }
                }
            }
        }

        if (!actionConfig) {
            return;
        }

        // Check if confirmation is required
        if (actionConfig.confirm) {
            this.showConfirm(actionConfig.confirm).then(
                () => {
                    this.performActionCallback(action, rowId, rowData, actionConfig);
                },
                () => {
                    // User cancelled
                }
            );
        } else {
            this.performActionCallback(action, rowId, rowData, actionConfig);
        }
    }

    performActionCallback(action, rowId, rowData, actionConfig) {
        const formData = new FormData();
        formData.append('action', 'action_callback');
        formData.append('action_name', action);
        formData.append('row_id', rowId);
        formData.append('row_data', JSON.stringify(rowData));

        fetch(
            window.location.href, {
            method: 'POST',
            body: formData
        }
        )
            .then(response => response.json())
            .then(
                data => {
                    if (data.success) {
                        this.loadData();
                        this.showNotification(data.message || actionConfig.success_message || 'Action completed', 'success');
                    } else {
                        this.showNotification(data.message || actionConfig.error_message || 'Action failed', 'danger');
                    }
                }
            )
            .catch(
                error => {
                    console.error('Error:', error);
                    this.showNotification('An error occurred', 'danger');
                }
            );
    }

    /**
     * Reset search functionality
     */
    resetSearch() {
        // Clear search input
        document.querySelectorAll('.datatables-search').forEach(searchInput => {
            searchInput.value = '';
        });

        // Reset search state and reload data
        this.search = '';
        this.currentPage = 1;
        this.loadData();
    }

    executeBulkAction() {
        const bulkSelect = document.querySelector('.datatables-bulk-action');
        if (!bulkSelect || !bulkSelect.value) {
            return;
        }

        const action = bulkSelect.value;
        const selectedIds = Array.from(this.selectedIds);

        if (selectedIds.length === 0) {
            this.showNotification('No records selected', 'warning');
            return;
        }

        // Check if action requires confirmation
        const actionConfig = this.bulkActions[action];
        if (actionConfig && actionConfig.confirm) {
            this.showConfirm(actionConfig.confirm).then(
                () => {
                    this.performBulkAction(action, selectedIds);
                },
                () => {
                    // User cancelled
                }
            );
        } else {
            this.performBulkAction(action, selectedIds);
        }
    }

    performBulkAction(action, selectedIds) {
        const formData = new FormData();
        formData.append('action', 'bulk_action');
        formData.append('bulk_action', action);
        formData.append('selected_ids', JSON.stringify(selectedIds));

        fetch(
            window.location.href, {
            method: 'POST',
            body: formData
        }
        )
            .then(response => response.json())
            .then(
                data => {
                    if (data.success) {
                        this.selectedIds.clear();
                        this.loadData();
                        this.showNotification(data.message || 'Bulk action completed', 'success');

                        // Reset bulk action controls
                        const bulkSelect = document.querySelector('.datatables-bulk-action');
                        if (bulkSelect) {
                            bulkSelect.value = '';
                        }

                        const selectAll = document.querySelector('.datatables-select-all');
                        if (selectAll) {
                            selectAll.checked = false;
                        }

                        this.updateBulkActionButtons();
                    } else {
                        this.showNotification(data.message || 'Bulk action failed', 'danger');
                    }
                }
            )
            .catch(
                error => {
                    console.error('Error:', error);
                    this.showNotification('An error occurred', 'danger');
                }
            );
    }

    // === FORM MODALS ===
    showAddModal(event) {
        if (event) {
            event.preventDefault();
        }
        this.showModal('add-modal');
    }

    showEditModal(id) {
        this.loadRecordForEdit(id);
        this.showModal('edit-modal');
    }

    showDeleteModal(id) {
        this.deleteId = id;
        this.showModal('delete-modal');
    }

    loadRecordForEdit(id) {
        // Fetch complete record data via AJAX instead of parsing table cells
        const params = new URLSearchParams({
            action: 'fetch_record',
            id: id
        });

        fetch('?' + params.toString())
            .then(response => response.json())
            .then(data => {
                if (data.success && data.data) {
                    // Populate all form fields with the fetched data
                    this.populateEditForm(data.data);
                } else {
                    console.error('Failed to fetch record:', data.message);
                    this.showNotification(data.message || 'Failed to fetch record data', 'danger');
                }
            })
            .catch(error => {
                console.error('Error fetching record:', error);
                this.showNotification('Error fetching record data', 'danger');
            });
    }

    populateEditForm(recordData) {
        // Get unqualified primary key name
        let unqualifiedPK = this.primaryKey;
        if (this.primaryKey.includes('.')) {
            unqualifiedPK = this.primaryKey.split('.')[1];
        }

        // Set the primary key field - try both qualified and unqualified
        let pkValue = recordData[this.primaryKey] || recordData[unqualifiedPK] || recordData['s.id'] || recordData['id'] || '';

        const pkField = document.getElementById(`edit-${unqualifiedPK}`);
        if (pkField) {
            pkField.value = pkValue;
        }

        // Get all form fields in the edit form
        const editForm = document.getElementById('edit-form');
        if (!editForm) return;

        // Find all form inputs, selects, and textareas
        const formElements = editForm.querySelectorAll('input, select, textarea');

        formElements.forEach(element => {
            const fieldName = element.name;

            // Skip if no field name or if it's the primary key (already handled)
            if (!fieldName || fieldName === unqualifiedPK) return;

            // Get value from record data
            const value = recordData[fieldName];

            if (value !== undefined && value !== null) {
                if (element.type === 'checkbox') {
                    element.checked = value == '1' || value === 'true' || value === true;
                } else if (element.type === 'radio') {
                    element.checked = element.value === String(value);
                } else {
                    // For select2 fields, add the option before setting value
                    if (element.hasAttribute('data-select2')) {
                        element.innerHTML = `<option value="${value}" selected>${value}</option>`;
                    }
                    element.value = value;
                }
            } else {
                // Clear field if no value found
                if (element.type === 'checkbox' || element.type === 'radio') {
                    element.checked = false;
                } else {
                    element.value = '';
                }
            }
        });

        // Update Select2 fields with record data for query parameter substitution
        const select2Fields = editForm.querySelectorAll('select[data-select2]');
        if (select2Fields.length > 0) {
            const recordDataJson = JSON.stringify(recordData);
            select2Fields.forEach(field => {
                field.setAttribute('data-record-data', recordDataJson);

                // Re-initialize Select2 with new record data if already initialized
                const existingInstance = field.kptSelect2Instance;
                if (existingInstance && field.value) {
                    existingInstance.config.recordData = recordData;
                    existingInstance.selectedValue = field.value;
                    existingInstance.loadInitialValue();
                }
            });
        }
    }

    submitAddForm(event) {
        event.preventDefault();
        const form = event.target;
        const formData = new FormData(form);
        formData.append('action', 'add_record');

        this.submitForm(formData, form, 'add-modal', 'Record added successfully');
        return false;
    }

    submitEditForm(event) {
        event.preventDefault();
        const form = event.target;
        const formData = new FormData(form);
        formData.append('action', 'edit_record');

        this.submitForm(formData, null, 'edit-modal', 'Record updated successfully');
        return false;
    }

    submitForm(formData, form, modalId, successMessage) {
        formData.append('table', this.tableName);

        fetch(
            window.location.href, {
            method: 'POST',
            body: formData
        }
        )
            .then(response => response.json())
            .then(
                data => {
                    if (data.success) {
                        this.hideModal(modalId);
                        if (form) {
                            form.reset();
                        }
                        this.loadData();
                        this.showNotification(successMessage, 'success');
                    } else {
                        this.showNotification(data.message || 'Operation failed', 'danger');
                    }
                }
            )
            .catch(
                error => {
                    console.error('Error:', error);
                    this.showNotification('An error occurred', 'danger');
                }
            );
    }

    confirmDelete() {
        if (!this.deleteId) {
            return;
        }

        const formData = new FormData();
        formData.append('action', 'delete_record');
        formData.append('id', this.deleteId);

        fetch(
            window.location.href, {
            method: 'POST',
            body: formData
        }
        )
            .then(response => response.json())
            .then(
                data => {
                    if (data.success) {
                        this.hideModal('delete-modal');
                        this.loadData();
                        this.showNotification('Record deleted successfully', 'success');
                    } else {
                        this.showNotification(data.message || 'Failed to delete record', 'danger');
                    }
                }
            )
            .catch(
                error => {
                    console.error('Error:', error);
                    this.showNotification('An error occurred', 'danger');
                }
            );

        this.deleteId = null;
    }

    bindTableEvents() {
        // Edit buttons
        document.querySelectorAll('.btn-edit').forEach(
            btn => {
                btn.addEventListener(
                    'click', (e) => {
                        e.preventDefault();
                        const id = e.target.closest('tr').getAttribute('data-id');
                        this.showEditModal(id);
                    }
                );
            }
        );

        // Delete buttons
        document.querySelectorAll('.btn-delete').forEach(
            btn => {
                btn.addEventListener(
                    'click', (e) => {
                        e.preventDefault();
                        const id = e.target.closest('tr').getAttribute('data-id');
                        this.showDeleteModal(id);
                    }
                );
            }
        );

        // Inline edit for regular fields - improved selector
        document.querySelectorAll('td .inline-editable:not(.boolean-toggle)').forEach(
            span => {
                span.addEventListener(
                    'click', (e) => {
                        e.preventDefault();
                        e.stopPropagation();
                        // For image fields, the target might be the img element, so use closest span
                        const editableElement = e.target.closest('.inline-editable');
                        this.startInlineEdit(editableElement);
                    }
                );
            }
        );

        // Boolean toggle for boolean fields - improved selector
        document.querySelectorAll('td .boolean-toggle').forEach(
            span => {
                span.addEventListener(
                    'click', (e) => {
                        e.preventDefault();
                        e.stopPropagation();
                        this.toggleBoolean(e.target.closest('.boolean-toggle'));
                    }
                );
            }
        );

        // Clickable rows
        document.querySelectorAll('tr.row-select').forEach(row => {
            row.addEventListener('click', (e) => {
                const clickedTd = e.target.closest('td');
                if (clickedTd && !clickedTd.classList.contains('row-check') &&
                    !clickedTd.classList.contains('row-action') &&
                    !clickedTd.classList.contains('cell-edit')) {

                    const checkbox = row.querySelector('.row-checkbox');
                    if (checkbox) {
                        checkbox.checked = !checkbox.checked;
                        this.toggleRowSelection(checkbox);
                    }
                }
            });
        });

    }

    startInlineEdit(element) {
        // Prevent multiple edits on the same cell
        if (element.querySelector('input, select, textarea')) {
            return;
        }

        const field = element.getAttribute('data-field');
        const id = element.getAttribute('data-id');
        const fieldType = element.getAttribute('data-type') || 'text';
        // Get current value - check for stored data-value first, then fallback to text content
        const currentValue = element.getAttribute('data-value') || element.textContent;

        if (!this.inlineEditableColumns.includes(field)) { return; }

        const inputClass = this.getThemeClass('input');
        const selectClass = this.getThemeClass('select');
        const textareaClass = this.getThemeClass('textarea');
        const buttonPrimaryClass = this.getThemeClass('button.primary');
        const buttonDefaultClass = this.getThemeClass('button.default');
        const buttonSmallClass = this.getThemeClass('button.small');
        const flexRightClass = this.getThemeClass('flex.right');
        const marginSmallTopClass = this.getThemeClass('margin.smallTop');
        const marginSmallRightClass = this.getThemeClass('margin.smallRight');
        const marginSmallBottomClass = this.getThemeClass('margin.smallBottom');
        const roundedClass = this.getThemeClass('border.rounded');
        const displayBlockClass = this.getThemeClass('display.block');

        // Get schema information for options
        const tableElement = document.querySelector('.datatables-table');
        const tableSchema = tableElement ? JSON.parse(tableElement.dataset.columns || '{}') : {};


        let inputElement;

        // Create appropriate input based on field type
        switch (fieldType) {
            case 'select':
                const options = tableSchema[field]?.form_options || {};

                inputElement = document.createElement('select');
                inputElement.className = selectClass;

                // Add options
                for (const [value, label] of Object.entries(options)) {
                    const option = document.createElement('option');
                    option.value = value;
                    option.textContent = label;
                    if (value === currentValue) {
                        option.selected = true;
                    }
                    inputElement.appendChild(option);
                }
                break;

            case 'select2':
                const query = tableSchema[field]?.select2_query || '';
                const minChars = 0;
                const maxResults = tableSchema[field]?.select2_max_results || 50;
                let originalLabel = currentValue;

                if (!query) {
                    console.error('No query configured for select2 field:', field);
                    element.textContent = currentValue;
                    return;
                }

                const selectEl = document.createElement('select');
                selectEl.className = selectClass;
                selectEl.setAttribute('data-select2', 'true');
                selectEl.setAttribute('data-query', query);
                selectEl.setAttribute('data-placeholder', 'Select...');
                selectEl.setAttribute('data-min-search-chars', minChars);
                selectEl.setAttribute('data-max-results', maxResults);
                selectEl.setAttribute('data-theme', this.theme);
                selectEl.innerHTML = `<option value="${currentValue}" selected>Loading...</option>`;
                selectEl.value = currentValue;

                element.innerHTML = '';
                element.appendChild(selectEl);

                setTimeout(() => {

                    if (typeof window.KPTSelect2 === 'function') {
                        const config = {
                            placeholder: selectEl.getAttribute('data-placeholder') || 'Select...',
                            query: selectEl.getAttribute('data-query') || '',
                            minSearchChars: selectEl.getAttribute('data-min-search-chars') || 0,
                            maxResults: selectEl.getAttribute('data-max-results') || 50,
                            theme: selectEl.getAttribute('data-theme') || 'uikit',
                            recordData: {}
                        };

                        new KPTSelect2(selectEl, config);

                        // Add change listener AFTER instance is created
                        selectEl.addEventListener('change', () => {
                            const newValue = selectEl.value;
                            if (newValue !== currentValue) {
                                this.saveInlineEdit(id, field, newValue, element);
                            } else {
                                if (selectEl.kptSelect2Instance) {
                                    element.textContent = selectEl.kptSelect2Instance.selectedLabel || currentValue;
                                } else {
                                    element.textContent = currentValue;
                                }
                            }
                        });

                        // Override close method to restore if no change
                        const instance = selectEl.kptSelect2Instance;
                        const originalClose = instance.close.bind(instance);
                        instance.close = function () {
                            const valueChanged = selectEl.value !== currentValue;
                            originalClose();

                            if (!valueChanged) {
                                // Restore original cell content
                                element.textContent = originalLabel;
                            }
                        };
                    } else {
                        console.error('KPTSelect2 class not found!');
                    }

                    setTimeout(() => {
                        if (selectEl.kptSelect2Instance) {
                            selectEl.kptSelect2Instance.selectedValue = currentValue;
                            selectEl.kptSelect2Instance.loadInitialValue();

                            // Store the label once loaded
                            setTimeout(() => {
                                originalLabel = selectEl.kptSelect2Instance.selectedLabel || currentValue;
                            }, 25);

                            setTimeout(() => {
                                selectEl.kptSelect2Instance.open();
                            }, 50);
                        } else {
                            console.error('NO INSTANCE CREATED');
                        }
                    }, 100);
                }, 150);

                return;

            case 'textarea':
                inputElement = document.createElement('textarea');
                inputElement.className = textareaClass;
                inputElement.value = currentValue;
                break;

            case 'number':
                inputElement = document.createElement('input');
                inputElement.type = 'number';
                inputElement.className = inputClass;
                inputElement.value = currentValue;
                break;

            case 'date':
                inputElement = document.createElement('input');
                inputElement.type = 'date';
                inputElement.className = inputClass;
                inputElement.value = currentValue;
                break;

            case 'datetime-local':
                inputElement = document.createElement('input');
                inputElement.type = 'datetime-local';
                inputElement.className = inputClass;
                inputElement.value = currentValue;
                break;

            case 'image':
                // Create container for image editing
                const container = document.createElement('div');
                container.style.minWidth = '200px';

                // Current image preview
                if (currentValue && currentValue.trim()) {
                    const imageSrc = currentValue.startsWith('http') ? currentValue : `/uploads/${currentValue}`;
                    const preview = document.createElement('img');
                    preview.src = imageSrc;
                    preview.style.maxWidth = '100px';
                    preview.style.maxHeight = '100px';
                    preview.style.objectFit = 'cover';
                    preview.className = `${roundedClass} ${marginSmallBottomClass} ${displayBlockClass}`;
                    container.appendChild(preview);
                }

                // URL input
                const urlInput = document.createElement('input');
                urlInput.type = 'url';
                urlInput.className = `${inputClass} ${marginSmallBottomClass}`;
                urlInput.placeholder = 'Enter image URL or upload file';
                urlInput.value = currentValue.startsWith('http') ? currentValue : '';

                // File input container
                const fileContainer = document.createElement('div');
                fileContainer.className = marginSmallBottomClass;

                const fileInput = document.createElement('input');
                fileInput.type = 'file';
                fileInput.className = inputClass;
                fileInput.accept = 'image/*';

                // Action buttons
                const buttonContainer = document.createElement('div');
                buttonContainer.className = `${flexRightClass} ${marginSmallTopClass}`;

                const saveBtn = document.createElement('button');
                saveBtn.className = `${buttonPrimaryClass} ${buttonSmallClass} ${marginSmallRightClass}`;
                saveBtn.textContent = 'Save';
                saveBtn.type = 'button';

                const cancelBtn = document.createElement('button');
                cancelBtn.className = `${buttonDefaultClass} ${buttonSmallClass}`;
                cancelBtn.textContent = 'Cancel';
                cancelBtn.type = 'button';

                fileContainer.appendChild(fileInput);
                buttonContainer.appendChild(saveBtn);
                buttonContainer.appendChild(cancelBtn);

                container.appendChild(urlInput);
                container.appendChild(fileContainer);
                container.appendChild(buttonContainer);

                // Save function
                const saveImageEdit = () => {
                    const urlValue = urlInput.value.trim();
                    const fileValue = fileInput.files[0];

                    if (fileValue) {
                        // Upload file
                        const formData = new FormData();
                        formData.append('action', 'upload_file');
                        formData.append('file', fileValue);
                        formData.append('prepend', element.getAttribute('data-prepend') || '');

                        fetch(window.location.href, {
                            method: 'POST',
                            body: formData
                        })
                            .then(response => response.json())
                            .then(data => {
                                if (data.success) {
                                    this.saveInlineEdit(id, field, data.file_name, element);
                                } else {
                                    cancelImageEdit();
                                    this.showNotification(data.message || 'Upload failed', 'danger');
                                }
                            })
                            .catch(error => {
                                cancelImageEdit();
                                this.showNotification('Upload error', 'danger');
                            });
                    } else if (urlValue !== currentValue) {
                        this.saveInlineEdit(id, field, urlValue, element);
                    } else {
                        cancelImageEdit();
                    }
                };

                const cancelImageEdit = () => {
                    if (currentValue && currentValue.trim()) {
                        const imageSrc = currentValue.startsWith('http') ? currentValue : `/uploads/${currentValue}`;
                        element.innerHTML = `<img src="${imageSrc}" alt="Image" style="max-width: 50px; max-height: 50px; object-fit: cover;" class="${roundedClass}">`;
                    } else {
                        element.innerHTML = 'No image';
                    }
                };

                // Event listeners
                saveBtn.addEventListener('click', saveImageEdit);
                cancelBtn.addEventListener('click', cancelImageEdit);

                urlInput.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        saveImageEdit();
                    } else if (e.key === 'Escape') {
                        e.preventDefault();
                        cancelImageEdit();
                    }
                });

                // Replace content and focus
                element.innerHTML = '';
                element.appendChild(container);
                urlInput.focus();
                return; // Exit early since we handle everything custom

            default: // text, email, etc.
                inputElement = document.createElement('input');
                inputElement.type = fieldType === 'email' ? 'email' : 'text';
                inputElement.className = inputClass;
                inputElement.value = currentValue;
        }

        const saveEdit = () => {
            const newValue = inputElement.value;
            if (newValue !== currentValue) {
                this.saveInlineEdit(id, field, newValue, element);
            } else {
                element.textContent = currentValue;
            }
        };

        const cancelEdit = () => {
            element.textContent = currentValue;
        };

        inputElement.addEventListener('blur', saveEdit);
        inputElement.addEventListener(
            'keydown', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    saveEdit();
                } else if (e.key === 'Escape') {
                    e.preventDefault();
                    cancelEdit();
                }
            }
        );

        element.textContent = '';
        element.appendChild(inputElement);
        inputElement.focus();
        if (inputElement.select) {
            inputElement.select();
        }
    }

    toggleBoolean(element) {
        const field = element.getAttribute('data-field');
        const id = element.getAttribute('data-id');

        // Get current value from the data attribute
        const currentValue = element.getAttribute('data-value');
        const isCurrentlyActive = currentValue == '1' || currentValue === 'true' || currentValue === true;
        const newValue = isCurrentlyActive ? '0' : '1';

        this.saveInlineEdit(id, field, newValue, element);
    }

    saveInlineEdit(id, field, value, element) {
        const formData = new FormData();
        formData.append('action', 'inline_edit');
        formData.append('id', id);
        formData.append('field', field);
        formData.append('value', value);

        const roundedClass = this.getThemeClass('border.rounded');
        const successClass = this.getThemeClass('icon.success');
        const dangerClass = this.getThemeClass('icon.danger');

        fetch(
            window.location.href, {
            method: 'POST',
            body: formData
        }
        )
            .then(response => response.json())
            .then(
                data => {
                    if (data.success) {

                        // reload the table data
                        this.loadData();

                        // Handle image fields differently
                        if (element.getAttribute('data-type') === 'image') {
                            if (value && value.trim()) {
                                const imageSrc = value.startsWith('http') ? value : `/uploads/${value}`;
                                element.innerHTML = `<img src="${imageSrc}" alt="Image" style="max-width: 50px; max-height: 50px; object-fit: cover;" class="${roundedClass}">`;
                                element.setAttribute('data-value', value);
                            } else {
                                element.innerHTML = 'No image';
                                element.setAttribute('data-value', '');
                            }
                        } else if (element.classList.contains('boolean-toggle')) {
                            const isActive = value == '1' || value === 'true' || value === true;
                            const iconClass = isActive ? successClass : dangerClass;

                            element.innerHTML = this.renderIcon(isActive ? 'check' : 'close', iconClass);
                            element.setAttribute('data-value', value);
                        } else if (element.getAttribute('data-type') === 'select') {

                            // Handle select fields - show label but store value
                            const tableElement = document.querySelector('.datatables-table');
                            const tableSchema = tableElement ? JSON.parse(tableElement.dataset.columns || '{}') : {};
                            const field = element.getAttribute('data-field');
                            const selectOptions = tableSchema[field]?.form_options || {};
                            const valueStr = String(value);
                            const displayLabel = valueStr in selectOptions ? selectOptions[valueStr] : value;


                            element.setAttribute('data-value', value);
                            element.textContent = displayLabel;
                        } else {
                            element.textContent = value;
                        }

                        // Update edit form if it's open and has this field
                        const editForm = document.getElementById(`edit-${field}`);
                        if (editForm) {
                            if (editForm.type === 'checkbox') {
                                editForm.checked = value === '1' || value === 'true' || value === true;
                            } else {
                                editForm.value = value;
                            }
                        }

                        this.showNotification('Field updated successfully', 'success');
                    } else {
                        element.textContent = element.getAttribute('data-original') || '';
                        this.showNotification(data.message || 'Failed to update field', 'danger');
                    }
                }
            )
            .catch(
                error => {
                    console.error('Error:', error);
                    element.textContent = element.getAttribute('data-original') || '';
                    this.showNotification('An error occurred', 'danger');
                }
            );
    }

    // === UTILITY METHODS ===
    getColumnCount() {
        // Calculate total columns including actions and bulk selection
        let count = Object.keys(this.columns).length || 1;
        count++; // Actions column
        if (this.bulkActionsEnabled) {
            count++; // Bulk selection column
        }
        return count;
    }

    changePageSize(newSize, event) {

        if (event) {
            event.preventDefault();
        }

        this.perPage = parseInt(newSize);
        this.currentPage = 1;

        const primaryClass = this.getThemeClass('button.primary');
        const defaultClass = this.getThemeClass('button.default');

        // Update button group active states
        document.querySelectorAll('.datatables-page-size-btn').forEach(btn => {
            const btnSize = parseInt(btn.getAttribute('data-size'));
            if (btnSize === this.perPage) {
                btn.className = btn.className.replace(defaultClass, primaryClass);
            } else {
                btn.className = btn.className.replace(primaryClass, defaultClass);
            }
        });

        // Also sync select dropdowns if present
        document.querySelectorAll('.datatables-page-size').forEach(select => {
            select.value = newSize;
        });

        this.loadData();
    }

    getRowClass(rowId) {
        // Use configured row class base or default
        const baseClass = this.cssClasses?.tr || 'datatables-row';
        return baseClass ? `${baseClass}-${rowId}` : '';
    }
}

// Make DataTables available globally
window.DataTablesJS = DataTablesJS;
