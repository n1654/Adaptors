<?php

// Script description
require_once 'smsd/net_common.php';
require_once 'smsd/sms_common.php';
require_once load_once('smsbd', 'common.php');
require_once load_once('quagga', 'quagga_connect.php');

$is_echo_present = false;

$error_list = array(
    "Error",
    "ERROR",
    "Duplicate",
    "Invalid",
    "denied",
    "Unsupported");

$disk_names = array(
    "@flash[0-9]+@",
    "@diskboot@",
    "@bootflash@",
    "@flash@");

// extract the prompt
function extract_prompt() {
    global $sms_sd_ctx;

    /* pour se synchroniser et extraire le prompt correctement */
    $buffer = sendexpectone(__FILE__ . ':' . __LINE__, $sms_sd_ctx, 'conf t', '(config)#');
    $buffer = sendexpectone(__FILE__ . ':' . __LINE__, $sms_sd_ctx, 'exit', '#');
    $buffer = trim($buffer);
    $buffer = substr(strrchr($buffer, "\n"), 1); // recuperer la derniere ligne
    $sms_sd_ctx->setPrompt($buffer);
}

function enter_config_mode() {
    global $sms_sd_ctx;

    unset($tab);
    $tab[0] = "try later";
    $tab[1] = "(config)#";

    $prompt_state = 0;
    $index = 99;
    $timeout = 2000;

    $sms_sd_ctx->sendCmd(__FILE__ . ':' . __LINE__, 'conf t');

    for ($i = 1; ($i <= 5) && ($prompt_state < 2); $i++) {
      $timeout = $timeout * 2;

      switch ($index) {
          case -1: // Error
              quagga_disconnect();
              return ERR_SD_TIMEOUTCONNECT;

          case 99: // wait for router
              $index = sendexpect(__FILE__ . ':' . __LINE__, $sms_sd_ctx, "", $tab);
              break;

          case 0: // "try later"
              sleep($timeout);
              $sms_sd_ctx->sendCmd(__FILE__ . ':' . __LINE__, 'conf t');

              $index = 99;
              $prompt_state = 1;
              break;

          case 1: // "(config)#"
              $prompt_state = 2;
              break;
        }
    }
    if ($prompt_state !== 2) {
        return ERR_SD_CMDTMOUT;
    }

    return SMS_OK;
}


function func_reboot($msg = 'SMSEXEC', $reload_now = false, $is_port_console = false) {
    global $sms_sd_ctx;
    global $sendexpect_result;
    global $result;

    $end = false;
    $tab[0] = '[yes/no]:';
    $tab[1] = '[confirm]';
    $tab[2] = 'to enter the initial configuration dialog? [yes/no]';
    $tab[3] = 'RETURN to get started!';
    $tab[4] = $sms_sd_ctx->getPrompt();
    $tab[5] = '>';
    $tab[6] = 'rommon 1';
    if ($reload_now !== false) {
        $cmd_line = "reload";
    } else {
        $cmd_line = "reload in 001 reason $msg";
    }

    do {
        $index = sendexpect(__FILE__ . ':' . __LINE__, $sms_sd_ctx, $cmd_line, $tab);
        if ($index === 0) {
            $cmd_line = 'no';
        }
        else if ($index === 1) {
            if ($cmd_line === 'no') {
                // enlever l'echo
                $result .= substr($sendexpect_result, 3);
            } else {
                $result .= $sendexpect_result;
            }
            $cmd_line = '';
        }
        else if ($index === 2) {
            $cmd_line = 'no';
        }
        else if ($index === 3) {
            if ($is_port_console === false) {
                $cmd_line = '';
            }
            else {
                $cmd_line = "\r";
            }
        }
        else if ($index === 6) {
            throw new SmsException("Rommon mode after reloading", ERR_SD_FAILED);
        } else {
            $end = true;
        }
    } while (!$end);
}

function func_write() {
    global $sms_sd_ctx;
    global $sendexpect_result;

    unset($tab);
    $tab[0] = "[no]:";
    $tab[1] = "[confirm]";
    $tab[2] = $sms_sd_ctx->getPrompt();
    $index = sendexpect(__FILE__ . ':' . __LINE__, $sms_sd_ctx, "write", $tab);
    if ($index === 0) {
        sms_log_error(__FILE__ . ':' . __LINE__ . ": [[!!! $sendexpect_result !!!]]\n");
        sendexpectone(__FILE__ . ':' . __LINE__, $sms_sd_ctx, "");
        throw new SmsException($sendexpect_result, ERR_SD_CMDFAILED);
    }

    if ($index === 1) {
        sendexpectone(__FILE__ . ':' . __LINE__, $sms_sd_ctx, "");
    }
    return SMS_OK;
}

?>
