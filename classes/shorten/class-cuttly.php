<?php
/**
 * Cuttly API shortener class.
 *
 * @package UtmDotCodes
 */

namespace UtmDotCodes;

/**
 * Class Cuttly.
 */
class Cuttly implements \UtmDotCodes\Shorten {

	const API_URL = 'https://cutt.ly/api/api.php';
	const ERROR_CODE_UNIQUE_PREFIX = 'cuttly';

	/**
	 * API credentials for Cutt.ly API.
	 *
	 * @var string|null The API key for the shortener.
	 */
	private $api_key;

	/**
	 * Response from API.
	 *
	 * @var object|null The response object from the shortener.
	 */
	private $response;

	/**
	 * Error message.
	 *
	 * @var object|null Error object with code and message properties.
	 */
	private $error_code;

	/**
	 * Cuttly constructor.
	 *
	 * @param string $api_key Credentials for API.
	 */
	public function __construct( $api_key ) {
		$this->api_key = $api_key;
        $this->add_custom_error_code_filter(1, 'the shortened link comes from the domain that shortens the link, i.e. the link has already been shortened');
        $this->add_custom_error_code_filter(2, 'the entered link is not a link');
        $this->add_custom_error_code_filter(3, 'the preferred link name is already taken');
        $this->add_custom_error_code_filter(4, 'Invalid API key');
        $this->add_custom_error_code_filter(5, 'the link has not passed the validation. Includes invalid characters');
        $this->add_custom_error_code_filter(6, 'The link provided is from a blocked domain');
    }

	/**
	 * See interface for docblock.
	 *
	 * @inheritDoc
	 *
	 * @param array  $data See interface.
	 * @param string $query_string See interface.
	 *
	 * @return void
	 */
	public function shorten( $data, $query_string ) {
		if ( isset( $data['meta_input'] ) ) {
			$data = $data['meta_input'];
		}

		if ( '' !== $this->api_key ) {
		    //assemble the long url with the utm query string
		    $link_to_shorten = $data['utmdclink_url'] . $query_string;
		    //assemble the full url for the GET request to the API endpoint
		    $fullQueryURL = add_query_arg([
		            'key' => $this->api_key,
                    'short' => urlencode($link_to_shorten),
                ], self::API_URL);
			$response = wp_remote_get(
				$fullQueryURL,
				// Selective overrides of WP_Http() defaults.
				[
					'timeout'     => 15,
					'redirection' => 5,
					'httpversion' => '1.1',
					'blocking'    => true,
					'headers'     => [
						'Content-Type'  => 'application/json',
					],
				]
			);
			if ( isset( $response->errors ) ) {
				$this->error_code = 100;
			} else {
				$body = json_decode( $response['body'], true);
				$response_code = intval( $response['response']['code'] );
				if ( 200 === $response_code || 201 === $response_code ) {
				    $urlData = $body['url'];
				    $status = (int) $urlData['status'];
				    if ( $status === 7) {

				        //if the status is 7 then the link has been shortened (according to API docs: https://cutt.ly/cuttly-api)
                        $response_url = '';

                        if ( isset( $urlData['shortLink'] ) ) {
                            $response_url = $urlData['shortLink'];
                        }

                        if ( filter_var( $response_url, FILTER_VALIDATE_URL ) ) {
                            $this->response = esc_url( wp_unslash( $urlData['shortLink'] ) );
                        }
                    } else {
				        //otherwise we handle the status codes according to the API docs: https://cutt.ly/cuttly-api
                        $this->set_custom_error_code($status);
                    }
				} elseif ( 403 === $response_code ) {
					$this->error_code = 4030;
				} else {
					$this->error_code = 500;
				}
			}
		}
	}

	/**
	 * Get response from Cutt.ly API for the request.
	 *
	 * @inheritDoc
	 */
	public function get_response() {
		return $this->response;
	}

	/**
	 * Get error code/message returned by Cutt.ly API for the request.
	 *
	 * @inheritDoc
	 */
	public function get_error() {
		return $this->error_code;
	}

    /**
     * sets the error code for a custom shortener error
     * @param $error_code
     */
	private function set_custom_error_code($error_code) {
	    $this->error_code = self::ERROR_CODE_UNIQUE_PREFIX . $error_code;

	}

    /**
     * adds a filter for a custom shortener error code
     * @param $custom_error_code
     * @param $custom_error_message
     * @param string $custom_error_class
     */
	private function add_custom_error_code_filter($custom_error_code, $custom_error_message, $custom_error_class = 'notice-info') {
        add_filter(
            'utmdc_error_message',
            function( $error_message, $error_code ) use ($custom_error_code, $custom_error_message, $custom_error_class) {
                if ( $custom_error_code . self::ERROR_CODE_UNIQUE_PREFIX === $error_code ) {
                    $error_message = [
                        'style'   => $custom_error_class,
                        'message' => $custom_error_message,
                    ];
                }
                return $error_message;
            },
            10,
            2
        );
    }
}
