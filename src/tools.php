<?php
/**
 * Tools page class.
 */
namespace WP_Super_Network;

class Tools_Page extends Page
{
	private $network;

	public function __construct( $network, $title, $menu, $capability, $slug, $description, $priority = 10, $submenu = false, $icon = '' )
	{
		$this->network = $network;
		parent::__construct( $title, $menu, $capability, $slug, $description, $priority, $submenu, $icon );
	}

	public function register( $network = false )
	{
		parent::register( $this->network );
	}

	protected function callback_inner()
	{
        foreach ( $this->sections() as $section )
		{
			echo '<h2>' . $section->title() . '</h2>';
			$section->callback();

			$fields = $section->fields();

			if ( !empty( $fields ) )
			{
				echo '<table class="form-table" role="presentation"><tbody>';

				foreach ( $fields as $field )
				{
					if ( $field->type() == 'checkbox' || is_array( $field->label() ) )
					{
						$label = $field->title();
					}
					else
					{
						$label = '<label for="' . $field->database() . '[' . $field->id() . ']">' . $field->title() . '</label>';
					}

					echo '<tr><th scope="row">';
					echo $label;
					echo '</th><td>';
					$field->callback();
					echo '</td></tr>';
				}

				echo '</tbody></table>';
			}
		}
	}
}
