<?php

namespace YourPrefix\WooDynamicCoupons;

class Plugin
{

	protected static ?Plugin $instance = null;
	private const API_PREFIX = 'your-prefix';
	private const API_VERSION = 'v1';
	private const CURRENT_COUPON_OPTION_NAME = 'your_prefix/coupons/current_campaign_coupon';
	private const COUPON_TYPE = 'your-prefix-abandoned-cart';

	/**
	 * Get singleton instance
	 */
	public static function getInstance(): ?Plugin
	{
		if (!self::$instance) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Plugin constructor.
	 *
	 * @since   1.0.0
	 */
	public function __construct()
	{
		add_action('rest_api_init', [ $this, '_registerApiRoutes' ]);
	}

	/**
	 * setup API endpoints
	 */
	public function _registerApiRoutes()
	{
		$routes = [
			/**
			 * /wp-json/your-prefix/v1/new-campaign-coupon
			 */
			(object) [
				'endpoint'            => 'new-campaign-coupon',
				'methods'             => [ 'GET' ],
				'callback'            => [ $this, '_getOrCreateCoupon' ],
				'permission_callback' => '__return_true',
			],

			/**
			 * /wp-json/your-prefix/v1/recent-campaign-coupons
			 */
			(object) [
				'endpoint'            => 'recent-campaign-coupons',
				'methods'             => [ 'GET' ],
				'callback'            => [ $this, '_getRecentCoupons' ],
				'permission_callback' => '__return_true',
			],
		];

		foreach ($routes as $route) {
			register_rest_route(self::API_PREFIX . '/' . self::API_VERSION, $route->endpoint, [
				'methods'             => $route->methods,
				'callback'            => $route->callback,
				'permission_callback' => $route->permission_callback,
			]);
		}
	}


	/**
	 * Get / generate an automated coupon for use in the Klaviyo campaign
	 *
	 * @return array|bool
	 */
	public function _getOrCreateCoupon()
	{
		$current_coupon = get_option(self::CURRENT_COUPON_OPTION_NAME);

		if (!$current_coupon) {
			$current_coupon = $this->cacheCurrentCouponApiResponse($this->createCoupon());
		} else {
			$current_coupon_has_expired = 86400 < (time() - $current_coupon[ 'date_created' ]);

			if ($current_coupon_has_expired || 'publish' !== get_post($current_coupon[ 'id' ])->post_status) {
				$current_coupon = $this->cacheCurrentCouponApiResponse($this->createCoupon());
			}
		}

		return $current_coupon;
	}

	/**
	 * Create new coupon
	 */
	private function createCoupon(): ?\WC_Coupon
	{
		if (!class_exists('\WC_Coupon')) {
			return null;
		}

		$coupon = new \WC_Coupon();
		$coupon->set_code('YOUR-PREFIX-' . rand(10, 1000) . '-' . rand(10, 1000));
		$coupon->set_discount_type('percent');
		$coupon->set_amount(20);
		$coupon->set_description(date('jS F Y') . ' abandoned cart code');
		$coupon->set_individual_use(true);
		$coupon->set_usage_limit_per_user(1);
		$coupon->set_date_expires(strtotime('+7 day', time()));
		$coupon->update_meta_data('_your_prefix_coupon_type', self::COUPON_TYPE); // set an identifier in meta so you can query later in getRecent + if cleanup is implemented

		$coupon->save();

		return $coupon;
	}


	/**
	 * @return array[]|false
	 */
	public function _getRecentCoupons()
	{
		if (!class_exists('\WC_Coupon')) {
			return false;
		}

		$query = new \WP_Query([
			'post_type'      => 'shop_coupon',
			'meta_query'     => [
				[
					'key'   => '_your_prefix_coupon_type',
					'value' => self::COUPON_TYPE,
				],
			],
			'order'          => 'desc',
			'orderby'        => 'date',
			'posts_per_page' => 10,
		]);

		return array_map(function ($coupon_post) {
			return $this->getCouponObjectForApiResponse(new \WC_Coupon($coupon_post->post_name));
		}, $query->posts);
	}

	/**
	 * Cache the API coupon info in the WP options table and return API output
	 */
	private function cacheCurrentCouponApiResponse(\WC_Coupon $coupon)
	{
		update_option(self::CURRENT_COUPON_OPTION_NAME, $this->getCouponObjectForApiResponse($coupon));

		return get_option(self::CURRENT_COUPON_OPTION_NAME);
	}

	/**
	 * @param \WC_Coupon $coupon
	 *
	 * @return array
	 */
	private function getCouponObjectForApiResponse(\WC_Coupon $coupon): array
	{
		return [
			'id'                     => $coupon->get_id(),
			'coupon'                 => $coupon->get_code(),
			'amount'                 => intval($coupon->get_amount()),
			'date_created'           => strtotime($coupon->get_date_created()),
			'date_created_formatted' => date('jS F Y', strtotime($coupon->get_date_created())),
			'date_expires'           => strtotime($coupon->get_date_expires()),
			'date_expires_formatted' => date('jS F Y', strtotime($coupon->get_date_expires())),
		];
	}
}