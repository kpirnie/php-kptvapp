<?php

/**
 * Streams View
 * 
 * @since 8.4
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Library
 */

defined('KPTV_PATH') || die('Direct Access is not allowed!');

// make sure we've got our namespaces...
use KPT\DataTables;

// Handle stream type filter (passed from router)
$type_filter = $which ?? 'live';
$valid_types = ['live' => 0, 'series' => 5, 'other' => 99];
//$valid_types = ['live' => 0, 'vod' => 4, 'series' => 5, 'other' => 99];
$type_value = $valid_types[$type_filter] ?? null;

// Handle the stream active filter (passed from router)
$active_filter = $type ?? 'active';
$valid_active = ['active' => 1, 'inactive' => 0];
$active_value = $valid_active[$active_filter] ?? null;

// setup the user id
$userId = KPTV_User::get_current_user()->id;

// setup the form fields
$formFieldsConfig = KPTV::view_configs('streams', userId: $userId)->form;

// setup the row actions - extract from view_configs
$rowActionsConfig = KPTV::view_configs('streams')->row;

// setup the bulk actions - extract from view_configs
$bulkActionsConfig = KPTV::view_configs('streams')->bulk;

// Configure database via constructor
$dbconf = (array) KPTV::get_setting('database');

// fire up the datatables class
$dt = new DataTables($dbconf);

// configure the datatable
$dt->table('kptv_streams s')
    ->primaryKey('s.id')  // Use qualified primary key
    ->join('LEFT', 'kptv_stream_providers p', 's.p_id = p.id')
    ->where([
        [ // unless specified as OR, it should always be AND
            'field' => 's.u_id',
            'comparison' => '=', // =, !=, >, <, <>, <=, >=, LIKE, NOT LIKE, IN, NOT IN, REGEXP
            'value' => $userId
        ],
        [ // unless specified as OR, it should always be AND
            'field' => 's_type_id',
            'comparison' => '=', // =, !=, >, <, <>, <=, >=, LIKE, NOT LIKE, IN, NOT IN, REGEXP
            'value' => $type_value
        ],
        [ // unless specified as OR, it should always be AND
            'field' => 's_active',
            'comparison' => '=', // =, !=, >, <, <>, <=, >=, LIKE, NOT LIKE, IN, NOT IN, REGEXP
            'value' => ($type_value == 99) ? 0 : $active_value,
        ],
    ])
    ->tableClass('uk-table uk-table-divider uk-table-small uk-margin-bottom')
    ->columns([
        's.id' => 'ID',
        's_active' => ['label' => 'Act', 'type' => 'boolean'],
        's_channel' => 'Ch',
        's_name' => 'Name',
        's_orig_name' => 'Orig. Name',
        's_tvg_id' => 'TVG ID',
        'p.sp_name' => 'Provider',
        's_tvg_logo' => ['label' => 'Logo', 'type' => 'image'],
    ])
    ->columnClasses([
        's.id' => 'hide-col',
        's_channel' => 'uk-min-width',
        's_tvg_id' => 'txt-truncate',
        'p.sp_name' => 'txt-truncate',
    ])
    ->sortable(['s_name', 's_channel', 's_tvg_id', 'p.sp_name'])
    ->defaultSort('s_name', 'ASC')
    ->inlineEditable(['s_active', 's_channel', 's_name', 's_tvg_logo', 's_tvg_id', 's.u_id',])
    ->perPage(25)
    ->pageSizeOptions([25, 50, 100, 250], true)
    ->bulkActions(true, $bulkActionsConfig[$type_filter])
    ->addForm('Add a Stream', $formFieldsConfig, class: 'uk-grid-small uk-grid')
    ->editForm('Update a Stream', $formFieldsConfig, class: 'uk-grid-small uk-grid')
    ->actionGroups([
        [
            'playstream' => [
                'icon' => 'play',
                'title' => 'Try to Play Stream',
                'class' => 'play-stream uk-margin-tiny-full',
                'href' => '#{s_orig_name}',
                'attributes' => [
                    'data-stream-url' => '{s_stream_uri}',
                    'data-stream-name' => '{s_orig_name}',
                ]
            ],
            'copystream' => [
                'icon' => 'link',
                'title' => 'Copy Stream Link',
                'class' => 'copy-link uk-margin-tiny-full',
                'href' => '{s_stream_uri}',
            ]
        ],
        $rowActionsConfig[$type_filter],
        ['edit', 'delete'],
    ]);

// Handle AJAX requests (before any HTML output)
if (isset($_POST['action']) || isset($_GET['action'])) {
    $dt->handleAjax();
}

// pull in the header
KPTV::pull_header();
?>
<h2 class="kptv-heading uk-heading-bullet"><?php echo (isset($type)) ? ucfirst($type) : ''; ?> <?php echo ucfirst($which); ?> Streams</h2>
<div class="">
    <?php

    // pull in the control panel
    KPTV::include_view('common/control-panel', ['dt' => $dt]);
    ?>
</div>
<div class="uk-margin the-datatable">
    <?php

    // write out the datatable component
    echo $dt->renderDataTableComponent();
    ?>
</div>
<div class="">
    <?php

    // pull in the control panel
    KPTV::include_view('common/control-panel', ['dt' => $dt]);
    ?>
</div>
<?php

// pull in the footer
KPTV::pull_footer();

// clean up
unset($dt, $formFields, $actionGroups, $bulkActions, $dbconf);
