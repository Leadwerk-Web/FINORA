<?php
/**
 * Structured FINORA field filling from static HTML sources.
 *
 * @package Leadwerk_Importer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Leadwerk_ACF_Filler {

	/** @var string */
	protected $source_root = '';

	/** @var Leadwerk_Media_Importer|null */
	protected $media_importer = null;

	/** @var array<string,array<string,int>> */
	protected $page_lookup = array();

	/** @var array<string,int> */
	protected $attachment_cache = array();

	/**
	 * Constructor.
	 *
	 * @param string                        $source_root    Source root.
	 * @param Leadwerk_Media_Importer|null  $media_importer Media importer.
	 * @param array<string,array<string,int>> $page_lookup  Page lookup.
	 */
	public function __construct( $source_root = '', $media_importer = null, $page_lookup = array() ) {
		$this->source_root    = rtrim( (string) $source_root, '/\\' );
		$this->media_importer = $media_importer instanceof Leadwerk_Media_Importer ? $media_importer : null;
		$this->page_lookup    = is_array( $page_lookup ) ? $page_lookup : array();
	}

	/**
	 * Build one structured payload from disk.
	 *
	 * @param array<string,mixed> $page_config            Page config.
	 * @param string              $lang                   Language code.
	 * @param string              $override_relative_file Optional alternative file path.
	 * @return array<string,mixed>
	 */
	public function build_page_payload( $page_config, $lang = 'de', $override_relative_file = '' ) {
		$relative_file = '' !== trim( (string) $override_relative_file )
			? (string) $override_relative_file
			: (string) ( $page_config['source_file'] ?? '' );
		$file_path     = $this->resolve_source_path( $relative_file );

		if ( '' === $relative_file || ! is_file( $file_path ) ) {
			Leadwerk_Logger::log( 'HTML-Datei nicht gefunden: ' . $relative_file );
			return array();
		}

		$html = file_get_contents( $file_path );
		if ( false === $html ) {
			Leadwerk_Logger::log( 'HTML-Datei konnte nicht gelesen werden: ' . $relative_file );
			return array();
		}

		return $this->build_page_payload_from_html( $page_config, (string) $html, $lang );
	}

	/**
	 * Build one payload directly from HTML.
	 *
	 * @param array<string,mixed> $page_config Page config.
	 * @param string              $html        HTML string.
	 * @param string              $lang        Language code.
	 * @return array<string,mixed>
	 */
	public function build_page_payload_from_html( $page_config, $html, $lang = 'de' ) {
		list( $dom, $xpath ) = $this->create_dom_xpath( $html );

		$payload = array(
			'body_class'       => $this->attr( $xpath, '//body', 'class' ),
			'document_title'   => $this->text( $xpath, '//title' ),
			'meta_description' => $this->attr( $xpath, '//meta[@name="description"]', 'content' ),
			'value'            => array(),
			'validation'       => array(),
			'layout_diagnostics' => array(),
		);

		$group = Leadwerk_Content_Schema::get_group( (string) ( $page_config['field_name'] ?? '' ) );
		if ( ! $group ) {
			return $payload;
		}

		if ( empty( $group['layouts'] ) ) {
			$legal_match      = $this->resolve_legal_match( $xpath );
			$payload['value'] = $this->normalize_group_value( (string) $page_config['field_name'], $this->build_legal_value( $xpath, $legal_match['node'] ?? null ) );
			$payload['validation'] = $this->build_payload_validation( (string) $page_config['field_name'], $group, $payload['value'], 1 );
			$payload['layout_diagnostics'] = $this->build_layout_diagnostics(
				(string) $page_config['field_name'],
				$group,
				$payload['value'],
				array(
					'legal_content' => array(
						'matched_by'    => (string) ( $legal_match['matched_by'] ?? 'missing' ),
						'selector_used' => (string) ( $legal_match['selector_used'] ?? '.legal-content' ),
						'found'         => ! empty( $legal_match['found'] ),
						'source_index'  => 0,
					),
				)
			);
			return $payload;
		}

		$section_match     = $this->resolve_group_section_nodes( (string) $page_config['field_name'], $group, $xpath );
		$sections          = (array) ( $section_match['sections'] ?? array() );
		$normalized_values = array();
		$index             = 0;

		foreach ( $group['layouts'] as $layout_key => $layout_schema ) {
			$layout_match         = (array) ( $section_match['matches'][ $layout_key ] ?? array() );
			$section_node         = $layout_match['node'] ?? ( $sections[ $index ] ?? null );
			$normalized_values[]  = $this->normalize_layout_value(
				(string) $page_config['field_name'],
				$layout_key,
				$this->parse_layout_value( $layout_key, $layout_schema, $section_node, $lang )
			);
			++$index;
		}

		if ( count( $sections ) !== count( $group['layouts'] ) ) {
			Leadwerk_Logger::log(
				sprintf(
					'Sektionen/Layouts Anzahl abweichend fuer %s (%s): %d HTML vs %d Schema',
					(string) ( $page_config['source_file'] ?? '' ),
					$lang,
					count( $sections ),
					count( $group['layouts'] )
				)
			);
		}

		$payload['value'] = $normalized_values;
		$payload['validation'] = $this->build_payload_validation( (string) $page_config['field_name'], $group, $payload['value'], count( $sections ) );
		$payload['layout_diagnostics'] = $this->build_layout_diagnostics(
			(string) $page_config['field_name'],
			$group,
			$payload['value'],
			(array) ( $section_match['matches'] ?? array() )
		);
		return $payload;
	}

	/**
	 * Build structured payload from a set of section HTML fragments.
	 *
	 * @param array<string,mixed> $page_config Page config.
	 * @param array<int,string>   $sections    Section HTML fragments.
	 * @param array<string,mixed> $metadata    Metadata fallback.
	 * @param string              $lang        Language code.
	 * @return array<string,mixed>
	 */
	public function build_page_payload_from_sections( $page_config, $sections, $metadata = array(), $lang = 'de' ) {
		$payload = array(
			'body_class'       => (string) ( $metadata['body_class'] ?? '' ),
			'document_title'   => (string) ( $metadata['document_title'] ?? '' ),
			'meta_description' => (string) ( $metadata['meta_description'] ?? '' ),
			'value'            => array(),
			'validation'       => array(),
			'layout_diagnostics' => array(),
		);

		$group = Leadwerk_Content_Schema::get_group( (string) ( $page_config['field_name'] ?? '' ) );
		if ( ! $group ) {
			return $payload;
		}

		if ( empty( $group['layouts'] ) ) {
			return $payload;
		}

		$sections = is_array( $sections ) ? array_values( $sections ) : array();
		$values   = array();
		$index    = 0;

		foreach ( $group['layouts'] as $layout_key => $layout_schema ) {
			$section_html = isset( $sections[ $index ] ) ? (string) $sections[ $index ] : '';
			$values[]     = $this->normalize_layout_value(
				(string) $page_config['field_name'],
				$layout_key,
				$this->parse_layout_html( $layout_key, $layout_schema, $section_html, $lang )
			);
			++$index;
		}

		$payload['value'] = $values;
		$payload['validation'] = $this->build_payload_validation( (string) $page_config['field_name'], $group, $payload['value'], count( $sections ) );
		$payload['layout_diagnostics'] = $this->build_layout_diagnostics( (string) $page_config['field_name'], $group, $payload['value'] );
		return $payload;
	}

	/**
	 * Validate stored group values against the structured schema.
	 *
	 * @param string $field_name Field group name.
	 * @param mixed  $value      Stored value.
	 * @return array<string,mixed>
	 */
	public function validate_group_value( $field_name, $value ) {
		$group = Leadwerk_Content_Schema::get_group( (string) $field_name );
		if ( ! $group ) {
			return array(
				'field_name'            => (string) $field_name,
				'has_visible_content'   => false,
				'visible_content_score' => 0,
				'empty_fields'          => array(),
				'empty_layouts'         => array(),
			);
		}

		$parsed_count = is_array( $value ) ? count( $value ) : ( empty( $group['layouts'] ) ? 1 : 0 );
		return $this->build_payload_validation( (string) $field_name, $group, $value, $parsed_count );
	}

	/**
	 * Build validation metrics for one payload.
	 *
	 * @param string              $field_name            Field group name.
	 * @param array<string,mixed> $group                 Group schema.
	 * @param mixed               $value                 Payload value.
	 * @param int                 $parsed_section_count  Parsed section count.
	 * @return array<string,mixed>
	 */
	protected function build_payload_validation( $field_name, $group, $value, $parsed_section_count = 0 ) {
		$validation = array(
			'field_name'             => $field_name,
			'is_legal'               => empty( $group['layouts'] ),
			'expected_layout_count'  => ! empty( $group['layouts'] ) ? count( $group['layouts'] ) : 1,
			'parsed_layout_count'    => is_array( $value ) && ! empty( $group['layouts'] ) ? count( $value ) : ( empty( $group['layouts'] ) ? 1 : 0 ),
			'parsed_section_count'   => max( 0, (int) $parsed_section_count ),
			'non_empty_layout_count' => 0,
			'missing_sections'       => 0,
			'empty_fields'           => array(),
			'empty_layouts'          => array(),
			'visible_content_score'  => 0,
			'has_visible_content'    => false,
		);

		if ( empty( $group['layouts'] ) ) {
			$fields = (array) ( $group['fields'] ?? array() );
			foreach ( $fields as $field_key => $definition ) {
				$field_report = $this->summarize_definition_visibility(
					is_array( $value ) && array_key_exists( $field_key, $value ) ? $value[ $field_key ] : Leadwerk_Content_Schema::get_default_value( $definition ),
					$definition,
					$field_key
				);
				$validation['visible_content_score'] += (int) $field_report['visible_count'];
				if ( ! empty( $field_report['empty_fields'] ) ) {
					$validation['empty_fields'] = array_merge( $validation['empty_fields'], $field_report['empty_fields'] );
				}
			}

			$validation['non_empty_layout_count'] = $validation['visible_content_score'] > 0 ? 1 : 0;
			$validation['has_visible_content']    = $validation['visible_content_score'] > 0;
			if ( ! $validation['has_visible_content'] ) {
				$validation['empty_layouts'][] = 'legal_content';
			}

			return $validation;
		}

		$sections         = is_array( $value ) ? array_values( $value ) : array();
		$expected_layouts = (array) ( $group['layouts'] ?? array() );
		$validation['missing_sections'] = max( 0, count( $expected_layouts ) - max( count( $sections ), (int) $parsed_section_count ) );

		$section_index = 0;
		foreach ( $expected_layouts as $layout_key => $layout_schema ) {
			$section      = isset( $sections[ $section_index ] ) && is_array( $sections[ $section_index ] )
				? $sections[ $section_index ]
				: $this->empty_layout_value( $layout_key, $layout_schema );
			$section_path = 'layout:' . $layout_key;
			$section_has_visible_content = false;

			foreach ( (array) ( $layout_schema['fields'] ?? array() ) as $field_key => $definition ) {
				$field_report = $this->summarize_definition_visibility(
					array_key_exists( $field_key, $section ) ? $section[ $field_key ] : Leadwerk_Content_Schema::get_default_value( $definition ),
					$definition,
					$section_path . '.' . $field_key
				);
				$validation['visible_content_score'] += (int) $field_report['visible_count'];
				if ( ! empty( $field_report['empty_fields'] ) ) {
					$validation['empty_fields'] = array_merge( $validation['empty_fields'], $field_report['empty_fields'] );
				}
				if ( (int) $field_report['visible_count'] > 0 ) {
					$section_has_visible_content = true;
				}
			}

			if ( ! $section_has_visible_content && $this->section_has_override_visible_content( $field_name, $layout_key, $section ) ) {
				$section_has_visible_content = true;
				++$validation['visible_content_score'];
			}

			if ( $section_has_visible_content ) {
				++$validation['non_empty_layout_count'];
			} else {
				$validation['empty_layouts'][] = $layout_key;
			}

			++$section_index;
		}

		$validation['has_visible_content'] = $validation['visible_content_score'] > 0 && $validation['non_empty_layout_count'] > 0;
		return $validation;
	}

	/**
	 * Build per-layout diagnostics for one payload.
	 *
	 * @param string                   $field_name    Field group name.
	 * @param array<string,mixed>      $group         Group schema.
	 * @param mixed                    $value         Payload value.
	 * @param array<string,array<string,mixed>> $matches Base match diagnostics.
	 * @return array<int,array<string,mixed>>
	 */
	protected function build_layout_diagnostics( $field_name, $group, $value, $matches = array() ) {
		$diagnostics = array();

		if ( empty( $group['layouts'] ) ) {
			$base          = (array) ( $matches['legal_content'] ?? array() );
			$visible_count = 0;
			$empty_fields  = array();

			foreach ( (array) ( $group['fields'] ?? array() ) as $field_key => $definition ) {
				$field_report   = $this->summarize_definition_visibility(
					is_array( $value ) && array_key_exists( $field_key, $value ) ? $value[ $field_key ] : Leadwerk_Content_Schema::get_default_value( $definition ),
					$definition,
					'layout:legal_content.' . $field_key
				);
				$visible_count += (int) $field_report['visible_count'];
				$empty_fields   = array_merge( $empty_fields, (array) ( $field_report['empty_fields'] ?? array() ) );
			}

			$diagnostics[] = array(
				'layout_key'                  => 'legal_content',
				'label'                       => 'Legal Content',
				'matched_by'                  => (string) ( $base['matched_by'] ?? 'missing' ),
				'selector_used'               => (string) ( $base['selector_used'] ?? '.legal-content' ),
				'source_index'                => (int) ( $base['source_index'] ?? 0 ),
				'found'                       => ! empty( $base['found'] ),
				'layout_has_visible_content'  => $visible_count > 0,
				'visible_content_score'       => $visible_count,
				'empty_fields'                => array_values( array_unique( $empty_fields ) ),
			);

			return $diagnostics;
		}

		$sections         = is_array( $value ) ? array_values( $value ) : array();
		$section_index    = 0;
		foreach ( (array) ( $group['layouts'] ?? array() ) as $layout_key => $layout_schema ) {
			$section       = isset( $sections[ $section_index ] ) && is_array( $sections[ $section_index ] )
				? $sections[ $section_index ]
				: $this->empty_layout_value( $layout_key, $layout_schema );
			$base          = (array) ( $matches[ $layout_key ] ?? array() );
			$visible_count = 0;
			$empty_fields  = array();

			foreach ( (array) ( $layout_schema['fields'] ?? array() ) as $field_key => $definition ) {
				$field_report   = $this->summarize_definition_visibility(
					array_key_exists( $field_key, $section ) ? $section[ $field_key ] : Leadwerk_Content_Schema::get_default_value( $definition ),
					$definition,
					'layout:' . $layout_key . '.' . $field_key
				);
				$visible_count += (int) $field_report['visible_count'];
				$empty_fields   = array_merge( $empty_fields, (array) ( $field_report['empty_fields'] ?? array() ) );
			}

			$layout_has_visible_content = $visible_count > 0;
			if ( ! $layout_has_visible_content && $this->section_has_override_visible_content( $field_name, $layout_key, $section ) ) {
				$layout_has_visible_content = true;
				++$visible_count;
			}

			$diagnostics[] = array(
				'layout_key'                 => (string) $layout_key,
				'label'                      => (string) ( $layout_schema['label'] ?? $layout_key ),
				'matched_by'                 => (string) ( $base['matched_by'] ?? 'index' ),
				'selector_used'              => (string) ( $base['selector_used'] ?? '' ),
				'source_index'               => (int) ( $base['source_index'] ?? $section_index ),
				'found'                      => ! array_key_exists( 'found', $base ) || ! empty( $base['found'] ),
				'layout_has_visible_content' => $layout_has_visible_content,
				'visible_content_score'      => $visible_count,
				'empty_fields'               => array_values( array_unique( $empty_fields ) ),
			);
			++$section_index;
		}

		return $diagnostics;
	}

	/**
	 * Decide whether a layout should count as visible based on important fields.
	 *
	 * @param string              $field_name Field group name.
	 * @param string              $layout_key Layout key.
	 * @param array<string,mixed> $section    Section data.
	 * @return bool
	 */
	protected function section_has_override_visible_content( $field_name, $layout_key, $section ) {
		$important_paths = array();

		if ( 'finora_home_sections' === $field_name && 'hero_slider' === $layout_key ) {
			$important_paths = array( 'slides', 'services', 'prev_label', 'next_label' );
		} elseif ( 'finora_contact_sections' === $field_name && 'contact_main' === $layout_key ) {
			$important_paths = array( 'title', 'intro', 'privacy_label', 'info_cards' );
		} elseif ( 'finora_real_estate_sections' === $field_name && in_array( $layout_key, array( 'intro', 'calculator', 'case', 'final_cta' ), true ) ) {
			$important_paths = array_keys( is_array( $section ) ? $section : array() );
		}

		foreach ( $important_paths as $path ) {
			if ( array_key_exists( $path, (array) $section ) && $this->value_has_visible_content( $section[ $path ] ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Whether a scalar/array value contains visible content.
	 *
	 * @param mixed $value Value.
	 * @return bool
	 */
	protected function value_has_visible_content( $value ) {
		if ( is_numeric( $value ) ) {
			return 0 !== (int) $value;
		}

		if ( is_string( $value ) ) {
			return '' !== trim( wp_strip_all_tags( $value ) );
		}

		if ( is_array( $value ) ) {
			foreach ( $value as $item ) {
				if ( $this->value_has_visible_content( $item ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Summarize visibility for one schema field definition.
	 *
	 * @param mixed               $value      Value.
	 * @param array<string,mixed> $definition Definition.
	 * @param string              $path       Logical path.
	 * @return array<string,mixed>
	 */
	protected function summarize_definition_visibility( $value, $definition, $path ) {
		$type = $definition['type'] ?? 'text';

		switch ( $type ) {
			case 'image':
				$visible = absint( $value ) > 0;
				return array(
					'visible_count' => $visible ? 1 : 0,
					'empty_fields'  => $visible ? array() : array( $path ),
				);

			case 'checkbox':
				return array(
					'visible_count' => ! empty( $value ) ? 1 : 0,
					'empty_fields'  => array(),
				);

			case 'repeater':
				$rows          = is_array( $value ) ? array_values( $value ) : array();
				$visible_count = 0;
				$empty_fields  = array();

				foreach ( $rows as $row_index => $row ) {
					if ( ! is_array( $row ) ) {
						continue;
					}

					$row_visible = 0;
					foreach ( (array) ( $definition['fields'] ?? array() ) as $sub_key => $sub_definition ) {
						$field_report = $this->summarize_definition_visibility(
							array_key_exists( $sub_key, $row ) ? $row[ $sub_key ] : Leadwerk_Content_Schema::get_default_value( $sub_definition ),
							$sub_definition,
							$path . '[' . $row_index . '].' . $sub_key
						);
						$row_visible    += (int) $field_report['visible_count'];
						$visible_count  += (int) $field_report['visible_count'];
						$empty_fields    = array_merge( $empty_fields, (array) $field_report['empty_fields'] );
					}

					if ( 0 === $row_visible ) {
						$empty_fields[] = $path . '[' . $row_index . ']';
					}
				}

				if ( empty( $rows ) ) {
					$empty_fields[] = $path;
				}

				return array(
					'visible_count' => $visible_count,
					'empty_fields'  => array_values( array_unique( $empty_fields ) ),
				);

			case 'classic_editor':
			case 'wysiwyg':
			case 'heading_html':
				$visible = '' !== trim( wp_strip_all_tags( (string) $value ) );
				return array(
					'visible_count' => $visible ? 1 : 0,
					'empty_fields'  => $visible ? array() : array( $path ),
				);

			case 'url':
			case 'text':
			case 'textarea':
			default:
				$visible = '' !== trim( (string) $value );
				return array(
					'visible_count' => $visible ? 1 : 0,
					'empty_fields'  => $visible ? array() : array( $path ),
				);
		}
	}

	/**
	 * Parse one layout from a section HTML fragment.
	 *
	 * @param string              $layout_key    Layout key.
	 * @param array<string,mixed> $layout_schema Layout schema.
	 * @param string              $html          Section HTML.
	 * @param string              $lang          Language code.
	 * @return array<string,mixed>
	 */
	protected function parse_layout_html( $layout_key, $layout_schema, $html, $lang ) {
		if ( '' === trim( $html ) ) {
			return $this->empty_layout_value( $layout_key, $layout_schema );
		}

		$wrapped = '<section class="leadwerk-temp-section">' . $html . '</section>';
		list( $dom, $xpath ) = $this->create_dom_xpath( $wrapped );
		$section_node        = $this->first_node( $xpath, '//section[contains(@class,"leadwerk-temp-section")]' );

		return $this->parse_layout_value( $layout_key, $layout_schema, $section_node, $lang );
	}

	/**
	 * Normalize one parsed layout value against the schema.
	 *
	 * @param string              $field_name Field group name.
	 * @param string              $layout_key Layout key.
	 * @param array<string,mixed> $value      Raw value.
	 * @return array<string,mixed>
	 */
	protected function normalize_layout_value( $field_name, $layout_key, $value ) {
		$schema = Leadwerk_Content_Schema::get_layout( $field_name, $layout_key );
		if ( ! $schema ) {
			return array( 'acf_fc_layout' => $layout_key );
		}

		$out                  = array();
		$out['acf_fc_layout'] = $layout_key;

		foreach ( (array) ( $schema['fields'] ?? array() ) as $field_key => $definition ) {
			$raw              = is_array( $value ) && array_key_exists( $field_key, $value ) ? $value[ $field_key ] : Leadwerk_Content_Schema::get_default_value( $definition );
			$out[ $field_key ] = $this->normalize_value_by_definition( $raw, $definition );
		}

		return $out;
	}

	/**
	 * Normalize a scalar group value.
	 *
	 * @param string              $field_name Field group name.
	 * @param array<string,mixed> $value      Raw value.
	 * @return array<string,mixed>
	 */
	protected function normalize_group_value( $field_name, $value ) {
		$group = Leadwerk_Content_Schema::get_group( $field_name );
		if ( ! $group || empty( $group['fields'] ) ) {
			return array();
		}

		$out = array();
		foreach ( (array) $group['fields'] as $field_key => $definition ) {
			$raw            = is_array( $value ) && array_key_exists( $field_key, $value ) ? $value[ $field_key ] : Leadwerk_Content_Schema::get_default_value( $definition );
			$out[ $field_key ] = $this->normalize_value_by_definition( $raw, $definition );
		}

		return $out;
	}

	/**
	 * Normalize one field value by definition.
	 *
	 * @param mixed               $value      Raw value.
	 * @param array<string,mixed> $definition Field definition.
	 * @return mixed
	 */
	protected function normalize_value_by_definition( $value, $definition ) {
		$type = $definition['type'] ?? 'text';

		switch ( $type ) {
			case 'text':
				return trim( (string) $value );
			case 'url':
				return esc_url_raw( (string) $value );
			case 'textarea':
				return trim( (string) $value );
			case 'classic_editor':
			case 'wysiwyg':
				return trim( (string) $value );
			case 'heading_html':
				return Leadwerk_Content_Schema::sanitize_heading_html( (string) $value );
			case 'image':
				return absint( $value );
			case 'checkbox':
				return ! empty( $value );
			case 'repeater':
				$rows = is_array( $value ) ? array_values( $value ) : array();
				$out  = array();
				foreach ( $rows as $row ) {
					if ( ! is_array( $row ) ) {
						continue;
					}
					$item = array();
					foreach ( (array) ( $definition['fields'] ?? array() ) as $sub_key => $sub_definition ) {
						$item[ $sub_key ] = $this->normalize_value_by_definition(
							array_key_exists( $sub_key, $row ) ? $row[ $sub_key ] : Leadwerk_Content_Schema::get_default_value( $sub_definition ),
							$sub_definition
						);
					}
					$out[] = $item;
				}
				return $out;
			default:
				return trim( (string) $value );
		}
	}

	/**
	 * Resolve section nodes for one field group with selector fallbacks.
	 *
	 * @param string                   $field_name Field group name.
	 * @param array<string,mixed>      $group      Group schema.
	 * @param DOMXPath                 $xpath      XPath instance.
	 * @return array<string,mixed>
	 */
	protected function resolve_group_section_nodes( $field_name, $group, $xpath ) {
		$sections      = $this->extract_body_sections( $xpath );
		$fallbacks     = $this->get_selector_fallbacks( $field_name );
		$used_nodes    = array();
		$matches       = array();
		$section_index = 0;

		foreach ( (array) ( $group['layouts'] ?? array() ) as $layout_key => $layout_schema ) {
			$section_node   = $sections[ $section_index ] ?? null;
			$selector       = (string) ( $fallbacks[ $layout_key ] ?? '' );
			$matched_by     = $section_node instanceof DOMNode ? 'index' : 'missing';
			$selector_used  = '';

			if ( '' !== $selector ) {
				if ( ! $section_node instanceof DOMNode || ! $this->node_matches_selector( $section_node, $selector ) ) {
					$fallback_node = $this->find_section_by_selector( $xpath, $selector, $used_nodes );
					if ( $fallback_node instanceof DOMNode ) {
						$section_node  = $fallback_node;
						$matched_by    = 'selector_fallback';
						$selector_used = $selector;
					} else {
						$matched_by    = $section_node instanceof DOMNode ? 'selector_miss' : 'missing';
						$selector_used = $selector;
					}
				} else {
					$selector_used = $selector;
				}
			}

			if ( $section_node instanceof DOMNode ) {
				$used_nodes[ spl_object_hash( $section_node ) ] = true;
			}

			$matches[ $layout_key ] = array(
				'node'          => $section_node instanceof DOMNode ? $section_node : null,
				'matched_by'    => $matched_by,
				'selector_used' => $selector_used,
				'source_index'  => $section_index,
				'found'         => $section_node instanceof DOMNode,
				'label'         => (string) ( $layout_schema['label'] ?? $layout_key ),
			);
			++$section_index;
		}

		return array(
			'sections' => $sections,
			'matches'  => $matches,
		);
	}

	/**
	 * Resolve the legal page wrapper.
	 *
	 * @param DOMXPath $xpath XPath instance.
	 * @return array<string,mixed>
	 */
	protected function resolve_legal_match( $xpath ) {
		$section = $this->find_section_by_selector( $xpath, '.legal-content' );
		if ( ! $section instanceof DOMNode ) {
			$section = $this->first_node( $xpath, '//section[contains(@class,"legal-content")][1]' );
		}

		return array(
			'node'          => $section instanceof DOMNode ? $section : null,
			'matched_by'    => $section instanceof DOMNode ? 'selector_fallback' : 'missing',
			'selector_used' => '.legal-content',
			'found'         => $section instanceof DOMNode,
		);
	}

	/**
	 * Return the semantic selector fallbacks for problematic groups.
	 *
	 * @param string $field_name Field group name.
	 * @return array<string,string>
	 */
	protected function get_selector_fallbacks( $field_name ) {
		$maps = array(
			'finora_home_sections' => array(
				'hero_slider'  => '.hero--slider',
				'pillars'      => '.pillars',
				'audience'     => '.slider-section',
				'why_finora'   => '.why-finora',
				'how_it_works' => '.how-it-works',
				'testimonials' => '.testimonials',
				'faq'          => '.faq',
			),
			'finora_contact_sections' => array(
				'hero'         => '.hero--kontakt',
				'contact_main' => '.section-kontakt-main',
			),
			'finora_real_estate_sections' => array(
				'hero'      => '.hero--immobilien',
				'intro'     => '.immobilien-intro-section',
				'timeline'  => '.immobilien-timeline-section',
				'calculator'=> '.immobilien-calc-v2',
				'case'      => '.adrian-fall-section',
				'final_cta' => '.final-cta-section',
			),
		);

		return (array) ( $maps[ $field_name ] ?? array() );
	}

	/**
	 * Find the first body section matching one selector.
	 *
	 * @param DOMXPath            $xpath      XPath instance.
	 * @param string              $selector   Simple selector.
	 * @param array<string,bool>  $used_nodes Already claimed nodes.
	 * @return DOMNode|null
	 */
	protected function find_section_by_selector( $xpath, $selector, $used_nodes = array() ) {
		$predicate = $this->selector_to_xpath_predicate( $selector );
		if ( '' === $predicate ) {
			return null;
		}

		foreach ( $this->query_nodes( $xpath, '//body/section[' . $predicate . ']' ) as $node ) {
			$hash = spl_object_hash( $node );
			if ( isset( $used_nodes[ $hash ] ) ) {
				continue;
			}
			return $node;
		}

		return null;
	}

	/**
	 * Whether a node matches one simple selector.
	 *
	 * @param DOMNode $node     Section node.
	 * @param string  $selector Selector.
	 * @return bool
	 */
	protected function node_matches_selector( $node, $selector ) {
		if ( ! $node instanceof DOMElement ) {
			return false;
		}

		$selector = trim( (string) $selector );
		if ( '' === $selector ) {
			return false;
		}

		if ( '.' === substr( $selector, 0, 1 ) ) {
			return $this->class_name_exists( $node, substr( $selector, 1 ) );
		}

		if ( '#' === substr( $selector, 0, 1 ) ) {
			return $node->hasAttribute( 'id' ) && (string) $node->getAttribute( 'id' ) === substr( $selector, 1 );
		}

		return strtolower( $node->tagName ) === strtolower( $selector );
	}

	/**
	 * Convert a simple selector to an XPath predicate.
	 *
	 * @param string $selector Selector.
	 * @return string
	 */
	protected function selector_to_xpath_predicate( $selector ) {
		$selector = trim( (string) $selector );
		if ( '' === $selector ) {
			return '';
		}

		if ( '.' === substr( $selector, 0, 1 ) ) {
			$class_name = substr( $selector, 1 );
			return 'contains(concat(" ", normalize-space(@class), " "), " ' . esc_attr( $class_name ) . ' ")';
		}

		if ( '#' === substr( $selector, 0, 1 ) ) {
			return '@id="' . esc_attr( substr( $selector, 1 ) ) . '"';
		}

		return 'self::' . sanitize_key( $selector );
	}

	/**
	 * Whether an element has one CSS class.
	 *
	 * @param DOMElement $node       Element.
	 * @param string     $class_name Class name.
	 * @return bool
	 */
	protected function class_name_exists( $node, $class_name ) {
		$classes = preg_split( '/\s+/', trim( (string) $node->getAttribute( 'class' ) ) );
		return in_array( $class_name, array_filter( (array) $classes ), true );
	}

	/**
	 * Build one legal page value.
	 *
	 * @param DOMXPath      $xpath        XPath.
	 * @param DOMNode|null  $section_node Optional legal section node.
	 * @return array<string,mixed>
	 */
	protected function build_legal_value( $xpath, $section_node = null ) {
		$context = $section_node instanceof DOMNode ? $section_node : $xpath;
		$query   = $section_node instanceof DOMNode
			? './/*[contains(@class,"legal-body")]/* | .//*[contains(@class,"legal-copy")]/*'
			: '//section[contains(@class,"legal-content")]//div[contains(@class,"legal-body")]/* | //section[contains(@class,"legal-content")]//div[contains(@class,"legal-copy")]/*';
		$content = '';
		foreach ( $this->query_nodes( $context, $query ) as $node ) {
			$content .= $node->ownerDocument->saveHTML( $node );
		}

		if ( '' === trim( $content ) ) {
			$fallback_query = $section_node instanceof DOMNode
				? './/*[contains(@class,"legal-body")][1] | .//*[contains(@class,"legal-copy")][1]'
				: '//section[contains(@class,"legal-content")]//*[contains(@class,"legal-body")][1] | //section[contains(@class,"legal-content")]//*[contains(@class,"legal-copy")][1]';
			$fallback_node = $this->first_node( $context, $fallback_query );
			if ( $fallback_node instanceof DOMNode ) {
				$content = $this->save_inner_html( $fallback_node );
			}
		}

		$headline_query = $section_node instanceof DOMNode
			? './/*[contains(@class,"legal-title")][1] | .//h1[1]'
			: '//section[contains(@class,"legal-content")]//*[contains(@class,"legal-title")][1] | //section[contains(@class,"legal-content")]//h1[1]';

		return array(
			'headline' => $this->text( $context, $headline_query ),
			'content'  => $content,
		);
	}

	/**
	 * Parse one layout value from a section node.
	 *
	 * @param string              $layout_key    Layout key.
	 * @param array<string,mixed> $layout_schema Layout schema.
	 * @param DOMNode|null        $section_node  Section node.
	 * @param string              $lang          Language code.
	 * @return array<string,mixed>
	 */
	protected function parse_layout_value( $layout_key, $layout_schema, $section_node, $lang ) {
		if ( ! $section_node instanceof DOMNode ) {
			return $this->empty_layout_value( $layout_key, $layout_schema );
		}

		switch ( (string) ( $layout_schema['template'] ?? $layout_key ) ) {
			case 'hero_slider':
				return $this->parse_hero_slider( $section_node );
			case 'pillars':
				return $this->parse_pillars( $section_node );
			case 'audience_switcher':
				return $this->parse_audience_switcher( $section_node );
			case 'why_finora':
				return $this->parse_why_finora( $section_node );
			case 'how_it_works':
				return $this->parse_how_it_works( $section_node );
			case 'testimonials':
				return $this->parse_testimonials( $section_node );
			case 'faq':
				return $this->parse_faq( $section_node );
			case 'hero':
				return $this->parse_hero( $section_node );
			case 'banner_cta':
				return $this->parse_banner_cta( $section_node );
			case 'about_bedeutet':
				return $this->parse_about_bedeutet( $section_node );
			case 'media_text':
				return $this->parse_media_text( $section_node );
			case 'workflow_blurbs':
				return $this->parse_workflow_blurbs( $section_node );
			case 'center_cta':
				return $this->parse_center_cta( $section_node );
			case 'tabs_section':
				return $this->parse_tabs_section( $section_node );
			case 'retirement_audience':
				return $this->parse_retirement_audience( $section_node );
			case 'concepts_section':
				return $this->parse_concepts_section( $section_node );
			case 'blurb_image_section':
				return $this->parse_blurb_image_section( $section_node );
			case 'approach_tiles':
				return $this->parse_approach_tiles( $section_node );
			case 'timeline':
				return $this->parse_timeline( $section_node );
			case 'target_groups':
				return $this->parse_target_groups( $section_node );
			case 'results_section':
				return $this->parse_results_section( $section_node );
			case 'real_estate_intro':
				return $this->parse_real_estate_intro( $section_node );
			case 'calculator':
				return $this->parse_calculator( $section_node );
			case 'case_highlight':
				return $this->parse_case_highlight( $section_node );
			case 'dark_cta':
				return $this->parse_dark_cta( $section_node );
			case 'responsibility':
				return $this->parse_responsibility( $section_node );
			case 'new_phase':
				return $this->parse_new_phase( $section_node );
			case 'outcomes':
				return $this->parse_outcomes( $section_node );
			case 'target_groups_image':
				return $this->parse_target_groups_image( $section_node );
			case 'audience_cards':
				return $this->parse_audience_cards( $section_node );
			case 'media_blurbs':
				return $this->parse_media_blurbs( $section_node );
			case 'invest_detail':
				return $this->parse_invest_detail( $section_node );
			case 'tax_detail':
				return $this->parse_tax_detail( $section_node );
			case 'contact_main':
				return $this->parse_contact_main( $section_node );
			default:
				return $this->empty_layout_value( $layout_key, $layout_schema );
		}
	}

	/**
	 * Return one empty layout value by schema.
	 *
	 * @param string              $layout_key    Layout key.
	 * @param array<string,mixed> $layout_schema Layout schema.
	 * @return array<string,mixed>
	 */
	protected function empty_layout_value( $layout_key, $layout_schema ) {
		$out                  = array();
		$out['acf_fc_layout'] = $layout_key;

		foreach ( (array) ( $layout_schema['fields'] ?? array() ) as $field_key => $definition ) {
			$out[ $field_key ] = Leadwerk_Content_Schema::get_default_value( $definition );
		}

		return $out;
	}

	/**
	 * Parse a hero section.
	 *
	 * @param DOMNode $section_node Section node.
	 * @return array<string,mixed>
	 */
	protected function parse_hero( $section_node ) {
		$image = $this->parse_image_fields( $section_node, './/*[contains(@class,"hero-img-main")]//img[1]' );
		$button = $this->parse_button( $section_node );

		return array_merge(
			array(
				'title'     => $this->html( $section_node, './/h1[1]' ),
				'subtitle'  => $this->text( $section_node, './/*[contains(@class,"hero-subtitle")][1]' ),
				'image'     => $image['id'],
				'image_alt' => $image['alt'],
			),
			$button
		);
	}

	/**
	 * Parse the home slider.
	 *
	 * @param DOMNode $section_node Section node.
	 * @return array<string,mixed>
	 */
	protected function parse_hero_slider( $section_node ) {
		$slides = array();
		foreach ( $this->query_nodes( $section_node, './/*[contains(@class,"hero-slide")]' ) as $slide ) {
			$button      = $this->parse_button( $slide );
			$background  = $this->resolve_image_from_style( $this->attr( $slide, '.', 'style' ) );
			$slides[] = array_merge(
				array(
					'title'          => $this->html( $slide, './/h1[1]' ),
					'subtitle'       => $this->text( $slide, './/*[contains(@class,"hero-slide-subtitle")][1]' ),
					'background'     => $background['id'],
					'background_alt' => $background['alt'],
				),
				$button
			);
		}

		$services = array();
		foreach ( $this->query_nodes( $section_node, './/*[contains(@class,"hero-services")]//*[contains(@class,"service-item")]' ) as $item ) {
			$image = $this->parse_image_fields( $item, './/img[1]' );
			$link  = $this->parse_link_target( $this->attr( $item, '.', 'href' ) );
			$label = $this->html( $item, './/div[last()]' );
			$services[] = array(
				'title'       => $label,
				'description' => '',
				'icon'        => $image['id'],
				'icon_alt'    => $image['alt'],
				'page_key'    => $link['page_key'],
				'url'         => $link['url'],
			);
		}

		return array(
			'slides'     => $slides,
			'services'   => $services,
			'prev_label' => $this->attr( $section_node, './/*[contains(@class,"hero-slider-prev")][1]', 'aria-label' ),
			'next_label' => $this->attr( $section_node, './/*[contains(@class,"hero-slider-next")][1]', 'aria-label' ),
		);
	}

	/**
	 * Parse a shared pillars section.
	 *
	 * @param DOMNode $section_node Section node.
	 * @return array<string,mixed>
	 */
	protected function parse_pillars( $section_node ) {
		$items = array();
		foreach ( $this->query_nodes( $section_node, './/*[contains(@class,"pillar-card")]' ) as $card ) {
			$image  = $this->parse_image_fields( $card, './/img[1]' );
			$button = $this->parse_button( $card );
			$items[] = array_merge(
				array(
					'icon'        => $image['id'],
					'icon_alt'    => $image['alt'],
					'title'       => $this->text( $card, './/h3[1]' ),
					'description' => $this->html( $card, './/*[contains(@class,"card-desc")][1]' ),
				),
				$this->prefix_button_fields( $button, 'button' )
			);
		}

		return array(
			'title' => $this->html( $section_node, './/h2[1]' ),
			'items' => $items,
		);
	}

	/**
	 * Parse the home audience switcher.
	 *
	 * @param DOMNode $section_node Section node.
	 * @return array<string,mixed>
	 */
	protected function parse_audience_switcher( $section_node ) {
		$items = array();
		foreach ( $this->query_nodes( $section_node, './/*[contains(@class,"fs-list")]//*[contains(@class,"fs-item")]' ) as $item ) {
			$src   = $this->attr( $item, '.', 'data-img' );
			$image = $this->resolve_image_field( $src );
			$items[] = array(
				'label'      => trim( preg_replace( '/\s+/', ' ', $item->textContent ) ),
				'card_title' => $this->attr( $item, '.', 'data-title' ),
				'body'       => '<p>' . esc_html( $this->attr( $item, '.', 'data-body' ) ) . '</p>',
				'image'      => $image['id'],
				'image_alt'  => $this->attr( $item, '.', 'data-title' ),
			);
		}

		return array(
			'title'      => $this->html( $section_node, './/*[contains(@class,"fs-intro")][1]' ),
			'prev_label' => $this->attr( $section_node, './/*[contains(@class,"fs-prev")][1]', 'aria-label' ),
			'next_label' => $this->attr( $section_node, './/*[contains(@class,"fs-next")][1]', 'aria-label' ),
			'items'      => $items,
		);
	}

	/**
	 * Parse why-finora section.
	 *
	 * @param DOMNode $section_node Section node.
	 * @return array<string,mixed>
	 */
	protected function parse_why_finora( $section_node ) {
		$blurbs = array();
		foreach ( $this->query_nodes( $section_node, './/*[contains(@class,"blurb")]' ) as $blurb ) {
			$blurbs[] = array(
				'title'   => $this->text( $blurb, './/h4[1]' ),
				'content' => $this->first_available_html( $blurb, array( './/p[1]', './/*[contains(@class,"blurb-content")][1]' ) ),
			);
		}

		$image  = $this->parse_image_fields( $section_node, './/*[contains(@class,"why-finora-right")]//img[1]' );
		$button = $this->parse_button( $section_node, './/a[contains(@class,"btn-section")][1]' );

		return array_merge(
			array(
				'title'     => $this->html( $section_node, './/h2[1]' ),
				'subtitle'  => $this->text( $section_node, './/*[contains(@class,"subtitle")][1]' ),
				'body'      => $this->html( $section_node, './/*[contains(@class,"about-text")][1]' ),
				'blurbs'    => $blurbs,
				'image'     => $image['id'],
				'image_alt' => $image['alt'],
			),
			$button
		);
	}

	/**
	 * Parse how-it-works section.
	 *
	 * @param DOMNode $section_node Section node.
	 * @return array<string,mixed>
	 */
	protected function parse_how_it_works( $section_node ) {
		$steps = array();
		foreach ( $this->query_nodes( $section_node, './/*[contains(@class,"how-step")]' ) as $step ) {
			$steps[] = array(
				'icon_text' => $this->text( $step, './/*[contains(@class,"step-icon")][1]' ),
				'title'     => $this->text( $step, './/h4[1]' ),
				'content'   => $this->html( $step, './/p[1]' ),
			);
		}

		return array_merge(
			array(
				'title' => $this->html( $section_node, './/h2[1]' ),
				'steps' => $steps,
			),
			$this->parse_button( $section_node, './/a[contains(@class,"btn")][last()]' )
		);
	}

	/**
	 * Parse testimonials section.
	 *
	 * @param DOMNode $section_node Section node.
	 * @return array<string,mixed>
	 */
	protected function parse_testimonials( $section_node ) {
		$items = array();
		foreach ( $this->query_nodes( $section_node, './/*[contains(@class,"testimonial-card")]' ) as $card ) {
			$force_expanded = '';
			if ( $card instanceof DOMElement && $card->hasAttribute( 'data-force-expanded' ) ) {
				$force_expanded = strtolower( trim( (string) $card->getAttribute( 'data-force-expanded' ) ) );
			}

			$items[] = array(
				'quote'          => $this->html( $card, './/*[contains(@class,"testimonial-text")][1]' ),
				'toggle_enabled' => 'true' !== $force_expanded,
				'initials'       => $this->text( $card, './/*[contains(@class,"testimonial-initials")][1]' ),
				'name'           => $this->text( $card, './/*[contains(@class,"testimonial-name")][1]' ),
				'role'           => $this->text( $card, './/*[contains(@class,"testimonial-role")][1]' ),
			);
		}

		return array(
			'title'    => $this->html( $section_node, './/h2[1]' ),
			'subtitle' => $this->text( $section_node, './/*[contains(@class,"testimonials-subtitle")][1]' ),
			'items'    => $items,
		);
	}

	/**
	 * Parse FAQ section.
	 *
	 * @param DOMNode $section_node Section node.
	 * @return array<string,mixed>
	 */
	protected function parse_faq( $section_node ) {
		$items = array();
		foreach ( $this->query_nodes( $section_node, './/*[contains(@class,"accordion-item")]' ) as $item ) {
			$items[] = array(
				'question' => $this->text( $item, './/*[contains(@class,"accordion-title")][1]' ),
				'answer'   => $this->html( $item, './/*[contains(@class,"accordion-content")][1]' ),
			);
		}

		$background = $this->resolve_image_from_style( $this->attr( $section_node, './/*[contains(@class,"faq-left")][1]', 'style' ) );

		return array(
			'title'            => $this->html( $section_node, './/h2[1]' ),
			'intro'            => $this->text( $section_node, './/*[contains(@class,"faq-intro")][1]' ),
			'background_image' => $background['id'],
			'items'            => $items,
		);
	}

	/**
	 * Parse a banner CTA section.
	 *
	 * @param DOMNode $section_node Section node.
	 * @return array<string,mixed>
	 */
	protected function parse_banner_cta( $section_node ) {
		$background = $this->resolve_image_from_style( $this->attr( $section_node, '.', 'style' ) );

		return array_merge(
			array(
				'title'            => $this->html( $section_node, './/h2[1]' ),
				'body'             => $this->first_available_html( $section_node, array( './/p[1]', './/div[contains(@class,"anim")][2]' ) ),
				'background_image' => $background['id'],
			),
			$this->parse_button( $section_node )
		);
	}

	/**
	 * Parse about-bedeutet section.
	 *
	 * @param DOMNode $section_node Section node.
	 * @return array<string,mixed>
	 */
	protected function parse_about_bedeutet( $section_node ) {
		$columns    = $this->query_nodes( $section_node, './/*[contains(@class,"two-col")][1]/*[contains(@class,"col-text")]' );
		$left       = isset( $columns[0] ) ? $columns[0] : null;
		$right      = isset( $columns[1] ) ? $columns[1] : null;
		$right_items = array();

		if ( $right ) {
			foreach ( $this->query_nodes( $right, './/*[contains(@class,"blurb")]' ) as $blurb ) {
				$right_items[] = array(
					'content' => $this->html( $blurb, './/*[contains(@class,"blurb-content")][1]' ),
				);
			}
		}

		return array_merge(
			array(
				'title'       => $this->first_available_html(
					$section_node,
					array(
						'.//*[contains(@class,"section-heading")][1]//h2[1]',
						'.//*[contains(@class,"two-col")][1]/*[contains(@class,"col-text")][1]//h2[1]',
					)
				),
				'left_body'   => $left ? $this->join_html( $this->query_nodes( $left, './p' ) ) : '',
				'right_title' => $right ? $this->text( $right, './/h4[1]' ) : '',
				'right_items' => $right_items,
			),
			$left ? $this->parse_button( $left ) : array()
		);
	}

	/**
	 * Parse a simple media/text section.
	 *
	 * @param DOMNode $section_node Section node.
	 * @return array<string,mixed>
	 */
	protected function parse_media_text( $section_node ) {
		$text  = $this->first_node( $section_node, './/*[contains(@class,"col-text")][1]' );
		$image = $this->first_node( $section_node, './/*[contains(@class,"col-img")][1]' );

		return array_merge(
			array(
				'title'          => $text ? $this->html( $text, './/h2[1]' ) : '',
				'body'           => $text ? $this->join_html( $this->query_nodes( $text, './p | .//*[contains(@class,"section-body")]/* | .//*[contains(@class,"section-subtitle")]' ) ) : '',
				'image'          => $image ? $this->parse_image_fields( $image, './/img[1]' )['id'] : 0,
				'image_alt'      => $image ? $this->parse_image_fields( $image, './/img[1]' )['alt'] : '',
				'image_position' => $this->is_image_first( $section_node ) ? 'left' : 'right',
			),
			$text ? $this->parse_button( $text ) : array()
		);
	}

	/**
	 * Parse workflow blurb section.
	 *
	 * @param DOMNode $section_node Section node.
	 * @return array<string,mixed>
	 */
	protected function parse_workflow_blurbs( $section_node ) {
		$columns = $this->query_nodes( $section_node, './/*[contains(@class,"two-col")][1]/*[contains(@class,"col-text")]' );
		$left    = isset( $columns[0] ) ? $columns[0] : null;
		$right   = isset( $columns[1] ) ? $columns[1] : null;
		$items   = array();

		if ( $right ) {
			foreach ( $this->query_nodes( $right, './/*[contains(@class,"blurb")]' ) as $blurb ) {
				$items[] = array(
					'content' => $this->html( $blurb, './/*[contains(@class,"blurb-content")][1]' ),
				);
			}
		}

		return array_merge(
			array(
				'title'     => $left ? $this->html( $left, './/h2[1]' ) : '',
				'intro'     => $right ? $this->html( $right, './/p[1]' ) : '',
				'items'     => $items,
				'highlight' => $right ? $this->html( $right, './/*[contains(@class,"workflow-highlight")][1]' ) : '',
			),
			$left ? $this->parse_button( $left ) : array()
		);
	}

	/**
	 * Parse centered CTA.
	 *
	 * @param DOMNode $section_node Section node.
	 * @return array<string,mixed>
	 */
	protected function parse_center_cta( $section_node ) {
		return array_merge(
			array(
				'title' => $this->html( $section_node, './/h2[1]' ),
				'body'  => $this->html( $section_node, './/p[1]' ),
			),
			$this->parse_button( $section_node )
		);
	}

	/**
	 * Parse tabs section.
	 *
	 * @param DOMNode $section_node Section node.
	 * @return array<string,mixed>
	 */
	protected function parse_tabs_section( $section_node ) {
		$tabs       = array();
		$nav_labels = array();
		foreach ( $this->query_nodes( $section_node, './/*[contains(@class,"tab-nav")]//button' ) as $button ) {
			$nav_labels[] = trim( $button->textContent );
		}

		$panels = $this->query_nodes( $section_node, './/*[contains(@class,"tab-panel")]' );
		foreach ( $panels as $index => $panel ) {
			$bullets = array();
			foreach ( $this->query_nodes( $panel, './/li' ) as $li ) {
				$bullets[] = array( 'text' => trim( $li->textContent ) );
			}

			$tabs[] = array(
				'title'   => isset( $nav_labels[ $index ] ) ? $nav_labels[ $index ] : $this->text( $panel, './/h4[1]' ),
				'intro'   => $this->html( $panel, './/h4[1]' ),
				'bullets' => $bullets,
				'outro'   => $this->html( $panel, './/p[last()]' ),
			);
		}

		$image = $this->parse_image_fields( $section_node, './/*[contains(@class,"tabs-img")]//img[1]' );

		return array_merge(
			array(
				'title'     => $this->html( $section_node, './/h2[1]' ),
				'intro'     => $this->html( $section_node, './/*[contains(@class,"favorites-title")]//p[1]' ),
				'image'     => $image['id'],
				'image_alt' => $image['alt'],
				'tabs'      => $tabs,
			),
			$this->parse_button( $section_node, './/*[contains(@class,"favorites-cta")]//a[1]' )
		);
	}

	/**
	 * Parse retirement audience section.
	 *
	 * @param DOMNode $section_node Section node.
	 * @return array<string,mixed>
	 */
	protected function parse_retirement_audience( $section_node ) {
		$jump_links = array();
		foreach ( $this->query_nodes( $section_node, './/*[contains(@class,"audience-links")]//a' ) as $link ) {
			$href         = $this->attr( $link, '.', 'href' );
			$jump_links[] = array(
				'label'     => trim( $link->textContent ),
				'anchor_id' => ltrim( (string) strstr( $href, '#' ), '#' ),
			);
		}

		$cards = array();
		foreach ( $this->query_nodes( $section_node, './/*[contains(@class,"audience-card")]' ) as $card ) {
			$variant = 'default';
			$class   = $this->attr( $card, '.', 'class' );
			if ( false !== strpos( $class, 'audience-card--highlight' ) ) {
				$variant = 'highlight';
			} elseif ( false !== strpos( $class, 'audience-card--large' ) ) {
				$variant = 'large';
			} elseif ( false !== strpos( $class, 'audience-card--small' ) ) {
				$variant = 'small';
			}

			$blurbs = array();
			foreach ( $this->query_nodes( $card, './/*[contains(@class,"blurb")]' ) as $blurb ) {
				$blurbs[] = array(
					'title'   => $this->text( $blurb, './/h4[1]' ),
					'content' => $this->html( $blurb, './/*[contains(@class,"blurb-content")][1]' ),
				);
			}

			$button   = $this->parse_button( $card );
			$cards[] = array_merge(
				array(
					'variant'   => $variant,
					'anchor_id' => $this->attr( $card, '.', 'id' ),
					'title'     => $this->text( $card, './/h3[1]' ),
					'intro'     => $this->html( $card, './p[1]' ),
					'blurbs'    => $blurbs,
				),
				$this->prefix_button_fields( $button, 'button' )
			);
		}

		return array(
			'title'      => $this->html( $section_node, './/h2[1]' ),
			'jump_links' => $jump_links,
			'cards'      => $cards,
		);
	}

	/**
	 * Parse concepts section.
	 *
	 * @param DOMNode $section_node Section node.
	 * @return array<string,mixed>
	 */
	protected function parse_concepts_section( $section_node ) {
		$items = array();
		foreach ( $this->query_nodes( $section_node, './/*[contains(@class,"blurb")]' ) as $blurb ) {
			$items[] = array(
				'content' => $this->html( $blurb, './/*[contains(@class,"blurb-content")][1]' ),
			);
		}

		$image = $this->parse_image_fields( $section_node, './/*[contains(@class,"concepts-image-wrap")]//img[1]' );

		return array_merge(
			array(
				'title'     => $this->html( $section_node, './/h2[1]' ),
				'intro'     => $this->html( $section_node, './/*[contains(@class,"concepts-text-col")]//p[1]' ),
				'image'     => $image['id'],
				'image_alt' => $image['alt'],
				'items'     => $items,
			),
			$this->parse_button( $section_node )
		);
	}

	/**
	 * Parse a blurb + image section.
	 *
	 * @param DOMNode $section_node Section node.
	 * @return array<string,mixed>
	 */
	protected function parse_blurb_image_section( $section_node ) {
		$items = array();
		foreach ( $this->query_nodes( $section_node, './/*[contains(@class,"blurb")]' ) as $blurb ) {
			$items[] = array(
				'content' => $this->html( $blurb, './/*[contains(@class,"blurb-content")][1]' ),
			);
		}

		$image = $this->parse_image_fields( $section_node, './/img[1]' );

		return array_merge(
			array(
				'title'     => $this->html( $section_node, './/h2[1]' ),
				'intro'     => $this->first_available_html( $section_node, array( './/*[contains(@class,"section-heading")]//p[1]', './/*[contains(@class,"col-text")]//p[1]' ) ),
				'image'     => $image['id'],
				'image_alt' => $image['alt'],
				'items'     => $items,
			),
			$this->parse_button( $section_node )
		);
	}

	/**
	 * Parse approach tiles section.
	 *
	 * @param DOMNode $section_node Section node.
	 * @return array<string,mixed>
	 */
	protected function parse_approach_tiles( $section_node ) {
		$tiles = array();
		$tile_nodes = $this->query_nodes(
			$section_node,
			'.//*[contains(concat(" ", normalize-space(@class), " "), " approach-tiles-grid ")]/*[contains(concat(" ", normalize-space(@class), " "), " approach-tile ")]'
		);
		foreach ( $tile_nodes as $tile ) {
			$tiles[] = array(
				'title'   => $this->text( $tile, './/h4[1]' ),
				'content' => $this->html( $tile, './/*[contains(@class,"approach-tile__back")]//p[1]' ),
			);
		}
		$tiles = $this->normalize_approach_tiles( $tiles, count( $tile_nodes ) );

		return array_merge(
			array(
				'title' => $this->html( $section_node, './/h2[1]' ),
				'body'  => $this->html( $section_node, './/*[contains(@class,"col-text")]//p[1]' ),
				'tiles' => $tiles,
			),
			$this->parse_button( $section_node )
		);
	}

	/**
	 * Normalize approach tiles so older broad class matches do not survive in stored data.
	 *
	 * @param array<int,array<string,mixed>> $tiles Source tiles.
	 * @param int                            $limit Expected maximum count.
	 * @return array<int,array<string,mixed>>
	 */
	protected function normalize_approach_tiles( $tiles, $limit = 0 ) {
		$normalized = array();
		$index_map  = array();
		$limit      = max( 0, (int) $limit );

		foreach ( (array) $tiles as $tile ) {
			if ( ! is_array( $tile ) ) {
				continue;
			}

			$title         = trim( wp_strip_all_tags( (string) ( $tile['title'] ?? '' ) ) );
			$content       = (string) ( $tile['content'] ?? '' );
			$content_text  = trim( wp_strip_all_tags( $content ) );
			$dedupe_source = '' !== $title ? $title : $content_text;
			if ( '' === $dedupe_source ) {
				continue;
			}

			$key = sanitize_title( $dedupe_source );
			if ( isset( $index_map[ $key ] ) ) {
				$existing_index   = $index_map[ $key ];
				$existing_tile    = $normalized[ $existing_index ];
				$existing_title   = trim( wp_strip_all_tags( (string) ( $existing_tile['title'] ?? '' ) ) );
				$existing_content = trim( wp_strip_all_tags( (string) ( $existing_tile['content'] ?? '' ) ) );
				if ( strlen( $content_text ) > strlen( $existing_content ) || ( '' === $existing_title && '' !== $title ) ) {
					$normalized[ $existing_index ] = array(
						'title'   => $title,
						'content' => $content,
					);
				}
				continue;
			}

			$index_map[ $key ] = count( $normalized );
			$normalized[]      = array(
				'title'   => $title,
				'content' => $content,
			);
		}

		if ( $limit > 0 ) {
			$normalized = array_slice( $normalized, 0, $limit );
		}

		return array_values( $normalized );
	}

	/**
	 * Parse timeline section.
	 *
	 * @param DOMNode $section_node Section node.
	 * @return array<string,mixed>
	 */
	protected function parse_timeline( $section_node ) {
		$items = array();
		foreach ( $this->query_nodes( $section_node, './/*[contains(@class,"timeline-item")]' ) as $item ) {
			$bullets = array();
			foreach ( $this->query_nodes( $item, './/li' ) as $li ) {
				$bullets[] = array( 'text' => trim( $li->textContent ) );
			}
			$items[] = array(
				'number'  => $this->text( $item, './/*[contains(@class,"timeline-item__number")][1]' ),
				'icon'    => $this->attr( $item, './/*[contains(@class,"timeline-item__icon")]//i[1]', 'class' ),
				'title'   => $this->text( $item, './/h4[1]' ),
				'body'    => $this->join_html( $this->query_nodes( $item, './/*[contains(@class,"timeline-item__card")][1]/p' ) ),
				'bullets' => $bullets,
			);
		}

		return array(
			'title' => $this->html( $section_node, './/h2[1]' ),
			'intro' => $this->first_available_html( $section_node, array( './/*[contains(@class,"section-heading")]//p[1]', './/p[1]' ) ),
			'items' => $items,
		);
	}

	/**
	 * Parse target groups section.
	 *
	 * @param DOMNode $section_node Section node.
	 * @return array<string,mixed>
	 */
	protected function parse_target_groups( $section_node ) {
		$items = array();
		foreach ( $this->query_nodes( $section_node, './/*[contains(@class,"target-group-grid")]//*[contains(@class,"blurb")]' ) as $blurb ) {
			$items[] = array(
				'content' => $this->html( $blurb, './/*[contains(@class,"blurb-content")][1]' ),
			);
		}

		return array_merge(
			array(
				'title'    => $this->html( $section_node, './/h2[1]' ),
				'subtitle' => $this->text( $section_node, './/*[contains(@class,"target-groups__subtitle")][1]' ),
				'items'    => $items,
				'summary'  => $this->html( $section_node, './/*[contains(@class,"target-groups__summary")][1]' ),
			),
			$this->parse_button( $section_node )
		);
	}

	/**
	 * Parse results section.
	 *
	 * @param DOMNode $section_node Section node.
	 * @return array<string,mixed>
	 */
	protected function parse_results_section( $section_node ) {
		$items = array();
		foreach ( $this->query_nodes( $section_node, './/*[contains(@class,"blurb")]' ) as $blurb ) {
			$items[] = array(
				'content' => $this->html( $blurb, './/*[contains(@class,"blurb-content")][1]' ),
			);
		}

		$image = $this->parse_image_fields( $section_node, './/img[1]' );

		return array_merge(
			array(
				'title'     => $this->html( $section_node, './/h2[1]' ),
				'intro'     => $this->html( $section_node, './/*[contains(@class,"col-text")]//p[1]' ),
				'image'     => $image['id'],
				'image_alt' => $image['alt'],
				'items'     => $items,
			),
			$this->parse_button( $section_node )
		);
	}

	/**
	 * Parse real estate intro section.
	 *
	 * @param DOMNode $section_node Section node.
	 * @return array<string,mixed>
	 */
	protected function parse_real_estate_intro( $section_node ) {
		$image = $this->parse_image_fields( $section_node, './/*[contains(@class,"immobilien-intro-image")][1]' );
		$stats = array();
		foreach ( $this->query_nodes( $section_node, './/*[contains(@class,"intro-stat")]' ) as $stat ) {
			$stats[] = array(
				'value' => $this->text( $stat, './/*[contains(@class,"intro-stat__value")][1]' ),
				'label' => $this->text( $stat, './/*[contains(@class,"intro-stat__label")][1]' ),
			);
		}

		$headings = $this->query_nodes( $section_node, './/h2' );
		$paras    = $this->query_nodes( $section_node, './/p' );
		$blurbs   = array();
		foreach ( $this->query_nodes( $section_node, './/*[contains(@class,"blurb")]' ) as $blurb ) {
			$blurbs[] = array(
				'title'   => $this->text( $blurb, './/h4[1]' ),
				'content' => $this->html( $blurb, './/*[contains(@class,"blurb-content")][1]' ),
			);
		}

		return array(
			'image'           => $image['id'],
			'image_alt'       => $image['alt'],
			'stats'           => $stats,
			'goals_title'     => isset( $headings[0] ) ? $this->html( $headings[0], '.' ) : '',
			'goals_body'      => isset( $paras[0] ) ? $this->html( $paras[0], '.' ) : '',
			'challenge_title' => isset( $headings[1] ) ? $this->html( $headings[1], '.' ) : '',
			'challenge_body'  => isset( $paras[1] ) ? $this->html( $paras[1], '.' ) : '',
			'blurbs'          => $blurbs,
		);
	}

	/**
	 * Parse calculator section.
	 *
	 * @param DOMNode $section_node Section node.
	 * @return array<string,mixed>
	 */
	protected function parse_calculator( $section_node ) {
		$cards = array();
		foreach ( $this->query_nodes( $section_node, './/*[contains(@class,"calc-v2__card")]' ) as $card ) {
			$rows = array();
			foreach ( $this->query_nodes( $card, './/*[contains(@class,"calc-v2__row")]' ) as $row ) {
				$modifier = '';
				$class    = $this->attr( $row, '.', 'class' );
				foreach ( array( 'plus', 'minus', 'subtotal', 'highlight', 'hero', 'accent', 'big' ) as $candidate ) {
					if ( false !== strpos( $class, 'calc-v2__row--' . $candidate ) || false !== strpos( $class, 'calc-v2__value--' . $candidate ) ) {
						$modifier = $candidate;
						break;
					}
				}
				$rows[] = array(
					'label'    => $this->text( $row, './/*[contains(@class,"calc-v2__label")][1]' ),
					'value'    => $this->text( $row, './/*[contains(@class,"calc-v2__value")][1]' ),
					'modifier' => $modifier,
				);
			}

			$cards[] = array(
				'title'    => $this->text( $card, './/*[contains(@class,"calc-v2__card-title")][1]' ),
				'icon'     => $this->attr( $card, './/*[contains(@class,"calc-v2__card-icon")]//i[1]', 'class' ),
				'featured' => false !== strpos( $this->attr( $card, '.', 'class' ), 'calc-v2__card--featured' ),
				'rows'     => $rows,
			);
		}

		$kpis = array();
		foreach ( $this->query_nodes( $section_node, './/*[contains(@class,"calc-v2__kpi")]' ) as $kpi ) {
			$kpis[] = array(
				'value'  => $this->text( $kpi, './/*[contains(@class,"calc-v2__kpi-value")][1]' ),
				'label'  => $this->text( $kpi, './/*[contains(@class,"calc-v2__kpi-label")][1]' ),
				'accent' => false !== strpos( $this->attr( $kpi, './/*[contains(@class,"calc-v2__kpi-value")][1]', 'class' ), '--accent' ),
			);
		}

		return array(
			'title'    => $this->html( $section_node, './/h2[1]' ),
			'subtitle' => $this->html( $section_node, './/*[contains(@class,"calc-v2__subtitle")][1]' ),
			'cards'    => $cards,
			'kpis'     => $kpis,
		);
	}

	/**
	 * Parse case highlight section.
	 *
	 * @param DOMNode $section_node Section node.
	 * @return array<string,mixed>
	 */
	protected function parse_case_highlight( $section_node ) {
		$image = $this->parse_image_fields( $section_node, './/*[contains(@class,"adrian-img")][1]' );

		return array(
			'image'     => $image['id'],
			'image_alt' => $image['alt'],
			'title'     => $this->html( $section_node, './/*[contains(@class,"adrian-heading")][1]' ),
			'body'      => $this->html( $section_node, './/*[contains(@class,"adrian-text")][1]' ),
			'quote'     => $this->html( $section_node, './/*[contains(@class,"adrian-quote")][1]' ),
		);
	}

	/**
	 * Parse dark CTA section.
	 *
	 * @param DOMNode $section_node Section node.
	 * @return array<string,mixed>
	 */
	protected function parse_dark_cta( $section_node ) {
		return array_merge(
			array(
				'title' => $this->html( $section_node, './/h2[1]' ),
				'body'  => $this->html( $section_node, './/p[1]' ),
			),
			$this->parse_button( $section_node )
		);
	}

	/**
	 * Parse responsibility section.
	 *
	 * @param DOMNode $section_node Section node.
	 * @return array<string,mixed>
	 */
	protected function parse_responsibility( $section_node ) {
		$image = $this->parse_image_fields( $section_node, './/*[contains(@class,"responsibility-bottom__image")]//img[1]' );
		$items = array();
		foreach ( $this->query_nodes( $section_node, './/*[contains(@class,"blurb")]' ) as $blurb ) {
			$items[] = array(
				'content' => $this->html( $blurb, './/*[contains(@class,"blurb-content")][1]' ),
			);
		}

		return array_merge(
			array(
				'title'     => $this->html( $section_node, './/*[contains(@class,"responsibility-top__main")]//h2[1]' ),
				'intro'     => $this->html( $section_node, './/*[contains(@class,"responsibility-top__main")]//p[1]' ),
				'image'     => $image['id'],
				'image_alt' => $image['alt'],
				'lead'      => $this->html( $section_node, './/*[contains(@class,"responsibility-lead")][1]' ),
				'items'     => $items,
				'note'      => $this->html( $section_node, './/*[contains(@class,"responsibility-note")][1]' ),
			),
			$this->parse_button( $section_node, './/*[contains(@class,"responsibility-top__side")]//a[1]' )
		);
	}

	/**
	 * Parse new-phase section.
	 *
	 * @param DOMNode $section_node Section node.
	 * @return array<string,mixed>
	 */
	protected function parse_new_phase( $section_node ) {
		$image = $this->parse_image_fields( $section_node, './/*[contains(@class,"new-phase-img")][1]' );
		$items = array();
		foreach ( $this->query_nodes( $section_node, './/*[contains(@class,"blurb")]' ) as $blurb ) {
			$items[] = array(
				'content' => $this->html( $blurb, './/*[contains(@class,"blurb-content")][1]' ),
			);
		}

		return array_merge(
			array(
				'image'     => $image['id'],
				'image_alt' => $image['alt'],
				'title'     => $this->html( $section_node, './/*[contains(@class,"new-phase-heading")][1]' ),
				'body'      => $this->html( $section_node, './/*[contains(@class,"new-phase-text")][1]' ),
				'items'     => $items,
			),
			$this->parse_button( $section_node )
		);
	}

	/**
	 * Parse outcomes section.
	 *
	 * @param DOMNode $section_node Section node.
	 * @return array<string,mixed>
	 */
	protected function parse_outcomes( $section_node ) {
		$items = array();
		foreach ( $this->query_nodes( $section_node, './/*[contains(@class,"flip-box")]' ) as $box ) {
			$items[] = array(
				'icon'    => $this->attr( $box, './/*[contains(@class,"flip-box-icon")]//i[1]', 'class' ),
				'title'   => $this->text( $box, './/h4[1]' ),
				'content' => $this->html( $box, './/*[contains(@class,"flip-box-back")]//p[1]' ),
			);
		}

		return array(
			'title' => $this->html( $section_node, './/h2[1]' ),
			'intro' => $this->first_available_html( $section_node, array( './/*[contains(@class,"section-heading")]//p[1]', './/*[contains(@class,"outcomes-text")][1]' ) ),
			'body'  => $this->html( $section_node, './/*[contains(@class,"outcomes-text")][1]' ),
			'items' => $items,
		);
	}

	/**
	 * Parse image target groups section.
	 *
	 * @param DOMNode $section_node Section node.
	 * @return array<string,mixed>
	 */
	protected function parse_target_groups_image( $section_node ) {
		$items = array();
		foreach ( $this->query_nodes( $section_node, './/*[contains(@class,"blurb")]' ) as $blurb ) {
			$items[] = array(
				'content' => $this->html( $blurb, './/*[contains(@class,"blurb-content")][1]' ),
			);
		}

		$image = $this->parse_image_fields( $section_node, './/img[1]' );

		return array_merge(
			array(
				'title'     => $this->html( $section_node, './/h2[1]' ),
				'intro'     => $this->html( $section_node, './/*[contains(@class,"target-intro")][1]' ),
				'items'     => $items,
				'image'     => $image['id'],
				'image_alt' => $image['alt'],
			),
			$this->parse_button( $section_node )
		);
	}

	/**
	 * Parse audience cards section.
	 *
	 * @param DOMNode $section_node Section node.
	 * @return array<string,mixed>
	 */
	protected function parse_audience_cards( $section_node ) {
		$items = array();
		foreach ( $this->query_nodes( $section_node, './/*[contains(@class,"audience-card")]' ) as $card ) {
			$class = $this->attr( $card, '.', 'class' );
			$items[] = array_merge(
				array(
					'title'    => $this->text( $card, './/h3[1]' ),
					'content'  => $this->html( $card, './/p[1]' ),
					'is_empty' => false !== strpos( $class, 'audience-card--empty' ),
				),
				$this->prefix_button_fields( $this->parse_button( $card ), 'button' )
			);
		}

		return array(
			'title' => $this->html( $section_node, './/h2[1]' ),
			'items' => $items,
		);
	}

	/**
	 * Parse media blurbs section.
	 *
	 * @param DOMNode $section_node Section node.
	 * @return array<string,mixed>
	 */
	protected function parse_media_blurbs( $section_node ) {
		$blurbs = array();
		foreach ( $this->query_nodes( $section_node, './/*[contains(@class,"blurb")]' ) as $blurb ) {
			$blurbs[] = array(
				'title'   => $this->text( $blurb, './/h4[1]' ),
				'content' => $this->html( $blurb, './/*[contains(@class,"blurb-content")][1]' ),
			);
		}
		$image = $this->parse_image_fields( $section_node, './/*[contains(@class,"feature-image")][1]' );

		return array_merge(
			array(
				'title'     => $this->html( $section_node, './/h2[1]' ),
				'subtitle'  => $this->text( $section_node, './/*[contains(@class,"section-subtitle")][1]' ),
				'body'      => $this->html( $section_node, './/*[contains(@class,"section-body")][1]' ),
				'blurbs'    => $blurbs,
				'image'     => $image['id'],
				'image_alt' => $image['alt'],
			),
			$this->parse_button( $section_node )
		);
	}

	/**
	 * Parse invest detail section.
	 *
	 * @param DOMNode $section_node Section node.
	 * @return array<string,mixed>
	 */
	protected function parse_invest_detail( $section_node ) {
		$trends = array();
		foreach ( $this->query_nodes( $section_node, './/*[contains(@class,"megatrend-grid")]//*[contains(@class,"flip-box")]' ) as $box ) {
			$trends[] = array(
				'icon'    => $this->attr( $box, './/*[contains(@class,"flip-box-icon")]//i[1]', 'class' ),
				'title'   => $this->text( $box, './/h4[1]' ),
				'content' => $this->html( $box, './/*[contains(@class,"flip-box-back")]//p[1]' ),
			);
		}

		$image = $this->parse_image_fields( $section_node, './/*[contains(@class,"feature-image")][1]' );

		return array_merge(
			array(
				'title'           => $this->html( $section_node, './/*[contains(@class,"two-col--invest-intro")]//h2[1]' ),
				'subtitle'        => $this->text( $section_node, './/*[contains(@class,"two-col--invest-intro")]//*[contains(@class,"section-subtitle")][1]' ),
				'body'            => $this->html( $section_node, './/*[contains(@class,"two-col--invest-intro")]//*[contains(@class,"section-body")][1]' ),
				'image'           => $image['id'],
				'image_alt'       => $image['alt'],
				'explainer_title' => $this->html( $section_node, './/*[contains(@class,"invest-explainer")]//h2[1]' ),
				'explainer_body'  => $this->html( $section_node, './/*[contains(@class,"invest-explainer")]//p[1]' ),
				'explainer_sub'   => $this->text( $section_node, './/*[contains(@class,"invest-explainer")]//h3[1]' ),
				'trends'          => $trends,
				'outro'           => $this->html( $section_node, './/*[contains(@class,"invest-outro")][1]' ),
			),
			$this->parse_button( $section_node )
		);
	}

	/**
	 * Parse tax detail section.
	 *
	 * @param DOMNode $section_node Section node.
	 * @return array<string,mixed>
	 */
	protected function parse_tax_detail( $section_node ) {
		$steps = array();
		foreach ( $this->query_nodes( $section_node, './/*[contains(@class,"step-card")]' ) as $step ) {
			$icon = $this->text( $step, './/*[contains(@class,"step-icon")][1]' );
			if ( '' === trim( $icon ) ) {
				$icon = $this->attr( $step, './/*[contains(@class,"step-icon")]//i[1]', 'class' );
			}
			$steps[] = array(
				'icon'    => $icon,
				'title'   => $this->text( $step, './/h4[1]' ),
				'content' => $this->html( $step, './/p[1]' ),
			);
		}

		$image = $this->parse_image_fields( $section_node, './/*[contains(@class,"feature-image")][1]' );

		return array_merge(
			array(
				'title'       => $this->html( $section_node, './/*[contains(@class,"two-col--tax-intro")]//h2[1]' ),
				'subtitle'    => $this->text( $section_node, './/*[contains(@class,"two-col--tax-intro")]//*[contains(@class,"section-subtitle")][1]' ),
				'body'        => $this->html( $section_node, './/*[contains(@class,"section-body")][1]' ),
				'how_title'   => $this->text( $section_node, './/*[contains(@class,"two-col--tax-intro")]//h3[1]' ),
				'how_body'    => $this->html( $section_node, './/*[contains(@class,"section-subtitle--dense")][1]' ),
				'image'       => $image['id'],
				'image_alt'   => $image['alt'],
				'steps_title' => $this->html( $section_node, './/*[contains(@class,"section-heading--center")]//h2[1]' ),
				'steps'       => $steps,
			),
			$this->parse_button( $section_node )
		);
	}

	/**
	 * Parse contact section.
	 *
	 * @param DOMNode $section_node Section node.
	 * @return array<string,mixed>
	 */
	protected function parse_contact_main( $section_node ) {
		$info_cards = array();
		foreach ( $this->query_nodes( $section_node, './/*[contains(@class,"kontakt-info-card")]' ) as $card ) {
			$href = $this->attr( $card, './/*[contains(@class,"kontakt-info-card__link")][1]', 'href' );
			$type = 'address';
			if ( 0 === strpos( $href, 'tel:' ) ) {
				$type = 'phone';
			} elseif ( 0 === strpos( $href, 'mailto:' ) ) {
				$type = 'email';
			} elseif ( '' !== trim( $href ) ) {
				$type = 'link';
			}
			$info_cards[] = array(
				'title' => $this->text( $card, './/h4[1]' ),
				'type'  => $type,
				'value' => $this->html( $card, './/p[1]' ),
				'href'  => $href,
			);
		}

		$privacy_href = $this->attr( $section_node, './/label[@for="contact-privacy"]//a[1]', 'href' );

		return array(
			'title'         => $this->html( $section_node, './/h2[1]' ),
			'intro'         => $this->html( $section_node, './/*[contains(@class,"kontakt-form-intro")][1]' ),
			'submit_label'  => $this->text( $section_node, './/*[contains(@class,"btn-submit")][1]' ),
			'privacy_label' => $this->html( $section_node, './/label[@for="contact-privacy"][1]' ),
			'privacy_page'  => $this->map_href_to_source_key( $privacy_href ),
			'info_cards'    => $info_cards,
		);
	}

	/**
	 * Resolve a source file path.
	 *
	 * @param string $relative_file Relative file.
	 * @return string
	 */
	protected function resolve_source_path( $relative_file ) {
		return '' === $this->source_root ? '' : $this->source_root . DIRECTORY_SEPARATOR . str_replace( '/', DIRECTORY_SEPARATOR, ltrim( $relative_file, '/\\' ) );
	}

	/**
	 * Create DOM and XPath.
	 *
	 * @param string $html HTML.
	 * @return array{0:DOMDocument,1:DOMXPath}
	 */
	protected function create_dom_xpath( $html ) {
		$dom = new DOMDocument( '1.0', 'UTF-8' );
		libxml_use_internal_errors( true );
		$dom->loadHTML( '<?xml encoding="utf-8" ?>' . $html );
		libxml_clear_errors();

		return array( $dom, new DOMXPath( $dom ) );
	}

	/**
	 * Extract direct body sections.
	 *
	 * @param DOMXPath $xpath XPath.
	 * @return array<int,DOMNode>
	 */
	protected function extract_body_sections( $xpath ) {
		$nodes = array();
		foreach ( $this->query_nodes( $xpath, '//body/section' ) as $node ) {
			$nodes[] = $node;
		}
		return $nodes;
	}

	/**
	 * Query nodes from DOMXPath or DOMNode.
	 *
	 * @param DOMXPath|DOMNode $context Context.
	 * @param string           $query   XPath query.
	 * @return array<int,DOMNode>
	 */
	protected function query_nodes( $context, $query ) {
		if ( $context instanceof DOMXPath ) {
			$list = $context->query( $query );
		} else {
			$list = ( new DOMXPath( $context->ownerDocument ) )->query( $query, $context );
		}

		$out = array();
		if ( $list instanceof DOMNodeList ) {
			foreach ( $list as $node ) {
				if ( $node instanceof DOMNode ) {
					$out[] = $node;
				}
			}
		}
		return $out;
	}

	/**
	 * Return the first matching node.
	 *
	 * @param DOMXPath|DOMNode $context Context.
	 * @param string           $query   XPath query.
	 * @return DOMNode|null
	 */
	protected function first_node( $context, $query ) {
		$nodes = $this->query_nodes( $context, $query );
		return ! empty( $nodes[0] ) ? $nodes[0] : null;
	}

	/**
	 * Return text content.
	 *
	 * @param DOMXPath|DOMNode $context Context.
	 * @param string           $query   XPath query.
	 * @return string
	 */
	protected function text( $context, $query ) {
		$node = $this->first_node( $context, $query );
		return $node ? trim( preg_replace( '/\s+/', ' ', $node->textContent ) ) : '';
	}

	/**
	 * Return one attribute value.
	 *
	 * @param DOMXPath|DOMNode $context Context.
	 * @param string           $query   XPath query.
	 * @param string           $attr    Attribute name.
	 * @return string
	 */
	protected function attr( $context, $query, $attr ) {
		$node = $this->first_node( $context, $query );
		return ( $node instanceof DOMElement && $node->hasAttribute( $attr ) ) ? trim( (string) $node->getAttribute( $attr ) ) : '';
	}

	/**
	 * Return one node as inner HTML.
	 *
	 * @param DOMXPath|DOMNode $context Context.
	 * @param string           $query   XPath query.
	 * @return string
	 */
	protected function html( $context, $query ) {
		$node = $this->first_node( $context, $query );
		return $node ? $this->save_inner_html( $node ) : '';
	}

	/**
	 * Return the first non-empty HTML match from a list.
	 *
	 * @param DOMNode  $context Context node.
	 * @param string[] $queries Queries.
	 * @return string
	 */
	protected function first_available_html( $context, $queries ) {
		foreach ( $queries as $query ) {
			$html = $this->html( $context, $query );
			if ( '' !== trim( $html ) ) {
				return $html;
			}
		}
		return '';
	}

	/**
	 * Save inner HTML for one node.
	 *
	 * @param DOMNode $node Node.
	 * @return string
	 */
	protected function save_inner_html( $node ) {
		if ( $node instanceof DOMDocument ) {
			return '';
		}

		if ( $node instanceof DOMElement ) {
			$html = '';
			foreach ( $node->childNodes as $child ) {
				$html .= $node->ownerDocument->saveHTML( $child );
			}
			return $html;
		}

		return $node->ownerDocument->saveHTML( $node );
	}

	/**
	 * Join multiple nodes as HTML.
	 *
	 * @param array<int,DOMNode> $nodes Nodes.
	 * @return string
	 */
	protected function join_html( $nodes ) {
		$html = '';
		foreach ( $nodes as $node ) {
			$html .= $node->ownerDocument->saveHTML( $node );
		}
		return $html;
	}

	/**
	 * Parse image fields.
	 *
	 * @param DOMNode $context Context node.
	 * @param string  $query   XPath query.
	 * @return array{id:int,alt:string}
	 */
	protected function parse_image_fields( $context, $query ) {
		$src = $this->attr( $context, $query, 'src' );
		return array(
			'id'  => $this->resolve_image_field( $src )['id'],
			'alt' => $this->attr( $context, $query, 'alt' ),
		);
	}

	/**
	 * Resolve an image source path to an attachment field payload.
	 *
	 * @param string $src Raw src.
	 * @return array{id:int,alt:string}
	 */
	protected function resolve_image_field( $src ) {
		$path = $this->resolve_asset_source_path( $src );
		return array(
			'id'  => $this->get_attachment_id_by_source( $path ),
			'alt' => '',
		);
	}

	/**
	 * Resolve a background image from inline style.
	 *
	 * @param string $style Style string.
	 * @return array{id:int,alt:string}
	 */
	protected function resolve_image_from_style( $style ) {
		$src = '';
		if ( preg_match( '/url\\([\'"]?([^\'")]+)[\'"]?\\)/i', (string) $style, $matches ) ) {
			$src = (string) $matches[1];
		}

		return $this->resolve_image_field( $src );
	}

	/**
	 * Parse a button target and label.
	 *
	 * @param DOMNode $context Context node.
	 * @param string  $query   Button query.
	 * @return array<string,string>
	 */
	protected function parse_button( $context, $query = './/a[contains(@class,"btn")][1]' ) {
		$node = $this->first_node( $context, $query );
		if ( ! $node instanceof DOMElement ) {
			return array(
				'cta_label'    => '',
				'cta_page_key' => '',
				'cta_url'      => '',
			);
		}

		$link = $this->parse_link_target( (string) $node->getAttribute( 'href' ) );
		return array(
			'cta_label'    => trim( preg_replace( '/\s+/', ' ', $node->textContent ) ),
			'cta_page_key' => $link['page_key'],
			'cta_url'      => $link['url'],
		);
	}

	/**
	 * Rename CTA fields for nested repeaters.
	 *
	 * @param array<string,string> $button Button payload.
	 * @param string               $prefix Prefix.
	 * @return array<string,string>
	 */
	protected function prefix_button_fields( $button, $prefix ) {
		return array(
			$prefix . '_label'    => (string) ( $button['cta_label'] ?? '' ),
			$prefix . '_page_key' => (string) ( $button['cta_page_key'] ?? '' ),
			$prefix . '_url'      => (string) ( $button['cta_url'] ?? '' ),
		);
	}

	/**
	 * Parse an href into internal page key or URL.
	 *
	 * @param string $href Raw href.
	 * @return array{page_key:string,url:string}
	 */
	protected function parse_link_target( $href ) {
		return array(
			'page_key' => $this->map_href_to_source_key( $href ),
			'url'      => $this->is_internal_html_link( $href ) ? '' : trim( (string) $href ),
		);
	}

	/**
	 * Whether an href points to an internal imported HTML file.
	 *
	 * @param string $href Href.
	 * @return bool
	 */
	protected function is_internal_html_link( $href ) {
		return '' !== $this->map_href_to_source_key( $href );
	}

	/**
	 * Map one href to a FINORA source key.
	 *
	 * @param string $href Href.
	 * @return string
	 */
	protected function map_href_to_source_key( $href ) {
		$href = trim( html_entity_decode( (string) $href, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
		if ( '' === $href ) {
			return '';
		}

		if ( false !== strpos( $href, '#' ) ) {
			$href = substr( $href, 0, (int) strpos( $href, '#' ) );
		}

		$href = trim( str_replace( '\\', '/', $href ) );
		$href = preg_replace( '#^(?:https?:)?//[^/]+/#i', '', $href );
		$href = ltrim( (string) $href, '/' );

		$map = array(
			'index.html'                => 'finora-home-v1',
			'ueber-finora.html'         => 'finora-about-v1',
			'finora-philosophie.html'   => 'finora-philosophy-v1',
			'kontakt.html'              => 'finora-contact-v1',
			'altersvorsorge.html'       => 'finora-retirement-v1',
			'investment-beratung.html'  => 'finora-investment-v1',
			'immobilien-beratung.html'  => 'finora-real-estate-v1',
			'erbanlage-beratung.html'   => 'finora-inheritance-v1',
			'impressum.html'            => 'finora-impressum-v1',
			'datenschutz.html'          => 'finora-datenschutz-v1',
			'en/index.html'             => 'finora-home-v1',
			'en/ueber-finora.html'      => 'finora-about-v1',
			'en/finora-philosophie.html'=> 'finora-philosophy-v1',
			'en/kontakt.html'           => 'finora-contact-v1',
			'en/altersvorsorge.html'    => 'finora-retirement-v1',
			'en/investment-beratung.html' => 'finora-investment-v1',
			'en/immobilien-beratung.html' => 'finora-real-estate-v1',
			'en/erbanlage-beratung.html'  => 'finora-inheritance-v1',
			'en/impressum.html'         => 'finora-impressum-v1',
			'en/datenschutz.html'       => 'finora-datenschutz-v1',
		);

		return isset( $map[ $href ] ) ? $map[ $href ] : '';
	}

	/**
	 * Resolve a relative asset path.
	 *
	 * @param string $raw Raw path.
	 * @return string
	 */
	protected function resolve_asset_source_path( $raw ) {
		$raw = trim( html_entity_decode( (string) $raw, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
		if ( '' === $raw || preg_match( '#^(?:https?:)?//#i', $raw ) || preg_match( '#^(?:mailto:|tel:|data:|javascript:|#)#i', $raw ) ) {
			return '';
		}

		$raw = str_replace( '\\', '/', $raw );
		$raw = preg_replace( '#^\./#', '', $raw );
		return ltrim( (string) $raw, '/' );
	}

	/**
	 * Get attachment ID by source path.
	 *
	 * @param string $path Relative source path.
	 * @return int
	 */
	protected function get_attachment_id_by_source( $path ) {
		$path = trim( (string) $path, '/' );
		if ( '' === $path ) {
			return 0;
		}

		if ( isset( $this->attachment_cache[ $path ] ) ) {
			return $this->attachment_cache[ $path ];
		}

		$id = 0;
		if ( $this->media_importer ) {
			$id = (int) $this->media_importer->get_attachment_id_by_source( $path );
		}

		if ( ! $id ) {
			$query = new WP_Query(
				array(
					'post_type'      => 'attachment',
					'post_status'    => 'any',
					'fields'         => 'ids',
					'posts_per_page' => 1,
					'meta_query'     => array(
						array(
							'key'   => 'leadwerk_source_path',
							'value' => $path,
						),
					),
				)
			);
			$ids = $query->get_posts();
			$id  = ! empty( $ids ) ? (int) $ids[0] : 0;
		}

		$this->attachment_cache[ $path ] = $id;
		return $id;
	}

	/**
	 * Whether the first column is an image column.
	 *
	 * @param DOMNode $section_node Section node.
	 * @return bool
	 */
	protected function is_image_first( $section_node ) {
		$children = $this->query_nodes( $section_node, './/*[contains(@class,"two-col")][1]/*' );
		if ( empty( $children[0] ) || ! $children[0] instanceof DOMElement ) {
			return false;
		}
		return false !== strpos( (string) $children[0]->getAttribute( 'class' ), 'col-img' );
	}
}
