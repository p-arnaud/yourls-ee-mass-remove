<?php
/*
Plugin Name: YOURLS EE Mass Remove
Plugin URI: https://github.com/p-arnaud/yourls-ee-mass-remove
Description: Remove several (or all) links.
Version: 1.0
Author: p-arnaud
Author URI: https://github.com/p-arnaud
*/

// Based from Mass Remove Links by Ozh (http://ozh.org/)

yourls_add_action( 'plugins_loaded', 'yourls_ee_mass_remove_add_page' );
function yourls_ee_mass_remove_add_page() {
        yourls_register_plugin_page( 'ozh_lmr', 'Link Mass Remove', 'yourls_ee_mass_remove_do_page' );
}

// Display admin page
function yourls_ee_mass_remove_do_page() {
        if( isset( $_POST['action'] ) && $_POST['action'] == 'link_mass_remove' ) {
                yourls_ee_mass_remove_process();
        } else {
                yourls_ee_mass_remove_form();
        }
}

// Display form
function yourls_ee_mass_remove_form() {
        $nonce = yourls_create_nonce('link_mass_remove');
        echo <<<HTML
<h2>Link Mass Remove</h2>
<p>Remove the following links:</p>
<form method="post">
<input type="hidden" name="action" value="link_mass_remove" />
<input type="hidden" name="nonce" value="$nonce" />

<p><label for="radio_date">
<input type="radio" name="what" id="radio_date" value="date"/>All links created on date
</label>
<input type="text" name="date" /> (mm/dd/yyyy)
</p>
<p><label for="radio_daterange">
<input type="radio" name="what" id="radio_daterange" value="daterange"/>All links created between
</label>
<input type="text" name="date1" /> and <input type="text" name="date2" /> (mm/dd/yyyy)
</p>
<p><label for="radio_ip">
<input type="radio" name="what" id="radio_ip" value="ip"/>All links created by IP
</label>
<input type="text" name="ip" />
</p>
<p><label for="radio_url">
<input type="radio" name="what" id="radio_url" value="url"/>All links pointing to a long URL containing
</label>
<input type="text" name="url" /> (case sensitive)
</p>
<p><label for="radio_all">
<input type="radio" name="what" id="radio_all" value="all"/>All links. All.
</label>
</p>
<p><label for="check_test"><input type="checkbox" id="check_test" name="test" value="test" /> Display results, do not delete. This is a test.</label></p>
<p><input type="submit" value="Delete" /> (no undo!)</p>
</form>
<script>
function select_radio(el){
$(el).parent().find(':radio').click();
}
$('input:text')
.click(function(){select_radio($(this))})
.focus(function(){select_radio($(this))})
.change(function(){select_radio($(this))});
</script>
HTML;
}

function yourls_ee_mass_remove_process() {
        // Check nonce
        yourls_verify_nonce( 'link_mass_remove' );

        $where = '';

        switch( $_POST['what'] ) {
                case 'all':
                        $where = '1=1';
                        break;

                case 'date':
                        $date = yourls_sanitize_date_for_sql( $_POST['date'] );
                        $where = "`timestamp` BETWEEN '$date 00:00:00' and '$date 23:59:59'";
                        break;

                case 'daterange':
                        $date1 = yourls_sanitize_date_for_sql( $_POST['date1'] );
                        $date2 = yourls_sanitize_date_for_sql( $_POST['date2'] );
                        $where = "`timestamp` BETWEEN '$date1 00:00:00' and '$date2 23:59:59'";
                        break;

                case 'ip':
                        $ip = yourls_escape( $_POST['ip'] );
                        $where = "`ip` ='$ip'";
                        break;

                case 'url':
                        $url = yourls_escape( $_POST['url'] );
                        $where = "`url` LIKE '%$url%'";
                        break;

                default:
                        echo 'Not implemented';
                        return;
        }

        global $ydb;
        $ee_multi_users_plugin = yourls_is_active_plugin('yourls-ee-multi-users/plugin.php');
        if ($ee_multi_users_plugin == 1) {
            $where .=  ee_multi_users_admin_list_where("");
        }
        $action = ( isset( $_POST['test'] ) && $_POST['test'] == 'test' ) ? 'SELECT' : 'DELETE' ;
        $select = ( $action == 'SELECT' ) ? '`keyword`,`url`' : '';


        if ($ee_multi_users_plugin == 1) {
            if ($action == 'DELETE') {
                $table = YOURLS_DB_TABLE_URL;
                $keywords = $ydb->get_results("SELECT * FROM `$table` WHERE $where");

                $table = YOURLS_DB_TABLE_URL_TO_USER;
                foreach ($keywords as $value) {
                    $ydb->query("DELETE FROM `$table`  where `url_keyword` = '$value->keyword'");
                }
            }
        }
        $table = YOURLS_DB_TABLE_URL;
        echo "$action $select FROM `$table` WHERE $where";
        $query = $ydb->get_results("$action $select FROM `$table` WHERE $where");

        if( $action == 'SELECT' ) {
                if( !$query ) {
                        echo 'No link found.';
                        return;
                } else {
                        echo '<p>'.count( $query ).' found:</p>';
                        echo '<ul>';
                        foreach( $query as $link ) {
                                $short = $link->keyword;
                                $url = $link->url;
                                echo "<li>$short: <a href='$url'>$url</a></li>\n";
                        }
                        echo '</ul>';
                        unset( $_POST['test'] );
                        echo '<form method="post">';
                        foreach( $_POST as $k=>$v ) {
                                if( $v )
                                        echo "<input type='hidden' name='$k' value='$v' />";
                        }
                        echo '<input type="submit" value="OK. Delete" /></form>';
                }
        } else {
                echo "Link(s) deleted.";
        }
}
