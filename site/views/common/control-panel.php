<?php

/**
 * Control Panel Component
 * 
 * @param DataTables $dt The datatable class
 */

defined('KPTV_PATH') || die('Direct Access is not allowed!');

?>
<div class="uk-width-1-1">
    <div class="uk-margin-bottom" uk-grid>
        <div class="uk-width-1-1 uk-width-1-2@s uk-grid-collapse uk-flex-center uk-flex-left@s" uk-grid>
            <?php echo $dt->renderSearchFormComponent(); ?>
        </div>
        <div class="uk-width-1-1 uk-width-1-2@s uk-flex uk-flex-right@s uk-flex-center uk-flex-left@s uk-padding-tiny-top bulk-action-bar">
            <?php if (in_array($dt->getBaseTableName(), ['kptv_stream_other', 'kptv_stream_missing'])) { ?>
                <style>
                    [uk-icon="plus"] {
                        display: none !important;
                    }
                </style>
            <?php } ?>
            <?php echo $dt->renderBulkActionsComponent(); ?>
        </div>
    </div>
    <?php if (! in_array($dt->getBaseTableName(), ['kptv_stream_providers', 'kptv_stream_epgs',])) { ?>
        <div class="uk-width-1-1">
            <div class="" uk-grid>
                <div class="uk-width-1-1 uk-width-1-2@s">
                    <div class="uk-text-center uk-text-left@s">
                        <div class="uk-margin-top-remove-s"><?php echo $dt->renderPageSizeSelectorComponent(true); ?></div>
                    </div>
                </div>
                <div class="uk-width-1-1 uk-width-1-2@s uk-flex uk-flex-right@s uk-flex-center uk-text-center uk-text-right@s">
                    <?php echo $dt->renderPaginationComponent(); ?>
                </div>
            </div>
        </div>
    <?php } ?>
</div>