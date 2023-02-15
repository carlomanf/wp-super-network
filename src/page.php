<?php
/**
 * Admin page class.
 */
namespace WP_Super_Network;

abstract class Page
{
	private $title;
	private $menu;
	private $capability;
	private $slug;
	private $description;
	private $priority;
	private $submenu;
	private $icon;
	private $sections;

	public function __construct( $title, $menu, $capability, $slug, $description, $priority, $submenu, $icon )
	{
		$this->title = $title;
		$this->menu = $menu;
		$this->capability = $capability;
		$this->slug = $slug;
		$this->description = $description;
		$this->priority = $priority;
		$this->submenu = $submenu;
		$this->icon = $icon;

		$this->sections = array();
	}

	public function callback()
	{
		echo '<div class="wrap">';
		printf( '<h1 class="wp-heading-inline">%s</h1>', $this->title );
		echo $this->description;
		$this->callback_inner();
		echo '</div>';
	}

	protected abstract function callback_inner();

	public function add( $section )
	{
		$this->sections[] = $section;
	}

	public function register( $network )
	{
		$hook = $network ? 'network_admin_menu' : 'admin_menu';
        add_action( $hook, array( $this, 'menu' ), $this->priority );
	}

	public function slug()
	{
		return $this->slug;
	}

	public function sections()
	{
		return $this->sections;
	}

	public function menu()
	{
		if ( $this->submenu )
			add_submenu_page( $this->submenu, $this->title, $this->menu, $this->capability, $this->slug, array( $this, 'callback' ) );
		else
			add_menu_page( $this->title, $this->menu, $this->capability, $this->slug, array( $this, 'callback' ), $this->icon );
	}
}
