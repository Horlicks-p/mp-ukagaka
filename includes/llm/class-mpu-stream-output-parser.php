<?php
/**
 * Streaming response parser for emotion tags and leading think blocks.
 *
 * @package MP_Ukagaka
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

/**
 * Streaming parser that converts provider deltas into normalized SSE events.
 */
class MPU_Stream_Output_Parser {
	/**
	 * Personality identifier used to resolve supported emotion tags.
	 *
	 * @var string|null
	 */
	private $personality_id;

	/**
	 * Response context used by the inner monologue policy.
	 *
	 * @var string
	 */
	private $context;

	/**
	 * Supported emotion tags for the active personality.
	 *
	 * @var array<int, string>
	 */
	private $supported_tags = array();

	/**
	 * Buffered provider text that has not been classified yet.
	 *
	 * @var string
	 */
	private $buffer = '';

	/**
	 * Buffered text inside the active think block.
	 *
	 * @var string
	 */
	private $think_buffer = '';

	/**
	 * Whether the parser is currently inside a think block.
	 *
	 * @var bool
	 */
	private $inside_think = false;

	/**
	 * Whether the active think block should emit think events.
	 *
	 * @var bool
	 */
	private $think_emit_enabled = false;

	/**
	 * Whether a thinking_start event has already been emitted.
	 *
	 * @var bool
	 */
	private $thinking_started = false;

	/**
	 * Whether visible response text has already started.
	 *
	 * @var bool
	 */
	private $main_output_started = false;

	/**
	 * Constructor.
	 *
	 * @param string|null $personality_id Personality identifier.
	 * @param string      $context        Response context.
	 */
	public function __construct( $personality_id = null, $context = 'chat' ) {
		$this->personality_id = $personality_id;
		$this->context        = is_string( $context ) && '' !== $context ? $context : 'chat';
		$this->supported_tags = function_exists( 'mpu_normalize_get_supported_tags' )
			? mpu_normalize_get_supported_tags( $personality_id )
			: array();
	}

	/**
	 * Feed one provider delta and return normalized SSE events.
	 *
	 * @param string $delta Provider delta text.
	 * @return array<int, array{event:string,data:array}>
	 */
	public function feed( $delta ) {
		if ( ! is_string( $delta ) || '' === $delta ) {
			return array();
		}

		$this->buffer .= $delta;
		return $this->drain_buffer( false );
	}

	/**
	 * Flush remaining buffered text at stream end.
	 *
	 * @return array<int, array{event:string,data:array}>
	 */
	public function flush() {
		return $this->drain_buffer( true );
	}

	/**
	 * Drain buffered text into parser events.
	 *
	 * @param bool $is_final Whether this is the final drain.
	 * @return array<int, array{event:string,data:array}>
	 */
	private function drain_buffer( $is_final ) {
		$events = array();

		while ( '' !== $this->buffer ) {
			if ( $this->inside_think ) {
				$progress = $this->drain_inside_think( $events, $is_final );
			} else {
				$progress = $this->drain_normal( $events, $is_final );
			}

			if ( ! $progress ) {
				break;
			}
		}

		if ( $is_final && $this->inside_think ) {
			if ( '' !== $this->buffer ) {
				$this->think_buffer .= $this->buffer;
				$this->buffer        = '';
			}
			$this->close_think( $events );
		} elseif ( $is_final && '' !== $this->buffer ) {
			$this->emit_delta( $events, $this->buffer );
			$this->buffer = '';
		}

		return $events;
	}

	/**
	 * Drain text while outside a think block.
	 *
	 * @param array<int, array{event:string,data:array}> $events   Event accumulator.
	 * @param bool                                       $is_final Whether this is the final drain.
	 * @return bool Whether progress was made.
	 */
	private function drain_normal( array &$events, $is_final ) {
		$lower = strtolower( $this->buffer );

		if ( ! $this->main_output_started ) {
			$trimmed     = ltrim( $this->buffer );
			$leading_len = strlen( $this->buffer ) - strlen( $trimmed );
			if ( $leading_len > 0 && ( $this->is_think_open_prefix( strtolower( $trimmed ) ) || null !== $this->match_open_think( strtolower( $trimmed ) ) ) ) {
				if ( '' === $trimmed && ! $is_final ) {
					return false;
				}
				$this->buffer = $trimmed;
				$lower        = strtolower( $this->buffer );
			}

			$open = $this->match_open_think( $lower );
			if ( null !== $open ) {
				$this->buffer = substr( $this->buffer, strlen( $open ) );
				$this->open_think( $events, true );
				return true;
			}

			if ( $this->is_think_open_prefix( $lower ) && ! $is_final ) {
				return false;
			}
		} else {
			$open = $this->match_open_think( $lower );
			if ( null !== $open ) {
				$this->warn( 'mid-response think block stripped from stream output' );
				$this->buffer = substr( $this->buffer, strlen( $open ) );
				$this->open_think( $events, false );
				return true;
			}
		}

		if ( 0 === strpos( $lower, '</think>' ) || 0 === strpos( $lower, '</thinking>' ) ) {
			$this->warn( 'orphan closing think tag emitted as plain text' );
			$tag = 0 === strpos( $lower, '</think>' ) ? '</think>' : '</thinking>';
			$this->emit_delta( $events, substr( $this->buffer, 0, strlen( $tag ) ) );
			$this->buffer = substr( $this->buffer, strlen( $tag ) );
			return true;
		}

		if ( '[' === $this->buffer[0] ) {
			return $this->drain_tag_candidate( $events, $is_final );
		}

		$next_special = $this->next_special_offset( $this->buffer );
		if ( false === $next_special ) {
			if ( ! $is_final && $this->could_be_partial_special( $this->buffer ) ) {
				$emit_len = max( 0, strlen( $this->buffer ) - 10 );
				if ( 0 === $emit_len ) {
					return false;
				}
				$this->emit_delta( $events, substr( $this->buffer, 0, $emit_len ) );
				$this->buffer = substr( $this->buffer, $emit_len );
				return true;
			}
			$this->emit_delta( $events, $this->buffer );
			$this->buffer = '';
			return true;
		}

		if ( 0 === $next_special ) {
			$this->emit_delta( $events, $this->buffer[0] );
			$this->buffer = substr( $this->buffer, 1 );
			return true;
		}

		$this->emit_delta( $events, substr( $this->buffer, 0, $next_special ) );
		$this->buffer = substr( $this->buffer, $next_special );
		return true;
	}

	/**
	 * Drain a bracketed candidate that may be an emotion tag.
	 *
	 * @param array<int, array{event:string,data:array}> $events   Event accumulator.
	 * @param bool                                       $is_final Whether this is the final drain.
	 * @return bool Whether progress was made.
	 */
	private function drain_tag_candidate( array &$events, $is_final ) {
		$close = strpos( $this->buffer, ']' );
		if ( false !== $close ) {
			$candidate = substr( $this->buffer, 0, $close + 1 );
			if ( preg_match( '/^\[([a-z][a-z0-9_-]{0,31})\]$/i', $candidate, $m ) ) {
				$tag = $this->resolve_supported_tag( $m[1] );
				if ( null !== $tag ) {
					$events[]     = array(
						'event' => 'emotion',
						'data'  => array(
							'tag'  => $tag,
							'file' => $tag . '.png',
						),
					);
					$this->buffer = substr( $this->buffer, $close + 1 );
					return true;
				}
			}
			$this->emit_delta( $events, $candidate );
			$this->buffer = substr( $this->buffer, $close + 1 );
			return true;
		}

		if ( $this->is_supported_tag_prefix( $this->buffer ) && ! $is_final ) {
			return false;
		}

		$this->emit_delta( $events, $this->buffer );
		$this->buffer = '';
		return true;
	}

	/**
	 * Drain text while inside a think block.
	 *
	 * @param array<int, array{event:string,data:array}> $events   Event accumulator.
	 * @param bool                                       $is_final Whether this is the final drain.
	 * @return bool Whether progress was made.
	 */
	private function drain_inside_think( array &$events, $is_final ) {
		$lower        = strtolower( $this->buffer );
		$pos_think    = strpos( $lower, '</think>' );
		$pos_thinking = strpos( $lower, '</thinking>' );
		$close_pos    = false;
		$close_len    = 0;

		if ( false !== $pos_think && ( false === $pos_thinking || $pos_think <= $pos_thinking ) ) {
			$close_pos = $pos_think;
			$close_len = strlen( '</think>' );
		} elseif ( false !== $pos_thinking ) {
			$close_pos = $pos_thinking;
			$close_len = strlen( '</thinking>' );
		}

		if ( false !== $close_pos ) {
			$this->append_think_text( $events, substr( $this->buffer, 0, $close_pos ) );
			$this->buffer = substr( $this->buffer, $close_pos + $close_len );
			$this->close_think( $events );
			return true;
		}

		$keep     = $is_final ? 0 : $this->closing_prefix_suffix_len( $lower );
		$emit_len = strlen( $this->buffer ) - $keep;
		if ( $emit_len > 0 ) {
			$this->append_think_text( $events, substr( $this->buffer, 0, $emit_len ) );
			$this->buffer = substr( $this->buffer, $emit_len );
			return true;
		}

		return false;
	}

	/**
	 * Enter a think block.
	 *
	 * @param array<int, array{event:string,data:array}> $events       Event accumulator.
	 * @param bool                                       $emit_enabled Whether this block may emit think events.
	 */
	private function open_think( array &$events, $emit_enabled ) {
		$this->inside_think       = true;
		$this->think_emit_enabled = $emit_enabled && $this->is_context_enabled();
		$this->think_buffer       = '';

		if ( $emit_enabled && ! $this->think_emit_enabled ) {
			$this->warn( sprintf( 'inner monologue suppressed for context "%s"', $this->context ) );
		}

		if ( $this->think_emit_enabled && ! $this->thinking_started ) {
			$this->thinking_started = true;
			$events[]               = array(
				'event' => 'status',
				'data'  => array( 'type' => 'thinking_start' ),
			);
		}
	}

	/**
	 * Leave a think block and emit final think events if enabled.
	 *
	 * @param array<int, array{event:string,data:array}> $events Event accumulator.
	 */
	private function close_think( array &$events ) {
		if ( $this->think_emit_enabled ) {
			$events[] = array(
				'event' => 'think',
				'data'  => array(
					'text'  => trim( $this->think_buffer ),
					'final' => true,
				),
			);
			$events[] = array(
				'event' => 'status',
				'data'  => array( 'type' => 'thinking_end' ),
			);
		}

		$this->inside_think       = false;
		$this->think_emit_enabled = false;
		$this->think_buffer       = '';
	}

	/**
	 * Append think text and emit progressive think_delta when enabled.
	 *
	 * @param array<int, array{event:string,data:array}> $events Event accumulator.
	 * @param string                                     $text   Think text chunk.
	 */
	private function append_think_text( array &$events, $text ) {
		if ( '' === $text ) {
			return;
		}

		$this->think_buffer .= $text;
		if ( $this->think_emit_enabled ) {
			$events[] = array(
				'event' => 'think_delta',
				'data'  => array( 'text' => $text ),
			);
		}
	}

	/**
	 * Emit visible text.
	 *
	 * @param array<int, array{event:string,data:array}> $events Event accumulator.
	 * @param string                                     $text   Visible text.
	 */
	private function emit_delta( array &$events, $text ) {
		if ( '' === $text ) {
			return;
		}
		$this->main_output_started = true;
		$events[]                  = array(
			'event' => 'delta',
			'data'  => array( 'text' => $text ),
		);
	}

	/**
	 * Match an opening think tag at the beginning of text.
	 *
	 * @param string $lower Lowercase text.
	 * @return string|null Matched tag, or null when absent.
	 */
	private function match_open_think( $lower ) {
		foreach ( array( '<thinking>', '<think>' ) as $tag ) {
			if ( 0 === strpos( $lower, $tag ) ) {
				return $tag;
			}
		}
		return null;
	}

	/**
	 * Check whether text is a prefix of an opening think tag.
	 *
	 * @param string $lower Lowercase text.
	 * @return bool Whether text is a think tag prefix.
	 */
	private function is_think_open_prefix( $lower ) {
		foreach ( array( '<think>', '<thinking>' ) as $tag ) {
			if ( 0 === strpos( $tag, $lower ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Check whether text is a prefix of a supported emotion tag.
	 *
	 * @param string $text Text to check.
	 * @return bool Whether text could become a supported tag.
	 */
	private function is_supported_tag_prefix( $text ) {
		foreach ( $this->supported_tags as $tag ) {
			$full = '[' . $tag . ']';
			if ( 0 === stripos( $full, $text ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Resolve a candidate tag against supported tags.
	 *
	 * @param string $name Candidate tag name.
	 * @return string|null Supported tag, or null when unknown.
	 */
	private function resolve_supported_tag( $name ) {
		if ( function_exists( 'mpu_normalize_resolve_emotion_tag' ) ) {
			return mpu_normalize_resolve_emotion_tag( $name, $this->supported_tags );
		}
		foreach ( $this->supported_tags as $tag ) {
			if ( 0 === strcasecmp( (string) $tag, (string) $name ) ) {
				return (string) $tag;
			}
		}
		return null;
	}

	/**
	 * Find the next character sequence that needs parser attention.
	 *
	 * @param string $text Buffered text.
	 * @return int|false Offset, or false when absent.
	 */
	private function next_special_offset( $text ) {
		$positions = array();
		foreach ( array( '[', '<think>', '<thinking>', '</think>', '</thinking>' ) as $needle ) {
			$pos = stripos( $text, $needle );
			if ( false !== $pos ) {
				$positions[] = $pos;
			}
		}
		return empty( $positions ) ? false : min( $positions );
	}

	/**
	 * Check whether the buffer ends with a partial special token.
	 *
	 * @param string $text Buffered text.
	 * @return bool Whether the suffix could become a special token.
	 */
	private function could_be_partial_special( $text ) {
		$lower = strtolower( $text );
		foreach ( array( '[', '<think>', '<thinking>', '</think>', '</thinking>' ) as $needle ) {
			$max = min( strlen( $lower ), strlen( $needle ) - 1 );
			for ( $len = $max; $len > 0; $len-- ) {
				if ( substr( $lower, -$len ) === substr( $needle, 0, $len ) ) {
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * Get suffix length that may become a closing think tag.
	 *
	 * @param string $lower Lowercase text.
	 * @return int Suffix length to preserve.
	 */
	private function closing_prefix_suffix_len( $lower ) {
		$best = 0;
		foreach ( array( '</think>', '</thinking>' ) as $needle ) {
			$max = min( strlen( $lower ), strlen( $needle ) - 1 );
			for ( $len = $max; $len > $best; $len-- ) {
				if ( substr( $lower, -$len ) === substr( $needle, 0, $len ) ) {
					$best = $len;
					break;
				}
			}
		}
		return $best;
	}

	/**
	 * Check whether inner monologue is enabled for this context.
	 *
	 * @return bool Whether think events may be emitted.
	 */
	private function is_context_enabled() {
		return function_exists( 'mpu_is_inner_monologue_enabled_for_context' )
			? mpu_is_inner_monologue_enabled_for_context( $this->context, $this->personality_id )
			: true;
	}

	/**
	 * Write a parser warning to the debug log.
	 *
	 * @param string $message Warning message.
	 */
	private function warn( $message ) {
		if ( function_exists( 'mpu_debug_log' ) ) {
			mpu_debug_log( 'MPU_Stream_Output_Parser: ' . $message );
		}
	}
}
