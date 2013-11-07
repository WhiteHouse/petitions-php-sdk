<?php

/**
 * @file
 * Provides SDK for connecting to Petitions API resources.
 */

class PetitionsApiConnector {
  protected $apiHost = NULL;
  protected $apiKey = NULL;

  /**
   * Class Constructor.
   */
  public function __construct($base, $key) {
    $this->apiKey = $key;
    $this->apiHost = $base;

    // This URL is used to test the connection.
    $test_url = "petitions.json";
    $this->runCurl($test_url);
  }

  /**
   * General cURL request function for GET and POST.
   *
   * @param string $url
   *   URL to be requested.
   *
   * @param array $get_vals
   *   (optional) Array of GET values. Defaults to an empty array.
   *
   * @param array $post_vals
   *   (optional) String to be sent with POST request. Defauls to NULL.
   *
   * @return object
   *   The decoded JSON object.
   */
  protected function runCurl($url, $get_vals = array(), $post_vals = NULL) {

    // Prepend apiHost URL.
    $url = $this->apiHost . '/' . $url;

    // Add $_GET params.
    if ($this->apiKey) {
      $get_vals = array_merge($get_vals, array('api_key' => $this->apiKey));
    }
    $get_string = $this->buildQueryString($get_vals);
    $url .= (strpos($url, '?') !== FALSE ? '&' : '?') . $get_string;
    $ch = curl_init($url);

    $options = array(
      CURLOPT_RETURNTRANSFER => TRUE,
      CURLOPT_SSL_VERIFYPEER => FALSE,
      CURLOPT_TIMEOUT => 3,
    );

    if ($post_vals != NULL) {
      $post_string = json_encode($post_vals);
      $options[CURLOPT_CUSTOMREQUEST] = "POST";
      $options[CURLOPT_POSTFIELDS] = $post_string;
      $options[CURLOPT_HTTPHEADER] = array(
        'Content-Type: application/json',
        'Content-Length: ' . strlen($post_string),
      );
    }

    curl_setopt_array($ch, $options);
    $response = json_decode(curl_exec($ch));
    curl_close($ch);

    // This can be overridden so that exceptions are caught in a child class.
    $this->verifyResponse($response, $url);

    return $response;
  }

  /**
   * Verifies that a response was successful.
   *
   * Overriding this method provides an opportunity for custom error logging.
   *
   * @param object $response
   *   The response obtained from the API.
   *
   * @param string $url
   *   The URL of the curl request.
   *
   * @throws Exception
   */
  protected function verifyResponse(&$response, $url) {
    if (empty($response->metadata->responseInfo->status)) {
      $e = new Exception("Could not connect to Petitions API.");
      $e->response = $response;
      $e->requestUrl = $url;

      throw $e;
    }
    elseif ($response->metadata->responseInfo->status != 200) {
      $developer_message = $response->metadata->responseInfo->developerMessage;
      $e = new Exception("Petitions API returned an Error code: " . $developer_message);
      $e->response = $response;
      $e->requestUrl = $url;

      throw $e;
    }
  }

  /**
   * Parses an array into a valid, rawurlencoded query string.
   *
   * This differs from http_build_query() as we need to rawurlencode() (instead
   * of urlencode()) all query parameters.
   *
   * @param array $query
   *   The query parameter array to be processed, e.g. $_GET.
   *
   * @param string $parent
   *   (optional) Internal use only. Used to build the $query array key for
   *   nested items. Defaults to empty string.
   *
   * @return string
   *   A rawurlencoded string which can be used as or appended to the URL query
   *   string.
   *
   * @see drupal_http_build_query()
   */
  public function buildQueryString(array $query, $parent = '') {
    $params = array();

    foreach ($query as $key => $value) {
      $key = ($parent ? $parent . '[' . rawurlencode($key) . ']' : rawurlencode($key));

      // Recurse into children.
      if (is_array($value)) {
        $params[] = $this->buildQueryString($value, $key);
      }
      // If a query parameter value is NULL, only append its key.
      elseif (!isset($value)) {
        $params[] = $key;
      }
      else {
        // For better readability of paths in query strings, we decode slashes.
        $params[] = $key . '=' . str_replace('%2F', '/', rawurlencode($value));
      }
    }

    return implode('&', $params);
  }

  /**
   * Fetches a list of petitions.
   *
   * @param int $limit
   *   (optional) The maximum number of results to return. Defaults to 10.
   *
   * @param int $offset
   *   (optional) The offset of the resultset to return. Defaults to 0.
   *
   * @param array $parameters
   *   (optional) An associative array of $_GET parameters to be appended to the
   *   request. Defaults to an empty array.
   *
   * @return object
   *   The JSON response.
   */
  public function getPetitions($limit = 10, $offset = 0, $parameters = array()) {
    $resource = 'petitions.json';

    $get_vals = array(
      'limit' => $limit,
      'offset' => $offset,
    );
    $get_vals += $parameters;

    return $this->runCurl($resource, $get_vals);
  }

  /**
   * Fetches a specific petition.
   *
   * @param string $petition_id
   *   The ID of the petition to fetch.
   *
   * @param boolean $mock
   *   Indicate whether returned data should be mock data (not real).
   *
   * @return object
   *   The JSON response.
   */
  public function getPetition($petition_id, $mock = FALSE) {
    $resource = 'petitions/' . $petition_id . '.json';
    $get_vals = array();

    if ($mock) {
      $get_vals['mock'] = '1';
    }

    return $this->runCurl($resource, $get_vals);
  }

  /**
   * Fetches a list of signatures for a specific petition.
   *
   * @param string $petition_id
   *   The ID of the petition to fetch.
   *
   * @param int $limit
   *   (optional) The maximum number of results to return. Defaults to 10.
   *
   * @param int $offset
   *   (optional) The offset of the resultset to return. Defaults to 0.
   *
   * @param array $parameters
   *   (optional) An associative array of $_GET parameters to be appended to the
   *   request. Defaults to empty array.
   *
   * @return object
   *   The JSON response.
   *
   * @see https://petitions.whitehouse.gov/developers
   */
  public function getSignatures($petition_id, $limit = 10, $offset = 0, $parameters = array()) {
    $resource = 'petitions/' . $petition_id . '/signatures.json';

    $get_vals = array(
      'limit' => $limit,
      'offset' => $offset,
    );
    $get_vals += $parameters;

    return $this->runCurl($resource, $get_vals);
  }

  /**
   * Send a signature to Petitiosn API.
   *
   * @param array $signature
   *   An associative array. Verify that correct signature keys
   *   are included, as per development documentation.
   *
   * @return object
   *   The JSON response.
   *
   * @see https://petitions.whitehouse.gov/developers
   */
  public function sendSignature($signature) {
    $resource = 'signatures.json';

    return $this->runCurl($resource, array(), $signature);
  }

  /**
   * Fetches validated signatures.
   *
   * @param string $petition_id
   *   (optional) The id of the petition for which validations will be fetched.
   *   Defaults to NULL.
   *
   * @param int $limit
   *   (optional) The maximum number of results to return. Defaults to 10.
   *
   * @param int $offset
   *   (optional) The offset of the resultset to return. Defaults to 0.
   *
   * @return object
   *   The JSON response.
   */
  public function getValidations($petition_id = NULL, $limit = 10, $offset = 0) {

    $get_vals = array(
      'key' => $this->apiKey,
      'limit' => $limit,
      'offset' => $offset,
    );
    if ($petition_id) {
      $get_vals['petition_id'] = $petition_id;
    }

    $resource = 'validations.json';

    return $this->runCurl($resource, $get_vals);
  }
}
