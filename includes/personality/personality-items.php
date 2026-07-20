<?php
/**
 * Personality item catalog functions.
 *
 * @package MP_Ukagaka
 * @subpackage Personality
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

/**
 * Load and validate the current personality item catalog.
 *
 * @param string|null $personality_id Personality ID, or null for current.
 * @return array Validated catalog.
 */
function mpu_load_personality_items( $personality_id = null ) {
	if ( null === $personality_id ) {
		$personality_id = mpu_get_current_personality_id();
	} else {
		$personality_id = mpu_sanitize_personality_id( $personality_id );
		if ( empty( $personality_id ) ) {
			return array();
		}
	}

	$path    = mpu_get_personalities_dir() . '/' . $personality_id . '/items.json';
	$catalog = mpu_load_json_file( $path );
	if ( ! is_array( $catalog ) || empty( $catalog['items'] ) || ! is_array( $catalog['items'] ) ) {
		return array();
	}

	return mpu_normalize_personality_items_catalog( $catalog );
}

/**
 * Validate and sanitize a decoded personality item catalog.
 *
 * @param array $catalog Decoded item catalog.
 * @return array Validated catalog.
 */
function mpu_normalize_personality_items_catalog( array $catalog ) {
	$items = array();
	foreach ( $catalog['items'] ?? array() as $item ) {
		if ( ! is_array( $item ) ) {
			continue;
		}

		$raw_id   = isset( $item['id'] ) ? (string) $item['id'] : '';
		$raw_kind = isset( $item['kind'] ) ? (string) $item['kind'] : '';
		$id       = sanitize_key( $raw_id );
		$kind     = sanitize_key( $raw_kind );
		$name     = isset( $item['name'] ) ? sanitize_text_field( $item['name'] ) : '';
		$image    = isset( $item['image'] ) ? (string) $item['image'] : '';
		$prompt   = isset( $item['prompt'] ) ? sanitize_textarea_field( $item['prompt'] ) : '';

		if (
			! preg_match( '/\A[a-z_][a-z0-9_]*\z/', $raw_id )
			|| ! in_array( $raw_kind, array( 'food', 'gift' ), true )
			|| '' === $name
			|| '' === $prompt
			|| ! preg_match( '/\A[a-z0-9_-]+\.(png|webp)\z/', $image )
		) {
			continue;
		}

		$reactions = array();
		if ( ! empty( $item['reactions'] ) && is_array( $item['reactions'] ) ) {
			foreach ( $item['reactions'] as $category ) {
				$category = sanitize_key( (string) $category );
				if ( '' !== $category ) {
					$reactions[] = $category;
				}
			}
			$reactions = array_values( array_unique( $reactions ) );
		}

		$variants = array();
		if ( ! empty( $item['variants'] ) && is_array( $item['variants'] ) ) {
			foreach ( $item['variants'] as $variant ) {
				if ( ! is_string( $variant ) ) {
					continue;
				}
				$variant = sanitize_text_field( $variant );
				if ( '' !== $variant ) {
					$variants[] = $variant;
				}
			}
			$variants = array_values( array_unique( $variants ) );
		}

		$items[] = array(
			'id'        => $id,
			'kind'      => $kind,
			'name'      => $name,
			'image'     => $image,
			'favorite'  => ! empty( $item['favorite'] ),
			'prompt'    => $prompt,
			'reactions' => $reactions,
			'variants'  => $variants,
		);
	}

	return array(
		'version'           => isset( $catalog['version'] ) ? sanitize_text_field( $catalog['version'] ) : '',
		'items_base_folder' => 'items',
		'items'             => $items,
	);
}

/**
 * Get one item from a personality catalog.
 *
 * @param string      $id Item ID.
 * @param string|null $personality_id Personality ID, or null for current.
 * @return array|false Item data, or false if not found.
 */
function mpu_get_personality_item( $id, $personality_id = null ) {
	$id      = sanitize_key( $id );
	$catalog = mpu_load_personality_items( $personality_id );

	foreach ( $catalog['items'] ?? array() as $item ) {
		if ( $id === $item['id'] ) {
			return $item;
		}
	}

	return false;
}

/**
 * Get all available item IDs for a personality.
 *
 * @param string|null $personality_id Personality ID, or null for current.
 * @return array Item IDs.
 */
function mpu_get_personality_item_ids( $personality_id = null ) {
	$catalog = mpu_load_personality_items( $personality_id );
	return array_values( array_column( $catalog['items'] ?? array(), 'id' ) );
}

/**
 * Collect the usable reaction prompt pool for an item.
 *
 * Merges the prompt lines of the given prompts.json categories in order,
 * dropping non-string and blank entries (malformed-JSON guard).
 *
 * @param array $reactions    Reaction category names referenced by an item.
 * @param array $prompts_data Loaded prompts.json data (category => lines[]).
 * @return array Flat list of usable prompt strings.
 */
function mpu_collect_item_reaction_pool( array $reactions, array $prompts_data ) {
	$pool = array();
	foreach ( $reactions as $category ) {
		if ( empty( $prompts_data[ $category ] ) || ! is_array( $prompts_data[ $category ] ) ) {
			continue;
		}
		foreach ( $prompts_data[ $category ] as $line ) {
			if ( is_string( $line ) && '' !== trim( $line ) ) {
				$pool[] = $line;
			}
		}
	}

	return $pool;
}
