<?php
/**
 * Class LP_Abstract_Course.
 *
 * @author  ThimPress
 * @package LearnPress/Classes
 * @version 3.0.0
 */

/**
 * Prevent loading this file directly
 */
defined( 'ABSPATH' ) || exit();

if ( ! function_exists( 'LP_Abstract_Course' ) ) {

	/**
	 * Class LP_Abstract_Course.
	 */
	abstract class LP_Abstract_Course extends LP_Abstract_Post_Data {
		/**
		 *
		 * @var string
		 */
		public $course_type = null;


		/**
		 * @var null
		 */
		protected $_count_users = null;

		/**
		 * @var null
		 */
		protected $_students_list = null;

		/**
		 * Course item is viewing in single course.
		 *
		 * @var LP_Course_Item
		 */
		protected $_viewing_item = null;

		/**
		 * @var LP_Course_CURD|null
		 */
		protected $_curd = null;

		/**
		 * Post type
		 *
		 * @var string
		 */
		protected $_post_type = LP_COURSE_CPT;

		/**
		 * @var array
		 */
		protected static $_lessons = array();

		/**
		 * @var array
		 */
		protected $_data = array(
			'status'               => '',
			'require_enrollment'   => '',
			'price'                => '',
			'sale_price'           => '',
			'sale_start'           => '',
			'sale_end'             => '',
			'duration'             => 0,
			'max_students'         => 0,
			'students'             => 0,
			'retake_count'         => 0,
			'featured'             => '',
			'block_lesson_content' => '',
			'course_result'        => '',
			'passing_conditional'  => '',
			'external_link'        => '',
			'payment'              => ''
		);

		/**
		 * Constructor gets the post object and sets the ID for the loaded course.
		 *
		 * @param mixed $the_course Course ID, post object, or course object
		 * @param mixed $deprecated Deprecated
		 */
		public function __construct( $the_course, $deprecated = '' ) {

			$this->_curd = new LP_Course_CURD();

			if ( is_numeric( $the_course ) && $the_course > 0 ) {
				$this->set_id( $the_course );
			} elseif ( $the_course instanceof self ) {
				$this->set_id( absint( $the_course->get_id() ) );
			} elseif ( ! empty( $the_course->ID ) ) {
				$this->set_id( absint( $the_course->ID ) );
			}

			if ( $this->get_id() > 0 ) {
				$this->load();
			}
		}

		/**
		 * Read course data.
		 * - Curriculum: sections, items, etc...
		 */
		public function load() {
			$this->_curd->load( $this );
			$id          = $this->get_id();
			$post_object = get_post( $id );
			$this->_set_data(
				array(
					'status'               => $post_object->post_status,
					'required_enroll'      => get_post_meta( $id, '_lp_required_enroll', true ),
					'price'                => get_post_meta( $id, '_lp_price', true ),
					'sale_price'           => get_post_meta( $id, '_lp_sale_price', true ),
					'sale_start'           => get_post_meta( $id, '_lp_sale_start', true ),
					'sale_end'             => get_post_meta( $id, '_lp_sale_end', true ),
					'duration'             => get_post_meta( $id, '_lp_duration', true ),
					'max_students'         => get_post_meta( $id, '_lp_max_students', true ),
					'students'             => false,
					'fake_students'        => get_post_meta( $id, '_lp_students', true ),
					'retake_count'         => get_post_meta( $id, '_lp_retake_count', true ),
					'featured'             => get_post_meta( $id, '_lp_featured', true ),
					'block_lesson_content' => get_post_meta( $id, '_lp_block_lesson_content', true ),
					'course_result'        => get_post_meta( $id, '_lp_course_result', true ),
					'passing_condition'    => get_post_meta( $id, '_lp_passing_condition', true ),
					'payment'              => get_post_meta( $id, '_lp_payment', true ),
					'final_quiz'           => get_post_meta( $id, '_lp_final_quiz', true ),
					'external_link'        => get_post_meta( $id, '_lp_external_link_buy_course', true ),
					'external_link_text'   => get_post_meta( $id, '_lp_external_link_text', true ),
				)
			);
		}

		/**
		 * __isset function.
		 *
		 * @param mixed $key
		 *
		 * @return bool
		 */
		public function __isset( $key ) {
			return metadata_exists( 'post', $this->get_id(), '_' . $key );
		}

		/**
		 * __get function.
		 *
		 * @param string $key
		 *
		 * @return mixed
		 */
		public function __get( $key ) {
			if ( strcasecmp( $key, 'ID' ) == 0 ) {
				$key = strtolower( $key );
			}
			if ( $key == 'id' ) {
				_deprecated_argument( __CLASS__ . '::id', '3.0.0' );
			}
			if ( empty( $this->{$key} ) ) {
				$value = false;
				switch ( $key ) {
					case 'current_item':
						if ( ! empty( LP()->global['course-item'] ) ) {
							$value = LP()->global['course-item'];
						}

						break;
					case 'current_lesson':
						$lesson_id = ( ( $lesson_id = learn_press_get_request( "lesson_id" ) ) && $this->has( 'item', $lesson_id ) ) ? $lesson_id : null;
						if ( $lesson_id ) {
							$value = LP_Lesson::get_lesson( $lesson_id );
						}
						break;
					case 'permalink':
						$value = get_the_permalink( $this->get_id() );
						break;
					case 'duration':
						$value         = get_post_meta( $this->get_id(), '_lp_' . $key, true );
						$duration      = learn_press_get_course_duration_support();
						$duration_keys = array_keys( $duration );
						if ( ! preg_match_all( '!([0-9]+)\s(' . join( '|', $duration_keys ) . ')!', $value, $matches ) ) {
							$a1    = absint( $value );
							$a2    = end( $duration_keys );
							$value = $a1 . ' ' . $a2;
							update_post_meta( $this->get_id(), '_lp_' . $key, $value );
						}
						break;
					default: // default is get course meta key
						if ( func_num_args() > 1 ) {
							$single = func_get_arg( 1 );
							if ( $single !== false && $single !== true ) {
								$single = true;
							}
						} else {
							$single = true;
						}
						$value = get_post_meta( $this->get_id(), '_lp_' . $key, $single );
						if ( ( $key == 'price' || $key == 'total' ) && get_post_meta( $this->get_id(), '_lp_payment', true ) != 'yes' ) {
							$value = 0;
						}

				}
				if ( ! empty( $value ) ) {
					$this->$key = $value;
				}
			}

			return ! empty( $this->$key ) ? $this->$key : null;
		}

		/**
		 * Get course thumbnail, return placeholder if it does not exists
		 *
		 * @param string $size
		 * @param array  $attr
		 *
		 * @return string
		 */
		public function get_image( $size = 'course_thumbnail', $attr = array() ) {
			$attr  = wp_parse_args(
				$attr,
				array(
					'alt' => $this->get_title()
				)
			);
			$image = false;
			if ( has_post_thumbnail( $this->get_id() ) ) {
				$image = get_the_post_thumbnail( $this->get_id(), $size, $attr );
			} elseif ( ( $parent_id = wp_get_post_parent_id( $this->get_id() ) ) && has_post_thumbnail( $parent_id ) ) {
				$image = get_the_post_thumbnail( $parent_id, $size, $attr );
			}
			if ( ! $image ) {
				if ( 'course_thumbnail' == $size ) {
					$image = LP()->image( 'no-image.png' );//'placeholder-400x250' );
				} else {
					$image = LP()->image( 'placeholder-800x450' );
				}
				$image = sprintf( '<img src="%s" %s />', $image, '' );
			}

			$image = apply_filters( 'learn_press_course_image', $image, $this->get_id(), $size, $attr );

			return apply_filters( 'learn-press/course/image', $image, $this->get_id(), $size, $attr );
		}

		/**
		 * @return false|string
		 */
		public function get_permalink() {
			return get_the_permalink( $this->get_id() );
		}

		/**
		 * @return bool
		 */
		public function is_visible() {
			return true;
		}

		/**
		 * @param string $field
		 *
		 * @return bool|int
		 */
		public function get_request_item( $field = 'id' ) {
			$return = LP()->global['course-item'];
			if ( ! empty( $_REQUEST['course-item'] ) ) {
				$type = $_REQUEST['course-item'];
				if ( $field == 'type' ) {
					$return = $type;
				} elseif ( $field == 'id' ) {
					$return = ! empty( $_REQUEST[ $type . '_id' ] ) ? $_REQUEST[ $type . '_id' ] : 0;
				} elseif ( $field == 'name' ) {
					$return = ! empty( $_REQUEST[ $type ] ) ? $_REQUEST[ $type ] : false;
				}
			}

			return $return;
		}

		/**
		 * Get the course's post data.
		 *
		 * @return object
		 */
		public function get_course_data() {
			return $this->post;
		}

		/**
		 * Course is exists if the post is not empty
		 *
		 * @return bool
		 */
		public function exists() {
			return LP_COURSE_CPT === get_post_type( $this->get_id() );
		}

		/**
		 * @return bool
		 */
		public function is_publish() {
			return 'publish' === get_post_status( $this->get_id() );
		}

		/**
		 * Check if this course is required enroll or not.
		 *
		 * @param mixed
		 *
		 * @return bool
		 */
		public function is_required_enroll() {
			$return = $this->get_data( 'required_enroll' ) == 'yes';
			// @deprecated
			$return = apply_filters( 'learn_press_course_required_enroll', $return, $this );

			return apply_filters( 'learn-press/course-require-enrollment', $return, $this->get_id() );
		}

		/**
		 * @deprecated
		 *
		 * @return mixed
		 */
		public function is_require_enrollment() {
			return $this->is_required_enroll();
		}

		/**
		 * @deprecated
		 */
		public function get_description() {
			_deprecated_function( __FUNCTION__, '3.0.0', 'LP_Course::get_content' );

			return $this->get_content();
		}

		/**
		 * Get all curriculum of this course.
		 *
		 * @param int  $section_id
		 * @param bool $force
		 *
		 * @return bool|LP_Course_Section
		 */
		public function get_curriculum( $section_id = 0, $force = false ) {
			LP_Debug::log_function( __CLASS__ . '::' . __FUNCTION__ );

			if ( ! $this->get_id() ) {
				return false;
			}
			$curriculum = $this->_curd->get_curriculum( $this->get_id() );

			$return = false;
			if ( $section_id ) {
				if ( ! empty( $curriculum[ $section_id ] ) ) {
					$return = $curriculum[ $section_id ];
				}
			} else {
				$return = $curriculum;
			}
			LP_Debug::log_function( __CLASS__ . '::' . __FUNCTION__ );

			return apply_filters( 'learn-press/course/curriculum', $return, $this->get_id(), $section_id );
		}

		/**
		 * Return list of item's ids in course's curriculum.
		 *
		 * @since 3.0.0
		 *
		 * @param string|array $type
		 * @param bool         $preview - True for including 'Preview' item
		 *
		 * @return array
		 */
		public function get_items( $type = '', $preview = true ) {

			LP_Debug::log_function( __CLASS__ . '::' . __FUNCTION__ );

			$key = $this->get_id() . '-' . md5( serialize( func_get_args() ) );

			if ( false === ( $items = wp_cache_get( 'course-' . $key, 'lp-course-items' ) ) ) {

				LP_Helper_CURD::update_meta_cache( 'post', $items );

				// get course items from cache
				$items = apply_filters( 'learn-press/course-items', wp_cache_get( 'course-' . $this->get_id(), 'lp-course-items' ) );

				if ( ( $type || ! $preview ) && $items ) {
					$item_types = array();
					if ( $type ) {
						settype( $type, 'array' );
					}

					foreach ( $items as $item_id ) {

						if ( ! $preview ) {
							$item = $this->get_item( $item_id );

							if ( ! $item || $item->is_preview() ) {
								continue;
							}
						}

						if ( ! $type || is_array( $type ) && in_array( get_post_type( $item_id ), $type ) ) {
							$item_types[] = $item_id;
						}
					}

					$items = apply_filters( 'learn-press/course-items-by-type', $item_types, $type, $this->get_id() );

				}

				wp_cache_set( 'course-' . $key, $items, 'lp-course-items' );

			}
			LP_Debug::log_function( __CLASS__ . '::' . __FUNCTION__ );

			return $items;
		}

		/**
		 * Set item is viewing in single course.
		 *
		 * @param LP_Course_Item $item
		 *
		 * @return int
		 */
		public function set_viewing_item( $item ) {
			if ( $this->_viewing_item && $this->_viewing_item->get_id() == $item->get_id() ) {
				return 0;
			}
			$user = learn_press_get_current_user();

			$this->_viewing_item = $item;
			$item->set_course( $this );

			return $user->maybe_update_item( $item->get_id(), $this->get_id() );
		}

		/**
		 * Get item is viewing in single course.
		 *
		 * @return LP_Course_Item
		 */
		public function get_viewing_item() {
			return apply_filters( 'learn-press/single-course-viewing-item', $this->_viewing_item, $this->get_id() );
		}

		/**
		 * Get raw data curriculum.
		 *
		 * @since 3.0.0
		 *
		 * @return array
		 */
		public function get_curriculum_raw() {
			$sections = $this->get_curriculum();

			$sections_data = array();
			if ( is_array( $sections ) ) {
				foreach ( $sections as $section ) {
					$sections_data[] = $section->to_array();
				}
			}

			return $sections_data;
		}

		/**
		 * @return int
		 */
		public function get_fake_students() {
			return $this->get_data( 'fake_students' );
		}

		/**
		 * Count the real users has enrolled
		 *
		 * @return int
		 */
		public function get_users_enrolled() {
			LP_Debug::log_function( __CLASS__ . '::' . __FUNCTION__ );

			$enrolled = $this->get_data( 'students' );

			if ( false === $enrolled ) {
				$enrolled = $this->count_students();

				$this->_set_data( 'students', $enrolled );
			}

			$enrolled = absint( $enrolled );

			// @deprecated
			$enrolled = apply_filters( 'learn_press_count_users_enrolled', $enrolled, $this );
			LP_Debug::log_function( __CLASS__ . '::' . __FUNCTION__ );

			return apply_filters( 'learn-press/course/users-enrolled', $enrolled, $this );
		}

		/**
		 * Output html for students enrolled counter
		 *
		 * @return string
		 */
		public function get_students_html() {
			$output = '';
			if ( $count = $this->get_users_enrolled() ):
				$user = learn_press_get_current_user();
				if ( $user->has_enrolled_course( $this->get_id() ) ):
					if ( $count == 1 ):
						$output .= __( 'You enrolled', 'learnpress' );
					else:
						$output .= sprintf(
							_nx(
								'You and one student enrolled',
								'You and <span class="course-students-number">%1$s</span> students enrolled',
								intval( $count - 1 ),
								'students-html',
								'learnpress'
							)
							, $count - 1
						);
					endif;
				else:
					$output = sprintf( _nx( 'One student enrolled', '<span class="course-students-number">%1$s</span> students enrolled', $count, 'students-html', 'learnpress' ), $count );
				endif;
			else:
				$output = __( 'No student enrolled', 'learnpress' );
			endif;

			return apply_filters( 'learn-press/students-enrolled-html', $output, $this->get_id() );
		}

		/**
		 * @param string $field
		 *
		 * @return LP_User|mixed
		 */
		public function get_instructor( $field = '' ) {
			$user = learn_press_get_user( get_post_field( 'post_author', $this->get_id() ) );

			return $field ? $user->get_data( $field ) : $user;
		}

		/**
		 * @return mixed
		 */
		public function get_instructor_name() {
			$instructor = $this->get_instructor();
			$name       = '';

			if ( $instructor ) {
				if ( $instructor->get_data( 'display_name' ) ) {
					$name = $instructor->get_data( 'display_name' );
				} elseif ( $instructor->get_data( 'user_nicename' ) ) {
					$name = $instructor->get_data( 'user_nicename' );
				} elseif ( $instructor->get_data( 'user_login' ) ) {
					$name = $instructor->get_data( 'user_login' );
				}
			}

			return apply_filters( 'learn-press/course/instructor-name', $name, $this->get_id() );
		}

		/**
		 * @return string
		 */
		public function get_instructor_html() {
			$instructor = $this->get_instructor_name();
			$html       = sprintf(
				'<a href="%s">%s</a>',
				learn_press_user_profile_link( get_post_field( 'post_author', $this->get_id() ) ),
				$instructor
			);

			return apply_filters( 'learn_press_course_instructor_html', $html, get_post_field( 'post_author', $this->get_id() ), $this->get_id() );
		}

		/**
		 * @deprecated
		 *
		 * @param null $user_id
		 *
		 * @return bool|mixed
		 */
		public function get_course_info( $user_id = null ) {
			if ( ! $user_id ) {
				$user_id = get_current_user_id();
			}
			$user = learn_press_get_user( $user_id );

			return $user ? $user->get_course_info( $this->get_id() ) : false;
		}

		/**
		 * Check if a course is FREE or need to pay or enroll
		 *
		 * @return bool
		 */
		public function is_free() {

			// @deprecated
			$is_free = apply_filters( 'learn_press_is_free_course', $this->get_price() == 0, $this );

			return apply_filters( 'learn-press/course-is-free', $is_free, $this->get_id() );
		}

		/**
		 * Get the origin price of course
		 * @return mixed
		 */
		public function get_origin_price() {
			$price = $this->get_data( 'price' );

			return $price;
		}

		/**
		 * Get the sale price of course. Check if sale price is set
		 * and the dates are valid.
		 *
		 * @return mixed
		 */
		public function get_sale_price() {
			return $this->has_sale_price() ? floatval( $this->get_data( 'sale_price' ) ) : false;
		}

		/**
		 * Check if course has 'sale price'
		 *
		 * @return mixed
		 */
		public function has_sale_price() {
			LP_Debug::log_function( __CLASS__ . '::' . __FUNCTION__ );

			// Check has post meta
			$has_sale_price = metadata_exists( 'post', $this->get_id(), '_lp_sale_price' );
			$sale_price     = $this->get_data( 'sale_price' );

			// Ensure sale price is a number
			if ( $has_sale_price ) {
				$has_sale_price = is_numeric( $sale_price );
			}

			// Ensure sale price is greater than 0
			if ( $has_sale_price ) {
				$has_sale_price = ( $sale_price = floatval( $sale_price ) ) >= 0;
			}

			// Ensure the dates are valid
			if ( $has_sale_price ) {
				$start_date = $this->get_data( 'sale_start' );
				$end_date   = $this->get_data( 'sale_end' );
				$now        = current_time( 'timestamp' );
				$end        = strtotime( $end_date );
				$start      = strtotime( $start_date );

				$has_sale_price = ( ( $now >= $start || ! $start_date ) && ( $now <= $end || ! $end_date ) );
			}

			// Ensure sale price is less than origin price
			if ( $has_sale_price ) {
				$has_sale_price = is_numeric( $this->get_data( 'price' ) ) && $sale_price < $this->get_data( 'price' );
			}

			LP_Debug::log_function( __CLASS__ . '::' . __FUNCTION__ );

			return apply_filters( 'learn-press/course-has-sale-price', $has_sale_price, $this->get_id() );
		}

		/**
		 * Get the price of course. If sale price is set and the dates is valid then
		 * return the sale price.
		 *
		 * @return mixed
		 */
		public function get_price() {
			$price = floatval( $this->get_data( 'price' ) );

			// Price is not set
			if ( ! $price /* || 'yes' != $this->get_data('payment') */ ) {
				$price = 0;
			} else {
				if ( false !== ( $sale_price = $this->get_sale_price() ) ) {
					$price = $sale_price;
				}
			}

			// @deprecated
			$price = apply_filters( 'learn_press_course_price', $price, $this );

			return apply_filters( 'learn-press/course-price', $price, $this->get_id() );
		}

		/**
		 * Get the price of course with html
		 *
		 * @return mixed
		 */
		public function get_price_html() {

			if ( $this->is_free() ) {
				$price_html = apply_filters( 'learn_press_course_price_html_free', __( 'Free', 'learnpress' ), $this );
			} else {
				$price      = $this->get_price();
				$price      = learn_press_format_price( $price, true );
				$price_html = apply_filters( 'learn_press_course_price_html', $price, $this );
			}

			return $price_html;
		}


		/**
		 * Get the price of course with html
		 *
		 * @return mixed
		 */
		public function get_origin_price_html() {
			$origin_price_html = '';
			//if ( 'yes' == $this->payment && 0 < $this->price ) {
			if ( $origin_price = $this->get_origin_price() ) {
				$origin_price      = learn_press_format_price( $origin_price, true );
				$origin_price_html = apply_filters( 'learn_press_course_origin_price_html', $origin_price, $this );
			}

			//}
			return $origin_price_html;
		}

		/**
		 * Get all items in a course.
		 *
		 * @deprecated
		 *
		 * @param string $type . Type of items, eg: lp_lesson, lp_quiz...
		 *
		 * @return array
		 */
		public function get_curriculum_items( $type = '' ) {
			return $this->get_items( $type );
		}

		/**
		 * @param bool $item_id
		 *
		 * @return bool|mixed
		 */
		public function is_viewing_item( $item_id = false ) {
			if ( false === ( $item = LP_Global::course_item() ) ) {
				return false;
			}

			return apply_filters( 'learn-press/is-viewing-item', false !== $item_id ? $item_id == $item->get_id() : $item->get_id(), $item_id, $this->get_id() );
		}

		/**
		 * @param $item_id
		 *
		 * @return bool|mixed
		 */
		public function is_current_item( $item_id ) {
			return $this->is_viewing_item( $item_id );
		}

		/**
		 * Check if the course has 'feature'
		 * This function call to a function with prefix 'has'
		 *
		 * @param string
		 *
		 * @return mixed
		 * @throws Exception
		 */
		public function has( $tag ) {
			$args = func_get_args();
			unset( $args[0] );
			$method   = 'has_' . preg_replace( '!-!', '_', $tag );
			$callback = array( $this, $method );
			if ( is_callable( $callback ) ) {
				return call_user_func_array( $callback, $args );
			} else {
				throw new Exception( sprintf( __( 'The function %s doesn\'t exists', 'learnpress' ), $tag ) );
			}
		}

		/**
		 * @param string
		 *
		 * @return mixed
		 * @throws Exception
		 */
		public function is( $tag ) {
			$args = func_get_args();
			unset( $args[0] );
			$method   = 'is_' . preg_replace( '!-!', '_', $tag );
			$callback = array( $this, $method );
			if ( is_callable( $callback ) ) {
				return call_user_func_array( $callback, $args );
			} else {
				throw new Exception( sprintf( __( 'The function %s doesn\'t exists', 'learnpress' ), $tag ) );
			}
		}

		/**
		 * Return true if this course can be purchasable.
		 * - Required enroll
		 * - Status is publish
		 *
		 *
		 * @return mixed
		 */
		public function is_purchasable() {
			$is_purchasable = $this->exists() && $this->is_required_enroll() && get_post_status( $this->get_id() ) == 'publish';

			//var_dump($this->exists() && $this->is_required_enroll() && get_post_status( $this->get_id() ));
			// @deprecated
			$is_purchasable = apply_filters( 'learn_press_item_is_purchasable', $is_purchasable, $this->get_id() );

			return apply_filters( 'learn-press/is-purchasable', $is_purchasable, $this->get_id() );
		}

		/**
		 * Check if students have enrolled course is reached.
		 *
		 * @return mixed
		 */
		public function is_in_stock() {
			$in_stock = true;
			if ( $max_allowed = $this->get_max_students() ) {
				$in_stock = $max_allowed > $this->count_students();
			}

			return apply_filters( 'learn-press/is-in-stock', $in_stock, $this->get_id() );
		}

		/**
		 * Get max students can enroll to course.
		 *
		 * @return int
		 *
		 * @since 3.0.0
		 */
		public function get_max_students() {
			return apply_filters( 'learn-press/max-students', absint( $this->get_data( 'max_students' ) ), $this->get_id() );
		}

		/**
		 * @return mixed
		 */
		public function count_students() {
			$count_in_order = $this->count_in_order( array( 'completed', 'processing' ) );

			return $count_in_order;
		}

		/**
		 * @param string|array $statuses
		 *
		 * @return mixed
		 */
		public function count_in_order( $statuses = 'completed' ) {
			return $this->_curd->count_by_orders( $this->get_id(), $statuses );
		}

		/**
		 * @return bool
		 */
		public function need_payment() {
			return $this->payment == 'yes';
		}

		/**
		 * Check if course contain an item in curriculum.
		 * Actually, find the item in each section inside curriculum.
		 *
		 * @param $item_id
		 *
		 * @return bool
		 */
		public function has_item( $item_id ) {
			$found = false;

			if ( $items = $this->get_items() ) {
				$found = in_array( $item_id, $items );
			}

			return apply_filters( 'learn-press/course-has-item', $found, $item_id, $this->get_id() );
		}

		/**
		 * Get course's item (lesson/quiz/etc...).
		 *
		 * @param int $item_id
		 *
		 * @return LP_Lesson|LP_Quiz
		 */
		public function get_item( $item_id ) {
			$item = false;
			if ( $this->has_item( $item_id ) ) {
				$item = LP_Course_Item::get_item( $item_id );
			}

			return apply_filters( 'learn-press/course-item', $item, $item_id, $this->get_id() );
		}

		/**
		 * Get course passing condition value.
		 *
		 * @param bool   $format
		 * @param string $context
		 *
		 * @return array|mixed|string
		 */
		public function get_passing_condition( $format = false, $context = '' ) {
			$value = $this->get_data( 'passing_condition' );
			if ( $format ) {
				$value = "{$value}%";
			}

			return 'edit' === $context ? $value : apply_filters( 'learn-press/course-passing-condition', $value, $this->get_id() );
		}

		/**
		 * @param $item_id
		 */
		public function can_view_item( $item_id ) {
			switch ( get_post_type() ) {
				case LP_QUIZ_CPT:
			}
		}

		/**
		 * Fetch all links of course's items into cache.
		 * Item Link = Course Permalink + SLUG + Item Slug
		 *
		 * @since 3.0.0
		 */
		public function get_item_links() {

			if ( false === ( $item_links = wp_cache_get( 'course-' . $this->get_id(), 'course-item-links' ) ) ) {

				if ( $items = $this->get_items() ) {

					$permalink    = trailingslashit( $this->get_permalink() );
					$post_types   = get_post_types( null, 'objects' );
					$has_query    = strpos( $permalink, '?' ) !== false;
					$parts        = explode( '?', $permalink );
					$is_permalink = '' !== get_option( 'permalink_structure' );
					$is_draft     = 'draft' === get_post_status( $this->get_id() );

					$custom_prefixes = array(
						LP_QUIZ_CPT   => LP()->settings->get( 'quiz_slug' ),
						LP_LESSON_CPT => LP()->settings->get( 'lesson_slug' )
					);

					if ( empty( $custom_prefixes[ LP_QUIZ_CPT ] ) ) {
						$custom_prefixes[ LP_QUIZ_CPT ] = $post_types[ LP_QUIZ_CPT ]->rewrite['slug'];
					}

					if ( empty( $custom_prefixes[ LP_LESSON_CPT ] ) ) {
						$custom_prefixes[ LP_LESSON_CPT ] = $post_types[ LP_LESSON_CPT ]->rewrite['slug'];
					}

					$custom_prefixes = array_map( 'sanitize_title_with_dashes', $custom_prefixes );
					foreach ( $custom_prefixes as $type => $custom_prefix ) {
						$custom_prefix            = sanitize_title_with_dashes( $custom_prefix );
						$custom_prefixes[ $type ] = preg_replace( '!^/!', '', trailingslashit( $custom_prefix ) );
					}

					$custom_prefixes = apply_filters( 'learn-press/course/custom-item-prefixes', $custom_prefixes, $this->get_id() );

					$slugs = apply_filters( 'learn-press/course/custom-item-slugs',
						array(
							LP_LESSON_CPT => 'lesson',
							LP_QUIZ_CPT   => 'quiz'
						)
					);

					foreach ( $items as $item_id ) {
						$item_permalink = $permalink;
						$item_type      = get_post_type( $item_id );
						if ( ! empty( $slugs[ $item_type ] ) ) {
							$post_name = get_post_field( 'post_name', $item_id );
							$prefix    = $custom_prefixes[ $item_type ];

							if ( $is_permalink && ! $is_draft ) {
								if ( $has_query ) {
									$item_permalink = $parts[0] . $prefix . $post_name . '?' . $parts[1];
								} else {
									$item_permalink .= $prefix . $post_name;
								}

							} else {
								$item_permalink = add_query_arg( array( $slugs[ $item_type ] => $post_name ), $permalink );
							}

							$item_permalink = $has_query ? untrailingslashit( $item_permalink ) : trailingslashit( $item_permalink );
						}
						$item_links[ $item_id ] = $item_permalink;
					}
				}

				wp_cache_set( 'course-' . $this->get_id(), $item_links, 'course-item-links' );
			}

			return $item_links;
		}

		/**
		 * @param int $item_id
		 *
		 * @return string
		 */
		public function get_item_link( $item_id ) {
			LP_Debug::log_function( __CLASS__ . '::' . __FUNCTION__ );
			$item_link = '';
			if ( false !== ( $item_links = $this->get_item_links() ) ) {
				if ( ! empty( $item_links[ $item_id ] ) ) {
					$item_link = $item_links[ $item_id ];
				}
			}
			LP_Debug::log_function( __CLASS__ . '::' . __FUNCTION__ );

			return apply_filters( 'learn-press/course/item-link', $item_link, $item_id, $this );
		}

		/**
		 * Get course's item at a position.
		 *
		 * @param int $at
		 *
		 * @return bool|mixed
		 */
		public function get_item_at( $at ) {
			if ( ! $items = $this->get_items() ) {
				return false;
			}

			return ! empty( $items[ $at ] ) ? $items[ $at ] : false;
		}

		/**
		 * Get position of an item in course curriculum.
		 *
		 * @param LP_Course_Item|LP_User_Item|int $item
		 *
		 * @return mixed
		 */
		public function get_item_position( $item ) {
			if ( ! $items = $this->get_items() ) {
				return false;
			}
			$item_id = is_a( $item, 'LP_User_Item' ) || is_a( $item, 'LP_Course_Item' ) ? $item->get_id() : absint( $item );

			return array_search( $item_id, $items );
		}

		/**
		 * @return bool|mixed
		 */
		public function get_current_item() {
			return $this->is_viewing_item();
		}

		/**
		 * Get item next to current item in course curriculum.
		 *
		 * @param null $args
		 *
		 * @return int|bool
		 */
		public function get_next_item( $args = null ) {
			LP_Debug::log_function( __CLASS__ . '::' . __FUNCTION__ );

			$current = $this->get_current_item();
			$items   = $this->get_items();
			$next    = false;
			if ( $count = sizeof( $items ) ) {
				if ( $current === false ) {
					$next = $items[0];
				} else {
					$current_position = $this->get_item_position( $current );
					if ( $current_position < $count - 1 ) {
						$current_position ++;
						$next = $items[ $current_position ];
					}
				}
			}
			LP_Debug::log_function( __CLASS__ . '::' . __FUNCTION__ );

			return apply_filters( 'learn-press/course/next-item', $next, $this->get_id() );
		}

		/**
		 * Get item prev back current item in course curriculum.
		 *
		 * @param null $args
		 *
		 * @return int|bool
		 */
		public function get_prev_item( $args = null ) {
			$current = $this->get_current_item();
			$items   = $this->get_items();
			$prev    = false;
			if ( $count = sizeof( $items ) ) {
				if ( $current !== false ) {
					$current_position = $this->get_item_position( $current );
					if ( $current_position > 0 ) {
						$current_position --;
						$prev = $items[ $current_position ];
					}
				}
			}

			return apply_filters( 'learn-press/course/prev-item', $prev, $this->get_id() );
		}

		/**
		 * Calculate course results for user by course results settings
		 *
		 * @param int     $user_id
		 * @param boolean $force
		 *
		 * @return mixed
		 */
		public function evaluate_course_results( $user_id = 0, $force = false ) {
			LP_Debug::log_function( __CLASS__ . '::' . __FUNCTION__ );

			if ( ! $user_id ) {
				$user_id = get_current_user_id();
			}

			if ( $user = learn_press_get_user( $user_id ) ) {
				$user_course = $user->get_course_data( $this->get_id() );
			}

			$result = isset( $user_course ) ? $user_course->get_results( 'result' ) : 0;
			LP_Debug::log_function( __CLASS__ . '::' . __FUNCTION__ );

			return $result;
		}


		public function enable_evaluate_item( $item_id, $user_id = 0 ) {
			if ( ! $user_id ) {
				$user_id = get_current_user_id();
			}

			return apply_filters( 'learn_press_enable_evaluate_course_item', true, $item_id, $user_id, $this->get_id() );
		}

		public function is_evaluation( $thing ) {
			return $this->get_data( 'course_result' ) == $thing;
		}

		/**
		 * Get number of lessons user has completed
		 *
		 * @param        $user_id
		 * @param bool   $force
		 * @param string $type
		 *
		 * @return int|bool
		 */
		public function get_completed_items( $user_id = 0, $force = false, $type = '' ) {

			if ( ! $user_id ) {
				$user_id = get_current_user_id();
			}

			$user = learn_press_get_user( $user_id );

			$items = $user ? $user->get_completed_items( $this->get_id() ) : false;

			return apply_filters( 'learn-press/user-completed-items', $items, $user_id, $this->get_id() );
		}

		/**
		 * @param int  $user_id
		 * @param bool $force
		 *
		 * @return mixed
		 */
		public function count_completed_items( $user_id = 0, $force = false, $type = '' ) {
			$items = $this->get_completed_items( $user_id, $force, $type );
			$count = 0;
			if ( $items ) {
				$count = sizeof( $items );
			}

			return apply_filters( 'learn_press_count_user_completed_items', $count, $this->get_id(), $user_id );
		}

		/**
		 * Count all items in a course.
		 *
		 * @param string|array $type    - Optional. Filter item by it's post-type, e.g: lp_lesson
		 * @param bool         $preview - Optional. False to exclude if item is preview
		 *
		 * @return int
		 */
		public function count_items( $type = '', $preview = true ) {
			if ( $items = $this->get_items( $type, $preview ) ) {
				return sizeof( $items );
			}

			return 0;
		}

		/**
		 * Check a quiz is a final quiz in this course
		 *
		 * @param $quiz_id
		 *
		 * @return mixed
		 */
		public function is_final_quiz( $quiz_id ) {
			return apply_filters( 'learn_press_is_final_quiz', $this->final_quiz == $quiz_id, $quiz_id, $this->get_id() );
		}

		public function get_final_quiz() {
			$final_quiz = $this->get_data( 'final_quiz' );

			return apply_filters( 'learn-press/course-final-quiz', $final_quiz, $this->get_id() );
		}

		public function set_final_quiz( $id ) {
			$this->_set_data( 'final_quiz', $id );
		}

		/**
		 * Get content of course item
		 *
		 * @param $item_id
		 *
		 * @return string
		 */
		public function get_item_content( $item_id ) {
			global $post;
			$post = get_post( $item_id );

			// setup global post to apply all filters hook to content
			setup_postdata( $post );

			// do shortcode
			$content = do_shortcode( get_the_content() );

			// restore post content
			wp_reset_postdata();

			return $content;
		}

		/**
		 * Get course duration in seconds
		 *
		 * @return int
		 */
		public function get_duration() {
			/**
			 * Duration is in string such as 10 week, 4 hour, etc...
			 * So we can use strtotime('+10 week') to convert it to seconds
			 */
			return strtotime( "+" . $this->get_data( 'duration' ), 0 );
		}


		/**
		 * Get course remaining time message
		 *
		 * @param $user_id
		 *
		 * @return string
		 */
		public function get_user_duration_html( $user_id = 0 ) {
			if ( ! $user_id ) {
				$user_id = get_current_user_id();
			}
			$duration    = $this->get_duration();
			$user        = learn_press_get_user( $user_id );
			$course_info = $user->get_course_info( $this->get_id() );
			$html        = '';
			if ( $course_info ) {
				$now        = current_time( 'timestamp' );
				$start_time = intval( strtotime( $course_info['start'] ) );
				if ( $start_time + $duration > $now ) {
					$remain = $start_time + $duration - $now;
					$remain = learn_press_seconds_to_weeks( $remain );
					$html   = sprintf( __( 'This course will end within %s next', 'learnpress' ), $remain );
				}
			}

			return $html;
		}

		/**
		 * Get expired time of this course if user has enrolled
		 *
		 * @param int $user_id
		 * @param     mixed
		 *
		 * @return mixed
		 */
		public function get_user_expired_time( $user_id = 0, $args = array() ) {

			if ( ! $user_id ) {
				$user_id = get_current_user_id();
			}
			$duration    = $this->get_duration();
			$user        = learn_press_get_user( $user_id );
			$course_info = $user->get_course_info( $this->get_id() );
			$start_time  = array_key_exists( 'start_time', $args ) ? $args['start_time'] : intval( strtotime( $course_info['start'] ) );
			if ( $duration == 0 ) {
				$duration = DAY_IN_SECONDS * 365 * 100;
			}
			$expired = $start_time + $duration;

			return apply_filters( 'learn_press_user_course_expired_time', $expired, $user_id, $this->get_id() );
		}

		/**
		 * Checks if this course has expired
		 *
		 * @param int $user_id
		 * @param     mixed
		 *
		 * @return mixed
		 */
		public function is_expired( $user_id = 0, $args = array() ) {
			settype( $args, 'array' );
			if ( ! $user_id ) {
				$user_id = get_current_user_id();
			}

			return apply_filters( 'learn_press_user_course_expired', $this->get_user_expired_time( $user_id, $args ) - current_time( 'timestamp' ) );
		}

		/**
		 * Output params for single course page
		 *
		 * @param null $args
		 *
		 * @return mixed
		 */
		public function output_args( $args = null ) {
			return array();
		}

		/**
		 * Get external link of "Buy this course" button
		 *
		 * @return mixed
		 */
		public function get_external_link() {
			return apply_filters( 'learn-press/course-external-link', $this->get_data( 'external_link' ), $this->get_id() );
		}

		public function get_external_link_text() {
			return apply_filters( 'learn-press/course-external-link-text', __( 'Buy this course', 'learnpress' ), $this->get_id() );
		}

		/**
		 * @return bool|string
		 */
		public function get_video_embed() {
			$video_id   = $this->video_id;
			$video_type = $this->video_type;

			if ( ! $video_id || ! $video_type ) {
				return false;
			}

			$embed  = '';
			$height = $this->video_embed_height;
			$width  = $this->video_embed_width;

			if ( 'youtube' === $video_type ) {
				$embed = '<iframe width="' . $width . '" height="' . $height . '" '
				         . 'src="https://www.youtube.com/embed/' . $video_id . '" '
				         . 'frameborder="0" allowfullscreen></iframe>';

			} elseif ( 'vimeo' === $video_type ) {
				$embed = '<iframe width="' . $width . '" height="' . $height . '" '
				         . ' src="https://player.vimeo.com/video/' . $video_id . '" '
				         . 'frameborder="0" webkitallowfullscreen mozallowfullscreen allowfullscreen></iframe>';
			}

			return $embed;
		}

		/**
		 * @return int
		 */
		public function get_retake_count() {
			return absint( $this->get_data( 'retake_count' ) );
		}

		/**
		 * @return LP_User|mixed
		 */
		public function get_author() {
			return learn_press_get_user( get_post_field( 'post_author', $this->get_id() ) );
		}

		/**
		 * @return mixed
		 */
		public function get_tags() {
			return apply_filters( 'learn-press/course-tags', get_the_term_list( $this->get_id(), 'course_tag', __( 'Tags: ', 'learnpress' ), ', ', '' ) );
		}
	}
}