<?php
/**
 * Settings page class.
 */
namespace WP_Super_Network;

class Settings_Page
{
	private $database;
	private $title;
	private $menu;
	private $capability;
	private $slug;
	private $description;
	private $priority;
	private $submenu;
	private $sections;

	public function __construct( $database, $title, $menu, $capability, $slug, $description, $priority = 10, $submenu = false )
	{
		$this->database = is_string( $database ) ? array( $database ) : $database;
		$this->title = $title;
		$this->menu = $menu;
		$this->capability = $capability;
		$this->slug = $slug;
		$this->description = $description;
		$this->priority = $priority;
		$this->submenu = $submenu;

		$this->sections = array();
	}

	public function database()
	{
		return $this->database;
	}

	public function callback()
	{
		echo '<div class="wrap">';
		printf( '<h2>%s</h2>', $this->title );
		echo '<form method="post" action="options.php">';
		echo $this->description;
		submit_button();
		do_action( 'supernetwork_page_content_' . $this->slug );
		settings_fields( $this->slug );
		do_settings_sections( $this->slug );
		submit_button();
		echo '</form></div>';
	}

	public function add( $section )
	{
		$this->sections[] = $section;
	}

	public function register()
	{
		add_action( 'admin_init', array( $this, 'init' ) );
		add_filter( 'admin_menu', array( $this, 'menu' ), $this->priority );
	}

	public function init()
	{
		foreach ( $this->sections as $section )
		{
			add_settings_section(
				$this->slug . '_' . $section->id(), // ID
				$section->title(), // title to be displayed
				array( $section, 'callback' ), // callback
				$this->slug // settings page to add to
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
					$this->slug, // settings page to add to
					$this->slug . '_' . $section->id() // section to add to
				);
			}
		}
	
		foreach ( $this->database as $database )
		register_setting( $this->slug, $database );
	}

	public function menu()
	{
		if ( $this->submenu )
			add_submenu_page( $this->submenu, $this->title, $this->menu, $this->capability, $this->slug, array( $this, 'callback' ) );
		else
			add_menu_page( $this->title, $this->menu, $this->capability, $this->slug, array( $this, 'callback' ) );
	}
}
