<?php
/**
 * Class: Boldgrid_Framework_Activate
 *
 * This class contians code that will run on activation
 * of a theme that utilizes the BoldGrid Theme Framework.
 *
 * @since 1.0.0
 * @package Boldgrid_Framework
 * @subpackage Boldgrid_Framework_Activate
 * @author BoldGrid <support@boldgrid.com>
 * @link https://boldgrid.com
 */

/**
 * Class: Boldgrid_Framework_Activate
 *
 * This class contians code that will run on activation
 * of a theme that utilizes the BoldGrid Theme Framework.
 *
 * @since 1.0.0
 */
class Boldgrid_Framework_Activate {

	/**
	 * The BoldGrid Theme Framework configurations.
	 *
	 * @since 1.0.0
	 * @access protected
	 * @var string $configs The BoldGrid Theme Framework configurations.
	 */
	protected $configs;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @param string $configs The BoldGrid Theme Framework configurations.
	 * @since 1.0.0
	 */
	public function __construct( $configs ) {
		$this->configs = $configs;
		$this->widgets = new Boldgrid_Framework_Widgets( $this->configs );
		$this->menus   = new Boldgrid_Framework_Menu( $this->configs );
		$this->scss    = new Boldgrid_Framework_SCSS( $this->configs );
		$this->color   = new Boldgrid_Framework_Customizer_Colors( $this->configs );
	}

	/**
	 * Reset The Boldgrid Theme Framework
	 * Removing any menu locations and widget locations
	 *
	 * @since 1.0.0
	 */
	public function reset() {
		$this->menus->reset_nav_locations();

		// Delete Option indicating that the framework needs to be setup.
		delete_option( 'boldgrid_framework_init' );

		// Do action for 3rd party.
		do_action( 'boldgrid_theme_reset' );
	}

	/**
	 * Activate Boldgrid theme framework
	 *
	 * @since 1.0.0
	 */
	public function do_activate() {
		if ( $this->menus->is_user_child() ) {
			return;
		}

		// Before running the activation, run deactivate just to be sure.
		$this->reset();

		$this->widgets->empty_widget_areas();

		// Then update the menu_check option to make sure this code only runs once.
		update_option( 'boldgrid_framework_init', true );

		// Set Color Palettes.
		$option = 'theme_mods_' . get_stylesheet();
		$this->set_palette( $option );

		// Do action for 3rd party.
		do_action( 'boldgrid_theme_activate' );
	}

	/**
	 * Remove any theme mods that were transferred to this theme
	 *
	 * @since 1.0.0
	 */
	public function undo_theme_mod_transfer() {
		$accept = true;
		$data = wp_unslash( $_POST['data']['accept'] );

		if ( ! empty( $data ) ) {
			$accept = $data;
		}
		if ( false === $accept ) {
			$theme_mods = get_option( 'theme_mods_' . get_stylesheet(), array() );
			if ( ! empty( $theme_mods['transferred_theme_mods'] ) && is_array( $theme_mods['transferred_theme_mods'] ) ) {
				foreach ( $theme_mods['transferred_theme_mods'] as $theme_mod ) {
					unset( $theme_mods[ $theme_mod ] );
				}
				$theme_mods['transferred_theme_mods'] = array();
			}

			update_option( 'theme_mods_' . get_stylesheet(), $theme_mods );

			// Compile All SCSS again!
			$this->scss->force_update_css();
		} else {
			// If accepted, reset the array of theme mods.
			set_theme_mod( 'transferred_theme_mods', array() );
		}

		wp_die();
	}

	/**
	 * Check & set the default palette theme mod and compile the css
	 * for a user that has not selected a new palette and uses
	 * the theme's defined default palette.
	 *
	 * @since 1.1.7
	 */
	public function set_palette( $option ) {
		$options = get_option( $option, array() );

		// Check that colors are enabled and defaults exist.
		$enabled = $this->configs['customizer-options']['colors']['enabled'];
		$palette = $this->configs['customizer-options']['colors']['defaults'];

		// If there's not a palette set by user, then set it.
		if ( ! array_key_exists( 'boldgrid_color_palette', $options ) && $enabled && $palette ) {

			// Normalize default passed in palettes from configs to RGB.
			foreach ( $palette as $index => $settings ) {

				// Convert default colors to RGBs if alternate format was passed in configs.
				foreach ( $palette[ $index ]['colors'] as $color_index => $color ) {
					$palette[ $index ]['colors'][ $color_index ] = ariColor::newColor( $color )->toCSS( 'rgb' );
				}

				// Convert neutral color to RGB if alternate format was passed in configs.
				if ( isset( $palette[ $index ]['neutral-color'] ) ) {
					$palette[ $index ]['neutral-color'] = ariColor::newColor( $palette[ $index ]['neutral-color'] )->toCSS( 'rgb' );
				}
			}

			// Initizalize $theme_mod array.
			$theme_mod = array();
			// Get assigned default palette for category/theme.
			$default_palette = $this->color->get_simplified_external_palettes( $palette );
			// Reset to access without specifying palette format as it can change.
			$active_palette = reset( $default_palette );
			// Find acitve palette format.
			$format = $active_palette['format'];
			// Set the theme mod array values.
			$theme_mod['state'] = array(
				'active-palette' => $format,
				'active-palette-id' => $this->color->create_palette_id( $active_palette ),
				'palettes' => $default_palette,
				'saved_palettes' => array(),
			);
			// This is not needed for theme mod.
			unset( $theme_mod['state']['palettes'][ $format ]['default'] );
			// Encode to pass to JS.
			$encoded_theme_mod = wp_json_encode( $theme_mod );

			// Set the theme mods.
			$options['boldgrid_color_palette'] = $encoded_theme_mod;
			$options['boldgrid_palette_class'] = $format;
			// Update the theme mods.
			update_option( $option, $options );
		}
	}

	/**
	 * Override the tgm configurations based on active plugins.
	 *
	 * @since 1.5.4
	 *
	 * @param  array $configs  BGTFW Configs.
	 * @return array           BGTFW Configs.
	 */
	public function tgm_override( $configs ) {
		include_once( ABSPATH . 'wp-admin/includes/plugin.php' );

		foreach ( $configs['tgm']['renamed_plugins'] as $renamed ) {

			// Check for renamed plugins.
			$plugins = get_plugins();

			foreach ( $plugins as $plugin => $args ) {

				// Check if old name matches installed plugins.
				$name = strtolower( str_replace( '-', ' ', $renamed['old_name'] ) );
				if ( isset( $args['Name'] ) && ( strtolower( $args['Name'] === $name ) ) ) {

					// Check if plugin is active.
					if ( class_exists( str_replace( ' ', '_', ucwords( $name ) ) ) ) {

						// Remove recommended plugin from configs.
						$configs['tgm']['plugins'] = $this->remove_recommended_plugin( $configs, $renamed['new_name'] );
					}
				}
			}
		}

		return $configs;
	}

	/**
	 * Remove a reccomended plugin from the configs.
	 *
	 * @since 1.5.4
	 *
	 * @param  array $configs              BGTFW Configs.
	 * @param  array $disabled_plugin_name List of disabled names.
	 * @return array $configs              BGTFW Configs.
	 */
	public function remove_recommended_plugin( $configs, $disabled_plugin_name ) {
		$plugins = array();
		foreach ( $configs['tgm']['plugins'] as $plugin ) {
			if ( $disabled_plugin_name !== $plugin['slug'] ) {
				$plugins[] = $plugin;
			}
		}

		$configs['tgm']['plugins'] = $plugins;

		return $configs;
	}

	/**
	 * Register the required plugins for this theme.
	 *
	 * In this example, we register five plugins:
	 * - one included with the TGMPA library
	 * - two from an external source, one from an arbitrary source, one from a GitHub repository
	 * - two from the .org repo, where one demonstrates the use of the `is_callable` argument
	 *
	 * The variables passed to the `tgmpa()` function should be:
	 * - an array of plugin arrays;
	 * - optionally a configuration array.
	 * If you are not changing anything in the configuration array, you can remove the array and remove the
	 * variable from the function call: `tgmpa( $plugins );`.
	 * In that case, the TGMPA default settings will be used.
	 *
	 * This function is hooked into `tgmpa_register`, which is fired on the WP `init` action on priority 10.
	 */
	public function register_required_plugins() {

		/**
		 * Whether or not to register our configured tgmpa plugins.
		 *
		 * The theme may configure a set of base plugins (in tgm.config.php), and our starter content
		 * may configure an additional set of plugins (specific to that starter content).
		 *
		 * The main purpose of this filter is to prevent issues in which the theme and the starter
		 * content both configure the same plugins.
		 *
		 * For example, if the theme is requiring p&pb(version 1.7, stable, from the repo), yet the
		 * starter cotnent is requiring p&pb(version 1.8, rc, from external URL), the plugins from
		 * the starter content should take precedence from the plugins reuqired for the theme only.
		 *
		 * @since 2.0.0
		 */
		$register = apply_filters( 'bgtfw_register_tgmpa', true );

		if( $register ) {
			tgmpa( $this->configs['tgm']['plugins'], $this->configs['tgm']['configs'] );
		}
	}

	/**
	 * Perform tasks on deactivation.
	 *
	 * @since 1.5.10
	 */
	public function do_deactivate() {
		delete_site_transient( 'bg_license_data' );
	}
}
