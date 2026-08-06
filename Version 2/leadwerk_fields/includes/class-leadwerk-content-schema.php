<?php
/**
 * Shared structured schema for FINORA importer, fields metaboxes and theme renderers.
 *
 * @package Leadwerk_Fields
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Leadwerk_Content_Schema {

	/**
	 * Return all section field groups.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function get_groups() {
		static $groups = null;

		if ( null !== $groups ) {
			return $groups;
		}

		$groups = array(
			'finora_home_sections'        => array(
				'label'       => 'FINORA Startseite',
				'description' => 'Sektionen der Startseite in fester Reihenfolge bearbeiten.',
				'source_keys' => array( 'finora-home-v1' ),
				'layouts'     => array(
					'hero_slider'  => self::layout_home_hero_slider(),
					'pillars'      => self::layout_pillars(),
					'audience'     => self::layout_home_audience_switcher(),
					'why_finora'   => self::layout_why_finora(),
					'how_it_works' => self::layout_how_it_works(),
					'testimonials' => self::layout_testimonials(),
					'faq'          => self::layout_faq(),
				),
			),
			'finora_about_sections'       => array(
				'label'       => 'FINORA Ueber uns',
				'description' => 'Sektionen der Ueber-FINORA-Seite in fester Reihenfolge bearbeiten.',
				'source_keys' => array( 'finora-about-v1' ),
				'layouts'     => array(
					'hero'         => self::layout_hero(),
					'why_finora'   => self::layout_why_finora(),
					'finanzwelt'   => self::layout_banner_cta(),
					'bedeutet'     => self::layout_about_bedeutet(),
					'how_it_works' => self::layout_how_it_works(),
					'testimonials' => self::layout_testimonials(),
					'faq'          => self::layout_faq(),
				),
			),
			'finora_philosophy_sections'  => array(
				'label'       => 'FINORA Philosophie',
				'description' => 'Sektionen der Philosophie-Seite in fester Reihenfolge bearbeiten.',
				'source_keys' => array( 'finora-philosophy-v1' ),
				'layouts'     => array(
					'hero'            => self::layout_hero(),
					'pillars'         => self::layout_pillars(),
					'basis_detail'    => self::layout_media_blurbs( 'basis_detail' ),
					'basis_audience'  => self::layout_audience_cards( 'basis_audience' ),
					'break_one'       => self::layout_center_cta( 'break_one' ),
					'invest_detail'   => self::layout_invest_detail(),
					'invest_audience' => self::layout_audience_cards( 'invest_audience' ),
					'break_two'       => self::layout_center_cta( 'break_two' ),
					'tax_detail'      => self::layout_tax_detail(),
					'tax_audience'    => self::layout_audience_cards( 'tax_audience' ),
					'break_three'     => self::layout_center_cta( 'break_three' ),
					'testimonials'    => self::layout_testimonials(),
					'faq'             => self::layout_faq(),
				),
			),
			'finora_contact_sections'     => array(
				'label'       => 'FINORA Kontakt',
				'description' => 'Sektionen der Kontakt-Seite in fester Reihenfolge bearbeiten.',
				'source_keys' => array( 'finora-contact-v1' ),
				'layouts'     => array(
					'hero'         => self::layout_hero( false ),
					'contact_main' => self::layout_contact_main(),
				),
			),
			'finora_danke_sections'       => array(
				'label'       => 'FINORA Danke',
				'description' => 'Sektionen der Danke-Seite in fester Reihenfolge bearbeiten.',
				'source_keys' => array( 'finora-danke-v1' ),
				'layouts'     => array(
					'hero'       => self::layout_hero( false ),
					'danke_main' => self::layout_danke_main(),
				),
			),
			'finora_retirement_sections'  => array(
				'label'       => 'FINORA Altersvorsorge',
				'description' => 'Sektionen der Altersvorsorge-Seite in fester Reihenfolge bearbeiten.',
				'source_keys' => array( 'finora-retirement-v1' ),
				'layouts'     => array(
					'hero'             => self::layout_hero( false ),
					'meaning'          => self::layout_media_text(),
					'workflow'         => self::layout_workflow_blurbs(),
					'private_vorsorge' => self::layout_media_text(),
					'gap_cta'          => self::layout_center_cta( 'gap_cta' ),
					'favorites'        => self::layout_tabs_section(),
					'audience'         => self::layout_retirement_audience(),
					'concepts'         => self::layout_concepts_section(),
					'final_cta'        => self::layout_center_cta( 'final_cta' ),
				),
			),
			'finora_investment_sections'  => array(
				'label'       => 'FINORA Investment',
				'description' => 'Sektionen der Investment-Seite in fester Reihenfolge bearbeiten.',
				'source_keys' => array( 'finora-investment-v1' ),
				'layouts'     => array(
					'hero'          => self::layout_hero( false ),
					'strategy'      => self::layout_media_text(),
					'challenge'     => self::layout_blurb_image_section(),
					'approach'      => self::layout_approach_tiles(),
					'timeline'      => self::layout_timeline(),
					'target_groups' => self::layout_target_groups(),
					'results'       => self::layout_results_section(),
					'final_cta'     => self::layout_center_cta( 'final_cta' ),
				),
			),
			'finora_real_estate_sections' => array(
				'label'       => 'FINORA Immobilien',
				'description' => 'Sektionen der Immobilien-Seite in fester Reihenfolge bearbeiten.',
				'source_keys' => array( 'finora-real-estate-v1' ),
				'layouts'     => array(
					'hero'      => self::layout_hero( false ),
					'intro'     => self::layout_real_estate_intro(),
					'timeline'  => self::layout_timeline(),
					'calculator'=> self::layout_calculator(),
					'case'      => self::layout_case_highlight(),
					'final_cta' => self::layout_dark_cta(),
				),
			),
			'finora_inheritance_sections' => array(
				'label'       => 'FINORA Erbanlage',
				'description' => 'Sektionen der Erbanlage-Seite in fester Reihenfolge bearbeiten.',
				'source_keys' => array( 'finora-inheritance-v1' ),
				'layouts'     => array(
					'hero'           => self::layout_hero( false ),
					'responsibility' => self::layout_responsibility(),
					'timeline'       => self::layout_timeline(),
					'new_phase'      => self::layout_new_phase(),
					'outcomes'       => self::layout_outcomes(),
					'target_group'   => self::layout_target_groups_image(),
					'final_cta'      => self::layout_center_cta( 'final_cta' ),
				),
			),
			'finora_tech_expats_sections' => array(
				'label'       => 'FINORA Tech Expats',
				'description' => 'Sektionen der Tech-Expats-Seite in fester Reihenfolge bearbeiten.',
				'source_keys' => array( 'finora-tech-expats-v1' ),
				'layouts'     => array(
					'hero'         => self::layout_tech_hero(),
					'pain_points'  => self::layout_tech_pain_points(),
					'audience'     => self::layout_tech_audience(),
					'solution'     => self::layout_tech_solution(),
					'pillars'      => self::layout_tech_pillars(),
					'mid_cta'      => self::layout_center_cta( 'tech_mid_cta' ),
					'value'        => self::layout_tech_value(),
					'process'      => self::layout_tech_process(),
					'testimonials' => self::layout_tech_testimonials(),
					'faq'          => self::layout_faq(),
					'final_cta'    => self::layout_tech_final_cta(),
				),
			),
			'impressum_page'              => array(
				'label'             => 'FINORA Impressum',
				'description'       => 'Impressum ueber Leadwerk Fields bearbeiten. Der Inhalt wird in das Seiten-HTML synchronisiert.',
				'source_keys'       => array( 'finora-impressum-v1' ),
				'sync_post_content' => true,
				'fields'            => array(
					'headline' => self::text( 'Seitenueberschrift' ),
					'content'  => self::editor( 'Inhalt' ),
				),
			),
			'datenschutz_page'            => array(
				'label'             => 'FINORA Datenschutz',
				'description'       => 'Datenschutzerklaerung ueber Leadwerk Fields bearbeiten. Der Inhalt wird in das Seiten-HTML synchronisiert.',
				'source_keys'       => array( 'finora-datenschutz-v1' ),
				'sync_post_content' => true,
				'fields'            => array(
					'headline' => self::text( 'Seitenueberschrift' ),
					'content'  => self::editor( 'Inhalt' ),
				),
			),
		);

		return $groups;
	}

	/**
	 * Return one field group schema.
	 *
	 * @param string $field_name Field name.
	 * @return array<string,mixed>|null
	 */
	public static function get_group( $field_name ) {
		$groups = self::get_groups();
		return $groups[ $field_name ] ?? null;
	}

	/**
	 * Resolve a field group by source key.
	 *
	 * @param string $source_key Source key.
	 * @return array<string,mixed>|null
	 */
	public static function get_group_for_source_key( $source_key ) {
		foreach ( self::get_groups() as $field_name => $group ) {
			if ( in_array( $source_key, $group['source_keys'], true ) ) {
				$group['field_name'] = $field_name;
				return $group;
			}
		}

		return null;
	}

	/**
	 * Resolve a field group by post.
	 *
	 * @param int|WP_Post $post Post object or ID.
	 * @return array<string,mixed>|null
	 */
	public static function get_group_for_post( $post ) {
		$post_id = is_object( $post ) ? (int) $post->ID : (int) $post;
		if ( ! $post_id ) {
			return null;
		}

		$source_key = (string) get_post_meta( $post_id, 'leadwerk_source_key', true );
		return self::get_group_for_source_key( $source_key );
	}

	/**
	 * Resolve a layout schema.
	 *
	 * @param string $field_name Field group name.
	 * @param string $layout     Layout name.
	 * @return array<string,mixed>|null
	 */
	public static function get_layout( $field_name, $layout ) {
		$group = self::get_group( $field_name );
		if ( ! $group ) {
			return null;
		}

		return $group['layouts'][ $layout ] ?? null;
	}

	/**
	 * Default value for a field definition.
	 *
	 * @param array<string,mixed> $definition Field definition.
	 * @return mixed
	 */
	public static function get_default_value( $definition ) {
		if ( is_array( $definition ) && array_key_exists( 'default', $definition ) ) {
			return $definition['default'];
		}

		$type = $definition['type'] ?? 'text';

		switch ( $type ) {
			case 'checkbox':
				return false;
			case 'image':
				return 0;
			case 'repeater':
			case 'select_options':
				return array();
			default:
				return '';
		}
	}

	/**
	 * Basic text field definition.
	 *
	 * @param string $label Label.
	 * @return array<string,mixed>
	 */
	protected static function text( $label ) {
		return array(
			'label' => $label,
			'type'  => 'text',
		);
	}

	/**
	 * Basic textarea field definition.
	 *
	 * @param string $label Label.
	 * @return array<string,mixed>
	 */
	protected static function textarea( $label ) {
		return array(
			'label' => $label,
			'type'  => 'textarea',
		);
	}

	/**
	 * Rich text field definition.
	 *
	 * @param string $label Label.
	 * @return array<string,mixed>
	 */
	protected static function editor( $label ) {
		return array(
			'label' => $label,
			'type'  => 'classic_editor',
		);
	}

	/**
	 * Inline-safe rich text field definition for headings.
	 *
	 * @param string $label Label.
	 * @return array<string,mixed>
	 */
	protected static function heading_html( $label ) {
		return array(
			'label' => $label,
			'type'  => 'heading_html',
		);
	}

	/**
	 * Normalize heading markup so it can be injected into existing h* nodes.
	 *
	 * @param string $html Raw heading markup.
	 * @return string
	 */
	public static function sanitize_heading_html( $html ) {
		$html = wp_kses_post( (string) $html );
		if ( '' === trim( wp_strip_all_tags( $html ) ) ) {
			return '';
		}

		if ( ! class_exists( 'DOMDocument' ) ) {
			$fallback = preg_replace( '#</?(p|div|section|article)\b[^>]*>#i', '', $html );
			$fallback = is_string( $fallback ) ? trim( $fallback ) : '';
			return '' === trim( wp_strip_all_tags( $fallback ) ) ? '' : $fallback;
		}

		$temp = new DOMDocument( '1.0', 'UTF-8' );
		libxml_use_internal_errors( true );
		$temp->loadHTML( '<?xml encoding="utf-8" ?><div id="leadwerk-heading-root">' . $html . '</div>' );
		libxml_clear_errors();

		$root = ( new DOMXPath( $temp ) )->query( '//*[@id="leadwerk-heading-root"]' )->item( 0 );
		if ( ! $root instanceof DOMNode ) {
			return '';
		}

		$normalized = self::serialize_inline_heading_children(
			$root,
			array(
				'a'      => true,
				'abbr'   => true,
				'b'      => true,
				'br'     => true,
				'cite'   => true,
				'code'   => true,
				'em'     => true,
				'i'      => true,
				'mark'   => true,
				'small'  => true,
				'span'   => true,
				'strong' => true,
				'sub'    => true,
				'sup'    => true,
				'u'      => true,
				'wbr'    => true,
			),
			array(
				'article',
				'aside',
				'blockquote',
				'div',
				'footer',
				'h1',
				'h2',
				'h3',
				'h4',
				'h5',
				'h6',
				'header',
				'li',
				'main',
				'ol',
				'p',
				'section',
				'ul',
			)
		);

		$normalized = trim( preg_replace( '/(?:<br>\s*){3,}/i', '<br><br>', (string) $normalized ) );
		return '' === trim( wp_strip_all_tags( $normalized ) ) ? '' : $normalized;
	}

	/**
	 * Serialize child nodes into inline-only heading HTML.
	 *
	 * @param DOMNode              $node                Root node.
	 * @param array<string,bool>   $allowed_inline_tags Allowed inline tags.
	 * @param string[]             $block_tags          Tags that should be flattened.
	 * @return string
	 */
	protected static function serialize_inline_heading_children( $node, $allowed_inline_tags, $block_tags ) {
		$chunks      = array();
		$last_was_br = false;

		foreach ( $node->childNodes as $child ) {
			$is_block = $child instanceof DOMElement && in_array( strtolower( $child->tagName ), $block_tags, true );
			$chunk    = self::serialize_inline_heading_node( $child, $allowed_inline_tags, $block_tags );
			if ( '' === $chunk ) {
				continue;
			}

			if ( $is_block && ! empty( $chunks ) && ! $last_was_br ) {
				$chunks[] = '<br>';
			}

			$chunks[]    = $chunk;
			$last_was_br = '<br>' === $chunk;
		}

		return implode( '', $chunks );
	}

	/**
	 * Serialize one node into inline-safe heading HTML.
	 *
	 * @param DOMNode            $node                Node.
	 * @param array<string,bool> $allowed_inline_tags Allowed inline tags.
	 * @param string[]           $block_tags          Tags that should be flattened.
	 * @return string
	 */
	protected static function serialize_inline_heading_node( $node, $allowed_inline_tags, $block_tags ) {
		if ( $node instanceof DOMText ) {
			$text = preg_replace( '/\s+/u', ' ', (string) $node->nodeValue );
			return '' === trim( (string) $text ) ? '' : esc_html( (string) $text );
		}

		if ( ! $node instanceof DOMElement ) {
			return '';
		}

		$tag = strtolower( $node->tagName );
		if ( 'br' === $tag ) {
			return '<br>';
		}

		if ( 'wbr' === $tag ) {
			return '<wbr>';
		}

		$children_html = self::serialize_inline_heading_children( $node, $allowed_inline_tags, $block_tags );
		if ( in_array( $tag, $block_tags, true ) || ! isset( $allowed_inline_tags[ $tag ] ) ) {
			return $children_html;
		}

		$attrs = '';
		if ( $node->hasAttributes() ) {
			foreach ( $node->attributes as $attribute ) {
				if ( ! $attribute instanceof DOMAttr ) {
					continue;
				}

				$attrs .= sprintf(
					' %1$s="%2$s"',
					esc_attr( $attribute->nodeName ),
					esc_attr( $attribute->nodeValue )
				);
			}
		}

		return sprintf( '<%1$s%2$s>%3$s</%1$s>', $tag, $attrs, $children_html );
	}

	/**
	 * URL field definition.
	 *
	 * @param string $label Label.
	 * @return array<string,mixed>
	 */
	protected static function url( $label ) {
		return array(
			'label' => $label,
			'type'  => 'url',
		);
	}

	protected static function url_anchor( $label ) {
		return array(
			'label' => $label,
			'type'  => 'url_anchor',
		);
	}

	/**
	 * Image field definition.
	 *
	 * @param string $label Label.
	 * @return array<string,mixed>
	 */
	protected static function image( $label ) {
		return array(
			'label' => $label,
			'type'  => 'image',
		);
	}

	/**
	 * Checkbox field definition.
	 *
	 * @param string $label Label.
	 * @return array<string,mixed>
	 */
	protected static function checkbox( $label, $default = false ) {
		return array(
			'label'   => $label,
			'type'    => 'checkbox',
			'default' => ! empty( $default ),
		);
	}

	/**
	 * Repeater field definition.
	 *
	 * @param string              $label    Label.
	 * @param array<string,mixed> $fields   Sub-fields.
	 * @param string|null         $add_text Optional button label.
	 * @return array<string,mixed>
	 */
	protected static function repeater( $label, $fields, $add_text = null ) {
		$definition = array(
			'label'  => $label,
			'type'   => 'repeater',
			'fields' => $fields,
		);

		if ( null !== $add_text ) {
			$definition['add_button_label'] = $add_text;
		}

		return $definition;
	}

	/**
	 * Internal/external button field set.
	 *
	 * @param string $prefix Field prefix.
	 * @return array<string,array<string,mixed>>
	 */
	protected static function button_fields( $prefix ) {
		return array(
			$prefix . '_label'    => self::text( 'Button Text' ),
			$prefix . '_page_key' => self::text( 'Button Zielseite (source_key)' ),
			$prefix . '_url'      => self::url( 'Button URL (Fallback/extern)' ),
		);
	}

	/**
	 * Standard hero layout.
	 *
	 * @param bool $with_button Whether CTA fields should be present.
	 * @return array<string,mixed>
	 */
	protected static function layout_hero( $with_button = true ) {
		$fields = array(
			'title'     => self::heading_html( 'Titel' ),
			'subtitle'  => self::textarea( 'Untertitel' ),
			'image'     => self::image( 'Bild' ),
			'image_alt' => self::text( 'Bild Alt-Text' ),
		);

		if ( $with_button ) {
			$fields = array_merge( $fields, self::button_fields( 'cta' ) );
		}

		return array(
			'label'    => 'Hero',
			'template' => 'hero',
			'fields'   => $fields,
		);
	}

	protected static function layout_tech_hero() {
		return array(
			'label'    => 'Tech Hero',
			'template' => 'tech_hero',
			'fields'   => array_merge(
				array(
					'title'          => self::heading_html( 'Titel' ),
					'subtitle'       => self::textarea( 'Untertitel' ),
					'background'     => self::image( 'Hintergrundbild' ),
					'background_alt' => self::text( 'Hintergrundbild Alt-Text' ),
					'services'       => self::repeater(
						'Hero Themen',
						array(
							'icon_class' => self::text( 'Icon CSS Klasse' ),
							'title'      => self::heading_html( 'Titel' ),
							'page_key'   => self::text( 'Zielseite (source_key)' ),
							'url'        => self::url_anchor( 'URL / Anchor' ),
						),
						'Thema hinzufuegen'
					),
				),
				self::button_fields( 'cta' )
			),
		);
	}

	protected static function layout_tech_pain_points() {
		return array(
			'label'    => 'Herausforderungen',
			'template' => 'tech_pain_points',
			'fields'   => array(
				'title' => self::heading_html( 'Titel' ),
				'intro' => self::textarea( 'Einleitung' ),
				'items' => self::repeater(
					'Karten',
					array(
						'icon_class' => self::text( 'Icon CSS Klasse' ),
						'label'      => self::text( 'Kategorie' ),
						'title'      => self::text( 'Titel' ),
						'content'    => self::editor( 'Text' ),
					),
					'Karte hinzufuegen'
				),
			),
		);
	}

	protected static function layout_tech_audience() {
		return array(
			'label'    => 'Tech Zielgruppe',
			'template' => 'tech_audience',
			'fields'   => array(
				'title'    => self::heading_html( 'Titel' ),
				'intro'    => self::textarea( 'Einleitung' ),
				'personas' => self::repeater(
					'Rollen',
					array(
						'icon_class' => self::text( 'Icon CSS Klasse' ),
						'label'      => self::text( 'Bezeichnung' ),
					),
					'Rolle hinzufuegen'
				),
			),
		);
	}

	protected static function layout_tech_solution() {
		return array(
			'label'    => 'Kevin / Beratung',
			'template' => 'tech_solution',
			'fields'   => array_merge(
				array(
					'title'     => self::heading_html( 'Titel' ),
					'body'      => self::editor( 'Text' ),
					'image'     => self::image( 'Bild' ),
					'image_alt' => self::text( 'Bild Alt-Text' ),
					'items'     => self::repeater(
						'Vertrauenspunkte',
						array(
							'icon_class' => self::text( 'Icon CSS Klasse' ),
							'text'       => self::text( 'Text' ),
						),
						'Punkt hinzufuegen'
					),
				),
				self::button_fields( 'cta' )
			),
		);
	}

	protected static function layout_tech_value() {
		return array(
			'label'    => 'Tech Mehrwert',
			'template' => 'tech_value',
			'fields'   => array(
				'title'          => self::heading_html( 'Titel' ),
				'intro'          => self::textarea( 'Einleitung' ),
				'points'         => self::repeater(
					'Mehrwert-Punkte',
					array(
						'icon_class' => self::text( 'Icon CSS Klasse' ),
						'content'    => self::editor( 'Text' ),
					),
					'Punkt hinzufuegen'
				),
				'diagram_columns'=> self::repeater(
					'Diagramm-Spalten',
					array(
						'title' => self::text( 'Spaltentitel' ),
						'items' => self::repeater(
							'Eintraege',
							array(
								'icon_class' => self::text( 'Icon CSS Klasse' ),
								'text'       => self::text( 'Text' ),
							),
							'Eintrag hinzufuegen'
						),
					),
					'Spalte hinzufuegen'
				),
				'open_hint'      => self::text( 'Vergroessern-Hinweis' ),
				'dialog_title'   => self::text( 'Dialogtitel' ),
			),
		);
	}

	protected static function layout_tech_pillars() {
		return array(
			'label'    => 'Tech Leistungssaeulen',
			'template' => 'tech_pillars',
			'fields'   => array(
				'title' => self::heading_html( 'Titel' ),
				'items' => self::repeater(
					'Karten',
					array(
						'icon_class'      => self::text( 'Icon CSS Klasse' ),
						'title'           => self::text( 'Titel' ),
						'description'     => self::editor( 'Beschreibung' ),
						'button_label'    => self::text( 'Button Text' ),
						'button_page_key' => self::text( 'Button Zielseite (source_key)' ),
						'button_url'      => self::url( 'Button URL (Fallback/extern)' ),
					),
					'Karte hinzufuegen'
				),
			),
		);
	}

	protected static function layout_tech_final_cta() {
		return array(
			'label'    => 'Abschluss-CTA',
			'template' => 'tech_final_cta',
			'fields'   => array_merge(
				array(
					'title'       => self::heading_html( 'Titel' ),
					'body'        => self::editor( 'Text' ),
					'sticky_meta' => self::text( 'Mobiler Sticky CTA Zusatz' ),
				),
				self::button_fields( 'primary_cta' ),
				self::button_fields( 'secondary_cta' ),
				self::button_fields( 'sticky_cta' )
			),
		);
	}

	protected static function layout_tech_process() {
		return array(
			'label'    => 'Tech Ablauf',
			'template' => 'tech_process',
			'fields'   => array_merge(
				array(
					'title' => self::heading_html( 'Titel' ),
					'steps' => self::repeater(
						'Schritte',
						array(
							'icon_class' => self::text( 'Icon CSS Klasse' ),
							'title'      => self::text( 'Titel' ),
							'content'    => self::editor( 'Text' ),
						),
						'Schritt hinzufuegen'
					),
				),
				self::button_fields( 'cta' )
			),
		);
	}

	protected static function layout_tech_testimonials() {
		$layout             = self::layout_testimonials();
		$layout['label']    = 'Tech Testimonials';
		$layout['template'] = 'tech_testimonials';
		return $layout;
	}

	/**
	 * Home hero slider layout.
	 *
	 * @return array<string,mixed>
	 */
	protected static function layout_home_hero_slider() {
		return array(
			'label'    => 'Hero Slider',
			'template' => 'hero_slider',
			'fields'   => array(
				'slides'     => self::repeater(
					'Slides',
					array(
						'title'          => self::heading_html( 'Titel' ),
						'subtitle'       => self::textarea( 'Untertitel' ),
						'background'     => self::image( 'Hintergrundbild' ),
						'background_alt' => self::text( 'Bild Alt-Text' ),
						'cta_label'      => self::text( 'Button Text' ),
						'cta_page_key'   => self::text( 'Button Zielseite (source_key)' ),
						'cta_url'        => self::url( 'Button URL (Fallback/extern)' ),
					),
					'Slide hinzufuegen'
				),
				'services'   => self::repeater(
					'Service Links',
					array(
						'title'       => self::heading_html( 'Titel' ),
						'description' => self::textarea( 'Kurztext' ),
						'icon'        => self::image( 'Icon' ),
						'icon_alt'    => self::text( 'Icon Alt-Text' ),
						'page_key'    => self::text( 'Zielseite (source_key)' ),
						'url'         => self::url( 'URL (Fallback/extern)' ),
					),
					'Service-Link hinzufuegen'
				),
				'prev_label' => self::text( 'Label Vorherige Folie' ),
				'next_label' => self::text( 'Label Naechste Folie' ),
			),
		);
	}

	/**
	 * Shared pillars layout.
	 *
	 * @return array<string,mixed>
	 */
	protected static function layout_pillars() {
		return array(
			'label'    => 'Drei Saeulen',
			'template' => 'pillars',
			'fields'   => array(
				'title' => self::heading_html( 'Titel' ),
				'items' => self::repeater(
					'Karten',
					array(
						'icon'            => self::image( 'Icon' ),
						'icon_alt'        => self::text( 'Icon Alt-Text' ),
						'title'           => self::text( 'Titel' ),
						'description'     => self::editor( 'Beschreibung' ),
						'button_label'    => self::text( 'Button Text' ),
						'button_page_key' => self::text( 'Button Zielseite (source_key)' ),
						'button_url'      => self::url( 'Button URL (Fallback/extern)' ),
					),
					'Karte hinzufuegen'
				),
			),
		);
	}

	/**
	 * Home audience switcher layout.
	 *
	 * @return array<string,mixed>
	 */
	protected static function layout_home_audience_switcher() {
		return array(
			'label'    => 'Fuer Menschen wie dich',
			'template' => 'audience_switcher',
			'fields'   => array(
				'title'      => self::heading_html( 'Titel' ),
				'prev_label' => self::text( 'Label Zurueck' ),
				'next_label' => self::text( 'Label Weiter' ),
				'items'      => self::repeater(
					'Profile',
					array(
						'label'      => self::text( 'Label' ),
						'card_title' => self::text( 'Kartentitel' ),
						'body'       => self::editor( 'Text' ),
						'image'      => self::image( 'Bild' ),
						'image_alt'  => self::text( 'Bild Alt-Text' ),
					),
					'Profil hinzufuegen'
				),
			),
		);
	}

	/**
	 * Shared why Finora layout.
	 *
	 * @return array<string,mixed>
	 */
	protected static function layout_why_finora() {
		return array(
			'label'    => 'Why Finora',
			'template' => 'why_finora',
			'fields'   => array_merge(
				array(
					'title'     => self::heading_html( 'Titel' ),
					'subtitle'  => self::textarea( 'Untertitel' ),
					'body'      => self::editor( 'Einleitung' ),
					'blurbs'    => self::repeater(
						'Vorteile',
						array(
							'title'   => self::text( 'Titel' ),
							'content' => self::editor( 'Text' ),
						),
						'Vorteil hinzufuegen'
					),
					'image'     => self::image( 'Bild' ),
					'image_alt' => self::text( 'Bild Alt-Text' ),
				),
				self::button_fields( 'cta' )
			),
		);
	}

	/**
	 * Shared how-it-works layout.
	 *
	 * @return array<string,mixed>
	 */
	protected static function layout_how_it_works() {
		return array(
			'label'    => 'How It Works',
			'template' => 'how_it_works',
			'fields'   => array_merge(
				array(
					'title' => self::heading_html( 'Titel' ),
					'steps' => self::repeater(
						'Schritte',
						array(
							'icon_text' => self::text( 'Icon/Text' ),
							'title'     => self::text( 'Titel' ),
							'content'   => self::editor( 'Text' ),
						),
						'Schritt hinzufuegen'
					),
				),
				self::button_fields( 'cta' )
			),
		);
	}

	/**
	 * Shared testimonials layout.
	 *
	 * @return array<string,mixed>
	 */
	protected static function layout_testimonials() {
		return array(
			'label'    => 'Testimonials',
			'template' => 'testimonials',
			'fields'   => array(
				'title'    => self::heading_html( 'Titel' ),
				'subtitle' => self::textarea( 'Untertitel' ),
				'items'    => self::repeater(
					'Testimonials',
					array(
						'quote'          => self::editor( 'Zitat' ),
						'toggle_enabled' => self::checkbox( 'Mehr/Weniger aktiv', true ),
						'initials'       => self::text( 'Initialen' ),
						'name'           => self::text( 'Name' ),
						'role'           => self::text( 'Rolle' ),
					),
					'Testimonial hinzufuegen'
				),
			),
		);
	}

	/**
	 * Shared FAQ layout.
	 *
	 * @return array<string,mixed>
	 */
	protected static function layout_faq() {
		return array(
			'label'    => 'FAQ',
			'template' => 'faq',
			'fields'   => array(
				'title'            => self::heading_html( 'Titel' ),
				'intro'            => self::textarea( 'Einleitung' ),
				'background_image' => self::image( 'Hintergrundbild' ),
				'items'            => self::repeater(
					'Fragen',
					array(
						'question' => self::text( 'Frage' ),
						'answer'   => self::editor( 'Antwort' ),
					),
					'Frage hinzufuegen'
				),
			),
		);
	}

	/**
	 * Shared banner CTA layout.
	 *
	 * @return array<string,mixed>
	 */
	protected static function layout_banner_cta() {
		return array(
			'label'    => 'Banner CTA',
			'template' => 'banner_cta',
			'fields'   => array_merge(
				array(
					'title'            => self::heading_html( 'Titel' ),
					'body'             => self::editor( 'Text' ),
					'background_image' => self::image( 'Hintergrundbild' ),
				),
				self::button_fields( 'cta' )
			),
		);
	}

	/**
	 * About "Bedeutet" layout.
	 *
	 * @return array<string,mixed>
	 */
	protected static function layout_about_bedeutet() {
		return array(
			'label'    => 'Was bedeutet Beratung',
			'template' => 'about_bedeutet',
			'fields'   => array_merge(
				array(
					'title'       => self::heading_html( 'Titel' ),
					'left_body'   => self::editor( 'Linke Spalte' ),
					'right_title' => self::text( 'Rechte Spalte Titel' ),
					'right_items' => self::repeater(
						'Rechte Spalte Punkte',
						array(
							'content' => self::editor( 'Text' ),
						),
						'Punkt hinzufuegen'
					),
				),
				self::button_fields( 'cta' )
			),
		);
	}

	/**
	 * Shared media + text section.
	 *
	 * @return array<string,mixed>
	 */
	protected static function layout_media_text() {
		return array(
			'label'    => 'Bild/Text',
			'template' => 'media_text',
			'fields'   => array_merge(
				array(
					'title'          => self::heading_html( 'Titel' ),
					'body'           => self::editor( 'Text' ),
					'image'          => self::image( 'Bild' ),
					'image_alt'      => self::text( 'Bild Alt-Text' ),
					'image_position' => self::text( 'Bild Position (left/right)' ),
				),
				self::button_fields( 'cta' )
			),
		);
	}

	/**
	 * Shared workflow/blurb layout.
	 *
	 * @return array<string,mixed>
	 */
	protected static function layout_workflow_blurbs() {
		return array(
			'label'    => 'Workflow',
			'template' => 'workflow_blurbs',
			'fields'   => array_merge(
				array(
					'title'     => self::heading_html( 'Titel' ),
					'intro'     => self::editor( 'Einleitung' ),
					'items'     => self::repeater(
						'Punkte',
						array(
							'content' => self::editor( 'Text' ),
						),
						'Punkt hinzufuegen'
					),
					'highlight' => self::editor( 'Highlight' ),
				),
				self::button_fields( 'cta' )
			),
		);
	}

	/**
	 * Shared centered CTA layout.
	 *
	 * @param string $variant Variant key.
	 * @return array<string,mixed>
	 */
	protected static function layout_center_cta( $variant ) {
		return array(
			'label'    => 'CTA',
			'template' => 'center_cta',
			'variant'  => $variant,
			'fields'   => array_merge(
				array(
					'title' => self::heading_html( 'Titel' ),
					'body'  => self::editor( 'Text' ),
				),
				self::button_fields( 'cta' )
			),
		);
	}

	/**
	 * Shared tabs section layout.
	 *
	 * @return array<string,mixed>
	 */
	protected static function layout_tabs_section() {
		return array(
			'label'    => 'Tabs',
			'template' => 'tabs_section',
			'fields'   => array_merge(
				array(
					'title'     => self::heading_html( 'Titel' ),
					'intro'     => self::editor( 'Einleitung' ),
					'image'     => self::image( 'Bild' ),
					'image_alt' => self::text( 'Bild Alt-Text' ),
					'tabs'      => self::repeater(
						'Tabs',
						array(
							'title'   => self::text( 'Titel' ),
							'intro'   => self::heading_html( 'Einleitung' ),
							'bullets' => self::repeater(
								'Bullet Points',
								array(
									'text' => self::text( 'Text' ),
								),
								'Bullet hinzufuegen'
							),
							'outro'   => self::editor( 'Abschluss' ),
						),
						'Tab hinzufuegen'
					),
				),
				self::button_fields( 'cta' )
			),
		);
	}

	/**
	 * Retirement audience cards layout.
	 *
	 * @return array<string,mixed>
	 */
	protected static function layout_retirement_audience() {
		return array(
			'label'    => 'Zielgruppen',
			'template' => 'retirement_audience',
			'fields'   => array(
				'title'      => self::heading_html( 'Titel' ),
				'jump_links' => self::repeater(
					'Jump Links',
					array(
						'label'     => self::text( 'Label' ),
						'anchor_id' => self::text( 'Anchor-ID' ),
					),
					'Jump-Link hinzufuegen'
				),
				'cards'      => self::repeater(
					'Karten',
					array(
						'variant'         => self::text( 'Variante (highlight/large/small/default)' ),
						'anchor_id'       => self::text( 'Anchor-ID' ),
						'title'           => self::text( 'Titel' ),
						'intro'           => self::editor( 'Einleitung' ),
						'blurbs'          => self::repeater(
							'Blurbs',
							array(
								'title'   => self::text( 'Titel' ),
								'content' => self::editor( 'Text' ),
							),
							'Blurb hinzufuegen'
						),
						'button_label'    => self::text( 'Button Text' ),
						'button_page_key' => self::text( 'Button Zielseite (source_key)' ),
						'button_url'      => self::url( 'Button URL (Fallback/extern)' ),
					),
					'Karte hinzufuegen'
				),
			),
		);
	}

	/**
	 * Concepts section layout.
	 *
	 * @return array<string,mixed>
	 */
	protected static function layout_concepts_section() {
		return array(
			'label'    => 'Konzepte',
			'template' => 'concepts_section',
			'fields'   => array_merge(
				array(
					'title'     => self::heading_html( 'Titel' ),
					'intro'     => self::editor( 'Einleitung' ),
					'image'     => self::image( 'Bild' ),
					'image_alt' => self::text( 'Bild Alt-Text' ),
					'items'     => self::repeater(
						'Punkte',
						array(
							'content' => self::editor( 'Text' ),
						),
						'Punkt hinzufuegen'
					),
				),
				self::button_fields( 'cta' )
			),
		);
	}

	/**
	 * Shared blurb section with optional image.
	 *
	 * @return array<string,mixed>
	 */
	protected static function layout_blurb_image_section() {
		return array(
			'label'    => 'Bild + Blurbs',
			'template' => 'blurb_image_section',
			'fields'   => array_merge(
				array(
					'title'     => self::heading_html( 'Titel' ),
					'intro'     => self::editor( 'Einleitung' ),
					'image'     => self::image( 'Bild' ),
					'image_alt' => self::text( 'Bild Alt-Text' ),
					'items'     => self::repeater(
						'Punkte',
						array(
							'content' => self::editor( 'Text' ),
						),
						'Punkt hinzufuegen'
					),
				),
				self::button_fields( 'cta' )
			),
		);
	}

	/**
	 * Shared approach tiles layout.
	 *
	 * @return array<string,mixed>
	 */
	protected static function layout_approach_tiles() {
		return array(
			'label'    => 'Approach Tiles',
			'template' => 'approach_tiles',
			'fields'   => array_merge(
				array(
					'title' => self::heading_html( 'Titel' ),
					'body'  => self::editor( 'Text' ),
					'tiles' => self::repeater(
						'Tiles',
						array(
							'title'   => self::text( 'Titel' ),
							'content' => self::editor( 'Rueckseite' ),
						),
						'Tile hinzufuegen'
					),
				),
				self::button_fields( 'cta' )
			),
		);
	}

	/**
	 * Shared timeline layout.
	 *
	 * @return array<string,mixed>
	 */
	protected static function layout_timeline() {
		return array(
			'label'    => 'Timeline',
			'template' => 'timeline',
			'fields'   => array(
				'title' => self::heading_html( 'Titel' ),
				'intro' => self::editor( 'Einleitung' ),
				'items' => self::repeater(
					'Timeline Punkte',
					array(
						'number'  => self::text( 'Nummer' ),
						'icon'    => self::text( 'Icon CSS Klasse' ),
						'title'   => self::text( 'Titel' ),
						'body'    => self::editor( 'Text' ),
						'bullets' => self::repeater(
							'Bullet Points',
							array(
								'text' => self::text( 'Text' ),
							),
							'Bullet hinzufuegen'
						),
					),
					'Timeline Punkt hinzufuegen'
				),
			),
		);
	}

	/**
	 * Shared target groups layout.
	 *
	 * @return array<string,mixed>
	 */
	protected static function layout_target_groups() {
		return array(
			'label'    => 'Zielgruppen',
			'template' => 'target_groups',
			'fields'   => array_merge(
				array(
					'title'    => self::heading_html( 'Titel' ),
					'subtitle' => self::text( 'Untertitel' ),
					'items'    => self::repeater(
						'Zielgruppen',
						array(
							'content' => self::editor( 'Text' ),
						),
						'Zielgruppe hinzufuegen'
					),
					'summary'  => self::editor( 'Zusammenfassung' ),
				),
				self::button_fields( 'cta' )
			),
		);
	}

	/**
	 * Shared results section.
	 *
	 * @return array<string,mixed>
	 */
	protected static function layout_results_section() {
		return array(
			'label'    => 'Ergebnisse',
			'template' => 'results_section',
			'fields'   => array_merge(
				array(
					'title'     => self::heading_html( 'Titel' ),
					'intro'     => self::editor( 'Einleitung' ),
					'image'     => self::image( 'Bild' ),
					'image_alt' => self::text( 'Bild Alt-Text' ),
					'items'     => self::repeater(
						'Punkte',
						array(
							'content' => self::editor( 'Text' ),
						),
						'Punkt hinzufuegen'
					),
				),
				self::button_fields( 'cta' )
			),
		);
	}

	/**
	 * Real estate intro layout.
	 *
	 * @return array<string,mixed>
	 */
	protected static function layout_real_estate_intro() {
		return array(
			'label'    => 'Immobilien Intro',
			'template' => 'real_estate_intro',
			'fields'   => array(
				'image'           => self::image( 'Bild' ),
				'image_alt'       => self::text( 'Bild Alt-Text' ),
				'stats'           => self::repeater(
					'Profilwerte',
					array(
						'value' => self::text( 'Wert' ),
						'label' => self::text( 'Label' ),
					),
					'Wert hinzufuegen'
				),
				'goals_title'     => self::heading_html( 'Ziele Titel' ),
				'goals_body'      => self::editor( 'Ziele Text' ),
				'challenge_title' => self::heading_html( 'Herausforderung Titel' ),
				'challenge_body'  => self::editor( 'Herausforderung Text' ),
				'blurbs'          => self::repeater(
					'Probleme',
					array(
						'title'   => self::text( 'Titel' ),
						'content' => self::editor( 'Text' ),
					),
					'Problem hinzufuegen'
				),
			),
		);
	}

	/**
	 * Calculator layout.
	 *
	 * @return array<string,mixed>
	 */
	protected static function layout_calculator() {
		return array(
			'label'    => 'Berechnung',
			'template' => 'calculator',
			'fields'   => array(
				'title'    => self::heading_html( 'Titel' ),
				'subtitle' => self::editor( 'Untertitel' ),
				'cards'    => self::repeater(
					'Karten',
					array(
						'title'    => self::text( 'Titel' ),
						'icon'     => self::text( 'Icon CSS Klasse' ),
						'featured' => self::checkbox( 'Featured' ),
						'rows'     => self::repeater(
							'Zeilen',
							array(
								'label'    => self::text( 'Label' ),
								'value'    => self::text( 'Wert' ),
								'modifier' => self::text( 'Modifier (plus/minus/subtotal/highlight/hero/accent)' ),
							),
							'Zeile hinzufuegen'
						),
					),
					'Karte hinzufuegen'
				),
				'kpis'     => self::repeater(
					'KPIs',
					array(
						'value'  => self::text( 'Wert' ),
						'label'  => self::text( 'Label' ),
						'accent' => self::checkbox( 'Accent' ),
					),
					'KPI hinzufuegen'
				),
			),
		);
	}

	/**
	 * Case highlight layout.
	 *
	 * @return array<string,mixed>
	 */
	protected static function layout_case_highlight() {
		return array(
			'label'    => 'Fallbeispiel',
			'template' => 'case_highlight',
			'fields'   => array(
				'image'     => self::image( 'Bild' ),
				'image_alt' => self::text( 'Bild Alt-Text' ),
				'title'     => self::heading_html( 'Titel' ),
				'body'      => self::editor( 'Text' ),
				'quote'     => self::editor( 'Zitat' ),
			),
		);
	}

	/**
	 * Dark CTA layout.
	 *
	 * @return array<string,mixed>
	 */
	protected static function layout_dark_cta() {
		return array(
			'label'    => 'Dark CTA',
			'template' => 'dark_cta',
			'fields'   => array_merge(
				array(
					'title' => self::heading_html( 'Titel' ),
					'body'  => self::editor( 'Text' ),
				),
				self::button_fields( 'cta' )
			),
		);
	}

	/**
	 * Responsibility section layout.
	 *
	 * @return array<string,mixed>
	 */
	protected static function layout_responsibility() {
		return array(
			'label'    => 'Verantwortung',
			'template' => 'responsibility',
			'fields'   => array_merge(
				array(
					'title'     => self::heading_html( 'Titel' ),
					'intro'     => self::editor( 'Einleitung' ),
					'image'     => self::image( 'Bild' ),
					'image_alt' => self::text( 'Bild Alt-Text' ),
					'lead'      => self::editor( 'Lead' ),
					'items'     => self::repeater(
						'Punkte',
						array(
							'content' => self::editor( 'Text' ),
						),
						'Punkt hinzufuegen'
					),
					'note'      => self::editor( 'Hinweis' ),
				),
				self::button_fields( 'cta' )
			),
		);
	}

	/**
	 * New phase section layout.
	 *
	 * @return array<string,mixed>
	 */
	protected static function layout_new_phase() {
		return array(
			'label'    => 'Neue Vermoegensphase',
			'template' => 'new_phase',
			'fields'   => array_merge(
				array(
					'image'     => self::image( 'Bild' ),
					'image_alt' => self::text( 'Bild Alt-Text' ),
					'title'     => self::heading_html( 'Titel' ),
					'body'      => self::editor( 'Text' ),
					'items'     => self::repeater(
						'Punkte',
						array(
							'content' => self::editor( 'Text' ),
						),
						'Punkt hinzufuegen'
					),
				),
				self::button_fields( 'cta' )
			),
		);
	}

	/**
	 * Outcomes flipbox layout.
	 *
	 * @return array<string,mixed>
	 */
	protected static function layout_outcomes() {
		return array(
			'label'    => 'Outcomes',
			'template' => 'outcomes',
			'fields'   => array(
				'title' => self::heading_html( 'Titel' ),
				'intro' => self::editor( 'Einleitung' ),
				'body'  => self::editor( 'Text' ),
				'items' => self::repeater(
					'Flipboxen',
					array(
						'icon'    => self::text( 'Icon CSS Klasse' ),
						'title'   => self::text( 'Titel' ),
						'content' => self::editor( 'Text' ),
					),
					'Flipbox hinzufuegen'
				),
			),
		);
	}

	/**
	 * Target groups with image layout.
	 *
	 * @return array<string,mixed>
	 */
	protected static function layout_target_groups_image() {
		return array(
			'label'    => 'Zielgruppe mit Bild',
			'template' => 'target_groups_image',
			'fields'   => array_merge(
				array(
					'title'     => self::heading_html( 'Titel' ),
					'intro'     => self::editor( 'Einleitung' ),
					'items'     => self::repeater(
						'Punkte',
						array(
							'content' => self::editor( 'Text' ),
						),
						'Punkt hinzufuegen'
					),
					'image'     => self::image( 'Bild' ),
					'image_alt' => self::text( 'Bild Alt-Text' ),
				),
				self::button_fields( 'cta' )
			),
		);
	}

	/**
	 * Audience cards layout.
	 *
	 * @param string $variant Variant.
	 * @return array<string,mixed>
	 */
	protected static function layout_audience_cards( $variant ) {
		return array(
			'label'    => 'Audience Cards',
			'template' => 'audience_cards',
			'variant'  => $variant,
			'fields'   => array(
				'title' => self::heading_html( 'Titel' ),
				'items' => self::repeater(
					'Karten',
					array(
						'title'           => self::text( 'Titel' ),
						'content'         => self::editor( 'Text' ),
						'button_label'    => self::text( 'Button Text' ),
						'button_page_key' => self::text( 'Button Zielseite (source_key)' ),
						'button_url'      => self::url( 'Button URL (Fallback/extern)' ),
						'is_empty'        => self::checkbox( 'Leere Platzhalter-Karte' ),
					),
					'Karte hinzufuegen'
				),
			),
		);
	}

	/**
	 * Media detail section with blurbs.
	 *
	 * @param string $variant Variant.
	 * @return array<string,mixed>
	 */
	protected static function layout_media_blurbs( $variant ) {
		return array(
			'label'    => 'Media Blurbs',
			'template' => 'media_blurbs',
			'variant'  => $variant,
			'fields'   => array_merge(
				array(
					'title'     => self::heading_html( 'Titel' ),
					'subtitle'  => self::textarea( 'Untertitel' ),
					'body'      => self::editor( 'Text' ),
					'blurbs'    => self::repeater(
						'Blurbs',
						array(
							'title'   => self::text( 'Titel' ),
							'content' => self::editor( 'Text' ),
						),
						'Blurb hinzufuegen'
					),
					'image'     => self::image( 'Bild' ),
					'image_alt' => self::text( 'Bild Alt-Text' ),
				),
				self::button_fields( 'cta' )
			),
		);
	}

	/**
	 * Philosophy invest detail layout.
	 *
	 * @return array<string,mixed>
	 */
	protected static function layout_invest_detail() {
		return array(
			'label'    => 'Investment Detail',
			'template' => 'invest_detail',
			'fields'   => array_merge(
				array(
					'title'           => self::heading_html( 'Titel' ),
					'subtitle'        => self::textarea( 'Untertitel' ),
					'body'            => self::editor( 'Text' ),
					'image'           => self::image( 'Bild' ),
					'image_alt'       => self::text( 'Bild Alt-Text' ),
					'explainer_title' => self::heading_html( 'Megatrends Titel' ),
					'explainer_body'  => self::editor( 'Megatrends Text' ),
					'explainer_sub'   => self::text( 'Megatrends Subline' ),
					'trends'          => self::repeater(
						'Megatrends',
						array(
							'icon'    => self::text( 'Icon CSS Klasse' ),
							'title'   => self::text( 'Titel' ),
							'content' => self::editor( 'Text' ),
						),
						'Megatrend hinzufuegen'
					),
					'outro'           => self::editor( 'Outro' ),
				),
				self::button_fields( 'cta' )
			),
		);
	}

	/**
	 * Philosophy tax detail layout.
	 *
	 * @return array<string,mixed>
	 */
	protected static function layout_tax_detail() {
		return array(
			'label'    => 'Tax Detail',
			'template' => 'tax_detail',
			'fields'   => array_merge(
				array(
					'title'       => self::heading_html( 'Titel' ),
					'subtitle'    => self::textarea( 'Untertitel' ),
					'body'        => self::editor( 'Text' ),
					'how_title'   => self::text( 'Wie das funktioniert Titel' ),
					'how_body'    => self::editor( 'Wie das funktioniert Text' ),
					'image'       => self::image( 'Bild' ),
					'image_alt'   => self::text( 'Bild Alt-Text' ),
					'steps_title' => self::heading_html( 'Schritte Titel' ),
					'steps'       => self::repeater(
						'Schritte',
						array(
							'icon'    => self::text( 'Icon/Text' ),
							'title'   => self::text( 'Titel' ),
							'content' => self::editor( 'Text' ),
						),
						'Schritt hinzufuegen'
					),
				),
				self::button_fields( 'cta' )
			),
		);
	}

	/**
	 * Contact main layout.
	 *
	 * @return array<string,mixed>
	 */
	protected static function layout_contact_main() {
		return array(
			'label'    => 'Kontakt Hauptbereich',
			'template' => 'contact_main',
			'fields'   => array(
				'title'         => self::heading_html( 'Titel' ),
				'intro'         => self::editor( 'Einleitung' ),
				'submit_label'  => self::text( 'Fallback Button Text' ),
				'privacy_label' => self::editor( 'Datenschutz Hinweis' ),
				'privacy_page'  => self::text( 'Datenschutz Zielseite (source_key)' ),
				'info_cards'    => self::repeater(
					'Info Karten',
					array(
						'title'   => self::text( 'Titel' ),
						'type'    => self::text( 'Typ (address/phone/email/link)' ),
						'value'   => self::editor( 'Inhalt' ),
						'href'    => self::url( 'Link URL' ),
					),
					'Info Karte hinzufuegen'
				),
			),
		);
	}

	/**
	 * Thank-you page main section layout.
	 *
	 * @return array<string,mixed>
	 */
	protected static function layout_danke_main() {
		return array(
			'label'    => 'Danke Hauptbereich',
			'template' => 'danke_main',
			'fields'   => array_merge(
				array(
					'body' => self::editor( 'Text' ),
				),
				self::button_fields( 'primary' ),
				self::button_fields( 'secondary' )
			),
		);
	}
}
