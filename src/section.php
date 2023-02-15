<?php
/**
 * Input section class.
 */
namespace WP_Super_Network;

class Input_Section
{
	private $id;
	private $title;
	private $description;
	private $callback;
	private $fields;

	public function __construct( $id, $title, $description = '', $callback = '' )
	{
		$this->id = $id;
		$this->title = $title;
		$this->description = $description;
		$this->callback = $callback;

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

		empty( $this->callback ) or call_user_func( $this->callback );
	}

	public function add( $field )
	{
		$this->fields[] = $field;
	}
}