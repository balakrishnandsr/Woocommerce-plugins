<?php
/**
 * Plugin Name: Advanced Bank Transfer
 * Plugin URI: https://github.com/balakrishnandsr/advanced-bank-transfer
 * Description: A brief description of the Plugin.
 * Version: 1.0
 * Author: Balakrishnan
 * Author URI: https://github.com/balakrishnandsr/
 * License: GPL3
 **/
defined("ABACS_DIR") or define('ABACS_DIR', __DIR__);
global $advanced_bank_transfer;
include __DIR__ . "/src/class-advanced-bank-transfer.php";
$advanced_bank_transfer = AdvancedBankTransfer::init();
