<?php
/**
 * Settings page class.
 */
namespace WP_Super_Network;

class Settings_Page extends Page
{
	private $database;

	public function __construct( $database, $title, $menu, $capability, $slug, $description, $priority = 10, $submenu = false, $icon = '' )
	{
		$this->database = is_string( $database ) ? array( $database ) : $database;
		parent::__construct( $title, $menu, $capability, $slug, $description, $priority, $submenu, $icon );
	}

	public function database()
	{
		return $this->database;
	}

	protected function callback_inner()
	{
		echo '<form method="post" action="options.php">';
		submit_button();
		do_action( 'supernetwork_page_content_' . $this->slug() );
		settings_fields( $this->slug() );
		do_settings_sections( $this->slug() );
		submit_button();
		echo '</form>';
	}

	public function register( $network = false )
	{
		add_action( 'admin_init', array( $this, 'init' ) );
        parent::register( false );
	}

	public function init()
	{
		foreach ( $this->sections() as $section )
		{
			add_settings_section(
				$this->slug() . '_' . $section->id(), // ID
				$section->title(), // title to be displayed
				array( $section, 'callback' ), // callback
				$this->slug() // settings page to add to
			);

			foreach ( $section->fields() as $field )
			{
				if ( $field->type() == 'checkbox' || is_array( $field->label() ) )
				{
					$label = $field->title();
				}
				else
				{
					$label = '<label for="' . $field->database() . '[' . $field->id() . ']">' . $field->title() . '</label>';
				}
		
				add_settings_field(
					$field->id(), // ID
					$label, // label
					array( $field, 'callback' ), // callback
					$this->slug(), // settings page to add to
					$this->slug() . '_' . $section->id() // section to add to
				);
			}
		}
	
		foreach ( $this->database as $database )
		register_setting( $this->slug(), $database );
	}
}
