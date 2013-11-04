<?php

/**
 * @file
 * Provides SDK for connecting to Petitions API resources.
 */
class PetitionsApi {
  protected $apiHost = NULL;
  protected $apiKey = NULL;

  /**
   * Class Constructor
   */
  public function __construct($base, $key){
    $this->apiKey = $key;
    $this->apiHost = $base;

    // This URL is used to test the connection.
    $testUrl = "petitions.json";
    $this->runCurl($testUrl);
  }

  /**
   * cURL request
   *
   * General cURL request function for GET and POST
   *
   * @param string $url
   *   URL to be requested
   *
   * @param array $getVals
   *   Array of GET values.
   *
   * @param array $postVals
   *   String to be sent with POST request.
   *
   * @return object
   *   The decoded JSON object.
   */
  private function runCurl($url, $getVals = array(), $postVals = NULL) {

    // Prepend apiHost URL.
    $url = $this->apiHost . '/' . $url;

    // Add $_GET params.
    if ($this->apiKey) {
      $getVals = array_merge($getVals, array('api_key' => $this->apiKey));
    }
    $getString = $this->buildQueryString($getVals);
    $url .= (strpos($url, '?') !== FALSE ? '&' : '?') . $getString;
    $ch = curl_init($url);

    $options = array(
      CURLOPT_RETURNTRANSFER => TRUE,
      // CURLOPT_COOKIE => "key=value",
      CURLOPT_SSL_VERIFYPEER => FALSE,
      CURLOPT_TIMEOUT => 3
    );

    if ($postVals != NULL){
      $postString = json_encode($postVals);
      $options[CURLOPT_CUSTOMREQUEST] = "POST";
      $options[CURLOPT_POSTFIELDS] = $postString;
      $options[CURLOPT_HTTPHEADER] = array(
        'Content-Type: application/json',
        'Content-Length: ' . strlen($postString),
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
   *
   * @param string $url
   *
   * @throws Exception
   */
  protected function verifyResponse($response, $url) {
    if (empty($response->metadata->responseInfo->status)) {
      $e = new Exception("Could not connect to Petitions API.");
      $e->response = $response;
      $e->requestUrl = $url;

      throw $e;
    }
    elseif ($response->metadata->responseInfo->status != 200) {
      $errorCode = $response->metadata->responseInfo->errorCode;
      $developerMessage = $response->metadata->responseInfo->developerMessage;
      $e = new Exception("Petitions API returned an Error code: " . $developerMessage);
      $e->response = $response;
      $e->requestUrl = $url;

      throw $e;
    }
  }

  /**
   * Parses an array into a valid, rawurlencoded query string.
   *
   * This differs from http_build_query() as we need to rawurlencode() (instead of
   * urlencode()) all query parameters.
   *
   * @param array $query
   *   The query parameter array to be processed, e.g. $_GET.
   *
   * @param string $parent
   *   Internal use only. Used to build the $query array key for nested items.
   *
   * @return string
   *   A rawurlencoded string which can be used as or appended to the URL query
   *   string.
   *
   * @see drupal_http_build_query().
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
   *   The size of the resultset to return.
   *
   * @param int $offset
   *   The offset of the resultset to return.
   *
   * @param array $parameters
   *   An associative array of $_GET parameters to be appended to the request.
   *
   * @return object
   *   The JSON response.
   */
  public function getPetitions($limit = 10, $offset = 0, $parameters = array()) {
    $resource = 'petitions.json';

    $getVals = array(
      'limit' => $limit,
      'offset' => $offset,
    );
    $getVals += $parameters;

    return $this->runCurl($resource, $getVals);
  }

  /**
   * Fetches a specific petition.
   *
   * @param string $petitionId
   *   The ID of the petition to fetch.
   *
   * @param boolean $mock
   *   Indicate whether returned data should be mock data (not real).
   *
   * @return object
   *   The JSON response.
   */
  public function getPetition($petitionId, $mock = FALSE) {
    $resource = 'petitions/' . $petitionId . '.json';
    $getVals = array();

    if ($mock) {
      $getVals['mock'] = '1';
    }

    return $this->runCurl($resource, $getVals);
  }

  /**
   * Fetches a list of signatures for a specific petition.
   *
   * @param string $petitionId
   *   The ID of the petition to fetch.
   *
   * @param int $limit
   *   The size of the resultset to return.
   *
   * @param int $offset
   *   The offset of the resultset to return.
   *
   * @param array $parameters
   *   An associative array of $_GET parameters to be appended to the request.
   *
   * @return object
   *   The JSON response.
   *
   * @see https://petitions.whitehouse.gov/developers
   */
  public function getSignatures($petitionId, $limit = 10, $offset = 0, $parameters = array()) {
    $resource = 'petitions/' . $petitionId . '/signatures.json';

    $getVals = array(
      'limit' => $limit,
      'offset' => $offset,
    );
    $getVals += $parameters;

    return $this->runCurl($resource, $getVals);
  }

  /**
   * Send signatures to We the People.
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
   * @param string $petitionId
   *   (optional) The id of the petition for which validations will be fetched.
   *
   * @param int $limit
   *   The size of the resultset to return.
   *
   * @param int $offset
   *   The offset of the resultset to return.
   *
   * @return object
   *   The JSON response.
   */
  public function getValidations($petitionId = NULL, $limit = 10, $offset = 0) {

    $getVals = array(
      'key' => $this->apiKey,
      'limit' => $limit,
      'offset' => $offset,
    );
    if ($petitionId) {
      $getVals['petition_id'] = $petitionId;
    }



    $resource = 'validations.json';

    return $this->runCurl($resource, $getVals);
  }
}
