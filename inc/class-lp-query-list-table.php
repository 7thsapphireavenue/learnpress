<?php

/**
 * Class LP_Query_List_Table
 */
class LP_Query_List_Table implements ArrayAccess {
	/**
	 * @var array|null
	 */
	protected $_data = null;

	/**
	 * LP_Query_List_Table constructor.
	 *
	 * @param $data
	 */
	public function __construct( $data ) {

		$this->_data = wp_parse_args(
			$data, array(
				'pages'  => 0,
				'total'  => 0,
				'items'  => null,
				'paged'  => 1,
				'limit'  => 10,
				'single' => 'item',
				'plural' => 'items'
			)
		);

		global $wp;
		if ( ! empty( $wp->query_vars['view_id'] ) ) {
			$this->_data['paged'] = absint( $wp->query_vars['view_id'] );
		}

		$this->_init();
	}

	/**
	 *
	 */
	protected function _init() {

	}

	/**
	 * @return int
	 */
	public function get_pages() {
		return absint( $this->_data['pages'] );
	}

	/**
	 * @return int
	 */
	public function get_total() {
		return absint( $this->_data['total'] );
	}

	/**
	 * @return array
	 */
	public function get_items() {
		return $this->_data['items'];
	}

	/**
	 * @return int
	 */
	public function get_paged() {
		return absint( $this->_data['paged'] );
	}

	/**
	 * @return int
	 */
	public function get_limit() {
		return absint( $this->_data['limit'] );
	}

	/**
	 * Pagination
	 *
	 * @param bool $echo
	 *
	 * @return string
	 */
	public function get_nav( $echo = true ) {
		return learn_press_paging_nav(
			array(
				'num_pages' => $this->get_pages(),
				'paged'     => $this->get_paged(),
				'echo'      => $echo,
				'format'    => '%#%/',
				'base'      => trailingslashit( preg_replace( '~\/[0-9]+\/?$~', '', learn_press_get_current_url() ) )
			)
		);
	}

	public function get_offset() {
		$from = ( $this->get_paged() - 1 ) * $this->get_limit() + 1;
		$to   = $from + $this->get_limit() - 1;
		$to   = min( $to, $this->get_total() );

		return array( $from, $to );
	}

	public function get_offset_text( $format = '' ) {
		$offset = $this->get_offset();
		if ( ! $format ) {
			if ( $this->_data['single'] && $this->_data['plural'] ) {
				$format = __( 'Displaying {{from}} to {{to}} of {{total}} {{item_name}}.', 'learnpress' );
			} else {
				$format = __( 'Displaying {{from}} to {{to}} of {{total}}.', 'learnpress' );
			}
		}

		return str_replace(
			array( '{{from}}', '{{to}}', '{{total}}', '{{item_name}}' ),
			array(
				$offset[0],
				$offset[1],
				$this->get_total(),
				$this->get_total() < 2 ? $this->_data['single'] : $this->_data['plural']
			),
			$format
		);
	}

	public function offsetExists( $offset ) {
		return array_key_exists( $offset, $this->_data );
	}

	public function offsetGet( $offset ) {
		return array_key_exists( $offset, $this->_data ) ? $this->_data[ $offset ] : false;
	}

	public function offsetSet( $offset, $value ) {
		$this->_data[ $offset ] = $value;
	}

	public function offsetUnset( $offset ) {
		return false;
	}
}