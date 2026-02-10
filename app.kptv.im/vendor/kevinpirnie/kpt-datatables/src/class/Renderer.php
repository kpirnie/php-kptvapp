<?php

declare(strict_types=1);

namespace KPT;

// Check if class already exists before declaring it
if (! class_exists('KPT\Renderer', false)) {

    /**
     * Renderer - HTML Rendering Engine for DataTables
     *
     * Responsible for generating all HTML output for the DataTables component
     * including the table structure, modals, forms, pagination, search,
     * bulk actions, aggregation footer rows, and JavaScript initialization.
     * Extends DataTablesBase for access to all configuration properties
     * and getter methods.
     *
     * @since   1.0.0
     * @author  Kevin Pirnie <me@kpirnie.com>
     * @package KPT\DataTables
     */
    class Renderer extends DataTablesBase
    {
        /**
         * Constructor - Initialize the Renderer
         *
         * Accepts an optional DataTables instance for standalone rendering.
         * When used as a parent class of DataTables, configuration is inherited
         * through the class hierarchy.
         *
         * @param DataTables|null $dataTable Optional DataTables instance
         */
        public function __construct(?DataTables $dataTable = null)
        {
        }

        /**
         * Render the complete DataTable HTML output
         *
         * Generates the full HTML including the table container,
         * modal dialogs, and JavaScript initialization script.
         *
         * @return string Complete HTML output
         */
        protected function render(): string
        {
            $html = $this->renderContainer();
            $html .= $this->renderModals();
            $html .= $this->renderInitScript();
            return $html;
        }

        /**
         * Render the main table container wrapper
         *
         * Creates the outer container div with theme-specific classes
         * and data attributes, then renders the table inside it.
         *
         * @return string HTML for the table container
         */
        protected function renderContainer(): string
        {
            $tableName = $this->getTableName();
            $tm = $this->getThemeManager();
            $containerClass = "datatables-container-{$tableName}";
            $themeContainerClass = $tm->getClasses('container');

            $html = "<div class=\"{$containerClass} datatables-container {$themeContainerClass}\" data-table=\"{$tableName}\">\n";
            $html .= $this->renderTable();
            $html .= "</div>\n";

            return $html;
        }

        /**
         * Render the bulk actions toolbar
         *
         * Generates the toolbar containing the add record button,
         * bulk action buttons (delete, custom actions), and any
         * injected HTML content with configurable positioning.
         * Each bulk action button is disabled by default and enabled
         * via JavaScript when rows are selected.
         *
         * @param  array $bulkConfig Bulk actions configuration array
         * @return string HTML for the bulk actions toolbar
         */
        protected function renderBulkActions(array $bulkConfig): string
        {
            $tm = $this->getThemeManager();
            $gridClass = $tm->getClass('grid.small');

            $html = "<div class=\"{$gridClass}\"" . ($this->theme === 'uikit' ? ' uk-grid' : '') . ">\n";

            // Add new record button - always present
            $html .= "      <div>\n";
            $html .= "          <a href=\"#\" class=\"" . $tm->getClasses('icon.link') . "\" ";
            $html .= "onclick=\"DataTables.showAddModal(event)\" ";
            $html .= ($this->theme === 'uikit' ? 'uk-icon="plus" uk-tooltip="Add a New Record"' : 'title="Add a New Record"') . ">";
            if ($this->theme !== 'uikit') {
                $html .= $tm->getIcon('plus');
            }
            $html .= "</a>\n";
            $html .= "      </div>\n";

            // Render bulk config level HTML with 'before' or 'both' location
            if (isset($bulkConfig['html'])) {
                if (is_array($bulkConfig['html']) && isset($bulkConfig['html']['location']) && isset($bulkConfig['html']['content'])) {
                    $location = $bulkConfig['html']['location'];
                    $content = $bulkConfig['html']['content'];
                    if ($location === 'before' || $location === 'both') {
                        $html .= "<div>\n{$content}\n</div>\n";
                    }
                } else {
                    $html .= "<div>\n{$bulkConfig['html']}\n</div>\n";
                }
            }

            // Collect all actions to render from bulk config and action groups
            $actionsToRender = [];

            if ($bulkConfig['enabled'] && !empty($bulkConfig['actions'])) {
                $actionsToRender = $bulkConfig['actions'];
            }

            // Check action groups for delete action to auto-add bulk delete
            $actionConfig = $this->getActionConfig();
            if (isset($actionConfig['groups'])) {
                foreach ($actionConfig['groups'] as $group) {
                    if (is_array($group)) {
                        foreach ($group as $actionKey => $actionData) {
                            if ($actionKey === 'delete' || (is_string($actionData) && $actionData === 'delete')) {
                                if (!isset($actionsToRender['delete'])) {
                                    $actionsToRender['delete'] = [
                                        'icon' => 'trash',
                                        'label' => 'Delete Selected',
                                        'confirm' => 'Are you sure you want to delete the selected records?'
                                    ];
                                }
                            }
                        }
                    }
                }
            }

            // Render each bulk action as an icon button
            if (!empty($actionsToRender)) {
                $actionCount = 0;

                // Check for and handle delete replacement flag
                $replaceDelete = array_key_exists('replacedelete', $actionsToRender);
                if ($replaceDelete) {
                    $actionsToRender = array_filter($actionsToRender, fn($key) => $key !== 'delete', ARRAY_FILTER_USE_KEY);
                }

                $totalActions = count($actionsToRender);
                foreach ($actionsToRender as $action => $config) {
                    // Handle HTML injection with location/content structure
                    if (isset($config['html'])) {
                        if (is_array($config['html']) && isset($config['html']['location']) && isset($config['html']['content'])) {
                            $location = $config['html']['location'];
                            $content = $config['html']['content'];
                            if ($location === 'before' || $location === 'both') {
                                $html .= "<div>\n{$content}\n</div>\n";
                            }
                            // Skip to next if only HTML injection with no action button
                            if ($location !== 'after' && $location !== 'both') {
                                continue;
                            }
                        } else {
                            // Legacy string format - render and continue
                            $content = $config['html'];
                            $html .= "<div>\n{$content}\n</div>\n";
                            continue;
                        }
                    }

                    $actionCount++;
                    $icon = $config['icon'] ?? 'link';
                    $label = $config['label'] ?? ucfirst($action);
                    $confirm = $config['confirm'] ?? '';

                    // Render the bulk action icon button
                    $html .= "<div>\n";
                    $html .= "<a class=\"" . $tm->getClasses('icon.link') . " datatables-bulk-action-btn\" ";
                    if ($this->theme === 'uikit') {
                        $html .= "uk-icon=\"{$icon}\" ";
                    }
                    $html .= "data-action=\"{$action}\" data-confirm=\"{$confirm}\" ";
                    $html .= "onclick=\"DataTables.executeBulkActionDirect('{$action}', event)\" ";
                    $html .= ($this->theme === 'uikit' ? "uk-tooltip=\"{$label}\"" : "title=\"{$label}\"") . " disabled>";
                    if ($this->theme !== 'uikit') {
                        $html .= $tm->getIcon($icon);
                    }
                    $html .= "</a>\n</div>\n";

                    // Handle action-level HTML with 'after' or 'both' location
                    if (isset($config['html']) && is_array($config['html']) && isset($config['html']['location']) && isset($config['html']['content'])) {
                        $location = $config['html']['location'];
                        $content = $config['html']['content'];
                        if ($location === 'after' || $location === 'both') {
                            $html .= "<div>\n{$content}\n</div>\n";
                        }
                    }
                }
            }

            // Handle bulk config level HTML with 'after' or 'both' location
            if (isset($bulkConfig['html']) && is_array($bulkConfig['html']) && isset($bulkConfig['html']['location']) && isset($bulkConfig['html']['content'])) {
                $location = $bulkConfig['html']['location'];
                $content = $bulkConfig['html']['content'];
                if ($location === 'after' || $location === 'both') {
                    $html .= "<div>\n{$content}\n</div>\n";
                }
            }

            $html .= "</div>\n";
            return $html;
        }

        /**
         * Render the search form with input field and reset button
         *
         * Generates a search input with a search icon (theme-appropriate)
         * and a reset button that clears the search and reloads data.
         * The search input includes debounced event handling via JavaScript.
         *
         * @return string HTML for the search form
         */
        protected function renderSearchForm(): string
        {
            $tm = $this->getThemeManager();

            // Search input container with icon
            $html = "<div>\n";
            $html .= "<div class=\"" . $tm->getClasses('inline') . " " . $tm->getClass('width.medium') . "\">\n";

            // Theme-specific search icon rendering
            if ($this->theme === 'uikit') {
                $html .= "<span class=\"uk-form-icon\" uk-icon=\"search\"></span>\n";
            } elseif ($this->theme === 'bootstrap') {
                $html .= "<span class=\"position-absolute\" style=\"left:12px;top:50%;transform:translateY(-50%);pointer-events:none;\"><i class=\"bi bi-search\"></i></span>\n";
            } else {
                $html .= "<span class=\"" . $tm->getClass('form.icon') . "\">" . $tm->getIcon('search') . "</span>\n";
            }

            // Search text input
            $inputClass = $tm->getClasses('input');
            $paddingStyle = ($this->theme === 'bootstrap') ? ' style="padding-left:38px;"' : '';
            $html .= "<input class=\"{$inputClass} datatables-search\" type=\"text\" placeholder=\"Search...\"{$paddingStyle}>\n";
            $html .= "</div>\n</div>\n";

            // Reset search button with refresh icon
            $html .= "<div>\n";
            $buttonClass = $tm->getClass('button.default');
            $html .= "<button class=\"{$buttonClass} refreshbutton\" type=\"button\" onclick=\"DataTables.resetSearch()\" ";
            $html .= ($this->theme === 'uikit' ? 'uk-tooltip="Reset Search"' : 'title="Reset Search"') . ">\n";

            // Theme-specific refresh icon
            if ($this->theme === 'uikit') {
                $html .= "<span uk-icon=\"refresh\"></span>\n";
            } elseif ($this->theme === 'bootstrap') {
                $html .= "<i class=\"bi bi-arrow-clockwise\"></i>\n";
            } else {
                $html .= $tm->getIcon('refresh');
            }

            $html .= "</button>\n</div>\n";

            return $html;
        }

        /**
         * Render the page size selector
         *
         * Generates either a button group or select dropdown for choosing
         * the number of records displayed per page. Includes an optional
         * "All" option to display every record without pagination.
         *
         * @param  bool $asButtonGroup Whether to render as a button group instead of dropdown
         * @return string HTML for the page size selector
         */
        protected function renderPageSizeSelector(bool $asButtonGroup = false): string
        {
            $tm = $this->getThemeManager();
            $options = $this->getPageSizeOptions();
            $includeAll = $this->getIncludeAllOption();
            $current = $this->getRecordsPerPage();

            if ($asButtonGroup) {
                // Render as a group of toggle buttons
                $html = "<div>\nPer Page: <div class=\"" . $tm->getKpDtClass('button-group') . "\">\n";
                foreach ($options as $option) {
                    $activeClass = $option === $current ? $tm->getClass('button.primary') : $tm->getClass('button.default');
                    $activeClass .= ' ' . $tm->getClass('button.small');
                    $html .= "<a class=\"{$activeClass} datatables-page-size-btn\" href=\"#\" data-size=\"{$option}\" onclick=\"DataTables.changePageSize({$option}, event)\">{$option}</a>\n";
                }
                if ($includeAll) {
                    $activeClass = $current === 0 ? $tm->getClass('button.primary') : $tm->getClass('button.default');
                    $activeClass .= ' ' . $tm->getClass('button.small');
                    $html .= "<a class=\"{$activeClass} datatables-page-size-btn\" href=\"#\" data-size=\"0\" onclick=\"DataTables.changePageSize(0, event)\">All</a>\n";
                }
                $html .= "</div>\n</div>\n";
            } else {
                // Render as a select dropdown
                $selectClass = $tm->getClasses('select') . ' ' . $tm->getClass('width.auto');
                $html = "<div>\nPer Page: <select class=\"{$selectClass} datatables-page-size\">\n";
                foreach ($options as $option) {
                    $selected = $option === $current ? ' selected' : '';
                    $html .= "<option value=\"{$option}\"{$selected}>{$option} records</option>\n";
                }
                if ($includeAll) {
                    $html .= "<option value=\"0\">All records</option>\n";
                }
                $html .= "</select>\n</div>\n";
            }

            return $html;
        }

        /**
         * Render the pagination controls
         *
         * Generates the record info display and pagination navigation.
         * The actual pagination links are populated dynamically via JavaScript
         * after data loads. Initial render shows placeholder disabled controls.
         *
         * @return string HTML for the pagination section
         */
        protected function renderPagination(): string
        {
            $tm = $this->getThemeManager();
            $paginationClass = $tm->getClasses('pagination');

            // Record info text (e.g., "Showing 1 to 25 of 100 records")
            $html = "<div>\n";
            $html .= "<div class=\"datatables-info\" id=\"datatables-info\">Showing 0 to 0 of 0 records</div>\n";

            // Pagination navigation list - populated by JavaScript
            $html .= "<ul class=\"{$paginationClass} datatables-pagination\" id=\"datatables-pagination\">\n";

            // Render initial disabled placeholder controls
            if ($this->theme === 'bootstrap') {
                $html .= "<li class=\"page-item disabled\"><span class=\"page-link\">&laquo;</span></li>\n";
                $html .= "<li class=\"page-item disabled\"><span class=\"page-link\">&raquo;</span></li>\n";
            } else {
                $disabledClass = $tm->getClass('pagination.disabled');
                $html .= "<li class=\"{$disabledClass}\"><span>" . ($this->theme === 'uikit' ? '<span uk-pagination-previous></span>' : '&laquo;') . "</span></li>\n";
                $html .= "<li class=\"{$disabledClass}\"><span>" . ($this->theme === 'uikit' ? '<span uk-pagination-next></span>' : '&raquo;') . "</span></li>\n";
            }

            $html .= "</ul>\n</div>\n";
            return $html;
        }

        /**
         * Render the table header row with column labels and sort indicators
         *
         * Generates <tr> with <th> elements for each configured column,
         * including optional bulk selection checkbox, action column placement,
         * and sortable header spans with sort direction icons.
         * Handles aliased columns for proper sort attribute generation.
         *
         * @return string HTML for the table header row
         */
        protected function renderTableHeaderRow(): string
        {
            $tm = $this->getThemeManager();
            $columns = $this->getColumns();
            $sortableColumns = $this->getSortableColumns();
            $actionConfig = $this->getActionConfig();
            $bulkActions = $this->getBulkActions();
            $cssClasses = $this->getCssClasses();

            $html = "<tr>\n";

            // Bulk selection "select all" checkbox column
            if ($bulkActions['enabled']) {
                $shrinkClass = $tm->getClass('th.shrink');
                $checkboxClass = $tm->getClasses('checkbox');
                $html .= "<th" . ($shrinkClass ? " class=\"{$shrinkClass}\"" : "") . ">\n";
                $html .= "<label><input type=\"checkbox\" class=\"{$checkboxClass} datatables-select-all\" onchange=\"DataTables.toggleSelectAll(this)\"></label>\n";
                $html .= "</th>\n";
            }

            // Action column at start position
            if ($actionConfig['position'] === 'start') {
                $shrinkClass = $tm->getClass('th.shrink');
                $html .= "<th" . ($shrinkClass ? " class=\"{$shrinkClass}\"" : "") . ">Actions</th>\n";
            }

            // Render each column header with optional sort functionality
            foreach ($columns as $column => $label) {
                // Determine if this column is sortable (check both full name and alias)
                $sortable = in_array($column, $sortableColumns);
                if (!$sortable && stripos($column, ' AS ') !== false) {
                    $parts = explode(' AS ', $column);
                    if (count($parts) === 2) {
                        $aliasName = trim($parts[1], '`\'" ');
                        $sortable = in_array($aliasName, $sortableColumns);
                    }
                }

                // Build column header classes
                $columnClass = $cssClasses['columns'][$column] ?? '';
                $thClass = $columnClass . ($sortable ? ' sortable' : '');

                // Generate the data-sort attribute using alias name if applicable
                $html .= "<th" . (!empty($thClass) ? " class=\"{$thClass}\"" : "") .
                    ($sortable ? " data-sort=\"" . (stripos($column, ' AS ') !== false ? trim(explode(' AS ', $column)[1], '`\'" ') : $column) . "\"" : "") . ">";

                if ($sortable) {
                    // Sortable header with clickable span and sort direction icon
                    $displayLabel = is_array($label) ? ($label['label'] ?? $column) : $label;
                    $sortableHeaderClass = $tm->getKpDtClass('sortable-header');
                    $html .= "<span class=\"sortable-header {$sortableHeaderClass}\">{$displayLabel} ";
                    if ($this->theme === 'uikit') {
                        $html .= "<span class=\"sort-icon\" uk-icon=\"triangle-up\"></span>";
                    } else {
                        $html .= "<span class=\"sort-icon\">" . $tm->getIcon('triangle-up') . "</span>";
                    }
                    $html .= "</span>";
                } else {
                    // Non-sortable plain label
                    $displayLabel = is_array($label) ? ($label['label'] ?? $column) : $label;
                    $html .= $displayLabel;
                }

                $html .= "</th>\n";
            }

            // Action column at end position
            if ($actionConfig['position'] === 'end') {
                $shrinkClass = $tm->getClass('th.shrink');
                $html .= "<th" . ($shrinkClass ? " class=\"{$shrinkClass}\"" : "") . " style=\"text-align: right\">Actions</th>\n";
            }

            $html .= "</tr>\n";
            return $html;
        }

        /**
         * Render the complete table element with header, body, and footer
         *
         * Generates the full <table> HTML including overflow wrapper,
         * thead with column headers, tbody placeholder for JavaScript-loaded data,
         * tfoot with column headers and optional aggregation rows, and
         * the table schema as a data attribute for client-side field type detection.
         *
         * @return string HTML for the complete table element
         */
        protected function renderTable(): string
        {
            $tm = $this->getThemeManager();
            $columns = $this->getColumns();
            $bulkActions = $this->getBulkActions();
            $cssClasses = $this->getCssClasses();
            $tableSchema = $this->getTableSchema();

            // Determine table and wrapper classes
            $tableClass = $cssClasses['table'] ?? $tm->getClass('table.full');
            $theadClass = $cssClasses['thead'] ?? '';
            $tbodyClass = $cssClasses['tbody'] ?? '';
            $themeTableClass = $tm->getKpDtClass('table');
            $overflowClass = $tm->getClasses('overflow.auto');

            // Overflow wrapper for responsive horizontal scrolling
            $html = "<div class=\"{$overflowClass}\">\n";

            // Table element with schema data attribute for JS field type detection
            $html .= "<table class=\"{$tableClass} {$themeTableClass} datatables-table\" data-columns='" . json_encode($tableSchema) . "'>\n";

            // Table header
            $html .= "<thead" . (!empty($theadClass) ? " class=\"{$theadClass}\"" : "") . ">\n";
            $html .= $this->renderTableHeaderRow();
            $html .= "</thead>\n";

            // Table body - populated dynamically via JavaScript AJAX calls
            $html .= "<tbody class=\"datatables-tbody" . (!empty($tbodyClass) ? " {$tbodyClass}" : "") . "\" id=\"datatables-tbody\">\n";

            // Calculate total column count for loading placeholder colspan
            $totalColumns = count($columns) + 1;
            if ($bulkActions['enabled']) {
                $totalColumns++;
            }

            // Initial loading placeholder row
            $centerClass = $tm->getClass('text.center');
            $html .= "<tr><td colspan=\"{$totalColumns}\" class=\"{$centerClass}\">Loading...</td></tr>\n";
            $html .= "</tbody>\n";

            // Table footer with column headers and optional aggregation rows
            // Aggregation rows display sum/average calculations per configured columns
            $footerAggregations = $this->getFooterAggregations();
            $html .= "<tfoot" . (!empty($theadClass) ? " class=\"{$theadClass}\"" : "") . ">\n";
            $html .= $this->renderTableHeaderRow();
            if (!empty($footerAggregations)) {
                $html .= $this->renderAggregationFooterRows($footerAggregations);
            }
            $html .= "</tfoot>\n";

            $html .= "</table>\n</div>\n";

            return $html;
        }

        /**
         * Render all modal dialogs
         *
         * Generates the add, edit, and delete confirmation modals.
         * Each modal is rendered according to the current theme's
         * dialog structure and styling conventions.
         *
         * @return string HTML for all modal dialogs
         */
        protected function renderModals(): string
        {
            $html = $this->renderAddModal();
            $html .= $this->renderEditModal();
            $html .= $this->renderDeleteModal();
            return $html;
        }

        /**
         * Render the add record modal dialog
         *
         * Generates a theme-appropriate modal containing a form with
         * all configured add form fields. The form uses AJAX submission
         * via the DataTables JavaScript handler. Includes cancel and
         * submit buttons with proper modal close behavior per theme.
         *
         * @return string HTML for the add record modal
         */
        protected function renderAddModal(): string
        {
            $tm = $this->getThemeManager();
            $formConfig = $this->getAddFormConfig();
            $formFields = $formConfig['fields'];
            $title = $formConfig['title'];
            $formClass = $formConfig['class'] ?? '';

            // Theme-specific modal structure
            if ($this->theme === 'bootstrap') {
                $html = "<div class=\"modal fade\" id=\"add-modal\" tabindex=\"-1\">\n<div class=\"modal-dialog\">\n<div class=\"modal-content\">\n";
                $html .= "<div class=\"modal-header\">\n<h5 class=\"modal-title\">{$title}</h5>\n";
                $html .= "<button type=\"button\" class=\"btn-close\" data-bs-dismiss=\"modal\"></button>\n</div>\n";
                $html .= "<div class=\"modal-body\">\n";
            } else {
                $modalClass = $tm->getClass('modal');
                $dialogClass = $tm->getClass('modal.dialog');
                $bodyClass = $tm->getClass('modal.body');
                $titleClass = $tm->getClass('modal.title');
                $html = "<div id=\"add-modal\" class=\"{$modalClass}\"" . ($this->theme === 'uikit' ? ' uk-modal' : '') . ">\n";
                $html .= "<div class=\"{$dialogClass}\">\n<div class=\"{$bodyClass}\">\n";
                $html .= "<h2 class=\"{$titleClass}\">{$title}</h2>\n";
            }

            // Form element with AJAX submit handler
            $formStackedClass = $tm->getClass('form.stacked');
            $html .= "<form class=\"{$formStackedClass} {$formClass}\" id=\"add-form\" onsubmit=\"return DataTables.submitAddForm(event)\">\n";

            // Render each configured form field
            foreach ($formFields as $field => $config) {
                $html .= $this->renderFormField($field, $config, 'add');
            }

            // Form action buttons (Cancel and Submit)
            $marginTopClass = $tm->getClass('margin.top');
            $textRightClass = $tm->getClass('text.right');
            $buttonDefaultClass = $tm->getClass('button.default');
            $buttonPrimaryClass = $tm->getClass('button.primary');
            $marginSmallLeftClass = $tm->getClass('margin.small.left');

            $html .= "<div class=\"{$marginTopClass} {$textRightClass}\">\n";

            // Cancel button with theme-appropriate modal close behavior
            if ($this->theme === 'bootstrap') {
                $html .= "<button class=\"{$buttonDefaultClass}\" type=\"button\" data-bs-dismiss=\"modal\">Cancel</button>\n";
            } else {
                $closeClass = $this->theme === 'uikit' ? ' uk-modal-close' : '';
                $onclick = $this->theme !== 'uikit' ? " onclick=\"KPDataTablesPlain.hideModal('add-modal')\"" : "";
                $html .= "<button class=\"{$buttonDefaultClass}{$closeClass}\" type=\"button\"{$onclick}>Cancel</button>\n";
            }

            $html .= "<button class=\"{$buttonPrimaryClass} {$marginSmallLeftClass}\" type=\"submit\">Add Record</button>\n";
            $html .= "</div>\n</form>\n";

            // Close modal structure
            if ($this->theme === 'bootstrap') {
                $html .= "</div>\n</div>\n</div>\n</div>\n";
            } else {
                $html .= "</div>\n</div>\n</div>\n";
            }

            return $html;
        }

        /**
         * Render the edit record modal dialog
         *
         * Generates a theme-appropriate modal containing a form with
         * all configured edit form fields. Includes a hidden primary key
         * field that is populated via JavaScript when editing a specific record.
         * The form uses AJAX submission via the DataTables JavaScript handler.
         *
         * @return string HTML for the edit record modal
         */
        protected function renderEditModal(): string
        {
            $tm = $this->getThemeManager();
            $formConfig = $this->getEditFormConfig();
            $formFields = $formConfig['fields'];
            $title = $formConfig['title'];
            $primaryKey = $this->getPrimaryKey();
            $formClass = $formConfig['class'] ?? '';

            // Extract unqualified primary key for form field naming
            $unqualifiedPK = strpos($primaryKey, '.') !== false ? explode('.', $primaryKey)[1] : $primaryKey;

            // Theme-specific modal structure
            if ($this->theme === 'bootstrap') {
                $html = "<div class=\"modal fade\" id=\"edit-modal\" tabindex=\"-1\">\n<div class=\"modal-dialog\">\n<div class=\"modal-content\">\n";
                $html .= "<div class=\"modal-header\">\n<h5 class=\"modal-title\">{$title}</h5>\n";
                $html .= "<button type=\"button\" class=\"btn-close\" data-bs-dismiss=\"modal\"></button>\n</div>\n";
                $html .= "<div class=\"modal-body\">\n";
            } else {
                $modalClass = $tm->getClass('modal');
                $dialogClass = $tm->getClass('modal.dialog');
                $bodyClass = $tm->getClass('modal.body');
                $titleClass = $tm->getClass('modal.title');
                $html = "<div id=\"edit-modal\" class=\"{$modalClass}\"" . ($this->theme === 'uikit' ? ' uk-modal' : '') . ">\n";
                $html .= "<div class=\"{$dialogClass}\">\n<div class=\"{$bodyClass}\">\n";
                $html .= "<h2 class=\"{$titleClass}\">{$title}</h2>\n";
            }

            // Form element with AJAX submit handler
            $formStackedClass = $tm->getClass('form.stacked');
            $html .= "<form class=\"{$formStackedClass} {$formClass}\" id=\"edit-form\" onsubmit=\"return DataTables.submitEditForm(event)\">\n";

            // Hidden primary key field - populated by JavaScript when loading record
            $html .= "<input type=\"hidden\" name=\"{$unqualifiedPK}\" id=\"edit-{$unqualifiedPK}\">\n";

            // Render each configured form field
            foreach ($formFields as $field => $config) {
                $html .= $this->renderFormField($field, $config, 'edit');
            }

            // Form action buttons (Cancel and Submit)
            $marginTopClass = $tm->getClass('margin.top');
            $textRightClass = $tm->getClass('text.right');
            $buttonDefaultClass = $tm->getClass('button.default');
            $buttonPrimaryClass = $tm->getClass('button.primary');
            $marginSmallLeftClass = $tm->getClass('margin.small.left');

            $html .= "<div class=\"{$marginTopClass} {$textRightClass}\">\n";

            // Cancel button with theme-appropriate modal close behavior
            if ($this->theme === 'bootstrap') {
                $html .= "<button class=\"{$buttonDefaultClass}\" type=\"button\" data-bs-dismiss=\"modal\">Cancel</button>\n";
            } else {
                $closeClass = $this->theme === 'uikit' ? ' uk-modal-close' : '';
                $onclick = $this->theme !== 'uikit' ? " onclick=\"KPDataTablesPlain.hideModal('edit-modal')\"" : "";
                $html .= "<button class=\"{$buttonDefaultClass}{$closeClass}\" type=\"button\"{$onclick}>Cancel</button>\n";
            }

            $html .= "<button class=\"{$buttonPrimaryClass} {$marginSmallLeftClass}\" type=\"submit\">Update Record</button>\n";
            $html .= "</div>\n</form>\n";

            // Close modal structure
            if ($this->theme === 'bootstrap') {
                $html .= "</div>\n</div>\n</div>\n</div>\n";
            } else {
                $html .= "</div>\n</div>\n</div>\n";
            }

            return $html;
        }

        /**
         * Render the delete confirmation modal dialog
         *
         * Generates a theme-appropriate confirmation dialog with a warning
         * message and Cancel/Delete buttons. The delete action is executed
         * via JavaScript when the user confirms.
         *
         * @return string HTML for the delete confirmation modal
         */
        protected function renderDeleteModal(): string
        {
            $tm = $this->getThemeManager();

            if ($this->theme === 'bootstrap') {
                // Bootstrap modal structure with header, body, and footer
                $html = "<div class=\"modal fade\" id=\"delete-modal\" tabindex=\"-1\">\n";
                $html .= "<div class=\"modal-dialog modal-dialog-centered\">\n<div class=\"modal-content\">\n";
                $html .= "<div class=\"modal-header\">\n<h5 class=\"modal-title\">Confirm Delete</h5>\n";
                $html .= "<button type=\"button\" class=\"btn-close\" data-bs-dismiss=\"modal\"></button>\n</div>\n";
                $html .= "<div class=\"modal-body\">\n<p>Are you sure you want to delete this record? This action cannot be undone.</p>\n</div>\n";
                $html .= "<div class=\"modal-footer\">\n";
                $html .= "<button class=\"btn btn-secondary\" type=\"button\" data-bs-dismiss=\"modal\">Cancel</button>\n";
                $html .= "<button class=\"btn btn-danger\" type=\"button\" onclick=\"DataTables.confirmDelete()\">Delete</button>\n";
                $html .= "</div>\n</div>\n</div>\n</div>\n";
            } else {
                // UIKit / Plain / Tailwind modal structure
                $modalClass = $tm->getClass('modal');
                $dialogClass = $tm->getClass('modal.dialog');
                $bodyClass = $tm->getClass('modal.body');
                $titleClass = $tm->getClass('modal.title');
                $marginTopClass = $tm->getClass('margin.top');
                $textRightClass = $tm->getClass('text.right');
                $buttonDefaultClass = $tm->getClass('button.default');
                $buttonDangerClass = $tm->getClass('button.danger');
                $marginSmallLeftClass = $tm->getClass('margin.small.left');

                $html = "<div id=\"delete-modal\" class=\"{$modalClass}\"" . ($this->theme === 'uikit' ? ' uk-modal' : '') . ">\n";
                $html .= "<div class=\"{$dialogClass}\">\n<div class=\"{$bodyClass}\">\n";
                $html .= "<h2 class=\"{$titleClass}\">Confirm Delete</h2>\n";
                $html .= "<p>Are you sure you want to delete this record? This action cannot be undone.</p>\n";

                // Action buttons
                $html .= "<div class=\"{$marginTopClass} {$textRightClass}\">\n";
                $closeClass = $this->theme === 'uikit' ? ' uk-modal-close' : '';
                $onclick = $this->theme !== 'uikit' ? " onclick=\"KPDataTablesPlain.hideModal('delete-modal')\"" : "";
                $html .= "<button class=\"{$buttonDefaultClass}{$closeClass}\" type=\"button\"{$onclick}>Cancel</button>\n";
                $html .= "<button class=\"{$buttonDangerClass} {$marginSmallLeftClass}\" type=\"button\" onclick=\"DataTables.confirmDelete()\">Delete</button>\n";
                $html .= "</div>\n</div>\n</div>\n</div>\n";
            }

            return $html;
        }

        /**
         * Render a single form field based on its type configuration
         *
         * Generates the appropriate HTML form element for a given field type
         * including label, input/select/textarea, validation attributes,
         * and theme-specific styling classes. Supports text, email, boolean,
         * checkbox, radio, textarea, select, file, image, and hidden field types.
         *
         * @param  string $field  Database field name
         * @param  array  $config Field configuration array with type, label, required, etc.
         * @param  string $prefix Form prefix for element IDs ('add' or 'edit')
         * @return string HTML for the form field
         */
        protected function renderFormField(string $field, array $config, string $prefix = 'add'): string
        {
            $tm = $this->getThemeManager();

            // Extract field configuration values
            $type = $config['type'];
            $label = ($config['label']) ?? '';
            $required = $config['required'] ?? false;
            $placeholder = $config['placeholder'] ?? '';
            $options = $config['options'] ?? [];
            $customClass = $config['class'] ?? '';
            $attributes = $config['attributes'] ?? [];
            $value = $config['value'] ?? '';
            $default = $config['default'] ?? '';
            $disabled = $config['disabled'] ?? false;

            // Select2 specific config
            $select2Query = $config['query'] ?? '';
            $select2MinChars = $config['min_search_chars'] ?? 0;
            $select2MaxResults = $config['max_results'] ?? 50;

            // Use default value if no explicit value provided
            if (empty($value) && !empty($default)) {
                $value = $default;
            }

            // Generate unique field ID and name
            $fieldId = "{$prefix}-{$field}";
            $fieldName = $field;

            // Get theme-specific form element classes
            $formControlsClass = $tm->getClass('form.controls');
            $formLabelClass = $tm->getClass('form.label');
            $inputClass = $tm->getClasses('input');
            $selectClass = $tm->getClasses('select');
            $textareaClass = $tm->getClasses('textarea');
            $checkboxClass = $tm->getClasses('checkbox');
            $radioClass = $tm->getClasses('radio');
            $dangerClass = $tm->getClass('text.danger');

            // Hidden fields render without wrapper or label
            if ($type === 'hidden') {
                return "<input type=\"hidden\" id=\"{$fieldId}\" name=\"{$fieldName}\" value=\"{$value}\">\n";
            }

            // Form control wrapper
            $html = "<div class=\"{$formControlsClass} {$customClass}\">\n";
            $attrString = $this->buildAttributeString($attributes);

            // Render appropriate input element based on field type
            switch ($type) {
                case 'boolean':
                    // Boolean rendered as Active/Inactive select dropdown
                    $html .= "<label class=\"{$formLabelClass}\" for=\"{$fieldId}\">{$label}" . ($required ? " <span class=\"{$dangerClass}\">*</span>" : "") . "</label>\n";
                    $html .= "<select class=\"{$selectClass}\" id=\"{$fieldId}\" name=\"{$fieldName}\" {$attrString} " . ($required ? "required" : "") . ($disabled ? " disabled" : "") . ">\n";
                    $selected0 = ($value == '0' || $value === false) ? ' selected' : '';
                    $selected1 = ($value == '1' || $value === true) ? ' selected' : '';
                    $html .= "<option value=\"0\"{$selected0}>Inactive</option>\n<option value=\"1\"{$selected1}>Active</option>\n</select>\n";
                    break;

                case 'checkbox':
                    // Checkbox with theme-specific layout (Bootstrap uses form-check structure)
                    if ($this->theme === 'bootstrap') {
                        $html .= "<div class=\"form-check\">\n";
                        $html .= "<input type=\"checkbox\" class=\"form-check-input\" id=\"{$fieldId}\" name=\"{$fieldName}\" value=\"1\" {$attrString}" . (($value == '1' || $value === true) ? " checked" : "") . ($disabled ? " disabled" : "") . ">\n";
                        $html .= "<label class=\"form-check-label\" for=\"{$fieldId}\">{$label}" . ($required ? " <span class=\"text-danger\">*</span>" : "") . "</label>\n</div>\n";
                    } else {
                        $html .= "<label><input type=\"checkbox\" class=\"{$checkboxClass}\" id=\"{$fieldId}\" name=\"{$fieldName}\" value=\"1\" {$attrString}" . (($value == '1' || $value === true) ? " checked" : "") . ($disabled ? " disabled" : "") . "> {$label}" . ($required ? " <span class=\"{$dangerClass}\">*</span>" : "") . "</label>\n";
                    }
                    break;

                case 'radio':
                    // Radio button group with multiple options
                    $html .= "<label class=\"{$formLabelClass}\">{$label}" . ($required ? " <span class=\"{$dangerClass}\">*</span>" : "") . "</label>\n";
                    foreach ($options as $optValue => $optLabel) {
                        $checked = ($value == $optValue) ? ' checked' : '';
                        $disabledAttr = $disabled ? ' disabled' : '';
                        if ($this->theme === 'bootstrap') {
                            $html .= "<div class=\"form-check\">\n<input type=\"radio\" class=\"form-check-input\" name=\"{$fieldName}\" id=\"{$fieldId}-{$optValue}\" value=\"{$optValue}\" {$attrString}{$checked}{$disabledAttr}" . ($required ? " required" : "") . ">\n";
                            $html .= "<label class=\"form-check-label\" for=\"{$fieldId}-{$optValue}\">{$optLabel}</label>\n</div>\n";
                        } else {
                            $marginClass = $tm->getClass('margin.small.right');
                            $html .= "<label class=\"{$marginClass}\"><input type=\"radio\" class=\"{$radioClass}\" name=\"{$fieldName}\" value=\"{$optValue}\" {$attrString}{$checked}{$disabledAttr}" . ($required ? " required" : "") . "> {$optLabel}</label>\n";
                        }
                    }
                    break;

                case 'textarea':
                    // Multi-line text input
                    $html .= "<label class=\"{$formLabelClass}\" for=\"{$fieldId}\">{$label}" . ($required ? " <span class=\"{$dangerClass}\">*</span>" : "") . "</label>\n";
                    $html .= "<textarea class=\"{$textareaClass}\" id=\"{$fieldId}\" name=\"{$fieldName}\" placeholder=\"{$placeholder}\" {$attrString} " . ($required ? "required" : "") . ($disabled ? " disabled" : "") . "></textarea>\n";
                    break;

                case 'select':
                    // Dropdown select with configurable options
                    $html .= "<label class=\"{$formLabelClass}\" for=\"{$fieldId}\">{$label}" . ($required ? " <span class=\"{$dangerClass}\">*</span>" : "") . "</label>\n";
                    $html .= "<select class=\"{$selectClass}\" id=\"{$fieldId}\" name=\"{$fieldName}\" {$attrString} " . ($required ? "required" : "") . ($disabled ? " disabled" : "") . ">\n";
                    if (!$required) {
                        $html .= "<option value=\"\">-- Select --</option>\n";
                    }
                    foreach ($options as $optValue => $optLabel) {
                        $selected = ($value == $optValue) ? ' selected' : '';
                        $html .= "<option value=\"{$optValue}\"{$selected}>{$optLabel}</option>\n";
                    }
                    $html .= "</select>\n";
                    break;

                case 'select2':
                    // Select2 searchable dropdown with AJAX
                    // Try config first, then fall back to schema
                    $select2Query = $select2Query ?? '';
                    $select2MinChars = $select2MinChars ?? 0;
                    $select2MaxResults = $select2MaxResults ?? 50;

                    if (empty($select2Query)) {
                        $tableSchema = $this->getTableSchema();
                        $schemaKey = strpos($field, '.') !== false ? explode('.', $field)[1] : $field;
                        $schemaInfo = $tableSchema[$schemaKey] ?? [];
                        $select2Query = $schemaInfo['select2_query'] ?? '';
                        $select2MinChars = $schemaInfo['select2_min_search_chars'] ?? 0;
                        $select2MaxResults = $schemaInfo['select2_max_results'] ?? 50;
                    }

                    $html .= "<label class=\"{$formLabelClass}\" for=\"{$fieldId}\">{$label}" . ($required ? " <span class=\"{$dangerClass}\">*</span>" : "") . "</label>\n";

                    // Render as native select with data attributes for JavaScript enhancement
                    $html .= "<select class=\"{$selectClass}\" id=\"{$fieldId}\" name=\"{$fieldName}\" ";
                    $html .= "data-select2=\"true\" ";
                    $html .= "data-query=\"" . htmlspecialchars($select2Query, ENT_QUOTES) . "\" ";
                    $html .= "data-placeholder=\"{$placeholder}\" ";
                    $html .= "data-min-search-chars=\"{$select2MinChars}\" ";
                    $html .= "data-max-results=\"{$select2MaxResults}\" ";
                    $html .= "data-theme=\"{$this->theme}\" ";
                    $html .= $attrString . " ";
                    $html .= ($required ? "required " : "");
                    $html .= ($disabled ? "disabled " : "");
                    $html .= ">\n";

                    // Add option if value is set
                    if (!empty($value)) {
                        $html .= "<option value=\"{$value}\" selected>{$value}</option>\n";
                    } else {
                        $html .= "<option value=\"\">{$placeholder}</option>\n";
                    }

                    $html .= "</select>\n";
                    break;
                    $html .= "<label class=\"{$formLabelClass}\" for=\"{$fieldId}\">{$label}" . ($required ? " <span class=\"{$dangerClass}\">*</span>" : "") . "</label>\n";

                    // Render as native select with data attributes for JavaScript enhancement
                    $html .= "<select class=\"{$selectClass}\" id=\"{$fieldId}\" name=\"{$fieldName}\" ";
                    $html .= "data-select2=\"true\" ";
                    $html .= "data-query=\"" . htmlspecialchars($select2Query, ENT_QUOTES) . "\" ";
                    $html .= "data-placeholder=\"{$placeholder}\" ";
                    $html .= "data-min-search-chars=\"{$select2MinChars}\" ";
                    $html .= "data-max-results=\"{$select2MaxResults}\" ";
                    $html .= "data-theme=\"{$this->theme}\" ";
                    $html .= $attrString . " ";
                    $html .= ($required ? "required " : "");
                    $html .= ($disabled ? "disabled " : "");
                    $html .= ">\n";

                    // Add option if value is set
                if (!empty($value)) {
                    $html .= "<option value=\"{$value}\" selected>{$value}</option>\n";
                } else {
                    $html .= "<option value=\"\">{$placeholder}</option>\n";
                }

                    $html .= "</select>\n";
                    break;

                case 'file':
                    // File upload input
                    $html .= "<label class=\"{$formLabelClass}\" for=\"{$fieldId}\">{$label}" . ($required ? " <span class=\"{$dangerClass}\">*</span>" : "") . "</label>\n";
                    $html .= "<input type=\"file\" class=\"{$inputClass}\" id=\"{$fieldId}\" name=\"{$fieldName}\" {$attrString} " . ($required ? "required" : "") . ($disabled ? " disabled" : "") . ">\n";
                    break;

                case 'image':
                    // Image field with URL input, file upload, and optional preview
                    $html .= "<label class=\"{$formLabelClass}\" for=\"{$fieldId}\">{$label}" . ($required ? " <span class=\"{$dangerClass}\">*</span>" : "") . "</label>\n";
                    if (!empty($value)) {
                        $imageSrc = (strpos($value, 'http') === 0) ? $value : "/uploads/{$value}";
                        $html .= "<div class=\"mb-2\"><img src=\"{$imageSrc}\" alt=\"Current image\" style=\"max-width: 150px; max-height: 150px; object-fit: cover;\" class=\"rounded\"></div>\n";
                    }
                    $html .= "<input type=\"url\" class=\"{$inputClass} mb-2\" id=\"{$fieldId}\" name=\"{$fieldName}\" placeholder=\"Enter image URL or upload file below\" value=\"{$value}\" {$attrString} " . ($disabled ? " disabled" : "") . ">\n";
                    $html .= "<div class=\"mt-2\">\n<input type=\"file\" class=\"{$inputClass}\" id=\"{$fieldId}-file\" name=\"{$fieldName}-file\" accept=\"image/*\" {$attrString} " . ($disabled ? " disabled" : "") . ">\n";
                    $html .= "<small class=\"" . $tm->getClass('text.muted') . "\">Upload an image file or enter URL above</small>\n</div>\n";
                    break;

                default:
                    // Standard text/email input
                    $html .= "<label class=\"{$formLabelClass}\" for=\"{$fieldId}\">{$label}" . ($required ? " <span class=\"{$dangerClass}\">*</span>" : "") . "</label>\n";
                    $inputType = ($type === 'email') ? 'email' : 'text';
                    $html .= "<input type=\"{$inputType}\" class=\"{$inputClass}\" id=\"{$fieldId}\" name=\"{$fieldName}\" placeholder=\"{$placeholder}\" value=\"{$value}\" {$attrString} " . ($required ? "required" : "") . ($disabled ? " disabled" : "") . ">\n";
                    break;
            }

            $html .= "</div>\n";
            return $html;
        }

        /**
         * Build an HTML attribute string from a key-value array
         *
         * Converts an associative array of attribute names and values
         * into a space-separated string of name="value" pairs for use
         * in HTML element generation.
         *
         * @param  array $attributes Associative array of attribute name => value pairs
         * @return string Space-separated HTML attribute string
         */
        protected function buildAttributeString(array $attributes): string
        {
            $attrParts = [];
            foreach ($attributes as $name => $value) {
                $attrParts[] = "{$name}=\"{$value}\"";
            }
            return implode(' ', $attrParts);
        }

        /**
         * Render JavaScript to initialize Select2 fields with record data
         *
         * Generates script to pass current record data to Select2 fields for
         * parameter substitution in queries when editing records.
         *
         * @param  string $formId Form ID ('add-form' or 'edit-form')
         * @param  array  $recordData Current record data for parameter substitution
         * @return string JavaScript code to initialize Select2 with record data
         * @since  1.2.0
         */
        protected function renderSelect2RecordDataScript(string $formId, array $recordData = []): string
        {
            if (empty($recordData)) {
                return '';
            }

            $recordDataJson = json_encode($recordData);

            $html = "<script>\n";
            $html .= "document.addEventListener('DOMContentLoaded', function() {\n";
            $html .= "    const form = document.getElementById('{$formId}');\n";
            $html .= "    if (form) {\n";
            $html .= "        const select2Fields = form.querySelectorAll('select[data-select2]');\n";
            $html .= "        select2Fields.forEach(field => {\n";
            $html .= "            field.setAttribute('data-record-data', '{$recordDataJson}');\n";
            $html .= "        });\n";
            $html .= "    }\n";
            $html .= "});\n";
            $html .= "</script>\n";

            return $html;
        }

        /**
         * Render aggregation footer rows for sum/avg display
         *
         * Generates HTML table rows that display calculated aggregation values
         * (sum and/or average) for configured columns. Supports both page-level
         * and full recordset scope calculations. Each aggregation type and scope
         * combination gets its own row with labeled cells that are populated
         * via JavaScript after data loads.
         *
         * @param  array $aggregations Footer aggregation configurations keyed by column name
         *                              Each entry contains 'type' (sum|avg|both) and 'scope' (page|all|both)
         * @return string HTML for aggregation footer rows
         * @since  1.1.0
         */
        /**
         * Render aggregation footer rows for sum/avg display
         *
         * Uses colspan for the label cell spanning all non-aggregated leading columns,
         * then individual cells for each remaining column (aggregated or empty).
         *
         * @param  array $aggregations Footer aggregation configurations
         * @return string HTML for aggregation rows
         */
        protected function renderAggregationFooterRows(array $aggregations): string
        {
            $tm = $this->getThemeManager();
            $columns = $this->getColumns();
            $bulkActions = $this->getBulkActions();
            $actionConfig = $this->getActionConfig();
            $boldStyle = 'font-weight: bold;';

            // Determine which row types are needed
            $needsPageSum = false;
            $needsPageAvg = false;
            $needsAllSum = false;
            $needsAllAvg = false;

            foreach ($aggregations as $column => $config) {
                $type = $config['type'];
                $scope = $config['scope'];
                if ($type === 'sum' || $type === 'both') {
                    if ($scope === 'page' || $scope === 'both') {
                        $needsPageSum = true;
                    }
                    if ($scope === 'all' || $scope === 'both') {
                        $needsAllSum = true;
                    }
                }
                if ($type === 'avg' || $type === 'both') {
                    if ($scope === 'page' || $scope === 'both') {
                        $needsPageAvg = true;
                    }
                    if ($scope === 'all' || $scope === 'both') {
                        $needsAllAvg = true;
                    }
                }
            }

            // Collect custom labels from aggregation configs
            $customLabel = '';
            foreach ($aggregations as $column => $config) {
                if (!empty($config['label'])) {
                    $customLabel = $config['label'];
                    break;
                }
            }

            $rows = [];
            if ($needsPageSum) {
                $rows[] = ['label' => $customLabel ?: 'Page Sum', 'agg' => 'sum', 'scope' => 'page'];
            }
            if ($needsAllSum) {
                $rows[] = ['label' => $customLabel ?: 'Total Sum', 'agg' => 'sum', 'scope' => 'all'];
            }
            if ($needsPageAvg) {
                $rows[] = ['label' => $customLabel ?: 'Page Avg', 'agg' => 'avg', 'scope' => 'page'];
            }
            if ($needsAllAvg) {
                $rows[] = ['label' => $customLabel ?: 'Total Avg', 'agg' => 'avg', 'scope' => 'all'];
            }

            // Build ordered list of column keys, resolving aliases
            $colKeys = [];
            foreach ($columns as $column => $label) {
                $colKey = $column;
                if (stripos($column, ' AS ') !== false) {
                    $parts = preg_split('/\s+AS\s+/i', $column);
                    $colKey = trim($parts[1] ?? $column, '`\'" ');
                }
                $colKeys[] = $colKey;
            }

            // Count leading non-aggregated data columns
            $dataLeading = 0;
            foreach ($colKeys as $colKey) {
                if (isset($aggregations[$colKey])) {
                    break;
                }
                $dataLeading++;
            }

            // Total colspan = extra columns (bulk, action-start) + non-aggregated data columns
            $leadingCols = $dataLeading;
            if ($bulkActions['enabled']) {
                $leadingCols++;
            }
            if ($actionConfig['position'] === 'start') {
                $leadingCols++;
            }
            $leadingCols = max(1, $leadingCols);

            // Trailing columns use only the data column offset
            $trailingColKeys = array_slice($colKeys, $dataLeading);
            ;

            $html = '';

            foreach ($rows as $row) {
                $html .= "<tr class=\"datatables-agg-row\" data-agg-type=\"{$row['agg']}\" data-agg-scope=\"{$row['scope']}\">\n";

                // Label cell with colspan
                $html .= "<td colspan=\"{$leadingCols}\" style=\"{$boldStyle} text-align: right;\">{$row['label']}</td>\n";

                // Individual cells for remaining columns
                foreach ($trailingColKeys as $colKey) {
                    if (isset($aggregations[$colKey])) {
                        $aggConfig = $aggregations[$colKey];
                        $showThis = ($aggConfig['type'] === $row['agg'] || $aggConfig['type'] === 'both')
                            && ($aggConfig['scope'] === $row['scope'] || $aggConfig['scope'] === 'both');

                        if ($showThis) {
                            $html .= "<td class=\"datatables-agg-cell\" data-agg-column=\"{$colKey}\" "
                                . "data-agg-type=\"{$row['agg']}\" data-agg-scope=\"{$row['scope']}\" "
                                . ">—</td>\n";
                        } else {
                            $html .= "<td></td>\n";
                        }
                    } else {
                        $html .= "<td></td>\n";
                    }
                }

                $html .= "</tr>\n";
            }

            return $html;
        }

        /**
         * Render the JavaScript initialization script
         *
         * Generates a <script> block that initializes the DataTablesJS
         * class on DOMContentLoaded with all configuration passed from PHP.
         * Serializes columns, action groups, bulk actions, CSS classes,
         * and aggregation settings to JSON for the JavaScript constructor.
         * Callbacks are stripped from action configs (not JSON-serializable)
         * but flagged with hasCallback for server-side execution routing.
         *
         * @return string HTML script block with DataTables initialization
         */
        protected function renderInitScript(): string
        {
            $tableName = $this->getTableName();
            $primaryKey = $this->getPrimaryKey();

            // Use unqualified primary key for JavaScript (no table prefix)
            $jsPrimaryKey = strpos($primaryKey, '.') !== false ? explode('.', $primaryKey)[1] : $primaryKey;

            $inlineEditableColumns = json_encode($this->getInlineEditableColumns());
            $bulkActions = $this->getBulkActions();
            $actionConfig = $this->getActionConfig();
            $columns = $this->getColumns();
            $defaultSortColumn = $this->getDefaultSortColumn();
            $defaultSortDirection = $this->getDefaultSortDirection();

            // Process action config for JavaScript serialization
            // Remove PHP callbacks (not JSON-serializable) but preserve the hasCallback flag
            // so JavaScript knows to route these actions to server-side execution
            if (isset($actionConfig['groups'])) {
                foreach ($actionConfig['groups'] as $groupIndex => $group) {
                    if (is_array($group) && !empty($group)) {
                        foreach ($group as $actionKey => $actionData) {
                            if (is_array($actionData) && isset($actionData['callback'])) {
                                $actionConfig['groups'][$groupIndex][$actionKey]['hasCallback'] = true;
                                unset($actionConfig['groups'][$groupIndex][$actionKey]['callback']);
                            }
                        }
                    }
                }
            }

            // Build JavaScript initialization block
            $html = "<script>\n";
            $html .= "var DataTables;\n";
            $html .= "document.addEventListener('DOMContentLoaded', function() {\n";
            $html .= "    DataTables = new DataTablesJS({\n";
            $html .= "        tableName: '{$tableName}',\n";
            $html .= "        primaryKey: '{$jsPrimaryKey}',\n";
            $html .= "        inlineEditableColumns: {$inlineEditableColumns},\n";
            $html .= "        perPage: " . $this->getRecordsPerPage() . ",\n";
            $html .= "        bulkActionsEnabled: " . ($bulkActions['enabled'] ? 'true' : 'false') . ",\n";
            $html .= "        bulkActions: " . json_encode($bulkActions['actions']) . ",\n";
            $html .= "        actionConfig: " . json_encode($actionConfig) . ",\n";
            $html .= "        columns: " . json_encode($columns) . ",\n";
            $html .= "        cssClasses: " . json_encode($this->getCssClasses()) . ",\n";
            $html .= "        defaultSortColumn: '{$defaultSortColumn}',\n";
            $html .= "        defaultSortDirection: '{$defaultSortDirection}',\n";
            $html .= "        theme: '{$this->theme}',\n";
            $html .= "        footerAggregations: " . json_encode($this->getFooterAggregations()) . "\n";

            $html .= "    });\n";
            $html .= "});\n";
            $html .= "</script>\n";

            return $html;
        }
    }
}
