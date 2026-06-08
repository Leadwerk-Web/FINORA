<?php
/**
 * 404 template.
 *
 * @package Leadwerk_Theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>
<main>
	<section class="content-section content-section--white legal-content" style="padding:clamp(7rem,12vw,10rem) 20px clamp(4rem,8vw,7rem);background:linear-gradient(135deg,#f8f5ef 0%,#ffffff 48%,#edf2f4 100%);">
		<div class="container--narrow" style="max-width:1180px;">
			<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:clamp(2rem,5vw,4rem);align-items:center;">
				<div>
					<p class="eyebrow"><?php esc_html_e( '404 / Strategie neu ausrichten', 'leadwerk-theme' ); ?></p>
					<h1 class="legal-title anim" style="font-size:clamp(3.15rem,4.6vw,4.75rem);line-height:1.02;margin-bottom:1.4rem;"><?php esc_html_e( 'Diese Route passt nicht mehr zum Plan.', 'leadwerk-theme' ); ?></h1>
					<div class="legal-body anim" style="animation-delay:100ms">
						<p style="font-size:1.05rem;max-width:35rem;"><?php esc_html_e( 'Die gesuchte Seite ist nicht erreichbar. Von hier aus finden Sie schnell zur Beratung, zur Philosophie oder direkt zur passenden Finanzfrage.', 'leadwerk-theme' ); ?></p>
						<div style="display:flex;flex-wrap:wrap;gap:.85rem;margin:2rem 0;">
							<a class="btn btn-primary" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Zur Startseite', 'leadwerk-theme' ); ?></a>
							<a class="btn" href="<?php echo esc_url( home_url( '/kontakt/' ) ); ?>" style="background:#fff;color:#1f2a2e;box-shadow:0 12px 28px rgba(31,42,46,.10);"><?php esc_html_e( 'Termin anfragen', 'leadwerk-theme' ); ?></a>
						</div>
						<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:.8rem;">
							<a href="<?php echo esc_url( home_url( '/altersvorsorge/' ) ); ?>" style="padding:1rem;background:#fff;color:#1f2a2e;text-decoration:none;box-shadow:0 12px 30px rgba(31,42,46,.08);"><strong><?php esc_html_e( 'Altersvorsorge', 'leadwerk-theme' ); ?></strong><br><span style="opacity:.7;"><?php esc_html_e( 'langfristig planen', 'leadwerk-theme' ); ?></span></a>
							<a href="<?php echo esc_url( home_url( '/investment-beratung/' ) ); ?>" style="padding:1rem;background:#fff;color:#1f2a2e;text-decoration:none;box-shadow:0 12px 30px rgba(31,42,46,.08);"><strong><?php esc_html_e( 'Investment', 'leadwerk-theme' ); ?></strong><br><span style="opacity:.7;"><?php esc_html_e( 'klar strukturieren', 'leadwerk-theme' ); ?></span></a>
							<a href="<?php echo esc_url( home_url( '/finora-philosophie/' ) ); ?>" style="padding:1rem;background:#fff;color:#1f2a2e;text-decoration:none;box-shadow:0 12px 30px rgba(31,42,46,.08);"><strong><?php esc_html_e( 'Philosophie', 'leadwerk-theme' ); ?></strong><br><span style="opacity:.7;"><?php esc_html_e( 'Finora verstehen', 'leadwerk-theme' ); ?></span></a>
						</div>
					</div>
				</div>
				<figure style="margin:0;position:relative;min-height:25rem;overflow:hidden;box-shadow:0 28px 70px rgba(31,42,46,.16);background:#1f2a2e;">
					<img src="<?php echo esc_url( LEADWERK_THEME_URI . '/assets/images/Finora-Beratung.jpg' ); ?>" alt="<?php esc_attr_e( 'Finora Beratungssituation', 'leadwerk-theme' ); ?>" style="width:100%;height:100%;min-height:25rem;object-fit:cover;display:block;filter:saturate(.82) contrast(.95);">
					<div aria-hidden="true" style="position:absolute;left:1rem;bottom:.5rem;font-size:clamp(5rem,16vw,11rem);font-weight:700;color:rgba(255,255,255,.42);line-height:.8;">404</div>
					<div style="position:absolute;right:1rem;top:1rem;background:rgba(255,255,255,.9);padding:.85rem 1rem;color:#1f2a2e;box-shadow:0 12px 30px rgba(0,0,0,.08);"><?php esc_html_e( 'Plan pruefen. Fokus behalten.', 'leadwerk-theme' ); ?></div>
				</figure>
			</div>
		</div>
	</section>
</main>
<?php
get_footer();
