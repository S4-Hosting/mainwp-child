<?php
/**
 * MainWP Client Report
 */
namespace MainWP\Child;

/**
 * Class MainWP_Client_Report
 *
 * @package MainWP\Child
 */
class MainWP_Client_Report extends MainWP_Client_Report_Base {

	/**
	 * @static
	 * @var null Holds the Public static instance of MainWP_Client_Report.
	 */
public static $instance = null;

	/**
	 * Get Class Name.
	 *
	 * @return string
	 */
public static function get_class_name() {
	return __CLASS__;
}

	/**
	 * Create a public static instance of MainWP_Client_Report|MainWP_Client_Report_Base|null.
	 *
	 * @return MainWP_Client_Report|MainWP_Client_Report_Base|null
	 */
public static function instance() {
	if ( null === self::$instance ) {
		self::$instance = new self();
	}

	return self::$instance;
}

	/**
	 * MainWP_Client_Report constructor.
	 */
public function __construct() {
	add_filter( 'wp_mainwp_stream_current_agent', array( $this, 'current_agent' ), 10, 1 );
}

	/**
	 * Initiate Client report
	 */
public function init() {
	add_filter( 'mainwp_site_sync_others_data', array( $this, 'sync_others_data' ), 10, 2 );
	add_action( 'mainwp_child_log', array( self::get_class_name(), 'do_reports_log' ) );
}

	/**
	 * Get current user agent.
	 *
	 * @param string $agent User agent.
	 * @return string $agent Current user agent.
	 *
	 * @deprecated Unused element.
	 */
public function current_agent( $agent ) {
	if ( isset( $_POST['function'] ) && isset( $_POST['mainwpsignature'] ) ) {
		$agent = '';
	}
	return $agent;
}

	/**
	 * Sync others data.
	 *
	 * @param array $information Holder for returned data.
	 * @param array $data Data to sync.
	 * @return array $information Synced data.
	 */
public function sync_others_data( $information, $data = array() ) {
	if ( isset( $data['syncClientReportData'] ) && $data['syncClientReportData'] ) {
		$creport_sync_data = array();
		$firsttime         = get_option( 'mainwp_creport_first_time_activated' );
		if ( false !== $firsttime ) {
			$creport_sync_data['firsttime_activated'] = $firsttime;
		}
		if ( ! empty( $creport_sync_data ) ) {
			$information['syncClientReportData'] = $creport_sync_data;
		}
	}
	return $information;
}

	/**
	 * Create reports log file.
	 *
	 * @param string $ext File extension.
	 *
	 * @deprecated Unused element.
	 */
public static function do_reports_log( $ext = '' ) {
	switch ( $ext ) {
		case 'backupbuddy':
			\MainWP_Child_Back_Up_Buddy::instance()->do_reports_log( $ext );
			break;
		case 'backupwordpress':
			\MainWP_Child_Back_Up_WordPress::instance()->do_reports_log( $ext );
			break;
		case 'backwpup':
			\MainWP_Child_Back_WP_Up::instance()->do_reports_log( $ext );
			break;
		case 'wordfence':
			\MainWP_Child_Wordfence::instance()->do_reports_log( $ext );
			break;
		case 'wptimecapsule':
			\MainWP_Child_Timecapsule::instance()->do_reports_log( $ext );
			break;
	}
}

	/**
	 * Actions: save_sucuri_stream, save_backup_stream, get_stream, set_showhide.
	 */
public function action() {

	$information = array();

	if ( ! function_exists( '\wp_mainwp_stream_get_instance' ) ) {
		$information['error'] = __( 'No MainWP Child Reports plugin installed.', 'mainwp-child' );
		MainWP_Helper::write( $information );
	}

	if ( isset( $_POST['mwp_action'] ) ) {
		switch ( $_POST['mwp_action'] ) {
			case 'save_sucuri_stream':
				$information = $this->save_sucuri_stream();
				break;
			case 'save_backup_stream':
				$information = $this->save_backup_stream();
				break;
			case 'get_stream':
				$information = $this->get_stream();
				break;
			case 'set_showhide':
				$information = $this->set_showhide();
				break;
		}
	}
	MainWP_Helper::write( $information );
}

	/**
	 * Save sucuri stream.
	 *
	 * @return bool true|false.
	 */
public function save_sucuri_stream() {
	$scan_data = isset( $_POST['scan_data'] ) ? $_POST['scan_data'] : '';
	do_action( 'mainwp_reports_sucuri_scan', $_POST['result'], $_POST['scan_status'], $scan_data, isset( $_POST['scan_time'] ) ? $_POST['scan_time'] : 0 );
	return true;
}

	/**
	 * Save backup stream.
	 *
	 * @return bool true|false.
	 */
public function save_backup_stream() {
	do_action( 'mainwp_backup', $_POST['destination'], $_POST['message'], $_POST['size'], $_POST['status'], $_POST['type'] );
	return true;
}

	/**
	 * Get stream.
	 *
	 * @return array $information Stream array.
	 */
public function get_stream() {

	$sections = isset( $_POST['sections'] ) ? maybe_unserialize( base64_decode( $_POST['sections'] ) ) : array(); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions -- base64_encode function is used for http encode compatible..
	if ( ! is_array( $sections ) ) {
		$sections = array();
	}

	$other_tokens = isset( $_POST['other_tokens'] ) ? maybe_unserialize( base64_decode( $_POST['other_tokens'] ) ) : array(); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions -- base64_encode function is used for http encode compatible..
	if ( ! is_array( $other_tokens ) ) {
		$other_tokens = array();
	}

	unset( $_POST['sections'] );
	unset( $_POST['other_tokens'] );

	$args    = $this->get_stream_get_params( $other_tokens, $sections );
	$records = \wp_mainwp_stream_get_instance()->db->query( $args );

	if ( ! is_array( $records ) ) {
		$records = array();
	}

	// fix invalid data, or skip records!
	$skip_records = array();

	// fix for incorrect posts created logs!
	// query created posts from WP posts data to simulate records logging for created posts.
	if ( isset( $_POST['direct_posts'] ) && ! empty( $_POST['direct_posts'] ) ) {
		$this->fix_logs_posts_created( $records, $skip_records );
	}

	$other_tokens_data = $this->get_stream_others_tokens( $records, $other_tokens, $skip_records );
	$sections_data     = $this->get_stream_sections_data( $records, $sections, $skip_records );

	$information = array(
		'other_tokens_data' => $other_tokens_data,
		'sections_data'     => $sections_data,
	);
	return $information;
}

	/**
	 * Set Branding Show/Hide.
	 *
	 * @return array $information Results array.
	 */
    public function set_showhide() {
        $hide = isset( $_POST['showhide'] ) && ( 'hide' === $_POST['showhide'] ) ? 'hide' : '';
        MainWP_Child_Branding::instance()->save_branding_options( 'hide_child_reports', $hide );
        $information['result'] = 'SUCCESS';

        return $information;
    }

	/**
	 * Initiate Client Reports.
	 */
    public function creport_init() {

	    $branding_opts = MainWP_Child_Branding::instance()->get_branding_options();
	    $hide_nag      = false;

        if ( isset( $branding_opts['hide_child_reports'] ) && 'hide' == $branding_opts['hide_child_reports'] ) {
            add_filter( 'all_plugins', array( $this, 'creport_branding_plugin' ) );
            add_action( 'admin_menu', array( $this, 'creport_remove_menu' ) );
            $hide_nag = true;
        }

        if ( ! $hide_nag ) {
            // check child branding settings!
            if ( MainWP_Child_Branding::instance()->is_branding() ) {
                $hide_nag = true;
            }
        }

        if ( $hide_nag ) {
            add_filter( 'site_transient_update_plugins', array( &$this, 'remove_update_nag' ) );
            add_filter( 'mainwp_child_hide_update_notice', array( &$this, 'hide_update_notice' ) );
        }
    }
    
    /**
     * Hide update notice.
     *
     * @param $slugs Slugs to hide.
     * @return array Slugs array.
     *
     * @deprecated Unused element.
     */
    public function hide_update_notice( $slugs ) {

		$slugs[] = 'mainwp-child-reports/mainwp-child-reports.php';
		return $slugs;
	}

    /**
     * Remove update nag.
     *
     * @param string $value Value to remove.
     * @return string Response.
     *
     * @deprecated Unused element.
     */
    public function remove_update_nag( $value ) {

		if ( isset( $_POST['mainwpsignature'] ) ) {
			return $value;
		}

		if ( ! MainWP_Helper::is_updates_screen() ) {
			return $value;
		}

		if ( isset( $value->response['mainwp-child-reports/mainwp-child-reports.php'] ) ) {
			unset( $value->response['mainwp-child-reports/mainwp-child-reports.php'] );
		}

		return $value;
	}

	/**
	 * Client Reports Branding plugin.
	 *
	 * @param array $plugins Plugins array.
	 * @return array Plugins array.
	 *
	 * @deprecated Unused element.
	 */
	public function creport_branding_plugin( $plugins ) {
		foreach ( $plugins as $key => $value ) {
			$plugin_slug = basename( $key, '.php' );
			if ( 'mainwp-child-reports' === $plugin_slug ) {
				unset( $plugins[ $key ] );
			}
		}
		return $plugins;
	}

	/**
	 * Client Remove Menu.
	 *
	 * @deprecated Unused element.
	 */
	public function creport_remove_menu() {
		remove_menu_page( 'mainwp_wp_stream' );
	}
}
