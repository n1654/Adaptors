<?php
/*
 * Available global variables
 *  $sms_csp            pointer to csp context to send response to user
 *  $sms_sd_ctx         pointer to sd_ctx context to retrieve useful field(s)
 *  $sms_sd_info        pointer to sd_info structure
 *  $SMS_RETURN_BUF     string buffer containing the result
 */

// Device adaptor

require_once 'smserror/sms_error.php';
require_once 'smsd/sms_common.php';

require_once load_once('quagga', 'quagga_connect.php');
require_once load_once('quagga', 'quagga_apply_conf.php');

require_once "$db_objects";

/**
 * Connect to device
 * @param  $login
 * @param  $passwd
 * @param  $adminpasswd
 */
function sd_connect($login = null, $passwd = null) {
    return quagga_connect($login, $passwd);
}

/**
 * Disconnect from device
 * @param $clean_exit
 */
function sd_disconnect() {
    return  quagga_disconnect();
}

function sd_execute_command($cmd, $need_sd_connection = false) {
   global $sms_sd_ctx;

    if ($need_sd_connection) {
        $ret = sd_connect();
        if ($ret !== SMS_OK) {
            return false;
        }
    }

    $ret = sendexpectone(__FILE__.':'.__LINE__, $sms_sd_ctx, $cmd);

    if ($need_sd_connection) {
        sd_disconnect();
    }

   return $ret;
}

function sd_apply_conf($configuration, $need_sd_connection = false, $push_to_startup = false, $ts_ip = null, $ts_port = null) {
    if ($need_sd_connection) {
        $ret = sd_connect ( null, null, null, $ts_ip, $ts_port );
    }
    if ($ret != SMS_OK) {
        throw new SmsException ( "", ERR_SD_CMDTMOUT );
    }

    $ret = quagga_apply_conf ( $configuration, $push_to_startup );

    if (! empty ( $ts_ip )) {
        sd_save_conf ();
    }

    if ($need_sd_connection) {
        sd_disconnect ( $ts_ip );
    }

    return $ret;
}

?>
