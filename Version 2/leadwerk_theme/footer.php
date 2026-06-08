<?php
/**
 * Theme footer.
 *
 * @package Leadwerk_Theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<?php
if ( function_exists( 'leadwerk_theme_render_footer_block' ) ) {
	echo leadwerk_theme_render_footer_block();
}

wp_footer();
?>
</body>
</html>
