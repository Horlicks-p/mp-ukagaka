<?php
/**
 * Handwritten story catalog loading and validation.
 *
 * @package MP_Ukagaka
 * @subpackage Personality
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Load a catalog within the personality directory, without widening dialog reads.
 *
 * @param string $personality_id Resolved personality ID; never infer a fallback.
 * @return array|null Validated public catalog, or null when unavailable.
 */
function mpu_load_personality_stories( $personality_id ) {
	if ( ! is_string( $personality_id ) || '' === $personality_id || mpu_sanitize_personality_id( $personality_id ) !== $personality_id ) {
		return null;
	}
	$root = realpath( mpu_get_personalities_dir() );
	$path = realpath( mpu_get_personalities_dir() . '/' . $personality_id . '/stories.json' );
	if ( false === $root || false === $path || ! mpu_is_path_within_allowed_dir( $path, $root ) ) {
		return null;
	}
	return mpu_normalize_personality_stories( mpu_load_json_file( $path ), $personality_id );
}

/**
 * Validate raw author data and expose only supported story fields.
 *
 * @param mixed  $catalog        Decoded JSON.
 * @param string $personality_id Personality owning the emoji catalog.
 * @return array|null Public story catalog.
 */
function mpu_normalize_personality_stories( $catalog, $personality_id ) {
	if ( ! is_array( $catalog ) || 1 !== ( $catalog['format_version'] ?? null ) || true !== ( $catalog['enabled'] ?? null ) ) {
		return null;
	}
	$config = array_key_exists( 'config', $catalog ) ? $catalog['config'] : array();
	$pause  = is_array( $config ) && array_key_exists( 'step_pause', $config ) ? $config['step_pause'] : array();
	if ( ! is_array( $config ) || ! is_array( $pause ) || ! isset( $catalog['stories'] ) || ! is_array( $catalog['stories'] ) ) {
		return null;
	}
	if ( array_values( $catalog['stories'] ) !== $catalog['stories'] ) {
		return null;
	}
	$defaults = array(
		'start_chance'     => 0.05,
		'cooldown_minutes' => 360,
	);
	foreach ( $defaults as $key => $default ) {
		$value = array_key_exists( $key, $config ) ? $config[ $key ] : $default;
		$max   = 'start_chance' === $key ? 1 : 1440;
		if ( ( ! is_int( $value ) && ! is_float( $value ) ) || ! is_finite( (float) $value ) || $value < 0 || $value > $max ) {
			return null;
		}
		$config[ $key ] = $value;
	}
	$pause_defaults = array(
		'base_ms'     => 1200,
		'per_char_ms' => 50,
		'min_ms'      => 1500,
		'max_ms'      => 4000,
	);
	foreach ( $pause_defaults as $key => $default ) {
		$value = array_key_exists( $key, $pause ) ? $pause[ $key ] : $default;
		if ( ! is_int( $value ) || $value < 0 || $value > 10000 ) {
			return null;
		}
		$pause[ $key ] = $value;
	}
	if ( $pause['min_ms'] > $pause['max_ms'] ) {
		return null;
	}
	$supported = mpu_normalize_get_supported_tags( $personality_id );
	$stories   = array();
	$seen      = array();
	foreach ( $catalog['stories'] as $story ) {
		if ( ! is_array( $story ) || ! isset( $story['id'] ) || ! is_string( $story['id'] ) || ! preg_match( '/^[a-z][a-z0-9_-]{0,63}$/D', $story['id'] ) ) {
			continue;
		}
		if ( isset( $seen[ $story['id'] ] ) ) {
			return null;
		}
		$seen[ $story['id'] ] = true;
		if ( ! isset( $story['steps'] ) || ! is_array( $story['steps'] ) || count( $story['steps'] ) < 2 || count( $story['steps'] ) > 5 ) {
			continue;
		}
		if ( array_values( $story['steps'] ) !== $story['steps'] ) {
			continue;
		}
		$steps = array();
		foreach ( $story['steps'] as $step ) {
			if ( ! is_array( $step ) || ! isset( $step['text'] ) || ! is_string( $step['text'] ) || '' === trim( $step['text'] ) || mb_strlen( $step['text'], 'UTF-8' ) > 120 || false !== strpbrk( $step['text'], '<>&' ) ) {
				continue 2;
			}
			// Optional per-step override of the computed pause after this line, e.g. a beat
			// for the listener to react. Same bounds as step_pause; invalid drops the story.
			$step_pause = null;
			if ( array_key_exists( 'pause_ms', $step ) ) {
				if ( ! is_int( $step['pause_ms'] ) || $step['pause_ms'] < 0 || $step['pause_ms'] > 10000 ) {
					continue 2;
				}
				$step_pause = $step['pause_ms'];
			}
			$emotion = isset( $step['emotion'] ) && is_string( $step['emotion'] )
				? mpu_normalize_resolve_emotion_tag( $step['emotion'], $supported ) : null;
			$steps[] = array(
				'text'     => $step['text'],
				'emoji'    => null === $emotion ? null : $emotion . '.png',
				'pause_ms' => $step_pause,
			);
		}
		$stories[] = array(
			'id'    => $story['id'],
			'steps' => $steps,
		);
	}
	if ( empty( $stories ) ) {
		return null;
	}
	return array(
		'personality' => $personality_id,
		'config'      => array(
			'start_chance'     => $config['start_chance'],
			'cooldown_minutes' => $config['cooldown_minutes'],
			'step_pause'       => array_intersect_key( $pause, $pause_defaults ),
		),
		'stories'     => $stories,
	);
}
