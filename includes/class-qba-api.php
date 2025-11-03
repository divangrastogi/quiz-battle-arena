<?php
/**
 * REST API Endpoints Class
 *
 * Handles all REST API endpoints for the plugin
 *
 * @package QuizBattleArena
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * QBA_API Class
 *
 * @since 1.0.0
 */
class QBA_API {

	/**
	 * REST API namespace
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private $namespace = 'quiz-battle-arena/v1';

	/**
	 * Initialize the API
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register REST API routes
	 *
	 * @since 1.0.0
	 */
	public function register_routes() {
		// Battle endpoints
		register_rest_route(
			$this->namespace,
			'/battles',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'create_battle' ),
				'permission_callback' => array( $this, 'check_user_logged_in' ),
				'args'                => $this->get_battle_creation_args(),
			)
		);

		register_rest_route(
			$this->namespace,
			'/battles/(?P<id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_battle' ),
				'permission_callback' => array( $this, 'check_battle_access' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/battles/(?P<id>\d+)/accept',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'accept_battle' ),
				'permission_callback' => array( $this, 'check_battle_participant' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/battles/(?P<id>\d+)/decline',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'decline_battle' ),
				'permission_callback' => array( $this, 'check_battle_participant' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/battles/(?P<id>\d+)/submit-answer',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'submit_answer' ),
				'permission_callback' => array( $this, 'check_battle_participant' ),
				'args'                => $this->get_answer_submission_args(),
			)
		);

		// Queue endpoints
		register_rest_route(
			$this->namespace,
			'/queue/join',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'join_queue' ),
				'permission_callback' => array( $this, 'check_user_logged_in' ),
				'args'                => $this->get_queue_join_args(),
			)
		);

		register_rest_route(
			$this->namespace,
			'/queue/leave',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'leave_queue' ),
				'permission_callback' => array( $this, 'check_user_logged_in' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/queue/status',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_queue_status' ),
				'permission_callback' => array( $this, 'check_user_logged_in' ),
			)
		);

		// Leaderboard endpoints
		register_rest_route(
			$this->namespace,
			'/leaderboard/(?P<type>[\w-]+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_leaderboard' ),
				'permission_callback' => '__return_true',
				'args'                => $this->get_leaderboard_args(),
			)
		);

		// User stats endpoints
		register_rest_route(
			$this->namespace,
			'/users/(?P<id>\d+)/stats',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_user_stats' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			$this->namespace,
			'/users/(?P<id>\d+)/badges',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_user_badges' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			$this->namespace,
			'/users/(?P<id>\d+)/battles',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_user_battles' ),
				'permission_callback' => '__return_true',
				'args'                => $this->get_pagination_args(),
			)
		);
	}

	/**
	 * Create new battle
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_battle( $request ) {
		$quiz_id        = $request->get_param( 'quiz_id' );
		$opponent_id    = $request->get_param( 'opponent_id' );
		$challenge_type = $request->get_param( 'challenge_type' ) ?: 'direct';

		$battle_id = qba_create_battle_challenge(
			$quiz_id,
			get_current_user_id(),
			$opponent_id,
			$challenge_type
		);

		if ( is_wp_error( $battle_id ) ) {
			return $battle_id;
		}

		return new WP_REST_Response(
			array(
				'battle_id' => $battle_id,
				'status'    => 'pending',
				'message'   => __( 'Battle challenge created successfully', 'quiz-battle-arena' ),
			),
			201
		);
	}

	/**
	 * Get battle details
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_battle( $request ) {
		$battle_id = $request->get_param( 'id' );
		$battle    = qba_get_battle( $battle_id );

		if ( ! $battle ) {
			return new WP_Error( 'battle_not_found', __( 'Battle not found', 'quiz-battle-arena' ), array( 'status' => 404 ) );
		}

		return new WP_REST_Response( qba_sanitize_battle_for_json( $battle ), 200 );
	}

	/**
	 * Accept battle challenge
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function accept_battle( $request ) {
		$battle_id = $request->get_param( 'id' );

		$result = qba_accept_battle_challenge( $battle_id, get_current_user_id() );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new WP_REST_Response(
			array(
				'message'    => __( 'Battle challenge accepted', 'quiz-battle-arena' ),
				'battle_url' => qba_get_battle_url( $battle_id ),
			),
			200
		);
	}

	/**
	 * Decline battle challenge
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function decline_battle( $request ) {
		$battle_id = $request->get_param( 'id' );

		$result = qba_decline_battle_challenge( $battle_id, get_current_user_id() );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new WP_REST_Response(
			array(
				'message' => __( 'Battle challenge declined', 'quiz-battle-arena' ),
			),
			200
		);
	}

	/**
	 * Submit answer
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function submit_answer( $request ) {
		$battle_id   = $request->get_param( 'battle_id' );
		$question_id = $request->get_param( 'question_id' );
		$answer      = $request->get_param( 'answer' );
		$time_taken  = $request->get_param( 'time_taken' );

		$result = qba_score_battle_answer( $battle_id, $question_id, $answer, $time_taken );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new WP_REST_Response( $result, 200 );
	}

	/**
	 * Join matchmaking queue
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function join_queue( $request ) {
		$quiz_id    = $request->get_param( 'quiz_id' );
		$queue_type = $request->get_param( 'queue_type' ) ?: 'random';

		$queue_id = qba_join_matchmaking_queue( get_current_user_id(), $quiz_id, $queue_type );

		if ( is_wp_error( $queue_id ) ) {
			return $queue_id;
		}

		return new WP_REST_Response(
			array(
				'queue_id' => $queue_id,
				'message'  => __( 'Joined matchmaking queue', 'quiz-battle-arena' ),
			),
			200
		);
	}

	/**
	 * Leave matchmaking queue
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function leave_queue( $request ) {
		$result = qba_leave_matchmaking_queue( get_current_user_id() );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new WP_REST_Response(
			array(
				'message' => __( 'Left matchmaking queue', 'quiz-battle-arena' ),
			),
			200
		);
	}

	/**
	 * Get queue status
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_queue_status( $request ) {
		$status = qba_get_queue_status( get_current_user_id() );

		return new WP_REST_Response( $status, 200 );
	}

	/**
	 * Get leaderboard
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_leaderboard( $request ) {
		$type   = $request->get_param( 'type' );
		$limit  = $request->get_param( 'limit' ) ?: 50;
		$offset = $request->get_param( 'offset' ) ?: 0;

		$leaderboard = qba_get_leaderboard(
			$type,
			array(
				'limit'  => $limit,
				'offset' => $offset,
			)
		);

		return new WP_REST_Response( $leaderboard, 200 );
	}

	/**
	 * Get user stats
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_user_stats( $request ) {
		$user_id = $request->get_param( 'id' );
		$stats   = qba_get_user_stats( $user_id );

		return new WP_REST_Response( $stats, 200 );
	}

	/**
	 * Get user badges
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_user_badges( $request ) {
		$user_id = $request->get_param( 'id' );
		$badges  = qba_get_user_badges( $user_id );

		return new WP_REST_Response( $badges, 200 );
	}

	/**
	 * Get user battles
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_user_battles( $request ) {
		$user_id  = $request->get_param( 'id' );
		$page     = $request->get_param( 'page' ) ?: 1;
		$per_page = $request->get_param( 'per_page' ) ?: 20;

		$battles = qba_get_user_battles(
			$user_id,
			array(
				'page'     => $page,
				'per_page' => $per_page,
			)
		);

		return new WP_REST_Response( $battles, 200 );
	}

	/**
	 * Permission callback: Check if user is logged in
	 *
	 * @since 1.0.0
	 * @return bool
	 */
	public function check_user_logged_in() {
		return is_user_logged_in();
	}

	/**
	 * Permission callback: Check if user can access battle
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request
	 * @return bool
	 */
	public function check_battle_access( $request ) {
		if ( ! is_user_logged_in() ) {
			return false;
		}

		$battle_id = $request->get_param( 'id' );
		$battle    = qba_get_battle( $battle_id );

		if ( ! $battle ) {
			return false;
		}

		$user_id = get_current_user_id();
		return $battle['challenger_id'] == $user_id || $battle['opponent_id'] == $user_id;
	}

	/**
	 * Permission callback: Check if user is battle participant
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request
	 * @return bool
	 */
	public function check_battle_participant( $request ) {
		if ( ! is_user_logged_in() ) {
			return false;
		}

		$battle_id = $request->get_param( 'id' );
		$battle    = qba_get_battle( $battle_id );

		if ( ! $battle ) {
			return false;
		}

		$user_id = get_current_user_id();
		return $battle['challenger_id'] == $user_id || $battle['opponent_id'] == $user_id;
	}

	/**
	 * Get battle creation arguments
	 *
	 * @since 1.0.0
	 * @return array
	 */
	private function get_battle_creation_args() {
		return array(
			'quiz_id'        => array(
				'required'          => true,
				'validate_callback' => function ( $value ) {
					return is_numeric( $value ) && $value > 0;
				},
			),
			'opponent_id'    => array(
				'required'          => true,
				'validate_callback' => function ( $value ) {
					return is_numeric( $value ) && $value > 0;
				},
			),
			'challenge_type' => array(
				'required' => false,
				'enum'     => array( 'direct', 'queue' ),
				'default'  => 'direct',
			),
		);
	}

	/**
	 * Get answer submission arguments
	 *
	 * @since 1.0.0
	 * @return array
	 */
	private function get_answer_submission_args() {
		return array(
			'battle_id'   => array(
				'required'          => true,
				'validate_callback' => function ( $value ) {
					return is_numeric( $value ) && $value > 0;
				},
			),
			'question_id' => array(
				'required'          => true,
				'validate_callback' => function ( $value ) {
					return is_numeric( $value ) && $value > 0;
				},
			),
			'answer'      => array(
				'required'          => true,
				'sanitize_callback' => 'sanitize_text_field',
			),
			'time_taken'  => array(
				'required'          => true,
				'validate_callback' => function ( $value ) {
					return is_numeric( $value ) && $value >= 0;
				},
			),
		);
	}

	/**
	 * Get queue join arguments
	 *
	 * @since 1.0.0
	 * @return array
	 */
	private function get_queue_join_args() {
		return array(
			'quiz_id'    => array(
				'required'          => true,
				'validate_callback' => function ( $value ) {
					return is_numeric( $value ) && $value > 0;
				},
			),
			'queue_type' => array(
				'required' => false,
				'enum'     => array( 'random', 'skill' ),
				'default'  => 'random',
			),
		);
	}

	/**
	 * Get leaderboard arguments
	 *
	 * @since 1.0.0
	 * @return array
	 */
	private function get_leaderboard_args() {
		return array(
			'limit'  => array(
				'validate_callback' => function ( $value ) {
					return is_numeric( $value ) && $value > 0 && $value <= 100;
				},
				'default'           => 50,
			),
			'offset' => array(
				'validate_callback' => function ( $value ) {
					return is_numeric( $value ) && $value >= 0;
				},
				'default'           => 0,
			),
		);
	}

	/**
	 * Get pagination arguments
	 *
	 * @since 1.0.0
	 * @return array
	 */
	private function get_pagination_args() {
		return array(
			'page'     => array(
				'validate_callback' => function ( $value ) {
					return is_numeric( $value ) && $value > 0;
				},
				'default'           => 1,
			),
			'per_page' => array(
				'validate_callback' => function ( $value ) {
					return is_numeric( $value ) && $value > 0 && $value <= 100;
				},
				'default'           => 20,
			),
		);
	}
}
