<?php
/**
 * Generic structured section renderer for FINORA pages.
 *
 * @package Leadwerk_Theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Render one structured FINORA group.
 *
 * @param array<string,mixed> $group   Group schema.
 * @param mixed               $value   Stored field value.
 * @param int                 $post_id Post ID.
 * @return string
 */
function leadwerk_theme_render_structured_page_group( $group, $value, $post_id = 0 ) {
	$resolved = leadwerk_theme_resolve_structured_group_value( $group, $value, $post_id );
	$value    = $resolved['value'];

	if ( ! empty( $resolved['override_html'] ) ) {
		return (string) $resolved['override_html'];
	}

	if ( empty( $group['layouts'] ) ) {
		$headline = isset( $value['headline'] ) ? (string) $value['headline'] : '';
		$content  = isset( $value['content'] ) ? (string) $value['content'] : '';

		if ( '' === trim( wp_strip_all_tags( $headline . $content ) ) ) {
			return leadwerk_theme_render_missing_content_notice( $group, $post_id );
		}

		return sprintf(
			'<section class="content-section content-section--white legal-content"><div class="container--narrow"><h1 class="legal-title anim">%1$s</h1><div class="legal-body anim" style="animation-delay:100ms">%2$s</div></div></section>',
			esc_html( $headline ),
			wp_kses_post( $content )
		);
	}

	$sections = is_array( $value ) ? array_values( $value ) : array();
	if ( empty( $sections ) ) {
		return leadwerk_theme_render_missing_content_notice( $group, $post_id );
	}

	$output = '';
	$index  = 0;
	foreach ( (array) $group['layouts'] as $layout_key => $layout_schema ) {
		$section = isset( $sections[ $index ] ) && is_array( $sections[ $index ] ) ? $sections[ $index ] : array( 'acf_fc_layout' => $layout_key );
		$output .= leadwerk_theme_render_structured_section( $section, $layout_schema, $layout_key, $index, $post_id );
		++$index;
	}

	if ( '' === trim( wp_strip_all_tags( $output ) ) ) {
		return leadwerk_theme_render_missing_content_notice( $group, $post_id );
	}

	return $output;
}

/**
 * Resolve the effective structured value for one page.
 *
 * @param array<string,mixed> $group   Group schema.
 * @param mixed               $value   Current field value.
 * @param int                 $post_id Post ID.
 * @return array<string,mixed>
 */
function leadwerk_theme_resolve_structured_group_value( $group, $value, $post_id = 0 ) {
	if ( leadwerk_theme_group_has_visible_content( $group, $value ) ) {
		return array(
			'value'                   => $value,
			'used_last_good_fallback' => false,
			'override_html'           => '',
		);
	}

	$field_name = (string) ( $group['field_name'] ?? '' );
	if ( '' !== $field_name ) {
		$snapshot = get_post_meta( $post_id, '_leadwerk_last_good_' . sanitize_key( $field_name ), true );
		if ( is_array( $snapshot ) && array_key_exists( 'value', $snapshot ) && leadwerk_theme_group_has_visible_content( $group, $snapshot['value'] ) ) {
			return array(
				'value'                   => $snapshot['value'],
				'used_last_good_fallback' => true,
				'override_html'           => '',
			);
		}
	}

	if ( empty( $group['layouts'] ) ) {
		$post_content = (string) get_post_field( 'post_content', $post_id );
		if ( '' !== trim( $post_content ) && ! has_block( 'acf/leadwerk-finora-page', $post_id ) ) {
			return array(
				'value'                   => $value,
				'used_last_good_fallback' => false,
				'override_html'           => $post_content,
			);
		}
	}

	return array(
		'value'                   => $value,
		'used_last_good_fallback' => false,
		'override_html'           => '',
	);
}

/**
 * Whether a group contains any visible content.
 *
 * @param array<string,mixed> $group Group schema.
 * @param mixed               $value Group value.
 * @return bool
 */
function leadwerk_theme_group_has_visible_content( $group, $value ) {
	if ( empty( $group['layouts'] ) ) {
		$headline = is_array( $value ) ? (string) ( $value['headline'] ?? '' ) : '';
		$content  = is_array( $value ) ? (string) ( $value['content'] ?? '' ) : '';
		return '' !== trim( wp_strip_all_tags( $headline . $content ) );
	}

	$sections = is_array( $value ) ? array_values( $value ) : array();
	if ( empty( $sections ) ) {
		return false;
	}

	foreach ( $sections as $section ) {
		if ( is_array( $section ) && leadwerk_theme_structured_section_has_visible_content( $section ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Render one structured section.
 *
 * @param array<string,mixed> $section       Section values.
 * @param array<string,mixed> $layout_schema Layout schema.
 * @param string              $layout_key    Layout key.
 * @param int                 $index         Section index.
 * @param int                 $post_id       Post ID.
 * @return string
 */
function leadwerk_theme_render_structured_section( $section, $layout_schema, $layout_key, $index, $post_id ) {
	$template = (string) ( $layout_schema['template'] ?? $layout_key );

	switch ( $template ) {
		case 'hero_slider':
			return leadwerk_theme_render_hero_slider_section( $section, $layout_schema, $layout_key, $index );
		case 'contact_main':
			return leadwerk_theme_render_contact_main_section( $section, $layout_schema, $layout_key, $index );
		default:
			return leadwerk_theme_render_generic_structured_section( $section, $layout_schema, $layout_key, $index, $post_id );
	}
}

/**
 * Render the home hero slider section as cards.
 *
 * @param array<string,mixed> $section       Section values.
 * @param array<string,mixed> $layout_schema Layout schema.
 * @param string              $layout_key    Layout key.
 * @param int                 $index         Section index.
 * @return string
 */
function leadwerk_theme_render_hero_slider_section( $section, $layout_schema, $layout_key, $index ) {
	$slides   = isset( $section['slides'] ) && is_array( $section['slides'] ) ? array_values( $section['slides'] ) : array();
	$services = isset( $section['services'] ) && is_array( $section['services'] ) ? array_values( $section['services'] ) : array();

	ob_start();
	?>
	<section class="leadwerk-structured-section leadwerk-structured-section--hero-slider leadwerk-layout--<?php echo esc_attr( sanitize_html_class( $layout_key ) ); ?>" data-layout="<?php echo esc_attr( $layout_key ); ?>" data-index="<?php echo esc_attr( (string) $index ); ?>">
		<div class="leadwerk-structured-container">
			<?php if ( ! empty( $slides ) ) : ?>
				<div class="leadwerk-hero-slider-grid">
					<?php foreach ( $slides as $slide ) : ?>
						<div class="leadwerk-hero-slide-card">
							<div class="leadwerk-hero-slide-card__media">
								<?php echo leadwerk_theme_render_structured_image( (int) ( $slide['background'] ?? 0 ), (string) ( $slide['background_alt'] ?? '' ), 'leadwerk-hero-slide-card__image' ); ?>
							</div>
							<div class="leadwerk-hero-slide-card__content">
								<?php echo leadwerk_theme_render_main_title( (string) ( $slide['title'] ?? '' ), 'leadwerk-hero-slide-card__title' ); ?>
								<?php echo leadwerk_theme_render_html_block( (string) ( $slide['subtitle'] ?? '' ), 'leadwerk-hero-slide-card__subtitle leadwerk-structured-copy' ); ?>
								<?php echo leadwerk_theme_render_button_markup( array(
									'label'    => (string) ( $slide['cta_label'] ?? '' ),
									'page_key' => (string) ( $slide['cta_page_key'] ?? '' ),
									'url'      => (string) ( $slide['cta_url'] ?? '' ),
								), 'leadwerk-structured-button' ); ?>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<?php if ( ! empty( $services ) ) : ?>
				<div class="leadwerk-structured-grid leadwerk-structured-grid--cards leadwerk-structured-grid--compact">
					<?php foreach ( $services as $service ) : ?>
						<article class="leadwerk-structured-card leadwerk-structured-card--service">
							<?php echo leadwerk_theme_render_structured_image( (int) ( $service['icon'] ?? 0 ), (string) ( $service['icon_alt'] ?? '' ), 'leadwerk-structured-card__icon' ); ?>
							<?php echo leadwerk_theme_render_main_title( (string) ( $service['title'] ?? '' ), 'leadwerk-structured-card__title leadwerk-structured-card__title--small', 'h3' ); ?>
							<?php echo leadwerk_theme_render_html_block( (string) ( $service['description'] ?? '' ), 'leadwerk-structured-copy leadwerk-structured-copy--small' ); ?>
							<?php echo leadwerk_theme_render_button_markup( array(
								'label'    => (string) ( wp_strip_all_tags( $service['title'] ?? '' ) ),
								'page_key' => (string) ( $service['page_key'] ?? '' ),
								'url'      => (string) ( $service['url'] ?? '' ),
							), 'leadwerk-structured-link leadwerk-structured-link--inline', true ); ?>
						</article>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
	</section>
	<?php

	return ob_get_clean();
}

/**
 * Render the contact main section.
 *
 * @param array<string,mixed> $section       Section values.
 * @param array<string,mixed> $layout_schema Layout schema.
 * @param string              $layout_key    Layout key.
 * @param int                 $index         Section index.
 * @return string
 */
function leadwerk_theme_render_contact_main_section( $section, $layout_schema, $layout_key, $index ) {
	$info_cards = isset( $section['info_cards'] ) && is_array( $section['info_cards'] ) ? array_values( $section['info_cards'] ) : array();
	$privacy    = (string) ( $section['privacy_label'] ?? '' );
	$privacy_key = (string) ( $section['privacy_page'] ?? '' );
	$privacy_url = '' !== $privacy_key ? leadwerk_theme_get_page_url( $privacy_key, leadwerk_theme_get_current_lang(), '#' ) : '#';
	$privacy_link_label = leadwerk_theme_get_string( 'contact_privacy_link_label', 'Datenschutz' );

	ob_start();
	?>
	<section class="leadwerk-structured-section leadwerk-structured-section--contact leadwerk-layout--<?php echo esc_attr( sanitize_html_class( $layout_key ) ); ?>" data-layout="<?php echo esc_attr( $layout_key ); ?>" data-index="<?php echo esc_attr( (string) $index ); ?>">
		<div class="leadwerk-structured-container">
			<div class="leadwerk-structured-shell leadwerk-structured-shell--two-column">
				<div class="leadwerk-structured-shell__content">
					<?php echo leadwerk_theme_render_main_title( (string) ( $section['title'] ?? '' ) ); ?>
					<?php echo leadwerk_theme_render_html_block( (string) ( $section['intro'] ?? '' ), 'leadwerk-structured-copy' ); ?>
					<?php if ( ! empty( $info_cards ) ) : ?>
						<div class="leadwerk-structured-grid leadwerk-structured-grid--cards">
							<?php foreach ( $info_cards as $card ) : ?>
								<article class="leadwerk-structured-card leadwerk-structured-card--contact">
									<?php echo leadwerk_theme_render_main_title( (string) ( $card['title'] ?? '' ), 'leadwerk-structured-card__title leadwerk-structured-card__title--small', 'h3' ); ?>
									<div class="leadwerk-structured-copy">
										<?php
										$value = (string) ( $card['value'] ?? '' );
										$href  = (string) ( $card['href'] ?? '' );
										if ( '' !== trim( $href ) ) {
											echo '<a href="' . esc_url( $href ) . '">' . wp_kses_post( $value ) . '</a>';
										} else {
											echo wp_kses_post( $value );
										}
										?>
									</div>
								</article>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
				</div>
				<div class="leadwerk-structured-shell__aside">
					<div class="leadwerk-structured-card leadwerk-structured-card--form">
						<?php echo leadwerk_theme_get_contact_form_markup(); ?>
						<?php if ( '' !== trim( $privacy ) ) : ?>
							<div class="leadwerk-structured-copy leadwerk-structured-copy--small leadwerk-contact-privacy">
								<?php
									echo wp_kses_post( $privacy );
									if ( '#' !== $privacy_url ) {
										echo ' <a class="leadwerk-structured-link leadwerk-structured-link--inline" href="' . esc_url( $privacy_url ) . '">' . esc_html( $privacy_link_label ) . '</a>';
									}
								?>
							</div>
						<?php endif; ?>
					</div>
				</div>
			</div>
		</div>
	</section>
	<?php

	return ob_get_clean();
}

/**
 * Render a generic structured section.
 *
 * @param array<string,mixed> $section       Section values.
 * @param array<string,mixed> $layout_schema Layout schema.
 * @param string              $layout_key    Layout key.
 * @param int                 $index         Section index.
 * @param int                 $post_id       Post ID.
 * @return string
 */
function leadwerk_theme_render_generic_structured_section( $section, $layout_schema, $layout_key, $index, $post_id ) {
	$title       = leadwerk_theme_pick_first_value( $section, array( 'title', 'goals_title', 'challenge_title', 'explainer_title', 'how_title', 'steps_title' ) );
	$intro_keys  = array( 'subtitle', 'intro', 'body', 'lead', 'summary', 'outro', 'note', 'goals_body', 'challenge_body', 'explainer_body', 'explainer_sub', 'how_body', 'left_body' );
	$image_id    = (int) leadwerk_theme_pick_first_value( $section, array( 'image', 'background_image' ), 0 );
	$image_alt   = (string) leadwerk_theme_pick_first_value( $section, array( 'image_alt', 'background_alt' ), '' );
	$has_content = leadwerk_theme_structured_section_has_visible_content( $section );

	if ( ! $has_content ) {
		return current_user_can( 'edit_post', $post_id )
			? '<section class="leadwerk-structured-section"><div class="leadwerk-structured-container"><div class="leadwerk-structured-empty">Section "' . esc_html( $layout_schema['label'] ?? $layout_key ) . '" has no visible content yet.</div></div></section>'
			: '';
	}

	ob_start();
	?>
	<section class="leadwerk-structured-section leadwerk-layout--<?php echo esc_attr( sanitize_html_class( $layout_key ) ); ?>" data-layout="<?php echo esc_attr( $layout_key ); ?>" data-index="<?php echo esc_attr( (string) $index ); ?>">
		<div class="leadwerk-structured-container">
			<div class="leadwerk-structured-shell<?php echo $image_id ? ' leadwerk-structured-shell--two-column' : ''; ?>">
				<div class="leadwerk-structured-shell__content">
					<?php echo leadwerk_theme_render_main_title( $title ); ?>
					<?php foreach ( $intro_keys as $intro_key ) : ?>
						<?php
						if ( ! isset( $section[ $intro_key ] ) || '' === trim( (string) $section[ $intro_key ] ) ) {
							continue;
						}
						echo leadwerk_theme_render_html_block( (string) $section[ $intro_key ], 'leadwerk-structured-copy leadwerk-structured-copy--' . sanitize_html_class( $intro_key ) );
						?>
					<?php endforeach; ?>

					<?php if ( ! empty( $section['right_title'] ) ) : ?>
						<h3 class="leadwerk-structured-subtitle"><?php echo esc_html( (string) $section['right_title'] ); ?></h3>
					<?php endif; ?>

					<?php echo leadwerk_theme_render_structured_repeaters( $section, $layout_schema ); ?>
					<?php echo leadwerk_theme_render_structured_buttons( $section ); ?>
				</div>

				<?php if ( $image_id ) : ?>
					<div class="leadwerk-structured-shell__aside">
						<?php echo leadwerk_theme_render_structured_image( $image_id, $image_alt, 'leadwerk-structured-image leadwerk-structured-image--aside' ); ?>
					</div>
				<?php endif; ?>
			</div>
		</div>
	</section>
	<?php

	return ob_get_clean();
}

/**
 * Render all repeaters for one section.
 *
 * @param array<string,mixed> $section       Section values.
 * @param array<string,mixed> $layout_schema Layout schema.
 * @return string
 */
function leadwerk_theme_render_structured_repeaters( $section, $layout_schema ) {
	$output = '';

	foreach ( (array) ( $layout_schema['fields'] ?? array() ) as $field_key => $definition ) {
		if ( 'repeater' !== ( $definition['type'] ?? '' ) ) {
			continue;
		}

		$items = isset( $section[ $field_key ] ) && is_array( $section[ $field_key ] ) ? array_values( $section[ $field_key ] ) : array();
		if ( empty( $items ) ) {
			continue;
		}

		$output .= '<div class="leadwerk-structured-repeater leadwerk-structured-repeater--' . esc_attr( sanitize_html_class( $field_key ) ) . '">';
		$output .= '<div class="leadwerk-structured-grid leadwerk-structured-grid--cards">';
		foreach ( $items as $item ) {
			$output .= leadwerk_theme_render_structured_card( is_array( $item ) ? $item : array(), (string) $field_key );
		}
		$output .= '</div></div>';
	}

	return $output;
}

/**
 * Render one generic repeater card.
 *
 * @param array<string,mixed> $item      Repeater item.
 * @param string              $field_key Parent field key.
 * @return string
 */
function leadwerk_theme_render_structured_card( $item, $field_key ) {
	if ( ! empty( $item['is_empty'] ) ) {
		return '<div class="leadwerk-structured-card leadwerk-structured-card--empty" aria-hidden="true"></div>';
	}

	$title      = leadwerk_theme_pick_first_value( $item, array( 'title', 'card_title', 'question', 'label' ) );
	$image_id   = (int) leadwerk_theme_pick_first_value( $item, array( 'image', 'icon' ), 0 );
	$image_alt  = (string) leadwerk_theme_pick_first_value( $item, array( 'image_alt', 'icon_alt' ), '' );
	$value_text = leadwerk_theme_pick_first_value( $item, array( 'value', 'number', 'icon_text' ) );
	$copy_keys  = array( 'intro', 'body', 'content', 'text', 'quote', 'description', 'answer', 'result', 'role' );
	$anchor_id  = isset( $item['anchor_id'] ) ? sanitize_title( (string) $item['anchor_id'] ) : '';

	ob_start();
	?>
	<article class="leadwerk-structured-card leadwerk-structured-card--<?php echo esc_attr( sanitize_html_class( $field_key ) ); ?>"<?php echo '' !== $anchor_id ? ' id="' . esc_attr( $anchor_id ) . '"' : ''; ?>>
		<?php if ( $image_id ) : ?>
			<?php echo leadwerk_theme_render_structured_image( $image_id, $image_alt, 'leadwerk-structured-card__image' ); ?>
		<?php endif; ?>

		<?php if ( '' !== trim( (string) $value_text ) ) : ?>
			<div class="leadwerk-structured-card__eyebrow"><?php echo esc_html( (string) $value_text ); ?></div>
		<?php endif; ?>

		<?php echo leadwerk_theme_render_main_title( (string) $title, 'leadwerk-structured-card__title leadwerk-structured-card__title--small', 'h3' ); ?>

		<?php foreach ( $copy_keys as $copy_key ) : ?>
			<?php
			if ( ! isset( $item[ $copy_key ] ) || '' === trim( (string) $item[ $copy_key ] ) ) {
				continue;
			}
			echo leadwerk_theme_render_html_block( (string) $item[ $copy_key ], 'leadwerk-structured-copy leadwerk-structured-copy--small' );
			?>
		<?php endforeach; ?>

		<?php
		foreach ( $item as $sub_key => $sub_value ) {
			if ( ! is_array( $sub_value ) || empty( $sub_value ) ) {
				continue;
			}

			echo '<div class="leadwerk-structured-sublist leadwerk-structured-sublist--' . esc_attr( sanitize_html_class( $sub_key ) ) . '">';
			foreach ( $sub_value as $sub_item ) {
				if ( is_array( $sub_item ) ) {
					echo leadwerk_theme_render_structured_card( $sub_item, $sub_key );
				}
			}
			echo '</div>';
		}
		?>

		<?php
			$href = isset( $item['href'] ) ? (string) $item['href'] : '';
			if ( '' !== trim( $href ) && ! empty( $item['value'] ) ) {
				echo '<p><a class="leadwerk-structured-link leadwerk-structured-link--inline" href="' . esc_url( $href ) . '">' . esc_html( leadwerk_theme_get_string( 'structured_open_link_label', 'Open link' ) ) . '</a></p>';
			}
		echo leadwerk_theme_render_structured_buttons( $item );
		?>
	</article>
	<?php

	return ob_get_clean();
}

/**
 * Render a main title.
 *
 * @param string $title     HTML title.
 * @param string $class     CSS class.
 * @param string $tag       Heading tag.
 * @return string
 */
function leadwerk_theme_render_main_title( $title, $class = 'leadwerk-structured-title', $tag = 'h2' ) {
	if ( '' === trim( wp_strip_all_tags( $title ) ) ) {
		return '';
	}

	$tag = in_array( $tag, array( 'h1', 'h2', 'h3', 'h4' ), true ) ? $tag : 'h2';
	$title = preg_replace( '#^<p[^>]*>|</p>$#i', '', trim( (string) $title ) );
	$title = str_replace( array( '<p>', '</p>' ), '', $title );

	return '<' . $tag . ' class="' . esc_attr( $class ) . '">' . wp_kses(
		$title,
		array(
			'br'     => array(),
			'strong' => array(),
			'em'     => array(),
			'span'   => array( 'class' => true ),
		)
	) . '</' . $tag . '>';
}

/**
 * Render an HTML block if populated.
 *
 * @param string $html  Content.
 * @param string $class CSS class.
 * @return string
 */
function leadwerk_theme_render_html_block( $html, $class = 'leadwerk-structured-copy' ) {
	if ( '' === trim( wp_strip_all_tags( $html ) ) ) {
		return '';
	}

	$content = (string) $html;
	if ( $content === wp_strip_all_tags( $content ) ) {
		$content = nl2br( esc_html( $content ) );
	} else {
		$content = wp_kses_post( $content );
	}

	return '<div class="' . esc_attr( $class ) . '">' . $content . '</div>';
}

/**
 * Render an image.
 *
 * @param int    $image_id Attachment ID.
 * @param string $alt      Alt text.
 * @param string $class    CSS class.
 * @return string
 */
function leadwerk_theme_render_structured_image( $image_id, $alt = '', $class = 'leadwerk-structured-image' ) {
	if ( ! $image_id ) {
		return '';
	}

	$image = wp_get_attachment_image( $image_id, 'full', false, array(
		'class' => $class,
		'alt'   => $alt,
	) );

	return $image ? $image : '';
}

/**
 * Render button groups discovered in a section/item array.
 *
 * @param array<string,mixed> $values Values.
 * @return string
 */
function leadwerk_theme_render_structured_buttons( $values ) {
	$prefixes = array();
	foreach ( (array) $values as $key => $value ) {
		if ( ! is_string( $key ) || ! preg_match( '/^([a-z0-9_]+)_label$/', $key, $matches ) ) {
			continue;
		}

		$prefixes[] = $matches[1];
	}

	if ( empty( $prefixes ) ) {
		return '';
	}

	$output = '<div class="leadwerk-structured-buttons">';
	foreach ( array_unique( $prefixes ) as $prefix ) {
		$output .= leadwerk_theme_render_button_markup(
			array(
				'label'    => (string) ( $values[ $prefix . '_label' ] ?? '' ),
				'page_key' => (string) ( $values[ $prefix . '_page_key' ] ?? '' ),
				'url'      => (string) ( $values[ $prefix . '_url' ] ?? '' ),
			),
			'leadwerk-structured-button'
		);
	}
	$output .= '</div>';

	return $output;
}

/**
 * Render one button or link.
 *
 * @param array<string,string> $button      Button data.
 * @param string               $class       CSS class.
 * @param bool                 $fallback_to_title Whether label can fall back to title text.
 * @return string
 */
function leadwerk_theme_render_button_markup( $button, $class = 'leadwerk-structured-button', $fallback_to_title = false ) {
	$label    = trim( (string) ( $button['label'] ?? '' ) );
	$page_key = trim( (string) ( $button['page_key'] ?? '' ) );
	$url      = trim( (string) ( $button['url'] ?? '' ) );

	if ( '' === $label && ! $fallback_to_title ) {
		return '';
	}

	$href = $page_key ? leadwerk_theme_get_page_url( $page_key, leadwerk_theme_get_current_lang(), $url ?: '#' ) : ( $url ?: '#' );
	if ( '' === $label ) {
		$label = esc_html__( 'Open', 'leadwerk-theme' );
	}

	return '<a class="' . esc_attr( $class ) . '" href="' . esc_url( $href ) . '">' . esc_html( $label ) . '</a>';
}

/**
 * Pick the first non-empty value from an array.
 *
 * @param array<string,mixed> $values  Values.
 * @param array<int,string>   $keys    Candidate keys.
 * @param mixed               $default Fallback.
 * @return mixed
 */
function leadwerk_theme_pick_first_value( $values, $keys, $default = '' ) {
	foreach ( $keys as $key ) {
		if ( ! array_key_exists( $key, $values ) ) {
			continue;
		}

		$value = $values[ $key ];

		if ( is_int( $value ) || ( is_numeric( $value ) && $default === 0 ) ) {
			if ( 0 !== (int) $value ) {
				return $value;
			}
			continue;
		}

		if ( is_string( $value ) && '' !== trim( $value ) ) {
			return $value;
		}
	}

	return $default;
}

/**
 * Whether one section has any visible content.
 *
 * @param array<string,mixed> $section Section data.
 * @return bool
 */
function leadwerk_theme_structured_section_has_visible_content( $section ) {
	foreach ( $section as $key => $value ) {
		if ( 'acf_fc_layout' === $key ) {
			continue;
		}

		if ( is_numeric( $value ) && 0 !== (int) $value ) {
			return true;
		}

		if ( is_string( $value ) && '' !== trim( wp_strip_all_tags( $value ) ) ) {
			return true;
		}

		if ( is_array( $value ) && ! empty( $value ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Render an admin-only missing content notice.
 *
 * @param array<string,mixed> $group   Group schema.
 * @param int                 $post_id Post ID.
 * @return string
 */
function leadwerk_theme_render_missing_content_notice( $group, $post_id = 0 ) {
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return '';
	}

	return '<section class="leadwerk-structured-section"><div class="leadwerk-structured-container"><div class="leadwerk-structured-empty">Leadwerk content is empty for "' . esc_html( $group['label'] ?? 'page' ) . '". Run the importer or fill the Leadwerk Fields metabox.</div></div></section>';
}

/**
 * Inline styles for the generic structured renderer.
 *
 * @return string
 */
function leadwerk_theme_get_structured_inline_styles() {
	return '
.leadwerk-structured-section{padding:72px 0;position:relative}
.leadwerk-structured-container{width:min(1180px,calc(100% - 40px));margin:0 auto}
.leadwerk-structured-shell{display:grid;gap:28px}
.leadwerk-structured-shell--two-column{grid-template-columns:minmax(0,1.2fr) minmax(280px,.8fr);align-items:start}
.leadwerk-structured-title{font-size:clamp(2rem,4vw,3.4rem);line-height:1.05;margin:0 0 16px;color:#0f172a}
.leadwerk-structured-subtitle{font-size:1.35rem;line-height:1.2;margin:20px 0 12px;color:#0f172a}
.leadwerk-structured-copy{font-size:1rem;line-height:1.75;color:#334155}
.leadwerk-structured-copy--small{font-size:.95rem;line-height:1.65}
.leadwerk-structured-copy p{margin:0 0 14px}
.leadwerk-structured-copy p:last-child{margin-bottom:0}
.leadwerk-structured-image,.leadwerk-structured-card__image,.leadwerk-hero-slide-card__image{display:block;width:100%;height:auto;border-radius:22px}
.leadwerk-structured-grid{display:grid;gap:18px}
.leadwerk-structured-grid--cards{grid-template-columns:repeat(auto-fit,minmax(220px,1fr));margin-top:22px}
.leadwerk-structured-grid--compact{margin-top:32px}
.leadwerk-structured-card{background:#fff;border:1px solid rgba(15,23,42,.08);border-radius:24px;padding:22px;box-shadow:0 20px 45px rgba(15,23,42,.06)}
.leadwerk-structured-card--empty{background:rgba(148,163,184,.12);border-style:dashed;min-height:160px}
.leadwerk-structured-card__title{margin:12px 0 10px;color:#0f172a;line-height:1.2}
.leadwerk-structured-card__title--small{font-size:1.25rem}
.leadwerk-structured-card__eyebrow{font-size:.82rem;letter-spacing:.08em;text-transform:uppercase;color:#0f766e;font-weight:700}
.leadwerk-structured-buttons{display:flex;gap:12px;flex-wrap:wrap;margin-top:24px}
.leadwerk-structured-button,.leadwerk-structured-link{display:inline-flex;align-items:center;justify-content:center;padding:12px 18px;border-radius:999px;background:#0f766e;color:#fff;text-decoration:none;font-weight:600;transition:transform .2s ease,opacity .2s ease}
.leadwerk-structured-link--inline{padding:0;background:none;color:#0f766e;border-radius:0}
.leadwerk-structured-button:hover,.leadwerk-structured-link:hover{opacity:.92;transform:translateY(-1px)}
.leadwerk-structured-repeater{margin-top:22px}
.leadwerk-structured-sublist{display:grid;gap:14px;margin-top:14px}
.leadwerk-hero-slider-grid{display:grid;gap:24px}
.leadwerk-hero-slide-card{display:grid;gap:20px;grid-template-columns:minmax(0,.95fr) minmax(0,1.05fr);padding:24px;background:linear-gradient(145deg,#f8fafc,#ffffff);border:1px solid rgba(15,23,42,.08);border-radius:28px;box-shadow:0 24px 48px rgba(15,23,42,.08)}
.leadwerk-hero-slide-card__title{font-size:clamp(2rem,3.4vw,3.2rem);line-height:1.02;margin:0 0 14px;color:#0f172a}
.leadwerk-hero-slide-card__subtitle{margin-bottom:20px}
.leadwerk-structured-section--hero-slider{padding-top:36px}
.leadwerk-structured-section--contact .leadwerk-structured-card--form{position:sticky;top:110px}
.leadwerk-contact-privacy{margin-top:18px}
.leadwerk-structured-empty{padding:20px 22px;border-radius:18px;background:#fff7ed;border:1px solid #fdba74;color:#9a3412}
@media (max-width: 900px){
	.leadwerk-structured-shell--two-column,.leadwerk-hero-slide-card{grid-template-columns:1fr}
	.leadwerk-structured-section{padding:56px 0}
}
';
}
