<?php
/**
 * Settings section class.
 */
namespace WP_Super_Network;

class Settings_Section
{
	private $id;
	private $title;
	private $description;
	private $fields;

	public function __construct( $id, $title, $description = '' )
	{
		$this->id = $id;
		$this->title = $title;
		$this->description = $description;

		$this->fields = array();
	}

	public function id()
	{
		return $this->id;
	}

	public function title()
	{
		return $this->title;
	}

	public function description()
	{
		return $this->description;
	}

	public function fields()
	{
		return $this->fields;
	}

	public function callback()
	{
		if ( !empty( $this->description ) )
			echo '<p>' . $this->description . '</p>';
	}

	public function add( $field )
	{
		$this->fields[] = $field;
	}
}