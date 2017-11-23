<?php

/**
 * Class LP_Lesson_CURD
 *
 * @author  ThimPress
 * @package LearnPress/Classes/CURD
 * @since   3.0.0
 */

/**
 * Prevent loading this file directly
 */
defined( 'ABSPATH' ) || exit();

if ( ! class_exists( 'LP_Lesson_CURD' ) ) {

	class LP_Lesson_CURD extends LP_Object_Data_CURD implements LP_Interface_CURD {

		public function create( &$lesson ) {
			// TODO: Implement create() method.
		}

		public function update( &$lesson ) {
			// TODO: Implement update() method.
		}

		/**
		 * Delete lesson.
		 *
		 * @since 3.0.0
		 *
		 * @param object $lesson_id
		 */
		public function delete( &$lesson_id ) {
			// course curd
			$curd = new LP_Course_CURD();
			// remove lesson from course items
			$curd->remove_item( $lesson_id );
		}

		/**
		 * Duplicate lesson.
		 *
		 * @since 3.0.0
		 *
		 * @param $lesson_id
		 * @param array $args
		 *
		 * @return mixed|WP_Error
		 */
		public function duplicate( &$lesson_id, $args = array() ) {

			if ( ! $lesson_id ) {
				return new WP_Error( __( '<p>Op! ID not found</p>', 'learnpress' ) );
			}

			if ( get_post_type( $lesson_id ) != LP_LESSON_CPT ) {
				return new WP_Error( __( '<p>Op! The lesson does not exist</p>', 'learnpress' ) );
			}

			// ensure that user can create lesson
			if ( ! current_user_can( 'edit_posts' ) ) {
				return new WP_Error( __( '<p>Sorry! You have not permission to duplicate this lesson</p>', 'learnpress' ) );
			}

			// duplicate lesson
			$new_lesson_id = learn_press_duplicate_post( $lesson_id, $args );

			if ( ! $new_lesson_id || is_wp_error( $new_lesson_id ) ) {
				return new WP_Error( __( '<p>Sorry! Duplicate lesson failed!</p>', 'learnpress' ) );
			} else {
				return $new_lesson_id;
			}
		}

		public function load( &$object ) {
			// TODO: Implement load() method.
		}
	}

}