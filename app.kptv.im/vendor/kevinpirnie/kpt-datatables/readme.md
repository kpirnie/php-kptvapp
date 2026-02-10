# KPT DataTables

Advanced PHP DataTables library with CRUD operations, search, sorting, pagination, bulk actions, JOIN support, calculated columns, footer aggregations, and multi-framework theme support (UIKit3, Bootstrap 5, Tailwind CSS, Plain).

## Features

- 🚀 **Full CRUD Operations** - Create, Read, Update, Delete with AJAX support
- 🔗 **Advanced JOIN Support** - Complex database relationships with table aliases
- 🔍 **Advanced Search** - Search all columns or specific columns with qualified column names
- 📊 **Sorting** - Multi-column sorting with visual indicators on joined tables
- 📄 **Pagination** - Configurable page sizes with first/last navigation
- ✅ **Bulk Actions** - Select multiple records for bulk operations with custom callbacks
- ✏️ **Inline Editing** - Double-click to edit fields directly in the table
- 📁 **File Uploads** - Built-in file upload handling with validation
- 🎨 **Multi-Framework Themes** - UIKit3, Bootstrap 5, Tailwind CSS, and Plain (framework-agnostic)
- 📱 **Responsive** - Mobile-friendly design
- 🎛️ **Customizable** - Extensive configuration options
- 🔧 **Chainable API** - Fluent interface for easy configuration
- 🧮 **Calculated Columns** - Computed columns with basic math operations (+, -, *, /, %) or custom SQL expressions
- 📈 **Footer Aggregations** - Sum and average calculations per page, full recordset, or both

## Requirements

- PHP 8.1 or higher
- PDO extension
- JSON extension

## Installation

Install via Composer:

```bash
composer require kevinpirnie/kpt-datatables
```

## Dependencies

This package depends on:

- [`kevinpirnie/kpt-database`](https://packagist.org/packages/kevinpirnie/kpt-database) - Database wrapper
- [`kevinpirnie/kpt-logger`](https://packagist.org/packages/kevinpirnie/kpt-logger) - Logging functionality

## Quick Start

### 1. Basic Setup

```php
<?php
require 'vendor/autoload.php';

use KPT\DataTables\DataTables;

// Configure database via constructor
$dbConfig = [
    'server' => 'localhost',
    'schema' => 'your_database',
    'username' => 'your_username',
    'password' => 'your_password',
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci'
];

$dataTable = new DataTables($dbConfig);
```

### 2. Include Required Assets

```php
// Include CSS (with theme)
echo DataTables::getCssIncludes('uikit', true, true);

// Include JavaScript files
echo DataTables::getJsIncludes('uikit', true, true);
```

### 3. Handle AJAX Requests

```php
// Handle AJAX requests (before any HTML output)
if (isset($_POST['action']) || isset($_GET['action'])) {
    $dataTable->handleAjax();
}
```

### 4. Simple Table

```php
// Configure and render table
echo $dataTable
    ->theme('uikit')  // Set theme: 'plain', 'uikit', 'bootstrap', or 'tailwind'
    ->table('users')
    ->columns([
        'id' => 'ID',
        'name' => 'Full Name',
        'email' => 'Email Address',
        'created_at' => 'Created'
    ])
    ->sortable(['name', 'email', 'created_at'])
    ->renderDataTableComponent();
```

## Theme Support

KPT DataTables supports multiple UI frameworks through a flexible theming system:

### Available Themes

| Theme | Description | CDN Support |
|-------|-------------|-------------|
| `plain` | Framework-agnostic with `kp-dt-*` prefixed classes | No |
| `uikit` | UIKit 3 framework (default) | Yes |
| `bootstrap` | Bootstrap 5 framework | Yes |
| `tailwind` | Tailwind CSS framework | Requires compilation |

### Using Themes

```php
// Set theme with CDN includes (default: true)
$dataTable->theme('bootstrap', true);

// Set theme without CDN (for self-hosted assets)
$dataTable->theme('uikit', false);
```

### Theme-Specific Assets

#### UIKit3 (Default)

```php
// CSS and JS includes
echo DataTables::getCssIncludes('uikit', true, true);
echo DataTables::getJsIncludes('uikit', true, true);
```

Automatically includes from CDN:

- UIKit CSS
- UIKit JS
- UIKit Icons

#### Bootstrap 5

```php
echo DataTables::getCssIncludes('bootstrap', true, true);
echo DataTables::getJsIncludes('bootstrap', true, true);
```

Automatically includes from CDN:

- Bootstrap CSS
- Bootstrap Icons CSS
- Bootstrap Bundle JS

#### Tailwind CSS

```php
echo DataTables::getCssIncludes('tailwind', false, true);
echo DataTables::getJsIncludes('tailwind', false, true);
```

**Tailwind Compilation Required:**

```bash
# Install dependencies
npm install

# Build CSS
npm run build:tailwind

# Watch for changes during development
npm run watch:tailwind
```

#### Plain Theme

```php
echo DataTables::getCssIncludes('plain', false, true);
echo DataTables::getJsIncludes('plain', false, true);
```

The plain theme uses `kp-dt-*` prefixed classes for all elements, making it easy to customize or integrate with any CSS framework. Styled similarly to UIKit3.

### CSS Class Structure

All themes include both framework-specific classes AND `kp-dt-*` prefixed classes for custom styling:

```html
<!-- Example table element -->
<table class="uk-table uk-table-striped kp-dt-table-uikit datatables-table">
```

This allows you to:

1. Override specific styles with `kp-dt-*` classes
2. Target elements consistently across themes
3. Add custom CSS without conflicts

### Custom Theme CSS

Each theme has its own CSS file at:

```
/vendor/kevinpirnie/kpt-datatables/src/assets/css/themes/{theme}.css
```

You can copy and modify these files for custom styling.

## Advanced Usage with JOINs

### JOIN Tables with Aliases

```php
$dataTable = new DataTables($dbConfig);

echo $dataTable
    ->theme('bootstrap')
    ->table('kptv_stream_other s')  // Main table with alias
    ->primaryKey('s.id')            // Qualified primary key
    ->join('LEFT', 'kptv_stream_providers p', 's.p_id = p.id')  // JOIN with alias
    ->columns([
        's.id' => 'ID',             // Qualified column names
        's_orig_name' => 'Original Name',
        's_stream_uri' => 'Stream URI',
        'p.sp_name' => 'Provider',  // Column from joined table
    ])
    ->columnClasses([
        's.id' => 'uk-min-width',
        's_stream_uri' => 'txt-truncate'
    ])
    ->sortable(['s_orig_name', 'p.sp_name'])  // Sort on joined columns
    ->perPage(25)
    ->pageSizeOptions([25, 50, 100, 250], true)
    ->bulkActions(true)
    ->actionGroups([
        [
            'export' => [
                'icon' => 'download',
                'title' => 'Export Record',
                'class' => 'btn-export',
                'href' => '/export/{id}'
            ]
        ],
        ['delete']  // Built-in delete action
    ])
    ->renderDataTableComponent();
```

## Complete Configuration Example

```php
$dataTable = new DataTables($dbConfig);

echo $dataTable
    ->theme('uikit')  // Choose your theme
    ->table('users u')
    ->primaryKey('u.user_id')
    ->join('LEFT', 'user_roles r', 'u.role_id = r.role_id')
    ->join('LEFT', 'departments d', 'u.dept_id = d.dept_id')
    ->columns([
        'u.user_id' => 'ID',
        'u.name' => 'Name',
        'u.email' => 'Email',
        'r.role_name' => 'Role',
        'd.dept_name' => 'Department',
        'u.status' => [
            'label' => 'Status',
            'type' => 'boolean'
        ]
    ])
    
    // Configure sorting and editing
    ->sortable(['u.name', 'u.email', 'r.role_name', 'd.dept_name'])
    ->inlineEditable(['u.name', 'u.email', 'u.status'])
    
    // Pagination options
    ->perPage(25)
    ->pageSizeOptions([10, 25, 50, 100], true)
    
    // Enable bulk actions with custom callbacks
    ->bulkActions(true, [
        'activate' => [
            'label' => 'Activate Selected',
            'icon' => 'check',
            'class' => 'uk-button-secondary',
            'confirm' => 'Activate selected users?',
            'callback' => function($ids, $db, $table) {
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                return $db->query("UPDATE users SET status = 'active' WHERE user_id IN ({$placeholders})")
                          ->bind($ids)
                          ->execute();
            },
            'success_message' => 'Users activated successfully',
            'error_message' => 'Failed to activate users'
        ]
    ])
    
    // Configure action button groups
    ->actionGroups([
        [
            'email' => [
                'icon' => 'mail',
                'title' => 'Send Email',
                'class' => 'btn-email',
                'href' => '/email/{id}'
            ],
            'profile' => [
                'icon' => 'user',
                'title' => 'View Profile',
                'href' => '/profile/{id}'
            ]
        ],
        ['edit', 'delete']  // Built-in actions
    ])
    
    // Add form configuration
    ->addForm('Add New User', [
        'name' => [
            'type' => 'text',
            'label' => 'Full Name',
            'required' => true,
            'placeholder' => 'Enter full name'
        ],
        'email' => [
            'type' => 'email',
            'label' => 'Email Address',
            'required' => true,
            'placeholder' => 'user@example.com'
        ],
        'role_id' => [
            'type' => 'select',
            'label' => 'Role',
            'required' => true,
            'options' => [
                '1' => 'Administrator',
                '2' => 'Editor',
                '3' => 'User'
            ]
        ],
        'avatar' => [
            'type' => 'file',
            'label' => 'Avatar Image'
        ],
        'status' => [
            'type' => 'boolean',
            'label' => 'Active Status',
            'value' => '1'
        ]
    ])
    
    // Edit form (similar to add form)
    ->editForm('Edit User', [
        'name' => [
            'type' => 'text',
            'label' => 'Full Name',
            'required' => true
        ],
        'email' => [
            'type' => 'email',
            'label' => 'Email Address',
            'required' => true
        ],
        'role_id' => [
            'type' => 'select',
            'label' => 'Role',
            'required' => true,
            'options' => [
                '1' => 'Administrator',
                '2' => 'Editor',
                '3' => 'User'
            ]
        ],
        'status' => [
            'type' => 'boolean',
            'label' => 'Active Status'
        ]
    ])
    
    // CSS customization
    ->tableClass('uk-table uk-table-striped uk-table-hover custom-table')
    ->rowClass('custom-row')
    ->columnClasses([
        'u.name' => 'uk-text-bold',
        'u.email' => 'uk-text-primary',
        'u.status' => 'uk-text-center'
    ])
    
    // File upload configuration
    ->fileUpload('uploads/avatars/', ['jpg', 'jpeg', 'png', 'gif'], 5242880) // 5MB limit
    
    ->renderDataTableComponent();
```

## Complete Working Example

```php
<?php
require 'vendor/autoload.php';

use KPT\DataTables\DataTables;

// Database configuration
$dbConfig = [
    'server' => 'localhost',
    'schema' => 'your_database',
    'username' => 'your_username',
    'password' => 'your_password',
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci'
];

// Create DataTables instance
$dataTable = new DataTables($dbConfig);

// Handle AJAX requests first
if (isset($_POST['action']) || isset($_GET['action'])) {
    $dataTable->handleAjax();
}

// Choose your theme
$theme = 'uikit'; // 'plain', 'uikit', 'bootstrap', 'tailwind'
?>
<!DOCTYPE html>
<html>
<head>
    <title>DataTables Example</title>
    <?php echo DataTables::getCssIncludes($theme, true, true); ?>
</head>
<body>
    <div class="uk-container uk-margin-top">
        <?php
        echo $dataTable
            ->theme($theme)
            ->table('users u')
            ->primaryKey('u.id')
            ->join('LEFT', 'user_roles r', 'u.role_id = r.role_id')
            ->columns([
                'u.id' => 'ID',
                'u.name' => 'Name',
                'u.email' => 'Email',
                'r.role_name' => 'Role',
                'u.status' => [
                    'label' => 'Status',
                    'type' => 'boolean'
                ]
            ])
            ->sortable(['u.name', 'u.email', 'r.role_name'])
            ->inlineEditable(['u.name', 'u.email', 'u.status'])
            ->bulkActions(true)
            ->addForm('Add User', [
                'name' => [
                    'type' => 'text',
                    'label' => 'Full Name',
                    'required' => true
                ],
                'email' => [
                    'type' => 'email',
                    'label' => 'Email',
                    'required' => true
                ],
                'role_id' => [
                    'type' => 'select',
                    'label' => 'Role',
                    'options' => [
                        '1' => 'Admin',
                        '2' => 'User'
                    ]
                ],
                'status' => [
                    'type' => 'boolean',
                    'label' => 'Active',
                    'value' => '1'
                ]
            ])
            ->editForm('Edit User', [
                'name' => [
                    'type' => 'text',
                    'label' => 'Full Name',
                    'required' => true
                ],
                'email' => [
                    'type' => 'email',
                    'label' => 'Email',
                    'required' => true
                ],
                'role_id' => [
                    'type' => 'select',
                    'label' => 'Role',
                    'options' => [
                        '1' => 'Admin',
                        '2' => 'User'
                    ]
                ],
                'status' => [
                    'type' => 'boolean',
                    'label' => 'Active'
                ]
            ])
            ->renderDataTableComponent();
        ?>
    </div>
    <?php echo DataTables::getJsIncludes($theme, true); ?>
</body>
</html>
```

## API Methods

### Core Configuration

- `theme(string $theme, bool $includeCdn = true)` - Set UI framework theme
- `table(string $tableName)` - Set the database table (supports aliases)
- `primaryKey(string $column)` - Set primary key column (supports qualified names)
- `database(array $config)` - Configure database connection
- `columns(array $columns)` - Configure table columns (supports qualified names)
- `join(string $type, string $table, string $condition)` - Add JOIN clause with alias support
- `where(array $conditions)` - Add WHERE conditions to filter records

### Display Options

- `sortable(array $columns)` - Set sortable columns (supports qualified names)
- `inlineEditable(array $columns)` - Set inline editable columns
- `search(bool $enabled)` - Enable/disable search
- `perPage(int $count)` - Set records per page
- `pageSizeOptions(array $options, bool $includeAll)` - Set page size options

### Actions and Forms

- `actions(string $position, bool $showEdit, bool $showDelete, array $customActions)` - Configure action buttons
- `actionGroups(array $groups)` - Configure grouped actions with separators
- `bulkActions(bool $enabled, array $actions)` - Configure bulk actions with callbacks
- `addForm(string $title, array $fields, bool $ajax)` - Configure add form
- `editForm(string $title, array $fields, bool $ajax)` - Configure edit form

### Calculations and Aggregations

- `calculatedColumn(string $alias, string $label, array $columns, string $operator)` - Add a computed column
- `calculatedColumnRaw(string $alias, string $label, string $expression)` - Add a computed column with custom SQL
- `footerAggregate(string $column, string $type, string $scope)` - Configure footer aggregation for a column
- `footerAggregateColumns(array $columns, string $type, string $scope)` - Configure footer aggregation for multiple columns

### Styling

- `tableClass(string $class)` - Set table CSS class
- `rowClass(string $class)` - Set row CSS class base
- `columnClasses(array $classes)` - Set column-specific CSS classes

### File Handling

- `fileUpload(string $path, array $extensions, int $maxSize)` - Configure file uploads

### Rendering

- `renderDataTableComponent()` - Generate complete HTML output
- `handleAjax()` - Handle AJAX requests

### Static Methods

- `DataTables::getCssIncludes(string $theme, bool $includeCdn, bool $useMinified)` - Get CSS include tags
- `DataTables::getJsIncludes(string $theme, bool $includeCdn, bool $useMinified)` - Get JavaScript include tags

## Field Types

### Text Inputs

```php
'field_name' => [
    'type' => 'text', // text, email, url, tel, number, password
    'label' => 'Field Label',
    'required' => true,
    'placeholder' => 'Placeholder text',
    'class' => 'custom-css-class',
    'attributes' => ['maxlength' => '100']
]
```

### Boolean/Checkbox Fields

```php
'active' => [
    'type' => 'boolean', // Renders as select in forms, toggle in table
    'label' => 'Active Status'
],
'newsletter' => [
    'type' => 'checkbox',
    'label' => 'Subscribe to Newsletter',
    'value' => '1'
]
```

### Select Dropdown

```php
'category' => [
    'type' => 'select',
    'label' => 'Category',
    'required' => true,
    'options' => [
        '1' => 'Category 1',
        '2' => 'Category 2',
        '3' => 'Category 3'
    ]
]
```

### Select2 - AJAX Searchable Dropdown

For large datasets where loading all options upfront is impractical, use Select2 fields with server-side search.

```php
'user_id' => [
    'type' => 'select2',
    'label' => 'User',
    'query' => 'SELECT id AS ID, u_name AS Label FROM kptv_users',
    'placeholder' => 'Select a user...',
    'required' => true,
    'min_search_chars' => 2,  // Minimum characters before search
    'max_results' => 50,       // Maximum results to return
    'class' => 'uk-width-1-1'
]
```

**Required Configuration:**

- `query` - SQL query that returns `ID` (value) and `Label` (display text) columns
- The query must use `AS ID` and `AS Label` aliases

**Optional Configuration:**

- `min_search_chars` - Minimum characters required before searching (default: 0)
- `max_results` - Maximum number of results to return (default: 50)
- `placeholder` - Placeholder text when no selection
- `required` - Whether the field is required
- `class` - CSS classes for styling

**Features:**

- ✅ AJAX-powered search as you type
- ✅ Debounced search (300ms delay)
- ✅ Works in add forms, edit forms, and inline editing
- ✅ Supports all themes (UIKit, Bootstrap, Tailwind, Plain)
- ✅ Displays labels in table, stores IDs in database
- ✅ Automatically loads current selection in edit mode

**Query Parameter Substitution:**

Use `{column_name}` placeholders in queries to reference other field values:

```php
'state' => [
    'type' => 'select',
    'options' => [/* states */]
],
'city' => [
    'type' => 'select2',
    'query' => 'SELECT id AS ID, city_name AS Label FROM cities WHERE state_id = {state}',
    'placeholder' => 'Select city...'
]
```

### File Upload

```php
'document' => [
    'type' => 'file',
    'label' => 'Upload Document'
]
```

## WHERE Conditions

### Filter Records with Custom Conditions

```php
->where([
    'AND' => [
        [
            'field' => 'status',
            'comparison' => '=',
            'value' => 'active'
        ],
        [
            'field' => 'created_at',
            'comparison' => '>=',
            'value' => '2024-01-01'
        ]
    ]
])
```

### Supported Comparison Operators

- `=`, `!=`, `<>`, `>`, `<`, `>=`, `<=`
- `LIKE`, `NOT LIKE`
- `IN`, `NOT IN` (with array values)
- `REGEXP`

## Calculated Columns

Add computed columns that perform math operations on existing numeric fields.

### Basic Calculated Column

```php
$dataTable
    ->table('order_items oi')
    ->columns([
        'oi.id' => 'ID',
        'oi.quantity' => 'Quantity',
        'oi.unit_price' => 'Unit Price',
    ])
    ->calculatedColumn('line_total', 'Line Total', ['oi.quantity', 'oi.unit_price'], '*')
    ->renderDataTableComponent();
```

### Supported Operators

| Operator | Description    | Example                          |
|----------|----------------|----------------------------------|
| `+`      | Addition       | `['col_a', 'col_b'], '+'`       |
| `-`      | Subtraction    | `['col_a', 'col_b'], '-'`       |
| `*`      | Multiplication | `['qty', 'price'], '*'`         |
| `/`      | Division       | `['total', 'count'], '/'`       |
| `%`      | Modulus        | `['value', 'divisor'], '%'`     |

### Multiple Columns

Calculations can span more than two columns:

```php
->calculatedColumn('total', 'Grand Total', ['subtotal', 'tax', 'shipping'], '+')
```

### Custom SQL Expressions

For complex calculations that go beyond a single operator:

```php
->calculatedColumnRaw('profit_margin', 'Margin %', '((oi.sell_price - oi.cost_price) / oi.sell_price) * 100')
```

## Footer Aggregations

Display sum and/or average values in the table footer for numeric columns, scoped to the current page, the full filtered recordset, or both.

### Basic Usage

```php
$dataTable
    ->table('orders')
    ->columns([
        'id' => 'ID',
        'customer' => 'Customer',
        'amount' => 'Amount',
        'tax' => 'Tax',
    ])
    ->footerAggregate('amount', 'sum', 'both')
    ->footerAggregate('tax', 'avg', 'all')
    ->renderDataTableComponent();
```

### Aggregate Multiple Columns

Apply the same aggregation type and scope to multiple columns at once:

```php
->footerAggregateColumns(['amount', 'tax', 'shipping'], 'both', 'both')
```

### Configuration Options

**Type** - What calculation to display:

| Type   | Description                     |
|--------|---------------------------------|
| `sum`  | Total of all values             |
| `avg`  | Average of all values           |
| `both` | Display both sum and average    |

**Scope** - Which records to calculate across:

| Scope  | Description                                      |
|--------|--------------------------------------------------|
| `page` | Calculate using only records on the current page  |
| `all`  | Calculate using the full filtered recordset       |
| `both` | Display both page-level and full recordset values |

### Aggregation with Calculated Columns

Footer aggregations work with calculated columns by referencing the alias:

```php
$dataTable
    ->table('order_items oi')
    ->columns([
        'oi.id' => 'ID',
        'oi.quantity' => 'Quantity',
        'oi.unit_price' => 'Unit Price',
    ])
    ->calculatedColumn('line_total', 'Line Total', ['oi.quantity', 'oi.unit_price'], '*')
    ->footerAggregate('line_total', 'sum', 'both')
    ->footerAggregate('oi.unit_price', 'avg', 'all')
    ->renderDataTableComponent();
```

### Footer Row Output

Depending on configuration, the table footer will display up to four aggregation rows:

| Row Label | Type | Scope | Description                          |
|-----------|------|-------|--------------------------------------|
| Page Sum  | sum  | page  | Sum of values on the current page    |
| Total Sum | sum  | all   | Sum of all filtered records          |
| Page Avg  | avg  | page  | Average of values on the current page|
| Total Avg | avg  | all   | Average of all filtered records      |

## Browser Support

- Chrome 60+
- Firefox 60+
- Safari 12+
- Edge 79+

## Contributing

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add some amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## Testing

```bash
# Run tests
composer test

# Run tests with coverage
composer test-coverage

# Run static analysis
composer phpstan

# Run code style check
composer cs-check
```

## Building Assets

```bash
# Install Node dependencies
npm install

# Build everything (Tailwind + JS bundle + CSS minification)
npm run build

# Build JS bundle only
npm run build:js

# Build CSS minification only
npm run build:css

# Tailwind watch mode for development
npm run watch:tailwind
```

## Security

If you discover any security-related issues, please email <security@kpirnie.com> instead of using the issue tracker.

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.

## Credits

- [Kevin Pirnie](https://github.com/kpirnie)
- [UIKit3](https://getuikit.com/) for the default UI framework
- [Bootstrap](https://getbootstrap.com/) for Bootstrap theme support
- [Tailwind CSS](https://tailwindcss.com/) for Tailwind theme support
- All contributors

## Support

- **Issues**: [GitHub Issues](https://github.com/kpirnie/kpt-datatables/issues)

## Roadmap

- [ ] Export functionality (CSV, Excel, PDF)
- [ ] REST API endpoints
- [x] Multi-framework theme support
- [x] Calculated columns and footer aggregations

---

**Made with ❤️ by [Kevin Pirnie](https://kkevinpirniecom)**
