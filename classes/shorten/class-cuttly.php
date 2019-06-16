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
		    $fullQueryURL = self::API_URL . '?key=' . $this->api_key . '&short=' . $link_to_shorten . '&name=' . $data['utmdclink_shorturl'];
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
            var_dump($response, true);

			if ( isset( $response->errors ) ) {
				$this->error_code = 100;
			} else {
				$body = json_decode( $response['body'], true);
				$response_code = intval( $response['response']['code'] );
				if ( 200 === $response_code || 201 === $response_code ) {
				    $urlData = $body['url'];
				    $status = $urlData['status'];
				    if ( 7 == $status) {
                        $response_url = '';

                        if ( isset( $body['shortLink'] ) ) {
                            $response_url = $body['shortLink'];
                        }

                        if ( filter_var( $response_url, FILTER_VALIDATE_URL ) ) {
                            $this->response = esc_url( wp_unslash( $body['shortLink'] ) );
                        }
                    } else {
                        //handle error status codes
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
}
