<?php

/**
 * Handle Template Loading Handler
 *
 * @package Give
 * @since   2.7.0
 */

namespace Give\Form;

use _WP_Dependency;
use Give\Form\Template\Hookable;
use Give\Form\Template\Scriptable;
use Give\Helpers\Form\Utils as FormUtils;
use Give\Helpers\Form\Template as FormTemplateUtils;
use Give\Helpers\Form\Template\Utils\Frontend as FrontendFormTemplateUtils;

defined( 'ABSPATH' ) || exit;

/**
 * LoadTemplate class.
 * This class is responsible to load necessary hooks and run required functions which help to render form template (in different style).
 *
 * @since 2.7.0
 */
class LoadTemplate {
	/**
	 * Default form template ID.
	 *
	 * @var string
	 */
	private $defaultTemplateID = 'legacy';

	/**
	 * Form template config.
	 *
	 * @var Template
	 */
	private $template;

	/**
	 * setup form template
	 *
	 * @since 2.7.0
	 * @param int $formId Form Id. Default value: check explanation in src/Helpers/Form/Utils.php:103
	 */
	private function setUpTemplate( $formId = null ) {
		$formId = (int) ( $formId ?: FrontendFormTemplateUtils::getFormId() );

		$templateID = FormTemplateUtils::getActiveID( $formId ) ?: $this->defaultTemplateID;

		$this->template = Give()->templates->getTemplate( $templateID );
	}

	/**
	 * Initialize form template
	 */
	public function init() {
		$this->setUpTemplate();

		// Exit is template is not valid.
		if ( ! ( $this->template instanceof Template ) ) {
			return;
		}

		// Load template hooks.
		if ( $this->template instanceof Hookable ) {
			$this->template->loadHooks();
		}

		// Load template scripts.
		if ( $this->template instanceof Scriptable ) {
			add_action( 'wp_enqueue_scripts', [ $this->template, 'loadScripts' ] );
		}

		$this->setUpFrontendHooks();
	}


	/**
	 * Setup frontend hooks
	 *
	 * @since 2.7.0
	 */
	private function setUpFrontendHooks() {
		add_action( 'give_embed_head', 'rel_canonical' );
		add_action( 'give_embed_head', 'wp_enqueue_scripts', 1 );
		add_action( 'give_embed_head', 'wp_resource_hints', 2 );
		add_action( 'give_embed_head', 'feed_links', 2 );
		add_action( 'give_embed_head', 'feed_links_extra', 3 );
		add_action( 'give_embed_head', 'rsd_link' );
		add_action( 'give_embed_head', 'wlwmanifest_link' );
		add_action( 'give_embed_head', 'adjacent_posts_rel_link_wp_head', 10, 0 );
		add_action( 'give_embed_head', 'noindex', 1 );
		add_action( 'give_embed_head', 'wp_generator' );
		add_action( 'give_embed_head', 'rel_canonical' );
		add_action( 'give_embed_head', 'wp_shortlink_wp_head', 10, 0 );
		add_action( 'give_embed_head', 'wp_site_icon', 99 );

		add_action( 'give_embed_head', 'wp_enqueue_scripts', 1 );
		add_action( 'give_embed_head', [ $this, 'handleEnqueueScripts' ], 2 );
		add_action( 'give_embed_head', 'wp_print_styles', 8 );
		add_action( 'give_embed_head', 'wp_print_head_scripts', 9 );
		add_action( 'give_embed_footer', 'wp_print_footer_scripts', 20 );
		add_filter( 'give_form_wrap_classes', [ $this, 'editClassList' ], 999 );
		add_action( 'give_hidden_fields_after', [ $this, 'addHiddenField' ] );

		// Handle receipt screen template
		add_action( 'wp_ajax_get_receipt', [ $this, 'handleReceiptAjax' ], 9 );
		add_action( 'wp_ajax_nopriv_get_receipt', [ $this, 'handleReceiptAjax' ], 9 );
	}

	/**
	 * Render sequoia receipt by ajax
	 *
	 * @since 2.7.0
	 */
	public function handleReceiptAjax() {
		// Do not handle form template receipt for legacy form.
		if ( FormUtils::isLegacyForm() ) {
			return;
		}

		// Show new receipt view only on donation confirmation page.
		if ( false === strpos( wp_get_referer(), untrailingslashit( FormUtils::getSuccessPageURL() ) ) ) {
			return;
		}

		ob_start();
		include_once $this->template->getReceiptView();
		$data = ob_get_clean();
		wp_send_json( $data );
		wp_die(); // All ajax handlers die when finished
	}


	/**
	 * Handle enqueue script
	 *
	 * @since 2.7.0
	 */
	public function handleEnqueueScripts() {
		global $wp_scripts, $wp_styles;
		wp_enqueue_scripts();

		$wp_styles->dequeue( $this->getListOfScriptsToDequeue( $wp_styles->registered ) );
		$wp_scripts->dequeue( $this->getListOfScriptsToDequeue( $wp_scripts->registered ) );
	}

	/**
	 * Edit donation form wrapper class list.
	 *
	 * @param array $classes
	 *
	 * @return array
	 * @since 2.7.0
	 */
	public function editClassList( $classes ) {
		// Remove display_style related classes because they (except onpage ) creates style conflict with form template.
		$classes = array_filter(
			$classes,
			static function ( $class ) {
				return false === strpos( $class, 'give-display-' );
			}
		);

		$classes[] = 'give-embed-form';

		if ( FormUtils::inIframe() ) {
			$classes[] = 'give-viewing-form-in-iframe';
		}

		return $classes;
	}

	/**
	 * Add hidden field
	 *
	 * @since 2.7.0
	 */
	public function addHiddenField() {
		printf(
			'<input type="hidden" name="%1$s" value="%2$s">',
			'give_embed_form',
			'1'
		);
	}

	/**
	 * Get filter list to dequeue scripts and style
	 *
	 * @param array $scripts
	 *
	 * @return array
	 * @since 2.7.0
	 */
	private function getListOfScriptsToDequeue( $scripts ) {
		$list = [];
		$skip = [ 'babel-polyfill' ];

		/* @var _WP_Dependency $data */
		foreach ( $scripts as $handle => $data ) {
			// Do not unset dependency.
			if ( in_array( $handle, $skip, true ) ) {
				continue;
			}

			if (
				0 === strpos( $handle, 'give' ) ||
				false !== strpos( $data->src, '\give' )
			) {
				// Store dependencies to skip.
				$skip = array_merge( $skip, $data->deps );
				continue;
			}

			$list[] = $handle;
		}

		return $list;
	}

	/**
	 * Get template.
	 *
	 * @return Template
	 * @since 2.7.0
	 */
	public function getTheme() {
		return $this->template;
	}
}