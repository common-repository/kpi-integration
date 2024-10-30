<?php
/**
 * Created by PhpStorm.
 * User: Leon
 * Date: 4/10/2018
 * Time: 3:05 PM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// admin area
final class kpiSettingsPage
{
	/**
	 * Holds the values to be used in the fields callbacks
	 */
	private $options;

	/**
	 * Start up
	 */
	public function __construct()
	{
		add_action( 'admin_menu', array( $this, 'add_plugin_page' ) );
		add_action( 'admin_init', array( $this, 'page_init' ) );
	}

	/**
	 * Add options page
	 */
	public function add_plugin_page()
	{
		// This page will be under "Settings"
		add_menu_page(
			'Settings Admin',
			'kpi settings',
			'manage_options',
			'kpi-settings-admin',
			array( $this, 'create_admin_page' )
		);
	}

	/**
	 * Options page callback
	 */
	public function create_admin_page()
	{
		// Set class property
		$this->options = get_option( 'kpi_opt_name' );
		?>
		<div class="wrap">
			<h1>kpi settings</h1>
			<form method="post" action="options.php">
				<?php
				// This prints out all hidden setting fields
				settings_fields( 'kpi_opt_group' );
				do_settings_sections( 'kpi-settings-admin' );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Register and add settings
	 */
	public function page_init()
	{
		register_setting(
			'kpi_opt_group', // Option group
			'kpi_opt_name', // Option name
			array( $this, 'sanitize' ) // Sanitize
		);

		add_settings_section(
			'setting_section_id', // ID
			'', // Title
			array( $this, 'print_section_info' ), // Callback
			'kpi-settings-admin' // Page
		);

		add_settings_field(
			'api_secret_key',
			'api_secret_key',
			array( $this, 'api_secret_key_callback' ),
			'kpi-settings-admin',
			'setting_section_id'
		);
	}

	/**
	 * Sanitize each setting field as needed
	 *
	 * @param array $input Contains all settings fields as array keys
	 */
	public function sanitize( $input )
	{
		$new_input = array();
		if( isset( $input['id_number'] ) )
			$new_input['id_number'] = absint( $input['id_number'] );

		if( isset( $input['api_secret_key'] ) )
			$new_input['api_secret_key'] = sanitize_text_field( $input['api_secret_key'] );

		return $new_input;
	}

	/**
	 * Print the Section text
	 */
	public function print_section_info()
	{
		print 'Enter your settings below:';
	}

	/**
	 * Get the settings option array and print one of its values
	 */
	public function api_secret_key_callback()
	{
		printf(
			'<input type="text" id="api_secret_key" name="kpi_opt_name[api_secret_key]" value="%s" />',
			isset( $this->options['api_secret_key'] ) ? esc_attr( $this->options['api_secret_key']) : ''
		);
	}
}