<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function leadwerk_theme_render_exact_page_group( $group, $value, $post_id = 0 ) {
	$resolved = function_exists( 'leadwerk_theme_resolve_structured_group_value' )
		? leadwerk_theme_resolve_structured_group_value( $group, $value, $post_id )
		: array(
			'value'         => $value,
			'override_html' => '',
		);

	$value = $resolved['value'] ?? $value;

	if ( ! empty( $resolved['override_html'] ) ) {
		return (string) $resolved['override_html'];
	}

	if ( empty( $group['layouts'] ) ) {
		return leadwerk_theme_render_exact_legal_group( $group, $value, $post_id );
	}

	$source_key        = (string) get_post_meta( $post_id, 'leadwerk_source_key', true );
	$template_sections = leadwerk_theme_get_source_template_sections( $source_key );
	$sections          = is_array( $value ) ? array_values( $value ) : array();
	$output            = '';
	$index             = 0;

	foreach ( (array) $group['layouts'] as $layout_key => $layout_schema ) {
		$section_value = isset( $sections[ $index ] ) && is_array( $sections[ $index ] ) ? $sections[ $index ] : array();
		$template_html = isset( $template_sections[ $index ] ) ? $template_sections[ $index ] : '';
		$output       .= leadwerk_theme_render_exact_layout_section( $layout_key, $layout_schema, $section_value, $template_html );
		++$index;
	}

	if ( '' === trim( wp_strip_all_tags( $output ) ) ) {
		return leadwerk_theme_render_exact_runtime_notice(
			'Exact shell rendering produced no visible content for "' . (string) ( $group['label'] ?? 'page' ) . '". Check source_shells and Leadwerk field data.',
			$post_id
		);
	}

	return $output;
}

function leadwerk_theme_render_exact_runtime_notice( $message, $post_id = 0 ) {
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return '';
	}

	return '<div class="runtime-notice runtime-notice--exact" style="margin:24px auto;max-width:1180px;padding:16px 18px;border:1px solid #fdba74;border-radius:16px;background:#fff7ed;color:#9a3412;">' . esc_html( $message ) . '</div>';
}

function leadwerk_theme_render_exact_legal_group( $group, $value, $post_id = 0 ) {
	$source_key = (string) get_post_meta( $post_id, 'leadwerk_source_key', true );
	$sections   = leadwerk_theme_get_source_template_sections( $source_key );

	if ( empty( $sections[0] ) || ! is_array( $value ) ) {
		return leadwerk_theme_render_exact_runtime_notice(
			'Exact legal shell is missing for "' . (string) ( $group['label'] ?? 'page' ) . '".',
			$post_id
		);
	}

	list( $dom, $xpath, $section_node ) = leadwerk_theme_create_template_dom( $sections[0] );
	if ( ! $section_node ) {
		return '';
	}

	leadwerk_theme_dom_set_inner_html(
		leadwerk_theme_dom_first( $xpath, './/*[contains(@class,"legal-title")][1]', $section_node ),
		(string) ( $value['headline'] ?? '' )
	);
	leadwerk_theme_dom_set_inner_html(
		leadwerk_theme_dom_first( $xpath, './/*[contains(@class,"legal-body")][1] | .//*[contains(@class,"legal-copy")][1]', $section_node ),
		(string) ( $value['content'] ?? '' )
	);

	return leadwerk_theme_dom_outer_html( $section_node );
}

function leadwerk_theme_render_exact_layout_section( $layout_key, $layout_schema, $section, $template_html ) {
	if ( '' === trim( $template_html ) ) {
		return '';
	}

	list( $dom, $xpath, $section_node ) = leadwerk_theme_create_template_dom( $template_html );
	if ( ! $section_node ) {
		return '';
	}

	leadwerk_theme_normalize_template_urls( $xpath, $section_node );

	$template = (string) ( $layout_schema['template'] ?? $layout_key );

	switch ( $template ) {
		case 'tech_hero':
			leadwerk_theme_bind_exact_tech_hero( $xpath, $section_node, $section );
			break;
		case 'tech_pain_points':
			leadwerk_theme_bind_exact_tech_pain_points( $xpath, $section_node, $section );
			break;
		case 'tech_audience':
			leadwerk_theme_bind_exact_tech_audience( $xpath, $section_node, $section );
			break;
		case 'tech_solution':
			leadwerk_theme_bind_exact_tech_solution( $xpath, $section_node, $section );
			break;
		case 'tech_pillars':
			leadwerk_theme_bind_exact_tech_pillars( $xpath, $section_node, $section );
			break;
		case 'tech_value':
			leadwerk_theme_bind_exact_tech_value( $xpath, $section_node, $section );
			break;
		case 'tech_process':
			leadwerk_theme_bind_exact_tech_process( $xpath, $section_node, $section );
			break;
		case 'tech_testimonials':
			leadwerk_theme_bind_exact_tech_testimonials( $xpath, $section_node, $section );
			break;
		case 'tech_final_cta':
			leadwerk_theme_bind_exact_tech_final_cta( $xpath, $section_node, $section );
			break;
		case 'hero':
			leadwerk_theme_bind_exact_hero( $xpath, $section_node, $section, false !== strpos( (string) $template_html, 'class="btn' ) );
			break;
		case 'hero_slider':
			leadwerk_theme_bind_exact_hero_slider( $xpath, $section_node, $section );
			break;
		case 'pillars':
			leadwerk_theme_bind_exact_pillars( $xpath, $section_node, $section );
			break;
		case 'audience_switcher':
			leadwerk_theme_bind_exact_audience_switcher( $xpath, $section_node, $section );
			break;
		case 'why_finora':
			leadwerk_theme_bind_exact_why_finora( $xpath, $section_node, $section );
			break;
		case 'how_it_works':
			leadwerk_theme_bind_exact_how_it_works( $xpath, $section_node, $section );
			break;
		case 'testimonials':
			leadwerk_theme_bind_exact_testimonials( $xpath, $section_node, $section );
			break;
		case 'faq':
			leadwerk_theme_bind_exact_faq( $xpath, $section_node, $section );
			break;
		case 'banner_cta':
			leadwerk_theme_bind_exact_banner_cta( $xpath, $section_node, $section );
			break;
		case 'about_bedeutet':
			leadwerk_theme_bind_exact_about_bedeutet( $xpath, $section_node, $section );
			break;
		case 'media_text':
			leadwerk_theme_bind_exact_media_text( $xpath, $section_node, $section );
			break;
		case 'workflow_blurbs':
			leadwerk_theme_bind_exact_workflow_blurbs( $xpath, $section_node, $section );
			break;
		case 'center_cta':
			leadwerk_theme_bind_exact_center_cta( $xpath, $section_node, $section );
			break;
		case 'tabs_section':
			leadwerk_theme_bind_exact_tabs_section( $xpath, $section_node, $section );
			break;
		case 'retirement_audience':
			leadwerk_theme_bind_exact_retirement_audience( $xpath, $section_node, $section );
			break;
		case 'concepts_section':
			leadwerk_theme_bind_exact_concepts_section( $xpath, $section_node, $section );
			break;
		case 'blurb_image_section':
			leadwerk_theme_bind_exact_blurb_image_section( $xpath, $section_node, $section );
			break;
		case 'approach_tiles':
			leadwerk_theme_bind_exact_approach_tiles( $xpath, $section_node, $section );
			break;
		case 'timeline':
			leadwerk_theme_bind_exact_timeline( $xpath, $section_node, $section );
			break;
		case 'target_groups':
			leadwerk_theme_bind_exact_target_groups( $xpath, $section_node, $section );
			break;
		case 'results_section':
			leadwerk_theme_bind_exact_results_section( $xpath, $section_node, $section );
			break;
		case 'real_estate_intro':
			leadwerk_theme_bind_exact_real_estate_intro( $xpath, $section_node, $section );
			break;
		case 'calculator':
			leadwerk_theme_bind_exact_calculator( $xpath, $section_node, $section );
			break;
		case 'case_highlight':
			leadwerk_theme_bind_exact_case_highlight( $xpath, $section_node, $section );
			break;
		case 'dark_cta':
			leadwerk_theme_bind_exact_dark_cta( $xpath, $section_node, $section );
			break;
		case 'responsibility':
			leadwerk_theme_bind_exact_responsibility( $xpath, $section_node, $section );
			break;
		case 'new_phase':
			leadwerk_theme_bind_exact_new_phase( $xpath, $section_node, $section );
			break;
		case 'outcomes':
			leadwerk_theme_bind_exact_outcomes( $xpath, $section_node, $section );
			break;
		case 'target_groups_image':
			leadwerk_theme_bind_exact_target_groups_image( $xpath, $section_node, $section );
			break;
		case 'audience_cards':
			leadwerk_theme_bind_exact_audience_cards( $xpath, $section_node, $section );
			break;
		case 'media_blurbs':
			leadwerk_theme_bind_exact_media_blurbs( $xpath, $section_node, $section );
			break;
		case 'invest_detail':
			leadwerk_theme_bind_exact_invest_detail( $xpath, $section_node, $section );
			break;
		case 'tax_detail':
			leadwerk_theme_bind_exact_tax_detail( $xpath, $section_node, $section );
			break;
		case 'contact_main':
			leadwerk_theme_bind_exact_contact_main( $xpath, $section_node, $section );
			break;
		case 'danke_main':
			leadwerk_theme_bind_exact_danke_main( $xpath, $section_node, $section );
			break;
	}

	if ( 0 === strpos( $template, 'tech_' ) ) {
		leadwerk_theme_mark_tech_contact_links( $xpath, $section_node );
	}

	return leadwerk_theme_dom_outer_html( $section_node );
}

/**
 * Preserve the Tech Expats navigation context on links to the contact page.
 *
 * @param DOMXPath $xpath XPath instance.
 * @param DOMNode  $section_node Section root.
 * @return void
 */
function leadwerk_theme_mark_tech_contact_links( $xpath, $section_node ) {
	$lang        = leadwerk_theme_get_current_lang();
	$contact_url = leadwerk_theme_get_page_url( 'finora-contact-v1', $lang );
	$contact_path = untrailingslashit( (string) wp_parse_url( $contact_url, PHP_URL_PATH ) );
	$links       = leadwerk_theme_dom_query( $xpath, './/a[@href]', $section_node );

	foreach ( $links as $link ) {
		if ( ! $link instanceof DOMElement ) {
			continue;
		}

		$href      = $link->getAttribute( 'href' );
		$href_path = untrailingslashit( (string) wp_parse_url( $href, PHP_URL_PATH ) );
		if ( '' !== $contact_path && $contact_path === $href_path ) {
			leadwerk_theme_dom_set_attr( $link, 'href', add_query_arg( 'from', 'tech-expats', $href ) );
		}
	}
}

function leadwerk_theme_get_source_template_map() {
	return array(
		'finora-home-v1'        => 'index.html',
		'finora-about-v1'       => 'ueber-finora.html',
		'finora-philosophy-v1'  => 'finora-philosophie.html',
		'finora-contact-v1'     => 'kontakt.html',
		'finora-danke-v1'       => 'danke.html',
		'finora-retirement-v1'  => 'altersvorsorge.html',
		'finora-investment-v1'  => 'investment-beratung.html',
		'finora-real-estate-v1' => 'immobilien-beratung.html',
		'finora-inheritance-v1' => 'erbanlage-beratung.html',
		'finora-tech-expats-v1' => 'tech-expats.html',
		'finora-impressum-v1'   => 'impressum.html',
		'finora-datenschutz-v1' => 'datenschutz.html',
		'finora-404-v1'         => '404.html',
	);
}

function leadwerk_theme_get_source_template_body_class_map() {
	return array(
		'finora-home-v1'        => 'page-home',
		'finora-about-v1'       => 'page-ueber',
		'finora-philosophy-v1'  => 'page-philosophie',
		'finora-contact-v1'     => 'page-kontakt',
		'finora-danke-v1'       => 'page-danke',
		'finora-retirement-v1'  => 'page-altersvorsorge',
		'finora-investment-v1'  => 'page-investment',
		'finora-real-estate-v1' => 'page-immobilien',
		'finora-inheritance-v1' => 'page-erbanlage',
		'finora-tech-expats-v1' => 'page-tech-expats',
		'finora-impressum-v1'   => 'page-legal',
		'finora-datenschutz-v1' => 'page-legal',
		'finora-404-v1'         => 'page-404 header-scrolled',
	);
}

function leadwerk_theme_normalize_body_class_string( $body_class ) {
	$classes = preg_split( '/\s+/', trim( (string) $body_class ) );
	$classes = is_array( $classes ) ? $classes : array();
	$classes = array_map( 'sanitize_html_class', $classes );
	$classes = array_values( array_unique( array_filter( $classes ) ) );

	return implode( ' ', $classes );
}

function leadwerk_theme_get_source_template_body_class( $source_key ) {
	static $cache = array();

	$source_key = (string) $source_key;
	if ( isset( $cache[ $source_key ] ) ) {
		return $cache[ $source_key ];
	}

	$body_class = '';
	$html       = leadwerk_theme_get_source_template_html( $source_key );

	if ( '' !== $html && preg_match( '/<body[^>]*class="([^"]+)"/i', $html, $matches ) ) {
		$body_class = leadwerk_theme_normalize_body_class_string( $matches[1] ?? '' );
	}

	if ( '' === $body_class ) {
		$fallback_map = leadwerk_theme_get_source_template_body_class_map();
		$body_class   = leadwerk_theme_normalize_body_class_string( $fallback_map[ $source_key ] ?? '' );
	}

	$cache[ $source_key ] = $body_class;
	return $cache[ $source_key ];
}

function leadwerk_theme_get_source_template_sections( $source_key ) {
	static $cache = array();

	$source_key = (string) $source_key;
	if ( isset( $cache[ $source_key ] ) ) {
		return $cache[ $source_key ];
	}

	$file_map  = leadwerk_theme_get_source_template_map();
	$file_name = $file_map[ $source_key ] ?? '';
	if ( '' === $file_name ) {
		$cache[ $source_key ] = array();
		return $cache[ $source_key ];
	}

	$file_path = trailingslashit( LEADWERK_THEME_DIR ) . 'source_shells/' . $file_name;
	if ( ! is_file( $file_path ) ) {
		$cache[ $source_key ] = array();
		return $cache[ $source_key ];
	}

	$html = file_get_contents( $file_path );
	if ( false === $html ) {
		$cache[ $source_key ] = array();
		return $cache[ $source_key ];
	}

	$cache[ $source_key ] = leadwerk_theme_extract_body_sections_from_html( (string) $html );
	return $cache[ $source_key ];
}

function leadwerk_theme_get_source_template_html( $source_key ) {
	static $cache = array();

	$source_key = (string) $source_key;
	if ( isset( $cache[ $source_key ] ) ) {
		return $cache[ $source_key ];
	}

	$file_map  = leadwerk_theme_get_source_template_map();
	$file_name = $file_map[ $source_key ] ?? '';
	if ( '' === $file_name ) {
		$cache[ $source_key ] = '';
		return '';
	}

	$file_path = trailingslashit( LEADWERK_THEME_DIR ) . 'source_shells/' . $file_name;
	if ( ! is_file( $file_path ) ) {
		$cache[ $source_key ] = '';
		return '';
	}

	$html = file_get_contents( $file_path );
	if ( false === $html ) {
		$cache[ $source_key ] = '';
		return '';
	}

	$cache[ $source_key ] = (string) $html;
	return $cache[ $source_key ];
}

function leadwerk_theme_get_exact_shell_source_key( $source_key = '' ) {
	$source_key = (string) $source_key;
	$file_map   = leadwerk_theme_get_source_template_map();

	if ( isset( $file_map[ $source_key ] ) ) {
		return $source_key;
	}

	return 'finora-home-v1';
}

function leadwerk_theme_create_document_dom( $html ) {
	$dom = new DOMDocument( '1.0', 'UTF-8' );
	libxml_use_internal_errors( true );
	$dom->loadHTML( '<?xml encoding="utf-8" ?>' . $html );
	libxml_clear_errors();

	return array(
		$dom,
		new DOMXPath( $dom ),
	);
}

function leadwerk_theme_render_exact_site_header() {
	$source_key = leadwerk_theme_get_exact_shell_source_key( leadwerk_theme_get_current_source_key() );
	$html       = leadwerk_theme_get_source_template_html( $source_key );

	if ( '' === trim( $html ) ) {
		return '';
	}

	list( $dom, $xpath ) = leadwerk_theme_create_document_dom( $html );

	$cursor = leadwerk_theme_dom_first( $xpath, '//body/*[contains(concat(" ", normalize-space(@class), " "), " cursor-follower ")][1]' );
	$header = leadwerk_theme_dom_first( $xpath, '//body/header[contains(concat(" ", normalize-space(@class), " "), " site-header ")][1]' );

	if ( ! $header instanceof DOMElement ) {
		return '';
	}

	leadwerk_theme_normalize_template_urls( $xpath, $header );

	$lang            = leadwerk_theme_get_current_lang();
	$strings         = leadwerk_theme_get_theme_strings( $lang );
	$current_key     = leadwerk_theme_get_current_source_key();
	$from_tech       = 'finora-contact-v1' === $current_key
		&& isset( $_GET['from'] )
		&& 'tech-expats' === sanitize_key( wp_unslash( $_GET['from'] ) );
	$home_url        = leadwerk_theme_get_page_url( 'finora-home-v1', $lang, home_url( '/' ) );
	$language_url    = leadwerk_theme_get_alternate_language_url();
	$de_url          = leadwerk_theme_get_page_url( $current_key, 'de', home_url( '/' ) );
	$en_url          = leadwerk_theme_get_page_url( $current_key, 'en', home_url( '/en/' ) );
	if ( $from_tech ) {
		$language_url = add_query_arg( 'from', 'tech-expats', $language_url );
		$de_url       = add_query_arg( 'from', 'tech-expats', $de_url );
		$en_url       = add_query_arg( 'from', 'tech-expats', $en_url );
	}
	$is_service_page = leadwerk_theme_is_service_page();
	$service_label   = $strings['services_menu_label'] ?? 'Leistungen';
	$lang_group_label = $strings['header_language_group_label'] ?? ( 'en' === $lang ? 'Choose language' : 'Sprache wählen' );
	$lang_button_label = $strings['header_language_button_label'] ?? ( 'en' === $lang ? 'Change language' : 'Sprache wechseln' );
	$open_menu_label = $strings['header_open_menu_label'] ?? ( 'en' === $lang ? 'Open menu' : 'Menü öffnen' );
	$lang_option_de  = $strings['header_language_option_de'] ?? 'Deutsch';
	$lang_option_en  = $strings['header_language_option_en'] ?? 'English';
	$language_label  = 'en' === $lang ? 'EN' : 'DE';
	$other_short     = 'en' === $lang ? 'DE' : 'EN';
	$output          = array();

	$header_logo_link = leadwerk_theme_dom_first( $xpath, './/*[contains(concat(" ", normalize-space(@class), " "), " header-logo ")]/a[1]', $header );
	$header_logo_img  = leadwerk_theme_dom_first( $xpath, './/*[contains(concat(" ", normalize-space(@class), " "), " header-logo ")]//img[1]', $header );
	$service_li       = leadwerk_theme_dom_first( $xpath, './/*[contains(concat(" ", normalize-space(@class), " "), " nav-menu ")]/li[1]', $header );
	$service_anchor   = leadwerk_theme_dom_first( $xpath, './/*[contains(concat(" ", normalize-space(@class), " "), " nav-menu ")]/li[1]/a[1]', $header );
	$nav_anchors      = leadwerk_theme_dom_query( $xpath, './/*[contains(concat(" ", normalize-space(@class), " "), " nav-menu ")]/li', $header );
	$dropdown_links   = leadwerk_theme_dom_query( $xpath, './/*[contains(concat(" ", normalize-space(@class), " "), " header-lang-dropdown ")]/a', $header );
	$mobile_links     = leadwerk_theme_dom_query( $xpath, './/*[contains(concat(" ", normalize-space(@class), " "), " mobile-menu ")]/a', $header );
	$mobile_sub_links = leadwerk_theme_dom_query( $xpath, './/*[contains(concat(" ", normalize-space(@class), " "), " mobile-menu ")]/*[contains(concat(" ", normalize-space(@class), " "), " sub-menu ")]/a', $header );

	leadwerk_theme_dom_set_attr( $header_logo_link, 'href', $home_url );
	leadwerk_theme_dom_set_attr( $header_logo_img, 'src', leadwerk_theme_get_option_image_url( 'header_logo', 'assets/images/Logo-final-weiss-rz_svg.svg' ) );
	leadwerk_theme_dom_set_attr( $header_logo_img, 'alt', 'Finora Investment Studio' );

	leadwerk_theme_dom_toggle_class( $service_li, 'is-active', $is_service_page );
	leadwerk_theme_dom_set_text( $service_anchor, $service_label );

	$service_pages = array(
		'finora-retirement-v1'  => 'Altersvorsorge-Beratung',
		'finora-investment-v1'  => 'Investment-Beratung',
		'finora-real-estate-v1' => 'Immobilien-Beratung',
		'finora-inheritance-v1' => 'Erbanlage-Beratung',
	);
	$service_keys = array_keys( $service_pages );
	foreach ( $service_keys as $index => $service_key ) {
		$link = $mobile_sub_links[ $index ] ?? null;
		if ( $link instanceof DOMElement ) {
			leadwerk_theme_dom_set_attr( $link, 'href', leadwerk_theme_get_page_url( $service_key, $lang ) );
			leadwerk_theme_dom_set_text( $link, leadwerk_theme_get_page_title( $service_key, $lang, $service_pages[ $service_key ] ) );
		}

		$link = leadwerk_theme_dom_first( $xpath, './/*[contains(concat(" ", normalize-space(@class), " "), " nav-menu ")]/li[1]/*[contains(concat(" ", normalize-space(@class), " "), " sub-menu ")]/li[' . ( $index + 1 ) . ']/a[1]', $header );
		if ( $link instanceof DOMElement ) {
			leadwerk_theme_dom_set_attr( $link, 'href', leadwerk_theme_get_page_url( $service_key, $lang ) );
			leadwerk_theme_dom_set_text( $link, leadwerk_theme_get_page_title( $service_key, $lang, $service_pages[ $service_key ] ) );
		}
	}

	$primary_pages = array(
		2 => array(
			'key'      => 'finora-philosophy-v1',
			'fallback' => 'Finora-Philosophie',
		),
		3 => array(
			'key'      => 'finora-about-v1',
			'fallback' => 'Ueber Finora',
		),
		4 => array(
			'key'      => 'finora-contact-v1',
			'fallback' => 'Kontakt',
		),
	);
	foreach ( $primary_pages as $position => $page_config ) {
		$page_key = $page_config['key'];
		$item = $nav_anchors[ $position - 1 ] ?? null;
		if ( $item instanceof DOMElement ) {
			$link = leadwerk_theme_dom_first( $xpath, './a[1]', $item );
			if ( $link instanceof DOMElement ) {
				leadwerk_theme_dom_set_attr( $link, 'href', leadwerk_theme_get_page_url( $page_key, $lang ) );
				leadwerk_theme_dom_set_text( $link, leadwerk_theme_get_page_title( $page_key, $lang, $page_config['fallback'] ) );
				leadwerk_theme_dom_toggle_class( $link, 'is-active', leadwerk_theme_is_source_key( $page_key ) );
			}
		}

		$mobile_link = $mobile_links[ $position - 1 ] ?? null;
		if ( $mobile_link instanceof DOMElement ) {
			leadwerk_theme_dom_set_attr( $mobile_link, 'href', leadwerk_theme_get_page_url( $page_key, $lang ) );
			leadwerk_theme_dom_set_text( $mobile_link, leadwerk_theme_get_page_title( $page_key, $lang, $page_config['fallback'] ) );
			leadwerk_theme_dom_toggle_class( $mobile_link, 'is-active', leadwerk_theme_is_source_key( $page_key ) );
		}
	}

	$header_lang_group    = leadwerk_theme_dom_first( $xpath, './/*[contains(concat(" ", normalize-space(@class), " "), " header-lang ")]', $header );
	$header_lang_button   = leadwerk_theme_dom_first( $xpath, './/*[contains(concat(" ", normalize-space(@class), " "), " header-lang-btn ")]', $header );
	$header_lang_label    = leadwerk_theme_dom_first( $xpath, './/*[contains(concat(" ", normalize-space(@class), " "), " header-lang-label ")]', $header );
	$mobile_locale_link   = leadwerk_theme_dom_first( $xpath, './/*[contains(concat(" ", normalize-space(@class), " "), " mobile-menu-locale ")]', $header );
	$mobile_menu_toggle   = leadwerk_theme_dom_first( $xpath, './/*[contains(concat(" ", normalize-space(@class), " "), " mobile-menu-toggle ")]', $header );
	$mobile_menu_services = $mobile_links[0] ?? null;

	leadwerk_theme_dom_set_attr( $header_lang_group, 'aria-label', $lang_group_label );
	leadwerk_theme_dom_set_attr( $header_lang_button, 'aria-label', $lang_button_label );
	leadwerk_theme_dom_set_attr( $header_lang_button, 'title', $lang_button_label );
	leadwerk_theme_dom_set_text( $header_lang_label, $language_label );
	leadwerk_theme_dom_set_attr( $mobile_menu_toggle, 'aria-label', $open_menu_label );
	leadwerk_theme_dom_set_text( $mobile_menu_services, $service_label );

	if ( isset( $dropdown_links[0] ) && $dropdown_links[0] instanceof DOMElement ) {
		leadwerk_theme_dom_set_attr( $dropdown_links[0], 'href', $de_url );
		leadwerk_theme_dom_set_attr( $dropdown_links[0], 'hreflang', 'de' );
		leadwerk_theme_dom_set_attr( $dropdown_links[0], 'lang', 'de' );
		leadwerk_theme_dom_set_text( $dropdown_links[0], $lang_option_de );
		leadwerk_theme_dom_toggle_class( $dropdown_links[0], 'is-active', 'de' === $lang );
	}

	if ( isset( $dropdown_links[1] ) && $dropdown_links[1] instanceof DOMElement ) {
		leadwerk_theme_dom_set_attr( $dropdown_links[1], 'href', $en_url );
		leadwerk_theme_dom_set_attr( $dropdown_links[1], 'hreflang', 'en' );
		leadwerk_theme_dom_set_attr( $dropdown_links[1], 'lang', 'en' );
		leadwerk_theme_dom_set_text( $dropdown_links[1], $lang_option_en );
		leadwerk_theme_dom_toggle_class( $dropdown_links[1], 'is-active', 'en' === $lang );
	}

	leadwerk_theme_dom_set_attr( $mobile_locale_link, 'href', $language_url );
	leadwerk_theme_dom_set_text( $mobile_locale_link, $other_short );

	if ( 'finora-tech-expats-v1' === $current_key ) {
		$contact_url = add_query_arg( 'from', 'tech-expats', leadwerk_theme_get_page_url( 'finora-contact-v1', $lang ) );
		$contact_nav = $nav_anchors[3] ?? null;
		$contact_link = $contact_nav instanceof DOMElement ? leadwerk_theme_dom_first( $xpath, './a[1]', $contact_nav ) : null;
		leadwerk_theme_dom_set_attr( $contact_link, 'href', $contact_url );

		$mobile_contact_link = $mobile_links[3] ?? null;
		leadwerk_theme_dom_set_attr( $mobile_contact_link, 'href', $contact_url );

		if ( $header_logo_link instanceof DOMElement && $header_logo_img instanceof DOMElement && $header_logo_link->parentNode ) {
			$header_logo_link->parentNode->insertBefore( $header_logo_img, $header_logo_link );
			$header_logo_link->parentNode->removeChild( $header_logo_link );
		}

		foreach ( $nav_anchors as $index => $nav_item ) {
			if ( 3 !== $index && $nav_item instanceof DOMNode && $nav_item->parentNode ) {
				$nav_item->parentNode->removeChild( $nav_item );
			}
		}

		$mobile_sub_menu = leadwerk_theme_dom_first( $xpath, './/*[contains(concat(" ", normalize-space(@class), " "), " mobile-menu ")]/*[contains(concat(" ", normalize-space(@class), " "), " sub-menu ")]', $header );
		if ( $mobile_sub_menu instanceof DOMNode && $mobile_sub_menu->parentNode ) {
			$mobile_sub_menu->parentNode->removeChild( $mobile_sub_menu );
		}

		foreach ( $mobile_links as $index => $mobile_link ) {
			if ( ! in_array( $index, array( 3, 4 ), true ) && $mobile_link instanceof DOMNode && $mobile_link->parentNode ) {
				$mobile_link->parentNode->removeChild( $mobile_link );
			}
		}
	}

	if ( $from_tech ) {
		$tech_url = leadwerk_theme_get_page_url( 'finora-tech-expats-v1', $lang );
		$tech_nav = $nav_anchors[3] ?? null;
		$tech_link = $tech_nav instanceof DOMElement ? leadwerk_theme_dom_first( $xpath, './a[1]', $tech_nav ) : null;
		leadwerk_theme_dom_set_attr( $tech_link, 'href', $tech_url );
		leadwerk_theme_dom_set_text( $tech_link, 'Tech Expats' );
		leadwerk_theme_dom_toggle_class( $tech_link, 'is-active', false );

		if ( $header_logo_link instanceof DOMElement && $header_logo_img instanceof DOMElement && $header_logo_link->parentNode ) {
			$header_logo_link->parentNode->insertBefore( $header_logo_img, $header_logo_link );
			$header_logo_link->parentNode->removeChild( $header_logo_link );
		}

		foreach ( $nav_anchors as $index => $nav_item ) {
			if ( 3 !== $index && $nav_item instanceof DOMNode && $nav_item->parentNode ) {
				$nav_item->parentNode->removeChild( $nav_item );
			}
		}

		$mobile_sub_menu = leadwerk_theme_dom_first( $xpath, './/*[contains(concat(" ", normalize-space(@class), " "), " mobile-menu ")]/*[contains(concat(" ", normalize-space(@class), " "), " sub-menu ")]', $header );
		if ( $mobile_sub_menu instanceof DOMNode && $mobile_sub_menu->parentNode ) {
			$mobile_sub_menu->parentNode->removeChild( $mobile_sub_menu );
		}

		$mobile_tech_link = $mobile_links[3] ?? null;
		leadwerk_theme_dom_set_attr( $mobile_tech_link, 'href', $tech_url );
		leadwerk_theme_dom_set_text( $mobile_tech_link, 'Tech Expats' );
		leadwerk_theme_dom_toggle_class( $mobile_tech_link, 'is-active', false );

		foreach ( $mobile_links as $index => $mobile_link ) {
			if ( ! in_array( $index, array( 3, 4 ), true ) && $mobile_link instanceof DOMNode && $mobile_link->parentNode ) {
				$mobile_link->parentNode->removeChild( $mobile_link );
			}
		}
	}

	foreach ( array(
		$cursor instanceof DOMNode ? leadwerk_theme_dom_outer_html( $cursor ) : '',
		leadwerk_theme_dom_outer_html( $header ),
	) as $chunk ) {
		if ( '' !== trim( $chunk ) ) {
			$output[] = $chunk;
		}
	}

	return implode( "\n", $output ?? array() );
}

function leadwerk_theme_render_exact_site_footer() {
	$source_key = leadwerk_theme_get_exact_shell_source_key( leadwerk_theme_get_current_source_key() );
	$html       = leadwerk_theme_get_source_template_html( $source_key );

	if ( '' === trim( $html ) ) {
		return '';
	}

	list( $dom, $xpath ) = leadwerk_theme_create_document_dom( $html );
	$footer = leadwerk_theme_dom_first( $xpath, '//body/footer[contains(concat(" ", normalize-space(@class), " "), " site-footer ")][1]' );

	if ( ! $footer instanceof DOMElement ) {
		return '';
	}

	leadwerk_theme_normalize_template_urls( $xpath, $footer );

	$lang            = leadwerk_theme_get_current_lang();
	$strings         = leadwerk_theme_get_theme_strings( $lang );
	$source_key      = leadwerk_theme_get_current_source_key();
	$footer_desc_key = leadwerk_theme_is_legal_source_key( $source_key ) ? 'footer_desc_legal' : ( 'finora-home-v1' === $source_key ? 'footer_desc_home' : 'footer_desc_general' );
	$address         = leadwerk_theme_get_option_value( 'company_address', "Brauerstr. 17\n76137 Karlsruhe" );
	$phone           = leadwerk_theme_get_option_value( 'company_phone', '01512 8915214' );
	$email           = leadwerk_theme_get_option_value( 'company_email', 'hallo@finora.de' );
	$phone_prefix    = $strings['footer_phone_prefix'] ?? ( 'en' === $lang ? 'Phone:' : 'Tel.:' );

	$logo      = leadwerk_theme_dom_first( $xpath, './/*[contains(concat(" ", normalize-space(@class), " "), " footer-logo ")]', $footer );
	$wordmark  = leadwerk_theme_dom_first( $xpath, './/*[contains(concat(" ", normalize-space(@class), " "), " footer-logo-full__img ")]', $footer );
	$desc      = leadwerk_theme_dom_first( $xpath, './/*[contains(concat(" ", normalize-space(@class), " "), " footer-desc ")]', $footer );
	$menu_h4   = leadwerk_theme_dom_first( $xpath, './/*[contains(concat(" ", normalize-space(@class), " "), " footer-main ")]/*[contains(concat(" ", normalize-space(@class), " "), " footer-col ")][2]/h4[1]', $footer );
	$topic_h4  = leadwerk_theme_dom_first( $xpath, './/*[contains(concat(" ", normalize-space(@class), " "), " footer-main ")]/*[contains(concat(" ", normalize-space(@class), " "), " footer-col ")][3]/h4[1]', $footer );
	$contact_h4 = leadwerk_theme_dom_first( $xpath, './/*[contains(concat(" ", normalize-space(@class), " "), " footer-contact ")]/h4[1]', $footer );
	$menu_links = leadwerk_theme_dom_query( $xpath, './/*[contains(concat(" ", normalize-space(@class), " "), " footer-main ")]/*[contains(concat(" ", normalize-space(@class), " "), " footer-col ")][2]//a', $footer );
	$topic_links = leadwerk_theme_dom_query( $xpath, './/*[contains(concat(" ", normalize-space(@class), " "), " footer-main ")]/*[contains(concat(" ", normalize-space(@class), " "), " footer-col ")][3]//a', $footer );
	$contact_items = leadwerk_theme_dom_query( $xpath, './/*[contains(concat(" ", normalize-space(@class), " "), " footer-contact ")]/*[contains(concat(" ", normalize-space(@class), " "), " contact-item ")]', $footer );
	$bottom_links = leadwerk_theme_dom_query( $xpath, './/*[contains(concat(" ", normalize-space(@class), " "), " footer-bottom ")]//a', $footer );

	leadwerk_theme_dom_set_attr( $logo, 'src', leadwerk_theme_get_option_image_url( 'footer_logo', 'assets/images/Logo-final-weiss-rz.png' ) );
	leadwerk_theme_dom_set_attr( $wordmark, 'src', leadwerk_theme_get_option_image_url( 'footer_wordmark', 'assets/images/Schriftzug.svg' ) );
	leadwerk_theme_dom_set_text( $desc, $strings[ $footer_desc_key ] ?? '' );
	leadwerk_theme_dom_set_text( $menu_h4, $strings['footer_menu_heading'] ?? 'Menü' );
	leadwerk_theme_dom_set_text( $topic_h4, $strings['footer_topics_heading'] ?? 'Themen' );
	leadwerk_theme_dom_set_text( $contact_h4, $strings['footer_contact_heading'] ?? 'Kontakt' );

	$menu_pages = array(
		'finora-home-v1'       => $strings['footer_menu_home'] ?? 'Startseite',
		'finora-philosophy-v1' => leadwerk_theme_get_page_title( 'finora-philosophy-v1', $lang, 'Finora-Philosophie' ),
		'finora-about-v1'      => leadwerk_theme_get_page_title( 'finora-about-v1', $lang, 'Ueber Finora' ),
		'finora-contact-v1'    => leadwerk_theme_get_page_title( 'finora-contact-v1', $lang, 'Kontakt' ),
	);
	foreach ( array_values( $menu_pages ) as $index => $menu_label ) {
		$link_keys = array_keys( $menu_pages );
		$link_key  = $link_keys[ $index ] ?? '';
		$link      = $menu_links[ $index ] ?? null;
		if ( $link instanceof DOMElement && '' !== $link_key ) {
			leadwerk_theme_dom_set_attr( $link, 'href', leadwerk_theme_get_page_url( $link_key, $lang ) );
			leadwerk_theme_dom_set_text( $link, $menu_label );
		}
	}

	$topic_pages = array(
		'finora-retirement-v1'  => 'Altersvorsorge-Beratung',
		'finora-investment-v1'  => 'Investment-Beratung',
		'finora-real-estate-v1' => 'Immobilien-Beratung',
		'finora-inheritance-v1' => 'Erbanlage-Beratung',
	);
	$topic_keys = array_keys( $topic_pages );
	foreach ( $topic_keys as $index => $topic_key ) {
		$link = $topic_links[ $index ] ?? null;
		if ( $link instanceof DOMElement ) {
			leadwerk_theme_dom_set_attr( $link, 'href', leadwerk_theme_get_page_url( $topic_key, $lang ) );
			leadwerk_theme_dom_set_text( $link, leadwerk_theme_get_page_title( $topic_key, $lang, $topic_pages[ $topic_key ] ) );
		}
	}

	$address_lines = preg_split( '/\r\n|\r|\n/', (string) $address );
	$address_html  = implode( '<br>', array_map( 'esc_html', array_filter( array_map( 'trim', (array) $address_lines ) ) ) );
	$address_node  = isset( $contact_items[0] ) ? leadwerk_theme_dom_first( $xpath, './p[1]', $contact_items[0] ) : null;
	$phone_node    = isset( $contact_items[1] ) ? leadwerk_theme_dom_first( $xpath, './p[1]', $contact_items[1] ) : null;
	$email_node    = isset( $contact_items[2] ) ? leadwerk_theme_dom_first( $xpath, './p[1]', $contact_items[2] ) : null;

	leadwerk_theme_dom_set_inner_html( $address_node, $address_html );
	leadwerk_theme_dom_set_inner_html( $phone_node, esc_html( $phone_prefix ) . ' <a href="' . esc_url( 'tel:' . preg_replace( '/\s+/', '', $phone ) ) . '">' . esc_html( $phone ) . '</a>' );
	leadwerk_theme_dom_set_inner_html( $email_node, '<a href="' . esc_url( 'mailto:' . $email ) . '">' . esc_html( $email ) . '</a>' );

	if ( isset( $bottom_links[0] ) && $bottom_links[0] instanceof DOMElement ) {
		leadwerk_theme_dom_set_attr( $bottom_links[0], 'href', leadwerk_theme_get_page_url( 'finora-impressum-v1', $lang ) );
		leadwerk_theme_dom_set_text( $bottom_links[0], leadwerk_theme_get_page_title( 'finora-impressum-v1', $lang, 'Impressum' ) );
	}

	if ( isset( $bottom_links[1] ) && $bottom_links[1] instanceof DOMElement ) {
		leadwerk_theme_dom_set_attr( $bottom_links[1], 'href', leadwerk_theme_get_page_url( 'finora-datenschutz-v1', $lang ) );
		leadwerk_theme_dom_set_text( $bottom_links[1], leadwerk_theme_get_page_title( 'finora-datenschutz-v1', $lang, 'Datenschutz' ) );
	}

	return leadwerk_theme_dom_outer_html( $footer );
}

function leadwerk_theme_map_template_href_to_url( $href ) {
	$href = trim( (string) $href );
	if ( '' === $href || '#' === $href || 0 === strpos( $href, '#' ) ) {
		return $href;
	}

	if ( preg_match( '#^(?:https?:)?//#i', $href ) || preg_match( '#^(?:mailto|tel):#i', $href ) ) {
		return $href;
	}

	$normalized = str_replace( '\\', '/', $href );
	$lang       = leadwerk_theme_get_current_lang();

	if ( 0 === strpos( $normalized, 'assets/' ) ) {
		return trailingslashit( LEADWERK_THEME_URI ) . ltrim( $normalized, '/' );
	}

	if ( 0 === strpos( $normalized, 'en/' ) ) {
		$lang       = 'en';
		$normalized = substr( $normalized, 3 );
	}

	$file_name  = basename( $normalized );
	$source_key = array_search( $file_name, leadwerk_theme_get_source_template_map(), true );
	if ( false !== $source_key ) {
		$fallback = 'de' === $lang ? home_url( '/' ) : home_url( '/' . $lang . '/' );
		return leadwerk_theme_get_page_url( (string) $source_key, $lang, $fallback );
	}

	return $href;
}

function leadwerk_theme_normalize_template_urls( $xpath, $section_node ) {
	foreach ( leadwerk_theme_dom_query( $xpath, './/*[@src]', $section_node ) as $node ) {
		if ( $node instanceof DOMElement ) {
			$src = leadwerk_theme_map_template_href_to_url( (string) $node->getAttribute( 'src' ) );
			leadwerk_theme_dom_set_attr( $node, 'src', $src );
		}
	}

	foreach ( array( 'poster', 'data-img', 'data-src', 'data-bg' ) as $attribute ) {
		foreach ( leadwerk_theme_dom_query( $xpath, './/*[@' . $attribute . ']', $section_node ) as $node ) {
			if ( $node instanceof DOMElement ) {
				$value = leadwerk_theme_map_template_href_to_url( (string) $node->getAttribute( $attribute ) );
				leadwerk_theme_dom_set_attr( $node, $attribute, $value );
			}
		}
	}

	foreach ( leadwerk_theme_dom_query( $xpath, './/*[@href]', $section_node ) as $node ) {
		if ( $node instanceof DOMElement ) {
			$href = leadwerk_theme_map_template_href_to_url( (string) $node->getAttribute( 'href' ) );
			leadwerk_theme_dom_set_attr( $node, 'href', $href );
		}
	}

	foreach ( leadwerk_theme_dom_query( $xpath, './/*[@style]', $section_node ) as $node ) {
		if ( ! $node instanceof DOMElement ) {
			continue;
		}

		$style = (string) $node->getAttribute( 'style' );
		if ( preg_match_all( '/url\((["\']?)([^"\')]+)\1\)/i', $style, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $match ) {
				$original = $match[2] ?? '';
				$mapped   = leadwerk_theme_map_template_href_to_url( (string) $original );
				if ( $mapped !== $original ) {
					$style = str_replace( $original, $mapped, $style );
				}
			}
			$node->setAttribute( 'style', $style );
		}
	}

	foreach ( leadwerk_theme_dom_query( $xpath, './/*[@srcset]', $section_node ) as $node ) {
		if ( ! $node instanceof DOMElement ) {
			continue;
		}

		$parts = array_map( 'trim', explode( ',', (string) $node->getAttribute( 'srcset' ) ) );
		$parts = array_map(
			static function ( $part ) {
				if ( '' === $part ) {
					return '';
				}

				$segments = preg_split( '/\s+/', $part, 2 );
				$url      = leadwerk_theme_map_template_href_to_url( (string) ( $segments[0] ?? '' ) );
				$descriptor = trim( (string) ( $segments[1] ?? '' ) );

				return '' !== $descriptor ? $url . ' ' . $descriptor : $url;
			},
			$parts
		);

		$node->setAttribute( 'srcset', implode( ', ', array_filter( $parts ) ) );
	}
}

function leadwerk_theme_extract_body_sections_from_html( $html ) {
	$sections = array();
	$dom      = new DOMDocument( '1.0', 'UTF-8' );

	libxml_use_internal_errors( true );
	$dom->loadHTML( '<?xml encoding="utf-8" ?>' . $html );
	libxml_clear_errors();

	$xpath = new DOMXPath( $dom );
	$list  = $xpath->query( '//body/section' );
	if ( $list instanceof DOMNodeList ) {
		foreach ( $list as $node ) {
			if ( $node instanceof DOMNode ) {
				$sections[] = $dom->saveHTML( $node );
			}
		}
	}

	return $sections;
}

function leadwerk_theme_create_template_dom( $html ) {
	$dom = new DOMDocument( '1.0', 'UTF-8' );
	libxml_use_internal_errors( true );
	$dom->loadHTML( '<?xml encoding="utf-8" ?><div id="leadwerk-root">' . $html . '</div>' );
	libxml_clear_errors();

	$xpath   = new DOMXPath( $dom );
	$section = leadwerk_theme_dom_first( $xpath, '//*[@id="leadwerk-root"]/*[1]' );

	return array( $dom, $xpath, $section instanceof DOMElement ? $section : null );
}

function leadwerk_theme_dom_query( $context, $query, $scope = null ) {
	if ( $context instanceof DOMXPath ) {
		$list = $context->query( $query, $scope );
	} else {
		$list = ( new DOMXPath( $context->ownerDocument ) )->query( $query, $context );
	}

	$nodes = array();
	if ( $list instanceof DOMNodeList ) {
		foreach ( $list as $node ) {
			if ( $node instanceof DOMNode ) {
				$nodes[] = $node;
			}
		}
	}

	return $nodes;
}

function leadwerk_theme_dom_first( $context, $query, $scope = null ) {
	$nodes = leadwerk_theme_dom_query( $context, $query, $scope );
	return ! empty( $nodes[0] ) ? $nodes[0] : null;
}

function leadwerk_theme_dom_outer_html( $node ) {
	return $node instanceof DOMNode ? $node->ownerDocument->saveHTML( $node ) : '';
}

function leadwerk_theme_dom_clear( $node ) {
	if ( ! $node instanceof DOMNode ) {
		return;
	}

	while ( $node->firstChild ) {
		$node->removeChild( $node->firstChild );
	}
}

function leadwerk_theme_dom_set_inner_html( $node, $html ) {
	if ( ! $node instanceof DOMNode ) {
		return;
	}

	$html = (string) $html;
	leadwerk_theme_dom_clear( $node );

	if ( '' === trim( $html ) ) {
		return;
	}

	$temp = new DOMDocument( '1.0', 'UTF-8' );
	libxml_use_internal_errors( true );
	$temp->loadHTML( '<?xml encoding="utf-8" ?><div id="leadwerk-fragment">' . wp_kses_post( $html ) . '</div>' );
	libxml_clear_errors();

	$fragment_nodes = ( new DOMXPath( $temp ) )->query( '//*[@id="leadwerk-fragment"]/* | //*[@id="leadwerk-fragment"]/text()' );
	if ( ! $fragment_nodes instanceof DOMNodeList ) {
		return;
	}

	foreach ( $fragment_nodes as $child ) {
		$node->appendChild( $node->ownerDocument->importNode( $child, true ) );
	}
}

function leadwerk_theme_dom_set_trusted_inner_html( $node, $html ) {
	if ( ! $node instanceof DOMNode ) {
		return;
	}

	$html = (string) $html;
	leadwerk_theme_dom_clear( $node );

	if ( '' === trim( $html ) ) {
		return;
	}

	$temp = new DOMDocument( '1.0', 'UTF-8' );
	libxml_use_internal_errors( true );
	$temp->loadHTML( '<?xml encoding="utf-8" ?><div id="leadwerk-fragment">' . $html . '</div>' );
	libxml_clear_errors();

	$fragment_nodes = ( new DOMXPath( $temp ) )->query( '//*[@id="leadwerk-fragment"]/* | //*[@id="leadwerk-fragment"]/text()' );
	if ( ! $fragment_nodes instanceof DOMNodeList ) {
		return;
	}

	foreach ( $fragment_nodes as $child ) {
		$node->appendChild( $node->ownerDocument->importNode( $child, true ) );
	}
}

function leadwerk_theme_normalize_heading_markup( $html ) {
	$html = (string) $html;

	if ( class_exists( 'Leadwerk_Content_Schema' ) && method_exists( 'Leadwerk_Content_Schema', 'sanitize_heading_html' ) ) {
		return Leadwerk_Content_Schema::sanitize_heading_html( $html );
	}

	$html = wp_kses_post( $html );
	if ( '' === trim( wp_strip_all_tags( $html ) ) ) {
		return '';
	}

	$html = preg_replace( '#</?(p|div|section|article|h1|h2|h3|h4|h5|h6)\b[^>]*>#i', '', $html );
	$html = preg_replace( '/(?:<br>\s*){3,}/i', '<br><br>', (string) $html );
	$html = is_string( $html ) ? trim( $html ) : '';

	return '' === trim( wp_strip_all_tags( $html ) ) ? '' : $html;
}

function leadwerk_theme_normalize_paragraph_markup( $html ) {
	$normalized = leadwerk_theme_normalize_heading_markup( $html );
	return '' === trim( wp_strip_all_tags( $normalized ) ) ? '' : $normalized;
}

function leadwerk_theme_force_strong_heading_markup( $html ) {
	$normalized = leadwerk_theme_normalize_heading_markup( $html );
	if ( '' === trim( wp_strip_all_tags( $normalized ) ) ) {
		return '';
	}

	if ( preg_match( '/^\s*<strong\b[^>]*>.*<\/strong>\s*$/is', $normalized ) ) {
		return $normalized;
	}

	return '<strong>' . $normalized . '</strong>';
}

function leadwerk_theme_set_placeholder_markup( $target, $html, $mode = 'container' ) {
	if ( ! $target instanceof DOMNode ) {
		return;
	}

	switch ( (string) $mode ) {
		case 'heading':
			$html = leadwerk_theme_normalize_heading_markup( $html );
			break;
		case 'paragraph':
			$html = leadwerk_theme_normalize_paragraph_markup( $html );
			break;
		case 'container':
		default:
			$html = (string) $html;
			break;
	}

	leadwerk_theme_dom_set_inner_html( $target, $html );
}

function leadwerk_theme_dom_set_text( $node, $text ) {
	if ( ! $node instanceof DOMNode ) {
		return;
	}

	leadwerk_theme_dom_clear( $node );
	$node->appendChild( $node->ownerDocument->createTextNode( wp_strip_all_tags( (string) $text ) ) );
}

function leadwerk_theme_dom_set_attr( $node, $attr, $value ) {
	if ( ! $node instanceof DOMElement ) {
		return;
	}

	$value = (string) $value;
	if ( '' === trim( $value ) ) {
		$node->removeAttribute( $attr );
		return;
	}

	$node->setAttribute( $attr, $value );
}

function leadwerk_theme_dom_remove( $node ) {
	if ( $node instanceof DOMNode && $node->parentNode ) {
		$node->parentNode->removeChild( $node );
	}
}

function leadwerk_theme_dom_ensure_count( $nodes, $count ) {
	$nodes = array_values( array_filter( $nodes ) );
	$count = max( 0, (int) $count );

	if ( empty( $nodes ) ) {
		return array();
	}

	$template = end( $nodes );
	$parent   = $template instanceof DOMNode ? $template->parentNode : null;

	while ( count( $nodes ) > $count ) {
		$node = array_pop( $nodes );
		leadwerk_theme_dom_remove( $node );
	}

	if ( ! $parent || ! $template ) {
		return $nodes;
	}

	while ( count( $nodes ) < $count ) {
		$clone   = $template->cloneNode( true );
		$parent->appendChild( $clone );
		$nodes[] = $clone;
	}

	return $nodes;
}

function leadwerk_theme_dom_toggle_class( $node, $class, $enabled ) {
	if ( ! $node instanceof DOMElement ) {
		return;
	}

	$classes = preg_split( '/\s+/', trim( (string) $node->getAttribute( 'class' ) ) );
	$classes = array_filter( is_array( $classes ) ? $classes : array() );

	if ( $enabled && ! in_array( $class, $classes, true ) ) {
		$classes[] = $class;
	}

	if ( ! $enabled ) {
		$classes = array_values(
			array_filter(
				$classes,
				static function ( $item ) use ( $class ) {
					return $item !== $class;
				}
			)
		);
	}

	$node->setAttribute( 'class', trim( implode( ' ', $classes ) ) );
}

function leadwerk_theme_get_exact_image_url( $image_id, $fallback = '' ) {
	$image_id = absint( $image_id );
	if ( $image_id ) {
		$url = wp_get_attachment_image_url( $image_id, 'full' );
		if ( $url ) {
			return $url;
		}
	}

	return (string) $fallback;
}

function leadwerk_theme_bind_exact_image( $xpath, $context, $query, $image_id, $alt = '' ) {
	$image = leadwerk_theme_dom_first( $xpath, $query, $context );
	if ( ! $image instanceof DOMElement ) {
		return;
	}

	$fallback = (string) $image->getAttribute( 'src' );
	$url      = leadwerk_theme_get_exact_image_url( (int) $image_id, $fallback );

	leadwerk_theme_dom_set_attr( $image, 'src', $url );
	if ( '' !== trim( (string) $alt ) ) {
		leadwerk_theme_dom_set_attr( $image, 'alt', $alt );
	}
}

function leadwerk_theme_resolve_exact_href( $page_key, $url = '' ) {
	$page_key = trim( (string) $page_key );
	$url      = trim( (string) $url );

	if ( '' !== $page_key ) {
		return leadwerk_theme_get_page_url( $page_key, leadwerk_theme_get_current_lang(), '' !== $url ? $url : '#' );
	}

	return '' !== $url ? $url : '#';
}

function leadwerk_theme_bind_exact_button( $xpath, $context, $query, $label, $page_key = '', $url = '' ) {
	$button = leadwerk_theme_dom_first( $xpath, $query, $context );
	if ( ! $button instanceof DOMElement ) {
		return;
	}

	if ( '' === trim( (string) $label ) ) {
		leadwerk_theme_dom_remove( $button );
		return;
	}

	leadwerk_theme_dom_set_attr( $button, 'href', leadwerk_theme_resolve_exact_href( $page_key, $url ) );
	leadwerk_theme_dom_set_text( $button, $label );
}

function leadwerk_theme_replace_style_url( $style, $url ) {
	$style = (string) $style;
	$url   = (string) $url;

	if ( '' === trim( $url ) ) {
		return $style;
	}

	if ( preg_match( '/url\\([\'"]?([^\'")]+)[\'"]?\\)/i', $style ) ) {
		return preg_replace( '/url\\([\'"]?([^\'")]+)[\'"]?\\)/i', "url('{$url}')", $style, 1 );
	}

	return rtrim( $style, '; ' ) . '; background-image:url(\'' . esc_url_raw( $url ) . '\');';
}

function leadwerk_theme_get_exact_blurb_html( $item ) {
	$title   = trim( (string) ( $item['title'] ?? '' ) );
	$content = (string) ( $item['content'] ?? '' );

	if ( '' !== $title ) {
		$content = preg_replace( '/^\s*<h4[^>]*>.*?<\/h4>/is', '', $content, 1 );
		return '<h4>' . esc_html( $title ) . '</h4>' . $content;
	}

	return $content;
}

function leadwerk_theme_get_prefixed_button_data( $item, $prefix ) {
	return array(
		'label'    => (string) ( $item[ $prefix . '_label' ] ?? '' ),
		'page_key' => (string) ( $item[ $prefix . '_page_key' ] ?? '' ),
		'url'      => (string) ( $item[ $prefix . '_url' ] ?? '' ),
	);
}

function leadwerk_theme_bind_exact_hero( $xpath, $section, $value, $has_button = true ) {
	leadwerk_theme_set_placeholder_markup( leadwerk_theme_dom_first( $xpath, './/h1[1]', $section ), (string) ( $value['title'] ?? '' ), 'heading' );
	leadwerk_theme_dom_set_text( leadwerk_theme_dom_first( $xpath, './/*[contains(@class,"hero-subtitle")][1]', $section ), (string) ( $value['subtitle'] ?? '' ) );
	leadwerk_theme_bind_exact_image( $xpath, $section, './/*[contains(@class,"hero-img-main")]//img[1]', (int) ( $value['image'] ?? 0 ), (string) ( $value['image_alt'] ?? '' ) );

	if ( $has_button ) {
		leadwerk_theme_bind_exact_button( $xpath, $section, './/a[contains(@class,"btn")][1]', (string) ( $value['cta_label'] ?? '' ), (string) ( $value['cta_page_key'] ?? '' ), (string) ( $value['cta_url'] ?? '' ) );
	}
}

function leadwerk_theme_bind_exact_icon_class( $xpath, $context, $query, $class_name ) {
	$icon = leadwerk_theme_dom_first( $xpath, $query, $context );
	if ( $icon instanceof DOMElement && '' !== trim( (string) $class_name ) ) {
		leadwerk_theme_dom_set_attr( $icon, 'class', trim( (string) $class_name ) );
	}
}

function leadwerk_theme_bind_exact_tech_hero( $xpath, $section, $value ) {
	leadwerk_theme_set_placeholder_markup( leadwerk_theme_dom_first( $xpath, './/h1[1]', $section ), (string) ( $value['title'] ?? '' ), 'heading' );
	leadwerk_theme_dom_set_text( leadwerk_theme_dom_first( $xpath, './/*[contains(@class,"hero-slide-subtitle")][1]', $section ), (string) ( $value['subtitle'] ?? '' ) );
	leadwerk_theme_bind_exact_button( $xpath, $section, './/a[contains(@class,"btn")][1]', (string) ( $value['cta_label'] ?? '' ), (string) ( $value['cta_page_key'] ?? '' ), (string) ( $value['cta_url'] ?? '' ) );
	if ( $section instanceof DOMElement ) {
		$image_url = leadwerk_theme_get_exact_image_url( (int) ( $value['background'] ?? 0 ), '' );
		if ( '' !== $image_url ) {
			leadwerk_theme_dom_set_attr(
				$section,
				'style',
				"background-image:linear-gradient(to left,rgba(4,60,67,.42) 0%,rgba(4,60,67,.62) 50%,rgba(4,60,67,.82) 100%),url('" . esc_url_raw( $image_url ) . "');"
			);
		}
	}

	$items = isset( $value['services'] ) && is_array( $value['services'] ) ? array_values( $value['services'] ) : array();
	$nodes = leadwerk_theme_dom_ensure_count( leadwerk_theme_dom_query( $xpath, './/*[contains(@class,"hero-services")]//*[contains(@class,"service-item")]', $section ), count( $items ) );
	foreach ( $nodes as $index => $node ) {
		$item = $items[ $index ] ?? array();
		if ( ! $node instanceof DOMElement || ! is_array( $item ) ) {
			continue;
		}
		leadwerk_theme_dom_set_attr( $node, 'href', leadwerk_theme_resolve_exact_href( (string) ( $item['page_key'] ?? '' ), (string) ( $item['url'] ?? '' ) ) );
		leadwerk_theme_bind_exact_icon_class( $xpath, $node, './/i[1]', (string) ( $item['icon_class'] ?? '' ) );
		leadwerk_theme_set_placeholder_markup( leadwerk_theme_dom_first( $xpath, './/div[last()]', $node ), (string) ( $item['title'] ?? '' ), 'heading' );
	}
}

function leadwerk_theme_bind_exact_tech_pain_points( $xpath, $section, $value ) {
	leadwerk_theme_set_placeholder_markup( leadwerk_theme_dom_first( $xpath, './/h2[1]', $section ), (string) ( $value['title'] ?? '' ), 'heading' );
	leadwerk_theme_dom_set_text( leadwerk_theme_dom_first( $xpath, './/*[contains(@class,"section-heading")]//p[1]', $section ), (string) ( $value['intro'] ?? '' ) );
	$items = isset( $value['items'] ) && is_array( $value['items'] ) ? array_values( $value['items'] ) : array();
	$nodes = leadwerk_theme_dom_ensure_count( leadwerk_theme_dom_query( $xpath, './/*[contains(@class,"tech-pain-card")]', $section ), count( $items ) );
	foreach ( $nodes as $index => $node ) {
		$item = $items[ $index ] ?? array();
		if ( ! is_array( $item ) ) {
			continue;
		}
		leadwerk_theme_bind_exact_icon_class( $xpath, $node, './/i[1]', (string) ( $item['icon_class'] ?? '' ) );
		leadwerk_theme_dom_set_text( leadwerk_theme_dom_first( $xpath, './/*[contains(@class,"tech-pain-card__label")][1]', $node ), (string) ( $item['label'] ?? '' ) );
		leadwerk_theme_dom_set_text( leadwerk_theme_dom_first( $xpath, './/h3[1]', $node ), (string) ( $item['title'] ?? '' ) );
		leadwerk_theme_set_placeholder_markup( leadwerk_theme_dom_first( $xpath, './/p[1]', $node ), (string) ( $item['content'] ?? '' ), 'paragraph' );
	}
}

function leadwerk_theme_bind_exact_tech_audience( $xpath, $section, $value ) {
	leadwerk_theme_set_placeholder_markup( leadwerk_theme_dom_first( $xpath, './/h2[1]', $section ), (string) ( $value['title'] ?? '' ), 'heading' );
	leadwerk_theme_dom_set_text( leadwerk_theme_dom_first( $xpath, './/*[contains(@class,"section-heading")]//p[1]', $section ), (string) ( $value['intro'] ?? '' ) );
	$items = isset( $value['personas'] ) && is_array( $value['personas'] ) ? array_values( $value['personas'] ) : array();
	$nodes = leadwerk_theme_dom_ensure_count( leadwerk_theme_dom_query( $xpath, './/*[contains(@class,"tech-persona-pill")]', $section ), count( $items ) );
	foreach ( $nodes as $index => $node ) {
		$item = $items[ $index ] ?? array();
		if ( ! $node instanceof DOMNode || ! is_array( $item ) ) {
			continue;
		}
		$icon = leadwerk_theme_dom_first( $xpath, './/i[1]', $node );
		leadwerk_theme_bind_exact_icon_class( $xpath, $node, './/i[1]', (string) ( $item['icon_class'] ?? '' ) );
		$icon_html = leadwerk_theme_dom_outer_html( $icon );
		leadwerk_theme_dom_set_inner_html( $node, $icon_html . esc_html( (string) ( $item['label'] ?? '' ) ) );
	}
}

function leadwerk_theme_bind_exact_tech_solution( $xpath, $section, $value ) {
	leadwerk_theme_set_placeholder_markup( leadwerk_theme_dom_first( $xpath, './/h2[1]', $section ), (string) ( $value['title'] ?? '' ), 'heading' );
	leadwerk_theme_set_placeholder_markup( leadwerk_theme_dom_first( $xpath, './/*[contains(@class,"col-text")]//p[1]', $section ), (string) ( $value['body'] ?? '' ), 'paragraph' );
	leadwerk_theme_bind_exact_image( $xpath, $section, './/*[contains(@class,"tech-coach-image")][1]', (int) ( $value['image'] ?? 0 ), (string) ( $value['image_alt'] ?? '' ) );
	leadwerk_theme_bind_exact_button( $xpath, $section, './/a[contains(@class,"btn-section")][1]', (string) ( $value['cta_label'] ?? '' ), (string) ( $value['cta_page_key'] ?? '' ), (string) ( $value['cta_url'] ?? '' ) );

	$items = isset( $value['items'] ) && is_array( $value['items'] ) ? array_values( $value['items'] ) : array();
	$nodes = leadwerk_theme_dom_ensure_count( leadwerk_theme_dom_query( $xpath, './/*[contains(@class,"tech-trust-list")]/li', $section ), count( $items ) );
	foreach ( $nodes as $index => $node ) {
		$item = $items[ $index ] ?? array();
		if ( ! is_array( $item ) ) {
			continue;
		}
		$icon_wrap = leadwerk_theme_dom_first( $xpath, './/*[contains(@class,"tech-trust-list__icon")][1]', $node );
		leadwerk_theme_bind_exact_icon_class( $xpath, $node, './/i[1]', (string) ( $item['icon_class'] ?? '' ) );
		$icon_html = leadwerk_theme_dom_outer_html( $icon_wrap );
		leadwerk_theme_dom_set_inner_html( $node, $icon_html . esc_html( (string) ( $item['text'] ?? '' ) ) );
	}
}

function leadwerk_theme_bind_exact_tech_value( $xpath, $section, $value ) {
	leadwerk_theme_set_placeholder_markup( leadwerk_theme_dom_first( $xpath, './/h2[1]', $section ), (string) ( $value['title'] ?? '' ), 'heading' );
	leadwerk_theme_dom_set_text( leadwerk_theme_dom_first( $xpath, './/*[contains(@class,"section-heading")]//p[1]', $section ), (string) ( $value['intro'] ?? '' ) );

	$points = isset( $value['points'] ) && is_array( $value['points'] ) ? array_values( $value['points'] ) : array();
	$point_nodes = leadwerk_theme_dom_ensure_count( leadwerk_theme_dom_query( $xpath, './/*[contains(@class,"tech-value-list")]/li', $section ), count( $points ) );
	foreach ( $point_nodes as $index => $node ) {
		$item = $points[ $index ] ?? array();
		leadwerk_theme_bind_exact_icon_class( $xpath, $node, './/i[1]', (string) ( $item['icon_class'] ?? '' ) );
		$content_node = leadwerk_theme_dom_first( $xpath, './span[last()]', $node );
		leadwerk_theme_dom_set_inner_html( $content_node, (string) ( $item['content'] ?? '' ) );
	}

	$columns = isset( $value['diagram_columns'] ) && is_array( $value['diagram_columns'] ) ? array_values( $value['diagram_columns'] ) : array();
	$column_nodes = leadwerk_theme_dom_ensure_count( leadwerk_theme_dom_query( $xpath, './/*[contains(@class,"tech-system-diagram")][1]/*[contains(@class,"tech-system-col")]', $section ), count( $columns ) );
	foreach ( $column_nodes as $column_index => $column_node ) {
		$column = $columns[ $column_index ] ?? array();
		if ( ! is_array( $column ) ) {
			continue;
		}
		leadwerk_theme_dom_set_text( leadwerk_theme_dom_first( $xpath, './/*[contains(@class,"tech-system-col__title")][1]', $column_node ), (string) ( $column['title'] ?? '' ) );
		$items = isset( $column['items'] ) && is_array( $column['items'] ) ? array_values( $column['items'] ) : array();
		$item_nodes = leadwerk_theme_dom_ensure_count( leadwerk_theme_dom_query( $xpath, './/li', $column_node ), count( $items ) );
		foreach ( $item_nodes as $item_index => $item_node ) {
			$item = $items[ $item_index ] ?? array();
			$icon = leadwerk_theme_dom_first( $xpath, './/i[1]', $item_node );
			leadwerk_theme_bind_exact_icon_class( $xpath, $item_node, './/i[1]', (string) ( $item['icon_class'] ?? '' ) );
			$icon_html = leadwerk_theme_dom_outer_html( $icon );
			leadwerk_theme_dom_set_inner_html( $item_node, $icon_html . esc_html( (string) ( $item['text'] ?? '' ) ) );
		}
	}

	$hint = leadwerk_theme_dom_first( $xpath, './/*[contains(@class,"tech-diagram-open__hint")][1]', $section );
	$hint_icon = leadwerk_theme_dom_outer_html( leadwerk_theme_dom_first( $xpath, './/i[1]', $hint ) );
	leadwerk_theme_dom_set_inner_html( $hint, $hint_icon . ' ' . esc_html( (string) ( $value['open_hint'] ?? '' ) ) );
	leadwerk_theme_dom_set_text( leadwerk_theme_dom_first( $xpath, './/*[contains(@class,"tech-diagram-dialog__title")][1]', $section ), (string) ( $value['dialog_title'] ?? '' ) );
}

function leadwerk_theme_bind_exact_tech_pillars( $xpath, $section, $value ) {
	leadwerk_theme_set_placeholder_markup( leadwerk_theme_dom_first( $xpath, './/h2[1]', $section ), (string) ( $value['title'] ?? '' ), 'heading' );
	$items = isset( $value['items'] ) && is_array( $value['items'] ) ? array_values( $value['items'] ) : array();
	$nodes = leadwerk_theme_dom_ensure_count( leadwerk_theme_dom_query( $xpath, './/*[contains(@class,"pillar-card")]', $section ), count( $items ) );
	foreach ( $nodes as $index => $node ) {
		$item = $items[ $index ] ?? array();
		if ( ! is_array( $item ) ) {
			continue;
		}
		leadwerk_theme_bind_exact_icon_class( $xpath, $node, './/i[1]', (string) ( $item['icon_class'] ?? '' ) );
		leadwerk_theme_dom_set_text( leadwerk_theme_dom_first( $xpath, './/h3[1]', $node ), (string) ( $item['title'] ?? '' ) );
		leadwerk_theme_set_placeholder_markup( leadwerk_theme_dom_first( $xpath, './/*[contains(@class,"card-desc")][1]', $node ), (string) ( $item['description'] ?? '' ), 'paragraph' );
		leadwerk_theme_bind_exact_button( $xpath, $node, './/a[contains(@class,"btn")][1]', (string) ( $item['button_label'] ?? '' ), (string) ( $item['button_page_key'] ?? '' ), (string) ( $item['button_url'] ?? '' ) );
	}
}

function leadwerk_theme_bind_exact_tech_final_cta( $xpath, $section, $value ) {
	leadwerk_theme_set_placeholder_markup( leadwerk_theme_dom_first( $xpath, './/h2[1]', $section ), (string) ( $value['title'] ?? '' ), 'heading' );
	leadwerk_theme_set_placeholder_markup( leadwerk_theme_dom_first( $xpath, './/p[1]', $section ), (string) ( $value['body'] ?? '' ), 'paragraph' );
	leadwerk_theme_bind_exact_button( $xpath, $section, './/*[contains(@class,"tech-final-cta__actions")]//a[contains(@class,"btn")][1]', (string) ( $value['primary_cta_label'] ?? '' ), (string) ( $value['primary_cta_page_key'] ?? '' ), (string) ( $value['primary_cta_url'] ?? '' ) );
	leadwerk_theme_bind_exact_button( $xpath, $section, './/*[contains(@class,"tech-final-cta__actions")]//a[contains(@class,"btn")][2]', (string) ( $value['secondary_cta_label'] ?? '' ), (string) ( $value['secondary_cta_page_key'] ?? '' ), (string) ( $value['secondary_cta_url'] ?? '' ) );
	$sticky_button = leadwerk_theme_dom_first( $xpath, './/*[contains(@class,"sticky-mobile-cta__btn")][1]', $section );
	leadwerk_theme_dom_set_attr( $sticky_button, 'href', leadwerk_theme_resolve_exact_href( (string) ( $value['sticky_cta_page_key'] ?? '' ), (string) ( $value['sticky_cta_url'] ?? '' ) ) );
	leadwerk_theme_dom_set_text( leadwerk_theme_dom_first( $xpath, './/*[contains(@class,"sticky-mobile-cta__label")][1]', $section ), (string) ( $value['sticky_cta_label'] ?? '' ) );
	leadwerk_theme_dom_set_text( leadwerk_theme_dom_first( $xpath, './/*[contains(@class,"sticky-mobile-cta__meta")][1]', $section ), (string) ( $value['sticky_meta'] ?? '' ) );
}

function leadwerk_theme_bind_exact_tech_process( $xpath, $section, $value ) {
	leadwerk_theme_set_placeholder_markup( leadwerk_theme_dom_first( $xpath, './/h2[1]', $section ), (string) ( $value['title'] ?? '' ), 'heading' );
	$steps = isset( $value['steps'] ) && is_array( $value['steps'] ) ? array_values( $value['steps'] ) : array();
	$nodes = leadwerk_theme_dom_ensure_count( leadwerk_theme_dom_query( $xpath, './/*[contains(@class,"how-step")]', $section ), count( $steps ) );
	foreach ( $nodes as $index => $node ) {
		$item = $steps[ $index ] ?? array();
		if ( ! is_array( $item ) ) {
			continue;
		}
		leadwerk_theme_bind_exact_icon_class( $xpath, $node, './/*[contains(@class,"step-icon")]//i[1]', (string) ( $item['icon_class'] ?? '' ) );
		leadwerk_theme_dom_set_text( leadwerk_theme_dom_first( $xpath, './/h4[1]', $node ), (string) ( $item['title'] ?? '' ) );
		leadwerk_theme_set_placeholder_markup( leadwerk_theme_dom_first( $xpath, './/p[1]', $node ), (string) ( $item['content'] ?? '' ), 'paragraph' );
	}
	leadwerk_theme_bind_exact_button( $xpath, $section, './/a[contains(@class,"btn")][last()]', (string) ( $value['cta_label'] ?? '' ), (string) ( $value['cta_page_key'] ?? '' ), (string) ( $value['cta_url'] ?? '' ) );
}

function leadwerk_theme_bind_exact_tech_testimonials( $xpath, $section, $value ) {
	leadwerk_theme_bind_exact_testimonials( $xpath, $section, $value );
}

function leadwerk_theme_bind_exact_hero_slider( $xpath, $section, $value ) {
	$slides = isset( $value['slides'] ) && is_array( $value['slides'] ) ? array_values( $value['slides'] ) : array();
	$nodes  = leadwerk_theme_dom_ensure_count( leadwerk_theme_dom_query( $xpath, './/*[contains(@class,"hero-slide")]', $section ), count( $slides ) );

	foreach ( $nodes as $index => $node ) {
		$item = isset( $slides[ $index ] ) ? $slides[ $index ] : array();
		if ( ! $node instanceof DOMElement || ! is_array( $item ) ) {
			continue;
		}

		leadwerk_theme_dom_toggle_class( $node, 'is-active', 0 === $index );
		leadwerk_theme_dom_set_attr( $node, 'data-slide', (string) $index );
		leadwerk_theme_set_placeholder_markup( leadwerk_theme_dom_first( $xpath, './/h1[1]', $node ), (string) ( $item['title'] ?? '' ), 'heading' );
		leadwerk_theme_dom_set_text( leadwerk_theme_dom_first( $xpath, './/*[contains(@class,"hero-slide-subtitle")][1]', $node ), (string) ( $item['subtitle'] ?? '' ) );
		leadwerk_theme_bind_exact_button( $xpath, $node, './/a[contains(@class,"btn")][1]', (string) ( $item['cta_label'] ?? '' ), (string) ( $item['cta_page_key'] ?? '' ), (string) ( $item['cta_url'] ?? '' ) );

		$image_url = leadwerk_theme_get_exact_image_url( (int) ( $item['background'] ?? 0 ), '' );
		if ( '' !== $image_url ) {
			leadwerk_theme_dom_set_attr( $node, 'style', leadwerk_theme_replace_style_url( $node->getAttribute( 'style' ), $image_url ) );
		}
	}

	$services = isset( $value['services'] ) && is_array( $value['services'] ) ? array_values( $value['services'] ) : array();
	$nodes    = leadwerk_theme_dom_ensure_count( leadwerk_theme_dom_query( $xpath, './/*[contains(@class,"hero-services")]//*[contains(@class,"service-item")]', $section ), count( $services ) );
	foreach ( $nodes as $index => $node ) {
		$item = isset( $services[ $index ] ) ? $services[ $index ] : array();
		if ( ! $node instanceof DOMElement || ! is_array( $item ) ) {
			continue;
		}

		leadwerk_theme_dom_set_attr( $node, 'href', leadwerk_theme_resolve_exact_href( (string) ( $item['page_key'] ?? '' ), (string) ( $item['url'] ?? '' ) ) );
		leadwerk_theme_bind_exact_image( $xpath, $node, './/img[1]', (int) ( $item['icon'] ?? 0 ), (string) ( $item['icon_alt'] ?? '' ) );
		leadwerk_theme_set_placeholder_markup( leadwerk_theme_dom_first( $xpath, './/div[last()]', $node ), (string) ( $item['title'] ?? '' ), 'heading' );
	}

	leadwerk_theme_dom_set_attr( leadwerk_theme_dom_first( $xpath, './/*[contains(@class,"hero-slider-prev")][1]', $section ), 'aria-label', (string) ( $value['prev_label'] ?? '' ) );
	leadwerk_theme_dom_set_attr( leadwerk_theme_dom_first( $xpath, './/*[contains(@class,"hero-slider-next")][1]', $section ), 'aria-label', (string) ( $value['next_label'] ?? '' ) );
}

function leadwerk_theme_bind_exact_pillars( $xpath, $section, $value ) {
	leadwerk_theme_set_placeholder_markup( leadwerk_theme_dom_first( $xpath, './/h2[1]', $section ), (string) ( $value['title'] ?? '' ), 'heading' );
	$items = isset( $value['items'] ) && is_array( $value['items'] ) ? array_values( $value['items'] ) : array();
	$nodes = leadwerk_theme_dom_ensure_count( leadwerk_theme_dom_query( $xpath, './/*[contains(@class,"pillar-card")]', $section ), count( $items ) );

	foreach ( $nodes as $index => $node ) {
		$item = isset( $items[ $index ] ) ? $items[ $index ] : array();
		if ( ! is_array( $item ) || ! $node instanceof DOMNode ) {
			continue;
		}

		leadwerk_theme_bind_exact_image( $xpath, $node, './/img[1]', (int) ( $item['icon'] ?? 0 ), (string) ( $item['icon_alt'] ?? '' ) );
		leadwerk_theme_dom_set_text( leadwerk_theme_dom_first( $xpath, './/h3[1]', $node ), (string) ( $item['title'] ?? '' ) );
		leadwerk_theme_set_placeholder_markup( leadwerk_theme_dom_first( $xpath, './/*[contains(@class,"card-desc")][1]', $node ), (string) ( $item['description'] ?? '' ), 'paragraph' );
		leadwerk_theme_bind_exact_button( $xpath, $node, './/a[contains(@class,"btn")][1]', (string) ( $item['button_label'] ?? '' ), (string) ( $item['button_page_key'] ?? '' ), (string) ( $item['button_url'] ?? '' ) );
	}
}

function leadwerk_theme_bind_exact_audience_switcher( $xpath, $section, $value ) {
	$items = isset( $value['items'] ) && is_array( $value['items'] ) ? array_values( $value['items'] ) : array();

	leadwerk_theme_set_placeholder_markup( leadwerk_theme_dom_first( $xpath, './/*[contains(@class,"fs-intro")][1]', $section ), (string) ( $value['title'] ?? '' ), 'heading' );
	leadwerk_theme_dom_set_attr( leadwerk_theme_dom_first( $xpath, './/*[contains(@class,"fs-prev")][1]', $section ), 'aria-label', (string) ( $value['prev_label'] ?? '' ) );
	leadwerk_theme_dom_set_attr( leadwerk_theme_dom_first( $xpath, './/*[contains(@class,"fs-next")][1]', $section ), 'aria-label', (string) ( $value['next_label'] ?? '' ) );
	leadwerk_theme_dom_set_text( leadwerk_theme_dom_first( $xpath, '//*[@id="fs-total"]', $section ), (string) count( $items ) );
	leadwerk_theme_dom_set_text( leadwerk_theme_dom_first( $xpath, '//*[@id="fs-current"]', $section ), ! empty( $items ) ? '1' : '0' );

	$buttons = leadwerk_theme_dom_ensure_count( leadwerk_theme_dom_query( $xpath, './/*[contains(@class,"fs-list")]//*[contains(@class,"fs-item")]', $section ), count( $items ) );
	foreach ( $buttons as $index => $button ) {
		$item = isset( $items[ $index ] ) ? $items[ $index ] : array();
		if ( ! $button instanceof DOMElement || ! is_array( $item ) ) {
			continue;
		}

		$body_text = trim( wp_strip_all_tags( (string) ( $item['body'] ?? '' ) ) );
		$image_url = leadwerk_theme_get_exact_image_url( (int) ( $item['image'] ?? 0 ), $button->getAttribute( 'data-img' ) );

		leadwerk_theme_dom_toggle_class( $button, 'is-active', 0 === $index );
		leadwerk_theme_dom_set_attr( $button, 'data-title', (string) ( $item['card_title'] ?? '' ) );
		leadwerk_theme_dom_set_attr( $button, 'data-body', $body_text );
		leadwerk_theme_dom_set_attr( $button, 'data-img', $image_url );
		leadwerk_theme_dom_set_text( $button, (string) ( $item['label'] ?? '' ) );
	}

	$first = isset( $items[0] ) && is_array( $items[0] ) ? $items[0] : array();
	leadwerk_theme_bind_exact_image( $xpath, $section, '//*[@id="fs-img"]', (int) ( $first['image'] ?? 0 ), (string) ( $first['image_alt'] ?? '' ) );
	leadwerk_theme_dom_set_text( leadwerk_theme_dom_first( $xpath, '//*[@id="fs-title"]', $section ), (string) ( $first['card_title'] ?? '' ) );
	leadwerk_theme_dom_set_text( leadwerk_theme_dom_first( $xpath, '//*[@id="fs-body"]', $section ), trim( wp_strip_all_tags( (string) ( $first['body'] ?? '' ) ) ) );
}

function leadwerk_theme_bind_exact_why_finora( $xpath, $section, $value ) {
	leadwerk_theme_set_placeholder_markup( leadwerk_theme_dom_first( $xpath, './/h2[1]', $section ), (string) ( $value['title'] ?? '' ), 'heading' );
	leadwerk_theme_dom_set_text( leadwerk_theme_dom_first( $xpath, './/*[contains(@class,"subtitle")][1]', $section ), (string) ( $value['subtitle'] ?? '' ) );
	leadwerk_theme_dom_set_inner_html( leadwerk_theme_dom_first( $xpath, './/*[contains(@class,"about-text")][1]', $section ), (string) ( $value['body'] ?? '' ) );

	$items = isset( $value['blurbs'] ) && is_array( $value['blurbs'] ) ? array_values( $value['blurbs'] ) : array();
	$nodes = leadwerk_theme_dom_ensure_count( leadwerk_theme_dom_query( $xpath, './/*[contains(@class,"blurb")]', $section ), count( $items ) );
	foreach ( $nodes as $index => $node ) {
		$item = isset( $items[ $index ] ) ? $items[ $index ] : array();
		leadwerk_theme_dom_set_inner_html( leadwerk_theme_dom_first( $xpath, './/*[contains(@class,"blurb-content")][1]', $node ), leadwerk_theme_get_exact_blurb_html( is_array( $item ) ? $item : array() ) );
	}

	leadwerk_theme_bind_exact_image( $xpath, $section, './/*[contains(@class,"why-finora-right")]//img[1]', (int) ( $value['image'] ?? 0 ), (string) ( $value['image_alt'] ?? '' ) );
	leadwerk_theme_bind_exact_button( $xpath, $section, './/a[contains(@class,"btn-section")][1]', (string) ( $value['cta_label'] ?? '' ), (string) ( $value['cta_page_key'] ?? '' ), (string) ( $value['cta_url'] ?? '' ) );
}

function leadwerk_theme_bind_exact_how_it_works( $xpath, $section, $value ) {
	leadwerk_theme_set_placeholder_markup( leadwerk_theme_dom_first( $xpath, './/h2[1]', $section ), (string) ( $value['title'] ?? '' ), 'heading' );
	$steps = isset( $value['steps'] ) && is_array( $value['steps'] ) ? array_values( $value['steps'] ) : array();
	$nodes = leadwerk_theme_dom_ensure_count( leadwerk_theme_dom_query( $xpath, './/*[contains(@class,"how-step")]', $section ), count( $steps ) );

	foreach ( $nodes as $index => $node ) {
		$item = isset( $steps[ $index ] ) ? $steps[ $index ] : array();
		if ( ! is_array( $item ) ) {
			continue;
		}

		leadwerk_theme_dom_set_text( leadwerk_theme_dom_first( $xpath, './/*[contains(@class,"step-icon")][1]', $node ), (string) ( $item['icon_text'] ?? '' ) );
		leadwerk_theme_dom_set_text( leadwerk_theme_dom_first( $xpath, './/h4[1]', $node ), (string) ( $item['title'] ?? '' ) );

		$paragraph = leadwerk_theme_dom_first( $xpath, './/p[1]', $node );
		if ( $paragraph ) {
			leadwerk_theme_set_placeholder_markup( $paragraph, (string) ( $item['content'] ?? '' ), 'paragraph' );
		} elseif ( '' !== trim( (string) ( $item['content'] ?? '' ) ) && $node instanceof DOMNode ) {
			$new_paragraph = $node->ownerDocument->createElement( 'p' );
			$node->appendChild( $new_paragraph );
			leadwerk_theme_set_placeholder_markup( $new_paragraph, (string) ( $item['content'] ?? '' ), 'paragraph' );
		}
	}

	leadwerk_theme_bind_exact_button( $xpath, $section, './/a[contains(@class,"btn")][last()]', (string) ( $value['cta_label'] ?? '' ), (string) ( $value['cta_page_key'] ?? '' ), (string) ( $value['cta_url'] ?? '' ) );
}

function leadwerk_theme_bind_exact_testimonials( $xpath, $section, $value ) {
	leadwerk_theme_set_placeholder_markup( leadwerk_theme_dom_first( $xpath, './/h2[1]', $section ), (string) ( $value['title'] ?? '' ), 'heading' );
	leadwerk_theme_dom_set_text( leadwerk_theme_dom_first( $xpath, './/*[contains(@class,"testimonials-subtitle")][1]', $section ), (string) ( $value['subtitle'] ?? '' ) );

	$items = isset( $value['items'] ) && is_array( $value['items'] ) ? array_values( $value['items'] ) : array();
	$nodes = leadwerk_theme_dom_ensure_count( leadwerk_theme_dom_query( $xpath, './/*[contains(@class,"testimonial-card")]', $section ), count( $items ) );
	foreach ( $nodes as $index => $node ) {
		$item = isset( $items[ $index ] ) ? $items[ $index ] : array();
		if ( ! is_array( $item ) ) {
			continue;
		}

		$toggle_enabled = ! array_key_exists( 'toggle_enabled', $item ) || ! empty( $item['toggle_enabled'] );
		leadwerk_theme_set_placeholder_markup( leadwerk_theme_dom_first( $xpath, './/*[contains(@class,"testimonial-text")][1]', $node ), (string) ( $item['quote'] ?? '' ), 'paragraph' );
		leadwerk_theme_dom_set_attr( $node, 'data-force-expanded', $toggle_enabled ? '' : 'true' );
		leadwerk_theme_dom_set_text( leadwerk_theme_dom_first( $xpath, './/*[contains(@class,"testimonial-initials")][1]', $node ), (string) ( $item['initials'] ?? '' ) );
		leadwerk_theme_dom_set_text( leadwerk_theme_dom_first( $xpath, './/*[contains(@class,"testimonial-name")][1]', $node ), (string) ( $item['name'] ?? '' ) );
		leadwerk_theme_dom_set_text( leadwerk_theme_dom_first( $xpath, './/*[contains(@class,"testimonial-role")][1]', $node ), (string) ( $item['role'] ?? '' ) );
	}
}

function leadwerk_theme_bind_exact_faq( $xpath, $section, $value ) {
	leadwerk_theme_set_placeholder_markup( leadwerk_theme_dom_first( $xpath, './/h2[1]', $section ), (string) ( $value['title'] ?? '' ), 'heading' );
	leadwerk_theme_dom_set_text( leadwerk_theme_dom_first( $xpath, './/*[contains(@class,"faq-intro")][1]', $section ), (string) ( $value['intro'] ?? '' ) );

	$left = leadwerk_theme_dom_first( $xpath, './/*[contains(@class,"faq-left")][1]', $section );
	if ( $left instanceof DOMElement ) {
		$image_url = leadwerk_theme_get_exact_image_url( (int) ( $value['background_image'] ?? 0 ), '' );
		if ( '' !== $image_url ) {
			$left->setAttribute( 'style', leadwerk_theme_replace_style_url( (string) $left->getAttribute( 'style' ), $image_url ) );
		}
	}

	$items = isset( $value['items'] ) && is_array( $value['items'] ) ? array_values( $value['items'] ) : array();
	$nodes = leadwerk_theme_dom_ensure_count( leadwerk_theme_dom_query( $xpath, './/*[contains(@class,"accordion-item")]', $section ), count( $items ) );
	foreach ( $nodes as $index => $node ) {
		$item = isset( $items[ $index ] ) ? $items[ $index ] : array();
		if ( ! is_array( $item ) ) {
			continue;
		}

		leadwerk_theme_dom_toggle_class( $node, 'is-open', 0 === $index );
		leadwerk_theme_dom_set_text( leadwerk_theme_dom_first( $xpath, './/*[contains(@class,"accordion-title")][1]', $node ), (string) ( $item['question'] ?? '' ) );
		leadwerk_theme_dom_set_inner_html( leadwerk_theme_dom_first( $xpath, './/*[contains(@class,"accordion-content")][1]', $node ), (string) ( $item['answer'] ?? '' ) );
	}
}

function leadwerk_theme_bind_exact_banner_cta( $xpath, $section, $value ) {
	leadwerk_theme_set_placeholder_markup( leadwerk_theme_dom_first( $xpath, './/h2[1]', $section ), (string) ( $value['title'] ?? '' ), 'heading' );
	$body_node = leadwerk_theme_dom_first( $xpath, './/div[contains(@class,"anim")][2]', $section );
	if ( ! $body_node ) {
		$body_node = leadwerk_theme_dom_first( $xpath, './/p[1]', $section );
	}
	leadwerk_theme_dom_set_inner_html( $body_node, (string) ( $value['body'] ?? '' ) );
	leadwerk_theme_bind_exact_button( $xpath, $section, './/a[contains(@class,"btn")][1]', (string) ( $value['cta_label'] ?? '' ), (string) ( $value['cta_page_key'] ?? '' ), (string) ( $value['cta_url'] ?? '' ) );

	if ( $section instanceof DOMElement ) {
		$image_url = leadwerk_theme_get_exact_image_url( (int) ( $value['background_image'] ?? 0 ), '' );
		if ( '' !== $image_url ) {
			$section->setAttribute( 'style', leadwerk_theme_replace_style_url( (string) $section->getAttribute( 'style' ), $image_url ) );
		}
	}
}

function leadwerk_theme_bind_exact_about_bedeutet( $xpath, $section, $value ) {
	$columns = leadwerk_theme_dom_query( $xpath, './/*[contains(@class,"two-col")][1]/*[contains(@class,"col-text")]', $section );
	$left    = $columns[0] ?? null;
	$right   = $columns[1] ?? null;

	leadwerk_theme_set_placeholder_markup( leadwerk_theme_dom_first( $xpath, './/*[contains(@class,"section-heading")][1]//h2[1]', $section ), (string) ( $value['title'] ?? '' ), 'heading' );
	leadwerk_theme_bind_exact_button( $xpath, $left, './/a[contains(@class,"btn")][1]', (string) ( $value['cta_label'] ?? '' ), (string) ( $value['cta_page_key'] ?? '' ), (string) ( $value['cta_url'] ?? '' ) );

	$left_button  = leadwerk_theme_dom_outer_html( leadwerk_theme_dom_first( $xpath, './/a[contains(@class,"btn")][1]', $left ) );
	leadwerk_theme_dom_set_inner_html( $left, (string) ( $value['left_body'] ?? '' ) . $left_button );

	leadwerk_theme_dom_set_text( leadwerk_theme_dom_first( $xpath, './/h4[1]', $right ), (string) ( $value['right_title'] ?? '' ) );
	$items = isset( $value['right_items'] ) && is_array( $value['right_items'] ) ? array_values( $value['right_items'] ) : array();
	$nodes = leadwerk_theme_dom_ensure_count( leadwerk_theme_dom_query( $xpath, './/*[contains(@class,"blurb")]', $right ), count( $items ) );
	foreach ( $nodes as $index => $node ) {
		$item = isset( $items[ $index ] ) ? $items[ $index ] : array();
		leadwerk_theme_dom_set_inner_html( leadwerk_theme_dom_first( $xpath, './/*[contains(@class,"blurb-content")][1]', $node ), (string) ( $item['content'] ?? '' ) );
	}
}

function leadwerk_theme_bind_exact_media_text( $xpath, $section, $value ) {
	$text_col = leadwerk_theme_dom_first( $xpath, './/*[contains(@class,"col-text")][1]', $section );
	leadwerk_theme_set_placeholder_markup( leadwerk_theme_dom_first( $xpath, './/h2[1]', $text_col ), (string) ( $value['title'] ?? '' ), 'heading' );
	leadwerk_theme_bind_exact_button( $xpath, $text_col, './/a[contains(@class,"btn")][1]', (string) ( $value['cta_label'] ?? '' ), (string) ( $value['cta_page_key'] ?? '' ), (string) ( $value['cta_url'] ?? '' ) );
	$heading_html = leadwerk_theme_dom_outer_html( leadwerk_theme_dom_first( $xpath, './/h2[1]', $text_col ) );
	$button_html  = leadwerk_theme_dom_outer_html( leadwerk_theme_dom_first( $xpath, './/a[contains(@class,"btn")][1]', $text_col ) );
	leadwerk_theme_dom_set_inner_html( $text_col, $heading_html . (string) ( $value['body'] ?? '' ) . $button_html );
	leadwerk_theme_bind_exact_image( $xpath, $section, './/*[contains(@class,"col-img")]//img[1]', (int) ( $value['image'] ?? 0 ), (string) ( $value['image_alt'] ?? '' ) );
}

function leadwerk_theme_bind_exact_workflow_blurbs( $xpath, $section, $value ) {
	$columns = leadwerk_theme_dom_query( $xpath, './/*[contains(@class,"two-col")][1]/*[contains(@class,"col-text")]', $section );
	$left    = $columns[0] ?? null;
	$right   = $columns[1] ?? null;

	leadwerk_theme_set_placeholder_markup( leadwerk_theme_dom_first( $xpath, './/h2[1]', $left ), (string) ( $value['title'] ?? '' ), 'heading' );
	leadwerk_theme_bind_exact_button( $xpath, $left, './/a[contains(@class,"btn")][1]', (string) ( $value['cta_label'] ?? '' ), (string) ( $value['cta_page_key'] ?? '' ), (string) ( $value['cta_url'] ?? '' ) );
	leadwerk_theme_set_placeholder_markup( leadwerk_theme_dom_first( $xpath, './/p[1]', $right ), (string) ( $value['intro'] ?? '' ), 'paragraph' );
	leadwerk_theme_dom_set_inner_html( leadwerk_theme_dom_first( $xpath, './/*[contains(@class,"workflow-highlight")][1]', $right ), (string) ( $value['highlight'] ?? '' ) );

	$items = isset( $value['items'] ) && is_array( $value['items'] ) ? array_values( $value['items'] ) : array();
	$nodes = leadwerk_theme_dom_ensure_count( leadwerk_theme_dom_query( $xpath, './/*[contains(@class,"blurb")]', $right ), count( $items ) );
	foreach ( $nodes as $index => $node ) {
		$item = isset( $items[ $index ] ) ? $items[ $index ] : array();
		leadwerk_theme_dom_set_inner_html( leadwerk_theme_dom_first( $xpath, './/*[contains(@class,"blurb-content")][1]', $node ), (string) ( $item['content'] ?? '' ) );
	}
}

function leadwerk_theme_bind_exact_center_cta( $xpath, $section, $value ) {
	leadwerk_theme_set_placeholder_markup( leadwerk_theme_dom_first( $xpath, './/h2[1]', $section ), leadwerk_theme_force_strong_heading_markup( (string) ( $value['title'] ?? '' ) ), 'heading' );
	leadwerk_theme_set_placeholder_markup( leadwerk_theme_dom_first( $xpath, './/p[1]', $section ), (string) ( $value['body'] ?? '' ), 'paragraph' );
	leadwerk_theme_bind_exact_button( $xpath, $section, './/a[contains(@class,"btn")][1]', (string) ( $value['cta_label'] ?? '' ), (string) ( $value['cta_page_key'] ?? '' ), (string) ( $value['cta_url'] ?? '' ) );
}

function leadwerk_theme_bind_exact_tabs_section( $xpath, $section, $value ) {
	leadwerk_theme_set_placeholder_markup( leadwerk_theme_dom_first( $xpath, './/h2[1]', $section ), (string) ( $value['title'] ?? '' ), 'heading' );
	leadwerk_theme_set_placeholder_markup( leadwerk_theme_dom_first( $xpath, './/*[contains(@class,"favorites-title")]//p[1]', $section ), (string) ( $value['intro'] ?? '' ), 'paragraph' );
	leadwerk_theme_bind_exact_button( $xpath, $section, './/*[contains(@class,"favorites-cta")]//a[1]', (string) ( $value['cta_label'] ?? '' ), (string) ( $value['cta_page_key'] ?? '' ), (string) ( $value['cta_url'] ?? '' ) );
	leadwerk_theme_bind_exact_image( $xpath, $section, './/*[contains(@class,"tabs-img")]//img[1]', (int) ( $value['image'] ?? 0 ), (string) ( $value['image_alt'] ?? '' ) );

	$tabs        = isset( $value['tabs'] ) && is_array( $value['tabs'] ) ? array_values( $value['tabs'] ) : array();
	$nav_nodes   = leadwerk_theme_dom_ensure_count( leadwerk_theme_dom_query( $xpath, './/*[contains(@class,"tab-nav")]//button', $section ), count( $tabs ) );
	$panel_nodes = leadwerk_theme_dom_ensure_count( leadwerk_theme_dom_query( $xpath, './/*[contains(@class,"tab-panel")]', $section ), count( $tabs ) );

	foreach ( $nav_nodes as $index => $nav_node ) {
		$item = isset( $tabs[ $index ] ) ? $tabs[ $index ] : array();
		if ( ! $nav_node instanceof DOMElement || ! is_array( $item ) ) {
			continue;
		}

		$panel_id = 'tab-' . ( $index + 1 );
		leadwerk_theme_dom_toggle_class( $nav_node, 'is-active', 0 === $index );
		leadwerk_theme_dom_set_attr( $nav_node, 'data-tab', $panel_id );
		leadwerk_theme_dom_set_text( $nav_node, (string) ( $item['title'] ?? '' ) );
	}

	foreach ( $panel_nodes as $index => $panel_node ) {
		$item = isset( $tabs[ $index ] ) ? $tabs[ $index ] : array();
		if ( ! $panel_node instanceof DOMElement || ! is_array( $item ) ) {
			continue;
		}

		$panel_id = 'tab-' . ( $index + 1 );
		leadwerk_theme_dom_toggle_class( $panel_node, 'is-active', 0 === $index );
		leadwerk_theme_dom_set_attr( $panel_node, 'id', $panel_id );
		leadwerk_theme_set_placeholder_markup( leadwerk_theme_dom_first( $xpath, './/h4[1]', $panel_node ), (string) ( $item['intro'] ?? '' ), 'heading' );

		$bullets = isset( $item['bullets'] ) && is_array( $item['bullets'] ) ? array_values( $item['bullets'] ) : array();
		$list    = leadwerk_theme_dom_first( $xpath, './/ul[1]', $panel_node );
		if ( $list ) {
			$bullet_nodes = leadwerk_theme_dom_ensure_count( leadwerk_theme_dom_query( $xpath, './li', $list ), count( $bullets ) );
			foreach ( $bullet_nodes as $bullet_index => $bullet_node ) {
				$bullet = isset( $bullets[ $bullet_index ] ) ? $bullets[ $bullet_index ] : array();
				leadwerk_theme_dom_set_text( $bullet_node, (string) ( $bullet['text'] ?? '' ) );
			}
		}

		leadwerk_theme_set_placeholder_markup( leadwerk_theme_dom_first( $xpath, './/p[last()]', $panel_node ), (string) ( $item['outro'] ?? '' ), 'paragraph' );
	}
}

function leadwerk_theme_bind_exact_retirement_audience( $xpath, $section, $value ) {
	leadwerk_theme_set_placeholder_markup( leadwerk_theme_dom_first( $xpath, './/h2[1]', $section ), (string) ( $value['title'] ?? '' ), 'heading' );

	$jump_links = isset( $value['jump_links'] ) && is_array( $value['jump_links'] ) ? array_values( $value['jump_links'] ) : array();
	$link_nodes = leadwerk_theme_dom_ensure_count( leadwerk_theme_dom_query( $xpath, './/*[contains(@class,"audience-links")]//a', $section ), count( $jump_links ) );
	foreach ( $link_nodes as $index => $link_node ) {
		$item = isset( $jump_links[ $index ] ) ? $jump_links[ $index ] : array();
		if ( ! is_array( $item ) ) {
			continue;
		}
		leadwerk_theme_dom_set_text( $link_node, (string) ( $item['label'] ?? '' ) );
		leadwerk_theme_dom_set_attr( $link_node, 'href', '#' . sanitize_title( (string) ( $item['anchor_id'] ?? '' ) ) );
	}

	$cards = isset( $value['cards'] ) && is_array( $value['cards'] ) ? array_values( $value['cards'] ) : array();
	$nodes = leadwerk_theme_dom_ensure_count( leadwerk_theme_dom_query( $xpath, './/*[contains(@class,"audience-card")]', $section ), count( $cards ) );
	foreach ( $nodes as $index => $node ) {
		$item = isset( $cards[ $index ] ) ? $cards[ $index ] : array();
		if ( ! $node instanceof DOMElement || ! is_array( $item ) ) {
			continue;
		}

		$base_class = preg_replace( '/\s+audience-card--(?:highlight|large|small)\b/', '', (string) $node->getAttribute( 'class' ) );
		$variant    = trim( (string) ( $item['variant'] ?? 'default' ) );
		if ( in_array( $variant, array( 'highlight', 'large', 'small' ), true ) ) {
			$base_class .= ' audience-card--' . $variant;
		}
		$node->setAttribute( 'class', trim( $base_class ) );
		leadwerk_theme_dom_set_attr( $node, 'id', sanitize_title( (string) ( $item['anchor_id'] ?? '' ) ) );
		leadwerk_theme_dom_set_text( leadwerk_theme_dom_first( $xpath, './/h3[1]', $node ), (string) ( $item['title'] ?? '' ) );
		leadwerk_theme_set_placeholder_markup( leadwerk_theme_dom_first( $xpath, './p[1]', $node ), (string) ( $item['intro'] ?? '' ), 'paragraph' );

		$blurbs      = isset( $item['blurbs'] ) && is_array( $item['blurbs'] ) ? array_values( $item['blurbs'] ) : array();
		$blurb_nodes = leadwerk_theme_dom_ensure_count( leadwerk_theme_dom_query( $xpath, './/*[contains(@class,"blurb")]', $node ), count( $blurbs ) );
		foreach ( $blurb_nodes as $blurb_index => $blurb_node ) {
			$blurb_item = isset( $blurbs[ $blurb_index ] ) ? $blurbs[ $blurb_index ] : array();
			leadwerk_theme_dom_set_inner_html( leadwerk_theme_dom_first( $xpath, './/*[contains(@class,"blurb-content")][1]', $blurb_node ), leadwerk_theme_get_exact_blurb_html( is_array( $blurb_item ) ? $blurb_item : array() ) );
		}

		$button = leadwerk_theme_get_prefixed_button_data( $item, 'button' );
		leadwerk_theme_bind_exact_button( $xpath, $node, './/a[contains(@class,"btn")][1]', $button['label'], $button['page_key'], $button['url'] );
	}
}

function leadwerk_theme_bind_exact_concepts_section( $xpath, $section, $value ) {
	leadwerk_theme_bind_exact_image( $xpath, $section, './/*[contains(@class,"concepts-image-wrap")]//img[1]', (int) ( $value['image'] ?? 0 ), (string) ( $value['image_alt'] ?? '' ) );
	leadwerk_theme_set_placeholder_markup( leadwerk_theme_dom_first( $xpath, './/h2[1]', $section ), (string) ( $value['title'] ?? '' ), 'heading' );
	leadwerk_theme_set_placeholder_markup( leadwerk_theme_dom_first( $xpath, './/*[contains(@class,"concepts-text-col")]//p[1]', $section ), (string) ( $value['intro'] ?? '' ), 'paragraph' );

	$items = isset( $value['items'] ) && is_array( $value['items'] ) ? array_values( $value['items'] ) : array();
	$nodes = leadwerk_theme_dom_ensure_count( leadwerk_theme_dom_query( $xpath, './/*[contains(@class,"blurb")]', $section ), count( $items ) );
	foreach ( $nodes as $index => $node ) {
		$item = isset( $items[ $index ] ) ? $items[ $index ] : array();
		leadwerk_theme_dom_set_inner_html( leadwerk_theme_dom_first( $xpath, './/*[contains(@class,"blurb-content")][1]', $node ), (string) ( $item['content'] ?? '' ) );
	}

	leadwerk_theme_bind_exact_button( $xpath, $section, './/a[contains(@class,"btn")][1]', (string) ( $value['cta_label'] ?? '' ), (string) ( $value['cta_page_key'] ?? '' ), (string) ( $value['cta_url'] ?? '' ) );
}

function leadwerk_theme_bind_exact_blurb_image_section( $xpath, $section, $value ) {
	leadwerk_theme_set_placeholder_markup( leadwerk_theme_dom_first( $xpath, './/h2[1]', $section ), (string) ( $value['title'] ?? '' ), 'heading' );
	$intro = leadwerk_theme_dom_first( $xpath, './/*[contains(@class,"section-heading")]//p[1]', $section );
	if ( ! $intro ) {
		$intro = leadwerk_theme_dom_first( $xpath, './/*[contains(@class,"col-text")]//p[1]', $section );
	}
	leadwerk_theme_set_placeholder_markup( $intro, (string) ( $value['intro'] ?? '' ), 'paragraph' );
	leadwerk_theme_bind_exact_image( $xpath, $section, './/img[1]', (int) ( $value['image'] ?? 0 ), (string) ( $value['image_alt'] ?? '' ) );

	$items = isset( $value['items'] ) && is_array( $value['items'] ) ? array_values( $value['items'] ) : array();
	$nodes = leadwerk_theme_dom_ensure_count( leadwerk_theme_dom_query( $xpath, './/*[contains(@class,"blurb")]', $section ), count( $items ) );
	foreach ( $nodes as $index => $node ) {
		$item = isset( $items[ $index ] ) ? $items[ $index ] : array();
		leadwerk_theme_dom_set_inner_html( leadwerk_theme_dom_first( $xpath, './/*[contains(@class,"blurb-content")][1]', $node ), (string) ( $item['content'] ?? '' ) );
	}

	leadwerk_theme_bind_exact_button( $xpath, $section, './/a[contains(@class,"btn")][1]', (string) ( $value['cta_label'] ?? '' ), (string) ( $value['cta_page_key'] ?? '' ), (string) ( $value['cta_url'] ?? '' ) );
}

function leadwerk_theme_bind_exact_approach_tiles( $xpath, $section, $value ) {
	leadwerk_theme_set_placeholder_markup( leadwerk_theme_dom_first( $xpath, './/h2[1]', $section ), (string) ( $value['title'] ?? '' ), 'heading' );
	leadwerk_theme_set_placeholder_markup( leadwerk_theme_dom_first( $xpath, './/*[contains(@class,"col-text")]//p[1]', $section ), (string) ( $value['body'] ?? '' ), 'paragraph' );
	leadwerk_theme_bind_exact_button( $xpath, $section, './/a[contains(@class,"btn")][1]', (string) ( $value['cta_label'] ?? '' ), (string) ( $value['cta_page_key'] ?? '' ), (string) ( $value['cta_url'] ?? '' ) );

	$template_tile_count = count(
		leadwerk_theme_dom_query(
			$xpath,
			'.//*[contains(concat(" ", normalize-space(@class), " "), " approach-tiles-grid ")]/*[contains(concat(" ", normalize-space(@class), " "), " approach-tile ")]',
			$section
		)
	);
	$tiles = leadwerk_theme_normalize_exact_approach_tiles(
		isset( $value['tiles'] ) && is_array( $value['tiles'] ) ? array_values( $value['tiles'] ) : array(),
		$template_tile_count
	);

	$col_tiles = leadwerk_theme_dom_first( $xpath, './/*[contains(concat(" ", normalize-space(@class), " "), " col-tiles ")]', $section );
	if ( ! $col_tiles instanceof DOMNode ) {
		return;
	}

	$grid_markup = '<div class="approach-tiles-grid">';
	foreach ( $tiles as $index => $item ) {
		if ( ! is_array( $item ) ) {
			continue;
		}

		$title   = wp_strip_all_tags( (string) ( $item['title'] ?? '' ) );
		$content = (string) ( $item['content'] ?? '' );
		$grid_markup .= '<div class="approach-tile approach-tile--' . ( $index + 1 ) . '">';
		$grid_markup .= '<div class="approach-tile__inner">';
		$grid_markup .= '<div class="approach-tile__front"><h4>' . esc_html( $title ) . '</h4></div>';
		$grid_markup .= '<div class="approach-tile__back"><p>' . wp_kses_post( $content ) . '</p></div>';
		$grid_markup .= '</div></div>';
	}
	$grid_markup .= '</div>';

	leadwerk_theme_dom_set_inner_html( $col_tiles, $grid_markup );
}

function leadwerk_theme_normalize_exact_approach_tiles( $tiles, $limit = 0 ) {
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

function leadwerk_theme_dom_set_icon_value( $node, $value ) {
	if ( ! $node instanceof DOMNode ) {
		return;
	}

	$value = trim( (string) $value );
	if ( '' === $value ) {
		return;
	}

	leadwerk_theme_dom_clear( $node );
	if ( false !== strpos( $value, 'fa-' ) ) {
		$icon = $node->ownerDocument->createElement( 'i' );
		$icon->setAttribute( 'class', $value );
		$node->appendChild( $icon );
		return;
	}

	$node->appendChild( $node->ownerDocument->createTextNode( wp_strip_all_tags( $value ) ) );
}

function leadwerk_theme_set_exact_modifier_class( $node, $pattern, $modifier ) {
	if ( ! $node instanceof DOMElement ) {
		return;
	}

	$class = preg_replace( $pattern, '', (string) $node->getAttribute( 'class' ) );
	$class = trim( preg_replace( '/\s+/', ' ', (string) $class ) );
	if ( '' !== trim( (string) $modifier ) ) {
		$class = trim( $class . ' ' . $modifier );
	}

	if ( '' !== $class ) {
		$node->setAttribute( 'class', $class );
	}
}

function leadwerk_theme_get_exact_counter_value( $text ) {
	$text = trim( wp_strip_all_tags( (string) $text ) );
	if ( '' === $text ) {
		return '';
	}

	if ( ! preg_match( '/[-+]?\d[\d\.\,\s]*/u', $text, $matches ) ) {
		return '';
	}

	$number = str_replace( array( ' ', "\xc2\xa0", "\xe2\x80\x89", "\xe2\x80\xaf" ), '', $matches[0] );
	if ( '' === $number ) {
		return '';
	}

	if ( false !== strpos( $number, ',' ) ) {
		$number = str_replace( '.', '', $number );
		$number = str_replace( ',', '.', $number );
	} else {
		$number = str_replace( '.', '', $number );
	}

	if ( is_numeric( $number ) ) {
		return (string) $number;
	}

	return '';
}

function leadwerk_theme_bind_exact_timeline( $xpath, $section, $value ) {
	leadwerk_theme_set_placeholder_markup( leadwerk_theme_dom_first( $xpath, './/h2[1]', $section ), (string) ( $value['title'] ?? '' ), 'heading' );
	leadwerk_theme_set_placeholder_markup( leadwerk_theme_dom_first( $xpath, './/*[contains(@class,"section-heading")]//p[1] | .//p[1]', $section ), (string) ( $value['intro'] ?? '' ), 'paragraph' );

	$items = isset( $value['items'] ) && is_array( $value['items'] ) ? array_values( $value['items'] ) : array();
	$nodes = leadwerk_theme_dom_ensure_count( leadwerk_theme_dom_query( $xpath, './/*[contains(@class,"timeline-item")]', $section ), count( $items ) );
	foreach ( $nodes as $index => $node ) {
		$item = isset( $items[ $index ] ) ? $items[ $index ] : array();
		if ( ! $node instanceof DOMElement || ! is_array( $item ) ) {
			continue;
		}

		leadwerk_theme_dom_set_text( leadwerk_theme_dom_first( $xpath, './/*[contains(@class,"timeline-item__number")][1]', $node ), (string) ( $item['number'] ?? '' ) );
		leadwerk_theme_dom_set_attr( leadwerk_theme_dom_first( $xpath, './/*[contains(@class,"timeline-item__icon")]//i[1]', $node ), 'class', (string) ( $item['icon'] ?? '' ) );
		leadwerk_theme_dom_set_text( leadwerk_theme_dom_first( $xpath, './/h4[1]', $node ), (string) ( $item['title'] ?? '' ) );

		$card        = leadwerk_theme_dom_first( $xpath, './/*[contains(@class,"timeline-item__card")][1]', $node );
		$icon_html   = leadwerk_theme_dom_outer_html( leadwerk_theme_dom_first( $xpath, './/*[contains(@class,"timeline-item__icon")][1]', $card ) );
		$title_html  = leadwerk_theme_dom_outer_html( leadwerk_theme_dom_first( $xpath, './/h4[1]', $card ) );
		$body_html   = (string) ( $item['body'] ?? '' );
		$bullets     = isset( $item['bullets'] ) && is_array( $item['bullets'] ) ? array_values( $item['bullets'] ) : array();
		$bullets_html = '';

		if ( ! empty( $bullets ) ) {
			$bullets_html = '<ul>';
			foreach ( $bullets as $bullet ) {
				$bullets_html .= '<li>' . esc_html( (string) ( $bullet['text'] ?? '' ) ) . '</li>';
			}
			$bullets_html .= '</ul>';
		}

		leadwerk_theme_dom_set_inner_html( $card, $icon_html . $title_html . $body_html . $bullets_html );
	}
}

function leadwerk_theme_bind_exact_target_groups( $xpath, $section, $value ) {
	leadwerk_theme_set_placeholder_markup( leadwerk_theme_dom_first( $xpath, './/h2[1]', $section ), (string) ( $value['title'] ?? '' ), 'heading' );
	leadwerk_theme_dom_set_text( leadwerk_theme_dom_first( $xpath, './/*[contains(@class,"target-groups__subtitle")][1]', $section ), (string) ( $value['subtitle'] ?? '' ) );

	$items = isset( $value['items'] ) && is_array( $value['items'] ) ? array_values( $value['items'] ) : array();
	$nodes = leadwerk_theme_dom_ensure_count( leadwerk_theme_dom_query( $xpath, './/*[contains(@class,"target-group-grid")]//*[contains(@class,"blurb")]', $section ), count( $items ) );
	foreach ( $nodes as $index => $node ) {
		$item = isset( $items[ $index ] ) ? $items[ $index ] : array();
		leadwerk_theme_dom_set_inner_html( leadwerk_theme_dom_first( $xpath, './/*[contains(@class,"blurb-content")][1]', $node ), (string) ( $item['content'] ?? '' ) );
	}

	leadwerk_theme_set_placeholder_markup( leadwerk_theme_dom_first( $xpath, './/*[contains(@class,"target-groups__summary")][1]', $section ), (string) ( $value['summary'] ?? '' ), 'paragraph' );
	leadwerk_theme_bind_exact_button( $xpath, $section, './/a[contains(@class,"btn")][1]', (string) ( $value['cta_label'] ?? '' ), (string) ( $value['cta_page_key'] ?? '' ), (string) ( $value['cta_url'] ?? '' ) );
}

function leadwerk_theme_bind_exact_results_section( $xpath, $section, $value ) {
	leadwerk_theme_set_placeholder_markup( leadwerk_theme_dom_first( $xpath, './/h2[1]', $section ), (string) ( $value['title'] ?? '' ), 'heading' );
	leadwerk_theme_set_placeholder_markup( leadwerk_theme_dom_first( $xpath, './/*[contains(@class,"col-text")]//p[1] | .//*[contains(@class,"section-heading")]//p[1]', $section ), (string) ( $value['intro'] ?? '' ), 'paragraph' );
	leadwerk_theme_bind_exact_image( $xpath, $section, './/*[contains(@class,"feature-image")] | .//img[1]', (int) ( $value['image'] ?? 0 ), (string) ( $value['image_alt'] ?? '' ) );

	$items = isset( $value['items'] ) && is_array( $value['items'] ) ? array_values( $value['items'] ) : array();
	$nodes = leadwerk_theme_dom_ensure_count( leadwerk_theme_dom_query( $xpath, './/*[contains(@class,"blurb")]', $section ), count( $items ) );
	foreach ( $nodes as $index => $node ) {
		$item = isset( $items[ $index ] ) ? $items[ $index ] : array();
		leadwerk_theme_dom_set_inner_html( leadwerk_theme_dom_first( $xpath, './/*[contains(@class,"blurb-content")][1]', $node ), (string) ( $item['content'] ?? '' ) );
	}

	leadwerk_theme_bind_exact_button( $xpath, $section, './/a[contains(@class,"btn")][1]', (string) ( $value['cta_label'] ?? '' ), (string) ( $value['cta_page_key'] ?? '' ), (string) ( $value['cta_url'] ?? '' ) );
}

function leadwerk_theme_bind_exact_real_estate_intro( $xpath, $section, $value ) {
	leadwerk_theme_bind_exact_image( $xpath, $section, './/*[contains(@class,"immobilien-intro-image")][1]', (int) ( $value['image'] ?? 0 ), (string) ( $value['image_alt'] ?? '' ) );

	$stats = isset( $value['stats'] ) && is_array( $value['stats'] ) ? array_values( $value['stats'] ) : array();
	$nodes = leadwerk_theme_dom_ensure_count( leadwerk_theme_dom_query( $xpath, './/*[contains(@class,"intro-stat")]', $section ), count( $stats ) );
	foreach ( $nodes as $index => $node ) {
		$item = isset( $stats[ $index ] ) ? $stats[ $index ] : array();
		leadwerk_theme_dom_set_text( leadwerk_theme_dom_first( $xpath, './/*[contains(@class,"intro-stat__value")][1]', $node ), (string) ( $item['value'] ?? '' ) );
		leadwerk_theme_dom_set_text( leadwerk_theme_dom_first( $xpath, './/*[contains(@class,"intro-stat__label")][1]', $node ), (string) ( $item['label'] ?? '' ) );
	}

	$headings = leadwerk_theme_dom_query( $xpath, './/*[contains(@class,"col-text--immobilien-intro")]//h2', $section );
	$paras    = leadwerk_theme_dom_query( $xpath, './/*[contains(@class,"col-text--immobilien-intro")]//p[not(contains(@class,"intro-stat__label")) and not(contains(@class,"intro-stat__value"))]', $section );

	if ( isset( $headings[0] ) ) {
		leadwerk_theme_set_placeholder_markup( $headings[0], (string) ( $value['goals_title'] ?? '' ), 'heading' );
	}
	if ( isset( $paras[0] ) ) {
		leadwerk_theme_set_placeholder_markup( $paras[0], (string) ( $value['goals_body'] ?? '' ), 'paragraph' );
	}
	if ( isset( $headings[1] ) ) {
		leadwerk_theme_set_placeholder_markup( $headings[1], (string) ( $value['challenge_title'] ?? '' ), 'heading' );
	}
	if ( isset( $paras[1] ) ) {
		leadwerk_theme_set_placeholder_markup( $paras[1], (string) ( $value['challenge_body'] ?? '' ), 'paragraph' );
	}

	$blurbs = isset( $value['blurbs'] ) && is_array( $value['blurbs'] ) ? array_values( $value['blurbs'] ) : array();
	$nodes  = leadwerk_theme_dom_ensure_count( leadwerk_theme_dom_query( $xpath, './/*[contains(@class,"blurb")]', $section ), count( $blurbs ) );
	foreach ( $nodes as $index => $node ) {
		$item = isset( $blurbs[ $index ] ) ? $blurbs[ $index ] : array();
		leadwerk_theme_dom_set_text( leadwerk_theme_dom_first( $xpath, './/h4[1]', $node ), (string) ( $item['title'] ?? '' ) );
		leadwerk_theme_dom_set_inner_html( leadwerk_theme_dom_first( $xpath, './/*[contains(@class,"blurb-content")][1]', $node ), leadwerk_theme_get_exact_blurb_html( $item ) );
	}
}

function leadwerk_theme_bind_exact_calculator( $xpath, $section, $value ) {
	leadwerk_theme_set_placeholder_markup( leadwerk_theme_dom_first( $xpath, './/h2[1]', $section ), (string) ( $value['title'] ?? '' ), 'heading' );
	leadwerk_theme_set_placeholder_markup( leadwerk_theme_dom_first( $xpath, './/*[contains(@class,"calc-v2__subtitle")][1]', $section ), (string) ( $value['subtitle'] ?? '' ), 'paragraph' );

	$cards = isset( $value['cards'] ) && is_array( $value['cards'] ) ? array_values( $value['cards'] ) : array();
	$nodes = leadwerk_theme_dom_ensure_count( leadwerk_theme_dom_query( $xpath, './/*[contains(@class,"calc-v2__card")]', $section ), count( $cards ) );
	foreach ( $nodes as $index => $node ) {
		$item = isset( $cards[ $index ] ) ? $cards[ $index ] : array();
		if ( ! $node instanceof DOMElement || ! is_array( $item ) ) {
			continue;
		}

		leadwerk_theme_dom_toggle_class( $node, 'calc-v2__card--featured', ! empty( $item['featured'] ) );
		leadwerk_theme_dom_set_attr( leadwerk_theme_dom_first( $xpath, './/*[contains(@class,"calc-v2__card-icon")]//i[1]', $node ), 'class', (string) ( $item['icon'] ?? '' ) );
		leadwerk_theme_dom_set_text( leadwerk_theme_dom_first( $xpath, './/*[contains(@class,"calc-v2__card-title")][1]', $node ), (string) ( $item['title'] ?? '' ) );

		$rows      = isset( $item['rows'] ) && is_array( $item['rows'] ) ? array_values( $item['rows'] ) : array();
		$row_nodes = leadwerk_theme_dom_ensure_count(
			leadwerk_theme_dom_query( $xpath, './/*[contains(@class,"calc-v2__rows")]/*[contains(@class,"calc-v2__row")]', $node ),
			count( $rows )
		);

		foreach ( $row_nodes as $row_index => $row_node ) {
			$row = isset( $rows[ $row_index ] ) ? $rows[ $row_index ] : array();
			if ( ! $row_node instanceof DOMElement || ! is_array( $row ) ) {
				continue;
			}

			leadwerk_theme_set_exact_modifier_class( $row_node, '/\s+calc-v2__row--[a-z-]+/', '' );
			$value_node = leadwerk_theme_dom_first( $xpath, './/*[contains(@class,"calc-v2__value")][1]', $row_node );
			leadwerk_theme_set_exact_modifier_class( $value_node, '/\s+calc-v2__value--[a-z-]+/', '' );

			$modifier = trim( (string) ( $row['modifier'] ?? '' ) );
			if ( in_array( $modifier, array( 'subtotal', 'highlight', 'hero' ), true ) ) {
				leadwerk_theme_set_exact_modifier_class( $row_node, '/\s+calc-v2__row--[a-z-]+/', 'calc-v2__row--' . $modifier );
			}
			if ( in_array( $modifier, array( 'plus', 'minus', 'accent', 'big', 'hero' ), true ) ) {
				leadwerk_theme_set_exact_modifier_class( $value_node, '/\s+calc-v2__value--[a-z-]+/', 'calc-v2__value--' . $modifier );
			}

			leadwerk_theme_dom_set_text( leadwerk_theme_dom_first( $xpath, './/*[contains(@class,"calc-v2__label")][1]', $row_node ), (string) ( $row['label'] ?? '' ) );
			leadwerk_theme_dom_set_text( $value_node, (string) ( $row['value'] ?? '' ) );
			leadwerk_theme_dom_set_attr( $value_node, 'data-count', leadwerk_theme_get_exact_counter_value( (string) ( $row['value'] ?? '' ) ) );
		}
	}

	$kpis  = isset( $value['kpis'] ) && is_array( $value['kpis'] ) ? array_values( $value['kpis'] ) : array();
	$nodes = leadwerk_theme_dom_ensure_count( leadwerk_theme_dom_query( $xpath, './/*[contains(@class,"calc-v2__kpi")]', $section ), count( $kpis ) );
	foreach ( $nodes as $index => $node ) {
		$item = isset( $kpis[ $index ] ) ? $kpis[ $index ] : array();
		if ( ! is_array( $item ) ) {
			continue;
		}

		$value_node = leadwerk_theme_dom_first( $xpath, './/*[contains(@class,"calc-v2__kpi-value")][1]', $node );
		leadwerk_theme_set_exact_modifier_class( $value_node, '/\s+calc-v2__kpi-value--accent/', ! empty( $item['accent'] ) ? 'calc-v2__kpi-value--accent' : '' );
		leadwerk_theme_dom_set_text( $value_node, (string) ( $item['value'] ?? '' ) );
		leadwerk_theme_dom_set_attr( $value_node, 'data-count', leadwerk_theme_get_exact_counter_value( (string) ( $item['value'] ?? '' ) ) );
		leadwerk_theme_dom_set_text( leadwerk_theme_dom_first( $xpath, './/*[contains(@class,"calc-v2__kpi-label")][1]', $node ), (string) ( $item['label'] ?? '' ) );
	}
}

function leadwerk_theme_bind_exact_case_highlight( $xpath, $section, $value ) {
	leadwerk_theme_bind_exact_image( $xpath, $section, './/*[contains(@class,"adrian-img")][1]', (int) ( $value['image'] ?? 0 ), (string) ( $value['image_alt'] ?? '' ) );
	leadwerk_theme_set_placeholder_markup( leadwerk_theme_dom_first( $xpath, './/*[contains(@class,"adrian-heading")][1]', $section ), (string) ( $value['title'] ?? '' ), 'heading' );
	leadwerk_theme_dom_set_inner_html( leadwerk_theme_dom_first( $xpath, './/*[contains(@class,"adrian-text")][1]', $section ), (string) ( $value['body'] ?? '' ) );
	leadwerk_theme_dom_set_inner_html( leadwerk_theme_dom_first( $xpath, './/*[contains(@class,"adrian-quote")][1]', $section ), (string) ( $value['quote'] ?? '' ) );
}

function leadwerk_theme_bind_exact_dark_cta( $xpath, $section, $value ) {
	leadwerk_theme_set_placeholder_markup( leadwerk_theme_dom_first( $xpath, './/h2[1]', $section ), leadwerk_theme_force_strong_heading_markup( (string) ( $value['title'] ?? '' ) ), 'heading' );
	$body_node = leadwerk_theme_dom_first( $xpath, './/div[./p][1]', $section );
	if ( ! $body_node ) {
		$body_node = leadwerk_theme_dom_first( $xpath, './/p[1]', $section );
	}
	leadwerk_theme_dom_set_inner_html( $body_node, (string) ( $value['body'] ?? '' ) );
	leadwerk_theme_bind_exact_button( $xpath, $section, './/a[contains(@class,"btn")][1]', (string) ( $value['cta_label'] ?? '' ), (string) ( $value['cta_page_key'] ?? '' ), (string) ( $value['cta_url'] ?? '' ) );
}

function leadwerk_theme_bind_exact_responsibility( $xpath, $section, $value ) {
	leadwerk_theme_set_placeholder_markup( leadwerk_theme_dom_first( $xpath, './/*[contains(@class,"responsibility-top__main")]//h2[1]', $section ), (string) ( $value['title'] ?? '' ), 'heading' );
	leadwerk_theme_set_placeholder_markup( leadwerk_theme_dom_first( $xpath, './/*[contains(@class,"responsibility-top__main")]//p[1]', $section ), (string) ( $value['intro'] ?? '' ), 'paragraph' );
	leadwerk_theme_bind_exact_button( $xpath, $section, './/*[contains(@class,"responsibility-top__side")]//a[1]', (string) ( $value['cta_label'] ?? '' ), (string) ( $value['cta_page_key'] ?? '' ), (string) ( $value['cta_url'] ?? '' ) );
	leadwerk_theme_bind_exact_image( $xpath, $section, './/*[contains(@class,"responsibility-bottom__image")]//img[1]', (int) ( $value['image'] ?? 0 ), (string) ( $value['image_alt'] ?? '' ) );
	leadwerk_theme_set_placeholder_markup( leadwerk_theme_dom_first( $xpath, './/*[contains(@class,"responsibility-lead")][1]', $section ), (string) ( $value['lead'] ?? '' ), 'paragraph' );

	$items = isset( $value['items'] ) && is_array( $value['items'] ) ? array_values( $value['items'] ) : array();
	$nodes = leadwerk_theme_dom_ensure_count( leadwerk_theme_dom_query( $xpath, './/*[contains(@class,"responsibility-bottom__content")]//*[contains(@class,"blurb")]', $section ), count( $items ) );
	foreach ( $nodes as $index => $node ) {
		$item = isset( $items[ $index ] ) ? $items[ $index ] : array();
		leadwerk_theme_dom_set_inner_html( leadwerk_theme_dom_first( $xpath, './/*[contains(@class,"blurb-content")][1]', $node ), (string) ( $item['content'] ?? '' ) );
	}

	leadwerk_theme_set_placeholder_markup( leadwerk_theme_dom_first( $xpath, './/*[contains(@class,"responsibility-note")][1]', $section ), (string) ( $value['note'] ?? '' ), 'paragraph' );
}

function leadwerk_theme_bind_exact_new_phase( $xpath, $section, $value ) {
	leadwerk_theme_bind_exact_image( $xpath, $section, './/*[contains(@class,"new-phase-img")][1]', (int) ( $value['image'] ?? 0 ), (string) ( $value['image_alt'] ?? '' ) );
	leadwerk_theme_set_placeholder_markup( leadwerk_theme_dom_first( $xpath, './/*[contains(@class,"new-phase-heading")][1]', $section ), (string) ( $value['title'] ?? '' ), 'heading' );
	leadwerk_theme_dom_set_inner_html( leadwerk_theme_dom_first( $xpath, './/*[contains(@class,"new-phase-text")][1]', $section ), (string) ( $value['body'] ?? '' ) );

	$items = isset( $value['items'] ) && is_array( $value['items'] ) ? array_values( $value['items'] ) : array();
	$nodes = leadwerk_theme_dom_ensure_count( leadwerk_theme_dom_query( $xpath, './/*[contains(@class,"new-phase-content")]//*[contains(@class,"blurb")]', $section ), count( $items ) );
	foreach ( $nodes as $index => $node ) {
		$item = isset( $items[ $index ] ) ? $items[ $index ] : array();
		leadwerk_theme_dom_set_inner_html( leadwerk_theme_dom_first( $xpath, './/*[contains(@class,"blurb-content")][1]', $node ), (string) ( $item['content'] ?? '' ) );
	}

	leadwerk_theme_bind_exact_button( $xpath, $section, './/*[contains(@class,"new-phase-content")]//a[contains(@class,"btn")][1]', (string) ( $value['cta_label'] ?? '' ), (string) ( $value['cta_page_key'] ?? '' ), (string) ( $value['cta_url'] ?? '' ) );
}

function leadwerk_theme_bind_exact_outcomes( $xpath, $section, $value ) {
	leadwerk_theme_set_placeholder_markup( leadwerk_theme_dom_first( $xpath, './/*[contains(@class,"section-heading")]//h2[1]', $section ), (string) ( $value['title'] ?? '' ), 'heading' );
	leadwerk_theme_set_placeholder_markup( leadwerk_theme_dom_first( $xpath, './/*[contains(@class,"section-heading")]//p[1]', $section ), (string) ( $value['intro'] ?? '' ), 'paragraph' );
	leadwerk_theme_dom_set_inner_html( leadwerk_theme_dom_first( $xpath, './/*[contains(@class,"outcomes-text")][1]', $section ), (string) ( $value['body'] ?? '' ) );

	$items = isset( $value['items'] ) && is_array( $value['items'] ) ? array_values( $value['items'] ) : array();
	$nodes = leadwerk_theme_dom_ensure_count( leadwerk_theme_dom_query( $xpath, './/*[contains(@class,"flip-box")]', $section ), count( $items ) );
	foreach ( $nodes as $index => $node ) {
		$item = isset( $items[ $index ] ) ? $items[ $index ] : array();
		if ( ! is_array( $item ) ) {
			continue;
		}

		leadwerk_theme_dom_set_attr( leadwerk_theme_dom_first( $xpath, './/*[contains(@class,"flip-box-icon")]//i[1]', $node ), 'class', (string) ( $item['icon'] ?? '' ) );
		leadwerk_theme_dom_set_text( leadwerk_theme_dom_first( $xpath, './/h4[1]', $node ), (string) ( $item['title'] ?? '' ) );
	leadwerk_theme_set_placeholder_markup( leadwerk_theme_dom_first( $xpath, './/*[contains(@class,"flip-box-back")]//p[1]', $node ), (string) ( $item['content'] ?? '' ), 'paragraph' );
	}
}

function leadwerk_theme_bind_exact_target_groups_image( $xpath, $section, $value ) {
	leadwerk_theme_set_placeholder_markup( leadwerk_theme_dom_first( $xpath, './/h2[1]', $section ), (string) ( $value['title'] ?? '' ), 'heading' );
	leadwerk_theme_set_placeholder_markup( leadwerk_theme_dom_first( $xpath, './/*[contains(@class,"target-intro")][1]', $section ), (string) ( $value['intro'] ?? '' ), 'paragraph' );

	$items = isset( $value['items'] ) && is_array( $value['items'] ) ? array_values( $value['items'] ) : array();
	$nodes = leadwerk_theme_dom_ensure_count( leadwerk_theme_dom_query( $xpath, './/*[contains(@class,"blurb")]', $section ), count( $items ) );
	foreach ( $nodes as $index => $node ) {
		$item = isset( $items[ $index ] ) ? $items[ $index ] : array();
		leadwerk_theme_dom_set_inner_html( leadwerk_theme_dom_first( $xpath, './/*[contains(@class,"blurb-content")][1]', $node ), (string) ( $item['content'] ?? '' ) );
	}

	leadwerk_theme_bind_exact_image( $xpath, $section, './/img[1]', (int) ( $value['image'] ?? 0 ), (string) ( $value['image_alt'] ?? '' ) );
	leadwerk_theme_bind_exact_button( $xpath, $section, './/a[contains(@class,"btn")][1]', (string) ( $value['cta_label'] ?? '' ), (string) ( $value['cta_page_key'] ?? '' ), (string) ( $value['cta_url'] ?? '' ) );
}

function leadwerk_theme_bind_exact_audience_cards( $xpath, $section, $value ) {
	leadwerk_theme_set_placeholder_markup( leadwerk_theme_dom_first( $xpath, './/h2[1]', $section ), (string) ( $value['title'] ?? '' ), 'heading' );

	$items = isset( $value['items'] ) && is_array( $value['items'] ) ? array_values( $value['items'] ) : array();
	$nodes = leadwerk_theme_dom_ensure_count( leadwerk_theme_dom_query( $xpath, './/*[contains(@class,"audience-card")]', $section ), count( $items ) );
	foreach ( $nodes as $index => $node ) {
		$item = isset( $items[ $index ] ) ? $items[ $index ] : array();
		if ( ! $node instanceof DOMElement || ! is_array( $item ) ) {
			continue;
		}

		leadwerk_theme_dom_toggle_class( $node, 'audience-card--empty', ! empty( $item['is_empty'] ) );
		leadwerk_theme_dom_set_attr( $node, 'aria-hidden', ! empty( $item['is_empty'] ) ? 'true' : '' );
		if ( ! empty( $item['is_empty'] ) ) {
			leadwerk_theme_dom_set_inner_html( $node, '' );
			continue;
		}

		leadwerk_theme_dom_set_text( leadwerk_theme_dom_first( $xpath, './/h3[1]', $node ), (string) ( $item['title'] ?? '' ) );
		leadwerk_theme_set_placeholder_markup( leadwerk_theme_dom_first( $xpath, './/p[1]', $node ), (string) ( $item['content'] ?? '' ), 'paragraph' );
		leadwerk_theme_bind_exact_button( $xpath, $node, './/a[contains(@class,"btn")][1]', (string) ( $item['button_label'] ?? '' ), (string) ( $item['button_page_key'] ?? '' ), (string) ( $item['button_url'] ?? '' ) );
	}
}

function leadwerk_theme_bind_exact_media_blurbs( $xpath, $section, $value ) {
	leadwerk_theme_set_placeholder_markup( leadwerk_theme_dom_first( $xpath, './/h2[1]', $section ), (string) ( $value['title'] ?? '' ), 'heading' );
	leadwerk_theme_dom_set_text( leadwerk_theme_dom_first( $xpath, './/*[contains(@class,"section-subtitle")][1]', $section ), (string) ( $value['subtitle'] ?? '' ) );
	leadwerk_theme_dom_set_inner_html( leadwerk_theme_dom_first( $xpath, './/*[contains(@class,"section-body")][1]', $section ), (string) ( $value['body'] ?? '' ) );

	$blurbs = isset( $value['blurbs'] ) && is_array( $value['blurbs'] ) ? array_values( $value['blurbs'] ) : array();
	$nodes  = leadwerk_theme_dom_ensure_count( leadwerk_theme_dom_query( $xpath, './/*[contains(@class,"blurb")]', $section ), count( $blurbs ) );
	foreach ( $nodes as $index => $node ) {
		$item = isset( $blurbs[ $index ] ) ? $blurbs[ $index ] : array();
		leadwerk_theme_dom_set_text( leadwerk_theme_dom_first( $xpath, './/h4[1]', $node ), (string) ( $item['title'] ?? '' ) );
		leadwerk_theme_dom_set_inner_html( leadwerk_theme_dom_first( $xpath, './/*[contains(@class,"blurb-content")][1]', $node ), leadwerk_theme_get_exact_blurb_html( $item ) );
	}

	leadwerk_theme_bind_exact_image( $xpath, $section, './/*[contains(@class,"feature-image")][1]', (int) ( $value['image'] ?? 0 ), (string) ( $value['image_alt'] ?? '' ) );
	leadwerk_theme_bind_exact_button( $xpath, $section, './/a[contains(@class,"btn")][1]', (string) ( $value['cta_label'] ?? '' ), (string) ( $value['cta_page_key'] ?? '' ), (string) ( $value['cta_url'] ?? '' ) );
}

function leadwerk_theme_bind_exact_invest_detail( $xpath, $section, $value ) {
	leadwerk_theme_set_placeholder_markup( leadwerk_theme_dom_first( $xpath, './/*[contains(@class,"two-col--invest-intro")]//h2[1]', $section ), (string) ( $value['title'] ?? '' ), 'heading' );
	leadwerk_theme_dom_set_text( leadwerk_theme_dom_first( $xpath, './/*[contains(@class,"two-col--invest-intro")]//*[contains(@class,"section-subtitle")][1]', $section ), (string) ( $value['subtitle'] ?? '' ) );
	leadwerk_theme_dom_set_inner_html( leadwerk_theme_dom_first( $xpath, './/*[contains(@class,"two-col--invest-intro")]//*[contains(@class,"section-body")][1]', $section ), (string) ( $value['body'] ?? '' ) );
	leadwerk_theme_bind_exact_image( $xpath, $section, './/*[contains(@class,"two-col--invest-intro")]//*[contains(@class,"feature-image")][1]', (int) ( $value['image'] ?? 0 ), (string) ( $value['image_alt'] ?? '' ) );
	leadwerk_theme_bind_exact_button( $xpath, $section, './/*[contains(@class,"two-col--invest-intro")]//a[contains(@class,"btn")][1]', (string) ( $value['cta_label'] ?? '' ), (string) ( $value['cta_page_key'] ?? '' ), (string) ( $value['cta_url'] ?? '' ) );

	leadwerk_theme_set_placeholder_markup( leadwerk_theme_dom_first( $xpath, './/*[contains(@class,"invest-explainer")]//h2[1]', $section ), (string) ( $value['explainer_title'] ?? '' ), 'heading' );
	leadwerk_theme_set_placeholder_markup( leadwerk_theme_dom_first( $xpath, './/*[contains(@class,"invest-explainer")]//p[1]', $section ), (string) ( $value['explainer_body'] ?? '' ), 'paragraph' );
	leadwerk_theme_dom_set_text( leadwerk_theme_dom_first( $xpath, './/*[contains(@class,"invest-explainer")]//h3[1]', $section ), (string) ( $value['explainer_sub'] ?? '' ) );

	$trends = isset( $value['trends'] ) && is_array( $value['trends'] ) ? array_values( $value['trends'] ) : array();
	$nodes  = leadwerk_theme_dom_ensure_count( leadwerk_theme_dom_query( $xpath, './/*[contains(@class,"megatrend-grid")]//*[contains(@class,"flip-box")]', $section ), count( $trends ) );
	foreach ( $nodes as $index => $node ) {
		$item = isset( $trends[ $index ] ) ? $trends[ $index ] : array();
		if ( ! is_array( $item ) ) {
			continue;
		}

		leadwerk_theme_dom_set_attr( leadwerk_theme_dom_first( $xpath, './/*[contains(@class,"flip-box-icon")]//i[1]', $node ), 'class', (string) ( $item['icon'] ?? '' ) );
		leadwerk_theme_dom_set_text( leadwerk_theme_dom_first( $xpath, './/h4[1]', $node ), (string) ( $item['title'] ?? '' ) );
		leadwerk_theme_set_placeholder_markup( leadwerk_theme_dom_first( $xpath, './/*[contains(@class,"flip-box-back")]//p[1]', $node ), (string) ( $item['content'] ?? '' ), 'paragraph' );
	}

	leadwerk_theme_dom_set_inner_html( leadwerk_theme_dom_first( $xpath, './/*[contains(@class,"invest-outro")][1]', $section ), (string) ( $value['outro'] ?? '' ) );
}

function leadwerk_theme_bind_exact_tax_detail( $xpath, $section, $value ) {
	leadwerk_theme_set_placeholder_markup( leadwerk_theme_dom_first( $xpath, './/*[contains(@class,"two-col--tax-intro")]//h2[1]', $section ), (string) ( $value['title'] ?? '' ), 'heading' );
	leadwerk_theme_dom_set_text( leadwerk_theme_dom_first( $xpath, './/*[contains(@class,"two-col--tax-intro")]//*[contains(@class,"section-subtitle")][1]', $section ), (string) ( $value['subtitle'] ?? '' ) );
	leadwerk_theme_dom_set_inner_html( leadwerk_theme_dom_first( $xpath, './/*[contains(@class,"two-col--tax-intro")]//*[contains(@class,"section-body")][1]', $section ), (string) ( $value['body'] ?? '' ) );
	leadwerk_theme_dom_set_text( leadwerk_theme_dom_first( $xpath, './/*[contains(@class,"two-col--tax-intro")]//h3[1]', $section ), (string) ( $value['how_title'] ?? '' ) );
	leadwerk_theme_set_placeholder_markup( leadwerk_theme_dom_first( $xpath, './/*[contains(@class,"section-subtitle--dense")][1]', $section ), (string) ( $value['how_body'] ?? '' ), 'paragraph' );
	leadwerk_theme_bind_exact_image( $xpath, $section, './/*[contains(@class,"two-col--tax-intro")]//*[contains(@class,"feature-image")][1]', (int) ( $value['image'] ?? 0 ), (string) ( $value['image_alt'] ?? '' ) );
	leadwerk_theme_bind_exact_button( $xpath, $section, './/*[contains(@class,"two-col--tax-intro")]//a[contains(@class,"btn")][1]', (string) ( $value['cta_label'] ?? '' ), (string) ( $value['cta_page_key'] ?? '' ), (string) ( $value['cta_url'] ?? '' ) );
	leadwerk_theme_set_placeholder_markup( leadwerk_theme_dom_first( $xpath, './/*[contains(@class,"section-heading--center")]//h2[1]', $section ), (string) ( $value['steps_title'] ?? '' ), 'heading' );

	$steps = isset( $value['steps'] ) && is_array( $value['steps'] ) ? array_values( $value['steps'] ) : array();
	$nodes = leadwerk_theme_dom_ensure_count( leadwerk_theme_dom_query( $xpath, './/*[contains(@class,"step-card")]', $section ), count( $steps ) );
	foreach ( $nodes as $index => $node ) {
		$item = isset( $steps[ $index ] ) ? $steps[ $index ] : array();
		if ( ! is_array( $item ) ) {
			continue;
		}

		leadwerk_theme_dom_set_icon_value( leadwerk_theme_dom_first( $xpath, './/*[contains(@class,"step-icon")][1]', $node ), (string) ( $item['icon'] ?? '' ) );
		leadwerk_theme_dom_set_text( leadwerk_theme_dom_first( $xpath, './/h4[1]', $node ), (string) ( $item['title'] ?? '' ) );
		leadwerk_theme_set_placeholder_markup( leadwerk_theme_dom_first( $xpath, './/p[1]', $node ), (string) ( $item['content'] ?? '' ), 'paragraph' );
	}
}

function leadwerk_theme_bind_exact_contact_main( $xpath, $section, $value ) {
	leadwerk_theme_set_placeholder_markup( leadwerk_theme_dom_first( $xpath, './/h2[1]', $section ), (string) ( $value['title'] ?? '' ), 'heading' );
	leadwerk_theme_set_placeholder_markup( leadwerk_theme_dom_first( $xpath, './/*[contains(@class,"kontakt-form-intro")][1]', $section ), (string) ( $value['intro'] ?? '' ), 'paragraph' );
	leadwerk_theme_dom_set_text( leadwerk_theme_dom_first( $xpath, './/*[contains(@class,"btn-submit")][1]', $section ), (string) ( $value['submit_label'] ?? '' ) );

	$privacy_label = leadwerk_theme_dom_first( $xpath, './/label[@for="contact-privacy"][1]', $section );
	leadwerk_theme_set_placeholder_markup( $privacy_label, (string) ( $value['privacy_label'] ?? '' ), 'paragraph' );
	$privacy_link = leadwerk_theme_dom_first( $xpath, './/label[@for="contact-privacy"]//a[1]', $section );
	if ( $privacy_link ) {
		leadwerk_theme_dom_set_attr( $privacy_link, 'href', leadwerk_theme_resolve_exact_href( (string) ( $value['privacy_page'] ?? '' ), (string) $privacy_link->getAttribute( 'href' ) ) );
	}

	$cards = isset( $value['info_cards'] ) && is_array( $value['info_cards'] ) ? array_values( $value['info_cards'] ) : array();
	$nodes = leadwerk_theme_dom_ensure_count( leadwerk_theme_dom_query( $xpath, './/*[contains(@class,"kontakt-info-card")]', $section ), count( $cards ) );
	foreach ( $nodes as $index => $node ) {
		$item = isset( $cards[ $index ] ) ? $cards[ $index ] : array();
		if ( ! $node instanceof DOMElement || ! is_array( $item ) ) {
			continue;
		}

		$link = leadwerk_theme_dom_first( $xpath, './/*[contains(@class,"kontakt-info-card__link")][1]', $node );
		$type = trim( (string) ( $item['type'] ?? '' ) );
		$href = trim( (string) ( $item['href'] ?? '' ) );
		$title = (string) ( $item['title'] ?? '' );
		$value = (string) ( $item['value'] ?? '' );

		$is_legacy_address_card = 'address' === $type && false !== strpos( $href, 'google.com/maps/dir/' );
		if ( $is_legacy_address_card ) {
			$type  = 'link';
			$href  = 'https://calendly.com/kevinhudel/kostenloses-erstgespraech';
			$title = 'Termin vereinbaren';
			$value = 'Kostenloses Erstgespr&auml;ch<br>via Calendly';
		}

		if ( '' === $href ) {
			if ( 'phone' === $type ) {
				$href = 'tel:' . preg_replace( '/\s+/', '', wp_strip_all_tags( $value ) );
			} elseif ( 'email' === $type ) {
				$href = 'mailto:' . sanitize_email( wp_strip_all_tags( $value ) );
			}
		}

		leadwerk_theme_dom_set_attr( $link, 'href', $href );
		leadwerk_theme_dom_set_text( leadwerk_theme_dom_first( $xpath, './/h4[1]', $node ), $title );
		leadwerk_theme_set_placeholder_markup( leadwerk_theme_dom_first( $xpath, './/p[1]', $node ), $value, 'paragraph' );
	}

	$form_card = leadwerk_theme_dom_first( $xpath, './/*[contains(@class,"contact-form-card")][1]', $section );
	if ( $form_card ) {
		leadwerk_theme_dom_set_trusted_inner_html( $form_card, leadwerk_theme_get_contact_form_markup() );
	}
}

function leadwerk_theme_bind_exact_danke_main( $xpath, $section, $value ) {
	leadwerk_theme_set_placeholder_markup(
		leadwerk_theme_dom_first( $xpath, './/*[contains(@class,"danke-content")]//p[1]', $section ),
		(string) ( $value['body'] ?? '' ),
		'paragraph'
	);
	leadwerk_theme_bind_exact_button(
		$xpath,
		$section,
		'.//*[contains(@class,"danke-actions")]//a[1]',
		(string) ( $value['primary_label'] ?? '' ),
		(string) ( $value['primary_page_key'] ?? '' ),
		(string) ( $value['primary_url'] ?? '' )
	);
	leadwerk_theme_bind_exact_button(
		$xpath,
		$section,
		'.//*[contains(@class,"danke-actions")]//a[2]',
		(string) ( $value['secondary_label'] ?? '' ),
		(string) ( $value['secondary_page_key'] ?? '' ),
		(string) ( $value['secondary_url'] ?? '' )
	);
}
