<?php
/**
 * Theme-native JSON-LD structured data for FINORA pages.
 *
 * @package Leadwerk_Theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Output JSON-LD graph in wp_head.
 *
 * @return void
 */
function leadwerk_theme_output_json_ld() {
	if ( is_admin() || wp_doing_ajax() ) {
		return;
	}

	$graph = leadwerk_theme_build_json_ld_graph();
	if ( empty( $graph ) ) {
		return;
	}

	$payload = array(
		'@context' => 'https://schema.org',
		'@graph'   => array_values( $graph ),
	);

	$json = wp_json_encode( $payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	if ( ! is_string( $json ) || '' === $json ) {
		return;
	}

	echo '<script type="application/ld+json">' . $json . '</script>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}
add_action( 'wp_head', 'leadwerk_theme_output_json_ld', 20 );

/**
 * Build the schema.org @graph for the current request.
 *
 * @return array<int,array<string,mixed>>
 */
function leadwerk_theme_build_json_ld_graph() {
	$post_id    = (int) get_queried_object_id();
	$source_key = $post_id ? (string) get_post_meta( $post_id, 'leadwerk_source_key', true ) : '';

	// Only FINORA-managed pages (and front page if mapped).
	if ( '' === $source_key && ! is_front_page() ) {
		return array();
	}

	if ( '' === $source_key && is_front_page() ) {
		$source_key = 'finora-home-v1';
	}

	$lang          = function_exists( 'leadwerk_theme_get_current_lang' ) ? leadwerk_theme_get_current_lang() : 'de';
	$home_url      = home_url( '/' );
	$org_id        = untrailingslashit( $home_url ) . '/#organization';
	$website_id    = untrailingslashit( $home_url ) . '/#website';
	$page_url      = $post_id ? get_permalink( $post_id ) : $home_url;
	$page_url      = is_string( $page_url ) && '' !== $page_url ? $page_url : $home_url;
	$in_language   = 'en' === $lang ? 'en' : 'de';

	$graph   = array();
	$graph[] = leadwerk_theme_build_organization_schema( $org_id, $home_url );
	$graph[] = array(
		'@type'     => 'WebSite',
		'@id'       => $website_id,
		'url'       => $home_url,
		'name'      => 'Finora Investment Studio',
		'inLanguage'=> $in_language,
		'publisher' => array( '@id' => $org_id ),
	);

	$breadcrumb = leadwerk_theme_build_breadcrumb_schema( $post_id, $source_key, $page_url, $home_url, $lang );
	if ( ! empty( $breadcrumb ) ) {
		$graph[] = $breadcrumb;
	}

	$faq = leadwerk_theme_build_faq_schema( $post_id, $in_language );
	if ( ! empty( $faq ) ) {
		$graph[] = $faq;
	}

	$service = leadwerk_theme_build_service_schema( $post_id, $source_key, $page_url, $org_id );
	if ( ! empty( $service ) ) {
		$graph[] = $service;
	}

	return array_values(
		array_filter(
			$graph,
			static function ( $item ) {
				return is_array( $item ) && ! empty( $item );
			}
		)
	);
}

/**
 * Organization + FinancialService (LocalBusiness family).
 *
 * @param string $org_id   Organization @id.
 * @param string $home_url Site home URL.
 * @return array<string,mixed>
 */
function leadwerk_theme_build_organization_schema( $org_id, $home_url ) {
	$address_raw = function_exists( 'leadwerk_theme_get_option_value' )
		? leadwerk_theme_get_option_value( 'company_address', "Brauerstr. 17\n76137 Karlsruhe" )
		: "Brauerstr. 17\n76137 Karlsruhe";
	$phone       = function_exists( 'leadwerk_theme_get_option_value' )
		? leadwerk_theme_get_option_value( 'company_phone', '01512 8915214' )
		: '01512 8915214';
	$email       = function_exists( 'leadwerk_theme_get_option_value' )
		? leadwerk_theme_get_option_value( 'company_email', 'hallo@finora.de' )
		: 'hallo@finora.de';
	$logo        = function_exists( 'leadwerk_theme_get_option_image_url' )
		? leadwerk_theme_get_option_image_url( 'footer_logo', 'assets/images/Logo-final-weiss-rz.png' )
		: '';

	$postal = leadwerk_theme_parse_postal_address( $address_raw );

	$schema = array(
		'@type'     => array( 'Organization', 'FinancialService' ),
		'@id'       => $org_id,
		'name'      => 'Finora Investment Studio',
		'url'       => $home_url,
		'email'     => sanitize_email( $email ),
		'telephone' => leadwerk_theme_normalize_phone_e164( $phone ),
		'address'   => $postal,
		'areaServed'=> array(
			'@type' => 'Country',
			'name'  => 'Deutschland',
		),
	);

	if ( '' !== trim( (string) $logo ) ) {
		$schema['logo']  = esc_url_raw( $logo );
		$schema['image'] = esc_url_raw( $logo );
	}

	return $schema;
}

/**
 * Parse multiline company address into PostalAddress.
 *
 * @param string $raw Raw address.
 * @return array<string,string>
 */
function leadwerk_theme_parse_postal_address( $raw ) {
	$lines = preg_split( '/\R+/', trim( (string) $raw ) );
	$lines = is_array( $lines ) ? array_values( array_filter( array_map( 'trim', $lines ) ) ) : array();

	$street = $lines[0] ?? 'Brauerstr. 17';
	$city_line = $lines[1] ?? '76137 Karlsruhe';
	$postal_code = '';
	$address_locality = $city_line;

	if ( preg_match( '/^(\d{5})\s+(.+)$/u', $city_line, $matches ) ) {
		$postal_code      = $matches[1];
		$address_locality = $matches[2];
	}

	return array(
		'@type'           => 'PostalAddress',
		'streetAddress'   => $street,
		'postalCode'     => $postal_code,
		'addressLocality' => $address_locality,
		'addressCountry'  => 'DE',
	);
}

/**
 * Normalize DE phone numbers toward E.164-ish format.
 *
 * @param string $phone Raw phone.
 * @return string
 */
function leadwerk_theme_normalize_phone_e164( $phone ) {
	$phone = trim( (string) $phone );
	$digits = preg_replace( '/[^\d+]/', '', $phone );
	if ( ! is_string( $digits ) || '' === $digits ) {
		return $phone;
	}

	if ( 0 === strpos( $digits, '+' ) ) {
		return $digits;
	}
	if ( 0 === strpos( $digits, '00' ) ) {
		return '+' . substr( $digits, 2 );
	}
	if ( 0 === strpos( $digits, '0' ) ) {
		return '+49' . substr( $digits, 1 );
	}

	return $digits;
}

/**
 * BreadcrumbList: Home → current page (skipped on front page).
 *
 * @param int    $post_id    Post ID.
 * @param string $source_key Source key.
 * @param string $page_url   Canonical page URL.
 * @param string $home_url   Home URL.
 * @param string $lang       Language code.
 * @return array<string,mixed>
 */
function leadwerk_theme_build_breadcrumb_schema( $post_id, $source_key, $page_url, $home_url, $lang ) {
	if ( 'finora-home-v1' === $source_key || is_front_page() ) {
		return array();
	}

	$home_label = 'en' === $lang ? 'Home' : 'Startseite';
	$page_title = $post_id ? get_the_title( $post_id ) : '';
	if ( '' === trim( (string) $page_title ) && function_exists( 'leadwerk_theme_get_page_title' ) ) {
		$page_title = leadwerk_theme_get_page_title( $source_key, $lang, '' );
	}
	if ( '' === trim( (string) $page_title ) ) {
		$page_title = 'en' === $lang ? 'Page' : 'Seite';
	}

	return array(
		'@type'           => 'BreadcrumbList',
		'@id'             => untrailingslashit( $page_url ) . '/#breadcrumb',
		'itemListElement' => array(
			array(
				'@type'    => 'ListItem',
				'position' => 1,
				'name'     => $home_label,
				'item'     => $home_url,
			),
			array(
				'@type'    => 'ListItem',
				'position' => 2,
				'name'     => wp_strip_all_tags( (string) $page_title ),
				'item'     => $page_url,
			),
		),
	);
}

/**
 * FAQPage from Leadwerk FAQ section items (visible content only).
 *
 * @param int    $post_id     Post ID.
 * @param string $in_language Language code.
 * @return array<string,mixed>
 */
function leadwerk_theme_build_faq_schema( $post_id, $in_language ) {
	if ( $post_id <= 0 || ! class_exists( 'Leadwerk_Content_Schema' ) || ! function_exists( 'get_field' ) ) {
		return array();
	}

	$group = Leadwerk_Content_Schema::get_group_for_post( $post_id );
	if ( ! $group || empty( $group['field_name'] ) || empty( $group['layouts'] ) ) {
		return array();
	}

	if ( ! isset( $group['layouts']['faq'] ) ) {
		return array();
	}

	$value    = get_field( $group['field_name'], $post_id );
	$sections = is_array( $value ) ? array_values( $value ) : array();
	$faq_data = null;
	$index    = 0;

	foreach ( (array) $group['layouts'] as $layout_key => $layout_schema ) {
		$section = isset( $sections[ $index ] ) && is_array( $sections[ $index ] ) ? $sections[ $index ] : array();
		++$index;

		$layout_name = (string) ( $section['acf_fc_layout'] ?? $layout_key );
		$template    = (string) ( $layout_schema['template'] ?? $layout_key );
		if ( 'faq' !== $layout_name && 'faq' !== $template && 'faq' !== $layout_key ) {
			continue;
		}

		$faq_data = $section;
		break;
	}

	if ( ! is_array( $faq_data ) || empty( $faq_data['items'] ) || ! is_array( $faq_data['items'] ) ) {
		return array();
	}

	$entities = array();
	foreach ( array_values( $faq_data['items'] ) as $item ) {
		if ( ! is_array( $item ) ) {
			continue;
		}

		$question = trim( wp_strip_all_tags( (string) ( $item['question'] ?? '' ) ) );
		$answer   = trim( wp_strip_all_tags( (string) ( $item['answer'] ?? '' ) ) );
		if ( '' === $question || '' === $answer ) {
			continue;
		}

		$entities[] = array(
			'@type'          => 'Question',
			'name'           => $question,
			'acceptedAnswer' => array(
				'@type' => 'Answer',
				'text'  => $answer,
			),
		);
	}

	if ( empty( $entities ) ) {
		return array();
	}

	return array(
		'@type'      => 'FAQPage',
		'inLanguage' => $in_language,
		'mainEntity' => $entities,
	);
}

/**
 * Service schema for consulting pages.
 *
 * @param int    $post_id    Post ID.
 * @param string $source_key Source key.
 * @param string $page_url   Page URL.
 * @param string $org_id     Organization @id.
 * @return array<string,mixed>
 */
function leadwerk_theme_build_service_schema( $post_id, $source_key, $page_url, $org_id ) {
	$service_keys = array(
		'finora-retirement-v1',
		'finora-investment-v1',
		'finora-real-estate-v1',
		'finora-inheritance-v1',
	);

	if ( ! in_array( $source_key, $service_keys, true ) ) {
		return array();
	}

	$name = $post_id ? get_the_title( $post_id ) : '';
	$name = trim( wp_strip_all_tags( (string) $name ) );
	if ( '' === $name ) {
		return array();
	}

	$description = '';
	if ( $post_id ) {
		$description = trim( (string) get_post_meta( $post_id, 'leadwerk_meta_description', true ) );
		if ( '' === $description ) {
			$description = trim( (string) get_post_meta( $post_id, '_yoast_wpseo_metadesc', true ) );
		}
	}

	$schema = array(
		'@type'       => 'Service',
		'@id'         => untrailingslashit( $page_url ) . '/#service',
		'name'        => $name,
		'url'         => $page_url,
		'provider'    => array( '@id' => $org_id ),
		'areaServed'  => array(
			'@type' => 'Country',
			'name'  => 'Deutschland',
		),
	);

	if ( '' !== $description ) {
		$schema['description'] = wp_strip_all_tags( $description );
	}

	return $schema;
}

/**
 * Disable Yoast JSON-LD on FINORA-managed pages to avoid duplicate schema.
 *
 * @param mixed $data Yoast output.
 * @return mixed
 */
function leadwerk_theme_disable_yoast_json_ld_on_finora_pages( $data ) {
	$post_id = (int) get_queried_object_id();
	if ( $post_id <= 0 ) {
		return $data;
	}

	$source_key = (string) get_post_meta( $post_id, 'leadwerk_source_key', true );
	if ( '' === $source_key && is_front_page() ) {
		$source_key = 'finora-home-v1';
	}

	if ( '' !== $source_key ) {
		return false;
	}

	return $data;
}
add_filter( 'wpseo_json_ld_output', 'leadwerk_theme_disable_yoast_json_ld_on_finora_pages' );
