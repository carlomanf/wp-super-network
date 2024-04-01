<?php
/**
 * Blog suggestion class.
 */
namespace WP_Super_Network;

class Suggestion
{
	/**
	 * First blog that was suggested, or null if no blog suggested.
	 *
	 * @since 1.3.0
	 * @var WP_Super_Network\Blog|null
	 */
	private $first_suggestion = null;

	/**
	 * Whether the `suggest_blog` method has never been called.
	 *
	 * @since 1.3.0
	 * @var bool
	 */
	private $never_suggested = true;

	/**
	 * Whether the `suggest_blog` method has never returned `false` before.
	 *
	 * @since 1.3.0
	 * @var bool
	 */
	private $never_failed = true;

	/**
	 * Sets the `$first_suggestion` variable to the first suggestion and checks if subsequent suggestions match.
	 *
	 * @since 1.3.0
	 *
	 * @param WP_Super_Network\Blog|null $suggestion Suggested blog.
	 *
	 * @return bool Returns true if all suggestions matched, or false if any suggestion did not match.
	 */
	public function suggest_blog( $suggestion )
	{
		if ( $this->never_failed )
		{
			if ( $this->never_suggested )
			{
				$this->never_suggested = false;
				$this->first_suggestion = $suggestion;
				return true;
			}
			else
			{
				$this->never_failed = !isset( $this->first_suggestion ) && !isset( $suggestion ) || isset( $this->first_suggestion ) && isset( $suggestion ) && $suggestion->id === $this->first_suggestion->id;
				return $this->never_failed;
			}
		}
		else
		{
			return false;
		}
	}

	/**
	 * Returns the blog that was suggested, or null if no blog suggested.
	 *
	 * @since 1.3.0
	 *
	 * @return WP_Super_Network\Blog|null Suggested blog.
	 */
	public function get()
	{
		return $this->never_failed ? $this->first_suggestion : null;
	}

	/**
	 * Whether the `suggest_blog` method has never been called.
	 *
	 * @since 1.3.0
	 *
	 * @return bool Whether the suggestion is fresh.
	 */
	public function fresh()
	{
		return $this->never_suggested;
	}
}
