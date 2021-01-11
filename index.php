<?php

/**
 * Process $_POST data and return a SAQL statement with DST support
 */

$ret_data = array(
    'formula' => '',
    'errors' => array(),
    'post' => $_POST,
);

/**
 * If any of these are missing, the function will exit.
 */
$requireds = array(
    'start_year',
    'end_year',
    'epoch_variable_name',
    'epoch_converted_variable_name',
    'timezone_name',
    'timezone_region',
);

$vars = array(); // This will hold all variables, if found.

foreach ($requireds as $required) {
    if (!isset($_POST[$required])) {
        $ret_data['errors'][] = 'Missing variable: ' . str_replace('_', ' ', $required);
    } else {
        $vars[$required] = $_POST[$required];
    }
}

if (!empty($ret_data['errors'])) {
    echo json_encode($ret_data);
    die();
}

/**
 * Set user-friendlier names here
 */
$start_year = (int) $vars['start_year'];
$end_year = (int) $vars['end_year'];
$epoch_variable_name = $vars['epoch_variable_name'];
$epoch_converted_variable_name = $vars['epoch_converted_variable_name'];
$timezone_name = $vars['timezone_name'];
$timezone_region = $vars['timezone_region'];

/**
 * These are available to process as DST
 */
$timezone_dst_regions = array(
    'US' => 'North_America',
    'CA' => 'North_America',
);
$timezone_dst_supported = array(
    'North_America' => array(
        '2007' => array(
            'start' => 'Second Sunday of March',
            'end' => 'First Sunday of November',
        ),
        '1987' => array(
            'start' => 'First Sunday of April',
            'end' => 'Last Sunday of October',
        ),
        '1976' => array(
            'start' => 'Last Sunday of April',
            'end' => 'Last Sunday of October',
        ),
    ),
);

/**
 * Begin SAQL case statement output...
 */
$ret_data['formula'] .= 'case' . PHP_EOL;

/**
 * Set the timezone to the timezone we are currently processing.
 * The start/end of Daylight Savings Time is determined based on the LOCAL
 * time - ex. in 2008, DST in Pacific Time began at 2am on the 2nd Sunday of March.
 * 2am is 2am in the local Pacific Time - not 2am in GMT or UTC.
 */
date_default_timezone_set($timezone_name);

/**
 * Loop through each year in the range to generate the case statement output.
 */
for ($year = $start_year; $year <= $end_year; $year += 1) {

    /**
     * Default to 2007 and later DST settings for North_America
     */
    $epoch = array(
        'start' => strtotime('Second Sunday of March ' . $year),
        'end' => strtotime('First Sunday of November ' . $year),
    );

    if (
        isset($timezone_dst_regions[$timezone_region]) &&            // if the provided timezone region is available for DST processing per $timezone_dst_regions variable
        ($dst_area = $timezone_dst_regions[$timezone_region]) &&    // set the $dst_area variable to simplify writing the next two conditional checks
        isset($timezone_dst_supported[$dst_area]) &&                // if the provided timezone region has entries available to process, continue
        is_array($timezone_dst_supported[$dst_area])                // if the provided timezone region entries is an array, we're good.
    ) {
        /**
         * Loop through each entry in the array - an entry's index ($key) is a YEAR,
         * we're looping over them descending backwards.
         */
        foreach ($timezone_dst_supported[$dst_area] as $key => $val) {
            /**
             * If the current $year we're processing is greater/equal to a year available...
             */
            if ($year >= (int) $key) {
                /**
                 * Use strtotime to perform the magic needed to determine a specific day/time based on
                 * plain english like "Second Sunday of March"
                 */
                $epoch['start'] = strtotime($val['start'] . ' ' . $year);
                $epoch['end'] = strtotime($val['end'] . ' ' . $year);

                break; // We're all set, let's get out of here.
            }
        }
    }

    /**
     * Add 1 hour, 59 minutes, 59 seconds, to set the epoch as 1:59am in the local time
     * 
     * TODO: Ensure support for other DST areas, in case they do not begin DST at 2am
     */
    $epoch['start'] += 7199;
    $epoch['end'] += 7199;

    $ret_data['formula'] .= "\t" . 'when ' . $epoch_variable_name . ' > ' . $epoch['start'] . ' and '  . $epoch_variable_name . ' <= ' . $epoch['end'];
    $ret_data['formula'] .= ' then toDate(' . $epoch_converted_variable_name . ' + 3600)' . PHP_EOL;
}

$ret_data['formula'] .= "\t"; // Tabs for formatting

$ret_data['formula'] .= 'else toDate(' . $epoch_converted_variable_name . ')' . PHP_EOL;
$ret_data['formula'] .= 'end';

/**
 * Logging to see if anyone uses this thing...
 */
$log = json_encode($vars);
error_log($log);

echo json_encode($ret_data);

die();
