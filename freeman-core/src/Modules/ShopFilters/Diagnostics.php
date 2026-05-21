<?php
/**
 * Shop Filters index diagnostic — a read-only table on the Freeman → Shop
 * Filters admin page that shows, per attribute term, the slug, name and indexed
 * product / in-stock counts straight from the index.
 *
 * It exists to debug the common storefront-data problem behind "a facet value
 * shows a count but the filtered URL returns nothing" or "picking size 5 shows
 * size S": scrambled term name↔slug pairs, terms present in the index that no
 * longer resolve, or values whose only products are out of stock. Filters match
 * by SLUG, so the slug column — not the label — is what the URL uses.
 *
 * Read-only: no writes, no storefront effect. Gated by the indexer flag (it
 * inspects what the indexer built) and rendered alongside the reindex tool.
 *
 * shape_rows() is pure and unit-tested; the render echoes and is live-QA.
 *
 * @package FreemanCore
 */

namespace Freeman\Core\Modules\ShopFilters;

defined( 'ABSPATH' ) || exit;

/**
 * Index diagnostic.
 */
final class Diagnostics {

	/**
	 * Index storage.
	 *
	 * @var Index_Repository
	 */
	private $repo;

	/**
	 * Constructor.
	 *
	 * @param Index_Repository|null $repo Repository (injected for tests).
	 */
	public function __construct( Index_Repository $repo = null ) {
		$this->repo = $repo ? $repo : new Index_Repository();
	}

	/**
	 * Hook the renderer onto the module page, below the reindex tool.
	 */
	public function boot() {
		add_action( 'freeman_core/module_page/shop_filters', array( $this, 'render' ), 20 );
	}

	/**
	 * Merge raw index stats with resolved term name/slug, flagging terms that
	 * no longer resolve (an orphaned index row — a data red flag). Sorted by
	 * taxonomy, then term name, then id. Pure.
	 *
	 * @param array $stats       Rows from Index_Repository::term_stats().
	 * @param array $term_lookup taxonomy => (term_id => ['slug','name']).
	 * @return array<int,array<string,mixed>>
	 */
	public static function shape_rows( array $stats, array $term_lookup ) {
		$rows = array();
		foreach ( $stats as $stat ) {
			$taxonomy = (string) ( $stat['taxonomy'] ?? '' );
			$term_id  = (int) ( $stat['term_id'] ?? 0 );
			$info     = $term_lookup[ $taxonomy ][ $term_id ] ?? null;
			$resolved = is_array( $info );

			$rows[] = array(
				'taxonomy' => $taxonomy,
				'term_id'  => $term_id,
				'name'     => $resolved ? (string) ( $info['name'] ?? '' ) : '',
				'slug'     => $resolved ? (string) ( $info['slug'] ?? '' ) : '',
				'products' => (int) ( $stat['products'] ?? 0 ),
				'in_stock' => (int) ( $stat['in_stock'] ?? 0 ),
				'resolved' => $resolved,
			);
		}

		usort(
			$rows,
			static function ( $a, $b ) {
				return strcmp( $a['taxonomy'], $b['taxonomy'] )
					?: ( strcmp( $a['name'], $b['name'] )
					?: ( $a['term_id'] <=> $b['term_id'] ) );
			}
		);

		return $rows;
	}

	/**
	 * Render the diagnostic table.
	 */
	public function render() {
		$stats = $this->repo->term_stats();
		if ( empty( $stats ) ) {
			return;
		}

		$lookup = array();
		foreach ( $stats as $stat ) {
			$taxonomy = (string) $stat['taxonomy'];
			$term_id  = (int) $stat['term_id'];
			$term     = get_term( $term_id, $taxonomy );
			if ( $term && ! is_wp_error( $term ) ) {
				$lookup[ $taxonomy ][ $term_id ] = array(
					'slug' => (string) $term->slug,
					'name' => (string) $term->name,
				);
			}
		}

		$rows = self::shape_rows( $stats, $lookup );
		?>
		<h2><?php esc_html_e( 'Index diagnostic', 'freeman-core' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'What the index actually holds, per term. Filters match by SLUG — if a value labelled one size returns another, or a URL returns no products, look for a name that disagrees with its slug, an "in stock" of 0, or an "unresolved" term below.', 'freeman-core' ); ?>
		</p>
		<table class="widefat striped" style="max-width:840px;">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Taxonomy', 'freeman-core' ); ?></th>
					<th><?php esc_html_e( 'Name', 'freeman-core' ); ?></th>
					<th><?php esc_html_e( 'Slug (used by filters)', 'freeman-core' ); ?></th>
					<th><?php esc_html_e( 'Term ID', 'freeman-core' ); ?></th>
					<th><?php esc_html_e( 'Products', 'freeman-core' ); ?></th>
					<th><?php esc_html_e( 'In stock', 'freeman-core' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $rows as $row ) : ?>
					<tr<?php echo $row['resolved'] ? '' : ' style="background:#fcf0f1;"'; ?>>
						<td><code><?php echo esc_html( $row['taxonomy'] ); ?></code></td>
						<td>
							<?php
							echo $row['resolved']
								? esc_html( $row['name'] )
								: '<em>' . esc_html__( 'unresolved — not a live term', 'freeman-core' ) . '</em>';
							?>
						</td>
						<td><code><?php echo esc_html( $row['slug'] ); ?></code></td>
						<td><?php echo esc_html( (string) $row['term_id'] ); ?></td>
						<td><?php echo esc_html( (string) $row['products'] ); ?></td>
						<td><?php echo esc_html( (string) $row['in_stock'] ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}
}
