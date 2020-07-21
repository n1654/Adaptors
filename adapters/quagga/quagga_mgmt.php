<?php
/*
 * Available global variables
 *  $sms_sd_info       sd_info structure
 *  $sdid
 *  $sms_module        module name (for patterns)
 *  $sd_poll_elt       pointer on sd_poll_t structure
 *  $sd_poll_peer      pointer on sd_poll_t structure of the peer (slave of master)
 */

// Script description
require_once 'smsd/sms_common.php';
require_once "$db_objects";

require_once load_once('quagga', 'adaptor.php');

function exit_error($line, $error) {
    sms_log_error("$line: $error\n");
    sd_disconnect();
    exit($error);
}

try {
    // Connection
    sd_connect();

    $temp = sendexpectone(__FILE__ . ':' . __LINE__, $sms_sd_ctx, "sh version");

    $arr = preg_split("/\r\n|\n|\r/", $temp);

    $asset['serial'] = $arr[0];


    $ret = sms_polld_set_asset_in_sd($sd_poll_elt, $asset);
    if ($ret !== 0)
    {
      exit_error(__FILE__ . ':' . __LINE__, ": sms_polld_set_asset_in_sd($sms_sd_ctx, $asset) Failed\n");
    }

    sd_disconnect();
} catch (Exception | Error $e) {
    sd_disconnect();
    exit($e->getCode());
}

return 0;

?>
