<?php
require_once('../../../wp-load.php');

if (class_exists('MC_Funnel')) {
    MC_Funnel::reset_to_defaults();
    echo "Funnel config reset to defaults.\n";
    print_r(MC_Funnel::get_config());
} else {
    echo "MC_Funnel class not found.\n";
}
