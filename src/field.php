<?php
/**
 * Settings field class.
 */
namespace WP_Super_Network;

class Settings_Field
{
	private $database;
	private $id;
	private $type;
	private $title;
	private $label;
	private $description;

	public function __construct( $database, $id, $type, $title, $label = '', $description = '' )
	{
		$this->database = $database;
		$this->id = $id;
		$this->type = $type;
		$this->title = $title;
		$this->label = $label;
		$this->description = $description;
	}

	public function database()
	{
		return $this->database;
	}

	public function id()
	{
		return $this->id;
	}

	public function type()
	{
		return $this->type;
	}

	public function title()
	{
		return $this->title;
	}

	public function label()
	{
		return $this->label;
	}

	public function description()
	{
		return $this->description;
	}

	/**
	 * Output a settings field or fieldset
	 */
	public function callback()
	{
		extract(
			apply_filters( 'supernetwork_settings_field_args',
				array(
					'setting' => $this->database,
					'field' => $this->id,
					'type' => $this->type,
					'labels' => $this->label,
					'description' => $this->description,
					'filters' => array( 'esc_textarea' ),
					'rows' => 8
				),
			$this )
		);

		$options = get_option( $setting );

		if ( $type === 'select' && is_array( $labels ) )
		{
			// Format for the name and ID attributes
			$name = sprintf( '%s[%s]', $setting, $field );

			printf( '<select id="%1$s" name="%1$s">', $name );

			$option = empty( $options[ $field ] ) ? '' : $options[ $field ];

			foreach ( $labels as $key => $label )
			{
				$selected = selected( $key, $option, false );
				printf( '<option value="%s"%s>%s</option>', $key, $selected, $label );
			}
		
			echo '</select>';

			if ( !empty( $description ) )
				printf( '<p class="description">%s</p>', $description );

			return;
		}

		if ( is_array( $labels ) )
		{
			$fields = $labels;
			echo '<fieldset>';
		}
		else
		{
			$fields = array( $labels );
		}

		foreach ( $fields as $key => $label )
		{
			$key = sprintf( $field, $key );

			if ( isset( $did_one ) && true === $did_one )
				echo '<br>';

			$did_one = true;

			// Format for the name and ID attributes
			$name = sprintf( '%s[%s]', $setting, $key );

			// Handle checkboxes
			if ( 'checkbox' === $type )
			{
				$checked = checked( true, isset( $options[ $key ] ), false );

				if ( !empty( $description ) && !is_array( $labels ) )
					echo '<fieldset>';

				printf( '<label for="%1$s"><input type="%2$s" id="%1$s" name="%1$s" value="1"%3$s> %4$s</label>', $name, $type, $checked, $label );

				if ( !empty( $description ) && !is_array( $labels ) )
					printf( '<p class="description">%s</p></fieldset>', $description );
			}
			else
			{
				// Add and apply the filters
				if ( !empty( $filters ) )
					foreach( $filters as $filter )
						add_filter( $name, $filter );

				$value = isset( $options[ $key ] ) ? apply_filters( $name, $options[ $key ] ) : '';

				// Handle single line text fields
				if ( 'text' === $type )
				{
					if ( !empty( $label ) && is_array( $labels ) )
						printf( '<label for="%s">', $name );

					printf( '<input class="regular-text" type="%2$s" id="%1$s" name="%1$s" value="%3$s">', $name, $type, $value );

					if ( !empty( $label ) && is_array( $labels ) )
						printf( ' %s</label>', $label );
				}

				// Handle rich text editors
				if ( 'editor' === $type )
					wp_editor( $value, sprintf( '%s_%s', $setting, $key ), array( 'textarea_name' => $name, 'textarea_rows' => $rows ) );

				// Add the description if there's one
				if ( !empty( $label ) && !is_array( $labels ) )
					printf( '<p class="description">%s</p>', $label );
			}
		}

		if ( is_array( $labels ) )
		{
			if ( !empty( $description ) )
				printf( '<p class="description">%s</p>', $description );

			echo '</fieldset>';
		}
	}
}
