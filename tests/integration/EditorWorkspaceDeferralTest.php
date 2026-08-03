<?php
/**
 * M1 editor deferral for block workspace posts.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Integration;

use AIMultilingual\Admin\Editor;
use AIMultilingual\Admin\TranslatorWorkspace;

/**
 * Legacy editor directs block posts to the translator workspace.
 */
final class EditorWorkspaceDeferralTest extends AimlTestCase {

	use WorkspaceTestHelpers;

	protected function setUp(): void {
		parent::setUp();
		$this->enable_strategy_f_flags();
	}

	public function test_block_post_shows_workspace_deferral_notice(): void {
		$language = $this->add_language();
		$post     = $this->create_block_page();
		$editor   = new Editor( $this->languages, $this->store, $this->strategy_f_extractor() );

		wp_set_current_user( $this->create_translator() );

		ob_start();
		$method = new \ReflectionMethod( Editor::class, 'render_form' );
		$method->setAccessible( true );
		$method->invoke( $editor, $post, (int) $language->language_id );
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'Translator Workspace', $html );
		$this->assertStringContainsString( 'page=' . TranslatorWorkspace::MENU_SLUG, $html );
		$this->assertStringContainsString( 'post_id=' . (int) $post->ID, $html );
		$this->assertStringContainsString( 'language=sv', $html );
		$this->assertStringContainsString( 'disabled', $html );
	}

	public function test_classic_post_does_not_show_workspace_deferral_notice(): void {
		$language = $this->add_language();
		$post     = $this->create_page( 'Classic only', 'Plain classic body without blocks.' );
		$editor   = new Editor( $this->languages, $this->store, $this->extractor );

		wp_set_current_user( $this->create_translator() );

		ob_start();
		$method = new \ReflectionMethod( Editor::class, 'render_form' );
		$method->setAccessible( true );
		$method->invoke( $editor, $post, (int) $language->language_id );
		$html = (string) ob_get_clean();

		$this->assertStringNotContainsString( 'Open Translator Workspace', $html );
	}
}
