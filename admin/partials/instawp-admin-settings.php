<?php
/**
 * Provide a admin area view for the plugin
 *
 * This file is used to markup the admin-facing aspects of the plugin.
 *
 * @link       https://instawp.com/
 * @since      1.0
 *
 * @package    instaWP
 * @subpackage instaWP/admin/partials
 */
global $instawp_plugin;


$connect_options = get_option( 'instawp_api_options', '' );
$instawp_api_url = get_option( 'instawp_api_url', '' );
$general_setting = InstaWP_Setting::get_setting( true, "" );
$api_key         = '';


$instawp_db_method = get_option( 'instawp_db_method', 'pdo' );
$instawp_db_method = empty( $instawp_db_method ) ? 'pdo' : $instawp_db_method;

if ( ! empty( get_option( 'instawp_heartbeat_option' ) ) ) {
	$instawp_heartbeat_option = get_option( 'instawp_heartbeat_option' );
} else {
	$instawp_heartbeat_option = 15;
}
if ( ! empty( $connect_options ) ) {
	$api_key = $connect_options['api_key'];
}
// $InstaWP_BackupUploader = new InstaWP_BackupUploader();
// $res                    = $InstaWP_BackupUploader->_rescan_local_folder_set_backup_api();

$tasks = InstaWP_taskmanager::get_tasks_backup_running();

if ( isset( $_POST['instawp_settings_nonce'] )
     && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['instawp_settings_nonce'] ) ), 'instawp_settings' )
) {
	$tasks = InstaWP_taskmanager::get_tasks_backup_running();

	foreach ( $tasks as $task_id => $task ) {


		if ( isset( $tasks[ $task_id ]['data']['backup']['sub_job']['backup_merge']['finished'] ) && $tasks[ $task_id ]['data']['backup']['sub_job']['backup_merge']['finished'] == '1' ) {

			echo esc_html( $task_id ) . "upload cancel";
			update_option( 'upload_cancel_' . $task_id, '1' );
			// InstaWP_taskmanager::update_backup_main_task_progress($task_id, 'upload', 100, 1);
			// InstaWP_taskmanager::update_backup_task_status($task_id, false, 'completed');
		} else {
			echo esc_html( $task_id ) . " backup cancel";

			$res = $instawp_plugin->backup_cancel_api();

		}
	}
}
?>
<div class="wrap instawp-settings-page">
    <h1>Settings</h1>
    <form method="POST" action="" id="instawp_settings">
        <table class="form-table" width="100%">
            <tr valign="top">
                <th scope="row">
                    <label for="num_elements">
                        API Key
                    </label>
                </th>
                <td>
                    <input type="text" name="api_key" id="instawp_api" value="<?php echo esc_html( $api_key ); ?>"/>
                    <a class="button button-primary" id="instawp-check-key">Check</a>
					<?php /*
                    <a href="https://app.instawp.io/user/api-tokens" target="_blank" class="">Generate API Key</a>   
                    */ ?>
					<?php
					$instawp_api_url = InstaWP_Setting::get_api_domain();
					$return_url      = urlencode( admin_url( 'admin.php?page=instawp-connect' ) );
					$source_url      = $instawp_api_url . '/authorize?source=InstaWP Connect&return_url=' . $return_url;
					?>
                    <a href="<?php echo $source_url; ?>" class="">Generate API Key</a>
                    <p class="instawp-err-msg"></p>
                </td>
            </tr>

            <tr valign="top">
                <th scope="row">
                    <label for="num_elements">
                        Heartbeat min.
                    </label>
                </th>
                <td>
                    <input type="number" min="2" max="120" value="<?php echo esc_html( $instawp_heartbeat_option ); ?>" name="instawp_api_heartbeat" id="instawp_api_heartbeat"/>
                </td>
            </tr>

            <tr valign="top">
                <th scope="row">
                    <label for="instawp_db_method">Database Method</label>
                </th>
                <td>
                    <select name="instawp_db_method" id="instawp_db_method">
                        <option value=""><?php esc_html_e( 'Select database method', 'instawp-connect' ); ?></option>
                        <option <?php selected( $instawp_db_method, 'pdo' ); ?> value="pdo"><?php esc_html_e( 'PDO', 'instawp-connect' ); ?></option>
                        <option <?php selected( $instawp_db_method, 'wpdb' ); ?> value="wpdb"><?php esc_html_e( 'WPDB', 'instawp-connect' ); ?></option>
                    </select>
                    <p class="description">WPDB option has a better compatibility, but the speed of backup and restore is slower.</p>
                    <p class="description">It is recommended to choose PDO option if pdo_mysql extension is installed on your server, which lets you backup and restore your site faster.</p>
                </td>
            </tr>

			<?php if ( isset( $_GET['internal'] ) && $_GET['page'] == 'instawp-settings' && 1 === intval( $_GET['internal'] ) ) {
				$interal_api_domain = get_option( 'instawp_api_url', '' );
				?>
                <tr valign="top" style="border-top: 1px solid;">
                    <th style="border-bottom: 1px solid;padding-left: 10px;">
						<?php echo strtoupper( 'Developer options' ); ?>
                    </th>
                </tr>
                <tr valign="top" style="border-bottom: 1px solid;">
                    <th scope="row">
                        <label for="num_elements">
                            API Domain
                        </label>
                    </th>
                    <td>
                        <input type="text" value="<?php echo esc_attr( $interal_api_domain ); ?>" required="" name="instawp_api_url_internal" id="instawp_api_url_internal"/>
                    </td>
                </tr>
			<?php } ?>
        </table>
		<?php wp_nonce_field( 'instawp_settings', 'instawp_settings_nonce' ); ?>
		<?php submit_button(); ?>
    </form>
    <hr>
    <div>
        <div>
            <h4>
                <?php esc_html_e('Reset Plugin', 'instawp-connect'); ?>
            </h4>
        </div>
        <div>

            <?php 
            $nonce = wp_create_nonce( 'delete_wpnonce' );
            $actionurl = admin_url( "admin.php?page=instawp-settings" );
            $delete_url = add_query_arg( array( 'delete_wpnonce' => $nonce ), $actionurl );
            ?> 
            <a href="<?php echo $delete_url; ?>" onclick="return confirm('Are you sure?')" class="button button-primary" >
                <?php esc_html_e('Reset', "instawp-connect"); ?>
            </a>
        </div>
    </div>
</div>
<script type="text/javascript">
    jQuery(document).ready(function () {

        jQuery(document).on('click', '#instawp-check-key', function () {
            var api_key = jQuery('#instawp_api').val();
            var api_heartbeat = jQuery('#instawp_api_heartbeat').val();
            jQuery.ajax({
                type: 'POST',
                url: instawp_ajax_object.ajax_url,
                data: {
                    action: "instawp_check_key",
                    nonce: instawp_ajax_object.ajax_nonce,
                    api_key: api_key,
                    api_heartbeat: api_heartbeat

                },
                success: function (response) {

                    var obj = JSON.parse(response);
                    if (obj.error == true) {
                        var msg = '<span style="color:red">' + obj.message + '</span>';
                    } else {
                        var msg = '<span style="color:green">' + obj.message + '</span>';
                    }
                    jQuery('.instawp-err-msg').html(msg);

                },
                error: function (errorThrown) {
                    console.log('error');
                }
            });
        });

        //save settings call to save heartbeat input value        
        jQuery(document).on('submit', '#instawp_settings', function (e) {
            e.preventDefault();

            let api_heartbeat = jQuery('#instawp_api_heartbeat').val(),
                instawp_api_url_internal = jQuery('#instawp_api_url_internal').val(),
                instawp_db_method = jQuery('#instawp_db_method').val();

            jQuery.ajax({
                type: 'POST',
                url: instawp_ajax_object.ajax_url,
                data: {
                    action: "instawp_settings_call",
                    nonce: instawp_ajax_object.ajax_nonce,
                    api_heartbeat: api_heartbeat,
                    instawp_api_url_internal: instawp_api_url_internal,
                    instawp_db_method: instawp_db_method
                },
                success: function (response) {
                    location.reload();
                }
            });
        });
    });


    /*//heartbeat call based on time code start
    var call_timing = "<?php // echo $instawp_heartbeat_option;?>";
    if( call_timing=='' ){
        call_timing = 15;
    }
    
    var heartbeatInterval = 1000 * 60 * parseInt( call_timing );
    setInterval(site_heartbeat, heartbeatInterval);

    function site_heartbeat(){       
        console.log("Call Started");
        jQuery.ajax({
            type: 'POST',
            url: instawp_ajax_object.ajax_url,
            data: {
               action: "instawp_heartbeat_check",
               nonce: instawp_ajax_object.ajax_nonce,                
            },
            success: function (response) {    
                console.log("Call response start");           
                console.log(response);
                console.log("Call response end");           
            },
            error: function (errorThrown) {
               console.log('error');
           }
       });    
   }*/
</script>