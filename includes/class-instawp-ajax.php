<?php

if ( ! defined( 'INSTAWP_PLUGIN_DIR' ) ) {
	die;
}

class InstaWP_AJAX {
	public $instawp_log;

	public function __construct() {
		$this->instawp_log = new InstaWP_Log();
		add_action( 'wp_ajax_instawp_check_key', array( $this, 'check_key' ) );
		add_action( 'wp_ajax_instawp_settings_call', array( $this, 'instawp_settings_call' ) );
		add_action( 'wp_ajax_instawp_connect', array( $this, 'connect' ) );
		add_action( 'wp_ajax_instawp_check_staging', array( $this, 'instawp_check_staging' ) );
		add_action( 'wp_ajax_instawp_logger', array( $this, 'instawp_logger_handle' ) );
		add_action( 'init', array( $this, 'deleter_folder_handle' ) );
		add_action( 'admin_notices', array( $this, 'instawp_connect_reset_admin_notices' ) );

		// Remote Management Settings save.
		add_action( 'wp_ajax_instawp_save_management_settings', array( $this, 'save_management_settings' ) );
	}

	public function save_management_settings() {
		check_ajax_referer( 'instawp-migrate', 'security' );

		$option_name  = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
		$option_value = isset( $_POST['value'] ) ? sanitize_text_field( wp_unslash( $_POST['value'] ) ) : '';

		if ( ! $option_name || ! $option_value ) {
			wp_send_json_error();
		}
		
		update_option( $option_name, $option_value );
		wp_send_json_success();
	}

	// Set transient admin notice function
	public function instawp_connect_reset_admin_notices() {
		if ( isset( $_GET['page'] ) && $_GET['page'] === "instawp-settings" ) {
			$plugins_reset_notice = get_transient( 'instawp_connect_plugin_reset_notice' );
			if ( false !== $plugins_reset_notice ) {
				$html = '<div class="notice notice-warning is-dismissible">';
				$html .= '<p>';
				$html .= $plugins_reset_notice;
				$html .= '</p>';
				$html .= '</div>';
				echo $html;
				delete_transient( 'instawp_connect_plugin_reset_notice' );
			}
		}
	}

	/*Remove un-usable data after our staging creation process is done*/
	public static function instawp_folder_remover_handle() {
		$folder_name        = 'instawpbackups';
		$dirPath            = WP_CONTENT_DIR . '/' . $folder_name;
		$dirPathLogFolder   = $dirPath . '/instawp_log';
		$dirPathErrorFolder = $dirPathLogFolder . '/error';

		if ( substr( $dirPath, strlen( $dirPath ) - 1, 1 ) != '/' ) {
			$dirPath .= '/';
		}
		if ( file_exists( $dirPath ) && is_dir( $dirPath ) ) {
			$instawpbackups_zip_files = glob( $dirPath . "*.zip", GLOB_MARK | GLOB_BRACE );
			foreach ( $instawpbackups_zip_files as $file ) {
				if ( is_file( $file ) ) {
					unlink( $file );
				}
			}
		}

		//log folder
		if ( substr( $dirPathLogFolder, strlen( $dirPathLogFolder ) - 1, 1 ) != '/' ) {
			$dirPathLogFolder .= '/';
		}
		if ( file_exists( $dirPathLogFolder ) && is_dir( $dirPathLogFolder ) ) {
			$instawp_log_txt_files = glob( $dirPathLogFolder . "*.txt", GLOB_MARK | GLOB_BRACE );
			foreach ( $instawp_log_txt_files as $lfile ) {
				if ( is_file( $lfile ) ) {
					unlink( $lfile );
				}
			}
		}

		//error folder
		if ( substr( $dirPathErrorFolder, strlen( $dirPathErrorFolder ) - 1, 1 ) != '/' ) {
			$dirPathErrorFolder .= '/';
		}
		if ( file_exists( $dirPathErrorFolder ) && is_dir( $dirPathErrorFolder ) ) {
			$errorfiles = glob( $dirPathErrorFolder . '{,.}[!.,!..]*', GLOB_MARK | GLOB_BRACE );
			foreach ( $errorfiles as $efile ) {
				if ( is_file( $efile ) ) {
					unlink( $efile );
				}
			}
		}
	}

	// Remove From settings internal
	public static function deleter_folder_handle() {
		if ( isset( $_REQUEST['delete_wpnonce'] ) && wp_verify_nonce( $_REQUEST['delete_wpnonce'], 'delete_wpnonce' ) ) {

			/* Delete Instawp related Options Start */
			global $wpdb;
			$options_table = $wpdb->prefix . "options";
			$sql           = "DELETE FROM $options_table WHERE option_name LIKE '%instawp%' AND option_name !='instawp_api_url'";
			$query         = $wpdb->query( $sql );
			/* Delete Instawp related Options End */

			self::instawp_folder_remover_handle();
			//After Delete Option Set API Domain
			InstaWP_Setting::set_api_domain();

			$transient_message = __( "InstaWP Connect Settings has been reset successfully.", 'instawp-connect' );

			set_transient( 'instawp_connect_plugin_reset_notice', $transient_message, MINUTE_IN_SECONDS );

			$redirect_url = admin_url( "admin.php?page=instawp-settings" );
			wp_redirect( $redirect_url );
			exit();
		}
	}

	/*Handle Js call to remove option*/
	public function instawp_logger_handle() {
		$res_array = array();
		if (
			! empty( $_POST['n'] ) &&
			wp_verify_nonce( $_POST['n'], 'instawp_nlogger_update_option_by-nlogger' ) &&
			! empty( $_POST['l'] )
		) {
			$l = $_POST['l'];
			update_option( 'instawp_finish_upload', array() );
			update_option( 'instawp_staging_list', array() );
			//self::instawp_folder_remover_handle();

			$res_array['message'] = 'success';
			$res_array['status']  = 1;
		} else {
			$res_array['message'] = 'failed';
			$res_array['status']  = 0;
		}

		wp_send_json( $res_array );
		wp_die();
	}

	public function instawp_settings_call() {

		$connect_ids = get_option( 'instawp_connect_id_options', '' );

		if ( isset( $_POST['instawp_api_url_internal'] ) ) {
			$instawp_api_url_internal = $_POST['instawp_api_url_internal'];
			InstaWP_Setting::set_api_domain( $instawp_api_url_internal );
		}

		$message           = '';
		$resType           = false;
		$connect_options   = get_option( 'instawp_api_options', '' );
		$instawp_db_method = isset( $_POST['instawp_db_method'] ) ? sanitize_text_field( $_POST['instawp_db_method'] ) : '';

		update_option( 'instawp_db_method', $instawp_db_method );

		if (
			isset( $connect_ids['data']['id'] ) &&
			! empty( $connect_ids['data']['id'] ) &&
			isset( $_POST['api_heartbeat'] ) &&
			! empty( $_POST['api_heartbeat'] ) &&
			! empty( $connect_options ) &&
			! empty( $connect_options['api_key'] )
		) {
			$api_heartbeat = intval( trim( $_REQUEST['api_heartbeat'] ) );

			$resType = true;
			$message = 'Settings saved successfully';
			update_option( 'instawp_heartbeat_option', $api_heartbeat );

			$heartbeat_option_val = (int) get_option( "instawp_heartbeat_option" );

			if ( (int) $heartbeat_option_val !== intval( $api_heartbeat ) ) {
				$timestamp = wp_next_scheduled( 'instwp_handle_heartbeat_cron_action' );
				wp_unschedule_event( $timestamp, 'instwp_handle_heartbeat_cron_action' );
			} else {
				$timestamp = wp_next_scheduled( 'instwp_handle_heartbeat_cron_action' );
				wp_unschedule_event( $timestamp, 'instwp_handle_heartbeat_cron_action' );

				if ( ! wp_next_scheduled( 'instwp_handle_heartbeat_cron_action' ) ) {
					wp_schedule_event( time(), 'instawp_heartbeat_interval', 'instwp_handle_heartbeat_cron_action' );
				}
			}
		} else {
			$resType = false;
			$message = 'something wrong';
		}

		$res_array            = array();
		$res_array['message'] = $message;
		$res_array['resType'] = $resType;
		echo json_encode( $res_array );
		wp_die();
	}

	public function instawp_check_staging() {

		//$this->ajax_check_security();

		global $InstaWP_Curl;

		$api_domain        = InstaWP_Setting::get_api_domain();
		$response          = array( 'progress' => 5, 'message' => 'New site creation in progress', );
		$connect_ids       = get_option( 'instawp_connect_id_options', '' );
		$bkp_init_opt      = get_option( 'instawp_backup_init_options', '' );
		$backup_status_opt = get_option( 'instawp_backup_status_options', '' );
		$connect_id        = $connect_ids['data']['id'] ?? '';
		$task_id           = $bkp_init_opt['task_info']['task_id'] ?? '';
		$site_id           = $backup_status_opt['data']['site_id'] ?? '';

		if ( empty( $connect_id ) || empty( $task_id ) || empty( $site_id ) ) {
			echo json_encode( $response );
			wp_die();
		}

		$url_restore_status           = $api_domain . INSTAWP_API_URL . '/connects/get_restore_status';
		$curl_response_restore_status = $InstaWP_Curl->curl( $url_restore_status, json_encode( array( 'connect_id' => $connect_id, 'task_id' => $task_id, 'site_id' => $site_id, ) ) );
		$curl_rd_restore_status       = $curl_response_restore_status['curl_res'] ?? '';
		$curl_rd_restore_status       = json_decode( $curl_rd_restore_status, true );

		if ( isset( $curl_rd_restore_status['data'] ) && isset( $curl_rd_restore_status['data']['progress'] ) ) {
			$response['progress'] = $curl_rd_restore_status['data']['progress'];
		}

		if ( isset( $curl_rd_restore_status['data'] ) && isset( $curl_rd_restore_status['data']['message'] ) ) {
			$response['message'] = $curl_rd_restore_status['data']['message'];
		}

		if ( ! isset( $curl_rd_restore_status['status'] ) || $curl_rd_restore_status['status'] == 0 ) {
			echo json_encode( $response );
			wp_die();
		}

		if ( isset( $curl_rd_restore_status['data'] ) && isset( $curl_rd_restore_status['data']['progress'] ) && $curl_rd_restore_status['data']['progress'] == 100 ) {

			$connect_id      = $curl_rd_restore_status['data']['wp'][0]['connect_id'];
			$site_name       = $curl_rd_restore_status['data']['wp'][0]['site_name'];
			$wp_admin_url    = $curl_rd_restore_status['data']['wp'][0]['wp_admin_url'];
			$wp_admin_email  = $curl_rd_restore_status['data']['wp'][0]['wp_admin_email'];
			$wp_username     = $curl_rd_restore_status['data']['wp'][0]['wp_username'];
			$wp_password     = $curl_rd_restore_status['data']['wp'][0]['wp_password'];
			$auto_login_hash = $curl_rd_restore_status['data']['wp'][0]['auto_login_hash'];

			instawp_staging_insert_site( array(
				'task_id'         => $task_id,
				'connect_id'      => $connect_id,
				'site_name'       => $site_name,
				'site_url'        => str_replace( '/wp-admin', '', $wp_admin_url ),
				'admin_email'     => $wp_admin_email,
				'username'        => $wp_username,
				'password'        => $wp_password,
				'auto_login_hash' => $auto_login_hash,
			) );

			$response = array(
				"progress"        => 100,
				"result"          => "success",
				"status"          => 1,
				"details"         => array(
					"name"  => $site_name,
					"url"   => "https://" . str_replace( '/wp-admin', '', $wp_admin_url ),
					"user"  => $wp_username,
					"code"  => $wp_password,
					"login" => add_query_arg( array( 'site' => $auto_login_hash ), $api_domain . '/wordpress-auto-login' ),
				),
				"server_response" => $curl_rd_restore_status,
			);
		}

		echo json_encode( $response );

		wp_die();
	}


	public function check_key() {
		global $InstaWP_Curl;

		// $this->ajax_check_security();

		$res        = array(
			'error'   => true,
			'message' => '',
		);
		$api_doamin = InstaWP_Setting::get_api_domain();
		$url        = $api_doamin . INSTAWP_API_URL . '/check-key';

		if ( isset( $_REQUEST['api_key'] ) && empty( $_REQUEST['api_key'] ) ) {
			$res['message'] = 'API Key is required';
			echo json_encode( $res );
			wp_die();
		}
		$api_key = sanitize_text_field( wp_unslash( $_REQUEST['api_key'] ) );

		$response = wp_remote_get( $url,
			array(
				'body'    => '',
				'headers' => array(
					'Authorization' => 'Bearer ' . $api_key,
					'Accept'        => 'application/json',
				),
			)
		);

		$response_code = wp_remote_retrieve_response_code( $response );

		if ( ! is_wp_error( $response ) && $response_code == 200 ) {
			$body = (array) json_decode( wp_remote_retrieve_body( $response ), true );

			$connect_options = array();
			if ( $body['status'] == true ) {

				$connect_options['api_key'] = $api_key;
				//$connect_options['api_heartbeat']  = $api_heartbeat;
				$connect_options['response'] = $body;

				//InstaWP_Setting::update_connect_option('instawp_connect_options',$connect_options,'api_key_opt');
				update_option( 'instawp_api_options', $connect_options );
				update_option( 'instawp_api_key', $api_key );

				/* Set Connect ID on Check API KEY Code Start */
				$connect_url = $api_doamin . INSTAWP_API_URL . '/connects';
				$php_version = substr( phpversion(), 0, 3 );
				$username    = null;
				$admin_users = get_users(
					array(
						'role__in' => array( 'administrator' ),
						'fields'   => array( 'user_login' )
					)
				);

				if ( ! empty( $admin_users ) ) {
					if ( is_null( $username ) ) {
						foreach ( $admin_users as $admin ) {
							$username = $admin->user_login;
						}
					}
				}

				$connect_body = json_encode(
					array(
						"url"         => get_site_url(),
						"php_version" => $php_version,
						"username"    => ! is_null( $username ) ? base64_encode( $username ) : "",
					)
				);

				$curl_response = $InstaWP_Curl->curl( $connect_url, $connect_body );
				update_option( 'instawp_connect_id_options_err', $curl_response );

				if ( $curl_response['error'] == false ) {
					$response = (array) json_decode( $curl_response['curl_res'], true );

					if ( $response['status'] == true ) {
						$connect_options                = InstaWP_Setting::get_option( 'instawp_connect_options', array() );
						$connect_id                     = $response['data']['id'];
						$connect_options[ $connect_id ] = $response;
						update_option( 'instawp_connect_id_options', $response );
					}
				}
				/* Set Connect ID on Check API KEY Code End */
				error_log( "BoDY MEssage: " . $body['message'] );
				$res = array(
					'error'   => false,
					'message' => $body['message'],
				);
			} else {
				$res = array(
					'error'   => true,
					'message' => 'Key Not Valid',
				);
			}
		} else {
			$res = array(
				'error'   => true,
				'message' => 'Key Not Valid',
			);
		}
		echo json_encode( $res );
		wp_die();
	}

	public function connect() {

		global $InstaWP_Curl;
		$this->ajax_check_security();
		$res        = array(
			'error'   => true,
			'message' => '',
		);
		$api_doamin = InstaWP_Setting::get_api_domain();
		$url        = $api_doamin . INSTAWP_API_URL . '/connects';

		// $connect_options = get_option('instawp_api_options', '');
		// if (!isset($connect_options['api_key']) && empty($connect_options['api_key'])) {
		//    $res['message'] = 'API Key is required';
		//    echo json_encode($res);
		//    wp_die();
		// }
		// $api_key = $connect_options['api_key'];
		$php_version = substr( phpversion(), 0, 3 );

		/*Get username*/
		$username    = null;
		$admin_users = get_users(
			array(
				'role__in' => array( 'administrator' ),
				'fields'   => array( 'user_login' )
			)
		);

		if ( ! empty( $admin_users ) ) {
			if ( is_null( $username ) ) {
				foreach ( $admin_users as $admin ) {
					$username = $admin->user_login;
				}
			}
		}
		/*Get username closes*/
		$body = json_encode(
			array(
				"url"         => get_site_url(),
				"php_version" => $php_version,
				"username"    => ! is_null( $username ) ? base64_encode( $username ) : "",
			)
		);

		/*Debugging*/
		error_log( strtoupper( "on connect call sent data ---> " ) . print_r( json_decode( $body, true ), true ) );
		/*Debugging*/

		$curl_response = $InstaWP_Curl->curl( $url, $body );
		update_option( 'instawp_connect_id_options_err', $curl_response );
		if ( $curl_response['error'] == false ) {

			$response = (array) json_decode( $curl_response['curl_res'], true );

			if ( $response['status'] == true ) {
				$connect_options                = InstaWP_Setting::get_option( 'instawp_connect_options', array() );
				$connect_id                     = $response['data']['id'];
				$connect_options[ $connect_id ] = $response;
				update_option( 'instawp_connect_id_options', $response ); // old
				//InstaWP_Setting::update_connect_option('instawp_connect_options',$connect_options,$connect_id);

				/* RUN CRON ON CONNECT START */
				$timestamp = wp_next_scheduled( 'instwp_handle_heartbeat_cron_action' );
				wp_unschedule_event( $timestamp, 'instwp_handle_heartbeat_cron_action' );

				if ( ! wp_next_scheduled( 'instwp_handle_heartbeat_cron_action' ) ) {
					wp_schedule_event( time(), 'instawp_heartbeat_interval', 'instwp_handle_heartbeat_cron_action' );
				}
				/* RUN CRON ON CONNECT END */

				$res['message'] = $response['message'];
				$res['error']   = false;
			} else {
				update_option( 'instawp_connect_id_options_err', $response );
				$res['message'] = 'Something Went Wrong. Please try again';
				$res['error']   = true;
			}
		} else {
			$res['message'] = 'Something Went Wrong. Please try again';
			$res['error']   = true;
		}

		echo json_encode( $res );
		wp_die();
	}

	public function test_connect() {

		$this->ajax_check_security();
		$res        = array(
			'error'   => true,
			'message' => '',
		);
		$api_doamin = InstaWP_Setting::get_api_domain();
		$url        = $api_doamin . INSTAWP_API_URL . '/connects/';

		$connect_options = get_option( 'instawp_api_options', '' );
		if ( ! isset( $connect_options['api_key'] ) && empty( $connect_options['api_key'] ) ) {
			$res['message'] = 'API Key is required';
			echo json_encode( $res );
			wp_die();
		}
		$api_key = $connect_options['api_key'];
		$header  = array(
			'Authorization' => 'Bearer ' . $api_key,
			'Accept'        => 'application/json',
			'Content-Type'  => 'application/json;charset=UTF-8',

		);
		$body    = json_encode( array( 'url' => get_site_url() ) );

		print_r( $body );

		$response      = wp_remote_post( $url, array(
			'headers' => $header,
			'body'    => json_encode( $body ),

		) );
		$response_code = wp_remote_retrieve_response_code( $response );
		print_r( $response );
		if ( ! is_wp_error( $response ) && $response_code == 200 ) {
			$body = (array) json_decode( wp_remote_retrieve_body( $response ), true );

			print_r( $body );
			// $connect_options = array();
			// if ($body['status'] == true) {
			//    $connect_options['api_key']   = $connect_options['api_key'];
			//    $connect_options['connected'] = true;
			//    $connect_options['response']  = $body;
			//    update_option('instawp_connect_id_options', $connect_options);
			//    $res = array(
			//       'error'   => false,
			//       'message' => 'Connected',

			//    );
			// }
			// else {
			//    $res = array(
			//       'error'   => true,
			//       'message' => 'Api Key Not Valid'

			//    );
			// }

		}
		echo json_encode( $res );
		wp_die();
	}

	public function ajax_check_security( $role = 'administrator' ) {
		check_ajax_referer( 'instawp_ajax', 'nonce' );
		$check = is_admin() && current_user_can( $role );
		$check = apply_filters( 'instawp_ajax_check_security', $check );
		if ( ! $check ) {
			wp_die();
		}
	}
}

new InstaWP_AJAX();
