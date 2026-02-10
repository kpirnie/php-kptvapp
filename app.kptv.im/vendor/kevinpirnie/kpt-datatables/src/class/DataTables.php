<?php

declare(strict_types=1);

namespace KPT;

use KPT\Database;
use KPT\Logger;
use Exception;
use RuntimeException;
use InvalidArgumentException;

// Check if class already exists before declaring it
if (! class_exists('KPT\DataTables', false)) {

    /**
     * DataTables - Advanced Database Table Management System
     *
     * A comprehensive table management system with CRUD operations, search, sorting,
     * pagination, bulk actions, and modal forms using multiple UI frameworks. This is the main class
     * that orchestrates all DataTables functionality and provides a fluent interface
     * for configuration.
     *
     * Features:
     * - Full CRUD operations with AJAX support
     * - Advanced search and filtering
     * - Multi-column sorting
     * - Configurable pagination
     * - Bulk actions with custom callbacks
     * - Inline editing capabilities
     * - File upload handling
     * - Responsive design with theme support
     * - Database JOIN support
     * - Multiple UI framework themes (Plain, UIKit, Bootstrap, Tailwind)
     * - Extensive customization options
     *
     * @since   1.0.0
     * @author  Kevin Pirnie <me@kpirnie.com>
     * @package KPT\DataTables
     */
    class DataTables extends Renderer
    {
        /**
         * Constructor - Initialize DataTables with database configuration
         *
         * @param array $dbConfig Database configuration array (optional)
         */
        public function __construct(array $dbConfig = [])
        {
            if (! empty($dbConfig)) {
                $this->dbConfig = $dbConfig;
                $this->initializeDatabase();
            }

            Logger::debug("DataTables instance created successfully");
        }

        /**
         * Initialize database connection from configuration
         *
         * @return void
         */
        private function initializeDatabase(): void
        {
            if (!empty($this->dbConfig)) {
                try {
                    $this->db = new Database((object)$this->dbConfig);
                    Logger::debug("Database connection initialized successfully");
                } catch (Exception $e) {
                    Logger::error("Failed to initialize database connection", ['error' => $e->getMessage()]);
                    $this->db = null;
                }
            }
        }

        /**
         * Load table schema from database and auto-detect field types
         *
         * @return void
         * @throws RuntimeException If database connection is not available
         */
        private function loadTableSchema(): void
        {
            if (!$this->db) {
                throw new RuntimeException('Database connection required before loading schema');
            }

            try {
                Logger::debug("Loading table schema", ['table' => $this->tableName]);

                // Get table structure using DESCRIBE with the fluent interface
                $schema = $this->db->query("DESCRIBE `{$this->tableName}`")->fetch();

                if (!$schema || empty($schema)) {
                    throw new RuntimeException("Table '{$this->tableName}' does not exist or is not accessible");
                }

                Logger::debug("Schema query returned", ['column_count' => count($schema)]);

                $this->tableSchema = [];

                foreach ($schema as $column) {
                    $this->tableSchema[$column->Field] = [
                        'type' => $this->parseColumnType($column->Type),
                        'null' => $column->Null === 'YES',
                        'key' => $column->Key,
                        'default' => $column->Default,
                        'extra' => $column->Extra
                    ];

                    // Auto-detect primary key
                    if ($column->Key === 'PRI') {
                        $this->primaryKey = $column->Field;
                    }
                }

                // If no columns specified, use all non-primary key columns
                if (empty($this->columns)) {
                    foreach ($this->tableSchema as $field => $info) {
                        if ($field !== $this->primaryKey) {
                            $this->columns[$field] = $this->generateColumnLabel($field);
                        }
                    }
                }

                Logger::debug("Table schema loaded successfully", [
                    'columns' => count($this->tableSchema),
                    'primary_key' => $this->primaryKey
                ]);
            } catch (Exception $e) {
                Logger::error("Failed to load table schema", [
                    'table' => $this->tableName,
                    'error' => $e->getMessage()
                ]);
                throw $e;
            }
        }

        /**
         * Parse MySQL column type to appropriate form field type with enhanced detection
         *
         * @param  string $columnType MySQL column type from DESCRIBE
         * @return string HTML form field type
         */
        private function parseColumnType(string $columnType): string
        {
            $type = strtolower($columnType);

            // Handle boolean/checkbox fields first (most specific)
            if (strpos($type, 'tinyint(1)') !== false || strpos($type, 'boolean') !== false || strpos($type, 'bit(1)') !== false) {
                return 'boolean';
            }

            // Handle other integer types
            if (strpos($type, 'int') !== false || strpos($type, 'integer') !== false) {
                return 'number';
            }

            // Handle decimal/float types
            if (strpos($type, 'decimal') !== false || strpos($type, 'float') !== false || strpos($type, 'double') !== false) {
                return 'number';
            }

            // Handle date/time types
            if (strpos($type, 'datetime') !== false || strpos($type, 'timestamp') !== false) {
                return 'datetime-local';
            }
            if (strpos($type, 'date') !== false) {
                return 'date';
            }
            if (strpos($type, 'time') !== false) {
                return 'time';
            }

            // Handle text types
            if (strpos($type, 'text') !== false || strpos($type, 'longtext') !== false || strpos($type, 'mediumtext') !== false) {
                return 'textarea';
            }

            // Handle select2 fields (before enum/select check)
            if (isset($field) && strpos(strtolower($field), 'select2') !== false) {
                return 'select2';
            }

            // Handle enum types
            if (strpos($type, 'enum') !== false) {
                return 'select';
            }

            // Handle image fields (VARCHAR fields with 'image' in name or specific pattern)
            if (
                strpos($type, 'varchar') !== false && (
                    strpos(strtolower($field ?? ''), 'image') !== false ||
                    strpos(strtolower($field ?? ''), 'photo') !== false ||
                    strpos(strtolower($field ?? ''), 'avatar') !== false ||
                    strpos(strtolower($field ?? ''), 'picture') !== false
                )
            ) {
                return 'image';
            }

            // Handle varchar with common patterns
            if (strpos($type, 'varchar') !== false) {
                // Check for email patterns in column names or types
                if (strpos($type, 'email') !== false) {
                    return 'email';
                }
                return 'text';
            }

            // Handle char types
            if (strpos($type, 'char') !== false) {
                return 'text';
            }

            return 'text'; // Default fallback
        }

        /**
         * Generate human-readable column label from database field name
         *
         * @param  string $field Database field name
         * @return string Human-readable label
         */
        private function generateColumnLabel(string $field): string
        {
            return ucwords(str_replace(['_', '-'], ' ', $field));
        }

        /**
         * Sanitize input to prevent injection attacks
         *
         * @param  string $input Raw input string
         * @return string Sanitized string
         */
        private function sanitizeInput(string $input): string
        {
            // Remove any non-alphanumeric characters except underscore, dash, and dot
            return preg_replace('/[^a-zA-Z0-9_\-\.]/', '', trim($input));
        }

        /**
         * Set the UI theme for the DataTable
         *
         * Available themes: 'plain', 'uikit', 'bootstrap', 'tailwind'
         *
         * @param  string $theme      Theme identifier
         * @param  bool   $includeCdn Whether to include CDN links for framework assets
         * @return self Returns self for method chaining
         */
        public function theme(string $theme, bool $includeCdn = true): self
        {
            $validThemes = [
                ThemeManager::THEME_PLAIN,
                ThemeManager::THEME_UIKIT,
                ThemeManager::THEME_BOOTSTRAP,
                ThemeManager::THEME_TAILWIND
            ];

            if (in_array($theme, $validThemes)) {
                $this->theme = $theme;
                $this->includeCdn = $includeCdn;
                $this->themeManager = new ThemeManager($theme);

                // Update default CSS classes based on theme
                $this->updateCssClassesForTheme();

                Logger::debug("DataTables theme set", ['theme' => $theme, 'includeCdn' => $includeCdn]);
            }

            return $this;
        }

        /**
         * Update default CSS classes based on selected theme
         *
         * @return void
         */
        private function updateCssClassesForTheme(): void
        {
            $tm = $this->getThemeManager();

            $this->cssClasses['table'] = $tm->getClass('table.full');
            $this->cssClasses['thead'] = $tm->getClass('thead');
            $this->cssClasses['tbody'] = $tm->getClass('tbody');
            $this->cssClasses['tfoot'] = $tm->getClass('tfoot');
        }

        /**
         * Set database configuration and initialize connection
         *
         * @param  array $config Database configuration array
         * @return self Returns self for method chaining
         */
        public function database(array $config): self
        {
            $this->dbConfig = $config;
            $this->initializeDatabase();
            return $this;
        }

        /**
         * Set the primary database table name and auto-detect schema
         *
         * This method specifies which table will be used for all CRUD operations.
         * The table name will be used in all generated SQL queries. Also automatically
         * loads the table schema to generate forms and validate data.
         *
         * @param  string $tableName The name of the database table
         * @return self Returns self for method chaining
         * @throws RuntimeException If database connection is not available
         */
        public function table(string $tableName): self
        {
            // Check if there's an alias (space followed by alias)
            if (preg_match('/^([a-zA-Z0-9_]+)\s+([a-zA-Z0-9_]+)$/', trim($tableName), $matches)) {
                // Table has alias: store both base table name and full name with alias
                $this->baseTableName = $matches[1];  // Base table name for operations
                $this->tableName = trim($tableName);  // Full name with alias for SELECT queries
            } else {
                // No alias: sanitize and store
                $this->baseTableName = $this->sanitizeInput($tableName);
                $this->tableName = $this->baseTableName;
            }

            // Only load schema if database is available
            if ($this->db) {
                try {
                    // Use base table name for schema loading
                    $originalTableName = $this->tableName;
                    $this->tableName = $this->baseTableName;  // Temporarily set to base name
                    $this->loadTableSchema();
                    $this->tableName = $originalTableName;  // Restore full name with alias
                } catch (Exception $e) {
                    Logger::error("Failed to load table schema", ['table' => $tableName, 'error' => $e->getMessage()]);
                    // Continue without schema - basic functionality will still work
                }
            }

            Logger::debug("DataTables table set", ['table' => $tableName]);
            return $this;
        }

        /**
         * Configure the columns to display in the table with enhanced field configuration
         *
         * Accepts column configurations where the array key is always the database column name.
         * Values can be simple display labels or detailed configuration arrays with type overrides,
         * form field options, CSS classes, and HTML attributes.
         *
         * Examples:
         * - Simple: ['name' => 'Full Name', 'email' => 'Email Address']
         * - Enhanced: ['active' => ['label' => 'Status', 'type' => 'checkbox', 'class' => 'uk-checkbox']]
         * - With options: ['status' => ['label' => 'Status', 'type' => 'select', 'options' => ['active' => 'Active', 'inactive' => 'Inactive']]]
         *
         * @param  array $columns Array of column configurations
         * @return self Returns self for method chaining
         */
        public function columns(array $columns): self
        {
            $this->columns = [];
            foreach ($columns as $column => $config) {
                if (is_string($config)) {
                    $this->columns[$column] = $config;
                } else {
                    $this->columns[$column] = $config['label'] ?? $this->generateColumnLabel($column);

                    // Get the unqualified column name for schema lookup
                    $schemaKey = $column;
                    if (strpos($column, '.') !== false) {
                        $schemaKey = explode('.', $column)[1];
                    }

                    // Store enhanced configuration in schema if available
                    if (isset($this->tableSchema[$schemaKey])) {
                        if (isset($config['type'])) {
                            $this->tableSchema[$schemaKey]['override_type'] = $config['type'];
                        }
                        if (isset($config['options'])) {
                            $this->tableSchema[$schemaKey]['form_options'] = (object)$config['options'];
                        }
                        if (isset($config['class'])) {
                            $this->tableSchema[$schemaKey]['form_class'] = $config['class'];
                        }
                        if (isset($config['attributes'])) {
                            $this->tableSchema[$schemaKey]['form_attributes'] = $config['attributes'];
                        }
                        if (isset($config['placeholder'])) {
                            $this->tableSchema[$schemaKey]['form_placeholder'] = $config['placeholder'];
                        }
                        // Store select2 specific configuration
                        if (isset($config['type']) && $config['type'] === 'select2') {
                            if (isset($config['query'])) {
                                $this->tableSchema[$schemaKey]['select2_query'] = $config['query'];
                            }
                            if (isset($config['min_search_chars'])) {
                                $this->tableSchema[$schemaKey]['select2_min_search_chars'] = $config['min_search_chars'];
                            }
                            if (isset($config['max_results'])) {
                                $this->tableSchema[$schemaKey]['select2_max_results'] = $config['max_results'];
                            }
                        }

                        // ONLY copy if qualified name is different
                        if ($column !== $schemaKey) {
                            $this->tableSchema[$column] = $this->tableSchema[$schemaKey];
                        }
                    }
                }
            }
            Logger::debug("DataTables columns configured", ['column_count' => count($columns)]);
            return $this;
        }

        /**
         * Add a JOIN clause to the main query
         *
         * Allows for complex queries involving multiple tables. Each JOIN is stored
         * and will be applied to both data retrieval and count queries.
         *
         * @param  string $type      JOIN type (INNER, LEFT, RIGHT, FULL OUTER)
         * @param  string $table     Table name to join with (can include alias)
         * @param  string $condition JOIN condition (e.g., 'a.id = b.foreign_id')
         * @return self Returns self for method chaining
         */
        public function join(string $type, string $table, string $condition): self
        {
            $this->joins[] = [
                'type' => strtoupper(trim($type)),    // Just normalize case and trim
                'table' => trim($table),              // DON'T sanitize - preserve spaces for aliases
                'condition' => trim($condition)       // DON'T sanitize - preserve column references
            ];

            Logger::debug("DataTables JOIN added", [
                'type' => $type,
                'table' => $table,
                'condition' => $condition
            ]);

            return $this;
        }

        /**
         * Add WHERE conditions to filter records
         *
         * @param  array $conditions Array of WHERE conditions
         * @return self Returns self for method chaining
         */
        public function where(array $conditions): self
        {
            $this->whereConditions = $conditions;
            Logger::debug("DataTables WHERE conditions set", ['conditions' => $conditions]);
            return $this;
        }

        /**
         * Define which columns can be sorted by users
         *
         * Only columns specified here will have clickable headers with sort indicators.
         * Column names should match the field names used in the database query.
         *
         * @param  array $columns Array of sortable column field names
         * @return self Returns self for method chaining
         */
        public function sortable(array $columns): self
        {
            $this->sortableColumns = array_map([$this, 'sanitizeInput'], $columns);
            Logger::debug("DataTables sortable columns set", ['columns' => $columns]);
            return $this;
        }

        /**
         * Define which columns support inline editing
         *
         * Columns specified here will be double-clickable for inline editing.
         * Only columns that are safe to edit should be included.
         *
         * @param  array $columns Array of inline editable column field names
         * @return self Returns self for method chaining
         */
        public function inlineEditable(array $columns): self
        {
            $this->inlineEditableColumns = array_map([$this, 'sanitizeInput'], $columns);
            Logger::debug("DataTables inline editable columns set", ['columns' => $columns]);
            return $this;
        }

        /**
         * Set the default number of records per page
         *
         * This sets the initial page size when the table loads. Users can still
         * change this using the page size selector if enabled.
         *
         * @param  int $count Number of records to show per page
         * @return self Returns self for method chaining
         */
        public function perPage(int $count): self
        {
            $this->recordsPerPage = max(1, $count);
            Logger::debug("DataTables records per page set", ['count' => $count]);
            return $this;
        }

        /**
         * Configure available page size options
         *
         * Sets the options available in the page size selector dropdown.
         * The includeAll parameter determines if an "All records" option is shown.
         *
         * @param  array $options    Array of page size options (e.g., [10, 25, 50, 100])
         * @param  bool  $includeAll Whether to include an "ALL" records option
         * @return self Returns self for method chaining
         */
        public function pageSizeOptions(array $options, bool $includeAll = true): self
        {
            $this->pageSizeOptions = array_map('intval', $options);
            $this->includeAllOption = $includeAll;

            Logger::debug(
                "DataTables page size options set",
                [
                    'options' => $options,
                    'include_all' => $includeAll
                ]
            );

            return $this;
        }

        /**
         * Enable or disable search functionality
         *
         * Controls whether the search input and column selector are displayed.
         * When enabled, provides both global and column-specific searching.
         *
         * @param  bool $enabled Whether search functionality should be enabled
         * @return self Returns self for method chaining
         */
        public function search(bool $enabled = true): self
        {
            $this->searchEnabled = $enabled;
            Logger::debug("DataTables search configured", ['enabled' => $enabled]);
            return $this;
        }

        /**
         * Configure bulk actions functionality
         *
         * Enables bulk operations on multiple selected records. Custom actions can
         * be defined with callback functions for complex operations.
         *
         * @param  bool  $enabled Whether to enable bulk actions
         * @param  array $actions Array of custom bulk action configurations
         * @return self Returns self for method chaining
         */
        public function bulkActions(bool $enabled = true, array $actions = []): self
        {
            $this->bulkActions['enabled'] = $enabled;

            // Merge custom actions with default actions
            if (!empty($actions)) {
                $this->bulkActions['actions'] = array_merge($this->bulkActions['actions'], $actions);
            }

            Logger::debug(
                "DataTables bulk actions configured",
                [
                    'enabled' => $enabled,
                    'actions' => array_keys($this->bulkActions['actions'])
                ]
            );

            return $this;
        }

        /**
         * Configure action groups with separators and custom actions
         *
         * @param  array $groups Array of action groups, each group is an array of actions
         * @return self Returns self for method chaining
         */
        public function actionGroups(array $groups): self
        {
            $this->actionConfig['groups'] = $groups;

            Logger::debug("DataTables action groups configured", ['group_count' => count($groups)]);
            return $this;
        }

        /**
         * Configure action buttons and their placement
         *
         * Controls the edit/delete buttons and any custom action buttons.
         * Actions can be positioned at the start or end of each table row.
         *
         * @param  string $position      Position of action column ('start' or 'end')
         * @param  bool   $showEdit      Whether to show the edit button
         * @param  bool   $showDelete    Whether to show the delete button
         * @param  array  $customActions Array of custom action button configurations
         * @return self Returns self for method chaining
         */
        public function actions(string $position = 'end', bool $showEdit = true, bool $showDelete = true, array $customActions = []): self
        {
            $this->actionConfig = [
                'position' => $position,
                'show_edit' => $showEdit,
                'show_delete' => $showDelete,
                'custom_actions' => $customActions
            ];

            Logger::debug("DataTables actions configured", $this->actionConfig);
            return $this;
        }

        /**
         * Set CSS class for the main table element
         *
         * Allows customization of the table's appearance using CSS classes.
         * Default uses UIKit3 table classes for styling.
         *
         * @param  string $class CSS class string for the table element
         * @return self Returns self for method chaining
         */
        public function tableClass(string $class): self
        {
            $this->cssClasses['table'] = $class;
            return $this;
        }

        /**
         * Set base CSS class for table rows
         *
         * This class will be combined with the record ID to create unique row classes.
         * For example, if $class is 'highlight' and record ID is 123, the final
         * class will be 'highlight-123'.
         *
         * @param  string $class Base CSS class for table rows
         * @return self Returns self for method chaining
         */
        public function rowClass(string $class): self
        {
            $this->cssClasses['tr'] = $class;
            return $this;
        }

        /**
         * Set CSS classes for specific columns
         *
         * Allows individual styling of table columns. The array keys should match
         * column names from the columns configuration.
         *
         * @param  array $classes Array of column name => CSS class mappings
         * @return self Returns self for method chaining
         */
        public function columnClasses(array $classes): self
        {
            $this->cssClasses['columns'] = $classes;
            return $this;
        }

        /**
         * Set the primary key column name
         *
         * Specifies which column serves as the unique identifier for records.
         * This is used for edit, delete, and bulk operations.
         *
         * @param  string $column Name of the primary key column
         * @return self Returns self for method chaining
         */
        public function primaryKey(string $column): self
        {
            $this->primaryKey = $this->sanitizeInput($column);
            Logger::debug("DataTables primary key set", ['column' => $column]);
            return $this;
        }

        /**
         * Configure file upload settings
         *
         * Sets up file upload validation including allowed file types, size limits,
         * and upload destination. Used for form fields with type 'file'.
         *
         * @param  string $uploadPath        Directory path where files will be uploaded
         * @param  array  $allowedExtensions Array of allowed file extensions (without dots)
         * @param  int    $maxFileSize       Maximum file size in bytes
         * @return self Returns self for method chaining
         */
        public function fileUpload(string $uploadPath = 'uploads/', array $allowedExtensions = [], int $maxFileSize = 10485760): self
        {
            $this->fileUploadConfig = [
                'upload_path' => rtrim($uploadPath, '/') . '/',    // Ensure trailing slash
                'allowed_extensions' => !empty($allowedExtensions) ? $allowedExtensions : $this->fileUploadConfig['allowed_extensions'],
                'max_file_size' => $maxFileSize
            ];

            Logger::debug("DataTables file upload configured", $this->fileUploadConfig);
            return $this;
        }

        /**
         * Render the complete DataTable HTML
         *
         * Generates all HTML, CSS includes, JavaScript includes, and initialization code
         * needed for a fully functional DataTable. This is the main method that produces
         * the final output.
         *
         * @return string Complete HTML output ready for display
         * @throws RuntimeException If required configuration is missing
         */
        public function renderDataTableComponent(): string
        {

            try {
                // Validate required configuration
                if (empty($this->tableName)) {
                    throw new RuntimeException('Table name must be set before rendering');
                }

                if (!$this->db) {
                    throw new RuntimeException('Database connection required before rendering');
                }

                // Generate HTML directly since we now extend Renderer
                $html = $this->renderContainer();      // Main table container
                $html .= $this->renderModals();         // Add/Edit/Delete modals (auto-generated)
                $html .= $this->renderInitScript();     // JavaScript initialization

                return $html;
            } catch (Exception $e) {
                Logger::error("DataTables render failed", ['message' => $e->getMessage()]);
                throw $e;
            }
        }

        /**
         * Add a calculated column to the table
         *
         * Supports operators: +, -, *, /, %
         *
         * @param  string $alias    Column alias for the result
         * @param  string $label    Display label
         * @param  array  $columns  Array of numeric column names to calculate
         * @param  string $operator Mathematical operator (+, -, *, /, %)
         * @return self Returns self for method chaining
         */
        public function calculatedColumn(string $alias, string $label, array $columns, string $operator = '+'): self
        {
            $validOperators = ['+', '-', '*', '/', '%'];
            if (!in_array($operator, $validOperators)) {
                throw new \InvalidArgumentException("Invalid operator: {$operator}. Allowed: " . implode(', ', $validOperators));
            }

            if (count($columns) < 2) {
                throw new \InvalidArgumentException('At least two columns are required for a calculation');
            }

            $expressionParts = [];
            foreach ($columns as $col) {
                $expressionParts[] = strpos($col, '.') !== false ? $col : "`{$col}`";
            }
            $expression = implode(" {$operator} ", $expressionParts);

            $this->calculatedColumns[$alias] = [
                'label' => $label,
                'expression' => $expression,
                'columns' => $columns,
                'operator' => $operator,
            ];

            $this->columns["{$expression} AS `{$alias}`"] = $label;

            Logger::debug("Calculated column added", ['alias' => $alias, 'expression' => $expression]);
            return $this;
        }

        /**
         * Add a calculated column with a custom SQL expression
         *
         * @param  string $alias      Column alias for the result
         * @param  string $label      Display label
         * @param  string $expression Raw SQL expression (e.g., '(price * quantity) - discount')
         * @return self Returns self for method chaining
         */
        public function calculatedColumnRaw(string $alias, string $label, string $expression): self
        {
            $this->calculatedColumns[$alias] = [
                'label' => $label,
                'expression' => $expression,
                'columns' => [],
                'operator' => 'raw',
            ];

            $this->columns["{$expression} AS `{$alias}`"] = $label;

            Logger::debug("Raw calculated column added", ['alias' => $alias, 'expression' => $expression]);
            return $this;
        }

        /**
         * Configure footer aggregation for a column
         *
         * @param  string $column Column name to aggregate
         * @param  string $type   Aggregation type: 'sum', 'avg', or 'both'
         * @param  string $scope  Scope: 'page', 'all', or 'both'
         * @return self Returns self for method chaining
         */
        public function footerAggregate(string $column, string $type = 'sum', string $scope = 'both', string $label = ''): self
        {
            $validTypes = ['sum', 'avg', 'both'];
            $validScopes = ['page', 'all', 'both'];

            if (!in_array($type, $validTypes)) {
                throw new \InvalidArgumentException("Invalid aggregation type: {$type}. Allowed: " . implode(', ', $validTypes));
            }

            if (!in_array($scope, $validScopes)) {
                throw new \InvalidArgumentException("Invalid scope: {$scope}. Allowed: " . implode(', ', $validScopes));
            }

            $this->footerAggregations[$column] = [
                'type' => $type,
                'scope' => $scope,
                'label' => $label,
            ];

            Logger::debug("Footer aggregation configured", ['column' => $column, 'type' => $type, 'scope' => $scope]);
            return $this;
        }

        /**
         * Configure footer aggregation for multiple columns at once
         *
         * @param  array  $columns Array of column names
         * @param  string $type    Aggregation type: 'sum', 'avg', or 'both'
         * @param  string $scope   Scope: 'page', 'all', or 'both'
         * @return self Returns self for method chaining
         */
        public function footerAggregateColumns(array $columns, string $type = 'sum', string $scope = 'both', string $label = ''): self
        {
            foreach ($columns as $column) {
                $this->footerAggregate($column, $type, $scope, $label);
            }
            return $this;
        }

        /**
         * Set GROUP BY clause for aggregate queries
         *
         * @param  string $column Column or expression to group by
         * @return self Returns self for method chaining
         */
        public function groupBy(string $column): self
        {
            $this->groupBy = $column;
            Logger::debug("DataTables GROUP BY set", ['column' => $column]);
            return $this;
        }

        /**
         * Handle incoming AJAX requests
         *
         * Processes all AJAX requests for DataTables operations including data fetching,
         * CRUD operations, bulk actions, and file uploads. This method should be called
         * before any HTML output when AJAX requests are detected. Always handled internally.
         *
         * @return void (method outputs JSON and exits)
         */
        public function handleAjax(): void
        {
            try {
                // Extract and sanitize the action from POST or GET parameters
                $action = $this->sanitizeInput($_POST['action'] ?? $_GET['action'] ?? '');

                if (empty($action)) {
                    throw new InvalidArgumentException('No action specified');
                }

                Logger::debug("DataTables handling AJAX request", ['action' => $action]);

                // Delegate to the AJAX handler
                $handler = new AjaxHandler($this);
                $handler->handle($action);
            } catch (Exception $e) {
                // Log the error and return error response
                Logger::error("DataTables AJAX error", ['message' => $e->getMessage()]);
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
                exit;
            }
        }

        /**
         * Configure the add form with custom fields
         *
         * @param  string $title Form title
         * @param  array  $fields Field configurations
         * @param  bool   $ajax Whether form should use AJAX submission
         * @return self Returns self for method chaining
         */
        public function addForm(string $title, array $fields, bool $ajax = true, string $class = ''): self
        {
            $this->addFormConfig = [
                'title' => $title,
                'fields' => $fields,
                'ajax' => $ajax,
                'class' => $class
            ];

            Logger::debug("DataTables add form configured", ['field_count' => count($fields)]);
            return $this;
        }

        /**
         * Configure the edit form with custom fields
         *
         * @param  string $title Form title
         * @param  array  $fields Field configurations
         * @param  bool   $ajax Whether form should use AJAX submission
         * @return self Returns self for method chaining
         */
        public function editForm(string $title, array $fields, bool $ajax = true, string $class = ''): self
        {
            $this->editFormConfig = [
                'title' => $title,
                'fields' => $fields,
                'ajax' => $ajax,
                'class' => $class
            ];

            Logger::debug("DataTables edit form configured", ['field_count' => count($fields)]);
            return $this;
        }

        /**
         * Set default sort column and direction
         *
         * @param  string $column    Column name to sort by default
         * @param  string $direction Sort direction ('ASC' or 'DESC')
         * @return self Returns self for method chaining
         */
        public function defaultSort(string $column, string $direction = 'ASC'): self
        {
            $this->defaultSortColumn = $this->sanitizeInput($column);
            $this->defaultSortDirection = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';

            Logger::debug("DataTables default sort set", [
                'column' => $column,
                'direction' => $this->defaultSortDirection
            ]);

            return $this;
        }

        /**
         * Get CSS includes for the current theme
         *
         * Generates the necessary link tags for CSS files, including optional
         * framework CDN links based on the configured theme.
         *
         * @param  string $theme        Theme identifier
         * @param  bool   $includeCdn   Whether to include CDN links (overrides instance setting)
         * @param  bool   $useMinified  Whether to use minified versions
         * @return string HTML with CSS includes
         */
        public static function getCssIncludes(string $theme = 'uikit', bool $includeCdn = true, bool $useMinified = false): string
        {
            $tm = new ThemeManager($theme);
            return $tm->getCssIncludes($includeCdn, $useMinified);
        }

        /**
         * Render JavaScript file includes
         *
         * Generates the necessary script tags for JavaScript files.
         * Files are loaded from the vendor directory structure.
         * Includes framework CDN links based on the configured theme.
         *
         * @param  string $theme      Theme identifier
         * @param  bool   $includeCdn Whether to include CDN links
         * @param  bool   $useMinified Whether to include the minified js
         * @return string HTML with JavaScript includes
         */
        public static function getJsIncludes(string $theme = 'uikit', bool $includeCdn = true, bool $useMinified = false): string
        {
            $tm = new ThemeManager($theme);
            $html = "<!-- DataTables JavaScript -->\n";

            // Include framework JS from CDN if enabled
            if ($includeCdn) {
                $html .= $tm->getJsIncludes(true, $useMinified);
            }

            // if we are minifying
            if ($useMinified) {
                // Include main DataTables JS
                $html .= "<script src=\"/vendor/kevinpirnie/kpt-datatables/src/assets/js/dist/kpt-datatables.min.js\" defer></script>\n";
                $html .= "<script src=\"/vendor/kevinpirnie/kpt-datatables/src/assets/js/dist/select2.min.js\" defer></script>\n";

                // otherwise
            } else {
                // Include theme helper for plain/tailwind/bootstrap themes
                if (in_array($theme, [ThemeManager::THEME_PLAIN, ThemeManager::THEME_TAILWIND, ThemeManager::THEME_BOOTSTRAP])) {
                    $html .= "<script src=\"/vendor/kevinpirnie/kpt-datatables/src/assets/js/theme-helpers.js\" defer></script>\n";
                }

                // Include main DataTables JS
                $html .= "<script src=\"/vendor/kevinpirnie/kpt-datatables/src/assets/js/datatables.js\" defer></script>\n";
                $html .= "<script src=\"/vendor/kevinpirnie/kpt-datatables/src/assets/js/select2.js\" defer></script>\n";
            }

            return $html;
        }

        /**
         * Render bulk actions component
         *
         * @return string HTML bulk actions controls
         */
        public function renderBulkActionsComponent(): string
        {
            return $this->renderBulkActions($this->getBulkActions());
        }

        /**
         * Render search form component
         *
         * @return string HTML search form elements
         */
        public function renderSearchFormComponent(): string
        {
            return $this->renderSearchForm();
        }

        /**
         * Render page size selector component
         *
         * @return string HTML page size selector
         */
        public function renderPageSizeSelectorComponent(bool $asButtonGroup = false): string
        {
            return $this->renderPageSizeSelector($asButtonGroup);
        }

        /**
         * Render pagination component
         *
         * @return string HTML pagination section
         */
        public function renderPaginationComponent(): string
        {
            return $this->renderPagination();
        }
    }
}
