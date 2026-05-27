<?php
/**
 * Shop Filters — on-sale / in-stock flag facet template.
 *
 * Renders the boolean flags (On sale, In stock) as checkboxes carrying the
 * `freeman-sf__flag` class + a `data-freeman-sf-flag` param name, so the reload
 * controller serialises them to the top-level `onsale=1` / `in_stock=1` params
 * (NOT the filter_ prefix — Url_State parses these as flags, not taxonomies).
 * Counts come from wc_product_meta_lookup (see Query_Builder). Expects:
 *   - array $flags : ['onsale'=>['count','selected'], 'in_stock'=>[…]] (keys present only when shown).
 *
 * @package FreemanCore
 */

defined( 'ABSPATH' ) || exit;

use Freeman\Core\Modules\ShopFilters\Labels;

/** @var array $flags */
$sf_flags = isset( $flags ) && is_array( $flags ) ? $flags : array();
if ( empty( $sf_flags ) ) {
	return;
}

$sf_flag_labels = array(
	'onsale'   => Labels::get( 'onsale' ),
	'in_stock' => Labels::get( 'in_stock' ),
);
?>
<fieldset class="freeman-sf__facet freeman-sf__facet--flags" data-freeman-sf-facet="flags">
	<legend class="freeman-sf__facet-title"><?php echo esc_html( Labels::get( 'flags_heading' ) ); ?></legend>
	<ul class="freeman-sf__terms">
		<?php foreach ( $sf_flags as $sf_key => $sf_flag ) : ?>
			<li class="freeman-sf__term">
				<label>
					<input
						type="checkbox"
						class="freeman-sf__checkbox freeman-sf__flag"
						data-freeman-sf-flag="<?php echo esc_attr( $sf_key ); ?>"
						value="1"
						<?php checked( ! empty( $sf_flag['selected'] ) ); ?>
					/>
					<span class="freeman-sf__term-label"><?php echo esc_html( $sf_flag_labels[ $sf_key ] ); ?></span>
					<span class="freeman-sf__term-count">(<?php echo esc_html( (string) (int) $sf_flag['count'] ); ?>)</span>
				</label>
			</li>
		<?php endforeach; ?>
	</ul>
</fieldset>
