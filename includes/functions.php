<?php
/**
 * All helper functions here
 */


if ( ! function_exists( 'instawp_staging_create_db_table' ) ) {
	/**
	 * @return void
	 */
	function instawp_staging_create_db_table() {

		if ( ! function_exists( 'maybe_create_table' ) ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		}

		$sql_create_table = "CREATE TABLE " . INSTAWP_DB_TABLE_STAGING_SITES . " (
        id int(50) NOT NULL AUTO_INCREMENT,
        task_id varchar(255) NOT NULL,
        connect_id varchar(255) NOT NULL,
        site_name varchar(255) NOT NULL,
        site_url varchar(255) NOT NULL,
	    admin_email varchar(255) NOT NULL,
	    username varchar(255) NOT NULL,
	    password varchar(255) NOT NULL,
	    auto_login_hash varchar(255) NOT NULL,
        datetime  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    );";

		maybe_create_table( INSTAWP_DB_TABLE_STAGING_SITES, $sql_create_table );
	}
}


if ( ! function_exists( 'instawp_staging_insert_site' ) ) {
	/**
	 * @param $args
	 *
	 * @return bool
	 */
	function instawp_staging_insert_site( $args = array() ) {

		global $wpdb;

		$task_id         = isset( $args['task_id'] ) ? $args['task_id'] : '';
		$connect_id      = isset( $args['connect_id'] ) ? $args['connect_id'] : '';
		$site_name       = isset( $args['site_name'] ) ? $args['site_name'] : '';
		$site_url        = isset( $args['site_url'] ) ? $args['site_url'] : '';
		$admin_email     = isset( $args['admin_email'] ) ? $args['admin_email'] : '';
		$username        = isset( $args['username'] ) ? $args['username'] : '';
		$password        = isset( $args['password'] ) ? $args['password'] : '';
		$auto_login_hash = isset( $args['auto_login_hash'] ) ? $args['auto_login_hash'] : '';
		$is_error        = false;

		// Check if any value is empty
		foreach ( $args as $key => $value ) {
			if ( empty( $value ) ) {
				$is_error = true;

				error_log( sprintf( esc_html__( 'Empty value for %s', 'instawp-connect' ), $key ) );
				break;
			}
		}

		if ( $is_error ) {
			return false;
		}

		$insert_response = $wpdb->insert( INSTAWP_DB_TABLE_STAGING_SITES,
			array(
				'task_id'         => $task_id,
				'connect_id'      => $connect_id,
				'site_name'       => $site_name,
				'site_url'        => $site_url,
				'admin_email'     => $admin_email,
				'username'        => $username,
				'password'        => $password,
				'auto_login_hash' => $auto_login_hash,
			)
		);

		if ( ! $insert_response ) {
			error_log( sprintf( esc_html__( 'Error occurred while inserting new site. Error Message: %s', 'instawp-connect' ), $wpdb->last_error ) );

			return false;
		}

		return true;
	}
}


if ( ! function_exists( 'instawp' ) ) {
	/**
	 * @return instaWP
	 */
	function instawp() {
		global $instawp;

		if ( empty( $instawp ) ) {
			$instawp = new instaWP();
		}

		return $instawp;
	}
}


if ( ! function_exists( 'instawp_get_packages' ) ) {
	function instawp_get_packages( $instawp_task, $data = array() ) {

		if ( ! class_exists( 'InstaWP_ZipClass' ) ) {
			include_once INSTAWP_PLUGIN_DIR . '/includes/class-instawp-zipclass.php';
		}

		if ( ! $instawp_task instanceof InstaWP_Backup_Task ) {
			return array();
		}

		$instawp_zip = new InstaWP_ZipClass();
		$packages    = $instawp_task->get_packages_info( $data['key'] );

		if ( ! $packages ) {

			if ( isset( $data['plugin_subpackage'] ) ) {
				$ret = $instawp_zip->get_plugin_packages( $data );
			} elseif ( isset( $data['uploads_subpackage'] ) ) {
				$ret = $instawp_zip->get_upload_packages( $data );
			} else {
				if ( $data['key'] == INSTAWP_BACKUP_TYPE_MERGE ) {
					$ret = $instawp_zip->get_packages( $data, true );
				} else {
					$ret = $instawp_zip->get_packages( $data );
				}
			}

			$packages = $instawp_task->set_packages_info( $data['key'], $ret['packages'] );
		}

		return $packages;
	}
}


if ( ! function_exists( 'instawp_build_zip_files' ) ) {
	function instawp_build_zip_files( $instawp_task, $packages = array(), $data = array() ) {

		if ( ! class_exists( 'InstaWP_ZipClass' ) ) {
			include_once INSTAWP_PLUGIN_DIR . '/includes/class-instawp-zipclass.php';
		}

		if ( ! $instawp_task instanceof InstaWP_Backup_Task ) {
			return array();
		}

		$result      = array();
		$instawp_zip = new InstaWP_ZipClass();

		foreach ( $packages as $package ) {

			instawp()->set_time_limit( $instawp_task->get_id() );

			if ( ! empty( $package['files'] ) && ! $package['backup'] ) {

				if ( isset( $data['uploads_subpackage'] ) ) {
					$files = $instawp_zip->get_upload_files_from_cache( $package['files'] );
				} else {
					$files = $package['files'];
				}

				if ( empty( $files ) ) {
					continue;
				}

				$zip_ret = $instawp_zip->_zip( $package['path'], $files, $data, $package['json'] );

				if ( $zip_ret['result'] == INSTAWP_SUCCESS ) {

					if ( isset( $data['uploads_subpackage'] ) ) {
						if ( file_exists( $package['files'] ) ) {
							@unlink( $package['files'] );
						}
					}

					$result['files'][] = $zip_ret['file_data'];
					$package['backup'] = true;

					$instawp_task->update_packages_info( $data['key'], $package, $zip_ret['file_data'] );
				}
			}
		}

		return $result;
	}
}


if ( ! function_exists( 'instawp_get_overall_migration_progress' ) ) {
	/**
	 * Calculate and return overall progress
	 *
	 * @param $migrate_id
	 *
	 * @return int|mixed|null
	 */
	function instawp_get_overall_migration_progress( $migrate_id = '' ) {

		$overall_progress = 0;

		if ( empty( $migrate_id ) || 0 == $migrate_id ) {
			return $overall_progress;
		}

		$status_response = InstaWP_Curl::do_curl( "migrates/{$migrate_id}/get_parts_status", array(), array(), false );
		$response_data   = InstaWP_Setting::get_args_option( 'data', $status_response, array() );
		$migrate_parts   = InstaWP_Setting::get_args_option( 'migrate_parts', $response_data, array() );
		$migrate_parts   = array_map( function ( $migrate_part ) {
			$restore_progress = InstaWP_Setting::get_args_option( 'restore_progress', $migrate_part );
			if ( ! $restore_progress || $restore_progress == 'null' ) {
				return 0;
			}

			return (int) $restore_progress;
		}, $migrate_parts );

		if ( count( $migrate_parts ) > 0 ) {
			$overall_progress = array_sum( $migrate_parts ) / count( $migrate_parts );
		}

		return apply_filters( 'INSTAWP_CONNECT/Filters/get_overall_migration_progress', $overall_progress, $migrate_id );
	}
}


if ( ! function_exists( 'instawp_get_migration_site_detail' ) ) {
	/**
	 * Return migration site detail
	 *
	 * @param $migrate_id
	 *
	 * @return array|mixed|null
	 */
	function instawp_get_migration_site_detail( $migrate_id = '' ) {

		if ( empty( $migrate_id ) || 0 == $migrate_id ) {
			return array();
		}

		$api_response  = InstaWP_Curl::do_curl( "migrates/{$migrate_id}", array(), array(), false );
		$response_data = InstaWP_Setting::get_args_option( 'data', $api_response, array() );
		$site_detail   = InstaWP_Setting::get_args_option( 'site_detail', $response_data, array() );

		if ( ! empty( $auto_login_hash = InstaWP_Setting::get_args_option( 'auto_login_hash', $site_detail ) ) ) {
			$site_detail['auto_login_url'] = sprintf( '%s/wordpress-auto-login?site=%s', InstaWP_Setting::get_api_domain(), $auto_login_hash );
		}

		return apply_filters( 'INSTAWP_CONNECT/Filters/get_migration_site_detail', $site_detail, $migrate_id );
	}
}


if ( ! function_exists( 'instawp_reset_running_migration' ) ) {
	/**
	 * Reset running migration
	 *
	 * @param $reset_type
	 *
	 * @return bool
	 */
	function instawp_reset_running_migration( $reset_type = 'soft' ) {

		$reset_type = empty( $reset_type ) ? InstaWP_Setting::get_option( 'instawp_reset_type', 'soft' ) : $reset_type;

		if ( ! in_array( $reset_type, array( 'soft', 'hard' ) ) ) {
			return false;
		}

		InstaWP_taskmanager::delete_all_task();
		$task = new InstaWP_Backup();
		$task->clean_backup();

		if ( 'hard' == $reset_type ) {
			delete_option( 'instawp_api_key' );
			delete_option( 'instawp_api_options' );
			update_option( 'instawp_api_url', esc_url_raw( 'https://app.instawp.io' ) );
		}


		delete_option( 'instawp_compress_setting' );


		$response = InstaWP_Curl::do_curl( "migrates/force-timeout", array( 'source_domain' => site_url() ) );

		if ( isset( $response['success'] ) && ! $response['success'] ) {
			error_log( json_encode( $response ) );
		}

		return true;
	}
}


if ( ! function_exists( 'instawp_backup_files' ) ) {
	/**
	 * @param InstaWP_Backup_Task $migrate_task_obj
	 *
	 * @return void
	 */
	function instawp_backup_files( InstaWP_Backup_Task $migrate_task_obj, $args = array() ) {

		$migrate_task = InstaWP_taskmanager::get_task( $migrate_task_obj->get_id() );

		foreach ( InstaWP_taskmanager::get_task_backup_data( $migrate_task_obj->get_id() ) as $key => $data ) {

			$backup_status = InstaWP_Setting::get_args_option( 'backup_status', $data );

			if ( 'completed' != $backup_status && 'backup_db' == $key ) {
				$backup_database = new InstaWP_Backup_Database();
				$backup_response = $backup_database->backup_database( $data, $migrate_task_obj->get_id() );

				if ( INSTAWP_SUCCESS == InstaWP_Setting::get_args_option( 'result', $backup_response ) ) {
					$migrate_task['options']['backup_options']['backup'][ $key ]['files'] = $backup_response['files'];
				} else {
					$migrate_task['options']['backup_options']['backup'][ $key ]['backup_status'] = 'in_progress';
				}

				$packages = instawp_get_packages( $migrate_task_obj, $migrate_task['options']['backup_options']['backup'][ $key ] );
				$result   = instawp_build_zip_files( $migrate_task_obj, $packages, $migrate_task['options']['backup_options']['backup'][ $key ] );

				if ( isset( $result['files'] ) && ! empty( $result['files'] ) ) {
					$migrate_task['options']['backup_options']['backup'][ $key ]['zip_files']     = $result['files'];
					$migrate_task['options']['backup_options']['backup'][ $key ]['backup_status'] = 'completed';
				}

				InstaWP_taskmanager::update_task( $migrate_task );
			}

			if ( 'completed' != $backup_status ) {

				$migrate_task['options']['backup_options']['backup'][ $key ]['files'] = $migrate_task_obj->get_need_backup_files( $migrate_task['options']['backup_options']['backup'][ $key ] );

				$packages = instawp_get_packages( $migrate_task_obj, $migrate_task['options']['backup_options']['backup'][ $key ] );
				$result   = instawp_build_zip_files( $migrate_task_obj, $packages, $migrate_task['options']['backup_options']['backup'][ $key ] );

				if ( isset( $result['files'] ) && ! empty( $result['files'] ) ) {
					$migrate_task['options']['backup_options']['backup'][ $key ]['zip_files']     = $result['files'];
					$migrate_task['options']['backup_options']['backup'][ $key ]['backup_status'] = 'completed';
				}

				InstaWP_taskmanager::update_task( $migrate_task );
			}
		}

		if ( InstaWP_Setting::get_args_option( 'clean_non_zip', $args, false ) === true ) {
			instawp_clean_non_zipped_files( $migrate_task_obj );
		}
	}
}


if ( ! function_exists( 'instawp_clean_non_zipped_files' ) ) {
	/**
	 * @param InstaWP_Backup_Task $migrate_task_obj
	 *
	 * @return void
	 */
	function instawp_clean_non_zipped_files( InstaWP_Backup_Task $migrate_task_obj ) {

		$migrate_task = InstaWP_taskmanager::get_task( $migrate_task_obj->get_id() );

		foreach ( InstaWP_taskmanager::get_task_backup_data( $migrate_task_obj->get_id() ) as $key => $data ) {

			$backup_status    = InstaWP_Setting::get_args_option( 'backup_status', $data );
			$backup_progress  = (int) InstaWP_Setting::get_args_option( 'backup_progress', $data );
			$temp_folder_path = isset( $data['path'] ) && isset( $data['prefix'] ) ? $data['path'] . 'temp-' . $data['prefix'] : '';

			if ( 'completed' == $backup_status ) {

				$is_delete_files_or_folder = false;

				if ( isset( $data['sql_file_name'] ) && is_file( $data['sql_file_name'] ) && file_exists( $data['sql_file_name'] ) ) {
					@unlink( $data['sql_file_name'] );

					$is_delete_files_or_folder = true;
				}

				if ( is_dir( $temp_folder_path ) ) {
					@rmdir( $temp_folder_path );

					$is_delete_files_or_folder = true;
				}

				if ( $is_delete_files_or_folder ) {
					$migrate_task['options']['backup_options']['backup'][ $key ]['backup_progress'] = $backup_progress + round( 100 / 5 );
				}

				InstaWP_taskmanager::update_task( $migrate_task );
			}
		}
	}
}


if ( ! function_exists( 'instawp_update_migration_status' ) ) {
	/**
	 * @param $migrate_id
	 * @param $part_id
	 * @param $args
	 *
	 * @return array
	 */
	function instawp_update_migration_status( $migrate_id = '', $part_id = '', $args = array() ) {

		if ( empty( $migrate_id ) || $migrate_id == 0 || empty( $part_id ) || $part_id == 0 ) {
			return array( 'success' => false, 'message' => esc_html__( 'Invalid migrate or part ID', 'instawp-connect' ) );
		}

		$defaults    = array(
			'type'     => 'restore',
			'progress' => 100,
			'message'  => esc_html__( 'Restore completed for this part', 'instawp-connect' ),
			'status'   => 'completed'
		);
		$status_args = wp_parse_args( $args, $defaults );

		return InstaWP_Curl::do_curl( "migrates/{$migrate_id}/parts/{$part_id}", $status_args, array(), 'patch' );
	}
}


if ( ! function_exists( 'instawp_upload_to_cloud' ) ) {
	/**
	 * Upload file to presigned url
	 *
	 * @param $cloud_url
	 * @param $local_file
	 * @param $args
	 *
	 * @return bool
	 */
	function instawp_upload_to_cloud( $cloud_url = '', $local_file = '', $args = array() ) {

		if ( empty( $cloud_url ) || empty( $local_file ) ) {
			return false;
		}

		$useragent    = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
		$default_args = array(
			'method'     => 'PUT',
			'body'       => file_get_contents( $local_file ),
			'timeout'    => 0,
			'decompress' => false,
			'stream'     => false,
			'filename'   => '',
			'user-agent' => $useragent,
			'headers'    => array(
				'Content-Type' => 'multipart/form-data'
			),
			'upload'     => true
		);
		$upload_args  = wp_parse_args( $args, $default_args );

		for ( $i = 0; $i < INSTAWP_REMOTE_CONNECT_RETRY_TIMES; $i ++ ) {

			$WP_Http_Curl = new WP_Http_Curl();
			$response     = $WP_Http_Curl->request( $cloud_url, $upload_args );

			if ( isset( $response['response']['code'] ) && 200 == $response['response']['code'] ) {
				return true;
			}
		}

		return false;
	}
}


if ( ! function_exists( 'instawp_get_upload_files' ) ) {
	/**
	 * Get files as array that will be uploaded
	 *
	 * @param $data
	 *
	 * @return array
	 */
	function instawp_get_upload_files( $data = array() ) {

		$files_path     = InstaWP_Setting::get_args_option( 'path', $data );
		$zip_files_path = array();

		foreach ( InstaWP_Setting::get_args_option( 'zip_files', $data, array() ) as $zip_file ) {

			$filename    = InstaWP_Setting::get_args_option( 'file_name', $zip_file );
			$part_size   = InstaWP_Setting::get_args_option( 'size', $zip_file );
			$part_number = $_COOKIE['part_number'] ?? 1;

			if ( ! empty( $filename ) && ! empty( $part_size ) ) {
				$zip_files_path[] = array(
					'filename'      => $files_path . $filename,
					'part_size'     => $part_size,
					'content_type'  => 'file',
					'source_status' => 'pending',
					'part_number'   => ++ $part_number,
				);

				setcookie( 'part_number', $part_number );
			}
		}

		return $zip_files_path;
	}
}


if ( ! function_exists( 'instawp_get_response_progresses' ) ) {
	/**
	 * Return response with progresses
	 *
	 * @param $migrate_task_id
	 * @param $migrate_id
	 * @param $response
	 * @param $args
	 *
	 * @return array|mixed
	 */
	function instawp_get_response_progresses( $migrate_task_id, $migrate_id, $response = array(), $args = array() ) {

		foreach ( InstaWP_taskmanager::get_task_backup_data( $migrate_task_id ) as $data ) {

			$backup_progress = (int) InstaWP_Setting::get_args_option( 'backup_progress', $data );
			$upload_progress = (int) InstaWP_Setting::get_args_option( 'upload_progress', $data );

			$response['backup']['progress'] = (int) $response['backup']['progress'] + $backup_progress;
			$response['upload']['progress'] = (int) $response['upload']['progress'] + $upload_progress;
		}

		if ( $response['backup']['progress'] >= 100 && $response['upload']['progress'] >= 100 ) {

			$overall_migration_progress        = instawp_get_overall_migration_progress( $migrate_id );
			$response['migrate']['progress']   = $overall_migration_progress;
			$response['migrate']['migrate_id'] = $migrate_id;

			if ( $overall_migration_progress == 100 && ! empty( $migration_site_detail = instawp_get_migration_site_detail( $migrate_id ) ) ) {

				$response['site_detail'] = $migration_site_detail;
				$response['status']      = 'completed';

				instawp_staging_insert_site( array(
					'task_id'         => $migrate_task_id,
					'connect_id'      => InstaWP_Setting::get_args_option( 'id', $migration_site_detail ),
					'site_name'       => str_replace( array( 'https://', 'http://' ), '', InstaWP_Setting::get_args_option( 'url', $migration_site_detail ) ),
					'site_url'        => InstaWP_Setting::get_args_option( 'url', $migration_site_detail ),
					'admin_email'     => InstaWP_Setting::get_args_option( 'wp_admin_email', $migration_site_detail ),
					'username'        => InstaWP_Setting::get_args_option( 'wp_username', $migration_site_detail ),
					'password'        => InstaWP_Setting::get_args_option( 'wp_password', $migration_site_detail ),
					'auto_login_hash' => InstaWP_Setting::get_args_option( 'auto_login_hash', $migration_site_detail ),
				) );

				if ( false !== InstaWP_Setting::get_args_option( 'delete_task', $args, true ) ) {
					InstaWP_taskmanager::delete_task( $migrate_task_id );
				}
			}
		}


		if ( true === InstaWP_Setting::get_args_option( 'generate_part_urls', $args, false ) ) {

			foreach ( InstaWP_taskmanager::get_task_backup_data( $migrate_task_id ) as $data ) {
				foreach ( InstaWP_Setting::get_args_option( 'zip_files_path', $data, array() ) as $zip_file ) {

					$part_id  = InstaWP_Setting::get_args_option( 'part_id', $zip_file );
					$part_url = InstaWP_Setting::get_args_option( 'part_url', $zip_file );

					if ( empty( $part_id ) || $part_id == 0 ) {
						continue;
					}

					$response['part_urls'][] = array(
						'part_url' => site_url( 'wp-content/' . INSTAWP_DEFAULT_BACKUP_DIR . '/' . $part_url ),
						'part_id'  => $part_id,
					);
				}
			}
		}

		return $response;
	}
}