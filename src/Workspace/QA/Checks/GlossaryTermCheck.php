<?php
/**
 * Glossary terminology QA warning (ADR-0014).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Workspace\QA\Checks;

use AIMultilingual\Glossary\GlossaryNormalizer;
use AIMultilingual\Glossary\GlossaryService;
use AIMultilingual\Workspace\QA\GlossaryLanguageAware;
use AIMultilingual\Workspace\QA\QACheck;
use AIMultilingual\Workspace\QA\QAIssue;

/**
 * Warns when an approved glossary target term is missing from the translation.
 */
final class GlossaryTermCheck implements QACheck, GlossaryLanguageAware {

	public const CODE = 'glossary_term_missing';

	/**
	 * Glossary service.
	 *
	 * @var GlossaryService
	 */
	private GlossaryService $glossary;

	/**
	 * Source language id for the current evaluation.
	 *
	 * @var int
	 */
	private int $source_lang_id = 0;

	/**
	 * Target language id for the current evaluation.
	 *
	 * @var int
	 */
	private int $target_lang_id = 0;

	/**
	 * Constructor.
	 *
	 * @param GlossaryService $glossary Glossary service.
	 */
	public function __construct( GlossaryService $glossary ) {
		$this->glossary = $glossary;
	}

	/**
	 * {@inheritdoc}
	 *
	 * @param int $source_lang_id Source language id.
	 * @param int $target_lang_id Target language id.
	 */
	public function set_language_pair( int $source_lang_id, int $target_lang_id ): void {
		$this->source_lang_id = $source_lang_id;
		$this->target_lang_id = $target_lang_id;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_id(): string {
		return 'glossary_term';
	}

	/**
	 * {@inheritdoc}
	 */
	public function default_severity(): string {
		return QAIssue::SEVERITY_WARNING;
	}

	/**
	 * {@inheritdoc}
	 *
	 * @param string $source_text Source text.
	 * @param string $target_text Target text.
	 * @param string $text_format Text format.
	 * @return list<QAIssue>
	 */
	public function check( string $source_text, string $target_text, string $text_format ): array {
		if ( $this->source_lang_id <= 0 || $this->target_lang_id <= 0 ) {
			return array();
		}

		$matches = $this->glossary->match_terms( $source_text, $this->source_lang_id, $this->target_lang_id, $text_format );

		$issues   = array();
		$seen     = array();
		$scan_tgt = ( new GlossaryNormalizer() )->prepare_scan_text( $target_text, $text_format );
		$tgt_fold = mb_strtolower( $scan_tgt, 'UTF-8' );

		foreach ( $matches as $match ) {
			if ( isset( $seen[ $match->glossary_id ] ) ) {
				continue;
			}
			$seen[ $match->glossary_id ] = true;

			$expected = mb_strtolower( trim( $match->target_term ), 'UTF-8' );
			if ( '' === $expected ) {
				continue;
			}

			if ( $this->target_contains_term( $tgt_fold, $expected ) ) {
				continue;
			}

			$issues[] = new QAIssue(
				self::CODE,
				QAIssue::SEVERITY_WARNING,
				sprintf(
					'Glossary term "%1$s" should use "%2$s".',
					$match->source_term,
					$match->target_term
				),
				array(
					'glossary_id'          => $match->glossary_id,
					'source_term'          => $match->source_term,
					'expected_target_term' => $match->target_term,
				)
			);
		}

		return $issues;
	}

	/**
	 * Whole-word presence check on folded target scan text.
	 *
	 * @param string $tgt_fold Folded target scan.
	 * @param string $expected Folded expected target term.
	 */
	private function target_contains_term( string $tgt_fold, string $expected ): bool {
		$len = mb_strlen( $expected, 'UTF-8' );
		$pos = 0;
		while ( true ) {
			$found = mb_strpos( $tgt_fold, $expected, $pos );
			if ( false === $found ) {
				break;
			}
			$pos    = $found;
			$before = $pos > 0 ? mb_substr( $tgt_fold, $pos - 1, 1, 'UTF-8' ) : '';
			$after  = mb_substr( $tgt_fold, $pos + $len, 1, 'UTF-8' );
			$ok_b   = '' === $before || ! preg_match( '/[\p{L}\p{N}]/u', $before );
			$ok_a   = '' === $after || ! preg_match( '/[\p{L}\p{N}]/u', $after );
			if ( $ok_b && $ok_a ) {
				return true;
			}
			++$pos;
		}

		return false;
	}
}
