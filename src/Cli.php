<?php
/**
 * WP-CLI commands.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual;

use AIMultilingual\Language\Languages;
use AIMultilingual\Translation\Extractor;
use AIMultilingual\Translation\Store;
use WP_CLI;
use WP_Error;
use WP_Post;

/**
 * The four commands Milestone 1 needs.
 *
 * Scope is deliberately narrow: these exist so the acceptance walkthrough can
 * be scripted and so translations can be seeded without clicking. Slug, job,
 * memory, glossary, provider and usage commands are not scaffolded here — each
 * arrives with the milestone that owns the feature, where it can actually be
 * tested.
 *
 * Commands are registered as closures rather than a command class so the
 * services they need can be injected instead of rebuilt.
 */
final class Cli {

	/**
	 * Registers the commands.
	 *
	 * @param Languages $languages Language configuration.
	 * @param Store     $store     Segment store.
	 * @param Extractor $extractor Source extractor.
	 */
	public static function register( Languages $languages, Store $store, Extractor $extractor ): void {
		if ( ! class_exists( WP_CLI::class ) ) {
			return;
		}

		WP_CLI::add_command(
			'aiml language list',
			static function () use ( $languages ): void {
				self::language_list( $languages );
			},
			array(
				'shortdesc' => 'Lists configured languages.',
			)
		);

		WP_CLI::add_command(
			'aiml language add',
			static function ( array $args, array $assoc ) use ( $languages ): void {
				self::language_add( $languages, $args, $assoc );
			},
			array(
				'shortdesc' => 'Adds a target language.',
				'synopsis'  => array(
					array(
						'type'        => 'positional',
						'name'        => 'code',
						'description' => 'URL code, for example sv.',
					),
					array(
						'type'        => 'assoc',
						'name'        => 'locale',
						'description' => 'WordPress locale, for example sv_SE.',
					),
					array(
						'type'        => 'assoc',
						'name'        => 'name',
						'description' => 'English name, for example Swedish.',
					),
					array(
						'type'        => 'assoc',
						'name'        => 'native-name',
						'optional'    => true,
						'description' => 'Name in the language itself.',
					),
					array(
						'type'        => 'assoc',
						'name'        => 'status',
						'optional'    => true,
						'options'     => array( 'disabled', 'preview', 'published' ),
						'description' => 'Initial state. Defaults to preview.',
					),
				),
			)
		);

		WP_CLI::add_command(
			'aiml translation get',
			static function ( array $args, array $assoc ) use ( $languages, $store ): void {
				self::translation_get( $languages, $store, $args, $assoc );
			},
			array(
				'shortdesc' => 'Prints one translated field.',
				'synopsis'  => self::translation_synopsis(),
			)
		);

		WP_CLI::add_command(
			'aiml translation set',
			static function ( array $args, array $assoc ) use ( $languages, $store, $extractor ): void {
				self::translation_set( $languages, $store, $extractor, $args, $assoc );
			},
			array(
				'shortdesc' => 'Stores one translated field.',
				'synopsis'  => array_merge(
					self::translation_synopsis(),
					array(
						array(
							'type'        => 'assoc',
							'name'        => 'value',
							'optional'    => true,
							'description' => 'Translated text. Omit and pass --stdin to read from standard input.',
						),
						array(
							'type'        => 'flag',
							'name'        => 'stdin',
							'optional'    => true,
							'description' => 'Read the translated text from standard input.',
						),
					)
				),
			)
		);
	}

	/**
	 * Shared positional/assoc definition for the translation commands.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private static function translation_synopsis(): array {
		return array(
			array(
				'type'        => 'positional',
				'name'        => 'post_id',
				'description' => 'Canonical post ID.',
			),
			array(
				'type'        => 'positional',
				'name'        => 'language',
				'description' => 'Target language code, for example sv.',
			),
			array(
				'type'        => 'assoc',
				'name'        => 'field',
				'options'     => array( 'title', 'excerpt', 'content' ),
				'description' => 'Which field to read or write.',
			),
		);
	}

	// -- Commands --

	/**
	 * Prints the language table.
	 *
	 * @param Languages $languages Language configuration.
	 */
	private static function language_list( Languages $languages ): void {
		$rows = array();

		foreach ( $languages->all() as $language ) {
			$rows[] = array(
				'id'      => (int) $language->language_id,
				'code'    => (string) $language->code,
				'locale'  => (string) $language->locale,
				'name'    => (string) $language->name,
				'status'  => (string) $language->status,
				'default' => $language->is_default ? 'yes' : 'no',
			);
		}

		if ( array() === $rows ) {
			WP_CLI::log( 'No languages configured.' );

			return;
		}

		WP_CLI\Utils\format_items( 'table', $rows, array( 'id', 'code', 'locale', 'name', 'status', 'default' ) );
	}

	/**
	 * Adds a target language.
	 *
	 * @param Languages            $languages Language configuration.
	 * @param array<int, string>   $args      Positional arguments.
	 * @param array<string, mixed> $assoc     Associative arguments.
	 */
	private static function language_add( Languages $languages, array $args, array $assoc ): void {
		$code = (string) ( $args[0] ?? '' );

		$result = $languages->insert(
			array(
				'code'        => $code,
				'locale'      => (string) ( $assoc['locale'] ?? '' ),
				'name'        => (string) ( $assoc['name'] ?? '' ),
				'native_name' => (string) ( $assoc['native-name'] ?? '' ),
				'status'      => (string) ( $assoc['status'] ?? Languages::STATUS_PREVIEW ),
			)
		);

		if ( $result instanceof WP_Error ) {
			WP_CLI::error( $result->get_error_message() );
		}

		WP_CLI::success( sprintf( 'Added language %s (id %d).', $code, (int) $result ) );
	}

	/**
	 * Prints one translated field.
	 *
	 * @param Languages            $languages Language configuration.
	 * @param Store                $store     Segment store.
	 * @param array<int, string>   $args      Positional arguments.
	 * @param array<string, mixed> $assoc     Associative arguments.
	 */
	private static function translation_get( Languages $languages, Store $store, array $args, array $assoc ): void {
		list( $post, $language, $field_key ) = self::resolve_target( $languages, $args, $assoc );

		$segment = $store->get( Store::SOURCE_POST, (int) $post->ID, (int) $language->language_id, $field_key );

		if ( null === $segment || Store::STATUS_MISSING === $segment->status ) {
			WP_CLI::error( 'No translation stored for that field.' );
		}

		if ( ! empty( $segment->is_stale ) ) {
			WP_CLI::warning( 'The source has changed since this translation was written.' );
		}

		WP_CLI::print_value( (string) ( $segment->translated_text ?? '' ) );
	}

	/**
	 * Stores one translated field.
	 *
	 * @param Languages            $languages Language configuration.
	 * @param Store                $store     Segment store.
	 * @param Extractor            $extractor Source extractor.
	 * @param array<int, string>   $args      Positional arguments.
	 * @param array<string, mixed> $assoc     Associative arguments.
	 */
	private static function translation_set( Languages $languages, Store $store, Extractor $extractor, array $args, array $assoc ): void {
		list( $post, $language, $field_key ) = self::resolve_target( $languages, $args, $assoc );

		// Same refusal the editor applies, enforced here so the scriptable path
		// cannot corrupt block or Elementor content.
		if ( Extractor::FIELD_CONTENT === $field_key && ! $extractor->can_translate_body( $post ) ) {
			WP_CLI::error( Extractor::body_notice( $extractor->body_status( $post ) ) );
		}

		$sources = $extractor->extract( $post );

		if ( ! isset( $sources[ $field_key ] ) ) {
			WP_CLI::error( 'That field is empty on the source post, so there is nothing to translate.' );
		}

		if ( ! empty( $assoc['stdin'] ) ) {
			$value = (string) file_get_contents( 'php://stdin' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		} elseif ( isset( $assoc['value'] ) ) {
			$value = (string) $assoc['value'];
		} else {
			WP_CLI::error( 'Pass --value=<text> or --stdin.' );

			return;
		}

		$spec = Extractor::fields()[ $field_key ];

		$result = $store->save_translation(
			array(
				'source_type'     => Store::SOURCE_POST,
				'source_id'       => (int) $post->ID,
				'source_subtype'  => (string) $post->post_type,
				'language_id'     => (int) $language->language_id,
				'field_key'       => $field_key,
				'segment_key'     => $field_key,
				'segment_order'   => (int) $spec['order'],
				'text_format'     => (string) $spec['format'],
				'source_text'     => (string) $sources[ $field_key ]['source_text'],
				'translated_text' => $value,
				'status'          => Store::STATUS_MANUALLY_EDITED,
			)
		);

		if ( $result instanceof WP_Error ) {
			WP_CLI::error( $result->get_error_message() );
		}

		WP_CLI::success( sprintf( 'Saved %s for post %d in %s.', $field_key, (int) $post->ID, (string) $language->code ) );
	}

	/**
	 * Resolves and validates the post, language and field of a command.
	 *
	 * @param Languages            $languages Language configuration.
	 * @param array<int, string>   $args      Positional arguments.
	 * @param array<string, mixed> $assoc     Associative arguments.
	 * @return array{0: WP_Post, 1: object, 2: string}
	 */
	private static function resolve_target( Languages $languages, array $args, array $assoc ): array {
		$post = get_post( (int) ( $args[0] ?? 0 ) );

		if ( ! $post instanceof WP_Post ) {
			WP_CLI::error( 'Unknown post.' );
		}

		$language = $languages->find_by_code( (string) ( $args[1] ?? '' ) );

		if ( null === $language ) {
			WP_CLI::error( 'Unknown language code.' );
		}

		if ( ! empty( $language->is_default ) ) {
			WP_CLI::error( 'The default language is the source; it is not translated.' );
		}

		$field_key = Extractor::field_key( (string) ( $assoc['field'] ?? '' ) );

		if ( null === $field_key ) {
			WP_CLI::error( 'Use --field=title, --field=excerpt or --field=content.' );
		}

		return array( $post, $language, $field_key );
	}
}
