<?php

/**
 * Filters View - Refactored to use modular system
 * 
 * @since 8.4
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Library
 */

defined('KPTV_PATH') || die('Direct Access is not allowed!');

// setup the user id
$userId = KPTV_User::get_current_user()->id;

// Configure database via constructor
$dbconf = (array) KPTV::get_setting('database');

// fire up the datatables class
$dt = new \KPT\DataTables($dbconf);

// setup the form fields
$formFields = KPTV::view_configs('filters', userId: $userId)->form;

// configure the datatable
$dt->table('kptv_stream_filters')
    ->theme('uikit', true)
    ->tableClass('uk-table uk-table-divider uk-table-small uk-margin-bottom')
    ->where([
        [ // unless specified as OR, it should always be AND
            'field' => 'u_id',
            'comparison' => '=', // =, !=, >, <, <>, <=, >=, LIKE, NOT LIKE, IN, NOT IN, REGEXP
            'value' => $userId
        ],
    ])
    ->columns([
        'id' => 'ID',
        'sf_active' => ['type' => 'boolean', 'label' => 'Active'],
        'sf_type_id' => [
            'label' => 'Type',
            'type' => 'select',
            'options' => [
                '0' => 'Include Name (regex)',
                '1' => 'Exclude Name',
                '2' => 'Exclude Name (regex)',
                '3' => 'Exclude Stream (regex)',
                '4' => 'Exclude Group (regex)',
            ]
        ],
        'sf_filter' => 'Filter',
    ])
    ->columnClasses([
        'id' => 'hide-col',
    ])
    ->sortable(['sf_active', 'sf_type_id',])
    ->defaultSort('sf_type_id', 'ASC')
    ->inlineEditable(['sf_active', 'sf_type_id', 'sf_filter'])
    ->perPage(25)
    ->pageSizeOptions([25, 50, 100, 250], true)
    ->bulkActions(true)
    ->actions('end', true, true, [])
    ->addForm('Add a Filter', $formFields, class: 'uk-grid-small uk-grid')
    ->editForm('Update a Filter', $formFields, class: 'uk-grid-small uk-grid');

// Handle AJAX requests (before any HTML output)
if (isset($_POST['action']) || isset($_GET['action'])) {
    $dt->handleAjax();
}

// pull in the header
KPTV::pull_header();
?>
<h2 class="kptv-heading uk-heading-bullet">Stream Filters</h2>
<div class="uk-border-bottom">
    <?php

    // pull in the control panel
    KPTV::include_view('common/control-panel', ['dt' => $dt]);
    ?>
</div>
<div class="uk-margin">
    <?php

    // write out the datatable component
    echo $dt->renderDataTableComponent();
    ?>
</div>
<div class="uk-border-top">
    <?php

    // pull in the control panel
    KPTV::include_view('common/control-panel', ['dt' => $dt]);
    ?>
</div>
<?php

// pull in the footer
KPTV::pull_footer();

// clean up
unset($dt, $formFields, $dbconf);
