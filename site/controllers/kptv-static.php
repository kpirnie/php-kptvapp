<?php

/**
 * Static Functions
 * 
 * This is our primary static object class
 * 
 * @since 8.4
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Library
 * 
 */

// define the primary app path if not already defined
defined('KPTV_PATH') || die('Direct Access is not allowed!');

// make sure the class does not already exist
if (! class_exists('KPTV_Static')) {

    /** 
     * Class Static
     * 
     * OTV Static Objects
     * 
     * @since 8.4
     * @access public
     * @author Kevin Pirnie <me@kpirnie.com>
     * @package KP Library
     * 
     * @var int MINUTE_IN_SECONDS Constant defining 60 seconds
     * @var int HOUR_IN_SECONDS Constant defining 3600 seconds
     * @var int DAY_IN_SECONDS Constant defining 86400 seconds
     * @var int WEEK_IN_SECONDS Constant defining 604800 seconds based on 7 days
     * @var int MONTH_IN_SECONDS Constant defining 2592000 seconds based on 30 days
     * @var int YEAR_IN_SECONDS Constant defining 31536000 seconds based on 365 days
     * 
     */
    class KPTV_Static
    {

        /**
         * These are our static time constants
         * They look familiar, because we're mimicing Wordpress's
         * time constants
         */
        const MINUTE_IN_SECONDS = 60;
        const HOUR_IN_SECONDS = (self::MINUTE_IN_SECONDS * 60);
        const DAY_IN_SECONDS = (self::HOUR_IN_SECONDS * 24);
        const WEEK_IN_SECONDS = (self::DAY_IN_SECONDS * 7);
        const MONTH_IN_SECONDS = (self::DAY_IN_SECONDS * 30);
        const YEAR_IN_SECONDS = (self::DAY_IN_SECONDS * 365);

        /** 
         * view_configs
         * 
         * Just something to centralize the view configs
         * 
         * @since 8.4
         * @access public
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @param string $which The config we need
         * @param array $extras Optional named parameters (userId, action, etc.)
         * 
         * @return object This method returns an object representing the configuration needed
         * 
         */
        public static function view_configs(string $which, ...$extras): object
        {

            // if we have extras extract them into the variables
            extract($extras);
            $userForExport = $extras['userForExport'] ?? '';
            $userId = $extras['userId'] ?? 0;

            // just return the matching config we need to present
            return (object) match ($which) {
                'epg' => [
                    'bulk' => [],
                    'row' => [],
                    'form' => [
                        'u_id' => [
                            'type' => 'hidden',
                            'value' => $userId,
                            'required' => true
                        ],
                        'se_active' => [
                            'label' => 'Active',
                            'type' => 'boolean',
                            'required' => true,
                            'class' => 'uk-width-1-2 uk-margin-bottom',
                        ],
                        'se_name' => [
                            'label' => 'Name',
                            'type' => 'text',
                            'class' => 'uk-width-1-2 uk-margin-bottom',
                        ],
                        'se_source' => [
                            'type' => 'url',
                            'label' => 'Source',
                            'class' => 'uk-width-1-1',
                        ],
                    ]
                ],
                'filters' => [
                    'bulk' => [],
                    'row' => [],
                    'form' => [
                        'u_id' => [
                            'label' => 'User',
                            'type' => 'select2',
                            'query' => 'SELECT id AS ID, u_name AS Label FROM kptv_users',
                            'placeholder' => 'Select a user...',
                            'required' => true,
                            'min_search_chars' => 2,
                            'max_results' => 50,
                            'class' => 'uk-width-1-1',
                        ],
                        'sf_active' => [
                            'label' => 'Active',
                            'type' => 'boolean',
                            'required' => true,
                            'class' => 'uk-width-1-2 uk-margin-bottom',
                        ],
                        'sf_type_id' => [
                            'label' => 'Filter Type',
                            'type' => 'select',
                            'required' => true,
                            'options' => [
                                '0' => 'Include Name (regex)',
                                '1' => 'Exclude Name',
                                '2' => 'Exclude Name (regex)',
                                '3' => 'Exclude Stream (regex)',
                                '4' => 'Exclude Group (regex)',
                            ],
                            'class' => 'uk-width-1-2 uk-margin-bottom',
                        ],
                        'sf_filter' => [
                            'type' => 'text',
                            'label' => 'Filter',
                            'class' => 'uk-width-1-1',
                        ],
                        /*'u_id' => [
                            'type' => 'hidden',
                            'value' => $userId,
                            'required' => true
                        ],*/

                    ]
                ],
                'providers' => [
                    'bulk' => [],
                    'row' => [
                        [
                            'html1' => [
                                'location' => 'after',
                                'content' => '<br class="action-nl" />'
                            ],
                            'html2' => [
                                'location' => 'before',
                                'content' => '<strong>XC: </strong>'
                            ],
                            'exportlivexc' => [
                                'icon' => 'link',
                                'title' => 'Copy Domain',
                                'class' => 'copy-link',
                                'href' => KPTV_XC_URI,
                            ],
                            'exportseriesxc' => [
                                'icon' => 'users',
                                'title' => 'Copy Username',
                                'class' => 'copy-link',
                                'href' => '{id}',
                            ],
                            'exportvodxc' => [
                                'icon' => 'server',
                                'title' => 'Copy Password',
                                'class' => 'copy-link',
                                'href' => $userForExport,
                            ],
                        ],
                        [
                            'html1' => [
                                'location' => 'before',
                                'content' => '<strong>M3U: </strong>'
                            ],
                            'html2' => [
                                'location' => 'after',
                                'content' => '<br class="action-nl" />'
                            ],
                            'exportlive' => [
                                'icon' => 'tv',
                                'title' => 'Export Live M3U',
                                'class' => 'copy-link',
                                'href' => '' . KPTV_URI . 'playlist/' . $userForExport . '/{id}/live',
                            ],
                            'exportseries' => [
                                'icon' => 'album',
                                'title' => 'Export Series M3U',
                                'class' => 'copy-link',
                                'href' => '' . KPTV_URI . 'playlist/' . $userForExport . '/{id}/series',
                            ],
                            /*'exportvod' => [
                                'icon' => 'video-camera', 
                                'title' => 'Export VOD M3U',
                                'class' => 'copy-link',
                                'href' => '' . KPTV_URI . 'playlist/' . $userForExport . '/{id}/vod',
                            ],*/
                        ],
                        [
                            'delprovider' => [
                                'icon' => 'trash',
                                'title' => 'Delete this Provider<br />(also delete\'s all associated streams)',
                                'success_message' => 'Provider and all it\'s streams have been deleted.',
                                'error_message' => 'Failed to delete the provider.',
                                'confirm' => 'Are you want to remove this provider and all it\'s streams?',
                                'callback' => function ($rowId, $rowData, $db, $tableName) {

                                    // make sure we have a row ID
                                    if (empty($rowId)) return false;

                                    // Delete all streams for the provider first
                                    $db->query("DELETE FROM `kptv_streams` WHERE `p_id` = ?")
                                        ->bind($rowId)
                                        ->execute();
                                    // now delete the provider
                                    return $db->query("DELETE FROM {$tableName} WHERE id = ?")
                                        ->bind($rowId)
                                        ->execute() !== false;
                                },
                            ],
                        ],
                    ],
                    'form' => [
                        'u_id' => [
                            'type' => 'hidden',
                            'value' => $userId,
                            'required' => true
                        ],
                        'sp_name' => [
                            'type' => 'text',
                            'required' => true,
                            'label' => 'Name',
                            'class' => 'uk-width-1-1',
                        ],
                        'sp_type' => [
                            'type' => 'select',
                            'required' => true,
                            'class' => 'uk-width-1-2 uk-margin-bottom',
                            'label' => 'Type',
                            'options' => [
                                0 => 'XC API',
                                1 => 'M3U',
                            ],
                        ],
                        'sp_cnx_limit' => [
                            'type' => 'text',
                            'required' => true,
                            'class' => 'uk-width-1-2 uk-margin-bottom',
                            'label' => 'Connections',
                            'default' => 1,
                        ],
                        'sp_domain' => [
                            'type' => 'url',
                            'required' => true,
                            'label' => 'Domain / URL',
                            'class' => 'uk-width-1-1',
                        ],
                        'sp_username' => [
                            'type' => 'text',
                            'required' => false,
                            'class' => 'uk-width-1-2 uk-margin-bottom',
                            'label' => 'XC Username',
                        ],
                        'sp_password' => [
                            'type' => 'text',
                            'required' => false,
                            'class' => 'uk-width-1-2 uk-margin-bottom',
                            'label' => 'XC Password',
                        ],
                        'sp_stream_type' => [
                            'type' => 'select',
                            'required' => false,
                            'class' => 'uk-width-1-2 uk-margin-bottom',
                            'label' => 'Stream Type',
                            'options' => [
                                0 => 'MPEGTS',
                                1 => 'HLS',
                            ],
                        ],
                        'sp_should_filter' => [
                            'label' => 'Should Filter?',
                            'type' => 'boolean',
                            'required' => true,
                            'class' => 'uk-width-1-2 uk-margin-bottom',
                        ],
                        'sp_priority' => [
                            'type' => 'number',
                            'required' => false,
                            'class' => 'uk-width-1-2 uk-margin-bottom',
                            'label' => 'Order Priority',
                            'default' => 1,
                        ],
                        'sp_refresh_period' => [
                            'type' => 'number',
                            'required' => false,
                            'class' => 'uk-width-1-2 uk-margin-bottom',
                            'label' => 'Refresh Period',
                            'default' => 1,
                        ],
                    ]
                ],
                'streams' => [
                    'bulk' => [
                        'live' => [
                            'livestreamact' => [
                                'label' => '(De)Activate Streams',
                                'icon' => 'crosshairs',
                                'callback' => function ($selectedIds, $database, $tableName) {

                                    // make sure we have records selected
                                    if (empty($selectedIds)) return false;

                                    // setup the placeholders and the query
                                    $placeholders = implode(',', array_fill(0, count($selectedIds), '?'));
                                    $sql = "UPDATE {$tableName} SET s_active = NOT s_active WHERE id IN ({$placeholders})";

                                    // return the execution
                                    return $database->query($sql)
                                        ->bind($selectedIds)
                                        ->execute() !== false;
                                },
                                'confirm' => 'Are you sure you want to (de)activate these streams?',
                                'success_message' => 'Records (de)activated',
                                'error_message' => 'Failed to (de)activate'
                            ],
                            'movetoseries' => [
                                'label' => 'Move to Series Streams',
                                'icon' => 'album',
                                'confirm' => 'Move the selected records to series streams?',
                                'callback' => function ($selectedIds, $database, $tableName) {

                                    // make sure we have selected items
                                    if (empty($selectedIds)) return false;

                                    // Track success/failure
                                    $successCount = 0;

                                    try {
                                        // Process all selected IDs
                                        foreach ($selectedIds as $id) {
                                            $result = KPTV::moveToType($database, $id, 5);
                                            if ($result) {
                                                $successCount++;
                                            }
                                        }

                                        // return
                                        return $successCount > 0;
                                    } catch (\Exception $e) {
                                        $database->rollback();
                                        return false;
                                    }
                                },
                                'success_message' => 'Records moved to series streams successfully',
                                'error_message' => 'Failed to move some or all records to series streams'
                            ],
                            /*'movetovod' => [
                                'label' => 'Move to VOD Streams',
                                'icon' => 'video-camera',
                                'confirm' => 'Move the selected records to vod streams?',
                                'callback' => function( $selectedIds, $database, $tableName ) {

                                    // make sure we have selected items
                                    if ( empty( $selectedIds ) ) return false;
                                    
                                    // Track success/failure
                                    $successCount = 0;
                                    
                                    try {
                                        // Process all selected IDs
                                        foreach($selectedIds as $id) {
                                            $result = KPTV::moveToType( $database, $id, 4 );
                                            if ($result) {
                                                $successCount++;
                                            }
                                        }
                                        
                                        // return
                                        return $successCount > 0;
                                        
                                    } catch (\Exception $e) {
                                        $database->rollback();
                                        return false;
                                    }
                                },
                                'success_message' => 'Records moved to vod streams successfully',
                                'error_message' => 'Failed to move some or all records to vod streams'
                            ],*/
                            'movetoother' => [
                                'label' => 'Move to Other Streams',
                                'icon' => 'nut',
                                'confirm' => 'Move the selected records to other streams?',
                                'callback' => function ($selectedIds, $database, $tableName) {
                                    // make sure we have selected items
                                    if (empty($selectedIds)) return false;
                                    $successCount = 0;
                                    try {
                                        // Process all selected IDs
                                        foreach ($selectedIds as $id) {
                                            $result = KPTV::moveToType($database, $id, 99);
                                            if ($result) {
                                                $successCount++;
                                            }
                                        }

                                        // return
                                        return $successCount > 0;
                                    } catch (\Exception $e) {
                                        $database->rollback();
                                        return false;
                                    }
                                },
                                'success_message' => 'Records moved to other streams successfully',
                                'error_message' => 'Failed to move some or all records to other streams'
                            ],
                        ],
                        'series' => [
                            'seriesstreamact' => [
                                'label' => '(De)Activate Streams',
                                'icon' => 'crosshairs',
                                'callback' => function ($selectedIds, $database, $tableName) {

                                    // make sure we have records selected
                                    if (empty($selectedIds)) return false;

                                    // setup the placeholders and the query
                                    $placeholders = implode(',', array_fill(0, count($selectedIds), '?'));
                                    $sql = "UPDATE {$tableName} SET s_active = NOT s_active WHERE id IN ({$placeholders})";

                                    // return the execution
                                    return $database->query($sql)
                                        ->bind($selectedIds)
                                        ->execute() !== false;
                                },
                                'confirm' => 'Are you sure you want to (de)activate these streams?',
                                'success_message' => 'Records (de)activated',
                                'error_message' => 'Failed to (de)activate'
                            ],
                            'movetolive' => [
                                'label' => 'Move to Live Streams',
                                'icon' => 'tv',
                                'confirm' => 'Move the selected records to live streams?',
                                'callback' => function ($selectedIds, $database, $tableName) {

                                    // make sure we have selected items
                                    if (empty($selectedIds)) return false;

                                    $successCount = 0;
                                    try {
                                        // Process all selected IDs
                                        foreach ($selectedIds as $id) {
                                            $result = KPTV::moveToType($database, $id, 0);
                                            if ($result) {
                                                $successCount++;
                                            }
                                        }

                                        // return
                                        return $successCount > 0;
                                    } catch (\Exception $e) {
                                        $database->rollback();
                                        return false;
                                    }
                                },
                                'success_message' => 'Records moved to live streams successfully',
                                'error_message' => 'Failed to move some or all records to live streams'
                            ],
                            /*'movetovod' => [
                                'label' => 'Move to VOD Streams',
                                'icon' => 'video-camera',
                                'confirm' => 'Move the selected records to vod streams?',
                                'callback' => function( $selectedIds, $database, $tableName ) {
                                    
                                    // make sure we have selected items
                                    if ( empty( $selectedIds ) ) return false;

                                    $successCount = 0;
                                    try {
                                        // Process all selected IDs
                                        foreach($selectedIds as $id) {
                                            $result = KPTV::moveToType( $database, $id, 4 );
                                            if ($result) {
                                                $successCount++;
                                            }
                                        }
                                        
                                        // return
                                        return $successCount > 0;
                                        
                                    } catch (\Exception $e) {
                                        $database->rollback();
                                        return false;
                                    }
                                },
                                'success_message' => 'Records moved to vod streams successfully',
                                'error_message' => 'Failed to move some or all records to vod streams'
                            ],*/
                            'movetoother' => [
                                'label' => 'Move to Other Streams',
                                'icon' => 'nut',
                                'confirm' => 'Move the selected records to other streams?',
                                'callback' => function ($selectedIds, $database, $tableName) {
                                    // make sure we have selected items
                                    if (empty($selectedIds)) return false;
                                    $successCount = 0;
                                    try {
                                        // Process all selected IDs
                                        foreach ($selectedIds as $id) {
                                            $result = KPTV::moveToType($database, $id, 99);
                                            if ($result) {
                                                $successCount++;
                                            }
                                        }

                                        // return
                                        return $successCount > 0;
                                    } catch (\Exception $e) {
                                        $database->rollback();
                                        return false;
                                    }
                                },
                                'success_message' => 'Records moved to other streams successfully',
                                'error_message' => 'Failed to move some or all records to other streams'
                            ],
                        ],
                        /*'vod' => [

                            'movetolive' => [
                                'label' => 'Move to Live Streams',
                                'icon' => 'tv',
                                'confirm' => 'Move the selected records to live streams?',
                                'callback' => function( $selectedIds, $database, $tableName ) {

                                    // make sure we have selected items
                                    if ( empty( $selectedIds ) ) return false;

                                    $successCount = 0;
                                    try {
                                        // Process all selected IDs
                                        foreach($selectedIds as $id) {
                                            $result = KPTV::moveToType( $database, $id, 0 );
                                            if ($result) {
                                                $successCount++;
                                            }
                                        }
                                        
                                        // return
                                        return $successCount > 0;
                                        
                                    } catch (\Exception $e) {
                                        $database->rollback();
                                        return false;
                                    }
                                },
                                'success_message' => 'Records moved to live streams successfully',
                                'error_message' => 'Failed to move some or all records to live streams'
                            ],
                            'movetoseries' => [
                                'label' => 'Move to Series Streams',
                                'icon' => 'album',
                                'confirm' => 'Move the selected records to series streams?',
                                'callback' => function( $selectedIds, $database, $tableName ) {

                                    // make sure we have selected items
                                    if ( empty( $selectedIds ) ) return false;
                                    
                                    $successCount = 0;
                                    try {
                                        // Process all selected IDs
                                        foreach($selectedIds as $id) {
                                            $result = KPTV::moveToType( $database, $id, 5 );
                                            if ($result) {
                                                $successCount++;
                                            }
                                        }
                                        
                                        // return
                                        return $successCount > 0;
                                    
                                    } catch (\Exception $e) {
                                        $database->rollback();
                                        return false;
                                    }
                                },
                                'success_message' => 'Records moved to series streams successfully',
                                'error_message' => 'Failed to move some or all records to series streams'
                            ],
                            'movetoother' => [
                                'label' => 'Move to Other Streams',
                                'icon' => 'nut',
                                'confirm' => 'Move the selected records to other streams?',
                                'callback' => function( $selectedIds, $database, $tableName ) {
                                    // make sure we have selected items
                                    if ( empty( $selectedIds ) ) return false;
                                    $successCount = 0;
                                    try {
                                        // Process all selected IDs
                                        foreach($selectedIds as $id) {
                                            $result = KPTV::moveToType( $database, $id, 99 );
                                            if ($result) {
                                                $successCount++;
                                            }
                                        }
                                        
                                        // return
                                        return $successCount > 0;
                                        
                                    } catch (\Exception $e) {
                                        $database->rollback();
                                        return false;
                                    }
                                },
                                'success_message' => 'Records moved to other streams successfully',
                                'error_message' => 'Failed to move some or all records to other streams'
                            ],
                        ],*/
                        'other' => [
                            'movetolive' => [
                                'label' => 'Move to Live Streams',
                                'icon' => 'tv',
                                'confirm' => 'Move the selected records to live streams?',
                                'callback' => function ($selectedIds, $database, $tableName) {

                                    // make sure we have selected items
                                    if (empty($selectedIds)) return false;

                                    $successCount = 0;
                                    try {
                                        // Process all selected IDs
                                        foreach ($selectedIds as $id) {
                                            $result = KPTV::moveToType($database, $id, 0);
                                            if ($result) {
                                                $successCount++;
                                            }
                                        }

                                        // return
                                        return $successCount > 0;
                                    } catch (\Exception $e) {
                                        $database->rollback();
                                        return false;
                                    }
                                },
                                'success_message' => 'Records moved to live streams successfully',
                                'error_message' => 'Failed to move some or all records to live streams'
                            ],
                            'movetoseries' => [
                                'label' => 'Move to Series Streams',
                                'icon' => 'album',
                                'confirm' => 'Move the selected records to series streams?',
                                'callback' => function ($selectedIds, $database, $tableName) {

                                    // make sure we have selected items
                                    if (empty($selectedIds)) return false;
                                    $successCount = 0;
                                    try {
                                        // Process all selected IDs
                                        foreach ($selectedIds as $id) {
                                            $result = KPTV::moveToType($database, $id, 5);
                                            if ($result) {
                                                $successCount++;
                                            }
                                        }

                                        // return
                                        return $successCount > 0;
                                    } catch (\Exception $e) {
                                        $database->rollback();
                                        return false;
                                    }
                                },
                                'success_message' => 'Records moved to series streams successfully',
                                'error_message' => 'Failed to move some or all records to series streams'
                            ],
                            /*'movetovod' => [
                                'label' => 'Move to VOD Streams',
                                'icon' => 'video-camera',
                                'confirm' => 'Move the selected records to vod streams?',
                                'callback' => function( $selectedIds, $database, $tableName ) {

                                    // make sure we have selected items
                                    if ( empty( $selectedIds ) ) return false;
                                    $successCount = 0;
                                    try {
                                        // Process all selected IDs
                                        foreach($selectedIds as $id) {
                                            $result = KPTV::moveToType( $database, $id, 4 );
                                            if ($result) {
                                                $successCount++;
                                            }
                                        }
                                        var_dump($successCount);exit;
                                        // return
                                        return $successCount > 0;
                                        
                                    } catch (\Exception $e) {
                                        $database->rollback();
                                        return false;
                                    }
                                },
                                'success_message' => 'Records moved to vod streams successfully',
                                'error_message' => 'Failed to move some or all records to vod streams'
                            ],*/
                        ],
                    ],
                    'row' => [
                        'live' => [
                            'html' => [
                                'location' => 'both',
                                'content' => '<br class="action-nl" />'
                            ],
                            'moveseries' => [
                                'icon' => 'album',
                                'title' => 'Move This Stream to Series Streams',
                                'callback' => function ($rowId, $rowData, $database, $tableName) {

                                    // move the stream
                                    return KPTV::moveToType($database, $rowId, 5);
                                },
                                'confirm' => 'Are you sure you want to move this stream?',
                                'success_message' => 'The stream has been moved.',
                                'error_message' => 'Failed to move the stream.'
                            ],
                            /*'movevod' => [
                                'icon' => 'video-camera',
                                'title' => 'Move This Stream to VOD Streams',
                                'callback' => function($rowId, $rowData, $database, $tableName) {

                                    // move the stream
                                    return KPTV::moveToType( $database, $rowId, 4 );
                                },
                                'confirm' => 'Are you sure you want to move this stream?',
                                'success_message' => 'The stream has been moved.',
                                'error_message' => 'Failed to move the stream.'
                            ],*/
                            'moveother' => [
                                'icon' => 'nut',
                                'title' => 'Move This Stream to Other Streams',
                                'callback' => function ($rowId, $rowData, $database, $tableName) {

                                    // move the stream
                                    return KPTV::moveToType($database, $rowId, 99);
                                },
                                'confirm' => 'Are you sure you want to move this stream?',
                                'success_message' => 'The stream has been moved.',
                                'error_message' => 'Failed to move the stream.'
                            ],
                        ],
                        'series' => [
                            'html' => [
                                'location' => 'both',
                                'content' => '<br class="action-nl" />'
                            ],
                            'movelive' => [
                                'icon' => 'tv',
                                'title' => 'Move This Stream to Live Streams',
                                'callback' => function ($rowId, $rowData, $database, $tableName) {

                                    // move the stream
                                    return KPTV::moveToType($database, $rowId, 0);
                                },
                                'confirm' => 'Are you sure you want to move this stream?',
                                'success_message' => 'The stream has been moved.',
                                'error_message' => 'Failed to move the stream.'
                            ],
                            /*'movevod' => [
                                'icon' => 'video-camera',
                                'title' => 'Move This Stream to VOD Streams',
                                'callback' => function($rowId, $rowData, $database, $tableName) {

                                    // move the stream
                                    return KPTV::moveToType( $database, $rowId, 4 );
                                },
                                'confirm' => 'Are you sure you want to move this stream?',
                                'success_message' => 'The stream has been moved.',
                                'error_message' => 'Failed to move the stream.'
                            ],*/
                            'moveother' => [
                                'icon' => 'nut',
                                'title' => 'Move This Stream to Other Streams',
                                'callback' => function ($rowId, $rowData, $database, $tableName) {

                                    // move the stream
                                    return KPTV::moveToType($database, $rowId, 99);
                                },
                                'confirm' => 'Are you sure you want to move this stream?',
                                'success_message' => 'The stream has been moved.',
                                'error_message' => 'Failed to move the stream.'
                            ],
                        ],
                        /*'vod' => [
                            'html' => [
                                'location' => 'both',
                                'content' => '<br class="action-nl" />'
                            ],
                            'movelive' => [
                                'icon' => 'tv',
                                'title' => 'Move This Stream to Live Streams',
                                'callback' => function($rowId, $rowData, $database, $tableName) {

                                    // move the stream
                                    returnKPTV::moveToType( $database, $rowId, 0 );
                                },
                                'confirm' => 'Are you sure you want to move this stream?',
                                'success_message' => 'The stream has been moved.',
                                'error_message' => 'Failed to move the stream.'
                            ],
                            'moveseries' => [
                                'icon' => 'album',
                                'title' => 'Move This Stream to Series Streams',
                                'callback' => function($rowId, $rowData, $database, $tableName) {

                                    // move the stream
                                    return KPTV::moveToType( $database, $rowId, 5 );
                                },
                                'confirm' => 'Are you sure you want to move this stream?',
                                'success_message' => 'The stream has been moved.',
                                'error_message' => 'Failed to move the stream.'
                            ],
                            'movevod' => [
                                'icon' => 'video-camera',
                                'title' => 'Move This Stream to VOD Streams',
                                'callback' => function($rowId, $rowData, $database, $tableName) {

                                    // move the stream
                                    return KPTV::moveToType( $database, $rowId, 4 );
                                },
                                'confirm' => 'Are you sure you want to move this stream?',
                                'success_message' => 'The stream has been moved.',
                                'error_message' => 'Failed to move the stream.'
                            ],
                            'moveother' => [
                                'icon' => 'nut',
                                'title' => 'Move This Stream to Other Streams',
                                'callback' => function($rowId, $rowData, $database, $tableName) {

                                    // move the stream
                                    return KPTV::moveToType( $database, $rowId, 99 );
                                },
                                'confirm' => 'Are you sure you want to move this stream?',
                                'success_message' => 'The stream has been moved.',
                                'error_message' => 'Failed to move the stream.'
                            ],
                        ],*/
                        'other' => [
                            'html' => [
                                'location' => 'both',
                                'content' => '<br class="action-nl" />'
                            ],
                            'movelive' => [
                                'icon' => 'tv',
                                'title' => 'Move This Stream to Live Streams',
                                'callback' => function ($rowId, $rowData, $database, $tableName) {

                                    // move the stream
                                    return KPTV::moveToType($database, $rowId, 0);
                                },
                                'confirm' => 'Are you sure you want to move this stream?',
                                'success_message' => 'The stream has been moved.',
                                'error_message' => 'Failed to move the stream.'
                            ],
                            'moveseries' => [
                                'icon' => 'album',
                                'title' => 'Move This Stream to Series Streams',
                                'callback' => function ($rowId, $rowData, $database, $tableName) {

                                    // move the stream
                                    return KPTV::moveToType($database, $rowId, 5);
                                },
                                'confirm' => 'Are you sure you want to move this stream?',
                                'success_message' => 'The stream has been moved.',
                                'error_message' => 'Failed to move the stream.'
                            ],
                            /*'movevod' => [
                                'icon' => 'video-camera',
                                'title' => 'Move This Stream to VOD Streams',
                                'callback' => function($rowId, $rowData, $database, $tableName) {

                                    // move the stream
                                    return KPTV::moveToType( $database, $rowId, 4 );
                                },
                                'confirm' => 'Are you sure you want to move this stream?',
                                'success_message' => 'The stream has been moved.',
                                'error_message' => 'Failed to move the stream.'
                            ],*/
                        ],
                    ],
                    'form' => [
                        's.u_id' => [
                            'type' => 'hidden',
                            'value' => $userId,
                            'required' => true
                        ],

                        's_name' => [
                            'type' => 'text',
                            'required' => true,
                            'class' => 'uk-width-1-1 uk-margin-bottom',
                            'label' => 'Name',
                        ],
                        's_orig_name' => [
                            'type' => 'text',
                            'required' => true,
                            'class' => 'uk-width-1-1 uk-margin-bottom',
                            'label' => 'Original Name',
                        ],
                        's_stream_uri' => [
                            'type' => 'url',
                            'required' => true,
                            'class' => 'uk-width-1-1 uk-margin-bottom',
                            'label' => 'Stream URL',
                        ],
                        'p_id' => [
                            'type' => 'select',
                            'required' => true,
                            'class' => 'uk-width-1-1 uk-margin-bottom',
                            'label' => 'Provider',
                            'options' => KPTV::getProviders($userId),
                        ],
                        's_active' => [
                            'type' => 'boolean',
                            'label' => 'Stream Active?',
                            'class' => 'uk-width-1-2',
                        ],
                        's_type_id' => [
                            'type' => 'select',
                            'label' => 'Stream Type',
                            'options' => [
                                0 => 'Live',
                                5 => 'Series',
                                //4 => 'VOD',
                            ],
                            'class' => 'uk-width-1-2',
                        ],
                        's_channel' => [
                            'type' => 'text',
                            'class' => 'uk-width-1-2',
                            'label' => 'Channel',
                            'default' => '0',
                        ],
                        's_tvg_logo' => [
                            'type' => 'image',
                            'class' => 'uk-width-1-2',
                            'label' => 'Channel Logo',
                        ],
                        's_tvg_id' => [
                            'type' => 'text',
                            'class' => 'uk-width-1-2',
                            'label' => 'TVG ID',
                        ],
                        's_tvg_group' => [
                            'type' => 'text',
                            'class' => 'uk-width-1-2',
                            'label' => 'TVG Group',
                        ],
                        's_extras' => [
                            'type' => 'text',
                            'class' => 'uk-width-1-1',
                            'label' => 'Attributes',
                        ],
                    ]
                ],
                'missing' => [
                    'bulk' => [
                        'replacedelete' => [
                            'label' => 'Delete Streams<br />(also deletes the master stream)',
                            'icon' => 'trash',
                            'confirm' => 'Are you sure you want to delete these streams?',
                            'callback' => function ($selectedIds, $db, $tableName) {
                                // make sure we have records selected
                                if (empty($selectedIds)) return false;

                                // setup the placeholders and the query
                                $placeholders = implode(',', array_fill(0, count($selectedIds), '?'));
                                $sql = "SELECT stream_id FROM {$tableName} WHERE id IN ({$placeholders})";

                                // get the records
                                $rs = $db->query($sql)
                                    ->bind($selectedIds)
                                    ->fetch();

                                // loop the records
                                foreach ($rs as $rec) {
                                    if ($rec->stream_id > 0) {
                                        $db->query("DELETE FROM `kptv_streams` WHERE `id` = ?")
                                            ->bind($rec->stream_id)
                                            ->execute();
                                    }
                                }

                                // return the execution
                                return $db->query("DELETE FROM {$tableName} WHERE id IN ({$placeholders})")
                                    ->bind($selectedIds)
                                    ->execute() !== false;
                            },
                            'success_message' => 'Records deleted',
                            'error_message' => 'Failed to delete the records'
                        ],
                        'clearmissing' => [
                            'label' => 'Clear Missing Streams<br />(only removes them from here)',
                            'icon' => 'ban',
                            'confirm' => 'Are you sure you want to delete these streams?',
                            'callback' => function ($selectedIds, $db, $tableName) {
                                // make sure we have records selected
                                if (empty($selectedIds)) return false;

                                // setup the placeholders and the query
                                $placeholders = implode(',', array_fill(0, count($selectedIds), '?'));

                                // return the execution
                                return $db->query("DELETE FROM {$tableName} WHERE id IN ({$placeholders})")
                                    ->bind($selectedIds)
                                    ->execute() !== false;
                            },
                            'success_message' => 'Records deleted',
                            'error_message' => 'Failed to delete the records'
                        ],
                    ],
                    'row' => [
                        [
                            'playstream' => [
                                'icon' => 'play',
                                'title' => 'Try to Play Stream',
                                'class' => 'play-stream',
                                'href' => '#{TheOrigName}',
                                'attributes' => [
                                    'data-stream-url' => '{TheStream}',
                                    'data-stream-name' => '{TheOrigName}',
                                ]
                            ],
                            'copystream' => [
                                'icon' => 'link',
                                'title' => 'Copy Stream Link',
                                'class' => 'copy-link',
                                'href' => '{TheStream}',
                            ]
                        ],
                        [
                            'deletemissing' => [
                                'icon' => 'trash',
                                'title' => 'Delete the Stream<br />(also deletes the master)',
                                'confirm' => 'Are you want to remove this stream?',
                                'callback' => function ($rowId, $rowData, $db, $tableName) {
                                    // make sure we have a row ID
                                    if (empty($rowId)) return false;

                                    // its a stream id
                                    if ($rowData["m.stream_id"] > 0) {
                                        $db->query("DELETE FROM `kptv_streams` WHERE `id` = ?")
                                            ->bind($rowData["m.stream_id"])
                                            ->execute();
                                    }

                                    // delete the missing record
                                    return $db->query("DELETE FROM `kptv_stream_missing` WHERE `id` = ?")
                                        ->bind($rowId)
                                        ->execute() !== false;
                                },
                                'success_message' => 'The stream has been deleted.',
                                'error_message' => 'Failed to delete the stream.',
                            ],
                            'clearmissing' => [
                                'icon' => 'ban',
                                'title' => 'Clear the Stream<br />(only deletes it from here)',
                                'confirm' => 'Are you want to remove this stream?',
                                'callback' => function ($rowId, $rowData, $db, $tableName) {
                                    // make sure we have a row ID
                                    if (empty($rowId)) return false;

                                    // delete the missing record
                                    return $db->query("DELETE FROM `kptv_stream_missing` WHERE `id` = ?")
                                        ->bind($rowId)
                                        ->execute() !== false;
                                },
                                'success_message' => 'The stream has been deleted.',
                                'error_message' => 'Failed to delete the stream.',
                            ],
                        ],
                    ],
                    'form' => []
                ],
                default => []
            };
        }

        /** 
         * days_in_between
         * 
         * Populate a message with our redirect
         * 
         * @since 8.4
         * @access public
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @param string $_date1 The first date
         * @param string $_date2 The second date
         * 
         * @return int This method returns the number of days between 2 dates
         * 
         */
        public static function days_in_between($_date1, $_date2): int
        {

            //return the difference between the 2 dates
            return date_diff(date_create($_date1), date_create($_date2))->format('%a');
        }


        public static function active_link(string $which): string
        {

            $route = \KPT\Router::getCurrentRoute();
            $route_path = $route->path;

            // hold the routes to match
            $routes = [
                'home' => ['/'],
                'info' => ['/users/faq', '/streams/faq', '/terms-of-use'],
                'admin' => ['/admin/users'],
                'account' => ['/users/changepass', '/users/login', '/users/register', '/users/forgot'],
                'streams' => [
                    '/streams/live/all',
                    '/streams/live/active',
                    '/streams/live/inactive',
                    '/streams/series/all',
                    '/streams/series/active',
                    '/streams/series/inactive',
                    '/streams/vod/all',
                    '/streams/vod/active',
                    '/streams/vod/inactive',
                ],
            ];

            // return the active class on the match
            return isset($routes[$which]) && in_array($route_path, $routes[$which], true)
                ? 'uk-active'
                : '';
        }


        public static function open_link(string $which): string
        {

            $route = \KPT\Router::getCurrentRoute();
            $route_path = $route->path;

            // hold the routes to match
            $routes = [
                'info' => ['/users/faq', '/streams/faq', '/terms-of-use'],

                'account' => ['/users/changepass', '/users/login', '/users/register', '/users/forgot'],
                'live' => ['/streams/live/all', '/streams/live/active', '/streams/live/inactive',],
                'series' => ['/streams/series/all', '/streams/series/active', '/streams/series/inactive',],
                'vod' => ['/streams/vod/all', '/streams/vod/active', '/streams/vod/inactive',],
            ];

            // return the active class on the match
            return isset($routes[$which]) && in_array($route_path, $routes[$which])
                ? 'uk-open'
                : '';
        }


        public static function get_counts(): array
        {

            // get the users ID
            $user_id = KPTV_User::get_current_user()->id;

            // stream count sql
            $stream_ct_qry = "SELECT 
                COUNT(id) as total_streams,
                SUM(CASE WHEN s_active = 1 AND s_type_id = 0 THEN 1 ELSE 0 END) as active_live,
                SUM(CASE WHEN s_active = 1 AND s_type_id = 5 THEN 1 ELSE 0 END) as active_series,
                SUM(CASE WHEN s_active = 1 AND s_type_id = 4 THEN 1 ELSE 0 END) as active_vod
            FROM kptv_streams
            WHERE u_id = ?";

            // provider count sql
            $provider_ct_qry = "SELECT 
                sp.sp_name as provider_name,
                COUNT(s.id) as total_streams,
                SUM(CASE WHEN s.s_active = 1 AND s.s_type_id = 0 THEN 1 ELSE 0 END) as active_live,
                SUM(CASE WHEN s.s_active = 1 AND s.s_type_id = 5 THEN 1 ELSE 0 END) as active_series,
                SUM(CASE WHEN s.s_active = 1 AND s.s_type_id = 4 THEN 1 ELSE 0 END) as active_vod
            FROM kptv_stream_providers sp
            LEFT JOIN kptv_streams s ON sp.id = s.p_id AND s.u_id = ?
            WHERE sp.u_id = ?
            GROUP BY sp.id, sp.sp_name
            ORDER BY sp.sp_priority, sp.sp_name;";

            // fire up the database class
            $db = new \KPT\Database(self::get_setting('database'));

            // run the queries
            $strm_ct = $db->query($stream_ct_qry)
                ->bind([$user_id])
                ->single()
                ->fetch();
            $prov_ct = $db->query($provider_ct_qry)
                ->bind([$user_id, $user_id])
                ->fetch();

            // setup the return data
            $ret = [
                'total' => $strm_ct->total_streams,
                'live' => $strm_ct->active_live,
                'series' => $strm_ct->active_series,
                'vod' => $strm_ct->active_vod,
                'per_provider' => $prov_ct,
            ];

            // clean up
            unset($prov_ct, $strm_ct, $db);

            // return
            return $ret;
        }

        public static function time_ago(string $datetime): string
        {
            $time = strtotime($datetime);
            $diff = time() - $time;

            if ($diff < 60) {
                return $diff . ' Seconds Ago';
            }

            if ($diff < 3600) {
                return floor($diff / 60) . ' Minutes Ago';
            }

            if ($diff < 86400) {
                return floor($diff / 3600) . ' Hours Ago';
            }

            if ($diff < 604800) {
                return floor($diff / 86400) . ' Days Ago';
            }

            // Beyond a week, show the date
            return date('M j', $time);
        }

        /** 
         * manage_the_session
         * 
         * Attempt to manage our session
         * 
         * @since 8.3
         * @access public
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @return void This method returns nothing
         * 
         */
        public static function manage_the_session(): void
        {

            // check if the session has been started
            if (session_status() !== PHP_SESSION_ACTIVE) {
                session_start();
            }

            // Force session write and close to prevent locks
            register_shutdown_function(function () {
                if (session_status() === PHP_SESSION_ACTIVE) {
                    session_write_close();
                }
            });
        }

        /** 
         * selected
         * 
         * Output "selected" for a drop-down
         * 
         * @since 8.3
         * @access public
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @param mixed $current The current item
         * @param mixed $expected The expected item
         * 
         * @return string Returns the string "selected" or empty
         * 
         */
        public static function selected($current, $expected): string
        {

            // if they are equal, return selected
            return $current == $expected ? 'selected' : '';
        }

        /** 
         * message_with_redirect
         * 
         * Populate a message with our redirect
         * 
         * @since 8.4
         * @access public
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @param string $_location The page we want to try to redirect to
         * @param string $_msg_type The type os message we should be showing
         * @param string $_msg The message content
         * 
         * @return void This method returns nothing
         * 
         */
        public static function message_with_redirect(string $_location, string $_msg_type, string $_msg): void
        {

            // setup the message
            $_SESSION['page_msg']['type'] = $_msg_type;
            $_SESSION['page_msg']['msg'] = sprintf('<p>%s</p>', $_msg);

            // redirect
            KPTV::try_redirect($_location);
        }

        /** 
         * try_redirect
         * 
         * Try to redirect
         * 
         * @since 8.4
         * @access public
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @param string $_location The page we want to try to redirect to
         * @param int $_status The HTTP status code used for the redirection: default 301 permanent
         * 
         * @return void This method returns nothing
         * 
         */
        public static function try_redirect(string $location, int $status = 301): void
        {

            // setup an error handler to handle the possible PHP warning you could get for modifying headers after output
            set_error_handler(function ($errno, $errstr, $errfile, $errline) {

                // make sure it throws an exception here
                throw new \ErrorException($errstr, 0, $errno, $errfile, $errline);
            }, E_WARNING);

            // now we can setup a trap to catch the warning
            try {

                // try to redirect
                header("Location: $location", true, $status);

                // caught it!
            } catch (\ErrorException $e) {

                // escape the location for output
                $escapedLocation = json_encode($location, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);

                // use javascript to do the redirect instead, as a fallback
                echo '<script type="text/javascript">setTimeout( function( ) { window.location.href="' . $escapedLocation . '"; }, 100 );</script>';
            }

            // return the default error handler
            restore_error_handler();

            // now we need to kill anything extra after we do all of this
            exit;
        }

        /** 
         * get_image_path
         * 
         * Static method for formatting the path to the image we need
         * 
         * @since 8.4
         * @access public
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @param string $_name The name of the image
         * @param string $_which Which image size do we need
         * 
         * @return string Returns the formatted path to the image
         * 
         */
        public static function get_image_path(string $_name, string $_which = 'header'): string
        {

            // return the path to the image
            return sprintf("/assets/images/%s-%s.jpg", $_name, $_which);
        }

        /** 
         * get_full_config
         * 
         * Get our full app config
         * 
         * @since 8.4
         * @access public
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @return object This method returns a standard class object of our applications configuration
         * 
         */
        public static function get_full_config(): object
        {

            static $config = null;

            if ($config !== null) {
                return $config;
            }

            // Use OPcache if available
            $configPath = KPTV_PATH . 'assets/config.json';

            if (
                function_exists('opcache_is_script_cached') &&
                opcache_is_script_cached($configPath)
            ) {
                $content = file_get_contents($configPath);
            } else {
                $content = file_get_contents($configPath);
                if (function_exists('opcache_compile_file')) {
                    opcache_compile_file($configPath);
                }
            }

            $config = json_decode($content);

            if (!$config) {
                $config = new \stdClass();
            }

            return $config;
        }

        /** 
         * get_setting
         * 
         * Get a single setting value from our config object
         * 
         * @since 8.4
         * @access public
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @return mixed This method returns a variable value of the setting requested
         * 
         */
        public static function get_setting(string $_name)
        {

            // get all our options
            $_all_opts = self::get_full_config();

            // get the single option based on the shortname passed
            if (isset($_all_opts->{$_name})) {

                // return the property
                return $_all_opts->{$_name};
            }

            // default to returning null
            return null;
        }

        /** 
         * get_ordinal
         * 
         * Static method for formatting a number as an ordinal number string
         * ie: 1st, 32nd, 100th, etc...
         * 
         * @since 8.4
         * @access public
         * @static
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @param int $_num The number to be formatted
         * 
         * @return string Returns the ordinal formatted number string
         * 
         */
        public static function get_oridinal(int $_num): ?string
        {

            // return the ordinal formatted number
            return $_num . substr(date('jS', mktime(0, 0, 0, 1, ($_num % 10 == 0 ? 9 : ($_num % 100 > 20 ? $_num % 10 : $_num % 100)), 2000)), -2);
        }

        /** 
         * find_in_array
         * 
         * Static method for determining if a string is in an array
         * search is case-insensitive
         * 
         * @since 8.4
         * @access public
         * @static
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @param string $_needle The string to find
         * @param array $_haystack The array to search
         * 
         * @return bool Returns if the string was found or not
         * 
         */
        public static function find_in_array(string $_needle, array $_haystack): bool
        {

            // see if our item is in any haystack
            return array_any(
                $_haystack,
                fn($_item) => stripos($_item, $_needle) !== false
            );
        }

        /** 
         * multidim_array_sort
         * 
         * Static method for sorting a multi-dimensional array
         * 
         * @since 8.4
         * @access public
         * @static
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @param array &$_array ByRef array to be sorted
         * @param string $_subkey String to sort the array by
         * @param bool $_sort_asc Boolean to determine the sort order
         * 
         */
        public static function multidim_array_sort(array &$_array, string $_subkey = "id", bool $_sort_asc = false)
        {

            // make sure there is at least 1 item
            if (count($_array))
                $temp_array[key($_array)] = array_shift($_array);

            // loop the array
            foreach ($_array as $key => $val) {

                // hold the offset
                $offset = 0;

                // hold the "found"
                $found = false;

                // loop over the inner keys
                foreach ($temp_array as $tmp_key => $tmp_val) {

                    // if found and the orignating key equals the found key
                    if (! $found and strtolower($val[$_subkey]) > strtolower($tmp_val[$_subkey])) {

                        // merge the arrays
                        $temp_array = array_merge((array) array_slice($temp_array, 0, $offset), array($key => $val), array_slice($temp_array, $offset));

                        // return true
                        $found = true;
                    }

                    // increment the offset
                    $offset++;
                }

                // if not found, merge
                if (! $found) $temp_array = array_merge($temp_array, array($key => $val));
            }

            // if asc, reverse the sort
            if ($_sort_asc) $_array = array_reverse($temp_array);

            // otherwise we're good to go
            else $_array = $temp_array;
        }

        /** 
         * object_to_array
         * 
         * Static method for converting an object to an array
         * 
         * @since 8.4
         * @access public
         * @static
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @param object $_val The object to be converted
         * 
         * @return array Returns the converted array
         * 
         */
        public static function object_to_array(object $_val): array
        {

            // hold the returnable array
            $result = array();

            // if there is an object to be converted
            if ($_val && is_object($_val)) {

                // loop over the object properties
                foreach ($_val as $key => $value) {

                    // if the value is an array or object, convert it
                    $result[$key] = (is_array($value) || is_object($value)) ? self::object_to_array($value) : $value;
                }
            }

            // return the converted array
            return $result;
        }

        /** 
         * encrypt
         * 
         * Static method for encrypting a string utilizing openssl libraries
         * if openssl is not found, will simply base64_encode the string
         * 
         * @since 8.4
         * @access public
         * @static
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @param string $_val The string to be encrypted
         * 
         * @return string Returns the encrypted or encoded string
         * 
         */
        public static function encrypt(string $_val): string
        {

            // hold our return
            $_ret = '';

            // compress our value
            $_val = gzcompress($_val);

            // make sure the openssl library exists
            if (! function_exists('openssl_encrypt')) {

                // it does not, so all we can really do is base64encode the string
                $_ret = base64_encode($_val);

                // otherwise
            } else {

                // the encryption method
                $_enc_method = "AES-256-CBC";

                // generate a key based on the _key
                $_the_key = hash('sha256', self::get_setting('mainkey'));

                // generate an initialization vector based on the _secret
                $_iv = substr(hash('sha256', self::get_setting('mainsecret')), 0, 16);

                // return the base64 encoded version of our encrypted string
                $_ret = base64_encode(openssl_encrypt($_val, $_enc_method, $_the_key, 0, $_iv));
            }

            // return our string
            return $_ret;
        }

        /** 
         * decrypt
         * 
         * Static method for decryption a string utilizing openssl libraries
         * if openssl is not found, will simply base64_decode the string
         * 
         * @since 8.4
         * @access public
         * @static
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @param string $_val The string to be encrypted
         * 
         * @return string Returns the decrypted or decoded string
         * 
         */
        public static function decrypt(string $_val): string
        {

            // hold our return
            $_ret = '';

            // make sure the openssl library exists
            if (! function_exists('openssl_decrypt')) {

                // it does not, so all we can really do is base64decode the string
                $_ret = base64_decode($_val);

                // otherwise
            } else {

                // the encryption method
                $_enc_method = "AES-256-CBC";

                // generate a key based on the _key
                $_the_key = hash('sha256', self::get_setting('mainkey'));

                // generate an initialization vector based on the _secret
                $_iv = substr(hash('sha256', self::get_setting('mainsecret')), 0, 16);

                // return the decrypted string
                $_ret = openssl_decrypt(base64_decode($_val), $_enc_method, $_the_key, 0, $_iv);
            }

            // return our string
            return ($_ret) ? gzuncompress($_ret) : '';
        }

        /** 
         * generate_password
         * 
         * Generates a random "password" string
         * 
         * @since 8.4
         * @access public
         * @static
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @param int $_min_length The minimum lenght string to generate. Default 32
         * 
         * @return string The randomly generated string
         * 
         */
        public static function generate_password(int $_min_length = 32): string
        {

            // hold the character set
            $alphabet = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890!@#$%*';

            // setup the randomizer
            $randomizer = new \Random\Randomizer(new \Random\Engine\Secure());

            // hold the length of the character set
            $length = $randomizer->getInt($_min_length, 64);

            // return the random string
            return $randomizer->getBytesFromString($alphabet, $length);
        }

        /** 
         * generate_rand_string
         * 
         * Generates a random "password" string
         * 
         * @since 8.4
         * @access public
         * @static
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @param int $_min_length The minimum lenght string to generate. Default 8
         * 
         * @return string The randomly generated string
         * 
         */
        public static function generate_rand_string(int $_min_length = 8): string
        {

            // hold the character set
            $alphabet = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890';

            // setup the randomizer
            $randomizer = new \Random\Randomizer(new \Random\Engine\Secure());

            // hold the length of the character set
            $length = $randomizer->getInt($_min_length, 64);

            // return the random string
            return $randomizer->getBytesFromString($alphabet, $length);
        }

        /** 
         * get_user_uri
         * 
         * Gets the current users URI that was attempted
         * 
         * @since 8.4
         * @access public
         * @static
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @return string Returns a string containing the URI
         * 
         */
        public static function get_user_uri(): string
        {

            // return the current URL
            return filter_var((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'], FILTER_SANITIZE_URL);
        }

        /** 
         * get_user_ip
         * 
         * Gets the current users public IP address
         * 
         * @since 8.4
         * @access public
         * @static
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @return string Returns a string containing the users public IP address
         * 
         */
        public static function get_user_ip(): string
        {

            // check if we've got a client ip header, and if it's valid
            if (isset($_SERVER['HTTP_CLIENT_IP']) && filter_var($_SERVER['HTTP_CLIENT_IP'], FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {

                // return it
                return filter_var($_SERVER['HTTP_CLIENT_IP'], FILTER_SANITIZE_URL);

                // maybe they're proxying?
            } elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR']) && filter_var($_SERVER['HTTP_X_FORWARDED_FOR'], FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {

                // return it
                return filter_var($_SERVER['HTTP_X_FORWARDED_FOR'], FILTER_SANITIZE_URL);

                // if all else fails, this should exist!
            } elseif (isset($_SERVER['REMOTE_ADDR']) && filter_var($_SERVER['REMOTE_ADDR'], FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {

                // return it
                return filter_var($_SERVER['REMOTE_ADDR'], FILTER_SANITIZE_URL);
            }

            // default return
            return '';
        }

        /** 
         * cidrMatch
         * 
         * match a possible cidr address
         * 
         * @since 8.4
         * @access public
         * @static
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @return string Returns a string containing the users public IP address
         * 
         */
        public static function cidrMatch($ip, $cidr)
        {

            // Simple IP comparison if no CIDR mask
            if (strpos($cidr, '/') === false) {
                return $ip === $cidr;
            }

            // CIDR range comparison
            list($subnet, $mask) = explode('/', $cidr);
            $ipLong = ip2long($ip);
            $subnetLong = ip2long($subnet);
            $maskLong = ~((1 << (32 - $mask)) - 1);

            // return if it's in range or not
            return ($ipLong & $maskLong) === ($subnetLong & $maskLong);
        }

        /** 
         * get_user_agent
         * 
         * Gets the current users browsers User Agent
         * 
         * @since 8.4
         * @access public
         * @static
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @return string Returns a string containing the users browsers User Agent
         * 
         */
        public static function get_user_agent(): string
        {

            // possible browser info
            $_browser = @get_browser();

            // let's see if the user agent header exists
            if (isset($_SERVER['HTTP_USER_AGENT'])) {

                // return the user agent
                return htmlspecialchars($_SERVER['HTTP_USER_AGENT']);

                // let's see if we have browser data
            } elseif ($_browser) {

                // return the browser name pattern
                return htmlspecialchars($_browser->browser_name_pattern);
            }

            // default return
            return '';
        }

        /** 
         * get_user_referer
         * 
         * Gets the current users referer
         * 
         * @since 8.4
         * @access public
         * @static
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @return string Returns a string containing the users referer
         * 
         */
        public static function get_user_referer(): string
        {

            // return the referer if it exists
            return isset($_SERVER['HTTP_REFERER']) ? filter_var($_SERVER['HTTP_REFERER'], FILTER_SANITIZE_URL) : '';
        }

        /** 
         * str_contains_any
         * 
         * Does a string contain any other string
         * 
         * @since 8.4
         * @access public
         * @static
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @param string $_to_search The string we're searching
         * @param array $_searching The string we're searching for
         * 
         * @return bool This method returns true or false
         * 
         */
        public static function str_contains_any(string $_to_search, array $_searching): bool
        {

            // filter down the string
            return array_any(
                $_searching,
                fn($n) => str_contains(strtolower($_to_search), strtolower($n))
            );
        }

        /** 
         * str_contains_any_re
         * 
         * Does a string contain any other string searching via regex
         * 
         * @since 8.4
         * @access public
         * @static
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @param string $_to_search The string we're searching
         * @param array $_searching The string we're searching for
         * 
         * @return bool This method returns truw or false
         * 
         */
        public static function str_contains_any_re(string $_to_search, array $_searching): bool
        {

            // return if it was found
            return array_any(
                $_searching,
                fn($_i) => (bool) preg_match('~' . $_i . '~i', $_to_search)
            );
        }

        /** 
         * send_email
         * 
         * Send an email through SMTP
         * 
         * @since 8.4
         * @access public
         * @static
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @param array $_to Who is the email going to: email, name?
         * @param string $_subj What is the emails subject
         * @param string $_msg What is the emails message
         * 
         * @return bool Returns success or not
         * 
         */
        public static function send_email(array $_to, string $_subj, string $_msg): bool
        {

            //Create a new PHPMailer instance
            $mail = new \PHPMailer\PHPMailer\PHPMailer();

            //Tell PHPMailer to use SMTP
            $mail->isSMTP();

            // if we want to debug
            if (filter_var(self::get_setting('smtp')->debug, FILTER_VALIDATE_BOOLEAN)) {

                // set it to client and server debug
                $mail->SMTPDebug = \PHPMailer\PHPMailer\SMTP::DEBUG_SERVER;
            }

            //Set the hostname of the mail server
            $mail->Host = self::get_setting('smtp')->server;

            // setup the type of SMTP security we'll use
            if (self::get_setting('smtp')->security && 'tls' === self::get_setting('smtp')->security) {

                // set to TLS
                $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;

                // just default to SSL
            } else {
                $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
            }

            //Set the SMTP port number - likely to be 25, 465 or 587
            $mail->Port = (self::get_setting('smtp')->port) ?? 25;

            //Whether to use SMTP authentication
            $mail->SMTPAuth = true;

            //Username to use for SMTP authentication
            $mail->Username = self::get_setting('smtp')->username;

            //Password to use for SMTP authentication
            $mail->Password = self::get_setting('smtp')->password;

            //Set who the message is to be sent from
            $mail->setFrom(self::get_setting('smtp')->fromemail, self::get_setting('smtp')->fromname); // email, name

            //Set who the message is to be sent to
            $mail->addAddress($_to[0], $_to[1]);

            // set if the email s)hould be HTML or not
            $mail->isHTML(filter_var(self::get_setting('smtp')->forcehtml, FILTER_VALIDATE_BOOLEAN));

            //Set the subject line
            $mail->Subject = $_subj;

            // set the mail body
            $mail->Body = $_msg;

            //send the message, check for errors
            if (! $mail->send()) {
                var_dump($mail->ErrorInfo);
                return false;
            } else {
                return true;
            }
        }

        /** 
         * mask_email_address
         * 
         * Mask an email address from bots
         * 
         * @since 8.4
         * @access public
         * @static
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @param string $_value The email address to mask
         * 
         * @return string The masked email address
         * 
         */
        public static function mask_email_address(string $_value): string
        {

            // hold the returnable string
            $_ret = '';

            // get the string length
            $_sl = strlen($_value);

            // loop over the string
            for ($_i = 0; $_i < $_sl; $_i++) {

                // apppend the ascii val to the returnable string
                $_ret .= '&#' . ord($_value[$_i]) . ';';
            }

            // return it
            return $_ret;
        }

        /** 
         * show_message
         * 
         * Show a UIKit based message
         * 
         * @since 8.4
         * @access public
         * @static
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @param string $_type The type of message we need to show
         * @param string $_msg The message to show
         * 
         * @return void This method returns nothing
         * 
         */
        public static function show_message(string $_type, string $_msg): void
        {

            // build out our HTML for the alerts
?>
            <div class="dark-version uk-alert uk-alert-<?php echo $_type; ?> uk-padding-small">
                <?php
                // show the icon and message based on the type
                echo match ($_type) {
                    'success' => '<span uk-icon="icon: check"></span> Yahoo!',
                    'warning' => '<span uk-icon="icon: question"></span> Hmm...',
                    'danger' => '<span uk-icon="icon: warning"></span> Uh Ohhh!',
                    'info' => '<span uk-icon="icon: info"></span> Heads Up',
                    default => '',
                };
                ?>
                <?php echo $_msg; ?>
            </div>

<?php
        }

        /** 
         * bool_to_icon
         * 
         * Just converts a boolean value to a UIKit icon
         * 
         * @since 8.4
         * @access public
         * @static
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @param bool $_val The value to convert, default false
         * 
         * @return string Returns the inline icon
         * 
         */
        public static function bool_to_icon(bool $_val = false): string
        {

            // if the value is true
            if ($_val) {

                // return a check mark icon
                return '<span uk-icon="check"></span>';
            }

            // return an X icon
            return '<span uk-icon="close"></span>';
        }

        /** 
         * array_key_contains_Subset
         * 
         * Checks if any key in an array contains a given subset string and returns the array item if found.
         * 
         * @since 8.4
         * @access public
         * @static
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @param array $array The array to search through.
         * @param string $subset The subset string to look for in the keys.
         * @param bool $caseSensitive Whether the search should be case-sensitive. Default is true.
         * 
         * @return array|bool Returns an array if the passed array contains the subset in any key, otherwise returns false
         * 
         */
        public static function array_key_contains_Subset(array $array, string $subset, bool $caseSensitive = true): array|bool
        {

            // Return false immediately if the array is empty
            if (empty($array)) {
                return false;
            }

            // Use PHP 8.4's array_find_key to locate the first key containing the subset
            // The callback receives both value and key, allowing us to search by key
            $matchingKey = array_find_key(
                $array,
                fn($value, $key) => $caseSensitive
                    // Case-sensitive: use str_contains for exact substring match
                    ? str_contains((string) $key, $subset)
                    // Case-insensitive: use stripos which ignores case
                    : stripos((string) $key, $subset) !== false
            );

            // If a matching key was found, return its associated value
            // Otherwise return false to indicate no match
            return $matchingKey !== null ? $array[$matchingKey] : false;
        }

        /** 
         * format_bytes
         * 
         * Static method for creating a human readable string from the number of bytes
         * 
         * @since 8.4
         * @access public
         * @static
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @return string Human readable string for the bytes
         * 
         **/
        public static function format_bytes(int $size, int $precision = 2): string
        {

            // if the size is empty
            if ($size <= 0) return '0 B';

            // base size for the calculation
            $base = log($size, 1024);
            $suffixes = ['B', 'KB', 'MB', 'GB', 'TB'];

            // return the value
            return round(pow(1024, $base - floor($base)), $precision) . ' ' . $suffixes[floor($base)];
        }

        /** 
         * get_cache_prefix
         * 
         * Static method for creating a normalized global cache prefix
         * 
         * @since 8.4
         * @access public
         * @static
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @return string A formatted cache key based on the uri the user browsed
         * 
         **/
        public static function get_cache_prefix(): string
        {

            // set the uri
            $uri = self::get_user_uri();

            // Remove protocol and www prefix
            $clean_uri = preg_replace('/^(https?:\/\/)?(www\.)?/', '', $uri);

            // Remove trailing slashes and paths
            $clean_uri = preg_replace('/\/.*$/', '', $clean_uri);

            // replace non-alphanumeric with underscores
            $clean_uri = preg_replace('/[^a-zA-Z0-9]/', '_', $clean_uri);

            // Remove consecutive underscores
            $clean_uri = preg_replace('/_+/', '_', $clean_uri);

            // Trim underscores from ends
            $clean_uri = trim($clean_uri, '_');

            // Ensure it starts with a letter (some cache backends require this)
            if (! preg_match('/^[A-Za-z]/', $clean_uri)) {
                $clean_uri = 'S_' . $clean_uri;
            }

            // Limit length for cache key compatibility
            $clean_uri = substr($clean_uri, 0, 20);

            // Always end with colon separator
            return $clean_uri . ':';
        }

        /** 
         * get_redirect_url
         * 
         * Static method for getting the redirect url for crud actions
         * 
         * @since 8.4
         * @access public
         * @static
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @return string The full URL
         * 
         **/
        public static function get_redirect_url(): string
        {

            // parse out the querystring
            $query_string = parse_url(self::get_user_uri(), PHP_URL_QUERY) ?? '';

            // parse out the actual URL including the path browsed
            $url = parse_url(self::get_user_uri(), PHP_URL_PATH) ?? '/';

            // return the formatted string
            return sprintf('%s?%s', $url, $query_string);
        }

        /**
         * Includes a view file with passed data
         * 
         * @param string $view_name Name of the view file (without extension)
         * @param array $data Associative array of data to pass to the view
         */
        public static function include_view(string $view_name, array $data = []): void
        {

            // Extract data to variables
            extract($data);

            // Include the view file
            include KPTV_PATH . "/views/{$view_name}.php";
        }

        /** 
         * pull_header
         * 
         * Static method for pulling the sites header
         * 
         * @since 8.4
         * @access public
         * @static
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @param array $data Associative array of data to pass to the view
         * 
         * @return void Returns nothing
         * 
         */
        public static function pull_header(array $data = []): void
        {

            // include the header and pass data if any
            self::include_view('wrapper/header', $data);
        }

        /** 
         * pull_footer
         * 
         * Static method for pulling the sites footer
         * 
         * @since 8.4
         * @access public
         * @static
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @param array $data Associative array of data to pass to the view
         * 
         * @return void Returns nothing
         * 
         */
        public static function pull_footer(array $data = [])
        {

            // include the header and pass data if any
            self::include_view('wrapper/footer', $data);
        }


        public static function moveToType($db, int $id, int $type = 99): bool
        {

            // Use transaction for multiple operations
            $db->transaction();
            try {

                // figure out what we're moving
                $result = match ($type) {
                    // live
                    0 => $db
                        ->query('UPDATE `kptv_streams` SET `s_type_id` = 0 WHERE `id` = ?')
                        ->bind([$id])  // Fixed: was using $which instead of $type
                        ->execute(),
                    // series
                    5 => $db
                        ->query('UPDATE `kptv_streams` SET `s_type_id` = 5 WHERE `id` = ?')
                        ->bind([$id])  // Fixed: was using $which instead of $type
                        ->execute(),
                    // vod
                    4 => $db
                        ->query('UPDATE `kptv_streams` SET `s_type_id` = 4 WHERE `id` = ?')
                        ->bind([$id])  // Fixed: was using $which instead of $type
                        ->execute(),
                    // other
                    default => $db
                        ->query('UPDATE `kptv_streams` SET `s_type_id` = 99 WHERE `id` = ?')
                        ->bind([$id])
                        ->execute(),
                };

                // Check if operation failed
                if ($result === false) {
                    $db->rollback();
                    return false;
                }

                // Commit if all successful
                $db->commit();
                return true;
            } catch (\Exception $e) {
                // Rollback on error
                $db->rollback();
                return false;
            }
        }

        public static function getProviders(int $userId): array
        {

            // setup the return
            $ret = [];

            $dbconf = (object) [
                'server' => DB_SERVER,
                'schema' => DB_SCHEMA,
                'username' => DB_USER,
                'password' => DB_PASS,
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
            ];

            // fire up the database class
            $db = new \KPT\Database($dbconf);

            // setup the recordset
            $rs = $db->query("SELECT id, sp_name FROM kptv_stream_providers WHERE u_id = ?")
                ->bind([$userId])
                ->asArray()
                ->fetch();

            // loop the array, if it is an array
            if (is_array($rs)) {
                foreach ($rs as $rec) {
                    // set the array items
                    $ret[$rec['id']] = $rec['sp_name'];
                }
            }

            // return them
            return $ret;
        }

        /**---------------------------------------------------------------------------------------------------- */
        /**---------------------------------------------------------------------------------------------------- */
        /**---------------------------------------------------------------------------------------------------- */
        /**---------------------------------------------------------------------------------------------------- */
        /**---------------------------------------------------------------------------------------------------- */
        /**---------------------------------------------------------------------------------------------------- */
        /** 
         * sanitize_string
         * 
         * Static method for sanitizing a string
         * 
         * @since 8.4
         * @access public
         * @static
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @param string $_val String to sanitize
         * 
         * @return string Returns a sanitized string
         * 
         */
        public static function sanitize_string(string $_val): string
        {

            // return the sanitized string, or empty
            return addslashes(mb_trim($_val));
        }

        /** 
         * sanitize_numeric
         * 
         * Static method for sanitizing a number
         * 
         * @since 8.4
         * @access public
         * @static
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @param var $_val Number to sanitize
         * 
         * @return var Returns a sanitized number
         * 
         */
        public static function sanitize_numeric($_val)
        {

            // return the sanitized string, or 0
            return filter_var($_val, FILTER_SANITIZE_NUMBER_FLOAT);
        }

        /** 
         * sanitize_the_email
         * 
         * Static method for sanitizing an email address
         * 
         * @since 8.4
         * @access public
         * @static 
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @param string $_val Email address to sanitize
         * 
         * @return string Returns a sanitized email address
         * 
         */
        public static function sanitize_the_email(string $_val): string
        {

            // return the sanitized email, or empty
            return (empty($_val)) ? '' : filter_var($_val, FILTER_SANITIZE_EMAIL);
        }

        /** 
         * sanitize_url
         * 
         * Static method for sanitizing a url
         * 
         * @since 8.4
         * @access public
         * @static
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @param string $_val URL to sanitize
         * 
         * @return string Returns a sanitized URL
         * 
         */
        public static function sanitize_url(string $_val): string
        {

            // return the sanitized url, or empty
            return (empty($_val)) ? '' : filter_var($_val, FILTER_SANITIZE_URL);
        }

        /** 
         * sanitize_css_js
         * 
         * Static method for sanitizing a CSS or JS
         * 
         * @since 8.4
         * @access public
         * @static
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @param string $_val CSS or JS to sanitize
         * 
         * @return string Returns sanitized CSS or JS
         * 
         */
        public static function sanitize_css_js(string $_val): string
        {

            // strip out script and style tags
            $string = preg_replace('@<(script|style)[^>]*?>.*?</\\1>@si', '', $_val);

            // strip out all other tage
            $string = strip_tags($string);

            // return the trimmed value
            return mb_trim($string);
        }

        /** 
         * sanitize_svg
         * 
         * Static method for sanitizing a svg's xml content
         * 
         * @since 8.4
         * @access public
         * @static
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @param string $_svg_xml SVG XML to sanitize
         * 
         * @return string Returns sanitized SVG XML
         * 
         */
        public static function sanitize_svg(string $_svg_xml): ?string
        {

            // if the string is empty
            if (empty($_svg_xml)) {

                // just return an empty string
                return '';
            }

            // return the clean xml
            return $_svg_xml;
        }

        /**
         * Sanitize path
         * 
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         * 
         * @param string|null $path Path to sanitize
         * @return string Sanitized path
         */
        public static function sanitize_path(?string $path): string
        {

            if (empty($path)) return '/';
            $path = parse_url($path, PHP_URL_PATH) ?? '';
            $path = preg_replace('#/+#', '/', $path); // Only normalize multiple slashes
            $path = mb_trim($path, '/');
            return $path === '' ? '/' : '/' . $path;
        }

        /** 
         * validate_string
         * 
         * Static method for validating a string
         * 
         * @since 8.4
         * @access public
         * @static
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @param string $_val String to validate
         * 
         * @return bool Returns a true/false if the input is a valid string
         * 
         */
        public static function validate_string(string $_val): bool
        {

            // check if the value is empty, then check if it's a string 
            return (empty($_val)) ? false : is_string($_val);
        }

        /** 
         * validate_number
         * 
         * Static method for validating a number
         * 
         * @since 8.4
         * @access public
         * @static
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @param var $_val Variable input to validate
         * 
         * @return bool Returns a true/false if the input is a valid number.
         * This includes float, decimal, integer, etc...
         * 
         */
        public static function validate_number($_val): bool
        {

            // check if the value is empty, then check if it's a number 
            return (empty($_val)) ? false : is_numeric($_val);
        }

        /** 
         * validate_alphanum
         * 
         * Static method for validating an alpha-numeric string
         * 
         * @since 8.4
         * @access public
         * @static
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @param string $_val String to validate
         * 
         * @return bool Returns a true/false if the input is a valid alpha-numeric string
         * 
         */
        public static function validate_alphanum(string $_val): bool
        {

            // check if the value is empty, then check if it's alpha numeric or space, _, -
            return (empty($_val)) ? false : preg_match('/^[\p{L}\p{N} ._-]+$/', $_val);
        }

        /** 
         * validate_username
         * 
         * Static method for validating an username string
         * 
         * @since 8.4
         * @access public
         * @static
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @param string $_val String to validate
         * 
         * @return bool Returns a true/false if the input is a valid username string
         * 
         */
        public static function validate_username(string $_val): bool
        {

            // check if the value is empty, then check if it has alpha numeric characters, _, or - in it 
            return (empty($_val)) ? false : preg_match('/^[\p{L}\p{N}._-]+$/', $_val);
        }

        /** 
         * validate_name
         * 
         * Static method for validating a name
         * 
         * @since 8.4
         * @access public
         * @static
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @param string $_val String to validate
         * 
         * @return bool Returns a true/false if the input is a valid name string
         * 
         */
        public static function validate_name(string $_value): bool
        {

            // validate the string
            if (! preg_match('/((^(?(?![^,]+?,)((.*?) )?(([A-Za-zà-üÀ-Ü\']*?) )?(([A-Za-zà-üÀ-Ü\']*?) )?)([A-ZÀ-Ü\']((\'|[a-z]{1,2})[A-ZÀ-Ü\'])?[a-zà-ü\']+))(?(?=,)(, ([A-Za-zà-üÀ-Ü\']*?))?( ([A-Za-zà-üÀ-Ü\']*?))?( ([A-Za-zà-üÀ-Ü\']*?))?)$)/', $_value)) {
                return false;
            }

            // otherwise, it all validates return true
            return true;
        }

        /** 
         * validate_email
         * 
         * Static method for validating an email address
         * 
         * @since 8.4
         * @access public
         * @static
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @param string $_val String email address to validate
         * 
         * @return bool Returns a true/false if the input is a valid string
         * 
         */
        public static function validate_email(string $_val): bool
        {

            // check if the value is empty, then check if it's an email address
            return (empty($_val)) ? false : filter_var($_val, FILTER_VALIDATE_EMAIL);
        }

        /** 
         * validate_url
         * 
         * Static method for validating a URL
         * 
         * @since 8.4
         * @access public
         * @static
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @param string $_val String URL to validate
         * 
         * @return bool Returns a true/false if the input is a valid URL
         * 
         */
        public static function validate_url(string $_val): bool
        {

            // check if the value is empty
            if (empty($_val)) {

                // it is, so return false
                return false;
            }

            // parse the URL
            $_url = parse_url($_val);

            // we need a scheme at the least
            if ($_url['scheme'] != 'http' && $_url['scheme'] != 'https') {

                // we don't have a scheme, return false
                return false;
            }

            // we have made it this far, return the domain validation
            return filter_var($_url['host'], FILTER_VALIDATE_DOMAIN);
        }

        /** 
         * validate_password
         * 
         * Static method for validating a password
         * 
         * @since 8.4
         * @access public
         * @static
         * @author Kevin Pirnie <me@kpirnie.com>
         * @package KP Library
         * 
         * @param string $_value String to validate
         * 
         * @return bool Returns a true/false if the input is a valid strong password
         *              Password Rules: 6-64 alphanumeric characters plus at least 1 !@#$%*
         * 
         */
        public static function validate_password(string $_value): bool
        {

            // validate the PW
            if (! preg_match('/(?=^.{8,64}$)(?=.[a-zA-Z\d])(?=.*[!@#$%*])(?!.*\s).*$/', $_value)) {
                return false;
            }

            // otherwise, it all validates return true
            return true;
        }
    }
}
