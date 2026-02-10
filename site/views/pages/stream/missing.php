<?php

/**
 * Missing Streams View
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
$dt = new KPT\DataTables($dbconf);

// setup the row actions - extract from view_configs
$bulkActionsConfig = KPTV::view_configs('missing')->bulk;

// setup the row actions - extract from view_configs
$rowActionsConfig = KPTV::view_configs('missing')->row;

// configure the datatable
$dt->table('kptv_stream_missing m')
    ->primaryKey('m.id')  // Use qualified primary key
    ->join('LEFT', 'kptv_stream_providers p', 'm.p_id = p.id')
    ->join('LEFT', 'kptv_streams s', 'm.stream_id = s.id')
    ->where([
        [ // unless specified as OR, it should always be AND
            'field' => 'm.u_id',
            'comparison' => '=', // =, !=, >, <, <>, <=, >=, LIKE, NOT LIKE, IN, NOT IN, REGEXP
            'value' => $userId
        ],
    ])
    ->tableClass('uk-table uk-table-divider uk-table-small uk-margin-bottom')
    ->columns([
        'm.id' => 'ID',
        'm.stream_id' => "StreamID",
        'COALESCE(s.s_stream_uri, "N/A") AS TheStream' => "Stream",
        'COALESCE(s.s_orig_name, "N/A") AS TheOrigName' => 'Original Name',
        'p.sp_name' => 'Provider',
    ])
    ->columnClasses([
        'm.id' => 'hide-col',
        'm.stream_id' => 'hide-col',
        'TheStream' => 'txt-truncate',
        'TheOrigName' => 'txt-truncate',
        'p.sp_name' => 'txt-truncate',
    ])
    ->sortable(['TheOrigName', 'p.sp_name'])
    ->defaultSort('TheOrigName', 'ASC')
    ->perPage(25)
    ->pageSizeOptions([25, 50, 100, 250], true)
    ->bulkActions(true, $bulkActionsConfig)
    ->actionGroups($rowActionsConfig);

// Handle AJAX requests (before any HTML output)
if (isset($_POST['action']) || isset($_GET['action'])) {
    $dt->handleAjax();
}

// pull in the header
KPTV::pull_header();
?>
<h2 class="kptv-heading uk-heading-bullet">Missing Streams</h2>
<p class="uk-text-meta uk-margin-remove-top">These streams exist in your database, but not at any of your providers.</p>
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
unset($dt, $formFields, $actionGroups, $bulkActions, $dbconf);
