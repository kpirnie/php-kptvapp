<?php

declare(strict_types=1);

namespace KPT;

use KPT\Logger;
use Exception;
use InvalidArgumentException;

// Check if class already exists before declaring it
if (! class_exists('KPT\AjaxHandler', false)) {

    /**
     * AjaxHandler - Handles AJAX Requests for DataTables
     *
     * This class processes all AJAX requests for DataTables operations including
     * data fetching, CRUD operations, bulk actions, inline editing, and file uploads.
     * It acts as the main controller for server-side operations with enhanced security
     * and input sanitization. Always handled internally, never accessible from public files.
     *
     * @since   1.0.0
     * @author  Kevin Pirnie <me@kpirnie.com>
     * @package KPT\DataTables
     */
    class AjaxHandler
    {
        /**
         * DataTables instance containing configuration and database access
         *
         * @var DataTables
         */
        private DataTables $dataTable;

        /**
         * Constructor - Initialize the AJAX handler
         *
         * @param DataTables $dataTable The DataTables instance with configuration
         */
        public function __construct(DataTables $dataTable)
        {
            $this->dataTable = $dataTable;
        }

        /**
         * Main AJAX request dispatcher with enhanced security validation
         *
         * Routes incoming AJAX requests to the appropriate handler method based
         * on the action parameter. This is the main entry point for all AJAX operations.
         * Includes whitelist validation for security.
         *
         * @param  string $action The action to perform (fetch_data, add_record, edit_record, etc.)
         * @return void
         * @throws InvalidArgumentException If the action is unknown or invalid
         */
        public function handle(string $action): void
        {
            // Whitelist of allowed actions for security
            $allowedActions = [
                'fetch_data',
                'add_record',
                'edit_record',
                'delete_record',
                'bulk_action',
                'inline_edit',
                'upload_file',
                'fetch_record',
                'action_callback',
                'fetch_aggregations',
                'fetch_select2_options',
            ];

            if (!in_array($action, $allowedActions)) {
                throw new InvalidArgumentException("Invalid action: {$action}");
            }

            // Route the request to the appropriate handler method
            switch ($action) {
                case 'fetch_data':
                    // Handle data retrieval for table display
                    $this->handleFetchData();
                    break;
                case 'fetch_record':
                    // Handle single record fetch for editing
                    $this->handleFetchRecord();
                    break;
                case 'add_record':
                    // Handle new record creation
                    $this->handleAddRecord();
                    break;
                case 'edit_record':
                    // Handle existing record updates
                    $this->handleEditRecord();
                    break;
                case 'delete_record':
                    // Handle single record deletion
                    $this->handleDeleteRecord();
                    break;
                case 'bulk_action':
                    // Handle bulk operations on multiple records
                    $this->handleBulkAction();
                    break;
                case 'inline_edit':
                    // Handle inline field editing
                    $this->handleInlineEdit();
                    break;
                case 'upload_file':
                    // Handle standalone file uploads
                    $this->handleFileUpload();
                    break;
                case 'action_callback':
                    // Handle action callbacks with full row data
                    $this->handleActionCallback();
                    break;
                case 'fetch_aggregations':
                    // Handle calculation aggregations
                    $this->handleFetchAggregations();
                    break;
                case 'fetch_select2_options':
                    // Handle Select2 options fetch
                    $this->handleFetchSelect2Options();
                    break;
            }
        }

        /**
         * Handle standalone file upload requests
         *
         * Processes file uploads that are sent separately from form submissions.
         * Validates file type, size, and moves file to configured upload directory.
         *
         * @return void (outputs JSON and exits)
         * @throws InvalidArgumentException If no file is uploaded
         */
        private function handleFileUpload(): void
        {
            // Check if file was uploaded
            if (!isset($_FILES['file'])) {
                throw new InvalidArgumentException('No file uploaded');
            }

            // Process the uploaded file
            $file = $_FILES['file'];
            $uploadResult = $this->uploadFile($file);

            // Send JSON response with upload result
            header('Content-Type: application/json');
            echo json_encode($uploadResult);
            exit;
        }

        /**
         * Process file uploads in form data
         *
         * Scans $_FILES for uploaded files and processes them, updating the form data
         * with the file paths. Used during add/edit record operations.
         *
         * @param  array $data Form data to process
         * @return array Updated form data with file paths
         */
        private function processFileUploads(array $data): array
        {
            // Loop through all uploaded files
            foreach ($_FILES as $fieldName => $file) {
                // Handle image field uploads (remove -file suffix)
                $actualFieldName = $fieldName;
                if (strpos($fieldName, '-file') !== false) {
                    $actualFieldName = str_replace('-file', '', $fieldName);
                }

                if ($file['error'] === UPLOAD_ERR_OK) {
                    $uploadResult = $this->uploadFile($file);

                    if ($uploadResult['success']) {
                        $data[$actualFieldName] = $uploadResult['file_name']; // Just filename, not full path
                    }
                }
            }

            return $data;
        }

        /**
         * Upload a single file with enhanced validation
         *
         * Handles the complete file upload process including validation of file size,
         * extension, directory creation, and file movement with security checks.
         *
         * @param  array $file File array from $_FILES
         * @return array Upload result with success status, file path, and message
         */
        private function uploadFile(array $file): array
        {
            // Get upload configuration
            $config = $this->dataTable->getFileUploadConfig();

            // Validate file size
            if ($file['size'] > $config['max_file_size']) {
                return [
                    'success' => false,
                    'message' => 'File size exceeds maximum allowed size'
                ];
            }

            // Extract and validate file extension
            $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($extension, $config['allowed_extensions'])) {
                return [
                    'success' => false,
                    'message' => 'File type not allowed'
                ];
            }

            // Ensure upload directory exists
            if (!is_dir($config['upload_path'])) {
                // Create directory with appropriate permissions
                mkdir($config['upload_path'], 0755, true);
            }

            // Generate unique filename with optional prepend
            $prepend = $_POST['prepend'] ?? '';
            $fileName = $prepend ? $prepend . '_' . uniqid() . '_' . basename($file['name']) : uniqid() . '_' . basename($file['name']);
            $filePath = $config['upload_path'] . $fileName;

            // Attempt to move uploaded file to final destination
            if (move_uploaded_file($file['tmp_name'], $filePath)) {
                return [
                    'success' => true,
                    'file_path' => $filePath,
                    'file_name' => $fileName,
                    'message' => 'File uploaded successfully'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Failed to move uploaded file'
                ];
            }
        }

        /**
         * Build SELECT query with filtering, sorting, and pagination
         *
         * Constructs a complete SELECT query based on the DataTables configuration
         * and request parameters. Handles JOINs, WHERE conditions, ORDER BY, and LIMIT.
         * All inputs are sanitized and validated.
         *
         * @param  string $search        Search term to filter results
         * @param  string $searchColumn  Specific column to search (or 'all' for global search)
         * @param  string $sortColumn    Column to sort by
         * @param  string $sortDirection Sort direction (ASC or DESC)
         * @param  int    $page          Page number for pagination
         * @param  int    $perPage       Number of records per page (0 for all records)
         * @return array Array with 'sql' query string and 'params' array
         */
        private function buildSelectQuery(string $search = '', string $searchColumn = '', string $sortColumn = '', string $sortDirection = 'ASC', int $page = 1, int $perPage = 25): array
        {
            // Build SELECT field list from column configuration
            $selectFields = [];
            $columns = $this->dataTable->getColumns();

            // If no columns configured, get all columns from schema
            if (empty($columns)) {
                $schema = $this->dataTable->getTableSchema();
                if (!empty($schema)) {
                    foreach ($schema as $columnName => $info) {
                        $selectFields[] = "`{$columnName}`";
                    }
                } else {
                    // Last resort - select all
                    $selectFields[] = "*";
                }
            } else {
                foreach ($columns as $column => $label) {
                    $selectFields[] = "`{$column}`";
                }
            }

            // Start building the SQL query
            $sql = "SELECT " . implode(', ', $selectFields) . " FROM `{$this->dataTable->getTableName()}`";
            $params = [];

            // Add JOIN clauses from configuration
            foreach ($this->dataTable->getJoins() as $join) {
                $sql .= " {$join['type']} JOIN {$join['table']} ON {$join['condition']}";
            }

            // Add WHERE clause for search functionality
            $searchConditions = [];
            foreach ($this->dataTable->getColumns() as $column => $label) {
                $searchConditions[] = "`{$column}` LIKE ?";
                $params[] = "%{$search}%";
            }

            // Only add WHERE clause if we have searchable columns
            if (!empty($searchConditions)) {
                $sql .= " WHERE " . implode(' OR ', $searchConditions);
            }

            // Add ORDER BY clause for sorting
            if (!empty($sortColumn) && in_array($sortColumn, $this->dataTable->getSortableColumns())) {
                // Validate and normalize sort direction
                $direction = strtoupper($sortDirection) === 'DESC' ? 'DESC' : 'ASC';
                $sql .= " ORDER BY `{$sortColumn}` {$direction}";
            }

            // Add LIMIT clause for pagination
            if ($perPage > 0) {
                // Calculate offset for pagination
                $offset = ($page - 1) * $perPage;
                $sql .= " LIMIT {$offset}, {$perPage}";
            }

            return ['sql' => $sql, 'params' => $params];
        }

        /**
         * Build COUNT query for pagination metadata
         *
         * Constructs a COUNT query to determine total number of records that match
         * the current search/filter criteria. Used for pagination calculations.
         *
         * @param  string $search       Search term to filter results
         * @param  string $searchColumn Specific column to search (or 'all' for global search)
         * @return array Array with 'sql' query string and 'params' array
         */
        private function buildCountQuery(string $search = '', string $searchColumn = ''): array
        {
            // Build basic COUNT query
            $sql = "SELECT COUNT(*) as total FROM `{$this->dataTable->getTableName()}`";
            $params = [];

            // Add same JOIN clauses as the main query
            foreach ($this->dataTable->getJoins() as $join) {
                $sql .= " {$join['type']} JOIN {$join['table']} ON {$join['condition']}";
            }

            // Add same WHERE conditions as the main query
            if (!empty($search)) {
                $columns = $this->dataTable->getColumns();

                // Use schema if no columns configured
                if (empty($columns)) {
                    $schema = $this->dataTable->getTableSchema();
                    $columns = array_keys($schema);
                } else {
                    $columns = array_keys($columns);
                }

                // Global search across all columns
                $searchConditions = [];
                foreach ($columns as $column) {
                    $searchConditions[] = "`{$column}` LIKE ?";
                    $params[] = "%{$search}%";
                }

                // Only add WHERE clause if we have searchable columns
                if (!empty($searchConditions)) {
                    $sql .= " WHERE " . implode(' OR ', $searchConditions);
                }
            }

            return ['sql' => $sql, 'params' => $params];
        }

        /**
         * Handle data fetching for table display with enhanced input sanitization
         *
         * Processes requests for table data including pagination, sorting, and searching.
         * Builds and executes SQL queries based on the request parameters and returns
         * JSON response with data and metadata. All inputs are sanitized and validated.
         *
         * @return void (outputs JSON and exits)
         */
        private function handleFetchData(): void
        {
            // Extract and validate pagination parameters with bounds checking
            $page = $this->validateInteger($_GET['page'] ?? 1, 1);
            $perPage = $this->validateInteger($_GET['per_page'] ?? $this->dataTable->getRecordsPerPage(), 0, 1000);

            // Sanitize search inputs with proper escaping
            $search = $this->sanitizeSearchInput($_GET['search'] ?? '');
            $searchColumn = $this->sanitizeColumnName($_GET['search_column'] ?? '');

            // Sanitize and validate sort inputs
            $sortColumn = $this->sanitizeColumnName($_GET['sort_column'] ?? '');
            $sortDirection = $this->sanitizeSortDirection($_GET['sort_direction'] ?? 'ASC');

            // Validate sort column exists in configuration
            if (!empty($sortColumn)) {
                $validColumns = array_keys($this->dataTable->getColumns());
                $sortColumnValid = in_array($sortColumn, $validColumns);

                // If not found, check if it matches an alias
                if (!$sortColumnValid) {
                    foreach ($validColumns as $column) {
                        if (stripos($column, ' AS ') !== false) {
                            $parts = explode(' AS ', $column);
                            if (count($parts) === 2) {
                                $aliasName = trim($parts[1], '`\'" ');
                                if ($aliasName === $sortColumn) {
                                    $sortColumn = $column; // Use full expression for SQL
                                    $sortColumnValid = true;
                                    break;
                                }
                            }
                        }
                    }
                }

                if (!$sortColumnValid) {
                    $sortColumn = ''; // Reset invalid column
                }
            }

            // Execute data query using fluent interface
            $data = $this->executeDataQuery($search, $searchColumn, $sortColumn, $sortDirection, $page, $perPage);

            // Execute count query using fluent interface
            $total = $this->executeCountQuery($search, $searchColumn);

            // Extract total count from result
            $totalRecords = $total ? $total->total : 0;

            // Calculate total pages (handle division by zero for "all" records)
            $totalPages = $perPage === 0 ? 1 : ceil($totalRecords / $perPage);

            // Send JSON response with data and metadata
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'data' => $data ?: [], // Ensure array even if no data
                'total' => $totalRecords,
                'page' => $page,
                'per_page' => $perPage,
                'total_pages' => $totalPages
            ]);

            // Make sure we exit so nothing else gets outputted
            exit;
        }

        /**
         * Execute data query with filtering, sorting, and pagination using fluent interface
         *
         * Builds and executes a SELECT query based on the provided parameters. Handles
         * table joins, WHERE conditions, search filtering, sorting, and pagination.
         * Uses the DataTables configuration to determine which fields to select and
         * how to construct the query.
         *
         * @param  string $search        Global search term to filter results across searchable columns
         * @param  string $searchColumn  Specific column to search in (use 'all' for global search)
         * @param  string $sortColumn    Column name to sort results by
         * @param  string $sortDirection Sort direction - 'ASC' for ascending, 'DESC' for descending
         * @param  int    $page          Page number for pagination (1-based)
         * @param  int    $perPage       Number of records per page (0 for all records)
         * @return mixed                 Query result object or false on failure
         * @since  1.0.0
         */
        private function executeDataQuery(string $search = '', string $searchColumn = '', string $sortColumn = '', string $sortDirection = 'ASC', int $page = 1, int $perPage = 25): mixed
        {
            $selectFields = $this->getSelectFields();
            $tableName = $this->dataTable->getTableName();

            if (strpos($tableName, ' ') !== false) {
                $sql = "SELECT " . implode(', ', $selectFields) . " FROM {$tableName}";
            } else {
                $sql = "SELECT " . implode(', ', $selectFields) . " FROM `{$tableName}`";
            }

            foreach ($this->dataTable->getJoins() as $join) {
                $sql .= " {$join['type']} JOIN {$join['table']} ON {$join['condition']}";
            }

            $params = [];

            // Add WHERE conditions first
            $whereClause = $this->buildWhereClause($this->dataTable->getWhereConditions(), $params);
            $hasWhere = !empty($whereClause);

            if ($hasWhere) {
                $sql .= $whereClause;
            }

            // Add search conditions
            if (!empty($search)) {
                $searchConditions = [];

                if (!empty($searchColumn) && $searchColumn !== 'all') {
                    if (strpos($searchColumn, '.') !== false) {
                        $searchConditions[] = "{$searchColumn} LIKE ?";
                    } else {
                        $searchConditions[] = "`{$searchColumn}` LIKE ?";
                    }
                    $params[] = "%{$search}%";
                } else {
                    foreach ($this->dataTable->getColumns() as $column => $label) {
                        // For aliased columns, use only the expression part (before AS) in WHERE clause
                        $searchColumn = $column;
                        if (stripos($column, ' AS ') !== false) {
                            $parts = explode(' AS ', $column);
                            $searchColumn = trim($parts[0]); // Use only the expression part
                        }

                        if (strpos($searchColumn, '.') !== false) {
                            $searchConditions[] = "{$searchColumn} LIKE ?";
                        } else {
                            $searchConditions[] = "`{$searchColumn}` LIKE ?";
                        }
                        $params[] = "%{$search}%";
                    }
                }

                if (!empty($searchConditions)) {
                    $sql .= ($hasWhere ? ' AND ' : ' WHERE ') . '(' . implode(' OR ', $searchConditions) . ')';
                }
            }

            // Add GROUP BY clause if configured
            $groupBy = $this->dataTable->getGroupBy();
            if (!empty($groupBy)) {
                $sql .= strpos($groupBy, '.') !== false ? " GROUP BY {$groupBy}" : " GROUP BY `{$groupBy}`";
            }

            // Check if sortColumn is sortable (handle both full expressions and aliases)
            $isSortable = false;
            if (!empty($sortColumn)) {
                $sortableColumns = $this->dataTable->getSortableColumns();

                if (in_array($sortColumn, $sortableColumns)) {
                    $isSortable = true;
                } else {
                    // Check if this is a full expression that matches a sortable alias
                    if (stripos($sortColumn, ' AS ') !== false) {
                        $parts = explode(' AS ', $sortColumn);
                        if (count($parts) === 2) {
                            $aliasName = trim($parts[1], '`\'" ');
                            if (in_array($aliasName, $sortableColumns)) {
                                $isSortable = true;
                            }
                        }
                    }
                }
            }

            if ($isSortable) {
                $direction = strtoupper($sortDirection) === 'DESC' ? 'DESC' : 'ASC';

                // Handle aliases - if sorting by an alias, use just the alias name in ORDER BY
                if (stripos($sortColumn, ' AS ') !== false) {
                    $parts = explode(' AS ', $sortColumn);
                    $aliasName = trim($parts[1], '`\'" ');
                    $sql .= " ORDER BY `{$aliasName}` {$direction}";
                } else if (strpos($sortColumn, '.') !== false) {
                    $sql .= " ORDER BY {$sortColumn} {$direction}";
                } else {
                    $sql .= " ORDER BY `{$sortColumn}` {$direction}";
                }
            }

            if ($perPage > 0) {
                $offset = ($page - 1) * $perPage;
                $sql .= " LIMIT {$offset}, {$perPage}";
            }

            $query = $this->dataTable->getDatabase()->query($sql);
            if (!empty($params)) {
                $query->bind($params);
            }
            $data = $query->fetch();

            // Fetch select2 labels for display
            if ($data) {
                $columns = $this->dataTable->getColumns();
                $tableSchema = $this->dataTable->getTableSchema();

                foreach ($columns as $column => $label) {
                    $schemaKey = strpos($column, '.') !== false ? explode('.', $column)[1] : $column;
                    $fieldInfo = $tableSchema[$schemaKey] ?? [];

                    if (isset($fieldInfo['override_type']) && $fieldInfo['override_type'] === 'select2') {
                        $query = $fieldInfo['select2_query'] ?? '';
                        if ($query) {
                            $labelMap = $this->fetchSelect2Labels($data, $schemaKey, $query);

                            // Replace IDs with labels in data
                            foreach ($data as &$row) {
                                if (isset($row->$schemaKey) && isset($labelMap[$row->$schemaKey])) {
                                    $row->{$schemaKey . '_label'} = $labelMap[$row->$schemaKey];
                                }
                            }
                        }
                    }
                }
            }

            return $data;
        }


        /**
         * Execute count query for pagination metadata using fluent interface
         *
         * Builds and executes a COUNT query that matches the same filtering conditions
         * as the main data query. Used to determine total number of records for
         * pagination calculations. Includes the same JOINs and WHERE conditions
         * as the data query but returns only the count.
         *
         * @param  string $search       Global search term to filter results
         * @param  string $searchColumn Specific column to search in (use 'all' for global search)
         * @return mixed                Query result object containing total count or false on failure
         * @since  1.0.0
         */
        private function executeCountQuery(string $search = '', string $searchColumn = ''): mixed
        {
            $tableName = $this->dataTable->getTableName();

            if (strpos($tableName, ' ') !== false) {
                $sql = "SELECT COUNT(*) as total FROM {$tableName}";
            } else {
                $sql = "SELECT COUNT(*) as total FROM `{$tableName}`";
            }

            foreach ($this->dataTable->getJoins() as $join) {
                $sql .= " {$join['type']} JOIN {$join['table']} ON {$join['condition']}";
            }

            $params = [];

            // Add WHERE conditions first
            $whereClause = $this->buildWhereClause($this->dataTable->getWhereConditions(), $params);
            $hasWhere = !empty($whereClause);

            if ($hasWhere) {
                $sql .= $whereClause;
            }

            // Add search conditions
            if (!empty($search)) {
                $columns = $this->dataTable->getColumns();
                if (empty($columns)) {
                    $schema = $this->dataTable->getTableSchema();
                    $columns = array_keys($schema);
                } else {
                    $columns = array_keys($columns);
                }

                $searchConditions = [];
                if (!empty($searchColumn) && $searchColumn !== 'all') {
                    if (strpos($searchColumn, '.') !== false) {
                        $searchConditions[] = "{$searchColumn} LIKE ?";
                    } else {
                        $searchConditions[] = "`{$searchColumn}` LIKE ?";
                    }
                    $params[] = "%{$search}%";
                } else {
                    foreach ($columns as $column) {
                        // For aliased columns, use only the expression part (before AS) in WHERE clause
                        $searchColumn = $column;
                        if (stripos($column, ' AS ') !== false) {
                            $parts = explode(' AS ', $column);
                            $searchColumn = trim($parts[0]); // Use only the expression part
                        }

                        if (strpos($searchColumn, '.') !== false) {
                            $searchConditions[] = "{$searchColumn} LIKE ?";
                        } else {
                            $searchConditions[] = "`{$searchColumn}` LIKE ?";
                        }
                        $params[] = "%{$search}%";
                    }
                }

                if (!empty($searchConditions)) {
                    $sql .= ($hasWhere ? ' AND ' : ' WHERE ') . '(' . implode(' OR ', $searchConditions) . ')';
                }
            }

            // Wrap with GROUP BY subquery if configured
            $groupBy = $this->dataTable->getGroupBy();
            if (!empty($groupBy)) {
                $groupExpr = strpos($groupBy, '.') !== false ? $groupBy : "`{$groupBy}`";
                $sql = "SELECT COUNT(*) as total FROM ({$sql} GROUP BY {$groupExpr}) AS grouped";
            }

            $query = $this->dataTable->getDatabase()->query($sql);
            if (!empty($params)) {
                $query->bind($params);
            }
            return $query->single()->fetch();
        }

        /**
         * Generate SELECT field list from DataTables configuration
         *
         * Creates an array of field names for the SELECT clause based on the
         * configured columns in the DataTables instance. Handles qualified column
         * names (table.column) and preserves the exact key structure. Falls back
         * to selecting all columns (*) if no specific columns are configured.
         *
         * @return array Array of SELECT field expressions with proper aliasing
         * @since  1.0.0
         */
        private function getSelectFields(): array
        {
            $selectFields = [];
            $columns = $this->dataTable->getColumns();

            if (empty($columns)) {
                $selectFields[] = "*";
            } else {
                // Include configured display columns
                foreach ($columns as $column => $label) {
                    $selectFields[] = preg_match('/\s+AS\s+/i', $column) ? $column : "{$column} AS `{$column}`";
                }

                // Also include any fields referenced in action configurations
                $actionConfig = $this->dataTable->getActionConfig();
                if (isset($actionConfig['groups'])) {
                    foreach ($actionConfig['groups'] as $group) {
                        //if (is_array($group) && !is_numeric(key($group))) {
                        if (is_array($group)) {
                            foreach ($group as $actionKey => $action) {
                                if (isset($action['attributes'])) {
                                    foreach ($action['attributes'] as $attrName => $attrValue) {
                                        // Extract field names from placeholders like {s_stream_uri}
                                        if (preg_match_all('/\{([^}]+)\}/', $attrValue, $matches)) {
                                            foreach ($matches[1] as $field) {
                                                if ($field !== 'id' && !isset($columns[$field])) {
                                                    // Check if this field is already in selectFields as an alias
                                                    $fieldAlreadyExists = false;
                                                    foreach ($selectFields as $existingField) {
                                                        if (strpos($existingField, "AS {$field}") !== false || strpos($existingField, "AS `{$field}`") !== false) {
                                                            $fieldAlreadyExists = true;
                                                            break;
                                                        }
                                                    }
                                                    if (!$fieldAlreadyExists) {
                                                        // Add this field to select if not already included
                                                        $selectFields[] = "`{$field}`";
                                                    }
                                                }
                                            }
                                        }
                                    }
                                }
                                // Also check href, onclick, etc. for placeholders
                                foreach (['href', 'onclick', 'title'] as $prop) {
                                    if (isset($action[$prop]) && is_string($action[$prop])) {
                                        if (preg_match_all('/\{([^}]+)\}/', $action[$prop], $matches)) {
                                            foreach ($matches[1] as $field) {
                                                if ($field !== 'id' && !isset($columns[$field])) {
                                                    // Check if this field is already in selectFields as an alias
                                                    $fieldAlreadyExists = false;
                                                    foreach ($selectFields as $existingField) {
                                                        if (strpos($existingField, "AS {$field}") !== false || strpos($existingField, "AS `{$field}`") !== false) {
                                                            $fieldAlreadyExists = true;
                                                            break;
                                                        }
                                                    }
                                                    if (!$fieldAlreadyExists) {
                                                        // Add this field to select if not already included
                                                        $selectFields[] = "`{$field}`";
                                                    }
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }

                // Remove duplicates
                $selectFields = array_unique($selectFields);
            }

            return $selectFields;
        }

        /**
         * Handle new record creation with schema validation
         *
         * Processes POST data to create a new record in the database. Validates
         * all input data against the table schema, handles file uploads, and
         * inserts the record into the base table. Excludes the primary key field
         * from insertion as it should be auto-generated.
         *
         * @return void                         Outputs JSON response and exits
         * @throws InvalidArgumentException     If no valid data provided for insertion
         * @since  1.0.0
         */
        private function handleAddRecord(): void
        {
            $data = $this->sanitizeFormData($_POST);
            $schema = $this->dataTable->getTableSchema();

            $validatedData = [];
            foreach ($data as $field => $value) {
                if (isset($schema[$field]) && $field !== $this->dataTable->getPrimaryKey()) {
                    $validatedData[$field] = $this->validateFieldValue($field, $value, $schema[$field]);
                }
            }

            $validatedData = $this->processFileUploads($validatedData);

            if (empty($validatedData)) {
                throw new InvalidArgumentException('No valid data to insert');
            }

            $fields = array_keys($validatedData);
            $placeholders = array_fill(0, count($fields), '?');

            // Use BASE table name for INSERT (no alias)
            $query = "INSERT INTO `{$this->dataTable->getBaseTableName()}` (`" .
                implode('`, `', $fields) .
                "`) VALUES (" .
                implode(', ', $placeholders) .
                ")";

            $result = $this->dataTable->getDatabase()
                ->query($query)
                ->bind(array_values($validatedData))
                ->execute();

            $success = $result !== false;
            $message = $success ? 'Record added successfully' : 'Failed to add record';
            $insertId = $success ? $this->dataTable->getDatabase()->getLastId() : null;

            header('Content-Type: application/json');
            echo json_encode([
                'success' => $success,
                'message' => $message,
                'id' => $insertId
            ]);
            exit;
        }

        /**
         * Handle existing record updates with enhanced validation
         *
         * Processes POST data to update an existing record in the database.
         * Validates the record ID, sanitizes and validates all field data against
         * the schema, handles file uploads, and updates the record. Respects
         * any configured WHERE conditions for security.
         *
         * @return void                         Outputs JSON response and exits
         * @throws InvalidArgumentException     If record ID is missing or invalid, or no valid data to update
         * @since  1.0.0
         */
        private function handleEditRecord(): void
        {
            $unqualifiedPK = $this->getUnqualifiedPrimaryKey();
            $id = $this->validateInteger($_POST[$unqualifiedPK] ?? null);
            if (!$id) {
                throw new InvalidArgumentException('Valid record ID is required');
            }

            $data = $this->sanitizeFormData($_POST);
            unset($data[$unqualifiedPK]);

            $schema = $this->dataTable->getTableSchema();
            $validatedData = [];

            foreach ($data as $field => $value) {
                if (isset($schema[$field]) && $field !== $unqualifiedPK) {
                    $validatedData[$field] = $this->validateFieldValue($field, $value, $schema[$field]);
                }
            }

            $validatedData = $this->processFileUploads($validatedData);

            if (empty($validatedData)) {
                throw new InvalidArgumentException('No valid data to update');
            }

            $fields = array_keys($validatedData);
            $setClause = implode(' = ?, ', array_map(function ($f) {
                return "`{$f}`";
            }, $fields)) . ' = ?';

            $sql = "UPDATE `{$this->dataTable->getBaseTableName()}` SET {$setClause}";
            $params = array_values($validatedData);

            // Add WHERE conditions
            $whereConditions = $this->dataTable->getWhereConditions();
            $additionalParams = [];
            $whereClause = $this->buildWhereClause($whereConditions, $additionalParams, true);

            if (!empty($whereClause)) {
                $sql .= $whereClause . " AND `{$unqualifiedPK}` = ?";
                $params = array_merge($params, $additionalParams, [$id]);
            } else {
                $sql .= " WHERE `{$unqualifiedPK}` = ?";
                $params[] = $id;
            }

            $result = $this->dataTable->getDatabase()
                ->query($sql)
                ->bind($params)
                ->execute();

            $success = $result !== false;
            $message = $success ? 'Record updated successfully' : 'Failed to update record';

            header('Content-Type: application/json');
            echo json_encode([
                'success' => $success,
                'message' => $message
            ]);
            exit;
        }

        /**
         * Handle single record deletion with ID validation
         *
         * Deletes a specific record from the database based on the provided ID.
         * Validates the record ID and respects any configured WHERE conditions
         * for security. Returns the number of affected rows to confirm deletion.
         *
         * @return void                         Outputs JSON response and exits
         * @throws InvalidArgumentException     If record ID is missing or invalid
         * @since  1.0.0
         */
        private function handleDeleteRecord(): void
        {
            $id = $this->validateInteger($_POST['id'] ?? null);
            if (!$id) {
                throw new InvalidArgumentException('Valid record ID is required');
            }

            $unqualifiedPK = $this->getUnqualifiedPrimaryKey();
            $sql = "DELETE FROM `{$this->dataTable->getBaseTableName()}`";
            $params = [$id];

            // Add WHERE conditions
            $whereConditions = $this->dataTable->getWhereConditions();
            $additionalParams = [];
            $whereClause = $this->buildWhereClause($whereConditions, $additionalParams, true);

            if (!empty($whereClause)) {
                $sql .= $whereClause . " AND `{$unqualifiedPK}` = ?";
                $params = array_merge($additionalParams, $params);
            } else {
                $sql .= " WHERE `{$unqualifiedPK}` = ?";
            }

            $result = $this->dataTable->getDatabase()
                ->query($sql)
                ->bind($params)
                ->execute();

            $success = $result !== false && $result > 0;
            $message = $success ? 'Record deleted successfully' : ($result === 0 ? 'Record not found' : 'Failed to delete record');

            header('Content-Type: application/json');
            echo json_encode([
                'success' => $success,
                'message' => $message,
                'affected_rows' => $result
            ]);
            exit;
        }

        /**
         * Handle single record fetch for editing
         *
         * Retrieves a specific record from the database for editing purposes.
         * Validates the record ID and respects any configured WHERE conditions.
         * Returns all fields from the base table for the specified record.
         *
         * @return void                         Outputs JSON response and exits
         * @throws InvalidArgumentException     If record ID is missing or invalid
         * @since  1.0.0
         */
        private function handleFetchRecord(): void
        {
            $id = $this->validateInteger($_GET['id'] ?? $_POST['id'] ?? null);
            if (!$id) {
                throw new InvalidArgumentException('Valid record ID is required');
            }

            $unqualifiedPK = $this->getUnqualifiedPrimaryKey();

            // For qualified primary keys in WHERE, we need to check both
            $primaryKey = $this->dataTable->getPrimaryKey();
            $idColumn = strpos($primaryKey, '.') !== false ? $unqualifiedPK : $primaryKey;

            $sql = "SELECT * FROM `{$this->dataTable->getBaseTableName()}`";
            $params = [$id];

            // Add WHERE conditions
            $whereConditions = $this->dataTable->getWhereConditions();
            $additionalParams = [];
            $whereClause = $this->buildWhereClause($whereConditions, $additionalParams, true);

            if (!empty($whereClause)) {
                $sql .= $whereClause . " AND `{$idColumn}` = ?";
                $params = array_merge($additionalParams, $params);
            } else {
                $sql .= " WHERE `{$idColumn}` = ?";
            }

            $result = $this->dataTable->getDatabase()
                ->query($sql)
                ->bind($params)
                ->single()
                ->fetch();

            $success = $result !== false;
            $message = $success ? 'Record fetched successfully' : 'Record not found';

            header('Content-Type: application/json');
            echo json_encode([
                'success' => $success,
                'message' => $message,
                'data' => $result ?: null
            ]);
            exit;
        }

        /**
         * Handle bulk actions on multiple records with enhanced security
         *
         * Processes bulk operations on multiple selected records. Validates that
         * bulk actions are enabled, the action is allowed, and the selected IDs
         * are valid. Supports built-in delete action and custom callback actions.
         * Respects configured WHERE conditions for security.
         *
         * @return void                         Outputs JSON response and exits
         * @throws InvalidArgumentException     If bulk action is invalid, not enabled, or no valid IDs provided
         * @since  1.0.0
         */
        private function handleBulkAction(): void
        {
            $bulkAction = $this->sanitizeInput($_POST['bulk_action'] ?? '');
            $selectedIds = $this->validateIdArray($_POST['selected_ids'] ?? '[]');

            if (empty($bulkAction) || empty($selectedIds)) {
                throw new InvalidArgumentException('Valid bulk action and selected IDs are required');
            }

            $bulkActions = $this->dataTable->getBulkActions();
            if (!$bulkActions['enabled']) {
                throw new InvalidArgumentException('Bulk actions are not enabled');
            }

            if (!isset($bulkActions['actions'][$bulkAction])) {
                throw new InvalidArgumentException("Unknown bulk action: {$bulkAction}");
            }

            $result = false;
            $message = '';

            switch ($bulkAction) {
                case 'delete':
                    $unqualifiedPK = $this->getUnqualifiedPrimaryKey();
                    $placeholders = implode(',', array_fill(0, count($selectedIds), '?'));
                    $sql = "DELETE FROM `{$this->dataTable->getBaseTableName()}`";
                    $params = $selectedIds;

                    // Add WHERE conditions
                    $whereConditions = $this->dataTable->getWhereConditions();
                    $additionalParams = [];
                    $whereClause = $this->buildWhereClause($whereConditions, $additionalParams, true);

                    if (!empty($whereClause)) {
                        $sql .= $whereClause . " AND `{$unqualifiedPK}` IN ({$placeholders})";
                        $params = array_merge($additionalParams, $selectedIds);
                    } else {
                        $sql .= " WHERE `{$unqualifiedPK}` IN ({$placeholders})";
                    }

                    $result = $this->dataTable->getDatabase()
                        ->query($sql)
                        ->bind($params)
                        ->execute();
                    $message = $result !== false ? 'Selected records deleted successfully' : 'Failed to delete selected records';
                    break;

                default:
                    $actionConfig = $bulkActions['actions'][$bulkAction];
                    if (isset($actionConfig['callback']) && is_callable($actionConfig['callback'])) {
                        $result = call_user_func(
                            $actionConfig['callback'],
                            $selectedIds,
                            $this->dataTable->getDatabase(),
                            $this->dataTable->getBaseTableName()  // Pass base table name
                        );
                        $message = $result ?
                            ($actionConfig['success_message'] ?? 'Bulk action completed successfully') : ($actionConfig['error_message'] ?? 'Bulk action failed');
                    }
                    break;
            }

            $affectedCount = is_int($result) ? $result : count($selectedIds);

            header('Content-Type: application/json');
            echo json_encode([
                'success' => $result !== false,
                'message' => $message,
                'affected_count' => $affectedCount
            ]);
            exit;
        }

        /**
         * Handle inline field editing with enhanced validation
         *
         * Updates a single field value for a specific record through inline editing.
         * Validates that the field is configured as inline editable, the record ID
         * is valid, and the new value meets schema requirements. Supports both
         * qualified and unqualified field names.
         *
         * @return void                         Outputs JSON response and exits
         * @throws InvalidArgumentException     If record ID, field name invalid, or field not inline editable
         * @since  1.0.0
         */
        private function handleInlineEdit(): void
        {
            $id = $this->validateInteger($_POST['id'] ?? null);
            $field = trim($_POST['field'] ?? '');
            $value = $_POST['value'] ?? null;

            if (!$id || !$field) {
                throw new InvalidArgumentException('Record ID and field are required');
            }

            $columnName = strpos($field, '.') !== false ? explode('.', $field)[1] : $field;
            $inlineEditableColumns = $this->dataTable->getInlineEditableColumns();

            if (!in_array($field, $inlineEditableColumns) && !in_array($columnName, $inlineEditableColumns)) {
                throw new InvalidArgumentException("Field '{$field}' is not inline editable. Configured fields: " . implode(', ', $inlineEditableColumns));
            }

            $schema = $this->dataTable->getTableSchema();
            if (isset($schema[$columnName])) {
                $value = $this->validateFieldValue($columnName, $value, $schema[$columnName]);
            }

            $unqualifiedPK = $this->getUnqualifiedPrimaryKey();
            $sql = "UPDATE `{$this->dataTable->getBaseTableName()}` SET `{$columnName}` = ?";
            $params = [$value, $id];

            // Add WHERE conditions
            $whereConditions = $this->dataTable->getWhereConditions();
            $additionalParams = [];
            $whereClause = $this->buildWhereClause($whereConditions, $additionalParams, true);

            if (!empty($whereClause)) {
                $sql .= $whereClause . " AND `{$unqualifiedPK}` = ?";
                $params = array_merge([$value], $additionalParams, [$id]);
            } else {
                $sql .= " WHERE `{$unqualifiedPK}` = ?";
            }

            $result = $this->dataTable->getDatabase()
                ->query($sql)
                ->bind($params)
                ->execute();

            $success = $result !== false;
            $message = $success ? 'Field updated successfully' : 'Failed to update field';

            header('Content-Type: application/json');
            echo json_encode([
                'success' => $success,
                'message' => $message
            ]);
            exit;
        }

        /**
         * Handle action callbacks with full row data
         *
         * Executes custom callback functions for row-specific actions. Finds the
         * appropriate callback based on the action name, validates the row ID,
         * and executes the callback with the row ID, full row data, database
         * connection, and base table name.
         *
         * @return void                         Outputs JSON response and exits
         * @throws InvalidArgumentException     If action name invalid, row ID missing, or callback not found
         * @since  1.0.0
         */
        private function handleActionCallback(): void
        {
            $actionName = $this->sanitizeInput($_POST['action_name'] ?? '');
            $rowId = $this->validateInteger($_POST['row_id'] ?? null);
            $rowData = json_decode($_POST['row_data'] ?? '{}', true);

            if (empty($actionName) || !$rowId) {
                throw new InvalidArgumentException('Valid action and row ID are required');
            }

            // Find the action configuration
            $actionConfig = $this->dataTable->getActionConfig();
            $callback = null;

            if (isset($actionConfig['groups'])) {
                foreach ($actionConfig['groups'] as $group) {
                    if (!is_array($group)) {
                        continue;
                    }
                    // Check if this group contains our action with a callable callback
                    if (isset($group[$actionName]['callback']) && is_callable($group[$actionName]['callback'])) {
                        $callback = $group[$actionName]['callback'];
                        $callbackConfig = $group[$actionName];
                        break;
                    }
                }
            }

            if (!$callback) {
                throw new InvalidArgumentException("No callback found for action: {$actionName}");
            }

            // Execute the callback with row ID and full row data
            $result = call_user_func(
                $callback,
                $rowId,
                $rowData,
                $this->dataTable->getDatabase(),
                $this->dataTable->getBaseTableName()
            );

            $success = $result !== false;
            $message = $success ?
                ($callbackConfig['success_message'] ?? 'Action completed successfully') : ($callbackConfig['error_message'] ?? 'Action failed');

            header('Content-Type: application/json');
            echo json_encode([
                'success' => $success,
                'message' => $message
            ]);
            exit;
        }

        /**
         * Handle aggregation data requests for footer calculations
         *
         * Computes SUM and/or AVG for configured columns across the full
         * (filtered) recordset. Returns JSON with aggregation results.
         *
         * @return void Outputs JSON and exits
         */
        private function handleFetchAggregations(): void
        {
            $search = $this->sanitizeSearchInput($_GET['search'] ?? '');
            $searchColumn = $this->sanitizeColumnName($_GET['search_column'] ?? '');

            $aggregations = $this->dataTable->getFooterAggregations();
            $calculatedColumns = $this->dataTable->getCalculatedColumns();

            if (empty($aggregations)) {
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'aggregations' => []]);
                exit;
            }

            $selectParts = [];
            foreach ($aggregations as $column => $config) {
                $type = $config['type'];

                // Determine SQL expression: use calculated column expression or raw column
                $sqlExpr = $column;
                if (isset($calculatedColumns[$column])) {
                    $sqlExpr = $calculatedColumns[$column]['expression'];
                } elseif (strpos($column, '.') === false) {
                    $sqlExpr = "`{$column}`";
                }

                if ($type === 'sum' || $type === 'both') {
                    $selectParts[] = "SUM({$sqlExpr}) AS `{$column}_sum`";
                }
                if ($type === 'avg' || $type === 'both') {
                    $selectParts[] = "AVG({$sqlExpr}) AS `{$column}_avg`";
                }
            }

            $tableName = $this->dataTable->getTableName();
            if (strpos($tableName, ' ') !== false) {
                $sql = "SELECT " . implode(', ', $selectParts) . " FROM {$tableName}";
            } else {
                $sql = "SELECT " . implode(', ', $selectParts) . " FROM `{$tableName}`";
            }

            foreach ($this->dataTable->getJoins() as $join) {
                $sql .= " {$join['type']} JOIN {$join['table']} ON {$join['condition']}";
            }

            $params = [];
            $whereClause = $this->buildWhereClause($this->dataTable->getWhereConditions(), $params);
            $hasWhere = !empty($whereClause);

            if ($hasWhere) {
                $sql .= $whereClause;
            }

            if (!empty($search)) {
                $searchConditions = [];
                foreach ($this->dataTable->getColumns() as $col => $label) {
                    $sc = $col;
                    if (stripos($col, ' AS ') !== false) {
                        $parts = explode(' AS ', $col);
                        $sc = trim($parts[0]);
                    }
                    if (strpos($sc, '.') !== false) {
                        $searchConditions[] = "{$sc} LIKE ?";
                    } else {
                        $searchConditions[] = "`{$sc}` LIKE ?";
                    }
                    $params[] = "%{$search}%";
                }
                if (!empty($searchConditions)) {
                    $sql .= ($hasWhere ? ' AND ' : ' WHERE ') . '(' . implode(' OR ', $searchConditions) . ')';
                }
            }

            // Handle GROUP BY - must wrap as subquery for aggregating over grouped results
            $groupBy = $this->dataTable->getGroupBy();
            if (!empty($groupBy)) {
                $groupExpr = strpos($groupBy, '.') !== false ? $groupBy : "`{$groupBy}`";

                // Build inner query that produces per-group values
                $innerSelectParts = [];
                foreach ($aggregations as $column => $config) {
                    if (isset($calculatedColumns[$column])) {
                        $innerSelectParts[] = $calculatedColumns[$column]['expression'] . " AS `{$column}`";
                    } elseif (strpos($column, '.') === false) {
                        $innerSelectParts[] = "`{$column}`";
                    } else {
                        $innerSelectParts[] = $column;
                    }
                }

                $innerSql = "SELECT " . implode(', ', $innerSelectParts) . " FROM " . (strpos($this->dataTable->getTableName(), ' ') !== false ? $this->dataTable->getTableName() : "`{$this->dataTable->getTableName()}`");

                foreach ($this->dataTable->getJoins() as $join) {
                    $innerSql .= " {$join['type']} JOIN {$join['table']} ON {$join['condition']}";
                }

                // Re-apply WHERE and search conditions
                $innerParams = [];
                $innerWhere = $this->buildWhereClause($this->dataTable->getWhereConditions(), $innerParams);
                if (!empty($innerWhere)) {
                    $innerSql .= $innerWhere;
                }

                if (!empty($search)) {
                    $innerSearchConditions = [];
                    foreach ($this->dataTable->getColumns() as $col => $label) {
                        $sc = $col;
                        if (stripos($col, ' AS ') !== false) {
                            $parts = explode(' AS ', $col);
                            $sc = trim($parts[0]);
                        }
                        if (strpos($sc, '.') !== false) {
                            $innerSearchConditions[] = "{$sc} LIKE ?";
                        } else {
                            $innerSearchConditions[] = "`{$sc}` LIKE ?";
                        }
                        $innerParams[] = "%{$search}%";
                    }
                    if (!empty($innerSearchConditions)) {
                        $innerSql .= (!empty($innerWhere) ? ' AND ' : ' WHERE ') . '(' . implode(' OR ', $innerSearchConditions) . ')';
                    }
                }

                $innerSql .= " GROUP BY {$groupExpr}";

                // Outer query aggregates the per-group values
                $outerSelectParts = [];
                foreach ($aggregations as $column => $config) {
                    $type = $config['type'];
                    if ($type === 'sum' || $type === 'both') {
                        $outerSelectParts[] = "SUM(`{$column}`) AS `{$column}_sum`";
                    }
                    if ($type === 'avg' || $type === 'both') {
                        $outerSelectParts[] = "AVG(`{$column}`) AS `{$column}_avg`";
                    }
                }

                $sql = "SELECT " . implode(', ', $outerSelectParts) . " FROM ({$innerSql}) AS grouped";
                $params = $innerParams;
            }

            $query = $this->dataTable->getDatabase()->query($sql);
            if (!empty($params)) {
                $query->bind($params);
            }
            $result = $query->single()->fetch();

            $output = [];
            if ($result) {
                foreach ($aggregations as $column => $config) {
                    $entry = ['column' => $column];
                    $sumKey = "{$column}_sum";
                    $avgKey = "{$column}_avg";
                    if (isset($result->$sumKey)) {
                        $entry['sum'] = (float) $result->$sumKey;
                    }
                    if (isset($result->$avgKey)) {
                        $entry['avg'] = (float) $result->$avgKey;
                    }
                    $output[$column] = $entry;
                }
            }

            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'aggregations' => $output]);
            exit;
        }

        /**
         * Get unqualified primary key column name for base table operations
         *
         * Extracts the column name from a potentially qualified primary key field.
         * If the primary key is qualified (e.g., 'users.id'), returns just the
         * column name (e.g., 'id'). Used for base table operations like INSERT,
         * UPDATE, and DELETE where table aliases are not used.
         *
         * @return string Unqualified primary key column name
         * @since  1.0.0
         */
        private function getUnqualifiedPrimaryKey(): string
        {
            $primaryKey = $this->dataTable->getPrimaryKey();

            // If qualified (s.id), extract just the column name (id)
            if (strpos($primaryKey, '.') !== false) {
                return explode('.', $primaryKey)[1];
            }
            return $primaryKey;
        }

        /**
         * Sanitize and validate form data array
         *
         * @param  array $data Raw form data
         * @return array Sanitized form data
         */
        private function sanitizeFormData(array $data): array
        {
            $sanitized = [];
            foreach ($data as $key => $value) {
                if ($key !== 'action') { // Skip action parameter
                    $sanitized[$this->sanitizeInput($key)] = $value;
                }
            }
            return $sanitized;
        }

        /**
         * Sanitize individual value based on type
         *
         * @param  mixed $value Value to sanitize
         * @return mixed Sanitized value
         */
        private function sanitizeValue($value)
        {
            if (is_string($value)) {
                return trim($value);
            }
            return $value;
        }

        /**
         * Validate and sanitize search input
         *
         * @param  string $input Raw search input
         * @return string Sanitized search input
         */
        private function sanitizeSearchInput(string $input): string
        {
            return trim(htmlspecialchars($input, ENT_QUOTES, 'UTF-8'));
        }

        /**
         * Sanitize column name input
         *
         * @param  string $column Raw column name
         * @return string Sanitized column name
         */
        private function sanitizeColumnName(string $column): string
        {
            return preg_replace('/[^a-zA-Z0-9_\.]/', '', $column);
        }

        /**
         * Sanitize sort direction input
         *
         * @param  string $direction Raw sort direction
         * @return string Valid sort direction (ASC or DESC)
         */
        private function sanitizeSortDirection(string $direction): string
        {
            return strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';
        }

        /**
         * Sanitize general input string
         *
         * @param  string $input Raw input
         * @return string Sanitized input
         */
        private function sanitizeInput(string $input): string
        {
            return preg_replace('/[^a-zA-Z0-9_\-\.]/', '', trim($input));
        }

        /**
         * Validate integer input with bounds
         *
         * @param  mixed $input Input to validate
         * @param  int   $min   Minimum allowed value
         * @param  int   $max   Maximum allowed value
         * @return int   Validated integer
         */
        private function validateInteger($input, int $min = 1, int $max = PHP_INT_MAX): int
        {
            $value = filter_var($input, FILTER_VALIDATE_INT);
            if ($value === false || $value < $min || $value > $max) {
                return $min;
            }
            return $value;
        }

        /**
         * Validate array of IDs
         *
         * @param  string $jsonIds JSON string of IDs
         * @return array  Validated array of integer IDs
         */
        private function validateIdArray(string $jsonIds): array
        {
            $ids = json_decode($jsonIds, true);
            if (!is_array($ids)) {
                return [];
            }

            return array_filter(array_map('intval', $ids), function ($id) {
                return $id > 0;
            });
        }

        /**
         * Validate field value against database schema
         *
         * @param  string $fieldName Field name
         * @param  mixed  $value     Value to validate
         * @param  array  $fieldInfo Schema information for field
         * @return mixed  Validated value
         */
        private function validateFieldValue(string $fieldName, $value, array $fieldInfo)
        {
            // Handle NULL values
            if ($value === null || $value === '') {
                if (!$fieldInfo['null']) {
                    throw new InvalidArgumentException("Field {$fieldName} cannot be null");
                }
                return null;
            }

            // Type-specific validation based on detected field type
            $fieldType = $fieldInfo['type'];

            switch ($fieldType) {
                case 'number':
                    if (!is_numeric($value)) {
                        throw new InvalidArgumentException("Field {$fieldName} must be numeric");
                    }
                    return is_float($value) ? (float)$value : (int)$value;

                case 'email':
                    if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                        throw new InvalidArgumentException("Field {$fieldName} must be a valid email");
                    }
                    return $value;

                case 'date':
                    if (!$this->isValidDate($value, 'Y-m-d')) {
                        throw new InvalidArgumentException("Field {$fieldName} must be a valid date (Y-m-d)");
                    }
                    return $value;

                case 'datetime-local':
                    if (!$this->isValidDate($value, 'Y-m-d\TH:i')) {
                        throw new InvalidArgumentException("Field {$fieldName} must be a valid datetime");
                    }
                    return $value;

                case 'checkbox':
                case 'boolean':
                    return $value ? 1 : 0;

                default:
                    // Just return the trimmed value, no HTML encoding
                    return is_string($value) ? trim($value) : $value;
            }
        }

        /**
         * Validate date format
         *
         * @param  string $date   Date string
         * @param  string $format Expected format
         * @return bool   True if valid date
         */
        private function isValidDate(string $date, string $format): bool
        {
            $d = \DateTime::createFromFormat($format, $date);
            return $d && $d->format($format) === $date;
        }

        /**
         * Build WHERE clause from conditions array
         *
         * @param  array $conditions WHERE conditions array
         * @param  array &$params    Parameters array to append to
         * @param  bool  $useBaseTable Whether to use base table (for UPDATE/DELETE)
         * @return string WHERE clause SQL
         */
        private function buildWhereClause(array $conditions, array &$params, bool $useBaseTable = false): string
        {
            if (empty($conditions)) {
                return '';
            }

            $whereParts = [];

            // Check if this is a simple indexed array of conditions (default AND)
            if (isset($conditions[0]) && is_array($conditions[0])) {
                // Numeric indexed array - treat as AND conditions
                foreach ($conditions as $condition) {
                    if (!isset($condition['field'], $condition['comparison'], $condition['value'])) {
                        continue;
                    }

                    $field = $condition['field'];
                    $comparison = strtoupper(trim($condition['comparison']));
                    $value = $condition['value'];

                    // Strip table alias for UPDATE/DELETE operations
                    if ($useBaseTable && strpos($field, '.') !== false) {
                        $field = $this->getUnqualifiedFieldName($field);
                    }

                    if (strpos($field, '.') !== false) {
                        $fieldSql = $field;
                    } else {
                        $fieldSql = "`{$field}`";
                    }

                    switch ($comparison) {
                        case 'IN':
                        case 'NOT IN':
                            if (is_array($value)) {
                                $placeholders = implode(',', array_fill(0, count($value), '?'));
                                $whereParts[] = "{$fieldSql} {$comparison} ({$placeholders})";
                                $params = array_merge($params, $value);
                            }
                            break;
                        case 'LIKE':
                        case 'NOT LIKE':
                            $whereParts[] = "{$fieldSql} {$comparison} ?";
                            $params[] = $value;
                            break;
                        default:
                            $whereParts[] = "{$fieldSql} {$comparison} ?";
                            $params[] = $value;
                            break;
                    }
                }

                return !empty($whereParts) ? ' WHERE ' . implode(' AND ', $whereParts) : '';
            }

            // Handle operator-based structure (AND/OR groups) - same logic applies
            foreach ($conditions as $operator => $conditionGroup) {
                if (!is_array($conditionGroup)) {
                    continue;
                }

                if (isset($conditionGroup['field'])) {
                    $conditionGroup = [$conditionGroup];
                }

                $groupParts = [];
                foreach ($conditionGroup as $condition) {
                    if (!isset($condition['field'], $condition['comparison'], $condition['value'])) {
                        continue;
                    }

                    $field = $condition['field'];
                    $comparison = strtoupper(trim($condition['comparison']));
                    $value = $condition['value'];

                    // Strip table alias for UPDATE/DELETE operations
                    if ($useBaseTable && strpos($field, '.') !== false) {
                        $field = $this->getUnqualifiedFieldName($field);
                    }

                    if (strpos($field, '.') !== false) {
                        $fieldSql = $field;
                    } else {
                        $fieldSql = "`{$field}`";
                    }

                    switch ($comparison) {
                        case 'IN':
                        case 'NOT IN':
                            if (is_array($value)) {
                                $placeholders = implode(',', array_fill(0, count($value), '?'));
                                $groupParts[] = "{$fieldSql} {$comparison} ({$placeholders})";
                                $params = array_merge($params, $value);
                            }
                            break;
                        case 'LIKE':
                        case 'NOT LIKE':
                            $groupParts[] = "{$fieldSql} {$comparison} ?";
                            $params[] = $value;
                            break;
                        default:
                            $groupParts[] = "{$fieldSql} {$comparison} ?";
                            $params[] = $value;
                            break;
                    }
                }

                if (!empty($groupParts)) {
                    if (strtoupper($operator) === 'OR') {
                        $whereParts[] = '(' . implode(' OR ', $groupParts) . ')';
                    } else {
                        $whereParts[] = '(' . implode(' AND ', $groupParts) . ')';
                    }
                }
            }

            return !empty($whereParts) ? ' WHERE ' . implode(' AND ', $whereParts) : '';
        }

        /**
         * Get unqualified column name from potentially qualified field
         *
         * @param  string $field Field name that may be qualified (e.g., 's.u_id')
         * @return string Unqualified column name (e.g., 'u_id')
         */
        private function getUnqualifiedFieldName(string $field): string
        {
            if (strpos($field, '.') !== false) {
                return explode('.', $field)[1];
            }
            return $field;
        }

        /**
         * Handle Select2 options fetch request
         *
         * Processes AJAX requests for Select2 dropdown options including search filtering,
         * parameter substitution from record data, and result limiting. Executes the
         * configured SQL query with ID/Label aliases and returns JSON results.
         *
         * @return void (outputs JSON and exits)
         * @throws InvalidArgumentException If query is missing or invalid
         * @since  1.2.0
         */
        private function handleFetchSelect2Options(): void
        {

            $query = $_POST['query'] ?? '';
            $search = $this->sanitizeSearchInput($_POST['search'] ?? '');
            $maxResults = $this->validateInteger($_POST['max_results'] ?? 50, 0);
            $valueFilter = $_POST['value_filter'] ?? '';
            $recordDataJson = $_POST['record_data'] ?? '{}';
            Logger::debug("Select2 fetch options", [
                'query' => $query,
                'search' => $search,
                'maxResults' => $maxResults,
                'valueFilter' => $valueFilter,
                'recordData' => $recordDataJson
            ]);
            if (empty($query)) {
                throw new InvalidArgumentException('Query is required for Select2 options');
            }

            // Parse record data for parameter substitution
            $recordData = json_decode($recordDataJson, true);
            if (!is_array($recordData)) {
                $recordData = [];
            }

            // Substitute {field_name} placeholders with record data values
            $processedQuery = $this->substituteQueryParameters($query, $recordData);

            // Build WHERE clause for search and value filter
            $whereClauses = [];
            $params = [];

            // Add value filter if present (for loading initial selected value)
            if (!empty($valueFilter)) {
                $whereClauses[] = "ID = ?";
                $params[] = $valueFilter;
            }

            // Add search filter if present
            if (!empty($search)) {
                // Extract the original column expression from the query before AS Label
                // Pattern: word/expression AS Label
                if (preg_match('/,\s*([a-zA-Z0-9_\.]+)\s+AS\s+[`\'"]*Label[`\'"]*\s*/i', $processedQuery, $matches)) {
                    $labelColumn = trim($matches[1]);
                } else {
                    $labelColumn = 'Label';
                }

                $whereClauses[] = "{$labelColumn} LIKE ?";
                $params[] = "%{$search}%";
            }

            // Combine base query with WHERE clause
            $finalQuery = $processedQuery;

            if (!empty($whereClauses)) {
                // Check if query already has WHERE clause
                if (stripos($finalQuery, 'WHERE') !== false) {
                    $finalQuery .= ' AND (' . implode(' AND ', $whereClauses) . ')';
                } else {
                    $finalQuery .= ' WHERE ' . implode(' AND ', $whereClauses);
                }
            }

            // Add LIMIT if max_results is specified
            if ($maxResults > 0) {
                $finalQuery .= " LIMIT {$maxResults}";
            }

            try {
                Logger::debug("Select2 executing query", ['sql' => $finalQuery, 'params' => $params]);

                // Execute query using database connection
                $query = $this->dataTable->getDatabase()->query($finalQuery);
                if (!empty($params)) {
                    $query->bind($params);
                }
                $results = $query->fetch();

                // Ensure results is an array
                if (!$results) {
                    $results = [];
                }

                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true,
                    'results' => $results
                ]);
                exit;
            } catch (\Exception $e) {
                Logger::error("Select2 query failed", [
                    'query' => $finalQuery,
                    'error' => $e->getMessage()
                ]);

                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'message' => 'Failed to fetch options',
                    'results' => []
                ]);
                exit;
            }
        }

        /**
         * Fetch select2 labels for display in table
         *
         * @param  array  $rows   Data rows with IDs
         * @param  string $column Column name
         * @param  string $query  Select2 query
         * @return array  Map of ID => Label
         */
        private function fetchSelect2Labels(array $rows, string $column, string $query): array
        {
            $ids = array_unique(array_column($rows, $column));
            $ids = array_filter($ids); // Remove empty values

            if (empty($ids)) {
                return [];
            }

            // Extract original column from query for WHERE clause
            if (preg_match('/,\s*([a-zA-Z0-9_\.]+)\s+AS\s+[`\'"]*Label[`\'"]*\s*/i', $query, $matches)) {
                $labelColumn = trim($matches[1]);
            } else {
                return [];
            }

            // Build query to fetch labels for all IDs
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $fetchQuery = "{$query} WHERE ID IN ({$placeholders})";

            try {
                $results = $this->dataTable->getDatabase()
                    ->query($fetchQuery)
                    ->bind($ids)
                    ->fetch();

                // Build ID => Label map
                $labelMap = [];
                if ($results) {
                    foreach ($results as $row) {
                        $labelMap[$row->ID] = $row->Label;
                    }
                }

                return $labelMap;
            } catch (\Exception $e) {
                Logger::error("Failed to fetch select2 labels", ['error' => $e->getMessage()]);
                return [];
            }
        }

        /**
         * Substitute query parameters from record data
         *
         * Replaces {field_name} placeholders in the query with actual values
         * from the provided record data array. Parameters are properly escaped
         * for SQL injection prevention.
         *
         * @param  string $query      SQL query with {field_name} placeholders
         * @param  array  $recordData Associative array of field => value pairs
         * @return string Processed query with substituted values
         * @since  1.2.0
         */
        private function substituteQueryParameters(string $query, array $recordData): string
        {
            // Find all {field_name} placeholders
            preg_match_all('/\{([a-zA-Z0-9_]+)\}/', $query, $matches);

            if (empty($matches[0])) {
                return $query;
            }

            $processedQuery = $query;

            // Replace each placeholder with its value from record data
            foreach ($matches[1] as $index => $fieldName) {
                $placeholder = $matches[0][$index];

                if (isset($recordData[$fieldName])) {
                    $value = $recordData[$fieldName];

                    // Escape value for SQL safety
                    if (is_numeric($value)) {
                        $escapedValue = $value;
                    } else {
                        // Use database escape method if available, otherwise basic escaping
                        $escapedValue = "'" . addslashes($value) . "'";
                    }

                    $processedQuery = str_replace($placeholder, $escapedValue, $processedQuery);
                } else {
                    // Replace with NULL if field not found in record data
                    $processedQuery = str_replace($placeholder, 'NULL', $processedQuery);
                }
            }

            return $processedQuery;
        }
    }
}
